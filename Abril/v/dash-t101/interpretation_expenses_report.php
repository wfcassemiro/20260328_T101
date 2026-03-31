<?php
/**
 * interpretation_expenses_report.php - Versão Abril 2026
 * Relatório de Despesas de Interpretação por Período
 * Filtros: período, cliente, tipo de despesa, responsável
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';
require_once __DIR__ . '/../config/dash_functions.php';

if (!isLoggedIn()) { header('Location: /login.php'); exit; }

$user_id = $_SESSION['user_id'];

// --- FILTROS ---
$date_from   = $_GET['date_from'] ?? '';
$date_to     = $_GET['date_to'] ?? '';
$client_id   = $_GET['client'] ?? '';
$expense_type = $_GET['type'] ?? '';
$paid_by     = $_GET['paid_by'] ?? '';

// --- QUERY PRINCIPAL ---
$sql = "
    SELECT 
        e.id,
        e.expense_type,
        e.description,
        e.amount,
        e.paid_by,
        e.created_at,
        p.id as project_id,
        p.title as project_title,
        p.currency,
        p.start_date,
        c.company as client_name
    FROM dash_interpretation_expenses e
    INNER JOIN dash_projects p ON e.project_id = p.id
    LEFT JOIN dash_clients c ON p.client_id = c.id
    WHERE e.user_id = ?
";
$params = [$user_id];

if ($date_from) { $sql .= " AND p.start_date >= ?"; $params[] = $date_from; }
if ($date_to)   { $sql .= " AND p.start_date <= ?"; $params[] = $date_to; }
if ($client_id) { $sql .= " AND p.client_id = ?"; $params[] = $client_id; }
if ($expense_type) { $sql .= " AND e.expense_type = ?"; $params[] = $expense_type; }
if ($paid_by)   { $sql .= " AND e.paid_by = ?"; $params[] = $paid_by; }

$sql .= " ORDER BY p.start_date DESC, e.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- TOTAIS ---
$total_all = 0;
$total_client = 0;
$total_interpreter = 0;
$totals_by_type = [];

foreach ($expenses as $exp) {
    $amt = (float)$exp['amount'];
    $total_all += $amt;
    if ($exp['paid_by'] === 'client') { $total_client += $amt; }
    else { $total_interpreter += $amt; }
    if (!isset($totals_by_type[$exp['expense_type']])) $totals_by_type[$exp['expense_type']] = 0;
    $totals_by_type[$exp['expense_type']] += $amt;
}

// --- CLIENTES (para filtro) ---
$stmt_cli = $pdo->prepare("SELECT id, company FROM dash_clients WHERE user_id = ? ORDER BY company ASC");
$stmt_cli->execute([$user_id]);
$clients = $stmt_cli->fetchAll(PDO::FETCH_ASSOC);

// Labels
$type_labels = ['travel' => 'Viagem', 'accommodation' => 'Hospedagem', 'food' => 'Alimentação', 'equipment' => 'Equipamento'];
$type_icons  = ['travel' => 'fa-plane', 'accommodation' => 'fa-hotel', 'food' => 'fa-utensils', 'equipment' => 'fa-tools'];
$paid_labels = ['client' => 'Cliente', 'interpreter' => 'Intérprete'];

// Descobrir tipos customizados existentes nos dados
foreach ($expenses as $exp) {
    if (!isset($type_labels[$exp['expense_type']])) {
        $type_labels[$exp['expense_type']] = ucfirst(str_replace(['custom_', '_'], ['', ' '], $exp['expense_type']));
        $type_icons[$exp['expense_type']] = 'fa-tag';
    }
}

// --- EXPORTAÇÃO CSV / EXCEL ---
$export = $_GET['export'] ?? '';
if ($export === 'csv' || $export === 'excel') {
    $separator = ($export === 'csv') ? ',' : "\t";
    $ext = ($export === 'csv') ? 'csv' : 'xls';
    $mime = ($export === 'csv') ? 'text/csv' : 'application/vnd.ms-excel';
    
    $filename = 'despesas_interpretacao_' . date('Y-m-d') . '.' . $ext;
    
    header('Content-Type: ' . $mime . '; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // BOM para UTF-8 no Excel
    echo "\xEF\xBB\xBF";
    
    // Cabeçalho
    $headers = ['Projeto', 'Cliente', 'Tipo', 'Descrição', 'Valor', 'Moeda', 'Responsável', 'Data Projeto'];
    echo implode($separator, $headers) . "\n";
    
    // Dados
    foreach ($expenses as $exp) {
        $row = [
            '"' . str_replace('"', '""', $exp['project_title']) . '"',
            '"' . str_replace('"', '""', $exp['client_name'] ?? '-') . '"',
            $type_labels[$exp['expense_type']] ?? $exp['expense_type'],
            '"' . str_replace('"', '""', $exp['description'] ?: '-') . '"',
            number_format($exp['amount'], 2, ',', ''),
            $exp['currency'],
            $paid_labels[$exp['paid_by']] ?? $exp['paid_by'],
            date('d/m/Y', strtotime($exp['start_date']))
        ];
        echo implode($separator, $row) . "\n";
    }
    
    // Totais
    echo "\n";
    echo implode($separator, ['', '', '', 'Total Geral', number_format($total_all, 2, ',', ''), '', '', '']) . "\n";
    echo implode($separator, ['', '', '', 'Total Cliente', number_format($total_client, 2, ',', ''), '', '', '']) . "\n";
    echo implode($separator, ['', '', '', 'Total Intérprete', number_format($total_interpreter, 2, ',', ''), '', '', '']) . "\n";
    
    exit;
}

$page_title = 'Relatório de Despesas - Dash-T101';
include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<style>
    .main-content { padding-bottom: 100px; }
    .video-card { background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.05)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; margin-bottom: 20px; }
    .profile-header-card { display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--brand-purple), #4a148c); padding: 20px; border: none; margin-bottom: 25px; }
    .header-icon-container { background: rgba(255,255,255,0.1); border-radius: 50%; width: 60px; height: 50px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .header-text-container { margin-left: 20px; }
    .header-text-container h2 { margin: 0 0 5px 0; padding: 0; font-size: 1.5rem; color: #fff; font-weight: 600; border: none; }
    .header-text-container p { margin: 0; color: rgba(255, 255, 255, 0.8); font-size: 1rem; }

    .vision-btn { background: var(--brand-purple); color: #fff; padding: 10px 20px; border-radius: 20px; border: 0; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; }
    .vision-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
    .vision-btn-secondary { background: rgba(255,255,255,0.1); }

    /* Filtros */
    .filter-form { padding: 20px 25px; }
    .filter-row { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
    .filter-group { flex: 1; min-width: 140px; display: flex; flex-direction: column; gap: 5px; }
    .filter-group label { font-size: 0.78rem; color: #aaa; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
    .vision-input, .vision-select { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); padding: 10px 14px; border-radius: 10px; color: #fff; width: 100%; box-sizing: border-box; font-size: 0.9rem; }

    /* Cards de Resumo */
    .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px; }
    .summary-card { background: linear-gradient(145deg, rgba(255,255,255,0.03), rgba(255,255,255,0.06)); border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 20px; display: flex; flex-direction: column; gap: 8px; }
    .summary-card .sc-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1rem; }
    .summary-card .sc-label { font-size: 0.78rem; color: #aaa; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
    .summary-card .sc-value { font-size: 1.4rem; font-weight: 800; color: #fff; font-family: monospace; }

    .sc-total .sc-icon { background: rgba(124, 77, 255, 0.15); color: #b388ff; }
    .sc-client .sc-icon { background: rgba(76, 175, 80, 0.15); color: #81c784; }
    .sc-client .sc-value { color: #81c784; }
    .sc-interpreter .sc-icon { background: rgba(255, 152, 0, 0.15); color: #ffb74d; }
    .sc-interpreter .sc-value { color: #ffb74d; }
    .sc-travel .sc-icon { background: rgba(64, 196, 255, 0.15); color: #40c4ff; }
    .sc-accommodation .sc-icon { background: rgba(171, 71, 188, 0.15); color: #ce93d8; }
    .sc-food .sc-icon { background: rgba(255, 183, 77, 0.15); color: #ffb74d; }
    .sc-equipment .sc-icon { background: rgba(149, 117, 205, 0.15); color: #9575cd; }

    /* Tabela */
    .vision-table { width: 100%; border-collapse: collapse; }
    .vision-table th { padding: 14px 18px; text-align: left; color: #aaa; border-bottom: 1px solid rgba(255,255,255,0.1); font-size: 0.85rem; font-weight: 600; }
    .vision-table td { padding: 14px 18px; border-bottom: 1px solid rgba(255,255,255,0.05); color: #fff; font-size: 0.9rem; }
    .vision-table tr:hover { background: rgba(255,255,255,0.02); }

    .type-badge { padding: 4px 10px; border-radius: 8px; font-size: 0.78rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
    .type-travel { background: rgba(64, 196, 255, 0.12); color: #40c4ff; }
    .type-accommodation { background: rgba(171, 71, 188, 0.12); color: #ce93d8; }
    .type-food { background: rgba(255, 183, 77, 0.12); color: #ffb74d; }
    .type-equipment { background: rgba(149, 117, 205, 0.12); color: #9575cd; }

    .paid-client { color: #81c784; font-weight: 600; }
    .paid-interpreter { color: #ffb74d; font-weight: 600; }

    .empty-state { text-align: center; padding: 50px 20px; color: #666; }
    .empty-state i { font-size: 2.5rem; margin-bottom: 15px; display: block; color: #444; }
    .empty-state p { font-size: 1rem; }

    /* Ordenação de colunas */
    .vision-table th[data-sort] { cursor: pointer; user-select: none; position: relative; padding-right: 28px; transition: color 0.2s; }
    .vision-table th[data-sort]:hover { color: #fff; }
    .vision-table th[data-sort]::after { content: '\f0dc'; font-family: 'Font Awesome 5 Free'; font-weight: 900; position: absolute; right: 6px; top: 50%; transform: translateY(-50%); font-size: 0.7rem; color: rgba(255,255,255,0.2); transition: color 0.2s; }
    .vision-table th[data-sort].asc::after { content: '\f0de'; color: var(--brand-purple-light, #b388ff); }
    .vision-table th[data-sort].desc::after { content: '\f0dd'; color: var(--brand-purple-light, #b388ff); }

    /* Rodapé de totais */
    .table-footer { display: flex; justify-content: flex-end; gap: 25px; padding: 18px 25px; border-top: 1px solid rgba(255,255,255,0.08); font-size: 0.9rem; }
    .table-footer span { color: #aaa; }
    .table-footer strong { font-family: monospace; font-size: 1rem; }
    /* Botões de Exportação */
    .btn-export { font-size: 0.85rem; padding: 8px 16px; border-radius: 10px; }
    .btn-csv { background: rgba(76, 175, 80, 0.15); border: 1px solid rgba(76, 175, 80, 0.3); color: #81c784; }
    .btn-csv:hover { background: rgba(76, 175, 80, 0.3); transform: translateY(-1px); }
    .btn-excel { background: rgba(64, 196, 255, 0.15); border: 1px solid rgba(64, 196, 255, 0.3); color: #40c4ff; }
    .btn-excel:hover { background: rgba(64, 196, 255, 0.3); transform: translateY(-1px); }
</style>

<div class="main-content">

    <div class="video-card profile-header-card">
        <div class="header-icon-container"><i class="fas fa-chart-bar" style="font-size: 1.6rem; color: #fff;"></i></div>
        <div class="header-text-container">
            <h2>Relatório de Despesas de Interpretação</h2>
            <p>Acompanhe todos os custos por período, cliente e tipo.</p>
        </div>
    </div>

    <?php
    // Montar query string atual para exportação (manter filtros)
    $export_params = [];
    if ($date_from) $export_params[] = 'date_from=' . urlencode($date_from);
    if ($date_to) $export_params[] = 'date_to=' . urlencode($date_to);
    if ($client_id) $export_params[] = 'client=' . urlencode($client_id);
    if ($expense_type) $export_params[] = 'type=' . urlencode($expense_type);
    if ($paid_by) $export_params[] = 'paid_by=' . urlencode($paid_by);
    $export_qs = !empty($export_params) ? '&' . implode('&', $export_params) : '';
    ?>
    <div style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; align-items:center;">
        <a href="index.php" class="vision-btn vision-btn-secondary"><i class="fas fa-home"></i> Voltar</a>
        <a href="reports.php" class="vision-btn vision-btn-secondary"><i class="fas fa-chart-line"></i> Relatórios</a>
        <a href="projects_list.php" class="vision-btn vision-btn-secondary"><i class="fas fa-folder-open"></i> Projetos</a>
        <?php if (!empty($expenses)): ?>
            <span style="color:#555; margin: 0 5px;">|</span>
            <a href="?export=csv<?php echo $export_qs; ?>" class="vision-btn btn-export btn-csv"><i class="fas fa-file-csv"></i> Exportar CSV</a>
            <a href="?export=excel<?php echo $export_qs; ?>" class="vision-btn btn-export btn-excel"><i class="fas fa-file-excel"></i> Exportar Excel</a>
        <?php endif; ?>
    </div>

    <!-- FILTROS -->
    <div class="video-card">
        <form method="GET" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label>De</label>
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>" class="vision-input">
                </div>
                <div class="filter-group">
                    <label>Até</label>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>" class="vision-input">
                </div>
                <div class="filter-group">
                    <label>Cliente</label>
                    <select name="client" class="vision-select">
                        <option value="">Todos</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($client_id == $c['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['company']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Tipo</label>
                    <select name="type" class="vision-select">
                        <option value="">Todos</option>
                        <?php foreach ($type_labels as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($expense_type == $key) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Responsável</label>
                    <select name="paid_by" class="vision-select">
                        <option value="">Todos</option>
                        <option value="client" <?php echo ($paid_by == 'client') ? 'selected' : ''; ?>>Cliente</option>
                        <option value="interpreter" <?php echo ($paid_by == 'interpreter') ? 'selected' : ''; ?>>Intérprete</option>
                    </select>
                </div>
                <div class="filter-group" style="flex:0; min-width: auto;">
                    <label>&nbsp;</label>
                    <div style="display:flex; gap:6px;">
                        <button type="submit" class="vision-btn"><i class="fas fa-filter"></i> Filtrar</button>
                        <?php if ($date_from || $date_to || $client_id || $expense_type || $paid_by): ?>
                            <a href="interpretation_expenses_report.php" class="vision-btn vision-btn-secondary"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- CARDS DE RESUMO -->
    <?php if (!empty($expenses)): ?>
    <div class="summary-grid">
        <div class="summary-card sc-total">
            <div class="sc-icon"><i class="fas fa-coins"></i></div>
            <span class="sc-label">Total Geral</span>
            <span class="sc-value"><?php echo number_format($total_all, 2, ',', '.'); ?></span>
        </div>
        <div class="summary-card sc-client">
            <div class="sc-icon"><i class="fas fa-user-tie"></i></div>
            <span class="sc-label">Cliente (Faturamento)</span>
            <span class="sc-value"><?php echo number_format($total_client, 2, ',', '.'); ?></span>
        </div>
        <div class="summary-card sc-interpreter">
            <div class="sc-icon"><i class="fas fa-user"></i></div>
            <span class="sc-label">Intérprete (Interno)</span>
            <span class="sc-value"><?php echo number_format($total_interpreter, 2, ',', '.'); ?></span>
        </div>
        <?php foreach ($totals_by_type as $tkey => $tval): ?>
            <?php if ($tval > 0): ?>
            <div class="summary-card sc-<?php echo $tkey; ?>">
                <div class="sc-icon"><i class="fas <?php echo $type_icons[$tkey]; ?>"></i></div>
                <span class="sc-label"><?php echo $type_labels[$tkey]; ?></span>
                <span class="sc-value"><?php echo number_format($tval, 2, ',', '.'); ?></span>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- TABELA DE DESPESAS -->
    <div class="video-card">
        <?php if (empty($expenses)): ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <p>Nenhuma despesa de interpretação encontrada para os filtros selecionados.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="vision-table">
                    <thead>
                        <tr>
                            <th data-sort="string">Projeto</th>
                            <th data-sort="string">Cliente</th>
                            <th data-sort="string">Tipo</th>
                            <th data-sort="string">Descrição</th>
                            <th data-sort="number">Valor</th>
                            <th data-sort="string">Responsável</th>
                            <th data-sort="date">Data Projeto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $exp): ?>
                        <tr>
                            <td data-sort-value="<?php echo htmlspecialchars($exp['project_title']); ?>">
                                <a href="projects.php?edit=<?php echo $exp['project_id']; ?>" style="color: var(--brand-purple-light, #b388ff); text-decoration: none; font-weight: 600;">
                                    <?php echo htmlspecialchars($exp['project_title']); ?>
                                </a>
                            </td>
                            <td data-sort-value="<?php echo htmlspecialchars($exp['client_name'] ?? ''); ?>"><?php echo htmlspecialchars($exp['client_name'] ?? '-'); ?></td>
                            <td data-sort-value="<?php echo $exp['expense_type']; ?>">
                                <span class="type-badge type-<?php echo $exp['expense_type']; ?>">
                                    <i class="fas <?php echo $type_icons[$exp['expense_type']]; ?>"></i>
                                    <?php echo $type_labels[$exp['expense_type']]; ?>
                                </span>
                            </td>
                            <td data-sort-value="<?php echo htmlspecialchars($exp['description'] ?? ''); ?>"><?php echo htmlspecialchars($exp['description'] ?: '-'); ?></td>
                            <td data-sort-value="<?php echo $exp['amount']; ?>" style="font-family:monospace; font-weight:bold;">
                                <?php echo number_format($exp['amount'], 2, ',', '.'); ?>
                                <small style="color:#888;"><?php echo $exp['currency']; ?></small>
                            </td>
                            <td data-sort-value="<?php echo $exp['paid_by']; ?>">
                                <?php if ($exp['paid_by'] === 'client'): ?>
                                    <span class="paid-client"><i class="fas fa-user-tie"></i> Cliente</span>
                                <?php else: ?>
                                    <span class="paid-interpreter"><i class="fas fa-user"></i> Intérprete</span>
                                <?php endif; ?>
                            </td>
                            <td data-sort-value="<?php echo $exp['start_date']; ?>"><?php echo date('d/m/Y', strtotime($exp['start_date'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-footer">
                <span>Total: <strong style="color:#fff;"><?php echo number_format($total_all, 2, ',', '.'); ?></strong></span>
                <span>Cliente: <strong style="color:#81c784;"><?php echo number_format($total_client, 2, ',', '.'); ?></strong></span>
                <span>Intérprete: <strong style="color:#ffb74d;"><?php echo number_format($total_interpreter, 2, ',', '.'); ?></strong></span>
                <span style="color:#888;">(<?php echo count($expenses); ?> registro<?php echo count($expenses) > 1 ? 's' : ''; ?>)</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('.vision-table');
    if (!table) return;
    const headers = table.querySelectorAll('th[data-sort]');
    const tbody = table.querySelector('tbody');
    if (!tbody) return;

    headers.forEach((th, colIdx) => {
        th.addEventListener('click', () => {
            const type = th.getAttribute('data-sort');
            const isAsc = th.classList.contains('asc');
            const dir = isAsc ? 'desc' : 'asc';

            headers.forEach(h => h.classList.remove('asc', 'desc'));
            th.classList.add(dir);

            const rows = Array.from(tbody.querySelectorAll('tr'));
            rows.sort((a, b) => {
                const cellA = a.cells[colIdx];
                const cellB = b.cells[colIdx];
                if (!cellA || !cellB) return 0;

                let valA = cellA.getAttribute('data-sort-value') || cellA.textContent.trim();
                let valB = cellB.getAttribute('data-sort-value') || cellB.textContent.trim();

                if (type === 'number') {
                    valA = parseFloat(valA) || 0;
                    valB = parseFloat(valB) || 0;
                    return dir === 'asc' ? valA - valB : valB - valA;
                }
                if (type === 'date') {
                    valA = new Date(valA) || 0;
                    valB = new Date(valB) || 0;
                    return dir === 'asc' ? valA - valB : valB - valA;
                }
                valA = valA.toLowerCase();
                valB = valB.toLowerCase();
                if (valA < valB) return dir === 'asc' ? -1 : 1;
                if (valA > valB) return dir === 'asc' ? 1 : -1;
                return 0;
            });

            rows.forEach(r => tbody.appendChild(r));
        });
    });
});
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>
