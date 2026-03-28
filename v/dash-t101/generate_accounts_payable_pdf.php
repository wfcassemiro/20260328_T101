<?php
session_start();
// **NOVO**: Definir fuso horário
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

// **NOVO**: Lista de serviços para o nome amigável
$service_types_list = [
    'traducao' => 'Tradução', 
    'revisao' => 'Revisão', 
    'localizacao' => 'Localização', 
    'interpretacao' => 'Interpretação', 
    'transcricao' => 'Transcrição', 
    'other' => 'Outros'
];

// --- Lógica de busca de dados (Exatamente a mesma do report_accounts_payable.php) ---

try {
    // 1. KPIs - Totais a Pagar por Moeda
    $stmt_kpi = $pdo->prepare("
        SELECT currency, SUM(total_cost) as total_payable
        FROM dash_jobs
        WHERE user_id = ?
          AND status = 'completed'
        GROUP BY currency
    ");
    $stmt_kpi->execute([$user_id]);
    $kpi_totals = $stmt_kpi->fetchAll(PDO::FETCH_ASSOC);

    // 2. Tabela - Trabalhos Pendentes de Pagamento
    $stmt_jobs = $pdo->prepare("
        SELECT 
            j.id, 
            j.total_cost, 
            j.currency, 
            j.deadline,
            j.service_type,
            f.name AS freelancer_name,
            p.title AS project_title,
            p.id AS project_id_num
        FROM dash_jobs j
        LEFT JOIN dash_freelancers f ON j.freelancer_id = f.id
        LEFT JOIN dash_projects p ON j.project_id = p.id
        WHERE j.user_id = ?
          AND j.status = 'completed'
        ORDER BY f.name ASC, j.deadline ASC
    ");
    $stmt_jobs->execute([$user_id]);
    $jobs_to_pay = $stmt_jobs->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}

// --- Classe PDF Customizada (para Cabeçalho e Rodapé) ---
class MYPDF extends TCPDF {
    // Cabeçalho
    public function Header() {
        $this->SetFont('helvetica', 'B', 16);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 15, 'Relatório de Contas a Pagar', 0, false, 'C', 0, '', 0, false, 'M', 'M');
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
$pdf->SetTitle('Contas a Pagar');
$pdf->SetSubject('Relatório de custos a pagar');

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
$pdf->Cell(0, 8, 'Resumo de Valores a Pagar', 0, 1, 'L', true);
$pdf->Ln(2);

$pdf->SetFont('helvetica', '', 10);
if (empty($kpi_totals)) {
    $pdf->Cell(0, 7, 'Nenhum valor a pagar encontrado.', 0, 1, 'L');
} else {
    foreach ($kpi_totals as $kpi) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(217, 30, 24); // Vermelho/Laranja
        $pdf->Cell(0, 7, 'Total ('.htmlspecialchars($kpi['currency']).'): ' . formatCurrency($kpi['total_payable'], $kpi['currency']), 0, 1, 'L');
    }
}
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(8);


// Tabela de Trabalhos
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 8, 'Detalhamento de Trabalhos Pendentes', 0, 1, 'L', true);
$pdf->Ln(2);

// Cabeçalho da Tabela
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(220, 220, 220);
$pdf->SetTextColor(0);
$pdf->SetDrawColor(128);
$w = [50, 60, 30, 25, 25]; // Larguras das colunas (Total 190mm)
$header = ['Fornecedor', 'Projeto', 'Serviço', 'Prazo', 'Custo'];

for($i = 0; $i < count($header); $i++) {
    $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
}
$pdf->Ln();

// Dados da Tabela
$pdf->SetFont('helvetica', '', 8);
$pdf->SetFillColor(255, 255, 255);
$pdf->SetTextColor(0);

if (empty($jobs_to_pay)) {
    $pdf->Cell(array_sum($w), 10, 'Nenhum trabalho pendente de pagamento encontrado.', 1, 0, 'C', 1);
    $pdf->Ln();
} else {
    $fill = 0; // Alternar cor de linha
    foreach($jobs_to_pay as $job) {
        
        $project_name = $pdf->GetStringWidth($job['project_title']) > $w[1]-5 ? substr($job['project_title'], 0, 35) . '...' : $job['project_title'];
        $freelancer_name = $pdf->GetStringWidth($job['freelancer_name']) > $w[0]-5 ? substr($job['freelancer_name'], 0, 28) . '...' : $job['freelancer_name'];
        $service_name = htmlspecialchars($service_types_list[$job['service_type']] ?? $job['service_type']);
        $date_display = $job['deadline'] ? date('d/m/Y', strtotime($job['deadline'])) : '-';
        
        // Alterna cor
        $fill = !$fill;
        $pdf->SetFillColor($fill ? 245 : 255);

        $pdf->Cell($w[0], 6, $freelancer_name, 'LR', 0, 'L', $fill);
        $pdf->Cell($w[1], 6, $project_name, 'LR', 0, 'L', $fill);
        $pdf->Cell($w[2], 6, $service_name, 'LR', 0, 'L', $fill);
        $pdf->Cell($w[3], 6, $date_display, 'LR', 0, 'C', $fill);
        $pdf->Cell($w[4], 6, formatCurrency($job['total_cost'], $job['currency']), 'LR', 0, 'R', $fill);
        $pdf->Ln();
    }
}
// Linha de fechamento da tabela
$pdf->Cell(array_sum($w), 0, '', 'T');


// --- Saída do PDF ---
// 'D' para forçar o Download
$pdf->Output('contas_a_pagar.pdf', 'D'); 

?>