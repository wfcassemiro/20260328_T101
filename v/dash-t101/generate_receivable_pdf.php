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
$today = new DateTime();
$currency = 'BRL';

// Inicializar categorias de "aging"
$aging_data = [
    'a_vencer' => [],
    'vencido_0_30' => [],
    'vencido_31_60' => [],
    'vencido_61_mais' => [],
];
$totals = [
    'a_vencer' => 0,
    'vencido_0_30' => 0,
    'vencido_31_60' => 0,
    'vencido_61_mais' => 0,
    'total_geral' => 0
];

// Lógica de busca de dados (Exatamente a mesma do report_accounts_receivable.php)
try {
    $stmt = $pdo->prepare(
        "SELECT c.company AS client_name, i.invoice_number, i.due_date, i.total_amount, i.currency
         FROM dash_invoices i
         JOIN dash_clients c ON i.client_id = c.id
         WHERE i.user_id = ? 
           AND i.status IN ('sent', 'overdue')
         ORDER BY i.due_date ASC"
    );
    $stmt->execute([$user_id]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($invoices as $invoice) {
        $due_date = new DateTime($invoice['due_date']);
        if ($due_date >= $today) {
            $aging_data['a_vencer'][] = $invoice;
            $totals['a_vencer'] += $invoice['total_amount'];
        } else {
            $days_overdue = $today->diff($due_date)->days;
            if ($days_overdue <= 30) {
                $aging_data['vencido_0_30'][] = $invoice;
                $totals['vencido_0_30'] += $invoice['total_amount'];
            } elseif ($days_overdue <= 60) {
                $aging_data['vencido_31_60'][] = $invoice;
                $totals['vencido_31_60'] += $invoice['total_amount'];
            } else {
                $aging_data['vencido_61_mais'][] = $invoice;
                $totals['vencido_61_mais'] += $invoice['total_amount'];
            }
        }
        $totals['total_geral'] += $invoice['total_amount'];
    }

} catch (Exception $e) {
    die("Erro ao gerar relatório: " . $e->getMessage());
}

// Classe PDF customizada
class MYPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 16);
        $this->Cell(0, 15, 'Relatório de Contas a Receber (Aging)', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(5);
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 15, 'Faturas em aberto em ' . date('d/m/Y'), 0, false, 'C', 0, '', 0, false, 'M', 'M');
    }
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
    
    // Função para desenhar a tabela de dados
    public function DrawTable($header, $data, $w) {
        $this->SetFillColor(230, 230, 230);
        $this->SetTextColor(0);
        $this->SetDrawColor(128, 128, 128);
        $this->SetFont('', 'B');
        for($i = 0; $i < count($header); ++$i) {
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
        }
        $this->Ln();

        // Dados
        $this->SetFont('', '');
        $this->SetFillColor(255, 255, 255);
        if (empty($data)) {
            $this->Cell(array_sum($w), 7, 'Nenhum item nesta categoria', 1, 0, 'C', 1);
            $this->Ln();
        } else {
            foreach($data as $row) {
                $this->Cell($w[0], 6, $this->GetStringWidth($row['client_name']) > $w[0] ? substr($row['client_name'], 0, 45) . '...' : $row['client_name'], 'LR', 0, 'L', 1);
                $this->Cell($w[1], 6, $row['invoice_number'], 'LR', 0, 'L', 1);
                $this->Cell($w[2], 6, date('d/m/Y', strtotime($row['due_date'])), 'LR', 0, 'C', 1);
                $this->Cell($w[3], 6, $row['currency'], 'LR', 0, 'C', 1);
                $this->Cell($w[4], 6, number_format($row['total_amount'], 2, ',', '.'), 'LR', 0, 'R', 1);
                $this->Ln();
            }
        }
        $this->Cell(array_sum($w), 0, '', 'T');
    }
}

// Iniciar PDF
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetTitle('Relatório de Contas a Receber');
$pdf->SetMargins(15, 27, 15);
$pdf->SetHeaderMargin(15);
$pdf->SetFooterMargin(15);
$pdf->SetAutoPageBreak(TRUE, 25);
$pdf->SetFont('helvetica', '', 10);
$pdf->AddPage();

// Cabeçalhos da tabela
$header = ['Cliente', 'Fatura', 'Vencimento', 'Moeda', 'Valor'];
$w = [80, 30, 30, 15, 25]; // Larguras

// Tabela 1: A Vencer
$pdf->SetFont('', 'B', 12);
$pdf->SetFillColor(230, 245, 230); // Verde claro
$pdf->Cell(array_sum($w), 8, 'A VENCER - Total: ' . formatCurrency($totals['a_vencer'], $currency), 1, 1, 'L', 1);
$pdf->DrawTable($header, $aging_data['a_vencer'], $w);
$pdf->Ln(5);

// Tabela 2: Vencido 0-30
$pdf->SetFont('', 'B', 12);
$pdf->SetFillColor(255, 245, 220); // Amarelo claro
$pdf->Cell(array_sum($w), 8, 'VENCIDO (0-30 dias) - Total: ' . formatCurrency($totals['vencido_0_30'], $currency), 1, 1, 'L', 1);
$pdf->DrawTable($header, $aging_data['vencido_0_30'], $w);
$pdf->Ln(5);

// Tabela 3: Vencido 31-60
$pdf->SetFont('', 'B', 12);
$pdf->SetFillColor(255, 235, 220); // Laranja claro
$pdf->Cell(array_sum($w), 8, 'VENCIDO (31-60 dias) - Total: ' . formatCurrency($totals['vencido_31_60'], $currency), 1, 1, 'L', 1);
$pdf->DrawTable($header, $aging_data['vencido_31_60'], $w);
$pdf->Ln(5);

// Tabela 4: Vencido 61+
$pdf->SetFont('', 'B', 12);
$pdf->SetFillColor(255, 220, 220); // Vermelho claro
$pdf->Cell(array_sum($w), 8, 'VENCIDO (61+ dias) - Total: ' . formatCurrency($totals['vencido_61_mais'], $currency), 1, 1, 'L', 1);
$pdf->DrawTable($header, $aging_data['vencido_61_mais'], $w);
$pdf->Ln(10);

// Total Geral
$pdf->SetFont('', 'B', 14);
$pdf->SetFillColor(240, 240, 255); // Azul claro
$pdf->Cell(130, 10, 'TOTAL GERAL A RECEBER:', 1, 0, 'R', 1);
$pdf->Cell(50, 10, formatCurrency($totals['total_geral'], $currency), 1, 1, 'R', 1);


// Fechar e gerar o PDF
$pdf->Output('contas_a_receber.pdf', 'D');
exit;
?>