<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php'; // Cria $pdo
require_once __DIR__ . '/../config/dash_functions.php';

// Carrega o TCPDF
require_once(__DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php');

if (!isLoggedIn()) {
    die("Acesso negado.");
}

$user_id = $_SESSION['user_id'];
$base_currency = 'BRL';

// Pegar datas do filtro
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// --- Lógica de busca de dados (Adaptada para agrupar por SERVIÇO com TAX) ---
$report_data = [];
$totals = ['revenue' => 0, 'cost' => 0, 'tax' => 0, 'profit' => 0, 'margin' => 0, 'project_count' => 0];
$rates_string = "1 BRL = 1.00 BRL";

try {
    // ====
    // || ETAPA 1: Buscar Taxas de Câmbio com Fallback ||
    // ====
    $rates = ['BRL' => 1.0];
    $rates_string_parts = [];

    $stmt_rates = $pdo->prepare("SELECT setting_key, setting_value FROM dash_settings WHERE user_id = ? AND setting_key LIKE 'rate_%'");
    $stmt_rates->execute([$user_id]);
    $db_rates = $stmt_rates->fetchAll(PDO::FETCH_ASSOC);

    if ($stmt_rates === false) {
        throw new Exception("Falha ao buscar taxas de câmbio.");
    }

    if (!empty($db_rates)) {
        foreach ($db_rates as $rate) {
            $currency_code = strtoupper(str_replace('rate_', '', $rate['setting_key']));
            $rate_value = (float)$rate['setting_value'];
            if ($rate_value > 0) {
                $rates[$currency_code] = $rate_value;
                if ($currency_code !== 'BRL') {
                    $rates_string_parts[] = "1 $currency_code = " . number_format($rate_value, 2, ',', '.') . " $base_currency";
                }
            }
        }
    }

    if (!empty($rates_string_parts)) {
        $rates_string = implode(' / ', $rates_string_parts);
    } else {
        $rates_string = "1 BRL = 1.00 BRL";
    }

    // Construção dos CASEs com fallback 1:1 para moedas desconhecidas
    $knownCurrencies = array_keys($rates);

    // Receita com fallback
    $sql_rate_cases_revenue = "
      CASE 
        " . implode(" ", array_map(function($cc) use ($pdo, $rates) {
            return "WHEN p.currency = " . $pdo->quote($cc) . " THEN p.total_amount * " . floatval($rates[$cc]);
        }, $knownCurrencies)) . "
        ELSE p.total_amount
      END
    ";

    // Custo com fallback
    $sql_rate_cases_cost = "
      CASE 
        " . implode(" ", array_map(function($cc) use ($pdo, $rates) {
            return "WHEN j.currency = " . $pdo->quote($cc) . " THEN j.total_cost * " . floatval($rates[$cc]);
        }, $knownCurrencies)) . "
        ELSE j.total_cost
      END
    ";

    // ====
    // || ETAPA 2: Obter Receita e Imposto AGRUPADO POR SERVIÇO ||
    // ====
    $stmt_revenue = $pdo->prepare(
        "SELECT 
            LOWER(COALESCE(p.service_type, 'other')) AS service_type,
            SUM($sql_rate_cases_revenue) AS total_revenue_brl,
            SUM(($sql_rate_cases_revenue) * (COALESCE(p.tax_percentage, 0) / 100)) AS total_tax_brl,
            COUNT(p.id) AS project_count
        FROM dash_projects p
        WHERE p.user_id = ? 
            AND p.status = 'completed'
            AND p.completed_date BETWEEN ? AND ?
        GROUP BY LOWER(COALESCE(p.service_type, 'other'))"
    );
    $stmt_revenue->execute([$user_id, $start_date, $end_date]);
    
    while ($row = $stmt_revenue->fetch(PDO::FETCH_ASSOC)) {
        $service_type = $row['service_type'] ?: 'other';
        $report_data[$service_type] = [
            'service_name' => translateServiceType($service_type),
            'revenue' => (float)$row['total_revenue_brl'],
            'tax' => (float)$row['total_tax_brl'],
            'cost' => 0,
            'profit' => 0,
            'margin' => 0,
            'project_count' => (int)$row['project_count']
        ];
        $totals['revenue'] += $row['total_revenue_brl'];
        $totals['tax'] += $row['total_tax_brl'];
        $totals['project_count'] += $row['project_count'];
    }

    // ====
    // || ETAPA 3: Obter Custo AGRUPADO POR SERVIÇO ||
    // ====
    $stmt_cost = $pdo->prepare(
        "SELECT 
            LOWER(COALESCE(p.service_type, 'other')) AS service_type,
            SUM($sql_rate_cases_cost) AS total_cost_brl
        FROM dash_jobs j
        JOIN dash_projects p ON j.project_id = p.id
        WHERE p.user_id = ?
            AND p.status = 'completed'
            AND p.completed_date BETWEEN ? AND ?
        GROUP BY LOWER(COALESCE(p.service_type, 'other'))"
    );
    $stmt_cost->execute([$user_id, $start_date, $end_date]);

    while ($row = $stmt_cost->fetch(PDO::FETCH_ASSOC)) {
        $service_type = $row['service_type'] ?: 'other';
        if (isset($report_data[$service_type])) {
            $report_data[$service_type]['cost'] = (float)$row['total_cost_brl'];
        } else {
            // Serviço com custo mas sem receita (improvável)
            $report_data[$service_type] = [
                'service_name' => translateServiceType($service_type),
                'revenue' => 0,
                'tax' => 0,
                'cost' => (float)$row['total_cost_brl'],
                'profit' => 0,
                'margin' => 0,
                'project_count' => 0
            ];
        }
        $totals['cost'] += (float)$row['total_cost_brl'];
    }

    // ====
    // || ETAPA 4: Calcular Lucro e Margem ||
    // ====
    foreach ($report_data as $service_type => &$data) {
        // Profit = Revenue - Cost - Tax (mirroring P&L logic)
        $data['profit'] = $data['revenue'] - $data['cost'] - $data['tax'];
        $data['margin'] = ($data['revenue'] > 0) ? ($data['profit'] / $data['revenue']) * 100 : 0;
    }
    unset($data);
    
    $totals['profit'] = $totals['revenue'] - $totals['cost'] - $totals['tax'];
    $totals['margin'] = ($totals['revenue'] > 0) ? ($totals['profit'] / $totals['revenue']) * 100 : 0;
    
    // Ordenar por lucro (maior primeiro)
    uasort($report_data, function($a, $b) { 
        return $b['profit'] <=> $a['profit']; 
    });

} catch (Exception $e) {
    die("Erro ao gerar relatório: " . $e->getMessage());
}

// Função para traduzir os ENUMs
function translateServiceType($type) {
    $map = [
        'translation'   => 'Tradução',
        'revision'      => 'Revisão',
        'proofreading'  => 'Revisão (Proofreading)',
        'localization'  => 'Localização',
        'interpretacao' => 'Interpretação',
        'transcription' => 'Transcrição',
        'mtpe'          => 'Pós-edição (MTPE)',
        'copywriting'   => 'Copywriting',
        'other'         => 'Outros'
    ];
    return $map[$type] ?? ucfirst($type);
}

// Classe PDF customizada
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

$pdf->header_title = 'Relatório de Lucratividade por Serviço';
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
$pdf->SetFont('helvetica', '', 9);
$pdf->AddPage();

// Cabeçalho da tabela (agora com coluna de Imposto)
$header = ['Serviço', 'Nº Proj.', 'Receita (BRL)', 'Custo (BRL)', 'Imposto (BRL)', 'Lucro (BRL)', 'Margem (%)'];
$w = [45, 18, 23, 23, 23, 23, 25]; // Total = 180
$pdf->SetFillColor(230, 230, 230);
$pdf->SetTextColor(0);
$pdf->SetDrawColor(128, 128, 128);
$pdf->SetFont('', 'B', 9);
for($i = 0; $i < count($header); ++$i) {
    $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
}
$pdf->Ln();

// Dados da tabela
$pdf->SetFont('', '', 8);
$pdf->SetFillColor(255, 255, 255);
if (empty($report_data)) {
    $pdf->Cell(array_sum($w), 10, 'Nenhum dado encontrado para o período.', 1, 0, 'C', 1);
    $pdf->Ln();
} else {
    foreach($report_data as $row) {
        $service_name = $pdf->GetStringWidth($row['service_name']) > $w[0] ? substr($row['service_name'], 0, 25) . '...' : $row['service_name'];
        
        $pdf->Cell($w[0], 6, $service_name, 'LR', 0, 'L', 1);
        $pdf->Cell($w[1], 6, $row['project_count'], 'LR', 0, 'C', 1);
        $pdf->Cell($w[2], 6, formatCurrency($row['revenue'], $base_currency), 'LR', 0, 'R', 1);
        $pdf->Cell($w[3], 6, formatCurrency($row['cost'], $base_currency), 'LR', 0, 'R', 1);
        $pdf->Cell($w[4], 6, formatCurrency($row['tax'], $base_currency), 'LR', 0, 'R', 1);
        $pdf->Cell($w[5], 6, formatCurrency($row['profit'], $base_currency), 'LR', 0, 'R', 1);
        $pdf->Cell($w[6], 6, number_format($row['margin'], 2, ',', '.') . '%', 'LR', 0, 'R', 1);
        $pdf->Ln();
    }
}

// Linha de fechamento
$pdf->Cell(array_sum($w), 0, '', 'T');
$pdf->Ln(1);

// Totais
$pdf->SetFont('', 'B', 9);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell($w[0], 7, 'TOTAL', 'LTB', 0, 'R', 1);
$pdf->Cell($w[1], 7, $totals['project_count'], 'TB', 0, 'C', 1);
$pdf->Cell($w[2], 7, formatCurrency($totals['revenue'], $base_currency), 'TB', 0, 'R', 1);
$pdf->Cell($w[3], 7, formatCurrency($totals['cost'], $base_currency), 'TB', 0, 'R', 1);
$pdf->Cell($w[4], 7, formatCurrency($totals['tax'], $base_currency), 'TB', 0, 'R', 1);
$pdf->Cell($w[5], 7, formatCurrency($totals['profit'], $base_currency), 'TB', 0, 'R', 1);
$pdf->Cell($w[6], 7, number_format($totals['margin'], 2, ',', '.') . '%', 'RTB', 0, 'R', 1);

// Fechar e gerar o PDF
$pdf->Output('lucratividade_servico.pdf', 'D');
exit;
?>