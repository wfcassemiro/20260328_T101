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

// Lógica de busca de dados (COM IMPOSTOS)
$report_data = [];
$totals = ['revenue' => 0, 'cost' => 0, 'tax' => 0, 'profit' => 0, 'margin' => 0];
$rates_string = "1 BRL = 1.00 BRL";

try {
    // ETAPA 1: Buscar Taxas de Câmbio Salvas
    $rates = ['brl' => 1.0];
    $sql_rate_cases_revenue = "CASE p.currency ";
    $sql_rate_cases_cost = "CASE j.currency ";
    
    $stmt_rates = $pdo->prepare("SELECT setting_key, setting_value FROM dash_settings WHERE user_id = ? AND setting_key LIKE 'rate_%'");
    $stmt_rates->execute([$user_id]);
    $db_rates = $stmt_rates->fetchAll(PDO::FETCH_KEY_PAIR);
    
    if (!isset($db_rates['rate_usd'])) $db_rates['rate_usd'] = 1.0;
    if (!isset($db_rates['rate_eur'])) $db_rates['rate_eur'] = 1.0;
    
    foreach ($db_rates as $key => $value) {
        $code_lower = str_replace('rate_', '', $key);
        $code_upper = strtoupper($code_lower);
        
        $rates[$code_lower] = (float)$value;
        $sql_rate_cases_revenue .= " WHEN '{$code_upper}' THEN p.total_amount * {$value} ";
        $sql_rate_cases_cost .= " WHEN '{$code_upper}' THEN j.total_cost * {$value} ";
        
        $rates_string .= " | 1 {$code_upper} = " . number_format($value, 2, ',', '.') . " BRL";
    }
    
    $sql_rate_cases_revenue .= " ELSE p.total_amount END"; 
    $sql_rate_cases_cost .= " ELSE j.total_cost END";

    // ETAPA 2: Obter Receita (Convertida para BRL)
    $stmt_revenue = $pdo->prepare(
        "SELECT c.id AS client_id, c.company AS client_name, SUM($sql_rate_cases_revenue) AS total_revenue_brl
        FROM dash_projects p JOIN dash_clients c ON p.client_id = c.id
        WHERE p.user_id = ? AND p.status = 'completed' AND p.completed_date BETWEEN ? AND ?
        GROUP BY c.id, c.company"
    );
    $stmt_revenue->execute([$user_id, $start_date, $end_date]);
    while ($row = $stmt_revenue->fetch(PDO::FETCH_ASSOC)) {
        $report_data[$row['client_id']] = [
            'client_name' => $row['client_name'], 
            'revenue' => (float)$row['total_revenue_brl'], 
            'cost' => 0, 
            'tax' => 0,
            'profit' => 0, 
            'margin' => 0
        ];
        $totals['revenue'] += $row['total_revenue_brl'];
    }

    // ETAPA 3: Obter Custo (Convertido para BRL)
    $stmt_cost = $pdo->prepare(
        "SELECT p.client_id, SUM($sql_rate_cases_cost) AS total_cost_brl
        FROM dash_jobs j JOIN dash_projects p ON j.project_id = p.id
        WHERE j.user_id = ? AND p.status = 'completed' AND p.completed_date BETWEEN ? AND ?
        GROUP BY p.client_id"
    );
    $stmt_cost->execute([$user_id, $start_date, $end_date]);
    while ($row = $stmt_cost->fetch(PDO::FETCH_ASSOC)) {
        if (isset($report_data[$row['client_id']])) {
            $report_data[$row['client_id']]['cost'] = (float)$row['total_cost_brl'];
        }
        $totals['cost'] += (float)$row['total_cost_brl'];
    }

    // [NOVO] ETAPA 4: Obter Impostos (Convertido para BRL)
    $sql_rate_cases_tax = str_replace('p.total_amount', '(p.total_amount * p.tax_percentage / 100)', $sql_rate_cases_revenue);
    $stmt_tax = $pdo->prepare(
        "SELECT p.client_id, SUM($sql_rate_cases_tax) AS total_tax_brl
        FROM dash_projects p
        WHERE p.user_id = ? AND p.status = 'completed' AND p.completed_date BETWEEN ? AND ?
        GROUP BY p.client_id"
    );
    $stmt_tax->execute([$user_id, $start_date, $end_date]);
    while ($row = $stmt_tax->fetch(PDO::FETCH_ASSOC)) {
        if (isset($report_data[$row['client_id']])) {
            $report_data[$row['client_id']]['tax'] = (float)$row['total_tax_brl'];
        }
        $totals['tax'] += (float)$row['total_tax_brl'];
    }

    // 5. Calcular Lucro e MARGEM (COM IMPOSTOS)
    foreach ($report_data as $client_id => $data) {
        $profit = $data['revenue'] - $data['cost'] - $data['tax'];
        $margin = ($data['revenue'] > 0) ? ($profit / $data['revenue']) * 100 : 0;
        $report_data[$client_id]['profit'] = $profit;
        $report_data[$client_id]['margin'] = $margin;
    }
    
    $totals['profit'] = $totals['revenue'] - $totals['cost'] - $totals['tax'];
    $totals['margin'] = ($totals['revenue'] > 0) ? ($totals['profit'] / $totals['revenue']) * 100 : 0;
    
    uasort($report_data, function($a, $b) { return $b['profit'] <=> $a['profit']; });

} catch (Exception $e) {
    die("Erro ao gerar relatório: " . $e->getMessage());
}

// Classe PDF customizada para Header/Footer
class MYPDF extends TCPDF {
    public $header_title = '';
    public $header_subtitle = '';
    public $rates_info = '';

    public function Header() {
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, $this->header_title, 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(5);
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 15, $this->header_subtitle, 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(5);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 15, 'Taxas Utilizadas: ' . $this->rates_info, 0, false, 'C', 0, '', 0, false, 'M', 'M');
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages() . ' | Gerado em ' . date('d/m/Y'), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Iniciar PDF
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->header_title = 'Relatório de Lucratividade por Cliente';
$pdf->header_subtitle = 'Período: ' . date('d/m/Y', strtotime($start_date)) . ' a ' . date('d/m/Y', strtotime($end_date));
$pdf->rates_info = $rates_string;

// Configurações do documento
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Dash-T101');
$pdf->SetTitle($pdf->header_title);
$pdf->SetMargins(15, 32, 15);
$pdf->SetHeaderMargin(15);
$pdf->SetFooterMargin(15);
$pdf->SetAutoPageBreak(TRUE, 25);
$pdf->SetFont('helvetica', '', 10);
$pdf->AddPage();

// Cabeçalho da Tabela - [ATUALIZADO COM IMPOSTOS]
$header = ['Cliente', 'Receita (BRL)', 'Custo (BRL)', 'Impostos (BRL)', 'Lucro (BRL)', 'Margem (%)'];
$w = [60, 22, 22, 22, 22, 22]; // Larguras das colunas
$pdf->SetFillColor(230, 230, 230);
$pdf->SetTextColor(0);
$pdf->SetDrawColor(128, 128, 128);
$pdf->SetFont('', 'B');
for($i = 0; $i < count($header); ++$i) {
    $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
}
$pdf->Ln();

// Dados da Tabela
$pdf->SetFont('', '');
$pdf->SetFillColor(255, 255, 255);
if (empty($report_data)) {
    $pdf->Cell(array_sum($w), 10, 'Nenhum dado encontrado para o período.', 1, 0, 'C', 1);
    $pdf->Ln();
} else {
    foreach($report_data as $row) {
        $client_name = $pdf->GetStringWidth($row['client_name']) > $w[0] ? substr($row['client_name'], 0, 35) . '...' : $row['client_name'];
        $pdf->Cell($w[0], 6, $client_name, 'LR', 0, 'L', 1);
        $pdf->Cell($w[1], 6, formatCurrency($row['revenue'], 'BRL'), 'LR', 0, 'R', 1);
        $pdf->Cell($w[2], 6, formatCurrency($row['cost'], 'BRL'), 'LR', 0, 'R', 1);
        $pdf->Cell($w[3], 6, formatCurrency($row['tax'], 'BRL'), 'LR', 0, 'R', 1);
        $pdf->Cell($w[4], 6, formatCurrency($row['profit'], 'BRL'), 'LR', 0, 'R', 1);
        $pdf->Cell($w[5], 6, number_format($row['margin'], 2, ',', '.') . '%', 'LR', 0, 'R', 1);
        $pdf->Ln();
    }
}
$pdf->Cell(array_sum($w), 0, '', 'T');
$pdf->Ln(1);

// Totais - [ATUALIZADO COM IMPOSTOS]
$pdf->SetFont('', 'B');
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell($w[0], 7, 'TOTAL', 'LTB', 0, 'R', 1);
$pdf->Cell($w[1], 7, formatCurrency($totals['revenue'], 'BRL'), 'TB', 0, 'R', 1);
$pdf->Cell($w[2], 7, formatCurrency($totals['cost'], 'BRL'), 'TB', 0, 'R', 1);
$pdf->Cell($w[3], 7, formatCurrency($totals['tax'], 'BRL'), 'TB', 0, 'R', 1);
$pdf->Cell($w[4], 7, formatCurrency($totals['profit'], 'BRL'), 'TB', 0, 'R', 1);
$pdf->Cell($w[5], 7, number_format($totals['margin'], 2, ',', '.') . '%', 'RTB', 0, 'R', 1);

// Fechar e gerar o PDF
$pdf->Output('lucratividade_cliente.pdf', 'D');
exit;
?>