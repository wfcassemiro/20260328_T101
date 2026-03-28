<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php'; // Cria $pdo
require_once __DIR__ . '/../config/dash_functions.php';

if (!isLoggedIn()) {
    exit('Acesso negado.');
}

$user_id = $_SESSION['user_id'];
$base_currency = 'BRL';

// Pegar datas do filtro (essencial para o CSV)
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$report_data = [];
$totals = ['revenue' => 0, 'cost' => 0, 'profit' => 0, 'margin' => 0, 'project_count' => 0];

// *** INÍCIO DA CORREÇÃO ***
// Função para traduzir os ENUMs
function translateServiceType($type) {
    $map = [
        'translation' => 'Tradução',
        'revision' => 'Revisão',
        'proofreading' => 'Revisão Simples',
        'localization' => 'Localização',
        'transcription' => 'Transcrição',
        'other' => 'Outro'
    ];
    return $map[$type] ?? ucfirst($type);
}
// *** FIM DA CORREÇÃO ***

try {
    // =========================================================================
    // || ETAPA 1: Buscar Taxas de Câmbio (Padrão: dash_settings)           ||
    // =========================================================================
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
    }
    
    $sql_rate_cases_revenue .= " ELSE p.total_amount END"; 
    $sql_rate_cases_cost .= " ELSE j.total_cost END";
    // =========================================================================

    $service_types_str = "'translation', 'revision', 'proofreading', 'localization', 'transcription', 'other'";

    // Inicializar array
    $all_service_types = ['translation', 'revision', 'proofreading', 'localization', 'transcription', 'other'];
    foreach ($all_service_types as $type) {
        $report_data[$type] = [
            'service_name' => translateServiceType($type),
            'revenue' => 0,
            'cost' => 0,
            'profit' => 0,
            'margin' => 0,
            'project_count' => 0
        ];
    }

    // ETAPA 2: Receita
    $stmt_revenue = $pdo->prepare(
        "SELECT 
            p.service_type, 
            SUM($sql_rate_cases_revenue) AS total_revenue_brl,
            COUNT(p.id) AS project_count
         FROM dash_projects p
         WHERE p.user_id = ? 
           AND p.status = 'completed'
           AND p.completed_date BETWEEN ? AND ?
           AND p.service_type IN ($service_types_str)
         GROUP BY p.service_type"
    );
    $stmt_revenue->execute([$user_id, $start_date, $end_date]);
    
    while ($row = $stmt_revenue->fetch(PDO::FETCH_ASSOC)) {
        $service_type = $row['service_type'];
        if (isset($report_data[$service_type])) {
            $report_data[$service_type]['revenue'] = (float)$row['total_revenue_brl'];
            $report_data[$service_type]['project_count'] = (int)$row['project_count'];
            $totals['revenue'] += $row['total_revenue_brl'];
            $totals['project_count'] += $row['project_count'];
        }
    }

    // ETAPA 3: Custo
    $stmt_cost = $pdo->prepare(
        "SELECT 
            p.service_type, 
            SUM($sql_rate_cases_cost) AS total_cost_brl
         FROM dash_jobs j
         JOIN dash_projects p ON j.project_id = p.id
         WHERE j.user_id = ?
           AND p.status = 'completed'
           AND p.completed_date BETWEEN ? AND ?
           AND p.service_type IN ($service_types_str)
         GROUP BY p.service_type"
    );
    $stmt_cost->execute([$user_id, $start_date, $end_date]);

    while ($row = $stmt_cost->fetch(PDO::FETCH_ASSOC)) {
        $service_type = $row['service_type'];
        if (isset($report_data[$service_type])) {
            $report_data[$service_type]['cost'] = (float)$row['total_cost_brl'];
            $totals['cost'] += (float)$row['total_cost_brl'];
        }
    }

    // ETAPA 4: Cálculos
    foreach ($report_data as $service_type => &$data) {
        if ($data['project_count'] == 0) {
            unset($report_data[$service_type]);
            continue;
        }
        $data['profit'] = $data['revenue'] - $data['cost'];
        $data['margin'] = ($data['revenue'] > 0) ? ($data['profit'] / $data['revenue']) * 100 : 0;
    }
    unset($data);
    
    $totals['profit'] = $totals['revenue'] - $totals['cost'];
    $totals['margin'] = ($totals['revenue'] > 0) ? ($totals['profit'] / $totals['revenue']) * 100 : 0;
    
    uasort($report_data, function($a, $b) {
        return $b['profit'] <=> $a['profit'];
    });

} catch (PDOException $e) {
    exit("Erro de banco de dados: " . $e->getMessage());
}

// ETAPA 5: Gerar CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="Dash-T101_Lucratividade_Servico_'.date('Y-m-d').'.csv"');
$output = fopen('php://output', 'w');

// Cabeçalho
fputcsv($output, ['Serviço', 'Nº Projetos', 'Receita (BRL)', 'Custo (BRL)', 'Lucro (BRL)', 'Margem (%)']);

// Dados
foreach ($report_data as $data) {
    fputcsv($output, [
        $data['service_name'], // Corrigido - já está traduzido
        $data['project_count'],
        number_format($data['revenue'], 2, '.', ''), // . para CSV
        number_format($data['cost'], 2, '.', ''),
        number_format($data['profit'], 2, '.', ''),
        number_format($data['margin'], 2, '.', '')
    ]);
}

// Rodapé (Totais)
fputcsv($output, [
    'TOTAL',
    $totals['project_count'],
    number_format($totals['revenue'], 2, '.', ''),
    number_format($totals['cost'], 2, '.', ''),
    number_format($totals['profit'], 2, '.', ''),
    number_format($totals['margin'], 2, '.', '')
]);

fclose($output);
exit;