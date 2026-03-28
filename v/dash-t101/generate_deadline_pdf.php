<?php
session_start();
// Definir fuso horário para garantir a data/hora correta no rodapé
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

// --- Lógica de busca de dados (Mesma do relatório) ---

try {
    // 1. Consultar projetos
    $stmt_projects = $pdo->prepare("
        SELECT 
            p.id, 
            p.title, 
            p.deadline, 
            p.completed_date,
            c.name AS client_name
        FROM dash_projects p
        LEFT JOIN dash_clients c ON p.client_id = c.id
        WHERE p.user_id = ?
          AND p.status = 'completed'
          AND p.deadline IS NOT NULL
          AND p.completed_date IS NOT NULL
        ORDER BY p.completed_date DESC
    ");
    $stmt_projects->execute([$user_id]);
    $projects = $stmt_projects->fetchAll(PDO::FETCH_ASSOC);

    $hasData = !empty($projects);

    // 2. Processamento dos KPIs e Dados da Tabela
    $total_completed = 0;
    $total_late = 0;
    $total_on_time = 0; // No prazo ou adiantado
    $total_delay_days = 0;
    $report_data = [];

    if ($hasData) {
        $total_completed = count($projects);
        
        foreach ($projects as $project) {
            $deadline_time = strtotime($project['deadline']);
            $completed_time = strtotime($project['completed_date']);
            
            $deadline_day = strtotime(date('Y-m-d', $deadline_time));
            $completed_day = strtotime(date('Y-m-d', $completed_time));

            $diff_seconds = $completed_day - $deadline_day;
            $diff_days = (int)round($diff_seconds / (60 * 60 * 24));

            $status_label = '';
            $variation_label = '';
            $status_class = ''; // Para lógica de cor

            if ($diff_days < 0) {
                $status_label = 'Adiantado';
                $variation_label = $diff_days . ' dias';
                $status_class = 'status-early';
                $total_on_time++;
            } elseif ($diff_days == 0) {
                $status_label = 'No prazo';
                $variation_label = '0 dias';
                $status_class = 'status-on-time';
                $total_on_time++;
            } else {
                $status_label = 'Atrasado';
                $variation_label = '+' . $diff_days . ' dias';
                $status_class = 'status-late';
                $total_late++;
                $total_delay_days += $diff_days;
            }
            
            $report_data[] = [
                'id' => $project['id'],
                'title' => $project['title'],
                'client_name' => $project['client_name'] ?? 'N/A',
                'deadline' => date('d/m/Y', $deadline_time),
                'completed_date' => date('d/m/Y', $completed_time),
                'status_label' => $status_label,
                'variation_label' => $variation_label,
                'status_class' => $status_class
            ];
        }
    }

    $on_time_percentage = ($total_completed > 0) ? ($total_on_time / $total_completed) * 100 : 0;
    $late_percentage = ($total_completed > 0) ? ($total_late / $total_completed) * 100 : 0;
    $average_delay = ($total_late > 0) ? ($total_delay_days / $total_late) : 0;

} catch (PDOException $e) {
    die("Erro ao buscar dados: " . $e->getMessage());
}

// --- Classe PDF Customizada (para Cabeçalho e Rodapé) ---
class MYPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 16);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 15, 'Relatório de Desempenho de Prazos', 0, false, 'C', 0, '', 0, false, 'M', 'M');
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
$pdf->SetTitle('Desempenho de Prazos');
$pdf->SetSubject('Relatório de pontualidade de entregas');

$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->AddPage();

// --- Conteúdo do PDF ---

// Resumo (KPIs)
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 8, 'Resumo de Desempenho', 0, 1, 'L', true);
$pdf->Ln(2);

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(95, 7, 'Total de Projetos Concluídos: ' . $total_completed, 0, 0, 'L');
$pdf->SetTextColor(0, 100, 0); // Verde
$pdf->Cell(95, 7, 'Entregas no Prazo/Adiantadas: ' . number_format($on_time_percentage, 1) . '%', 0, 1, 'R');
$pdf->SetTextColor(192, 0, 0); // Vermelho
$pdf->Cell(95, 7, 'Entregas Atrasadas: ' . number_format($late_percentage, 1) . '%', 0, 0, 'L');
$pdf->SetTextColor(0, 0, 128); // Azul
$pdf->Cell(95, 7, 'Atraso Médio (dias): ' . number_format($average_delay, 1), 0, 1, 'R');

$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(8);


// Tabela de Trabalhos
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 8, 'Detalhamento de Entregas', 0, 1, 'L', true);
$pdf->Ln(2);

// Cabeçalho da Tabela
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(220, 220, 220);
$pdf->SetTextColor(0);
$pdf->SetDrawColor(128);
$w = [55, 45, 25, 25, 20, 20]; // Larguras das colunas (Total 190mm)
$header = ['Projeto', 'Cliente', 'Prazo', 'Entrega', 'Status', 'Variação'];

for($i = 0; $i < count($header); $i++) {
    $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
}
$pdf->Ln();

// Dados da Tabela
$pdf->SetFont('helvetica', '', 8);
$pdf->SetFillColor(255, 255, 255);
$pdf->SetTextColor(0);

if (!$hasData) {
    $pdf->Cell(array_sum($w), 10, 'Nenhum projeto concluído encontrado.', 1, 0, 'C', 1);
    $pdf->Ln();
} else {
    foreach($report_data as $data) {
        
        // Truncar nomes longos para o PDF
        $project_name = $pdf->GetStringWidth($data['title']) > $w[0]-5 ? substr($data['title'], 0, 30) . '...' : $data['title'];
        $client_name = $pdf->GetStringWidth($data['client_name']) > $w[1]-5 ? substr($data['client_name'], 0, 25) . '...' : $data['client_name'];
        
        // Definir cor da linha
        if ($data['status_class'] === 'status-late') {
            $pdf->SetFillColor(255, 240, 240); // Vermelho claro
            $pdf->SetTextColor(192, 0, 0);
        } else if ($data['status_class'] === 'status-early' || $data['status_class'] === 'status-on-time') {
            $pdf->SetFillColor(240, 255, 240); // Verde claro
            $pdf->SetTextColor(0, 100, 0);
        } else {
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetTextColor(0);
        }

        $pdf->Cell($w[0], 6, $project_name, 'LR', 0, 'L', 1);
        $pdf->Cell($w[1], 6, $client_name, 'LR', 0, 'L', 1);
        $pdf->Cell($w[2], 6, $data['deadline'], 'LR', 0, 'C', 1);
        $pdf->Cell($w[3], 6, $data['completed_date'], 'LR', 0, 'C', 1);
        $pdf->Cell($w[4], 6, $data['status_label'], 'LR', 0, 'C', 1);
        $pdf->Cell($w[5], 6, $data['variation_label'], 'LR', 0, 'R', 1);
        $pdf->Ln();
    }
}
// Linha de fechamento da tabela
$pdf->SetTextColor(0);
$pdf->Cell(array_sum($w), 0, '', 'T');


// --- Saída do PDF ---
$pdf->Output('desempenho_prazos.pdf', 'D'); 

?>