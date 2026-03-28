<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Verificar se é admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: /login.php');
    exit;
}

$page_title = 'Logs de Acesso - Admin';
$message = '';
$error = '';

// Parâmetros de filtro
$log_action = $_GET['action'] ?? '';
$date_filter = $_GET['date'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Buscar logs
try {
    $where_conditions = [];
    $params = [];
    
    if ($log_action) {
        $where_conditions[] = "al.action = ?";
        $params[] = $log_action;
    }
    
    if ($date_filter) {
        $where_conditions[] = "DATE(al.created_at) = ?";
        $params[] = $date_filter;
    }
    
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    // Buscar logs com paginação, juntando com a tabela de usuários
    $stmt = $pdo->prepare("
        SELECT 
            al.id, 
            al.user_id, 
            u.name as user_name, 
            al.action, 
            al.resource, 
            al.ip_address, 
            al.user_agent, 
            al.created_at
        FROM access_logs al
        LEFT JOIN users u ON al.user_id = u.id
        $where_clause 
        ORDER BY al.created_at DESC 
        LIMIT $per_page OFFSET $offset
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    // Total para paginação
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM access_logs al $where_clause");
    $stmt->execute($params);
    $total_logs = $stmt->fetchColumn();
    $total_pages = ceil($total_logs / $per_page);
    
    // Buscar tipos de ação (actions) disponíveis
    $stmt = $pdo->query("SELECT DISTINCT action FROM access_logs ORDER BY action");
    $log_actions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Estatísticas
    $stmt = $pdo->query("SELECT COUNT(*) FROM access_logs WHERE DATE(created_at) = CURDATE()");
    $today_logs = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    $logs = [];
    $log_actions = [];
    $total_logs = 0;
    $total_pages = 0;
    $today_logs = 0;
    $error = 'Erro ao carregar logs: ' . $e->getMessage();
}

include __DIR__ . '/../vision/includes/head.php';
?>
<style>
    /* Transição suave e padding padrão para o conteúdo principal */
    .main-content {
        padding: 20px;
        transition: margin-left 0.3s ease;
    }

    /* Aplica a margem à esquerda SOMENTE quando o sidebar está aberto */
    body.sidebar-open .main-content {
        margin-left: 280px; /* Ajuste este valor se a largura da sua sidebar for diferente */
    }

    /* Ajusta o espaçamento interno dos cards e o gap vertical */
    .video-card {
        margin-bottom: 20px;
        padding: 25px; 
    }
    
    .vision-form .form-grid {
        gap: 15px 20px;
    }

    /* Estilização padrão para botões */
    .cta-btn, .page-btn {
        padding: 12px 20px;
        border-radius: 20px;
        text-decoration: none;
        font-weight: bold;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .cta-btn {
        background-color: rgba(128, 128, 255, 0.8);
        color: white;
    }

    .cta-btn:hover {
        background-color: rgba(128, 128, 255, 1);
        transform: translateY(-2px);
    }
    
    .page-btn {
        background-color: rgba(255, 255, 255, 0.2);
        color: #f0f0f0;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .page-btn:hover {
        background-color: rgba(255, 255, 255, 0.3);
    }
    
    /* ESTILOS DO MODAL */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.7);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        backdrop-filter: blur(5px);
    }

    .modal-content {
        background: rgba(30, 30, 50, 0.85);
        backdrop-filter: blur(15px);
        padding: 30px;
        border-radius: 15px;
        width: 90%;
        max-width: 600px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
        position: relative;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        padding-bottom: 15px;
        margin-bottom: 20px;
    }
    
    .modal-header h3 {
        margin: 0;
    }

    .close-btn {
        background: none;
        border: none;
        color: white;
        font-size: 1.5rem;
        cursor: pointer;
    }
</style>
<?php
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-list-alt"></i> Logs de Acesso</h1>
            <p>Monitoramento e auditoria de acessos ao sistema</p>
            <a href="index.php" class="cta-btn">
                <i class="fas fa-arrow-left"></i> Voltar ao Admin
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="video-card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3>Logs Hoje</h3>
                    <span class="stats-number"><?php echo number_format($today_logs); ?></span>
                </div>
                <div class="stats-icon stats-icon-blue">
                    <i class="fas fa-calendar-day"></i>
                </div>
            </div>
        </div>

        <div class="video-card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3>Total de Logs</h3>
                    <span class="stats-number"><?php echo number_format($total_logs); ?></span>
                </div>
                <div class="stats-icon stats-icon-purple">
                    <i class="fas fa-list"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="video-card">
        <h2><i class="fas fa-filter"></i> Filtros</h2>
        
        <form method="GET" class="vision-form">
            <div class="form-grid">
                <div class="form-group">
                    <label for="action">
                        <i class="fas fa-tags"></i> Tipo de Ação
                    </label>
                    <select id="action" name="action">
                        <option value="">Todos os tipos</option>
                        <?php foreach ($log_actions as $action_item): ?>
                            <option value="<?php echo htmlspecialchars($action_item); ?>" 
                                    <?php echo $log_action == $action_item ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($action_item); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date">
                        <i class="fas fa-calendar"></i> Data
                    </label>
                    <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                </div>

                <div class="form-group">
                    <label>&nbsp;</label>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="cta-btn">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <a href="logs.php" class="page-btn">
                            <i class="fas fa-times"></i> Limpar
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="video-card">
        <div class="card-header">
            <h2><i class="fas fa-list"></i> Logs de Acesso (<?php echo number_format($total_logs); ?>)</h2>
        </div>

        <?php if (empty($logs)): ?>
            <div class="alert-warning">
                <i class="fas fa-info-circle"></i>
                <?php echo ($log_action || $date_filter) ? 'Nenhum log encontrado com os critérios de busca.' : 'Nenhum log registrado ainda.'; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-calendar"></i> Data/Hora</th>
                            <th><i class="fas fa-tags"></i> Ação</th>
                            <th><i class="fas fa-user"></i> Usuário</th>
                            <th><i class="fas fa-info-circle"></i> Recurso</th>
                             <th><i class="fas fa-network-wired"></i> Endereço IP</th>
                            <th><i class="fas fa-eye"></i> Detalhes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                <td>
                                    <span class="status-badge status-in_progress">
                                        <?php echo htmlspecialchars($log['action']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['user_name'] ?? $log['user_id'] ?? 'Sistema'); ?></td>
                                <td>
                                    <span class="text-primary">
                                        <?php echo htmlspecialchars(substr($log['resource'], 0, 80)) . (strlen($log['resource']) > 80 ? '...' : ''); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                                <td>
                                    <button onclick="showLogDetails(<?php echo htmlspecialchars(json_encode($log)); ?>)" 
                                            class="page-btn" title="Ver Detalhes">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div style="text-align: center; margin-top: 30px;">
                    <?php
                    $base_url = "logs.php?";
                    if ($log_action) $base_url .= "action=" . urlencode($log_action) . "&";
                    if ($date_filter) $base_url .= "date=" . urlencode($date_filter) . "&";
                    ?>
                    
                    <?php if ($page > 1): ?>
                        <a href="<?php echo $base_url; ?>page=1" class="page-btn">« Primeira</a>
                        <a href="<?php echo $base_url; ?>page=<?php echo $page - 1; ?>" class="page-btn">‹ Anterior</a>
                    <?php endif; ?>
                    
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="cta-btn" style="margin: 0 5px;"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="<?php echo $base_url; ?>page=<?php echo $i; ?>" 
                               class="page-btn" style="margin: 0 5px;"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo $base_url; ?>page=<?php echo $page + 1; ?>" class="page-btn">Próxima ›</a>
                        <a href="<?php echo $base_url; ?>page=<?php echo $total_pages; ?>" class="page-btn">Última »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div id="logModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Detalhes do Log</h3>
            <button type="button" onclick="closeLogModal()" class="close-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div id="logDetails">
            </div>
    </div>
</div>

<script>
function showLogDetails(log) {
    const modal = document.getElementById('logModal');
    const details = document.getElementById('logDetails');
    
    let userName = log.user_name || log.user_id || 'Sistema';

    details.innerHTML = `
        <div class="form-group">
            <label><strong>Data/Hora:</strong></label>
            <p>${new Date(log.created_at).toLocaleString('pt-BR')}</p>
        </div>
        <div class="form-group">
            <label><strong>Ação:</strong></label>
            <p><span class="status-badge">${log.action}</span></p>
        </div>
        <div class="form-group">
            <label><strong>Usuário:</strong></label>
            <p>${userName}</p>
        </div>
        <div class="form-group">
            <label><strong>Recurso:</strong></label>
            <textarea readonly rows="6" style="width: 100%; font-family: monospace; font-size: 0.8rem; background-color: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 5px; padding: 10px;">${log.resource || 'N/A'}</textarea>
        </div>
        <div class="form-group">
            <label><strong>Endereço IP:</strong></label>
            <p>${log.ip_address || 'N/A'}</p>
        </div>
        <div class="form-group">
            <label><strong>User Agent:</strong></label>
            <p style="font-size: 0.8rem; word-break: break-all;">${log.user_agent || 'N/A'}</p>
        </div>
    `;
    
    modal.style.display = 'flex';
}

function closeLogModal() {
    document.getElementById('logModal').style.display = 'none';
}

// Fechar modal clicando fora
document.getElementById('logModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeLogModal();
    }
});
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>