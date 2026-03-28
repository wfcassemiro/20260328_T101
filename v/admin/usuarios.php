<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Verificar se é admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: /login.php');
    exit;
}

$page_title = 'Gerenciar Usuários - Admin';
$message = '';
$error = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'toggle_user':
                $message = 'Usuários estão sempre ativos quando logados.';
                break;
            
            case 'toggle_subscription':
                $message = 'Status da assinatura é controlado pelo Hotmart.';
                break;
            
            case 'delete_user':
                $user_id = $_POST['user_id'] ?? '';
                $stmt = $pdo->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ?");
                $stmt->execute([$user_id]);
                $message = "Usuário excluído com sucesso!";
                break;
        }
    } catch (PDOException $e) {
        $error = 'Erro ao processar ação: ' . $e->getMessage();
    }
}

// Buscar usuários (apenas não excluídos)
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

try {
    $where_clause = 'WHERE deleted_at IS NULL';
    $params = [];
    
    if ($search) {
        $where_clause .= " AND (name LIKE ? OR email LIKE ?)";
        $params = ["%$search%", "%$search%"];
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users $where_clause ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    // Total para paginação
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users $where_clause");
    $stmt->execute($params);
    $total_users = $stmt->fetchColumn();
    $total_pages = ceil($total_users / $per_page);
    
} catch (PDOException $e) {
    $users = [];
    $total_users = 0;
    $total_pages = 0;
    $error = 'Erro ao carregar usuários: ' . $e->getMessage();
}

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-users-cog"></i> Gerenciar Usuários</h1>
            <p>Administração de usuários e permissões da plataforma</p>
            <div class="hero-actions">
                <a href="adicionar_usuario.php" class="cta-btn">
                    <i class="fas fa-user-plus"></i> Adicionar Usuário
                </a>
                <a href="index.php" class="page-btn">
                    <i class="fas fa-arrow-left"></i> Voltar ao Admin
                </a>
            </div>
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

    <div class="video-card">
        <div class="card-header">
            <h2><i class="fas fa-list"></i> Lista de Usuários (<?php echo number_format($total_users); ?>)</h2>
            
            <div class="search-filters">
                <form method="GET" class="search-form">
                    <div class="search-group">
                        <input type="text" name="search" placeholder="Buscar usuários..."
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="page-btn">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if ($search): ?>
                            <a href="usuarios.php" class="page-btn">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($users)): ?>
            <div class="alert-warning">
                <i class="fas fa-info-circle"></i>
                <?php echo $search ? 'Nenhum usuário encontrado com os critérios de busca.' : 'Nenhum usuário cadastrado.'; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-user"></i> Nome</th>
                            <th><i class="fas fa-envelope"></i> Email</th>
                            <th><i class="fas fa-user-tag"></i> Perfil</th>
                            <th><i class="fas fa-calendar"></i> Cadastro</th>
                            <th><i class="fas fa-calendar-times"></i> Vencimento</th>
                            <th><i class="fas fa-toggle-on"></i> Status</th>
                            <th><i class="fas fa-cogs"></i> Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <span class="text-primary"><?php echo htmlspecialchars($user['name']); ?></span>
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <span class="admin-badge"><i class="fas fa-crown"></i></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $user['role'] === 'admin' ? 'completed' : 'pending'; ?>">
                                        <?php echo ucfirst($user['role'] ?? 'subscriber'); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if (isset($user['expires_at']) && $user['expires_at']): ?>
                                        <?php 
                                        $expires = strtotime($user['expires_at']);
                                        $now = time();
                                        $expired = $expires < $now;
                                        ?>
                                        <span class="status-badge status-<?php echo $expired ? 'cancelled' : 'completed'; ?>">
                                            <?php echo date('d/m/Y', $expires); ?>
                                            <?php if ($expired): ?>
                                                <i class="fas fa-exclamation-triangle"></i>
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">Sem limite</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-completed">
                                        Ativo
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- Botão Editar -->
                                        <a href="editar_usuario.php?id=<?php echo urlencode($user['id']); ?>" 
                                           class="page-btn" title="Editar Usuário">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <!-- Botão Excluir -->
                                        <form method="POST" style="display:inline;" 
                                              onsubmit="return confirm('Tem certeza que deseja excluir este usuário? Esta ação pode ser revertida pelo administrador.');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                            <button type="submit" class="page-btn danger-btn" title="Excluir Usuário">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        
                                        <?php if (isset($user['password_reset_token'])): ?>
                                            <a href="/definir_senha.php?token=<?php echo $user['password_reset_token']; ?>" 
                                               target="_blank" class="page-btn" title="Link de Senha">
                                                <i class="fas fa-key"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="cta-btn current-page"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                               class="page-btn"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="video-card">
        <h3><i class="fas fa-chart-bar"></i> Estatísticas</h3>
        <div class="stats-grid">
            <div class="stat-item">
                <i class="fas fa-users"></i>
                <div>
                    <div class="stat-number"><?php echo number_format($total_users); ?></div>
                    <div class="stat-label">Total de Usuários</div>
                </div>
            </div>
            
            <?php
            // Calcular estatísticas (apenas usuários não excluídos)
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND deleted_at IS NULL");
                $admins = $stmt->fetchColumn();
                
                $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE (role = 'subscriber' OR role IS NULL) AND deleted_at IS NULL");
                $subscribers = $stmt->fetchColumn();
                
                $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE expires_at IS NOT NULL AND expires_at < NOW() AND deleted_at IS NULL");
                $expired = $stmt->fetchColumn();
            } catch (Exception $e) {
                $admins = $subscribers = $expired = 0;
            }
            ?>
            
            <div class="stat-item">
                <i class="fas fa-crown"></i>
                <div>
                    <div class="stat-number"><?php echo number_format($admins); ?></div>
                    <div class="stat-label">Administradores</div>
                </div>
            </div>
            
            <div class="stat-item">
                <i class="fas fa-user"></i>
                <div>
                    <div class="stat-number"><?php echo number_format($subscribers); ?></div>
                    <div class="stat-label">Assinantes</div>
                </div>
            </div>
            
            <div class="stat-item">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <div class="stat-number"><?php echo number_format($expired); ?></div>
                    <div class="stat-label">Acesso Expirado</div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.hero-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.admin-badge {
    color: #fbbf24;
    font-size: 0.875rem;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.danger-btn {
    background: #dc2626 !important;
    color: white !important;
    border: 1px solid #dc2626 !important;
}

.danger-btn:hover {
    background: #b91c1c !important;
    border-color: #b91c1c !important;
    transform: translateY(-2px);
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
}

.current-page {
    background: var(--brand-purple);
    color: white;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    border: 1px solid var(--glass-border);
}

.stat-item i {
    font-size: 2rem;
    color: var(--brand-purple);
}

.stat-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: white;
}

.stat-label {
    color: var(--text-light);
    font-size: 0.875rem;
}

@media (max-width: 768px) {
    .hero-actions {
        flex-direction: column;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .pagination {
        flex-wrap: wrap;
    }
}

/* Botões */
.report-nav-buttons { display: flex; gap: 15px; margin-bottom: 20px; }
.vision-btn { background: var(--brand-purple); color: white; border: 1px solid var(--brand-purple); border-radius: 20px; padding: 12px 24px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); }
.vision-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3); }
.vision-btn-primary { background: var(--brand-purple); border-color: var(--brand-purple); }
.vision-btn-primary:hover { background: var(--brand-purple-dark); border-color: var(--brand-purple-dark); }
.vision-btn-secondary { background: rgba(255, 255, 255, 0.1); color: var(--text-primary); border-color: rgba(255, 255, 255, 0.2); }
.vision-btn-secondary:hover { background: rgba(255, 255, 255, 0.2) !important; border-color: rgba(255, 255, 255, 0.3) !important; color: #fff !important; }

</style>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>