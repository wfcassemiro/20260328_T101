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

// --- Lógica de busca de dados (Exatamente a mesma do report_accounts_receivable.php) ---

try {
    // 1. KPIs - Totais a Receber por Moeda
    $stmt_kpi = $pdo->prepare("
        SELECT currency, SUM(total_amount) as total_receivable
        FROM dash_projects
        WHERE user_id = ?
          AND status = 'completed'
          AND payment_date IS NULL
        GROUP BY currency
    ");
    $stmt_kpi->execute([$user_id]);
    $kpi_totals = $stmt_kpi->fetchAll(PDO::FETCH_ASSOC);

    // 2. Tabela - Projetos Pendentes de Pagamento
    $stmt_projects = $pdo->prepare("
        SELECT p.id, p.title, p.po_number, p.total_amount, p.currency, p.completed_date, p.deadline, c.company AS company_name
        FROM dash_projects p
        LEFT JOIN dash_clients c ON p.client_id = c.id
        WHERE p.user_id = ?
          AND p.status = 'completed'
          AND p.payment_date IS NULL
        ORDER BY p.deadline ASC, p.completed_date ASC
    ");
    $stmt_projects->execute([$user_id]);
    $projects_to_receive = $stmt_projects->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}

// --- Classe PDF Customizada (para Cabeçalho e Rodapé) ---
class MYPDF extends TCPDF {
    // Cabeçalho
    public function Header() {
        $this->SetFont('helvetica', 'B', 16);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 15, 'Relatório de Contas a Receber', 0, false, 'C', 0, '', 0, false, 'M', 'M');
    }

    // Rodapé
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages() . ' | Gerado pelo Dash-T101, em ' . date('d/m/Y H:i'), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// --- Criação do Documento PDF ---
// 'P' para Portrait (Retrato)
$pdf = new MYPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Informações do documento
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Dash-T101');
$pdf->SetTitle('Contas a Receber');
$pdf->SetSubject('Relatório de valores a receber');

// Margens
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Quebra de página
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Adiciona a página
$pdf->AddPage();

// --- Conteúdo do PDF ---

// Resumo (KPIs)
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 8, 'Resumo de Valores a Receber', 0, 1, 'L', true);
$pdf->Ln(2);

$pdf->SetFont('helvetica', '', 10);
if (empty($kpi_totals)) {
    $pdf->Cell(0, 7, 'Nenhum valor a receber encontrado.', 0, 1, 'L');
} else {
    foreach ($kpi_totals as $kpi) {
        $pdf->SetTextColor(34, 153, 84); // Verde
        $pdf->Cell(0, 7, 'Total ('.htmlspecialchars($kpi['currency']).'): ' . formatCurrency($kpi['total_receivable'], $kpi['currency']), 0, 1, 'L');
    }
}
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(8);


// Tabela de Projetos
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 8, 'Detalhamento de projetos pendentes', 0, 1, 'L', true);
$pdf->Ln(2);

// Cabeçalho da Tabela
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(220, 220, 220);
$pdf->SetTextColor(0);
$pdf->SetDrawColor(128);
$w = [60, 70, 25, 35]; // Larguras das colunas (Total 190mm)
$header = ['Cliente', 'Projeto', 'Conclusão', 'Valor Total'];

for($i = 0; $i < count($header); $i++) {
    $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
}
$pdf->Ln();

// Dados da Tabela
$pdf->SetFont('helvetica', '', 8);
$pdf->SetFillColor(255, 255, 255);
$pdf->SetTextColor(0);

if (empty($projects_to_receive)) {
    $pdf->Cell(array_sum($w), 10, 'Nenhum projeto pendente de pagamento encontrado.', 1, 0, 'C', 1);
    $pdf->Ln();
} else {
    foreach($projects_to_receive as $project) {
        
        $project_name = $pdf->GetStringWidth($project['title']) > $w[1]-5 ? substr($project['title'], 0, 40) . '...' : $project['title'];
        $client_name = $pdf->GetStringWidth($project['company_name']) > $w[0]-5 ? substr($project['company_name'], 0, 35) . '...' : $project['company_name'];
        
        $date_display = $project['completed_date'] ? date('d/m/Y', strtotime($project['completed_date'])) : (
                        $project['deadline'] ? date('d/m/Y', strtotime($project['deadline'])) : '-'
                        );

        $pdf->Cell($w[0], 6, $client_name, 'LR', 0, 'L', 1);
        $pdf->Cell($w[1], 6, $project_name, 'LR', 0, 'L', 1);
        $pdf->Cell($w[2], 6, $date_display, 'LR', 0, 'C', 1);
        $pdf->Cell($w[3], 6, formatCurrency($project['total_amount'], $project['currency']), 'LR', 0, 'R', 1);
        $pdf->Ln();
    }
}
// Linha de fechamento da tabela
$pdf->Cell(array_sum($w), 0, '', 'T');


// --- Saída do PDF ---
// MODIFICADO: 'D' para forçar o Download
$pdf->Output('contas_a_receber.pdf', 'D'); 

?>