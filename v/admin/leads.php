<?php
/**
 * Painel de Leads - Área Administrativa
 * Acesso restrito a administradores
 */

session_start();
date_default_timezone_set('America/Sao_Paulo');

// Verifica autenticação e permissão de admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$page_title = 'Gerenciar Leads - Admin';
$success_message = '';
$error_message = '';

// Verificar se a coluna pode_receber_email existe, se não, criar
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM leads LIKE 'pode_receber_email'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE leads ADD COLUMN pode_receber_email TINYINT(1) DEFAULT 1");
    }
} catch (PDOException $e) {
    error_log("Erro ao verificar/criar coluna pode_receber_email: " . $e->getMessage());
}

// Processar ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Excluir lead
    if ($_POST['action'] === 'delete_lead' && isset($_POST['lead_id'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM leads WHERE id = ?");
            $stmt->execute([$_POST['lead_id']]);
            $success_message = "Lead excluído com sucesso!";
        } catch (PDOException $e) {
            $error_message = "Erro ao excluir lead: " . $e->getMessage();
        }
    }
    
    // Alterar permissão de email
    if ($_POST['action'] === 'toggle_email' && isset($_POST['lead_id'])) {
        try {
            $new_status = isset($_POST['pode_receber_email']) ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE leads SET pode_receber_email = ? WHERE id = ?");
            $stmt->execute([$new_status, $_POST['lead_id']]);
            $success_message = $new_status ? "Lead adicionado à lista de e-mails!" : "Lead removido da lista de e-mails!";
        } catch (PDOException $e) {
            $error_message = "Erro ao atualizar permissão: " . $e->getMessage();
        }
    }
    
    // Alterar múltiplos leads de uma vez
    if ($_POST['action'] === 'bulk_toggle_email' && isset($_POST['lead_ids'])) {
        try {
            $new_status = ($_POST['bulk_status'] === 'enable') ? 1 : 0;
            $lead_ids = $_POST['lead_ids'];
            $placeholders = implode(',', array_fill(0, count($lead_ids), '?'));
            $stmt = $pdo->prepare("UPDATE leads SET pode_receber_email = ? WHERE id IN ($placeholders)");
            $params = array_merge([$new_status], $lead_ids);
            $stmt->execute($params);
            $count = count($lead_ids);
            $success_message = $new_status ? 
                "$count lead(s) adicionado(s) à lista de e-mails!" : 
                "$count lead(s) removido(s) da lista de e-mails!";
        } catch (PDOException $e) {
            $error_message = "Erro ao atualizar em lote: " . $e->getMessage();
        }
    }
    
    // Exportar para CSV
    if ($_POST['action'] === 'export_csv') {
        try {
            $stmt = $pdo->query("SELECT nome, email, whatsapp, fonte, COALESCE(pode_receber_email, 1) as pode_receber_email, created_at FROM leads ORDER BY created_at DESC");
            $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="leads_' . date('Y-m-d_H-i-s') . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            // BOM para Excel reconhecer UTF-8
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Cabeçalho
            fputcsv($output, ['Nome', 'E-mail', 'WhatsApp', 'Fonte', 'Recebe E-mails', 'Data/Hora Cadastro'], ';');
            
            // Dados
            foreach ($leads as $lead) {
                fputcsv($output, [
                    $lead['nome'],
                    $lead['email'],
                    $lead['whatsapp'],
                    $lead['fonte'],
                    $lead['pode_receber_email'] ? 'Sim' : 'Não',
                    date('d/m/Y H:i:s', strtotime($lead['created_at']))
                ], ';');
            }
            
            fclose($output);
            exit;
        } catch (PDOException $e) {
            $error_message = "Erro ao exportar: " . $e->getMessage();
        }
    }
}

// Buscar leads com filtros
$filter_fonte = $_GET['fonte'] ?? '';
$filter_data_inicio = $_GET['data_inicio'] ?? '';
$filter_data_fim = $_GET['data_fim'] ?? '';
$filter_email_status = $_GET['email_status'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT *, COALESCE(pode_receber_email, 1) as pode_receber_email FROM leads WHERE 1=1";
$params = [];

if (!empty($filter_fonte)) {
    $sql .= " AND fonte = ?";
    $params[] = $filter_fonte;
}

if (!empty($filter_data_inicio)) {
    $sql .= " AND DATE(created_at) >= ?";
    $params[] = $filter_data_inicio;
}

if (!empty($filter_data_fim)) {
    $sql .= " AND DATE(created_at) <= ?";
    $params[] = $filter_data_fim;
}

if ($filter_email_status === 'enabled') {
    $sql .= " AND COALESCE(pode_receber_email, 1) = 1";
} elseif ($filter_email_status === 'disabled') {
    $sql .= " AND COALESCE(pode_receber_email, 1) = 0";
}

if (!empty($search)) {
    $sql .= " AND (nome LIKE ? OR email LIKE ? OR whatsapp LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $leads = [];
    $error_message = "Erro ao buscar leads: " . $e->getMessage();
}

// Estatísticas
try {
    $stats = [];
    
    // Total de leads
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM leads");
    $stats['total'] = $stmt->fetch()['total'];
    
    // Leads que podem receber email
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM leads WHERE COALESCE(pode_receber_email, 1) = 1");
    $stats['recebem_email'] = $stmt->fetch()['total'];
    
    // Leads hoje
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM leads WHERE DATE(created_at) = CURDATE()");
    $stats['hoje'] = $stmt->fetch()['total'];
    
    // Leads esta semana
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM leads WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['semana'] = $stmt->fetch()['total'];
    
    // Leads este mês
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM leads WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
    $stats['mes'] = $stmt->fetch()['total'];
    
    // Fontes distintas
    $stmt = $pdo->query("SELECT fonte, COUNT(*) as total FROM leads GROUP BY fonte ORDER BY total DESC");
    $stats['fontes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $stats = ['total' => 0, 'recebem_email' => 0, 'hoje' => 0, 'semana' => 0, 'mes' => 0, 'fontes' => []];
}

// Incluir templates
include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<style>
.leads-container {
    padding: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.page-header h1 {
    color: #c084fc;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-header h1 i {
    color: #FFD700;
}

.header-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background: rgba(30, 30, 30, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}

.stat-card .stat-number {
    font-size: 2.2rem;
    font-weight: bold;
    color: #c084fc;
    margin-bottom: 5px;
}

.stat-card .stat-label {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.85rem;
}

.stat-card.highlight {
    background: linear-gradient(135deg, rgba(192, 132, 252, 0.2), rgba(139, 92, 246, 0.1));
    border-color: rgba(192, 132, 252, 0.3);
}

.stat-card.highlight .stat-number {
    color: #FFD700;
}

.stat-card.stat-green {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(16, 185, 129, 0.1));
    border-color: rgba(16, 185, 129, 0.3);
}

.stat-card.stat-green .stat-number {
    color: #10b981;
}

/* Filters */
.filters-card {
    background: rgba(30, 30, 30, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
}

.filters-card h3 {
    color: #c084fc;
    margin: 0 0 15px 0;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.filters-form {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 140px;
}

.filter-group label {
    display: block;
    margin-bottom: 5px;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.85rem;
}

.filter-group input,
.filter-group select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(0, 0, 0, 0.3);
    color: white;
    font-size: 0.9rem;
}

.filter-group input:focus,
.filter-group select:focus {
    outline: none;
    border-color: #c084fc;
}

.filter-buttons {
    display: flex;
    gap: 10px;
}

/* Buttons */
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, #c084fc, #8b5cf6);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(192, 132, 252, 0.3);
}

.btn-success {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.2);
}

.btn-danger {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
    padding: 6px 10px;
}

.btn-danger:hover {
    background: rgba(239, 68, 68, 0.3);
}

.btn-warning {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.btn-warning:hover {
    background: rgba(245, 158, 11, 0.3);
}

/* Bulk Actions */
.bulk-actions {
    display: none;
    background: rgba(192, 132, 252, 0.1);
    border: 1px solid rgba(192, 132, 252, 0.3);
    border-radius: 10px;
    padding: 15px 20px;
    margin-bottom: 20px;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.bulk-actions.active {
    display: flex;
}

.bulk-actions span {
    color: #c084fc;
    font-weight: 600;
}

/* Table */
.leads-card {
    background: rgba(30, 30, 30, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    overflow: hidden;
}

.leads-card-header {
    padding: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.leads-card-header h3 {
    color: #c084fc;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.leads-count {
    background: rgba(192, 132, 252, 0.2);
    color: #c084fc;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
}

.table-container {
    overflow-x: auto;
}

.leads-table {
    width: 100%;
    border-collapse: collapse;
}

.leads-table th,
.leads-table td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.leads-table th {
    background: rgba(0, 0, 0, 0.3);
    color: #c084fc;
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.leads-table tr:hover {
    background: rgba(255, 255, 255, 0.03);
}

.leads-table td {
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.65rem;
}

.lead-name {
    font-weight: 600;
    color: white;
}

.lead-email a {
    color: #c084fc;
    text-decoration: none;
}

.lead-email a:hover {
    text-decoration: underline;
}

.lead-whatsapp {
    color: #25D366;
    display: flex;
    align-items: center;
    gap: 6px;
}

.lead-whatsapp a {
    color: #25D366;
    text-decoration: none;
}

.lead-whatsapp a:hover {
    text-decoration: underline;
}

.fonte-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
    background: rgba(139, 92, 246, 0.2);
    color: #a78bfa;
}

.date-time {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.85rem;
}

.date-time .date {
    display: block;
    color: white;
}

.date-time .time {
    font-size: 0.8rem;
}

/* Email Toggle */
.email-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
}

.toggle-switch {
    position: relative;
    width: 44px;
    height: 24px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(239, 68, 68, 0.3);
    transition: .3s;
    border-radius: 24px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
}

.toggle-switch input:checked + .toggle-slider {
    background-color: rgba(16, 185, 129, 0.5);
}

.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(20px);
}

.toggle-label {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.6);
}

.toggle-label.enabled {
    color: #10b981;
}

.toggle-label.disabled {
    color: #ef4444;
}

/* Actions column */
.actions-cell {
    display: flex;
    gap: 8px;
    align-items: center;
}

/* Messages */
.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #10b981;
}

.alert-error {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: rgba(255, 255, 255, 0.5);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 20px;
    color: rgba(192, 132, 252, 0.3);
}

.empty-state h3 {
    margin: 0 0 10px 0;
    color: rgba(255, 255, 255, 0.7);
}

/* Checkbox styling */
.lead-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #c084fc;
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 992px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .filters-form {
        flex-direction: column;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .bulk-actions {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<div class="main-content">
    <div class="leads-container">
        <!-- Header -->
        <div class="page-header">
            <h1><i class="fas fa-users"></i> Gerenciar Leads</h1>
            <div class="header-actions">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="export_csv">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-file-csv"></i> Exportar CSV
                    </button>
                </form>
                <a href="emails.php" class="btn btn-primary">
                    <i class="fas fa-envelope"></i> Enviar E-mails
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card highlight">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total de Leads</div>
            </div>
            <div class="stat-card stat-green">
                <div class="stat-number"><?php echo $stats['recebem_email']; ?></div>
                <div class="stat-label">Recebem E-mails</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['hoje']; ?></div>
                <div class="stat-label">Hoje</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['semana']; ?></div>
                <div class="stat-label">Esta Semana</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['mes']; ?></div>
                <div class="stat-label">Este Mês</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <h3><i class="fas fa-filter"></i> Filtros</h3>
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label>Buscar</label>
                    <input type="text" name="search" placeholder="Nome, email ou WhatsApp..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-group">
                    <label>Fonte</label>
                    <select name="fonte">
                        <option value="">Todas</option>
                        <?php foreach ($stats['fontes'] as $fonte): ?>
                        <option value="<?php echo htmlspecialchars($fonte['fonte']); ?>"
                                <?php echo $filter_fonte === $fonte['fonte'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($fonte['fonte']); ?> (<?php echo $fonte['total']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Status E-mail</label>
                    <select name="email_status">
                        <option value="">Todos</option>
                        <option value="enabled" <?php echo $filter_email_status === 'enabled' ? 'selected' : ''; ?>>Recebem e-mails</option>
                        <option value="disabled" <?php echo $filter_email_status === 'disabled' ? 'selected' : ''; ?>>Não recebem</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Data Início</label>
                    <input type="date" name="data_inicio" value="<?php echo htmlspecialchars($filter_data_inicio); ?>">
                </div>
                
                <div class="filter-group">
                    <label>Data Fim</label>
                    <input type="date" name="data_fim" value="<?php echo htmlspecialchars($filter_data_fim); ?>">
                </div>
                
                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="leads.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Limpar
                    </a>
                </div>
            </form>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions" id="bulkActions">
            <span><i class="fas fa-check-square"></i> <span id="selectedCount">0</span> selecionado(s)</span>
            <form method="POST" style="display: inline;" id="bulkForm">
                <input type="hidden" name="action" value="bulk_toggle_email">
                <input type="hidden" name="bulk_status" id="bulkStatus" value="">
                <div id="selectedLeadsContainer"></div>
                <button type="submit" class="btn btn-success" onclick="document.getElementById('bulkStatus').value='enable'">
                    <i class="fas fa-envelope"></i> Adicionar à lista
                </button>
                <button type="submit" class="btn btn-warning" onclick="document.getElementById('bulkStatus').value='disable'">
                    <i class="fas fa-envelope-slash"></i> Remover da lista
                </button>
            </form>
            <button type="button" class="btn btn-secondary" onclick="deselectAll()">
                <i class="fas fa-times"></i> Cancelar
            </button>
        </div>

        <!-- Leads Table -->
        <div class="leads-card">
            <div class="leads-card-header">
                <h3><i class="fas fa-list"></i> Lista de Leads</h3>
                <span class="leads-count"><?php echo count($leads); ?> registro(s)</span>
            </div>
            
            <?php if (empty($leads)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Nenhum lead encontrado</h3>
                <p>Ajuste os filtros ou aguarde novos cadastros.</p>
            </div>
            <?php else: ?>
            <div class="table-container">
                <table class="leads-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll" onclick="toggleSelectAll()" class="lead-checkbox"></th>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>WhatsApp</th>
                            <th>Fonte</th>
                            <th>Recebe E-mails</th>
                            <th>Data/Hora (GMT-3)</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $lead): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="lead-checkbox lead-select" 
                                       value="<?php echo htmlspecialchars($lead['id']); ?>" 
                                       onchange="updateBulkActions()">
                            </td>
                            <td class="lead-name"><?php echo htmlspecialchars($lead['nome']); ?></td>
                            <td class="lead-email">
                                <a href="mailto:<?php echo htmlspecialchars($lead['email']); ?>">
                                    <?php echo htmlspecialchars($lead['email']); ?>
                                </a>
                            </td>
                            <td class="lead-whatsapp">
                                <i class="fab fa-whatsapp"></i>
                                <?php 
                                $whatsapp_number = preg_replace('/[^0-9]/', '', $lead['whatsapp']);
                                if (strlen($whatsapp_number) === 11) {
                                    $whatsapp_number = '55' . $whatsapp_number;
                                }
                                ?>
                                <a href="https://wa.me/<?php echo $whatsapp_number; ?>" target="_blank">
                                    <?php echo htmlspecialchars($lead['whatsapp']); ?>
                                </a>
                            </td>
                            <td>
                                <span class="fonte-badge"><?php echo htmlspecialchars($lead['fonte']); ?></span>
                            </td>
                            <td>
                                <form method="POST" class="email-toggle">
                                    <input type="hidden" name="action" value="toggle_email">
                                    <input type="hidden" name="lead_id" value="<?php echo htmlspecialchars($lead['id']); ?>">
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="pode_receber_email" 
                                               <?php echo $lead['pode_receber_email'] ? 'checked' : ''; ?>
                                               onchange="this.form.submit()">
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <span class="toggle-label <?php echo $lead['pode_receber_email'] ? 'enabled' : 'disabled'; ?>">
                                        <?php echo $lead['pode_receber_email'] ? 'Sim' : 'Não'; ?>
                                    </span>
                                </form>
                            </td>
                            <td class="date-time">
                                <span class="date"><?php echo date('d/m/Y', strtotime($lead['created_at'])); ?></span>
                                <span class="time"><?php echo date('H:i:s', strtotime($lead['created_at'])); ?></span>
                            </td>
                            <td class="actions-cell">
                                <form method="POST" style="display: inline;" 
                                      onsubmit="return confirm('Tem certeza que deseja excluir este lead?');">
                                    <input type="hidden" name="action" value="delete_lead">
                                    <input type="hidden" name="lead_id" value="<?php echo htmlspecialchars($lead['id']); ?>">
                                    <button type="submit" class="btn btn-danger" title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Selecionar/Desselecionar todos
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.lead-select');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateBulkActions();
}

// Atualizar ações em lote
function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.lead-select:checked');
    const bulkActions = document.getElementById('bulkActions');
    const selectedCount = document.getElementById('selectedCount');
    const container = document.getElementById('selectedLeadsContainer');
    
    if (checkboxes.length > 0) {
        bulkActions.classList.add('active');
        selectedCount.textContent = checkboxes.length;
        
        // Limpar e adicionar os IDs selecionados
        container.innerHTML = '';
        checkboxes.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'lead_ids[]';
            input.value = cb.value;
            container.appendChild(input);
        });
    } else {
        bulkActions.classList.remove('active');
    }
    
    // Atualizar checkbox "Selecionar todos"
    const allCheckboxes = document.querySelectorAll('.lead-select');
    document.getElementById('selectAll').checked = 
        allCheckboxes.length > 0 && checkboxes.length === allCheckboxes.length;
}

// Desselecionar todos
function deselectAll() {
    document.querySelectorAll('.lead-select').forEach(cb => cb.checked = false);
    document.getElementById('selectAll').checked = false;
    updateBulkActions();
}

// Auto-hide alerts after 5 seconds
document.querySelectorAll('.alert').forEach(function(alert) {
    setTimeout(function() {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(function() {
            alert.remove();
        }, 500);
    }, 5000);
});
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>