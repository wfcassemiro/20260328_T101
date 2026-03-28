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

$job_status_list = [
    'completed' => 'Pendente',
    'paid' => 'Pago'
];

// --- Lógica de Filtro (GET) ---
$selected_freelancer_id = isset($_GET['freelancer_id']) ? (int)$_GET['freelancer_id'] : null;
$startDate = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

if ($selected_freelancer_id === null) {
    die("Fornecedor não selecionado.");
}

// --- Busca de Dados ---
try {
    // 1. Obter o nome do fornecedor
    $stmt_name = $pdo->prepare("SELECT name FROM dash_freelancers WHERE id = ? AND user_id = ?");
    $stmt_name->execute([$selected_freelancer_id, $user_id]);
    $selected_freelancer_name = $stmt_name->fetchColumn();
    
    if (!$selected_freelancer_name) {
        die("Fornecedor não encontrado.");
    }

    // 2. Buscar trabalhos (Jobs)
    $stmt_jobs = $pdo->prepare("
        SELECT 
            j.id,
            j.service_type,
            j.total_cost,
            j.currency,
            j.status,
            j.completed_date,
            p.title AS project_title
        FROM dash_jobs j
        LEFT JOIN dash_projects p ON j.project_id = p.id
        WHERE j.user_id = ?
          AND j.freelancer_id = ?
          AND j.status IN ('completed', 'paid') 
          AND j.completed_date BETWEEN ? AND ?
        ORDER BY j.completed_date DESC, j.id DESC
    ");
    $stmt_jobs->execute([$user_id, $selected_freelancer_id, $startDate, $endDate]);
    $report_data = $stmt_jobs->fetchAll(PDO::FETCH_ASSOC);
    
    $hasData = !empty($report_data);

    // 3. Calcular KPIs
    $kpi_summary = [
        'total_payable' => [],
        'total_paid' => [],
        'total_geral' => []
    ];
    foreach ($report_data as $job) {
        $currency = $job['currency'];
        $cost = (float)$job['total_cost'];
        if (!isset($kpi_summary['total_payable'][$currency])) $kpi_summary['total_payable'][$currency] = 0;
        if (!isset($kpi_summary['total_paid'][$currency])) $kpi_summary['total_paid'][$currency] = 0;
        if (!isset($kpi_summary['total_geral'][$currency])) $kpi_summary['total_geral'][$currency] = 0;

        if ($job['status'] == 'completed') {
            $kpi_summary['total_payable'][$currency] += $cost;
        } else if ($job['status'] == 'paid') {
            $kpi_summary['total_paid'][$currency] += $cost;
        }
        $kpi_summary['total_geral'][$currency] += $cost;
    }

} catch (PDOException $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}

// --- Classe PDF Customizada ---
class MYPDF extends TCPDF {
    public $freelancerName = 'Fornecedor'; // Nome padrão
    public $filterPeriod = ''; // Período

    public function Header() {
        $this->SetFont('helvetica', 'B', 16);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 15, 'Extrato do Fornecedor', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        
        // Informações do Filtro no Cabeçalho
        $this->SetY(20);
        $this->SetFont('helvetica', 'B', 10);
        $this->SetTextColor(50, 50, 50);
        $this->Cell(0, 7, $this->freelancerName, 0, 1, 'C');
        $this->SetFont('helvetica', '', 9);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 6, 'Período (Conclusão): ' . $this->filterPeriod, 0, 1, 'C');
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

// Seta as variáveis customizadas do Header
$pdf->freelancerName = $selected_freelancer_name;
$pdf->filterPeriod = date('d/m/Y', strtotime($startDate)) . ' até ' . date('d/m/Y', strtotime($endDate));

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Dash-T101');
$pdf->SetTitle('Extrato do Fornecedor - ' . $selected_freelancer_name);
$pdf->SetSubject('Extrato de trabalhos e pagamentos');

$pdf->SetMargins(PDF_MARGIN_LEFT, 35, PDF_MARGIN_RIGHT); // Margem TOP maior (35) para o cabeçalho customizado
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->AddPage();

// --- Conteúdo do PDF ---

// Resumo (KPIs)
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 8, 'Resumo de Custos', 0, 1, 'L', true);
$pdf->Ln(2);

$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(192, 0, 0); // Vermelho
$pdf->Cell(63, 7, 'Total Pendente (a pagar):', 0, 0, 'L');
$pdf->Cell(127, 7, implode(', ', array_map(function($total, $currency) {
    return formatCurrency($total, $currency);
}, $kpi_summary['total_payable'], array_keys($kpi_summary['total_payable']))) ?: '-', 0, 1, 'L');

$pdf->SetTextColor(0, 100, 0); // Verde
$pdf->Cell(63, 7, 'Total Pago (no período):', 0, 0, 'L');
$pdf->Cell(127, 7, implode(', ', array_map(function($total, $currency) {
    return formatCurrency($total, $currency);
}, $kpi_summary['total_paid'], array_keys($kpi_summary['total_paid']))) ?: '-', 0, 1, 'L');

$pdf->SetTextColor(0, 0, 128); // Azul
$pdf->Cell(63, 7, 'Total Geral (no período):', 0, 0, 'L');
$pdf->Cell(127, 7, implode(', ', array_map(function($total, $currency) {
    return formatCurrency($total, $currency);
}, $kpi_summary['total_geral'], array_keys($kpi_summary['total_geral']))) ?: '-', 0, 1, 'L');

$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(8);


// Tabela de Trabalhos
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 8, 'Detalhamento de Trabalhos', 0, 1, 'L', true);
$pdf->Ln(2);

// Cabeçalho da Tabela
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(220, 220, 220);
$pdf->SetTextColor(0);
$pdf->SetDrawColor(128);
$w = [25, 75, 30, 30, 30]; // Larguras (Total 190mm)
$header = ['Data Concl.', 'Projeto', 'Serviço', 'Valor', 'Status'];

for($i = 0; $i < count($header); $i++) {
    $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
}
$pdf->Ln();

// Dados da Tabela
$pdf->SetFont('helvetica', '', 8);
$pdf->SetFillColor(255, 255, 255);
$pdf->SetTextColor(0);

if (!$hasData) {
    $pdf->Cell(array_sum($w), 10, 'Nenhum trabalho encontrado para este fornecedor no período.', 1, 0, 'C', 1);
    $pdf->Ln();
} else {
    $fill = 0; // Alternar cor
    foreach($report_data as $job) {
        
        $project_name = $pdf->GetStringWidth($job['project_title']) > $w[1]-5 ? substr($job['project_title'], 0, 45) . '...' : ($job['project_title'] ?? 'N/A');
        $service_name = htmlspecialchars($service_types_list[$job['service_type']] ?? $job['service_type']);
        $date_display = date('d/m/Y', strtotime($job['completed_date']));
        $value_display = formatCurrency($job['total_cost'], $job['currency']);
        $status_display = $job_status_list[$job['status']] ?? $job['status'];

        // Alterna cor
        $fill = !$fill;
        $pdf->SetFillColor($fill ? 245 : 255);
        
        // Cor do texto do status
        if ($job['status'] == 'completed') {
            $pdf->SetTextColor(192, 0, 0); // Pendente = Vermelho
        } else {
            $pdf->SetTextColor(0, 100, 0); // Pago = Verde
        }

        $pdf->Cell($w[0], 6, $date_display, 'LR', 0, 'C', $fill);
        $pdf->SetTextColor(0); // Reseta cor
        $pdf->Cell($w[1], 6, $project_name, 'LR', 0, 'L', $fill);
        $pdf->Cell($w[2], 6, $service_name, 'LR', 0, 'L', $fill);
        
        if ($job['status'] == 'completed') $pdf->SetTextColor(192, 0, 0);
        else $pdf->SetTextColor(0, 100, 0);
        
        $pdf->Cell($w[3], 6, $value_display, 'LR', 0, 'R', $fill);
        $pdf->Cell($w[4], 6, $status_display, 'LR', 0, 'C', $fill);
        
        $pdf->SetTextColor(0); // Reseta cor
        $pdf->Ln();
    }
}
// Linha de fechamento da tabela
$pdf->Cell(array_sum($w), 0, '', 'T');


// --- Saída do PDF ---
$pdf_filename = 'extrato_' . preg_replace('/[^a-z0-9]/i', '_', strtolower($selected_freelancer_name)) . '.pdf';
$pdf->Output($pdf_filename, 'D'); 

?>