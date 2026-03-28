<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';
require_once __DIR__ . '/../config/dash_functions.php';

// Verificar se o usuário está logado
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$page_title = 'Relatório de Lucratividade por Cliente'; // Título simplificado

// Definir datas padrão (últimos 30 dias)
$default_end_date = date('Y-m-d');
$default_start_date = date('Y-m-d', strtotime('-30 days'));

// Pegar datas do filtro (se houver)
$start_date = $_GET['start_date'] ?? $default_start_date;
$end_date = $_GET['end_date'] ?? $default_end_date;

// Inicializar array de resultados
$report_data = [];
$totals = ['revenue' => 0, 'cost' => 0, 'tax' => 0, 'profit' => 0, 'margin' => 0, 'project_count' => 0];
$rates_string = "1 BRL = 1.00 BRL"; // String para exibir as taxas
$base_currency = 'BRL';
$general_error = null;
$missing_rates_warning = null;
$hasData = false;


try {
    // ====
    // || ETAPA 1: Buscar Taxas de Câmbio Salvas (CORRIGIDO)    ||
    // ====
    $rates = ['BRL' => 1.0]; // Moeda base
    $sql_rate_cases_revenue_parts = []; // [NOVO] Array para as partes
    $sql_rate_cases_cost_parts = [];  // [NOVO] Array para as partes
    $rates_string_parts = [];

    $stmt_rates = $pdo->prepare("SELECT setting_key, setting_value FROM dash_settings WHERE user_id = ? AND setting_key LIKE 'rate_%'");
    $stmt_rates->execute([$user_id]);
    $db_rates = $stmt_rates->fetchAll(PDO::FETCH_ASSOC);

    if (empty($db_rates) && !$stmt_rates) {
        throw new Exception("Falha ao buscar taxas de câmbio.");
    }

    foreach ($db_rates as $rate) {
        $currency_code = strtoupper(str_replace('rate_', '', $rate['setting_key']));
        $rate_value = (float)$rate['setting_value'];
        if ($rate_value > 0) {
            $rates[$currency_code] = $rate_value; // Armazena a taxa
            if ($currency_code !== 'BRL') { 
                $sql_rate_cases_revenue_parts[] = " WHEN " . $pdo->quote($currency_code) . " THEN p.total_amount * " . floatval($rate_value);
                $sql_rate_cases_cost_parts[] = " WHEN " . $pdo->quote($currency_code) . " THEN j.total_cost * " . floatval($rate_value);
                $rates_string_parts[] = "1 $currency_code = $rate_value $base_currency";
            }
        }
    }

    // Montagem final da string SQL
    $sql_rate_cases_revenue = "CASE p.currency " . implode(" ", $sql_rate_cases_revenue_parts) . " WHEN 'BRL' THEN p.total_amount ELSE 0 END";
    $sql_rate_cases_cost = "CASE j.currency " . implode(" ", $sql_rate_cases_cost_parts) . " WHEN 'BRL' THEN j.total_cost ELSE 0 END";

    if (!empty($rates_string_parts)) {
        $rates_string = implode(' / ', $rates_string_parts);
    }
    // ====
    // || FIM DA CORREÇÃO    ||
    // ====


    // ====
    // || ETAPA 2: Buscar Receita (Revenue) e Imposto por Cliente    ||
    // ====
    $sql_revenue = "
        SELECT 
            p.client_id,
            c.company AS client_name,
            SUM($sql_rate_cases_revenue) AS total_revenue_brl,
            SUM($sql_rate_cases_revenue * (COALESCE(p.tax_percentage, 0) / 100)) AS total_tax_brl,
            COUNT(p.id) AS project_count
        FROM dash_projects p
        JOIN dash_clients c ON p.client_id = c.id
        WHERE 
            p.user_id = ? AND 
            p.status = 'completed' AND
            p.completed_date BETWEEN ? AND ?
        GROUP BY p.client_id, c.company
    ";
    
    $stmt_revenue = $pdo->prepare($sql_revenue);
    $stmt_revenue->execute([$user_id, $start_date, $end_date]);
    
    while ($row = $stmt_revenue->fetch(PDO::FETCH_ASSOC)) {
        $client_id = $row['client_id'];
        $report_data[$client_id] = [
            'client_name' => $row['client_name'],
            'revenue' => (float)$row['total_revenue_brl'],
            'tax' => (float)$row['total_tax_brl'],
            'cost' => 0, // Inicializa o custo
            'profit' => 0,
            'margin' => 0,
            'project_count' => (int)$row['project_count']
        ];
    }

    // ====
    // || ETAPA 3: Buscar Custo (Cost) por Cliente    ||
    // ====
    $sql_cost = "
        SELECT 
            p.client_id, 
            SUM($sql_rate_cases_cost) AS total_cost_brl
        FROM dash_jobs j
        JOIN dash_projects p ON j.project_id = p.id
        WHERE 
            p.user_id = ? AND 
            p.status = 'completed' AND
            p.completed_date BETWEEN ? AND ?
        GROUP BY p.client_id
    ";

    $stmt_cost = $pdo->prepare($sql_cost);
    $stmt_cost->execute([$user_id, $start_date, $end_date]);

    while ($row = $stmt_cost->fetch(PDO::FETCH_ASSOC)) {
        $client_id = $row['client_id'];
        if (isset($report_data[$client_id])) {
            $report_data[$client_id]['cost'] = (float)$row['total_cost_brl'];
        }
        // Se um cliente tiver custo mas não receita, ele é ignorado neste relatório
    }

    // ====
    // || ETAPA 4: Calcular Lucro, Margem e Totais    ||
    // ====
    if (!empty($report_data)) {
        $hasData = true;
    }
    
    foreach ($report_data as $client_id => &$data) {
        // Profit = Revenue - Cost - Tax (mirroring P&L logic)
        $data['profit'] = $data['revenue'] - $data['cost'] - $data['tax'];
        $data['margin'] = ($data['revenue'] > 0) ? ($data['profit'] / $data['revenue']) * 100 : 0;

        // Classe para cor da margem
        if ($data['margin'] > 25) $data['margin_class'] = 'status-completed'; // Verde
        elseif ($data['margin'] > 0) $data['margin_class'] = 'status-pending'; // Amarelo
        else $data['margin_class'] = 'status-cancelled'; // Vermelho/Cinza

        // Soma para os totais
        $totals['revenue'] += $data['revenue'];
        $totals['cost'] += $data['cost'];
        $totals['tax'] += $data['tax'];
        $totals['profit'] += $data['profit'];
        $totals['project_count'] += $data['project_count'];
    }
    unset($data); // Libera a referência

    // Margem média total
    $totals['margin'] = ($totals['revenue'] > 0) ? ($totals['profit'] / $totals['revenue']) * 100 : 0;

    // Classe para cor da margem total
    if ($totals['margin'] > 25) $totals['margin_class'] = 'status-completed';
    elseif ($totals['margin'] > 0) $totals['margin_class'] = 'status-pending';
    else $totals['margin_class'] = 'status-cancelled';

    // Ordenar dados por Lucro (maior primeiro)
    uasort($report_data, function($a, $b) {
        return $b['profit'] <=> $a['profit'];
    });

    if (count($db_rates) === 0) {
        $missing_rates_warning = "Nenhuma taxa de câmbio encontrada. Cálculos podem estar incorretos. Por favor, configure as taxas em 'Moedas'.";
    }

} catch (Exception $e) {
    $general_error = "Erro ao gerar relatório: " . $e->getMessage();
} catch (PDOException $e) {
    $general_error = "Erro de banco de dados: " . $e->getMessage();
}

// --- Fim da Lógica PHP ---

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">

    <div class="video-card profile-header-card" style="background: linear-gradient(135deg, var(--brand-purple), #4a148c); border: none; margin-bottom: 25px;">
        <div class="header-icon-container">
            <i class="fas fa-users" style="font-size: 1.8rem; color: #fff;"></i>
        </div>
        <div class="header-text-container" style="margin-left: 20px;">
            <h2 style="margin: 0 0 5px 0; padding: 0; font-size: 1.5rem; color: #fff; font-weight: 600; border: none;"><?php echo $page_title; ?></h2>
            <p style="margin: 0; color: rgba(255, 255, 255, 0.8); font-size: 1rem;">Projetos concluídos entre <?php echo date('d/m/Y', strtotime($start_date)); ?> e <?php echo date('d/m/Y', strtotime($end_date)); ?></p>
        </div>
    </div>
    
    <div class="report-nav-buttons">
        <a href="reports.php" class="vision-btn vision-btn-secondary"><i class="fas fa-arrow-left"></i> Voltar aos Relatórios</a>
        <a href="index.php" class="vision-btn vision-btn-secondary"><i class="fas fa-home"></i> Voltar ao Dash-T101</a>
    </div>

    <?php if ($general_error): ?>
        <div class="alert-error"><i class="fas fa-exclamation-triangle"></i><?php echo htmlspecialchars($general_error); ?></div>
    <?php endif; ?>
    <?php if ($missing_rates_warning): ?>
        <div class="alert-info"><i class="fas fa-info-circle"></i><?php echo htmlspecialchars($missing_rates_warning); ?> <a href="settings.php" style="color: white; font-weight: 600;">Cadastrar Moedas</a>.</div>
    <?php endif; ?>

    <div class="video-card">
        <h2><i class="fas fa-filter"></i> Filtrar Relatório</h2>
        <form method="GET" action="report_client_profitability.php" class="vision-form">
            <div class="form-row-flex">
                <div class="form-group">
                    <label for="start_date">Data de Início (Conclusão)</label>
                    <input type="date" id="start_date" name="start_date" class="vision-input" value="<?php echo htmlspecialchars($start_date); ?>" required>
                </div>
                <div class="form-group">
                    <label for="end_date">Data de Fim (Conclusão)</label>
                    <input type="date" id="end_date" name="end_date" class="vision-input" value="<?php echo htmlspecialchars($end_date); ?>" required>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="vision-btn vision-btn-primary">
                    <i class="fas fa-filter"></i> Filtrar
                </button>
                <a href="report_client_profitability.php" class="vision-btn vision-btn-secondary">Limpar (30 dias)</a>
            </div>
        </form>
    </div>

    <div class="video-card">
        <h2><i class="fas fa-coins"></i> Resumo Consolidado (<?php echo $base_currency; ?>)</h2>
        <div class="kpi-grid" style="padding: 20px 30px;">
            <div class="kpi-card-media kpi-revenue">
                <h4><i class="fas fa-dollar-sign"></i> Receita Total</h4>
                <h3><?php echo formatCurrency($totals['revenue'], $base_currency); ?></h3>
            </div>
            <div class="kpi-card-media kpi-cost">
                <h4><i class="fas fa-wallet"></i> Custo Total</h4>
                <h3><?php echo formatCurrency($totals['cost'], $base_currency); ?></h3>
            </div>
            <div class="kpi-card-media kpi-tax">
                <h4><i class="fas fa-file-invoice-dollar"></i> Imposto Total</h4>
                <h3><?php echo formatCurrency($totals['tax'], $base_currency); ?></h3>
            </div>
            <div class="kpi-card-media kpi-profit">
                <h4><i class="fas fa-chart-line"></i> Lucro Líquido</h4>
                <h3><?php echo formatCurrency($totals['profit'], $base_currency); ?></h3>
            </div>
            <div class="kpi-card-media kpi-margin">
                <h4><i class="fas fa-percentage"></i> Margem Líquida</h4>
                <h3><?php echo number_format($totals['margin'], 2, ',', '.'); ?>%</h3>
            </div>
        </div>
        <div class="alert-info" style="margin: 0 30px 30px; border-radius: 12px;">
            <i class="fas fa-info-circle"></i> Taxas de câmbio usadas: <?php echo htmlspecialchars($rates_string); ?>
        </div>
    </div>

    <div class="video-card">
        <div class="card-header-refined">
            <h2><i class="fas fa-list-ul"></i> Detalhamento por Cliente</h2>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="generate_client_profit_pdf.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                   id="btn_export_pdf_client" 
                   class="vision-btn vision-btn-secondary <?php echo $hasData ? '' : 'disabled-link'; ?>" 
                   style="background-color: #c0392b; border-color: #c0392b; color: #fff;"
                   target="_blank">
                    <i class="fas fa-file-pdf"></i> Exportar PDF
                </a>
                <button id="btn_export_csv_client" class="vision-btn vision-btn-secondary" <?php echo $hasData ? '' : 'disabled'; ?>>
                    <i class="fas fa-file-csv"></i> Exportar CSV
                </button>
            </div>
        </div>
        
        <div class="vision-table-container">
            <table class="vision-table" id="report_table">
                <thead>
                    <tr>
                        <th><i class="fas fa-user"></i> Cliente</th>
                        <th><i class="fas fa-hashtag"></i> Projetos</th>
                        <th><i class="fas fa-dollar-sign"></i> Receita (<?php echo $base_currency; ?>)</th>
                        <th><i class="fas fa-wallet"></i> Custo (<?php echo $base_currency; ?>)</th>
                        <th><i class="fas fa-file-invoice-dollar"></i> Imposto (<?php echo $base_currency; ?>)</th>
                        <th><i class="fas fa-chart-line"></i> Lucro Líquido (<?php echo $base_currency; ?>)</th>
                        <th><i class="fas fa-percentage"></i> Margem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($report_data)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">Nenhum dado encontrado para o período.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($report_data as $client_id => $data): ?>
                            <tr>
                                <td class="project-name-refined"><?php echo htmlspecialchars($data['client_name']); ?></td>
                                <td style="text-align: center;"><?php echo $data['project_count']; ?></td>
                                <td class="value-cell-refined" style="color: var(--accent-green);"><?php echo formatCurrency($data['revenue'], $base_currency); ?></td>
                                <td class="value-cell-refined" style="color: var(--accent-orange);"><?php echo formatCurrency($data['cost'], $base_currency); ?></td>
                                <td class="value-cell-refined" style="color: var(--accent-orange);"><?php echo formatCurrency($data['tax'], $base_currency); ?></td>
                                <td class="value-cell-refined" style="color: <?php echo ($data['profit'] >= 0) ? 'var(--accent-blue)' : 'var(--accent-orange)'; ?>;">
                                    <?php echo formatCurrency($data['profit'], $base_currency); ?>
                                </td>
                                <td>
                                    <span class="status-badge-refined <?php echo $data['margin_class']; ?>" style="font-size: 0.9rem;">
                                        <?php echo number_format($data['margin'], 2, ',', '.'); ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="table-totals">
                        <td colspan="2"><strong>Total Geral</strong></td>
                        <td><strong><?php echo formatCurrency($totals['revenue'], $base_currency); ?></strong></td>
                        <td><strong><?php echo formatCurrency($totals['cost'], $base_currency); ?></strong></td>
                        <td><strong><?php echo formatCurrency($totals['tax'], $base_currency); ?></strong></td>
                        <td><strong><?php echo formatCurrency($totals['profit'], $base_currency); ?></strong></td>
                        <td>
                            <span class="status-badge-refined <?php echo $totals['margin_class']; ?>" style="font-size: 0.9rem;">
                                <strong><?php echo number_format($totals['margin'], 2, ',', '.'); ?>%</strong>
                            </span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<style>
/* Alertas */
.alert-error { background: #ef4444; color: #fff; padding: 15px 30px; border-radius: 12px; margin-bottom: 20px; }
.alert-info { background: var(--accent-blue); color: #fff; padding: 15px 30px; border-radius: 12px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
.main-content .video-card { margin-bottom: 20px; }

/* Cards */
.video-card { background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.05)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; }
.video-card > h2 { margin: 0; padding: 25px 30px 20px; font-size: 1.3rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); }
.card-header-refined { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; padding: 25px 30px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); }
.card-header-refined h2 { margin: 0; font-size: 1.3rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 12px; }

/* Botões */
.report-nav-buttons { display: flex; gap: 15px; margin-bottom: 20px; }
.vision-btn { background: var(--brand-purple); color: white; border: 1px solid var(--brand-purple); border-radius: 20px; padding: 12px 24px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); }
.vision-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3); }
.vision-btn:disabled, .disabled-link { background: rgba(255, 255, 255, 0.1) !important; border-color: rgba(255, 255, 255, 0.1) !important; color: var(--text-muted) !important; cursor: not-allowed !important; }
.vision-btn-primary { background: var(--brand-purple); border-color: var(--brand-purple); }
.vision-btn-primary:hover { background: var(--brand-purple-dark); border-color: var(--brand-purple-dark); }
.vision-btn-secondary { background: rgba(255, 255, 255, 0.1); color: var(--text-primary); border-color: rgba(255, 255, 255, 0.2); }
.vision-btn-secondary:hover { background: rgba(255, 255, 255, 0.2) !important; border-color: rgba(255, 255, 255, 0.3) !important; color: #fff !important; }

/* Formulário */
.vision-form { padding: 20px 30px 30px; }
.form-row-flex { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px; }
.form-group { display: flex; flex-direction: column; gap: 8px; flex: 1 1 200px; }
.form-group label { font-weight: 600; color: var(--text-secondary); font-size: 0.9rem; }
.vision-input { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 12px 16px; color: var(--text-primary); font-size: 0.95rem; }
.form-actions { display: flex; gap: 12px; margin-top: 20px; }

/* Header */
.profile-header-card { display: flex; align-items: center; justify-content: center; }
.header-icon-container { background: rgba(255, 255, 255, 0.1); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.header-text-container { margin-left: 20px; }
.header-text-container h2 { margin: 0 0 5px 0; padding: 0; font-size: 1.5rem; color: #fff; font-weight: 600; border: none; }
.header-text-container p { margin: 0; color: rgba(255, 255, 255, 0.8); font-size: 1rem; }

/* Tabela */
.vision-table-container { margin: 0 30px 30px; overflow-x: auto; padding-bottom: 10px; }
.vision-table { width: 100%; border-collapse: collapse; }
.vision-table th { background: rgba(255, 255, 255, 0.05); border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding: 18px 20px; font-weight: 600; font-size: 0.9rem; color: var(--text-secondary); text-align: left; }
.vision-table th i { font-size: 0.8rem; margin-right: 6px; opacity: 0.7; }
.vision-table td { padding: 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.04); font-size: 0.95rem; vertical-align: middle; }
.vision-table tr:hover { background: rgba(255, 255, 255, 0.03); }
.project-name-refined { font-weight: 600; color: var(--text-primary); font-size: 1rem; }
.value-cell-refined { font-weight: 600; }
.vision-table tfoot tr.table-totals td { border-top: 2px solid var(--brand-purple); background: rgba(142, 68, 173, 0.1); font-size: 1.1rem; padding: 20px; }
.vision-table tfoot strong { color: var(--text-primary); }

/* KPI */
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; }
@media (min-width: 1200px) { .kpi-grid { grid-template-columns: repeat(5, 1fr); } }
.kpi-card-media { background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.05)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; padding: 25px; text-align: center; }
.kpi-card-media h4 { margin: 0 0 10px 0; font-size: 1rem; font-weight: 600; color: var(--text-secondary); display: flex; align-items: center; gap: 8px; justify-content: center; }
.kpi-card-media h4 i { color: #FFD700; }
.kpi-card-media h3 { margin: 0 0 8px 0; font-size: 2rem; font-weight: 700; }
.kpi-revenue h3 { color: var(--accent-green) !important; }
.kpi-cost h3 { color: var(--accent-orange) !important; }
.kpi-tax h3 { color: var(--accent-orange) !important; }
.kpi-profit h3 { color: var(--accent-blue); }
.kpi-margin h3 { color: var(--accent-cyan); }

/* Status Badges (para Margem) */
.status-badge-refined { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: capitalize; background: rgba(255, 255, 255, 0.1); color: var(--text-secondary); }
.status-badge-refined.status-pending { background: rgba(255, 193, 7, 0.1); color: #ffca28; } /* Amarelo (Margem > 0) */
.status-badge-refined.status-completed { background: rgba(67, 160, 71, 0.1); color: #81c784; } /* Verde (Margem > 25) */
.status-badge-refined.status-cancelled { background: rgba(239, 83, 80, 0.1); color: #ef5350; } /* Vermelho (Margem < 0) */
</style>

<script>
    // --- Lógica de Exportação CSV ---
    function exportClientProfitTableToCSV(filename) {
        let csv = [];
        const rows = document.querySelectorAll("#report_table tr");
        
        // Cabeçalho
        let header = [];
        const ths = rows[0].querySelectorAll("th");
        ths.forEach(th => {
            let thText = th.innerText.trim();
            header.push('"' + thText.replace(/"/g, '""') + '"');
        });
        csv.push(header.join(','));
        
        // Body
        const bodyRows = document.querySelectorAll("#report_table tbody tr");
        bodyRows.forEach(row => {
            let rowData = [];
            row.querySelectorAll("td").forEach(td => {
                let data = td.innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/\s+/g, " ").trim();
                rowData.push('"' + data.replace(/"/g, '""') + '"');
            });
            csv.push(rowData.join(','));
        });

        // Footer (Totals)
        const tfootRows = document.querySelectorAll("#report_table tfoot tr");
        tfootRows.forEach(row => {
            let rowData = [];
            row.querySelectorAll("td").forEach(td => {
                let data = td.innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/\s+/g, " ").trim();
                rowData.push('"' + data.replace(/"/g, '""') + '"');
            });
            csv.push(rowData.join(','));
        });

        // Download
        const csvFile = new Blob(["\uFEFF" + csv.join('\n')], { type: 'text/csv;charset=utf-8;' }); 
        
        if (window.navigator.msSaveOrOpenBlob) {
            window.navigator.msSaveOrOpenBlob(csvFile, filename);
        } else {
            const downloadLink = document.createElement("a");
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.download = filename;
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const btnCsv = document.getElementById('btn_export_csv_client');
        if (btnCsv) {
            btnCsv.addEventListener('click', function() {
                if (this.disabled) return;
                exportClientProfitTableToCSV('lucratividade_por_cliente.csv');
            });
        }
        
        const btnPdf = document.getElementById('btn_export_pdf_client');
        if (btnPdf) {
            btnPdf.addEventListener('click', function(e) {
                if (this.classList.contains('disabled-link')) {
                    e.preventDefault(); 
                }
            });
        }
    });
</script>

<?php
include __DIR__ . '/../vision/includes/footer.php';
?>