<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';
require_once __DIR__ . '/../config/dash_functions.php';

// Carrega o TCPDF
require_once(__DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php');

if (!isLoggedIn()) {
    die("Acesso negado.");
}

$user_id = $_SESSION['user_id'];

// --- Definição de Nomes Amigáveis ---
$service_types_list = [
    'translation' => 'Tradução', 
    'revision' => 'Revisão', 
    'proofreading' => 'Revisão (Proofreading)',
    'localization' => 'Localização', 
    'interpretacao' => 'Interpretação', 
    'transcription' => 'Transcrição', 
    'other' => 'Outros'
];

// --- Lógica de Filtro de Data (GET) ---
$startDate = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

$report_data = [];
$kpi_totals = [];
$kpi_jobs = [];
$table_data = [];
$hasData = false;

try {
    // --- Consultas ao Banco de Dados (CORRIGIDO) ---
    $stmt = $pdo->prepare("
        SELECT 
            service_type, 
            currency, 
            SUM(total_cost) as total_cost, 
            COUNT(id) as total_jobs
        FROM dash_jobs 
        WHERE user_id = ? 
          AND status IN ('completed', 'paid') 
          AND DATE(updated_at) BETWEEN ? AND ? 
        GROUP BY service_type, currency 
        ORDER BY service_type, total_cost DESC
    ");
    $stmt->execute([$user_id, $startDate, $endDate]);
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hasData = !empty($report_data);

    // --- Processamento de KPIs e Tabela ---
    foreach ($report_data as $data) {
        $currency = $data['currency'];
        
        // 1. Para KPIs
        if (!isset($kpi_totals[$currency])) {
            $kpi_totals[$currency] = 0;
            $kpi_jobs[$currency] = 0;
        }
        $kpi_totals[$currency] += $data['total_cost'];
        $kpi_jobs[$currency] += $data['total_jobs'];
        
        // 2. Para Tabela
        $table_data[] = [
            'service_name' => $service_types_list[$data['service_type']] ?? $data['service_type'],
            'currency' => $data['currency'],
            'total_cost' => $data['total_cost'],
            'total_jobs' => $data['total_jobs'],
            'average_cost' => ($data['total_jobs'] > 0) ? $data['total_cost'] / $data['total_jobs'] : 0
        ];
    }

} catch (PDOException $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}

// --- Classe PDF Customizada ---
class MYPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 16);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 15, 'Relatório de Custo por Serviço', 0, false, 'C', 0, '', 0, false, 'M', 'M');
    }
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages() . ' | Gerado pelo Dash-T101, em ' . date('d/m/Y H:i'), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// --- Criação do Documento PDF ---
$pdf = new MYPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Dash-T101');
$pdf->SetTitle('Custo por Serviço');
$pdf->SetSubject('Relatório de custos agrupados por serviço');
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->AddPage();

// --- Conteúdo do PDF ---
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 8, 'Período do Relatório (Conclusão do trabalho)', 0, 1, 'L', true);
$pdf->Ln(2);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 7, 'De: ' . date('d/m/Y', strtotime($startDate)) . '  Até: ' . date('d/m/Y', strtotime($endDate)), 0, 1, 'L');
$pdf->Ln(5);

// Resumo (KPIs)
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 8, 'Resumo de Custos', 0, 1, 'L', true);
$pdf->Ln(2);

$pdf->SetFont('helvetica', '', 10);
if (empty($kpi_totals)) {
    $pdf->Cell(0, 7, 'Nenhum dado encontrado no período.', 0, 1, 'L');
} else {
    foreach ($kpi_totals as $currency => $total) {
        $pdf->SetTextColor(192, 0, 0); // Vermelho
        $pdf->Cell(95, 7, 'Custo Total (' . $currency . '): ' . formatCurrency($total, $currency), 0, 0, 'L');
        
        $pdf->SetTextColor(0, 0, 128); // Azul
        $avg = ($kpi_jobs[$currency] > 0) ? $total / $kpi_jobs[$currency] : 0;
        $pdf->Cell(95, 7, 'Custo médio por trabalho (' . $currency . '): ' . formatCurrency($avg, $currency), 0, 1, 'R');
    }
}
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(8);

// Tabela de Trabalhos
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 8, 'Detalhamento de custo por serviço', 0, 1, 'L', true);
$pdf->Ln(2);

// Cabeçalho da Tabela
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(220, 220, 220);
$pdf->SetTextColor(0);
$pdf->SetDrawColor(128);
$w = [60, 20, 40, 30, 40]; // Larguras (Total 190mm)
$header = ['Serviço', 'Moeda', 'Custo total', 'Nº trabalhos', 'Custo médio'];
for($i = 0; $i < count($header); $i++) {
    $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
}
$pdf->Ln();

// Dados da Tabela
$pdf->SetFont('helvetica', '', 8);
$pdf->SetFillColor(255, 255, 255);
$pdf->SetTextColor(0);

if (!$hasData) {
    $pdf->Cell(array_sum($w), 10, 'Nenhum custo encontrado para o período.', 1, 0, 'C', 1);
    $pdf->Ln();
} else {
    $fill = 0; 
    foreach($table_data as $data) {
        $fill = !$fill;
        $pdf->SetFillColor($fill ? 245 : 255);
        $pdf->Cell($w[0], 6, $data['service_name'], 'LR', 0, 'L', $fill);
        $pdf->Cell($w[1], 6, $data['currency'], 'LR', 0, 'C', $fill);
        $pdf->SetTextColor(192, 0, 0); 
        $pdf->Cell($w[2], 6, formatCurrency($data['total_cost'], $data['currency']), 'LR', 0, 'R', $fill);
        $pdf->SetTextColor(0);
        $pdf->Cell($w[3], 6, $data['total_jobs'], 'LR', 0, 'C', $fill);
        $pdf->SetTextColor(0, 0, 128);
        $pdf->Cell($w[4], 6, formatCurrency($data['average_cost'], $data['currency']), 'LR', 0, 'R', $fill);
        $pdf->SetTextColor(0);
        $pdf->Ln();
    }
}
$pdf->Cell(array_sum($w), 0, '', 'T');

// --- Saída do PDF ---
$pdf->Output('custo_por_servico.pdf', 'D'); 

?>