<?php
session_start();

// Ajuste os caminhos conforme a localização deste arquivo.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';
require_once __DIR__ . '/../config/dash_functions.php'; // Contém isLoggedIn, getDashboardStats, etc.

// Inicializar variáveis
$user_id = $_SESSION['user_id'] ?? null;
$error = '';
$message = '';

// ====
// INÍCIO DOS ENDPOINTS DA API
// ====

/**
 * Endpoint para buscar a lista de clientes do usuário.
 */
if (isset($_GET['api']) && $_GET['api'] === 'get_clients') {
    header('Content-Type: application/json; charset=utf-8');
    if (!function_exists('isLoggedIn') || !isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'not_authenticated']);
        exit;
    }

    try {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id, company FROM dash_clients WHERE user_id = :uid ORDER BY company ASC");
        $stmt->execute([':uid' => $_SESSION['user_id']]);
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['clients' => $clients]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'server_error', 'message' => $e->getMessage()]);
    }
    exit;
}

/**
 * Endpoint para buscar a lista de freelancers do usuário.
 */
if (isset($_GET['api']) && $_GET['api'] === 'get_freelancers') {
    header('Content-Type: application/json; charset=utf-8');
    if (!function_exists('isLoggedIn') || !isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'not_authenticated']);
        exit;
    }
    try {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id, name FROM dash_freelancers WHERE user_id = :uid AND is_active = 1 ORDER BY name ASC");
        $stmt->execute([':uid' => $_SESSION['user_id']]);
        $freelancers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['freelancers' => $freelancers]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'server_error', 'message' => $e->getMessage()]);
    }
    exit;
}

/**
 * Endpoint para gerar o relatório de projetos.
 */
if (isset($_GET['api']) && $_GET['api'] === 'projects_report') {
    header('Content-Type: application/json; charset=utf-8');
    if (!function_exists('isLoggedIn') || !isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'not_authenticated']);
        exit;
    }

    try {
        global $pdo;
        $user_id = $_SESSION['user_id'];
        $params = [];
        $kpi_params = [];

        // Base SQL
        $sql = "FROM dash_projects p LEFT JOIN dash_clients c ON p.client_id = c.id WHERE p.user_id = ?";
        $params[] = $user_id;
        $kpi_params[] = $user_id;

        // Filtros de Data (Obrigatório)
        if (empty($_GET['start_date']) || empty($_GET['end_date'])) {
            throw new Exception('Datas de início e fim são obrigatórias.');
        }
        $start_date = $_GET['start_date'];
        $end_date = $_GET['end_date'];
        $sql .= " AND p.created_at BETWEEN ? AND ?";
        $params[] = $start_date . ' 00:00:00';
        $params[] = $end_date . ' 23:59:59';
        
        $kpi_sql_date_filter = " AND p.created_at BETWEEN ? AND ?";
        $kpi_params[] = $start_date . ' 00:00:00';
        $kpi_params[] = $end_date . ' 23:59:59';

        // Outros Filtros (Opcionais)
        if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
            $sql .= " AND p.status = ?";
            $params[] = $_GET['status'];
            $kpi_sql_date_filter .= " AND p.status = ?";
            $kpi_params[] = $_GET['status'];
        }
        if (!empty($_GET['client_id'])) {
            $sql .= " AND p.client_id = ?";
            $params[] = $_GET['client_id'];
            $kpi_sql_date_filter .= " AND p.client_id = ?";
            $kpi_params[] = $_GET['client_id'];
        }
        if (!empty($_GET['currency'])) {
            $sql .= " AND p.currency = ?";
            $params[] = $_GET['currency'];
            $kpi_sql_date_filter .= " AND p.currency = ?";
            $kpi_params[] = $_GET['currency'];
        }
        if (!empty($_GET['min_value'])) {
            $sql .= " AND p.total_amount >= ?";
            $params[] = $_GET['min_value'];
            $kpi_sql_date_filter .= " AND p.total_amount >= ?";
            $kpi_params[] = $_GET['min_value'];
        }
        if (!empty($_GET['max_value'])) {
            $sql .= " AND p.total_amount <= ?";
            $params[] = $_GET['max_value'];
            $kpi_sql_date_filter .= " AND p.total_amount <= ?";
            $kpi_params[] = $_GET['max_value'];
        }

        // Paginação
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $items_per_page = 15;
        $offset = ($page - 1) * $items_per_page;

        // Total de itens
        $total_stmt = $pdo->prepare("SELECT COUNT(p.id) " . $sql);
        $total_stmt->execute($params);
        $total_items = $total_stmt->fetchColumn();
        $total_pages = ceil($total_items / $items_per_page);

        // Busca de projetos
        $projects_stmt = $pdo->prepare("SELECT p.id, p.title AS project_name, p.status, p.total_amount, p.currency, p.created_at, c.company AS company_name " . $sql . " ORDER BY p.created_at DESC LIMIT ? OFFSET ?");
        $params_with_pagination = $params;
        $params_with_pagination[] = $items_per_page;
        $params_with_pagination[] = $offset;
        $projects_stmt->execute($params_with_pagination);
        $projects = $projects_stmt->fetchAll(PDO::FETCH_ASSOC);

        // KPIs
        // CORREÇÃO AQUI: Removemos o 'FROM' extra dos selects, pois $kpi_base_sql já começa com 'FROM'
        $kpi_base_sql = "FROM dash_projects p WHERE p.user_id = ? " . $kpi_sql_date_filter;
        
        $kpi_revenue_stmt = $pdo->prepare("SELECT SUM(total_amount) AS total_revenue " . $kpi_base_sql . " AND p.currency = 'BRL'");
        $kpi_revenue_stmt->execute($kpi_params);
        $total_revenue_brl = $kpi_revenue_stmt->fetchColumn() ?: 0;
        
        $kpi_total_stmt = $pdo->prepare("SELECT COUNT(id) AS total_projects " . $kpi_base_sql);
        $kpi_total_stmt->execute($kpi_params);
        $total_projects = $kpi_total_stmt->fetchColumn() ?: 0;
        
        $kpi_inprogress_stmt = $pdo->prepare("SELECT COUNT(id) AS in_progress_count " . $kpi_base_sql . " AND p.status = 'in_progress'");
        $kpi_inprogress_stmt->execute($kpi_params);
        $in_progress_count = $kpi_inprogress_stmt->fetchColumn() ?: 0;
        
        $kpi_completed_stmt = $pdo->prepare("SELECT COUNT(id) AS completed_count " . $kpi_base_sql . " AND p.status = 'completed'");
        $kpi_completed_stmt->execute($kpi_params);
        $completed_count = $kpi_completed_stmt->fetchColumn() ?: 0;

        // Montar resposta
        $response = [
            'projects' => $projects,
            'kpis' => [
                'total_projects' => $total_projects,
                'total_revenue' => $total_revenue_brl,
                'in_progress_count' => $in_progress_count,
                'completed_count' => $completed_count,
            ],
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_items' => $total_items,
                'items_per_page' => $items_per_page,
            ],
        ];

        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => 'bad_request', 'message' => $e->getMessage()]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'server_error', 'message' => $e->getMessage()]);
    }
    exit;
}
// ====
// FIM DOS ENDPOINTS DA API
// ====

// Se não for uma chamada de API, continue carregando a página.

// Verificar se o usuário está logado (proteção da página principal)
if (!$user_id) {
    header('Location: /login.php');
    exit;
}

$page_title = 'Dash-T101';
$page_description = "Seu painel de controle financeiro e de projetos.";

// Buscar nome do usuário
$username = 'Usuário';
try {
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $username = $stmt->fetchColumn() ?: $username;
} catch (PDOException $e) {
    // Ignorar erro, continuar com nome padrão
}

// *** LÓGICA DE FILTRO DE PERÍODO ***
$period_days = 30; // Padrão
$start_date_filter = null;
$end_date_filter = null;
$active_filter = '30'; // Para o botão
$period_label = 'Último mês'; // Padrão corrigido

if (isset($_GET['filter']) && $_GET['filter'] === 'custom') {
    if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
        $start_date_filter = $_GET['start_date'];
        $end_date_filter = $_GET['end_date'];
        $active_filter = 'custom';

        $d1 = new DateTime($start_date_filter);
        $d2 = new DateTime($end_date_filter);
        $diff = $d2->diff($d1);
        $period_days = $diff->days; 
        $period_label = 'Personalizado (' . $d1->format('d/m/y') . ' - ' . $d2->format('d/m/y') . ')';
        
    } else {
        $active_filter = '30';
        $period_days = 30;
        $period_label = 'Último mês';
    }
} elseif (isset($_GET['period']) && is_numeric($_GET['period'])) {
    $period_days = (int)$_GET['period'];
    $active_filter = (string)$period_days;
    
    // Capitalização corrigida
    switch ($period_days) {
        case 7: $period_label = 'Última semana'; break;
        case 30: $period_label = 'Último mês'; break;
        case 90: $period_label = 'Último trimestre'; break;
        case 182: $period_label = 'Último semestre'; break;
        case 365: $period_label = 'Último ano'; break;
        default: $period_label = "Últimos $period_days dias";
    }
}
// *** FIM DA LÓGICA DE FILTRO ***


// *** FUNÇÃO PARA DADOS FINANCEIROS E GRÁFICOS ***
function getGeneralFinancials($pdo, $user_id, $period_days = 30, $start_date_override = null, $end_date_override = null) {
    
    // Definir as datas de início e fim
    if ($start_date_override && $end_date_override) {
        $start_date_sql = $start_date_override . ' 00:00:00';
        $end_date_sql = $end_date_override . ' 23:59:59';
    } else {
        $end_date_sql = date('Y-m-d H:i:s'); // Hoje
        $start_date_sql = date('Y-m-d H:i:s', strtotime("-$period_days days"));
    }
    
    $stats = [];
    
    // Buscar taxas de câmbio
    $rates = ['BRL' => 1.0]; // Moeda base
    try {
        $stmt_rates = $pdo->prepare("SELECT setting_key, setting_value FROM dash_settings WHERE user_id = ? AND setting_key LIKE 'rate_%'");
        $stmt_rates->execute([$user_id]);
        foreach ($stmt_rates->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $code = strtoupper(str_replace('rate_', '', $row['setting_key']));
            $rates[$code] = (float)$row['setting_value'];
        }
    } catch (Exception $e) {}
    
    $convert_to_brl = function($amount, $currency) use ($rates) {
        $rate = $rates[$currency] ?? 1.0; 
        return $amount * $rate;
    };

    // Cálculo de Imposto Total
    $total_tax_brl = 0;
    try {
        $stmt_tax = $pdo->prepare("SELECT total_amount, currency, tax_percentage FROM dash_projects WHERE user_id = :uid AND status = 'completed' AND completed_date BETWEEN :start AND :end");
        $stmt_tax->execute([':uid' => $user_id, ':start' => $start_date_sql, ':end' => $end_date_sql]);
        $completed_projects_tax = $stmt_tax->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($completed_projects_tax as $project) {
            $revenue_brl = $convert_to_brl($project['total_amount'], $project['currency']);
            if ($revenue_brl !== null) {
                $tax_percentage = (float)($project['tax_percentage'] ?? 0);
                $total_tax_brl += $revenue_brl * ($tax_percentage / 100);
            }
        }
    } catch (Exception $e) {
        $total_tax_brl = 0; 
    }
    $stats['total_tax_brl'] = $total_tax_brl;

    // 1. Total Faturado (Receita)
    $stmt_revenue = $pdo->prepare(
        "SELECT SUM(total_amount) AS total_revenue, currency 
         FROM dash_projects 
         WHERE user_id = ? AND status = 'completed' AND completed_date BETWEEN ? AND ? 
         GROUP BY currency"
    );
    $stmt_revenue->execute([$user_id, $start_date_sql, $end_date_sql]);
    $revenues = $stmt_revenue->fetchAll(PDO::FETCH_ASSOC);
    $total_revenue_brl = 0;
    foreach ($revenues as $r) {
        $total_revenue_brl += $convert_to_brl($r['total_revenue'], $r['currency']);
    }

    // 2. Total de Custo
    $stmt_cost = $pdo->prepare(
        "SELECT SUM(j.total_cost) AS total_cost, j.currency 
         FROM dash_jobs j
         JOIN dash_projects p ON j.project_id = p.id
         WHERE j.user_id = ? 
           AND p.status = 'completed' 
           AND p.completed_date BETWEEN ? AND ? 
         GROUP BY j.currency"
    );
    $stmt_cost->execute([$user_id, $start_date_sql, $end_date_sql]);
    $costs = $stmt_cost->fetchAll(PDO::FETCH_ASSOC);
    $total_cost_brl = 0;
    foreach ($costs as $c) {
        $total_cost_brl += $convert_to_brl($c['total_cost'], $c['currency']);
    }

    // 3. Projetos em Andamento
    $stmt_progress = $pdo->prepare("SELECT COUNT(id) AS in_progress_projects FROM dash_projects WHERE user_id = ? AND status = 'in_progress'");
    $stmt_progress->execute([$user_id]);
    $in_progress_projects = $stmt_progress->fetchColumn() ?: 0;

    // 4. Projetos Concluídos
    $stmt_completed = $pdo->prepare("SELECT COUNT(id) AS completed_projects FROM dash_projects WHERE user_id = ? AND status = 'completed' AND completed_date BETWEEN ? AND ?");
    $stmt_completed->execute([$user_id, $start_date_sql, $end_date_sql]);
    $completed_projects = $stmt_completed->fetchColumn() ?: 0;

    // 5. Dados do Gráfico
    $start_date_chart = $start_date_sql;
    $end_date_chart = $end_date_sql;
    
    $stmt_chart = $pdo->prepare(
        "SELECT DATE(completed_date) as date, SUM(total_amount) as daily_revenue, currency 
         FROM dash_projects 
         WHERE user_id = ? AND status = 'completed' AND completed_date BETWEEN ? AND ? 
         GROUP BY DATE(completed_date), currency 
         ORDER BY date ASC"
    );
    $stmt_chart->execute([$user_id, $start_date_chart, $end_date_chart]);
    $chart_data_raw = $stmt_chart->fetchAll(PDO::FETCH_ASSOC);

    // Formatar dados para o Chart.js
    $chart_data = ['labels' => [], 'data' => []];
    $period = new DatePeriod(
        new DateTime($start_date_chart), 
        new DateInterval('P1D'),
        (new DateTime($end_date_chart))->modify('+1 day') 
    );
    $revenue_map = [];
    foreach ($chart_data_raw as $row) {
        $date_key = $row['date'];
        if (!isset($revenue_map[$date_key])) {
            $revenue_map[$date_key] = 0;
        }
        $revenue_map[$date_key] += $convert_to_brl($row['daily_revenue'], $row['currency']);
    }
    foreach ($period as $value) {
        $date_key = $value->format('Y-m-d');
        $chart_data['labels'][] = $value->format('d/m');
        $chart_data['data'][] = $revenue_map[$date_key] ?? 0;
    }

    return [
        'total_revenue_brl' => $total_revenue_brl,
        'total_cost_brl' => $total_cost_brl,
        'in_progress_projects' => $in_progress_projects,
        'completed_projects' => $completed_projects,
        'chart_data' => $chart_data,
        'total_tax_brl' => $total_tax_brl
    ];
}

// Buscar estatísticas com base nos filtros definidos
$stats = getGeneralFinancials($pdo, $user_id, $period_days, $start_date_filter, $end_date_filter);

// Incluir cabeçalhos
include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<style>
    .main-content { padding-bottom: 20px; }
    .profile-header-card { display: flex; align-items: center; justify-content: center; }
    .dashboard-header { margin-bottom: 25px; }
    .dashboard-header h1 { font-size: 2.2rem; font-weight: 700; color: var(--text-primary); margin: 0 0 5px 0; }
    .dashboard-header p { font-size: 1.1rem; color: var(--text-secondary); margin: 0; }
    
    /* Filtros */
    .filter-bar { align-items: center; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; margin-top: 20px; }
    .filter-label { font-size: 0.9rem; font-weight: 500; color: var(--text-muted); }
    .filter-group { display: flex; background: rgba(255, 255, 255, 0.05); border-radius: 30px; padding: 5px; flex-wrap: wrap; }
    .filter-btn { color: var(--text-secondary); text-decoration: none; padding: 8px 18px; border-radius: 30px; font-size: 0.9rem; font-weight: 600; transition: all 0.3s ease; }
    .filter-btn:hover { background: rgba(255, 255, 255, 0.1); color: var(--text-primary); }
    .filter-btn.active { background: var(--brand-purple); color: #fff; box-shadow: 0 4px 12px rgba(142, 68, 173, 0.4); }

    /* KPIs */
    .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr) !important; gap: 20px; margin-bottom: 20px; }
    .kpi-card-grande, .kpi-card-media { 
        background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.05)); 
        border: 1px solid rgba(255, 255, 255, 0.08); 
        border-radius: 20px; 
        padding: 25px; 
        text-align: center;
        transition: all 0.3s ease;
    }
    .kpi-card-grande:hover, .kpi-card-media:hover { transform: translateY(-5px); border-color: var(--brand-purple-light); box-shadow: 0 10px 25px rgba(142, 68, 173, 0.4); }
    .kpi-card-grande h4, .kpi-card-media h4 { margin: 0 0 10px 0; font-size: 1rem; font-weight: 600; color: var(--text-secondary); display: flex; align-items: center; gap: 8px; justify-content: center; }
    .kpi-card-media h4 i { color: #FFD700; }
    .kpi-card-grande h3, .kpi-card-media h3 { margin: 0 0 8px 0; font-size: 2rem; font-weight: 700; }
    .kpi-card-grande .kpi-subtext, .kpi-card-media .kpi-subtext { font-size: 0.85rem; color: var(--text-muted); }
    
    /* Cores Específicas */
    .kpi-revenue h3 { color: var(--accent-green) !important; }
    .kpi-inprogress h3 { color: var(--accent-orange) !important; }
    .kpi-completed h3 { color: var(--accent-blue); }
    .kpi-cost h3 { color: var(--accent-orange) !important; }
    .kpi-tax h3 { color: var(--accent-orange) !important; }
    .kpi-profit h3 { color: var(--accent-blue); }
    .kpi-margin h3 { color: var(--accent-orange); }

    .chart-container { padding: 0 20px 20px; }
    
    /* Quick Links Grid */
    .quick-links-grid { 
        display: grid; 
        grid-template-columns: repeat(3, 1fr); 
        gap: 20px; 
        padding: 20px 25px; 
    }
    @media (max-width: 768px) {
        .quick-links-grid { 
            grid-template-columns: 1fr; 
        } 
    }

    .quick-link-card { 
        display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; 
        background: rgba(255, 255, 255, 0.05); border-radius: 16px; padding: 25px; 
        text-decoration: none; color: var(--text-secondary); transition: all 0.3s ease; 
        border: 1px solid rgba(255, 255, 255, 0.1); 
        position: relative; overflow: hidden;
    }
    .quick-link-card i { font-size: 1.8rem; color: #FFD700; }
    .quick-link-card span { font-size: 0.95rem; font-weight: 600; text-align: center; }
    .quick-link-card:hover { background: rgba(255, 255, 255, 0.1); color: var(--text-primary); transform: translateY(-5px); box-shadow: 0 8px 20px rgba(142, 68, 173, 0.35); border-color: var(--brand-purple-light); }

    /* INDICADOR "NOVO" */
    .new-badge {
        position: absolute; top: 10px; right: 10px;
        background: linear-gradient(135deg, #ff3d00, #ff9100);
        color: white; font-size: 0.6rem; font-weight: 800;
        padding: 3px 8px; border-radius: 10px;
        text-transform: uppercase; box-shadow: 0 2px 8px rgba(255, 61, 0, 0.4);
        animation: pulseNew 2s infinite;
    }
    @keyframes pulseNew { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } }

    /* Modal Report */
    .report-btn-highlight { margin-left: auto; display: inline-flex; align-items: center; gap: 10px; padding: 10px 18px; border-radius: 30px; border: 0; cursor: pointer; font-weight: 700; color: #fff; background: linear-gradient(135deg, #ff3d00, #ff9100); box-shadow: 0 8px 24px rgba(255, 61, 0, 0.35); position: relative; overflow: hidden; }
    .report-btn-highlight .glow { position: absolute; inset: -2px; background: radial-gradient(120px 40px at var(--mx, 50%) -20%, rgba(255,255,255,0.45), transparent 50%); mix-blend-mode: soft-light; pointer-events: none; transition: opacity .3s ease; opacity: 0; }
    .report-btn-highlight:hover .glow { opacity: 1; }
    .report-btn-highlight:hover { transform: translateY(-1px); box-shadow: 0 12px 28px rgba(255, 61, 0, 0.45);}
    
    .report-modal { position: fixed; inset: 0; display: none; z-index: 999; }
    .report-modal.active { display: block; }
    .report-modal-backdrop { position: absolute; inset: 0; background: rgba(10,10,20,0.6); backdrop-filter: blur(6px); opacity: 0; transition: opacity .25s ease; }
    .report-modal.active .report-modal-backdrop { opacity: 1; }
    .report-modal-dialog { position: relative; width: min(720px, 92vw); margin: 8vh auto; background: linear-gradient(160deg, rgba(35, 0, 60, 0.9), rgba(10, 10, 25, 0.9)); border: 1px solid rgba(180, 120, 255, 0.25); border-radius: 20px; box-shadow: 0 24px 60px rgba(0,0,0,0.5); transform: translateY(12px) scale(.98); opacity: 0; transition: transform .25s ease, opacity .25s ease; overflow: hidden; }
    /* Adiciona espaçamento interno para afastar o conteúdo das bordas */
    .report-modal-dialog {
        padding: 40px !important; /* Espaçamento generoso nas laterais e topo/base */
        box-sizing: border-box;  /* Garante que o padding não quebre a largura do modal */
    }
    
    /* Ajuste para telas menores (celular) para não perder muito espaço */
    @media (max-width: 768px) {
        .report-modal-dialog {
            padding: 20px !important;
        }
    }
    .report-modal.active .report-modal-dialog { transform: translateY(0) scale(1); opacity: 1; }
    
    /* Inputs/Selects */
    .vision-input, .vision-select { background: rgba(255, 255, 255, 0.05) !important; backdrop-filter: blur(20px) !important; border: 1px solid rgba(255, 255, 255, 0.1) !important; border-radius: 16px !important; padding: 12px 16px !important; color: var(--text-primary) !important; font-size: 0.95rem !important; outline: none !important; width: 100%; box-sizing: border-box; }
    input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); }

    /* Estilos para os botões do Modal (CORREÇÃO) */
    .vision-btn { 
        padding: 10px 20px; 
        border-radius: 30px; 
        border: 0; 
        cursor: pointer; 
        font-weight: 600; 
        transition: 0.2s; 
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    
    .vision-btn-secondary { 
        background: rgba(255, 255, 255, 0.1); 
        color: #fff; 
        border: 1px solid rgba(255, 255, 255, 0.2); 
    }
    
    .vision-btn-secondary:hover { 
        background: rgba(255, 255, 255, 0.2); 
        border-color: rgba(255, 255, 255, 0.3);
    }

    /* Estilos para a Tabela de Resultados do Relatório */
    .report-output {
        margin-top: 30px;
        background: rgba(255, 255, 255, 0.02);
        border-radius: 20px;
        padding: 20px;
        border: 1px solid rgba(255, 255, 255, 0.05);
    }

    .report-table { width: 100%; border-collapse: collapse; margin-top: 15px; color: #fff; }
    .report-table th { text-align: left; padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.1); color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase; }
    .report-table td { padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.95rem; }
    .report-table tr:last-child td { border-bottom: none; }
    
    .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; }
    .status-completed { background: rgba(46, 204, 113, 0.2); color: #2ecc71; }
    .status-in_progress { background: rgba(241, 196, 15, 0.2); color: #f1c40f; }
    .status-pending { background: rgba(52, 152, 219, 0.2); color: #3498db; }

    /* Media Queries */
    @media (max-width: 1200px) { .report-grid { grid-template-columns: 1fr; } }
    @media (max-width: 992px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } .report-grid { grid-template-columns: 1fr; } .kpi-grid { grid-template-columns: 1fr; } }
</style>

<div class="main-content">

    <div class="video-card profile-header-card" style="background: linear-gradient(135deg, var(--brand-purple), #4a148c); border: none; margin-bottom: 25px;">
        <div class="header-icon-container" style="background: rgba(255, 255, 255, 0.1); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <i class="fas fa-chart-pie" style="font-size: 1.8rem; color: #fff;"></i>
        </div>
        <div class="header-text-container" style="margin-left: 20px;">
            <h2 style="margin: 0 0 5px 0; padding: 0; font-size: 1.5rem; color: #fff; font-weight: 600; border: none;">Dash-T101</h2>
            <p style="margin: 0; color: rgba(255, 255, 255, 0.8); font-size: 1rem;">Controle seus projetos e finanças</p>
        </div>
    </div>

    <div class="video-card">
        <h2 style="padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1);"><i class="fas fa-rocket"></i> Acesso rápido</h2>
        <div class="quick-links-grid">
            
            <a href="projects.php" class="quick-link-card">
                <i class="fas fa-folder-plus"></i>
                <span>Criar projeto</span>
            </a>
            
            <a href="projects_list.php" class="quick-link-card">
                <i class="fas fa-list-alt"></i>
                <span>Ver projetos</span>
            </a>

            <a href="budget.php" class="quick-link-card">
                <span class="new-badge">Novo</span>
                <i class="fas fa-file-contract"></i>
                <span>Criar orçamento</span>
            </a>
            
            <a href="freelancers.php" class="quick-link-card">
                <i class="fas fa-user-plus"></i>
                <span>Adicionar fornecedor</span>
            </a>
            
            <a href="freelancers_list.php" class="quick-link-card">
                <i class="fas fa-users-cog"></i>
                <span>Ver fornecedores</span>
            </a>
            
            <a href="clients.php" class="quick-link-card">
                <i class="fas fa-building"></i>
                <span>Adicionar cliente</span>
            </a>
            
            <a href="clients_list.php" class="quick-link-card">
                <i class="fas fa-city"></i>
                <span>Ver clientes</span>
            </a>
            
            <a href="invoices.php" class="quick-link-card">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Criar fatura</span>
            </a>

            <a href="invoices_list.php" class="quick-link-card">
                <i class="fas fa-receipt"></i>
                <span>Ver faturas</span>
            </a>
            
            <a href="reports.php" class="quick-link-card">
                <i class="fas fa-chart-line"></i>
                <span>Relatórios</span>
            </a>

            <a href="time_tracker/time-tracker.php" class="quick-link-card">
                <span class="new-badge">Novo</span>
                <i class="fas fa-stopwatch"></i>
                <span>Time Tracker</span>
            </a>
            
            <a href="settings.php" class="quick-link-card">
                <i class="fas fa-coins"></i>
                <span>Moedas</span>
            </a>
            
        </div>
    </div>

    <div class="filter-bar" style="margin-top: 20px; display: block;">
        
        <form action="index.php" method="GET" id="period-filter-form">
            <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap; margin-bottom: 15px;">
                <span class="filter-label">Exibindo dados de:</span>
                <div class="filter-group">
                    <a href="index.php?period=7" class="filter-btn <?php echo ($active_filter == '7') ? 'active' : ''; ?>">Semana</a>
                    <a href="index.php?period=30" class="filter-btn <?php echo ($active_filter == '30') ? 'active' : ''; ?>">Mês</a>
                    <a href="index.php?period=90" class="filter-btn <?php echo ($active_filter == '90') ? 'active' : ''; ?>">Trimestre</a>
                    <a href="index.php?period=182" class="filter-btn <?php echo ($active_filter == '182') ? 'active' : ''; ?>">Semestre</a>
                    <a href="index.php?period=365" class="filter-btn <?php echo ($active_filter == '365') ? 'active' : ''; ?>">Ano</a>
                    <a href="#" id="btn-custom-period" class="filter-btn <?php echo ($active_filter == 'custom') ? 'active' : ''; ?>">Personalizado</a>
                </div>
                
                <div class="filter-bar-actions" style="margin-left: auto; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <div class="report-buttons-group" style="display: flex; gap: 10px;">
                        <button id="btn-open-report-modal" class="report-btn-highlight" type="button">
                            <i class="fas fa-file-alt"></i> <span>Mostrar relatório</span>
                            <span class="glow"></span>
                        </button>
                    </div>
                </div>
            </div>
            <div id="custom-period-fields" class="form-row" 
                 style="display: <?php echo ($active_filter == 'custom') ? 'grid' : 'none'; ?>; 
                        grid-template-columns: 1fr 1fr auto; 
                        gap: 20px; 
                        background: rgba(255, 255, 255, 0.05); 
                        padding: 20px; 
                        border-radius: 16px; 
                        border: 1px solid rgba(255, 255, 255, 0.1);
                        align-items: flex-end;">
                <input type="hidden" name="filter" value="custom">
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="start_date_filter">Data Inicial</label>
                    <input type="date" id="start_date_filter" name="start_date" class="vision-input" 
                           value="<?php echo htmlspecialchars($start_date_filter ?? ''); ?>">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="end_date_filter">Data Final</label>
                    <input type="date" id="end_date_filter" name="end_date" class="vision-input"
                           value="<?php echo htmlspecialchars($end_date_filter ?? ''); ?>">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <button type="submit" class="vision-btn vision-btn-primary">
                        <i class="fas fa-filter"></i> Aplicar
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php 
    $profit_brl = $stats['total_revenue_brl'] - $stats['total_cost_brl'] - $stats['total_tax_brl'];
    $profit_margin = ($stats['total_revenue_brl'] > 0) ? ($profit_brl / $stats['total_revenue_brl']) * 100 : 0;
    ?>

    <div class="kpi-grid">
        <div class="kpi-card-media kpi-revenue">
            <h4><i class="fas fa-dollar-sign"></i> Receita (<?php echo htmlspecialchars($period_label); ?>)</h4>
            <h3><?php echo formatCurrency($stats['total_revenue_brl'], 'BRL'); ?></h3>
            <span class="kpi-subtext">Projetos concluídos (consolidado em BRL)</span>
        </div>
        <div class="kpi-card-media kpi-cost">
            <h4><i class="fas fa-wallet"></i> Custo (<?php echo htmlspecialchars($period_label); ?>)</h4>
            <h3><?php echo formatCurrency($stats['total_cost_brl'], 'BRL'); ?></h3>
            <span class="kpi-subtext">Custos dos projetos (consolidado em BRL)</span>
        </div>
        <div class="kpi-card-media kpi-tax">
            <h4><i class="fas fa-file-invoice-dollar"></i> Imposto (<?php echo htmlspecialchars($period_label); ?>)</h4>
            <h3 id="kpi_total_tax"><?php echo formatCurrency($stats['total_tax_brl'], 'BRL'); ?></h3>
            <span class="kpi-subtext">Total de impostos (projetos concluídos)</span>
        </div>
        <div class="kpi-card-media kpi-profit">
            <h4><i class="fas fa-chart-line"></i> Lucro (<?php echo htmlspecialchars($period_label); ?>)</h4>
            <h3><?php echo formatCurrency($profit_brl, 'BRL'); ?></h3>
            <span class="kpi-subtext">Receita - Custo - Imposto (consolidado em BRL)</span>
        </div>
        <div class="kpi-card-media kpi-completed">
            <h4><i class="fas fa-check-circle"></i> Concluídos (<?php echo htmlspecialchars($period_label); ?>)</h4>
            <h3><?php echo $stats['completed_projects']; ?></h3>
            <span class="kpi-subtext">Projetos concluídos no período</span>
        </div>
        
        <div class="kpi-card-media kpi-margin">
            <h4><i class="fas fa-percentage"></i> Margem (<?php echo htmlspecialchars($period_label); ?>)</h4>
            <h3><?php echo number_format($profit_margin, 2, ',', '.'); ?>%</h3>
            <span class="kpi-subtext">Lucro/Receita (após impostos)</span>
        </div>
    </div>
    <div class="video-card">
        <h2><i class="fas fa-chart-bar"></i> Faturamento (<?php echo htmlspecialchars($period_label); ?>) - Concluídos</h2>
        <div class="chart-container">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <section id="report-output" class="report-output" hidden>
        <div id="report-content" class="report-content"></div>
        <div id="report-pagination" class="report-pagination-controls"></div>
    </section>

</div>

<div id="report-modal" class="report-modal" aria-hidden="true">
    <div class="report-modal-backdrop" data-close-modal></div>
    <div class="report-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="reportModalTitle">
        <div class="report-modal-header">
            <h3 id="reportModalTitle"><i class="fas fa-file-alt"></i> Gerar relatório de projetos</h3>
        </div>
        <form id="report-filters-form" class="report-form">
            
            <div class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div class="form-group">
                    <label for="start_date_report">Data inicial</label>
                    <input type="date" id="start_date_report" name="start_date" required>
                </div>
                <div class="form-group">
                    <label for="end_date_report">Data final</label>
                    <input type="date" id="end_date_report" name="end_date" required>
                </div>
            </div>
            
            <div class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div class="form-group">
                    <label for="status_report">Status do Projeto</label>
                    <select id="status_report" name="status">
                        <option value="all">Todos</option>
                        <option value="pending">Pendente</option>
                        <option value="in_progress">Em andamento</option>
                        <option value="completed">Concluído</option>
                        <option value="cancelled">Cancelado</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="client_id_report">Cliente</label>
                    <select id="client_id_report" name="client_id">
                        <option value="">Todos</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div class="form-group">
                    <label for="currency_report">Moeda</label>
                    <select id="currency_report" name="currency">
                        <option value="">Todas</option>
                        <option value="BRL">BRL</option>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="min_value">Valor mínimo</label>
                    <input type="number" id="min_value" name="min_value" step="0.01" placeholder="Ex: 100.50">
                </div>
                <div class="form-group">
                    <label for="max_value">Valor máximo</label>
                    <input type="number" id="max_value" name="max_value" step="0.01" placeholder="Ex: 5000.00">
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="vision-btn vision-btn-secondary" data-close-modal>Cancelar</button>
                <button type="button" id="btn-generate-report" class="report-btn-highlight">
                    <i class="fas fa-magnifying-glass-chart"></i> Gerar relatório
                </button>
            </div>
        </form>
        <p> </p>
        <span class="kpi-subtext">Atenção:</span>
        <span class="kpi-subtext">O relatório será exibido na parte de baixo da página.<p></p> Para relatórios impressos, use a função "Relatórios", no Dash-T101.</span>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- 1. Gráfico de Receita ---
    const ctx = document.getElementById('revenueChart');
    if (ctx) {
        try {
            const chartData = <?php echo json_encode($stats['chart_data'] ?? ['labels'=>[], 'data'=>[]]); ?>;
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Faturamento (BRL)',
                        data: chartData.data,
                        fill: true,
                        backgroundColor: 'rgba(142, 68, 173, 0.2)',
                        borderColor: 'rgba(142, 68, 173, 1)',
                        borderWidth: 2,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, ticks: { color: 'rgba(255, 255, 255, 0.7)' }, grid: { color: 'rgba(255, 255, 255, 0.1)' } },
                        x: { ticks: { color: 'rgba(255, 255, 255, 0.7)' }, grid: { display: false } }
                    },
                    plugins: { legend: { display: false } }
                }
            });
        } catch(e) { console.error("Erro ao carregar gráfico", e); }
    }

    // --- 2. Filtro de Período Personalizado ---
    const btnCustomPeriod = document.getElementById('btn-custom-period');
    const customFields = document.getElementById('custom-period-fields');
    if (btnCustomPeriod) {
        btnCustomPeriod.addEventListener('click', function(e) {
            e.preventDefault();
            customFields.style.display = (customFields.style.display === 'none' || customFields.style.display === '') ? 'grid' : 'none';
        });
    }

    // --- 3. Modal e Relatórios (A Lógica que Faltava) ---
    const modal = document.getElementById('report-modal');
    const openBtn = document.getElementById('btn-open-report-modal');
    const btnGenerate = document.getElementById('btn-generate-report');
    const reportOutput = document.getElementById('report-output');
    const reportContent = document.getElementById('report-content');
    const clientSelect = document.getElementById('client_id_report');

    // Funções do Modal
    function openModal() {
        modal.classList.add('active');
        loadClientFilter(); // Carrega clientes ao abrir
    }

    function closeModal() {
        modal.classList.remove('active');
    }

    if (openBtn) openBtn.addEventListener('click', openModal);
    
    // Fechar ao clicar fora ou no X
    modal.addEventListener('click', (e) => {
        if (e.target.hasAttribute('data-close-modal') || e.target.classList.contains('report-modal-backdrop')) {
            closeModal();
        }
    });

    // Carregar Clientes via API
    async function loadClientFilter() {
        // Se já tiver opções (além do "Todos"), não carrega de novo
        if (clientSelect.options.length > 1) return;

        try {
            const res = await fetch('index.php?api=get_clients');
            if (!res.ok) throw new Error('Erro na API');
            
            const data = await res.json();
            if (data.clients) {
                data.clients.forEach(client => {
                    const option = new Option(client.company, client.id);
                    clientSelect.add(option);
                });
            }
        } catch (err) {
            console.error('Falha ao carregar clientes:', err);
            clientSelect.innerHTML = '<option value="">Erro ao carregar</option>';
        }
    }

    // Gerar Relatório (Ação do Botão)
    if (btnGenerate) {
        btnGenerate.addEventListener('click', async function() {
            // Feedback visual de carregamento
            const originalText = btnGenerate.innerHTML;
            btnGenerate.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando...';
            btnGenerate.disabled = true;

            try {
                // Pegar dados do formulário
                const form = document.getElementById('report-filters-form');
                const formData = new FormData(form);
                const params = new URLSearchParams(formData);

                // Validar datas (básico)
                if(!formData.get('start_date') || !formData.get('end_date')) {
                    alert('Por favor, selecione as datas de início e fim.');
                    throw new Error('Datas inválidas');
                }

                // Chamar API
                const response = await fetch('index.php?api=projects_report&' + params.toString());
                const data = await response.json();

                if (data.error) throw new Error(data.message || 'Erro ao gerar relatório');

                // Renderizar Resultados
                renderReportTable(data.projects, data.kpis);
                
                // Mostrar a área de resultados e fechar modal
                reportOutput.hidden = false;
                closeModal();
                
                // Scroll até o resultado
                reportOutput.scrollIntoView({ behavior: 'smooth' });

            } catch (error) {
                console.error(error);
                if(error.message !== 'Datas inválidas') alert('Erro: ' + error.message);
            } finally {
                btnGenerate.innerHTML = originalText;
                btnGenerate.disabled = false;
            }
        });
    }

    // Função para desenhar a tabela HTML
    function renderReportTable(projects, kpis) {
        let html = `
            <div class="kpi-grid" style="margin-bottom: 20px;">
                <div class="kpi-card-media">
                    <h4>Projetos</h4>
                    <h3>${kpis.total_projects}</h3>
                </div>
                <div class="kpi-card-media kpi-revenue">
                    <h4>Receita (BRL)</h4>
                    <h3>R$ ${parseFloat(kpis.total_revenue).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</h3>
                </div>
                <div class="kpi-card-media kpi-completed">
                    <h4>Concluídos</h4>
                    <h3>${kpis.completed_count}</h3>
                </div>
            </div>
        `;

        if (!projects || projects.length === 0) {
            html += '<div style="text-align:center; padding:30px; color:#aaa;">Nenhum projeto encontrado para os filtros selecionados.</div>';
        } else {
            html += `
            <div style="overflow-x:auto;">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Projeto</th>
                            <th>Cliente</th>
                            <th>Status</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            projects.forEach(p => {
                const date = new Date(p.created_at).toLocaleDateString('pt-BR');
                const valor = parseFloat(p.total_amount).toLocaleString('pt-BR', {minimumFractionDigits: 2});
                
                // Tradução simples de status
                let statusLabel = p.status;
                if(p.status === 'completed') statusLabel = 'Concluído';
                if(p.status === 'in_progress') statusLabel = 'Em Andamento';
                if(p.status === 'pending') statusLabel = 'Pendente';

                html += `
                    <tr>
                        <td>${date}</td>
                        <td><strong>${p.project_name}</strong></td>
                        <td>${p.company_name || '-'}</td>
                        <td><span class="status-badge status-${p.status}">${statusLabel}</span></td>
                        <td>${p.currency} ${valor}</td>
                    </tr>
                `;
            });

            html += `</tbody></table></div>`;
        }

        reportContent.innerHTML = html;
    }

});
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>