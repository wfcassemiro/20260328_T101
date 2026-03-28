<?php
session_start();

// --- CORREÇÃO DE FUSO HORÁRIO ---
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';

if (!function_exists('isLoggedIn') || !isLoggedIn()) {
    die('Acesso negado. Por favor, faça login.');
}

$user_id = $_SESSION['user_id'];

$filters = [
    'start_date' => $_GET['start_date'] ?? null,
    'end_date'   => $_GET['end_date'] ?? null,
    'status'     => $_GET['status'] ?? 'all',
    'client_id'  => $_GET['client_id'] ?? null,
    'currency'   => $_GET['currency'] ?? null,
    'min_value'  => $_GET['min_value'] ?? null,
    'max_value'  => $_GET['max_value'] ?? null,
];

if (!$filters['start_date'] || !$filters['end_date']) {
    die('Parâmetros de data ausentes.');
}

try {
    global $pdo;

    // --- BUSCAR NOME DO USUÁRIO ---
    $user_name_for_file = 'usuario';
    try {
        $stmt_user = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $stmt_user->execute([$user_id]);
        $user = $stmt_user->fetch();
        if ($user && !empty($user['name'])) {
            $safe_name = str_replace(' ', '_', $user['name']);
            $user_name_for_file = preg_replace('/[^A-Za-z0-9_.-]/', '', $safe_name);
        }
    } catch (Exception $e) {
        error_log('Não foi possível buscar o nome do usuário para o relatório CSV: ' . $e->getMessage());
    }
    
    // Construir query
    $sql = "
        FROM dash_projects p
        LEFT JOIN dash_clients c ON c.id = p.client_id
        WHERE p.user_id = :uid
          AND DATE(p.created_at) BETWEEN :start AND :end
    ";

    $params = [
        ':uid'   => $user_id,
        ':start' => $filters['start_date'],
        ':end'   => $filters['end_date']
    ];

    if (!empty($filters['status']) && $filters['status'] !== 'all') {
        $sql .= " AND p.status = :status";
        $params[':status'] = $filters['status'];
    }
    if (!empty($filters['client_id'])) {
        $sql .= " AND p.client_id = :client_id";
        $params[':client_id'] = $filters['client_id'];
    }
    if (!empty($filters['currency'])) {
        $sql .= " AND p.currency = :currency";
        $params[':currency'] = $filters['currency'];
    }
    if (!empty($filters['min_value'])) {
        $sql .= " AND p.total_amount >= :min_value";
        $params[':min_value'] = $filters['min_value'];
    }
    if (!empty($filters['max_value'])) {
        $sql .= " AND p.total_amount <= :max_value";
        $params[':max_value'] = $filters['max_value'];
    }

    $projects_sql = "SELECT
                        p.id, p.title AS project_name, p.status, p.total_amount, p.created_at, p.currency,
                        p.source_language, p.target_language, c.company AS company_name
                    " . $sql . " ORDER BY p.created_at DESC";

    $stmt = $pdo->prepare($projects_sql);
    $stmt->execute($params);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_projects = count($projects);
    $total_revenue = 0;
    foreach ($projects as $p) {
        $total_revenue += (float)$p['total_amount'];
    }

    // Criar arquivo CSV
    $reports_dir = __DIR__ . '/generated_reports/';
    if (!file_exists($reports_dir)) {
        mkdir($reports_dir, 0755, true);
    }

    $filename = 'Relatório_projetos_' . date('d-m-Y_H\hi') . '_' . $user_name_for_file . '.csv';
    $filepath_on_server = $reports_dir . $filename;
    
    $output = fopen($filepath_on_server, 'w');
    
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, ['RELATÓRIO DE PROJETOS - DASH-T101'], ';');
    fputcsv($output, ['Gerado pelo Dash-T101, o sistema de gerenciamento de projetos da Translators101'], ';');
    fputcsv($output, [''], ';');
    fputcsv($output, ['Período', date('d/m/Y', strtotime($filters['start_date'])) . ' a ' . date('d/m/Y', strtotime($filters['end_date']))], ';');
    fputcsv($output, ['Data de Geração', date('d/m/Y H:i')], ';');
    fputcsv($output, [''], ';');
    fputcsv($output, ['ESTATÍSTICAS'], ';');
    fputcsv($output, ['Total de Projetos', $total_projects], ';');
    fputcsv($output, ['Receita Total', 'R$ ' . number_format($total_revenue, 2, ',', '.')], ';');
    fputcsv($output, [''], ';');
    fputcsv($output, [''], ';');
    fputcsv($output, ['Projeto', 'Cliente', 'Status', 'Idioma Origem', 'Idioma Destino', 'Valor', 'Moeda', 'Data de Criação'], ';');

    $status_labels = ['pending' => 'Pendente', 'in_progress' => 'Em Andamento', 'completed' => 'Concluído', 'cancelled' => 'Cancelado'];

    foreach ($projects as $project) {
        fputcsv($output, [
            $project['project_name'],
            $project['company_name'] ?? 'N/A',
            $status_labels[$project['status']] ?? $project['status'],
            strtoupper($project['source_language'] ?? ''),
            strtoupper($project['target_language'] ?? ''),
            number_format($project['total_amount'], 2, ',', '.'),
            $project['currency'],
            date('d/m/Y', strtotime($project['created_at']))
        ], ';');
    }
    
    fclose($output);

    // Salvar registro no banco de dados
    $file_size = filesize($filepath_on_server);

    // **CORREÇÃO APLICADA AQUI**
    $file_path_for_db = 'generated_reports/' . $filename;

    $stmt = $pdo->prepare(
        "INSERT INTO dash_reports (user_id, report_name, report_type, file_path, filters_json, total_projects, total_revenue, file_size)
         VALUES (?, ?, 'csv', ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $user_id, $filename, $file_path_for_db, json_encode($filters),
        $total_projects, $total_revenue, $file_size
    ]);

    // Fazer download do arquivo
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . $file_size);
    readfile($filepath_on_server);
    exit;

} catch (Exception $e) {
    error_log('Erro ao gerar CSV: ' . $e->getMessage());
    die('Erro ao gerar relatório CSV: ' . $e->getMessage());
}