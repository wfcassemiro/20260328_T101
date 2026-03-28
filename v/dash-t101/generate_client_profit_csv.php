<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';
require_once __DIR__ . '/../config/dash_functions.php';

if (!isLoggedIn()) {
    die("Acesso negado.");
}

$user_id = $_SESSION['user_id'];
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Lógica de busca de dados (Exatamente a mesma do report_client_profitability.php)
$report_data = [];
$totals = ['revenue' => 0, 'cost' => 0, 'profit' => 0, 'margin' => 0];
$rates_string = "1 BRL = 1.00 BRL"; // String para exibir as taxas

try {
    // =========================================================================
    // || ETAPA 1: Buscar Taxas de Câmbio Salvas                          ||
    // =========================================================================
    $rates = ['brl' => 1.0]; // Moeda base
    $sql_rate_cases_revenue = "CASE p.currency "; // Para SQL de Receita
    $sql_rate_cases_cost = "CASE j.currency "; // Para SQL de Custo
    
    $stmt_rates = $pdo->prepare("SELECT setting_key, setting_value FROM dash_settings WHERE user_id = ? AND setting_key LIKE 'rate_%'");
    $stmt_rates->execute([$user_id]);
    $db_rates = $stmt_rates->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Adiciona padrões se não existirem
    if (!isset($db_rates['rate_usd'])) $db_rates['rate_usd'] = 1.0;
    if (!isset($db_rates['rate_eur'])) $db_rates['rate_eur'] = 1.0;
    
    foreach ($db_rates as $key => $value) {
        $code_lower = str_replace('rate_', '', $key); // "usd"
        $code_upper = strtoupper($code_lower); // "USD"
        
        $rates[$code_lower] = (float)$value;
        $sql_rate_cases_revenue .= " WHEN '{$code_upper}' THEN p.total_amount * {$value} ";
        $sql_rate_cases_cost .= " WHEN '{$code_upper}' THEN j.total_cost * {$value} ";
        
        $rates_string .= " | 1 {$code_upper} = " . number_format($value, 2, ',', '.') . " BRL";
    }
    
    $sql_rate_cases_revenue .= " ELSE p.total_amount END"; 
    $sql_rate_cases_cost .= " ELSE j.total_cost END";
    // =========================================================================


    // =========================================================================
    // || ETAPA 2: Obter Receita (Convertida para BRL)                      ||
    // =========================================================================
    $stmt_revenue = $pdo->prepare(
        "SELECT c.id AS client_id, c.company AS client_name, SUM($sql_rate_cases_revenue) AS total_revenue_brl
         FROM dash_projects p JOIN dash_clients c ON p.client_id = c.id
         WHERE p.user_id = ? AND p.status = 'completed' AND p.completed_date BETWEEN ? AND ?
         GROUP BY c.id, c.company"
    );
    $stmt_revenue->execute([$user_id, $start_date, $end_date]);
    while ($row = $stmt_revenue->fetch(PDO::FETCH_ASSOC)) {
        $report_data[$row['client_id']] = ['client_name' => $row['client_name'], 'revenue' => (float)$row['total_revenue_brl'], 'cost' => 0, 'profit' => 0, 'margin' => 0];
        $totals['revenue'] += $row['total_revenue_brl'];
    }

    // =========================================================================
    // || ETAPA 3: Obter Custo (Convertido para BRL)                        ||
    // =========================================================================
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

    // 4. Calcular Lucro e MARGEM
    foreach ($report_data as $client_id => $data) {
        $profit = $data['revenue'] - $data['cost'];
        $margin = ($data['revenue'] > 0) ? ($profit / $data['revenue']) * 100 : 0;
        $report_data[$client_id]['profit'] = $profit;
        $report_data[$client_id]['margin'] = $margin;
    }
    
    $totals['profit'] = $totals['revenue'] - $totals['cost'];
    $totals['margin'] = ($totals['revenue'] > 0) ? ($totals['profit'] / $totals['revenue']) * 100 : 0;
    
    uasort($report_data, function($a, $b) { return $b['profit'] <=> $a['profit']; });

} catch (Exception $e) {
    die("Erro ao gerar relatório: " . $e->getMessage());
}

// Gerar CSV
$filename = "lucratividade_cliente_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// Abre o "output" do PHP como um arquivo
$output = fopen('php://output', 'w');

// Adiciona o BOM do UTF-8 para o Excel ler acentos corretamente
fwrite($output, "\xEF\xBB\xBF");

// =========================================================================
// || NOVO: Linhas de Cabeçalho do Relatório com Taxas                  ||
// =========================================================================
fputcsv($output, ['Relatório de Lucratividade por Cliente']);
fputcsv($output, ['Período:', date('d/m/Y', strtotime($start_date)) . ' a ' . date('d/m/Y', strtotime($end_date))]);
fputcsv($output, ['Taxas Utilizadas (Base BRL):', str_replace(' | ', ', ', $rates_string)]);
fputcsv($output, []); // Linha em branco

// Cabeçalhos do CSV
fputcsv($output, [
    'Cliente', 
    'Receita (BRL)', 
    'Custo (BRL)', 
    'Lucro (BRL)', 
    'Margem (%)'
]);

// Dados
foreach ($report_data as $row) {
    fputcsv($output, [
        $row['client_name'],
        number_format($row['revenue'], 2, ',', '.'),
        number_format($row['cost'], 2, ',', '.'),
        number_format($row['profit'], 2, ',', '.'),
        number_format($row['margin'], 2, ',', '.') . '%'
    ]);
}

// Linha de Total
fputcsv($output, [
    'TOTAL',
    number_format($totals['revenue'], 2, ',', '.'),
    number_format($totals['cost'], 2, ',', '.'),
    number_format($totals['profit'], 2, ',', '.'),
    number_format($totals['margin'], 2, ',', '.') . '%'
]);

fclose($output);
exit;
?>