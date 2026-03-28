<?php
session_start();
// Definir fuso horário
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

// --- Definição de Nomes Amigáveis (CORRIGIDO) ---
$service_types_list = [
    'translation' => 'Tradução', 
    'revision' => 'Revisão', 
    'proofreading' => 'Revisão (Proofreading)',
    'localization' => 'Localização', 
    'interpretacao' => 'Interpretação', 
    'transcription' => 'Transcrição', 
    'other' => 'Outros'
];

$unit_types_list = [
    'palavra' => 'Palavras',
    'hora' => 'Horas',
    'minuto' => 'Minutos',
    'lauda' => 'Laudas',
    'diaria' => 'Diárias' // ADICIONADO
];

// --- Lógica de Filtro de Data ---
$endDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime('-30 days'));

if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $startDate = $_GET['start_date'];
}
if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $endDate = $_GET['end_date'];
}

try {
    // 1. KPIs - Totais por Unidade
    $stmt_kpi = $pdo->prepare("
        SELECT 
            unit_type, 
            SUM(word_count) as total_quantity
        FROM dash_projects 
        WHERE user_id = ? 
          AND status = 'completed' 
          AND completed_date BETWEEN ? AND ? 
        GROUP BY unit_type
    ");
    $stmt_kpi->execute([$user_id, $startDate, $endDate]);
    $kpi_totals = $stmt_kpi->fetchAll(PDO::FETCH_ASSOC);

    // 2. Tabela - Detalhamento
    $stmt_table = $pdo->prepare("
        SELECT 
            service_type, 
            unit_type, 
            SUM(word_count) as total_quantity, 
            COUNT(id) as total_projects 
        FROM dash_projects 
        WHERE user_id = ? 
          AND status = 'completed' 
          AND completed_date BETWEEN ? AND ? 
        GROUP BY service_type, unit_type 
        ORDER BY service_type, unit_type
    ");
    $stmt_table->execute([$user_id, $startDate, $endDate]);
    $report_data = $stmt_table->fetchAll(PDO::FETCH_ASSOC);

    $hasData = !empty($report_data);

    // Processar KPIs para fácil exibição (CORRIGIDO)
    $kpi_summary = [
        'palavra' => 0,
        'hora' => 0,
        'minuto' => 0,
        'lauda' => 0,
        'diaria' => 0, // ADICIONADO
    ];
    foreach ($kpi_totals as $kpi) {
        if (isset($kpi_summary[$kpi['unit_type']])) {
            $kpi_summary[$kpi['unit_type']] = $kpi['total_quantity'];
        }
    }
    
    // Contagem total de projetos
    $stmt_total_projects = $pdo->prepare("SELECT COUNT(DISTINCT id) FROM dash_projects WHERE user_id = ? AND status = 'completed' AND completed_date BETWEEN ? AND ?");
    $stmt_total_projects->execute([$user_id, $startDate, $endDate]);
    $kpi_summary['total_projects_unique'] = $stmt_total_projects->fetchColumn();


} catch (PDOException $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}

// --- Classe PDF Customizada ---
class MYPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 16);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 15, 'Relatório de Volume de Produção', 0, false, 'C', 0, '', 0, false, 'M', 'M');
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
$pdf->SetTitle('Volume de Produção');
$pdf->SetSubject('Relatório de volume de trabalho concluído');

$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->AddPage();

// --- Conteúdo do PDF ---

// Período do Relatório
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 8, 'Período do Relatório (Data de Conclusão)', 0, 1, 'L', true);
$pdf->Ln(2);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 7, 'De: ' . date('d/m/Y', strtotime($startDate)) . '  Até: ' . date('d/m/Y', strtotime($endDate)), 0, 1, 'L');
$pdf->Ln(5);

// Resumo (KPIs) (CORRIGIDO)
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 8, 'Resumo de Volume', 0, 1, 'L', true);
$pdf->Ln(2);

$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(0, 0, 128); // Azul para os valores
$pdf->Cell(95, 7, 'Total de Projetos Concluídos: ' . $kpi_summary['total_projects_unique'], 0, 0, 'L');
$pdf->Cell(95, 7, 'Total de Palavras: ' . number_format($kpi_summary['palavra'], 0, ',', '.'), 0, 1, 'R');
$pdf->Cell(95, 7, 'Total de Horas: ' . number_format($kpi_summary['hora'], 2, ',', '.'), 0, 0, 'L');
$pdf->Cell(95, 7, 'Total de Minutos: ' . number_format($kpi_summary['minuto'], 0, ',', '.'), 0, 1, 'R');
$pdf->Cell(95, 7, 'Total de Laudas: ' . number_format($kpi_summary['lauda'], 2, ',', '.'), 0, 0, 'L');
$pdf->Cell(95, 7, 'Total de Diárias: ' . number_format($kpi_summary['diaria'], 2, ',', '.'), 0, 1, 'R'); // ADICIONADO
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(8);


// Tabela de Trabalhos
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 8, 'Detalhamento por serviço e unidade', 0, 1, 'L', true);
$pdf->Ln(2);

// Cabeçalho da Tabela
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(220, 220, 220);
$pdf->SetTextColor(0);
$pdf->SetDrawColor(128);
$w = [60, 45, 45, 40]; // Larguras (Total 190mm)
$header = ['Tipo de Serviço', 'Unidade de Medida', 'Quantidade Total', 'Nº de Projetos'];

for($i = 0; $i < count($header); $i++) {
    $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
}
$pdf->Ln();

// Dados da Tabela
$pdf->SetFont('helvetica', '', 8);
$pdf->SetFillColor(255, 255, 255);
$pdf->SetTextColor(0);

if (!$hasData) {
    $pdf->Cell(array_sum($w), 10, 'Nenhum projeto concluído encontrado para o período.', 1, 0, 'C', 1);
    $pdf->Ln();
} else {
    $fill = 0; // Alternar cor
    foreach($report_data as $data) {
        // Alterna cor
        $fill = !$fill;
        $pdf->SetFillColor($fill ? 245 : 255);
        
        $service_name = htmlspecialchars($service_types_list[$data['service_type']] ?? $data['service_type']);
        $unit_name = htmlspecialchars($unit_types_list[$data['unit_type']] ?? $data['unit_type']);
        
        // (CORRIGIDO) Formata com decimais se for hora, lauda ou diaria
        $quantity = (in_array($data['unit_type'], ['hora', 'lauda', 'diaria'])) 
                    ? number_format($data['total_quantity'], 2, ',', '.')
                    : number_format($data['total_quantity'], 0, ',', '.');
        
        $pdf->Cell($w[0], 6, $service_name, 'LR', 0, 'L', $fill);
        $pdf->Cell($w[1], 6, $unit_name, 'LR', 0, 'L', $fill);
        $pdf->Cell($w[2], 6, $quantity, 'LR', 0, 'R', $fill);
        $pdf->Cell($w[3], 6, $data['total_projects'], 'LR', 0, 'C', $fill);
        $pdf->Ln();
    }
}
// Linha de fechamento da tabela
$pdf->Cell(array_sum($w), 0, '', 'T');


// --- Saída do PDF ---
$pdf->Output('volume_producao.pdf', 'D'); 

?>