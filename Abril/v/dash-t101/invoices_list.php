<?php
/**
 * invoices_list.php - Versão Abril 2026
 * Melhorias: Ordenação por colunas, botões Editar e Excluir
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';
require_once __DIR__ . '/../config/dash_functions.php';

if (!isLoggedIn()) { header('Location: /login.php'); exit; }

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// --- AÇÕES POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Excluir Fatura
    if ($action === 'delete_invoice') {
        $inv_id = $_POST['invoice_id'] ?? 0;
        try {
            // Remover itens e vínculos antes de excluir a fatura
            $pdo->prepare("DELETE FROM dash_invoice_items WHERE invoice_id = ?")->execute([$inv_id]);
            $pdo->prepare("DELETE FROM dash_invoice_projects WHERE invoice_id = ?")->execute([$inv_id]);
            $pdo->prepare("DELETE FROM dash_invoices WHERE id = ? AND user_id = ?")->execute([$inv_id, $user_id]);
            $_SESSION['temp_message'] = "Fatura excluída com sucesso!";
            header("Location: invoices_list.php"); exit;
        } catch (Exception $e) {
            $error = "Erro ao excluir: " . $e->getMessage();
        }
    }
}

if (isset($_SESSION['temp_message'])) { $message = $_SESSION['temp_message']; unset($_SESSION['temp_message']); }

// --- Filtros ---
$client_id = $_GET['client'] ?? '';
$status = $_GET['status'] ?? '';
$start_date = $_GET['start'] ?? '';
$end_date = $_GET['end'] ?? '';

$sql = "SELECT i.*, c.company as client_name 
        FROM dash_invoices i 
        LEFT JOIN dash_clients c ON i.client_id = c.id 
        WHERE i.user_id = ?";
$params = [$user_id];

if ($client_id) { $sql .= " AND i.client_id = ?"; $params[] = $client_id; }
if ($status) { $sql .= " AND i.status = ?"; $params[] = $status; }
if ($start_date) { $sql .= " AND i.date >= ?"; $params[] = $start_date; }
if ($end_date) { $sql .= " AND i.date <= ?"; $params[] = $end_date; }

$sql .= " ORDER BY i.date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Carregar Clientes para o Filtro
$clients = $pdo->prepare("SELECT id, company FROM dash_clients WHERE user_id = ? ORDER BY company");
$clients->execute([$user_id]);
$clients = $clients->fetchAll();

$page_title = 'Minhas Faturas - Dash-T101';
include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<style>
    .main-content { padding-bottom: 100px; }
    .profile-header-card { display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--brand-purple), #4a148c); padding: 20px; margin-bottom: 25px; }
    .header-icon-container { background: rgba(255,255,255,0.1); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .header-text-container { margin-left: 20px; }
    .header-text-container h2 { margin: 0; font-size: 1.5rem; color: #fff; }
    
    .video-card { background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.05)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; margin-bottom: 20px; }
    
    .vision-form-refined { padding: 20px 30px; }
    .form-row { display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; }
    .form-group { flex: 1; min-width: 150px; }
    .vision-input, .vision-select { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); padding: 10px 15px; border-radius: 10px; color: #fff; width: 100%; box-sizing: border-box; }
    
    .vision-table { width: 100%; border-collapse: collapse; }
    .vision-table th { padding: 15px 20px; text-align: left; color: #aaa; border-bottom: 1px solid rgba(255,255,255,0.1); font-size: 0.9rem; }
    .vision-table td { padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); color: #fff; }
    .vision-table tr:hover { background: rgba(255,255,255,0.02); }
    
    .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
    .status-draft { background: rgba(128,128,128,0.2); color: #bbb; }
    .status-sent { background: rgba(64, 196, 255, 0.15); color: #40c4ff; }
    .status-paid { background: rgba(102, 187, 106, 0.15); color: #66bb6a; }
    .status-overdue { background: rgba(255, 152, 0, 0.15); color: #ffb74d; }
    .status-cancelled { background: rgba(239, 83, 80, 0.15); color: #ef5350; }
    
    .action-btn { width: 34px; height: 34px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.05); color: #ccc; text-decoration: none; margin-right: 5px; transition:0.2s; border: 0; cursor: pointer; }
    .action-btn:hover { background: rgba(255,255,255,0.15); color: #fff; }
    .btn-del:hover { background: rgba(239, 83, 80, 0.2); color: #ef5350; }
    .btn-edit { color: #ffca28; }
    .btn-edit:hover { background: rgba(255, 202, 40, 0.2); }
    
    .vision-btn { background: var(--brand-purple); color: #fff; padding: 10px 20px; border-radius: 20px; border: 0; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; }
    .vision-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
    .vision-btn-secondary { background: rgba(255,255,255,0.1); }

    /* Ordenação de colunas */
    .vision-table th[data-sort] { cursor: pointer; user-select: none; position: relative; padding-right: 28px; transition: color 0.2s; }
    .vision-table th[data-sort]:hover { color: #fff; }
    .vision-table th[data-sort]::after { content: '\f0dc'; font-family: 'Font Awesome 5 Free'; font-weight: 900; position: absolute; right: 8px; top: 50%; transform: translateY(-50%); font-size: 0.7rem; color: rgba(255,255,255,0.2); transition: color 0.2s; }
    .vision-table th[data-sort].asc::after { content: '\f0de'; color: var(--brand-purple-light, #b388ff); }
    .vision-table th[data-sort].desc::after { content: '\f0dd'; color: var(--brand-purple-light, #b388ff); }
</style>

<div class="main-content">
    
    <div class="video-card profile-header-card">
        <div class="header-icon-container"><i class="fas fa-file-invoice-dollar" style="font-size: 1.8rem; color: #fff;"></i></div>
        <div class="header-text-container">
            <h2>Faturas</h2>
            <p>Histórico de cobranças emitidas.</p>
        </div>
    </div>

    <div style="display:flex; gap:10px; margin-bottom:20px;">
        <a href="projects_list.php" class="vision-btn vision-btn-secondary"><i class="fas fa-arrow-left"></i> Voltar aos Projetos</a>
        <a href="invoices.php" class="vision-btn"><i class="fas fa-plus"></i> Nova Fatura</a>
    </div>

    <?php if ($message): ?><div style="background:#22c55e; color:#fff; padding:15px; border-radius:10px; margin-bottom:20px;"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div style="background:#ef4444; color:#fff; padding:15px; border-radius:10px; margin-bottom:20px;"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div><?php endif; ?>

    <div class="video-card">
        <form method="GET" class="vision-form-refined">
            <div class="form-row">
                <div class="form-group">
                    <label style="color:#aaa; font-size:0.8rem;">Cliente</label>
                    <select name="client" class="vision-select">
                        <option value="">Todos</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($client_id==$c['id'])?'selected':''; ?>><?php echo htmlspecialchars($c['company']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label style="color:#aaa; font-size:0.8rem;">Status</label>
                    <select name="status" class="vision-select">
                        <option value="">Todos</option>
                        <option value="draft" <?php echo ($status=='draft')?'selected':''; ?>>Rascunho</option>
                        <option value="sent" <?php echo ($status=='sent')?'selected':''; ?>>Enviado</option>
                        <option value="paid" <?php echo ($status=='paid')?'selected':''; ?>>Pago</option>
                        <option value="overdue" <?php echo ($status=='overdue')?'selected':''; ?>>Vencido</option>
                        <option value="cancelled" <?php echo ($status=='cancelled')?'selected':''; ?>>Cancelado</option>
                    </select>
                </div>
                <div class="form-group">
                    <label style="color:#aaa; font-size:0.8rem;">De</label>
                    <input type="date" name="start" value="<?php echo $start_date; ?>" class="vision-input">
                </div>
                <div class="form-group">
                    <label style="color:#aaa; font-size:0.8rem;">Até</label>
                    <input type="date" name="end" value="<?php echo $end_date; ?>" class="vision-input">
                </div>
                <div class="form-group" style="flex:0;">
                    <button type="submit" class="vision-btn"><i class="fas fa-filter"></i> Filtrar</button>
                </div>
            </div>
        </form>
    </div>

    <div class="video-card">
        <div style="overflow-x:auto;">
            <table class="vision-table">
                <thead>
                    <tr>
                        <th data-sort="string">Número</th>
                        <th data-sort="string">Cliente</th>
                        <th data-sort="date">Data</th>
                        <th data-sort="date">Vencimento</th>
                        <th data-sort="string">Status</th>
                        <th data-sort="number">Valor</th>
                        <th style="text-align:right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($invoices)): ?>
                        <tr><td colspan="7" style="text-align:center; padding:30px; color:#aaa;">Nenhuma fatura encontrada.</td></tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $inv): 
                            $status_labels = ['draft'=>'Rascunho','sent'=>'Enviado','paid'=>'Pago','overdue'=>'Vencido','cancelled'=>'Cancelado'];
                            $status_label = $status_labels[$inv['status']] ?? ucfirst($inv['status']);
                        ?>
                        <tr>
                            <td data-sort-value="<?php echo htmlspecialchars($inv['number']); ?>"><strong><?php echo htmlspecialchars($inv['number']); ?></strong></td>
                            <td data-sort-value="<?php echo htmlspecialchars($inv['client_name'] ?? ''); ?>"><?php echo htmlspecialchars($inv['client_name']); ?></td>
                            <td data-sort-value="<?php echo $inv['date']; ?>"><?php echo date('d/m/Y', strtotime($inv['date'])); ?></td>
                            <td data-sort-value="<?php echo $inv['due_date']; ?>"><?php echo date('d/m/Y', strtotime($inv['due_date'])); ?></td>
                            <td data-sort-value="<?php echo $inv['status']; ?>"><span class="status-badge status-<?php echo $inv['status']; ?>"><?php echo $status_label; ?></span></td>
                            <td data-sort-value="<?php echo $inv['total']; ?>" style="font-family:monospace; font-weight:bold;"><?php echo formatCurrency($inv['total'], $inv['currency']); ?></td>
                            <td style="text-align:right;">
                                <a href="view_invoice.php?id=<?php echo $inv['id']; ?>" class="action-btn" title="Visualizar"><i class="fas fa-eye"></i></a>
                                <a href="view_invoice.php?id=<?php echo $inv['id']; ?>&download=true" target="_blank" class="action-btn" title="Baixar PDF"><i class="fas fa-download"></i></a>
                                <a href="invoices.php?edit=<?php echo $inv['id']; ?>" class="action-btn btn-edit" title="Editar"><i class="fas fa-edit"></i></a>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_invoice">
                                    <input type="hidden" name="invoice_id" value="<?php echo $inv['id']; ?>">
                                    <button type="submit" class="action-btn btn-del" title="Excluir" onclick="return confirm('Excluir esta fatura? Esta ação não pode ser desfeita.');"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('.vision-table');
    if (!table) return;
    const headers = table.querySelectorAll('th[data-sort]');
    const tbody = table.querySelector('tbody');

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
