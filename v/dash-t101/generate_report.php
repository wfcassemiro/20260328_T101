"<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';

// Verificar se o usuário está logado
if (!isLoggedIn()) {
    die('Acesso não autorizado');
}

$user_id = $_SESSION['user_id'];

// Receber parâmetros
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$status_filter = $_POST['status_filter'] ?? 'all';

// Validar datas
if (empty($start_date) || empty($end_date)) {
    die('Datas inválidas');
}

// Obter configurações do usuário
$user_settings = getUserSettings($user_id);

// Construir query com filtros
$where_clause = \"WHERE p.user_id = ? AND p.created_at BETWEEN ? AND ?\";
$params = [$user_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59'];

if ($status_filter !== 'all') {
    $where_clause .= \" AND p.status = ?\";
    $params[] = $status_filter;
}

// Buscar projetos
$stmt = $pdo->prepare(\"
    SELECT 
        p.*,
        p.title AS project_name,
        c.company AS company_name,
        c.name AS contact_name
    FROM dash_projects p
    LEFT JOIN dash_clients c ON p.client_id = c.id
    $where_clause
    ORDER BY p.created_at DESC
\");
$stmt->execute($params);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular estatísticas do período
$total_projects = count($projects);
$total_revenue = 0;
$projects_by_status = [
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'cancelled' => 0
];

foreach ($projects as $project) {
    $total_revenue += $project['total_amount'] ?? 0;
    $status = $project['status'];
    if (isset($projects_by_status[$status])) {
        $projects_by_status[$status]++;
    }
}

// Gerar HTML do relatório
$html = generateReportHTML(
    $start_date,
    $end_date,
    $status_filter,
    $projects,
    $total_projects,
    $total_revenue,
    $projects_by_status,
    $user_settings
);

// Configurar headers para PDF
header('Content-Type: text/html; charset=utf-8');
echo $html;

/**
 * Gera o HTML do relatório
 */
function generateReportHTML($start_date, $end_date, $status_filter, $projects, $total_projects, $total_revenue, $projects_by_status, $user_settings) {
    $status_labels = [
        'all' => 'Todos',
        'pending' => 'Pendente',
        'in_progress' => 'Em andamento',
        'completed' => 'Concluídos'
    ];
    
    $status_text = $status_labels[$status_filter] ?? 'Todos';
    $currency = $user_settings['default_currency'] ?? 'BRL';
    
    $html = '<!DOCTYPE html>
<html lang=\"pt-BR\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>Relatório de Projetos - Dash-T101</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: \"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            color: #333;
        }
        
        .report-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .report-header {
            background: linear-gradient(135deg, #8e44ad, #9b59b6);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .report-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .report-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .report-info {
            background: #f8f9fa;
            padding: 30px 40px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #8e44ad;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .info-item label {
            display: block;
            font-size: 0.85rem;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .info-item .value {
            font-size: 1.2rem;
            color: #333;
            font-weight: 600;
        }
        
        .stats-section {
            padding: 40px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .stats-section h2 {
            font-size: 1.8rem;
            margin-bottom: 25px;
            color: #8e44ad;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            padding: 25px;
            border-radius: 16px;
            border: 2px solid #e9ecef;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #8e44ad;
            margin-bottom: 10px;
        }
        
        .stat-card .stat-label {
            font-size: 0.95rem;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .projects-section {
            padding: 40px;
        }
        
        .projects-section h2 {
            font-size: 1.8rem;
            margin-bottom: 25px;
            color: #8e44ad;
        }
        
        .projects-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .projects-table thead {
            background: linear-gradient(135deg, #8e44ad, #9b59b6);
            color: white;
        }
        
        .projects-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        
        .projects-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .projects-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-in_progress {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .value-highlight {
            font-weight: 700;
            color: #28a745;
        }
        
        .no-projects {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .no-projects-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .report-footer {
            background: #f8f9fa;
            padding: 30px 40px;
            text-align: center;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .btn-print {
            background: #8e44ad;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin: 20px;
            box-shadow: 0 4px 15px rgba(142, 68, 173, 0.3);
        }
        
        .btn-print:hover {
            background: #9b59b6;
            box-shadow: 0 6px 20px rgba(142, 68, 173, 0.4);
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .report-container {
                box-shadow: none;
                border-radius: 0;
            }
            
            .btn-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class=\"report-container\">
        <!-- Header -->
        <div class=\"report-header\">
            <h1>📊 Relatório de Projetos</h1>
            <p>Dash-T101 - Sistema de Gestão de Tradução</p>
        </div>
        
        <!-- Informações do Relatório -->
        <div class=\"report-info\">
            <div class=\"info-grid\">
                <div class=\"info-item\">
                    <label>Período</label>
                    <div class=\"value\">' . date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date)) . '</div>
                </div>
                <div class=\"info-item\">
                    <label>Status Filtrado</label>
                    <div class=\"value\">' . htmlspecialchars($status_text) . '</div>
                </div>
                <div class=\"info-item\">
                    <label>Data de Geração</label>
                    <div class=\"value\">' . date('d/m/Y H:i') . '</div>
                </div>
            </div>
        </div>
        
        <!-- Estatísticas -->
        <div class=\"stats-section\">
            <h2>📈 Resumo Estatístico</h2>
            <div class=\"stats-grid\">
                <div class=\"stat-card\">
                    <div class=\"stat-value\">' . $total_projects . '</div>
                    <div class=\"stat-label\">Total de Projetos</div>
                </div>
                <div class=\"stat-card\">
                    <div class=\"stat-value\">' . formatCurrency($total_revenue, $currency) . '</div>
                    <div class=\"stat-label\">Receita Total</div>
                </div>
                <div class=\"stat-card\">
                    <div class=\"stat-value\">' . $projects_by_status['in_progress'] . '</div>
                    <div class=\"stat-label\">Em Andamento</div>
                </div>
                <div class=\"stat-card\">
                    <div class=\"stat-value\">' . $projects_by_status['completed'] . '</div>
                    <div class=\"stat-label\">Concluídos</div>
                </div>
            </div>
        </div>
        
        <!-- Lista de Projetos -->
        <div class=\"projects-section\">
            <h2>📋 Detalhamento dos Projetos</h2>';
    
    if (empty($projects)) {
        $html .= '
            <div class=\"no-projects\">
                <div class=\"no-projects-icon\">📁</div>
                <h3>Nenhum projeto encontrado</h3>
                <p>Não há projetos para o período e filtros selecionados.</p>
            </div>';
    } else {
        $html .= '
            <table class=\"projects-table\">
                <thead>
                    <tr>
                        <th>Projeto</th>
                        <th>Cliente</th>
                        <th>Status</th>
                        <th>Idiomas</th>
                        <th>Valor</th>
                        <th>Prazo</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($projects as $project) {
            $status_class = 'status-' . $project['status'];
            $status_label_map = [
                'pending' => 'Pendente',
                'in_progress' => 'Em Andamento',
                'completed' => 'Concluído',
                'cancelled' => 'Cancelado'
            ];
            $status_label = $status_label_map[$project['status']] ?? $project['status'];
            
            $html .= '
                <tr>
                    <td><strong>' . htmlspecialchars($project['project_name']) . '</strong></td>
                    <td>' . htmlspecialchars($project['company_name'] ?? 'N/A') . '</td>
                    <td><span class=\"status-badge ' . $status_class . '\">' . $status_label . '</span></td>
                    <td>' . strtoupper($project['source_language']) . ' → ' . strtoupper($project['target_language']) . '</td>
                    <td class=\"value-highlight\">' . formatCurrency($project['total_amount'] ?? 0, $currency) . '</td>
                    <td>' . ($project['deadline'] ? date('d/m/Y', strtotime($project['deadline'])) : '-') . '</td>
                </tr>';
        }
        
        $html .= '
                </tbody>
            </table>';
    }
    
    $html .= '
        </div>
        
        <!-- Footer -->
        <div class=\"report-footer\">
            <button class=\"btn-print\" onclick=\"window.print()\">🖨️ Imprimir / Salvar PDF</button>
            <p><strong>Dash-T101</strong> - Sistema de Gestão de Projetos de Tradução</p>
            <p>Relatório gerado automaticamente em ' . date('d/m/Y \à\s H:i') . '</p>
        </div>
    </div>
</body>
</html>';
    
    return $html;
}
"