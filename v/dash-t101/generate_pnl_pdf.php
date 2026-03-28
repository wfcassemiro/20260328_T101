<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';
require_once __DIR__ . '/../config/dash_functions.php';

// Carrega o TCPDF
require_once(__DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php');

if (!isLoggedIn()) {
    die("Acesso negado.");
}

$user_id = $_SESSION['user_id'];
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// --- Lógica de busca de dados (Incluindo IMPOSTOS) ---

// Buscar taxas de câmbio para conversão
$rates = ['BRL' => 1.0];
try {
    $stmt_rates = $pdo->prepare("SELECT setting_key, setting_value FROM dash_settings WHERE user_id = ? AND setting_key LIKE 'rate_%'");
    $stmt_rates->execute([$user_id]);
    foreach ($stmt_rates->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $code = strtoupper(str_replace('rate_', '', $row['setting_key']));
        if((float)$row['setting_value'] > 0) {
            $rates[$code] = (float)$row['setting_value'];
        }
    }
} catch (Exception $e) {
    // Ignora, continua apenas com BRL
}

// Função helper para converter moedas para BRL
$convert_to_brl = function($amount, $currency) use ($rates) {
    if ($currency === 'BRL') {
        return $amount;
    }
    if (!isset($rates[$currency]) || $rates[$currency] == 0) {
        return null; 
    }
    $rate = $rates[$currency];
    return $amount * $rate;
};

// 1. Tabela - Projetos Concluídos no Período (COM tax_percentage)
$stmt_projects = $pdo->prepare("
    SELECT 
        p.id, p.title, p.status, p.total_amount, p.currency, p.created_at, p.completed_date,
        p.tax_percentage,
        c.company as company_name
    FROM dash_projects p
    LEFT JOIN dash_clients c ON p.client_id = c.id
    WHERE
        p.user_id = ? AND
        p.completed_date BETWEEN ? AND ?
    ORDER BY p.completed_date DESC
");
$stmt_projects->execute([$user_id, $start_date, $end_date]);
$projects = $stmt_projects->fetchAll(PDO::FETCH_ASSOC);

// 2. Resumo de Receita
$stmt_revenue_summary = $pdo->prepare("
    SELECT currency, SUM(total_amount) as total_revenue
    FROM dash_projects
    WHERE user_id = ? AND status = 'completed' AND completed_date BETWEEN ? AND ?
    GROUP BY currency
");
$stmt_revenue_summary->execute([$user_id, $start_date, $end_date]);
$all_revenues = $stmt_revenue_summary->fetchAll(PDO::FETCH_ASSOC);

$total_revenue_brl = 0;
foreach ($all_revenues as $revenue) {
    $revenue_in_brl = $convert_to_brl($revenue['total_revenue'], $revenue['currency']);
    if($revenue_in_brl !== null) {
        $total_revenue_brl += $revenue_in_brl;
    }
}

// 3. Resumo de Custo
$stmt_cost_summary = $pdo->prepare("
    SELECT j.currency, SUM(j.total_cost) as total_cost
    FROM dash_jobs j
    JOIN dash_projects p ON j.project_id = p.id
    WHERE p.user_id = ? AND p.status = 'completed' AND p.completed_date BETWEEN ? AND ?
    GROUP BY j.currency
");
$stmt_cost_summary->execute([$user_id, $start_date, $end_date]);
$all_costs = $stmt_cost_summary->fetchAll(PDO::FETCH_ASSOC);

$total_cost_brl = 0;
foreach ($all_costs as $cost) {
    $cost_in_brl = $convert_to_brl($cost['total_cost'], $cost['currency']);
    if($cost_in_brl !== null) {
        $total_cost_brl += $cost_in_brl;
    }
}

// 4. [CORRIGIDO] Calcular impostos a partir dos projetos já buscados
$total_tax_brl = 0;
foreach ($projects as $project) {
    $revenue_brl = $convert_to_brl($project['total_amount'], $project['currency']);
    $tax_percentage = isset($project['tax_percentage']) ? (float)$project['tax_percentage'] : 0;
    $tax_brl = ($revenue_brl ?? 0) * ($tax_percentage / 100);
    $total_tax_brl += $tax_brl;
}

// 5. Cálculo de Lucro e Margem (COM IMPOSTOS)
$total_profit_brl = $total_revenue_brl - $total_cost_brl - $total_tax_brl;
$margin_percent = ($total_revenue_brl > 0) ? ($total_profit_brl / $total_revenue_brl) * 100 : 0;

// 6. Preparar dados da Tabela (Buscando custos, impostos e convertendo para BRL)
$projects_data = [];
foreach ($projects as $project) {
    $stmt_jobs = $pdo->prepare("SELECT total_cost, currency FROM dash_jobs WHERE project_id = ? AND user_id = ?");
    $stmt_jobs->execute([$project['id'], $user_id]);
    $jobs = $stmt_jobs->fetchAll(PDO::FETCH_ASSOC);

    $cost_brl = 0;
    foreach ($jobs as $job) {
        $converted_cost = $convert_to_brl($job['total_cost'], $job['currency']);
        $cost_brl += $converted_cost ?? 0;
    }
    
    $revenue_brl = $convert_to_brl($project['total_amount'], $project['currency']);
    
    // Calcular imposto do projeto
    $tax_percentage = isset($project['tax_percentage']) ? (float)$project['tax_percentage'] : 0;
    $tax_brl = ($revenue_brl ?? 0) * ($tax_percentage / 100);
    
    $profit_brl = ($revenue_brl ?? 0) - $cost_brl - $tax_brl;
    
    $projects_data[] = [
        'project' => $project,
        'revenue_brl' => $revenue_brl ?? 0,
        'cost_brl' => $cost_brl,
        'tax_brl' => $tax_brl,
        'profit_brl' => $profit_brl
    ];
}

// --- Fim da Lógica de Dados ---

// --- Início da Geração do PDF ---

class MYPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, 'Relatório de Lucratividade (P&L)', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(5);
        $this->SetFont('helvetica', '', 9);
        $this->Cell(0, 10, 'Período: ' . date('d/m/Y', strtotime($_GET['start_date'])) . ' a ' . date('d/m/Y', strtotime($_GET['end_date'])), 0, false, 'C', 0, '', 0, false, 'M', 'M');
    }
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Dash-T101');
$pdf->SetTitle('Relatório de Lucratividade');
$pdf->SetMargins(10, 25, 10);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->AddPage('L', 'A4');

// Resumo Consolidado (BRL) - COM IMPOSTOS
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 10, 'Resumo Consolidado (Convertido para BRL)', 0, 1, 'L');
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(0);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(55, 7, 'Receita Total (BRL)', 1, 0, 'C', 1);
$pdf->Cell(55, 7, 'Custo Total (BRL)', 1, 0, 'C', 1);
$pdf->Cell(55, 7, 'Impostos (BRL)', 1, 0, 'C', 1);
$pdf->Cell(55, 7, 'Lucro Total (BRL)', 1, 0, 'C', 1);
$pdf->Cell(57, 7, 'Margem', 1, 1, 'C', 1);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(55, 7, formatCurrency($total_revenue_brl, 'BRL'), 1, 0, 'C');
$pdf->Cell(55, 7, formatCurrency($total_cost_brl, 'BRL'), 1, 0, 'C');
$pdf->Cell(55, 7, formatCurrency($total_tax_brl, 'BRL'), 1, 0, 'C');
$pdf->Cell(55, 7, formatCurrency($total_profit_brl, 'BRL'), 1, 0, 'C');
$pdf->Cell(57, 7, number_format($margin_percent, 2, ',', '.') . '%', 1, 1, 'C');
$pdf->Ln(8);

// Tabela Detalhada de Projetos - COM IMPOSTOS
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 10, 'Detalhamento de Projetos (Valores Convertidos para BRL)', 0, 1, 'L');
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(0);
$pdf->SetFont('helvetica', 'B', 9);
$w = [70, 67, 25, 25, 25, 25, 30]; // Larguras das colunas (Total 267)
$pdf->Cell($w[0], 7, 'Projeto', 1, 0, 'C', 1);
$pdf->Cell($w[1], 7, 'Cliente', 1, 0, 'C', 1);
$pdf->Cell($w[2], 7, 'Conclusão', 1, 0, 'C', 1);
$pdf->Cell($w[3], 7, 'Receita (BRL)', 1, 0, 'C', 1);
$pdf->Cell($w[4], 7, 'Custo (BRL)', 1, 0, 'C', 1);
$pdf->Cell($w[5], 7, 'Impostos (BRL)', 1, 0, 'C', 1);
$pdf->Cell($w[6], 7, 'Lucro (BRL)', 1, 1, 'C', 1);
$pdf->SetFont('helvetica', '', 8);
$pdf->SetFillColor(255, 255, 255);

if (empty($projects_data)) {
    $pdf->Cell(array_sum($w), 10, 'Nenhum projeto concluído encontrado no período.', 1, 0, 'C', 1);
    $pdf->Ln();
} else {
    foreach($projects_data as $data) {
        $project = $data['project'];
        
        $revenue_brl = $data['revenue_brl'];
        $cost_brl = $data['cost_brl'];
        $tax_brl = $data['tax_brl'];
        $profit_brl = $data['profit_brl'];

        $project_name = $pdf->GetStringWidth($project['title']) > $w[0]-5 ? substr($project['title'], 0, 40) . '...' : $project['title'];
        $client_name = $pdf->GetStringWidth($project['company_name']) > $w[1]-5 ? substr($project['company_name'], 0, 35) . '...' : $project['company_name'];

        $pdf->Cell($w[0], 6, $project_name, 'LR', 0, 'L', 1);
        $pdf->Cell($w[1], 6, $client_name, 'LR', 0, 'L', 1);
        $pdf->Cell($w[2], 6, date('d/m/Y', strtotime($project['completed_date'])), 'LR', 0, 'C', 1);
        $pdf->Cell($w[3], 6, formatCurrency($revenue_brl, 'BRL'), 'LR', 0, 'R', 1);
        $pdf->Cell($w[4], 6, formatCurrency($cost_brl, 'BRL'), 'LR', 0, 'R', 1);
        $pdf->Cell($w[5], 6, formatCurrency($tax_brl, 'BRL'), 'LR', 0, 'R', 1);
        
        if ($profit_brl < 0) {
            $pdf->SetTextColor(255, 0, 0);
        }
        $pdf->Cell($w[6], 6, formatCurrency($profit_brl, 'BRL'), 'LR', 0, 'R', 1);
        $pdf->SetTextColor(0);
        
        $pdf->Ln();
    }
}
$pdf->Cell(array_sum($w), 0, '', 'T');

// Fechar e enviar o PDF
$pdf->Output('Relatorio_Lucratividade_' . date('Y-m-d') . '.pdf', 'D');
?>