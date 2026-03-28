<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';
require_once __DIR__ . '/../config/dash_functions.php';

if (!isLoggedIn()) { header('Location: /login.php'); exit; }

$user_id = $_SESSION['user_id'];
$message = '';

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
    
    .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
    .status-draft { background: #808080; color: #fff; }
    .status-sent { background: #40c4ff; color: #fff; }
    .status-paid { background: #66bb6a; color: #fff; }
    
    .action-btn { width: 34px; height: 34px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.05); color: #ccc; text-decoration: none; margin-right: 5px; transition:0.2s;}
    .action-btn:hover { background: rgba(255,255,255,0.15); color: #fff; }
    
    .vision-btn { background: var(--brand-purple); color: #fff; padding: 10px 20px; border-radius: 20px; border: 0; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
</style>

<div class="main-content">
    
    <div class="video-card profile-header-card">
        <div class="header-icon-container"><i class="fas fa-file-invoice-dollar" style="font-size: 1.8rem; color: #fff;"></i></div>
        <div class="header-text-container">
            <h2>Faturas</h2>
            <p>Histórico de cobranças emitidas.</p>
        </div>
    </div>

    <div style="margin-bottom:20px;">
        <a href="projects_list.php" class="vision-btn"><i class="fas fa-arrow-left"></i> Voltar aos Projetos</a>
    </div>

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
                        <th>Número</th>
                        <th>Cliente</th>
                        <th>Data</th>
                        <th>Vencimento</th>
                        <th>Status</th>
                        <th>Valor</th>
                        <th style="text-align:right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($invoices)): ?>
                        <tr><td colspan="7" style="text-align:center; padding:30px; color:#aaa;">Nenhuma fatura encontrada.</td></tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($inv['number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($inv['client_name']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($inv['date'])); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($inv['due_date'])); ?></td>
                            <td><span class="status-badge status-<?php echo $inv['status']; ?>"><?php echo ucfirst($inv['status']=='draft'?'Rascunho':$inv['status']); ?></span></td>
                            <td style="font-family:monospace; font-weight:bold;"><?php echo formatCurrency($inv['total'], $inv['currency']); ?></td>
                            <td style="text-align:right;">
                                <a href="view_invoice.php?id=<?php echo $inv['id']; ?>" class="action-btn" title="Visualizar"><i class="fas fa-eye"></i></a>
                                <a href="view_invoice.php?id=<?php echo $inv['id']; ?>&download=true" target="_blank" class="action-btn" title="Baixar PDF"><i class="fas fa-download"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>