<?php
date_default_timezone_set('America/Sao_Paulo');
session_start();
require_once __DIR__ . '/../config/database.php';

// Verificar se é admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: /login.php');
    exit;
}

$page_title = 'Editar Usuário - Admin';
$message = '';
$error = '';
$user_id = $_GET['id'] ?? 0;

// Buscar dados do usuário
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        header('Location: usuarios.php');
        exit;
    }
} catch (PDOException $e) {
    $error = 'Erro ao carregar usuário: ' . $e->getMessage();
    $usuario = null;
}

// Processar edição
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usuario) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'subscriber';
    $expires_at = $_POST['expires_at'] ?? null;
    $password = trim($_POST['password'] ?? '');
    
    // Validações
    if (empty($name)) {
        $error = 'Nome é obrigatório.';
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email válido é obrigatório.';
    } else {
        try {
            // Verificar se email já existe (exceto o próprio usuário)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND deleted_at IS NULL");
            $stmt->execute([$email, $user_id]);
            
            if ($stmt->fetch()) {
                $error = 'Este email já está sendo usado por outro usuário.';
            } else {
                // Preparar dados para update
                $update_data = [
                    'name' => $name,
                    'email' => $email,
                    'role' => $role,
                    'expires_at' => $expires_at ?: null,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $sql = "UPDATE users SET name = ?, email = ?, role = ?, expires_at = ?, updated_at = ?";
                $params = array_values($update_data);
                
                // Se senha foi informada, incluir no update
                if (!empty($password)) {
                    $sql .= ", password_hash = ?";
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }
                
                // Processar resets de trial
                if (!empty($_POST['reset_trial_7d'])) {
                    $sql .= ", had_trial_7d = 0";
                }
                if (!empty($_POST['reset_trial_24h'])) {
                    $sql .= ", had_trial_24h = 0";
                }
                if (!empty($_POST['restart_trial'])) {
                    $sql .= ", trial_started_at = NULL, trial_lectures_watched = '[]'";
                }
                
                // Aprovar trial manualmente
                if (!empty($_POST['approve_trial'])) {
                    $sql .= ", trial_approved = 1, trial_approved_at = NOW()";
                }
                
                // Se está mudando para um tipo trial, resetar o contador e aprovar automaticamente
                if (in_array($role, ['trial_7d', 'trial_24h'])) {
                    $old_role = $usuario['role'] ?? '';
                    if ($old_role !== $role) {
                        $sql .= ", trial_started_at = NULL, trial_lectures_watched = '[]', trial_approved = 1, trial_approved_at = NOW()";
                    }
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $user_id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                $message = 'Usuário atualizado com sucesso!';
                
                // Recarregar dados do usuário
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $usuario = $stmt->fetch();
            }
        } catch (PDOException $e) {
            $error = 'Erro ao atualizar usuário: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-user-edit"></i> Editar Usuário</h1>
            <p>Modificar informações e permissões do usuário</p>
            <div class="hero-actions">
                <a href="usuarios.php" class="page-btn">
                    <i class="fas fa-arrow-left"></i> Voltar aos Usuários
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

    <?php if ($usuario): ?>
        <div class="video-card">
            <div class="card-header">
                <h2><i class="fas fa-user"></i> Dados do Usuário</h2>
            </div>

            <form method="POST" class="form-grid">
                <div class="form-group">
                    <label for="name">
                        <i class="fas fa-user"></i> Nome Completo *
                    </label>
                    <input type="text" id="name" name="name" required
                           value="<?php echo htmlspecialchars($usuario['name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> Email *
                    </label>
                    <input type="email" id="email" name="email" required
                           value="<?php echo htmlspecialchars($usuario['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="role">
                        <i class="fas fa-user-tag"></i> Perfil do Usuário
                    </label>
                    <select id="role" name="role">
                        <option value="subscriber" <?php echo ($usuario['role'] ?? 'subscriber') === 'subscriber' ? 'selected' : ''; ?>>
                            Assinante
                        </option>
                        <option value="free" <?php echo ($usuario['role'] ?? '') === 'free' ? 'selected' : ''; ?>>
                            Free
                        </option>
                        <option value="admin" <?php echo ($usuario['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>
                            Administrador
                        </option>
                        <option value="trial_7d" <?php echo ($usuario['role'] ?? '') === 'trial_7d' ? 'selected' : ''; ?>>
                            Trial 7 Dias
                        </option>
                        <option value="trial_24h" <?php echo ($usuario['role'] ?? '') === 'trial_24h' ? 'selected' : ''; ?>>
                            Trial 24h (3 Palestras)
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="expires_at">
                        <i class="fas fa-calendar-times"></i> Data de Expiração
                    </label>
                    <input type="datetime-local" id="expires_at" name="expires_at"
                           value="<?php echo $usuario['expires_at'] ? date('Y-m-d\TH:i', strtotime($usuario['expires_at'])) : ''; ?>">
                    <small class="form-help">Deixe em branco para acesso sem limite de tempo</small>
                </div>

                <?php if (in_array($usuario['role'] ?? '', ['trial_7d', 'trial_24h']) && empty($usuario['trial_approved'])): ?>
                <div class="form-group">
                    <label><i class="fas fa-check-circle"></i> Aprovação do trial</label>
                    <div class="trial-approval-box">
                        <label class="checkbox-label approve-checkbox">
                            <input type="checkbox" name="approve_trial" value="1">
                            <span><i class="fas fa-unlock"></i> Aprovar acesso trial agora</span>
                        </label>
                        <small class="form-help" style="color: #f59e0b;">
                            <i class="fas fa-exclamation-triangle"></i> Este usuário ainda não foi aprovado e não consegue fazer login.
                        </small>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($usuario['had_trial_7d']) || isset($usuario['had_trial_24h']) || in_array($usuario['role'] ?? '', ['trial_7d', 'trial_24h'])): ?>
                <div class="form-group">
                    <label><i class="fas fa-redo"></i> Opções de trial</label>
                    <div class="trial-reset-options">
                        <?php if (!empty($usuario['had_trial_7d'])): ?>
                        <label class="checkbox-label">
                            <input type="checkbox" name="reset_trial_7d" value="1">
                            <span>Permitir novo Trial 7 Dias (já utilizado)</span>
                        </label>
                        <?php endif; ?>
                        
                        <?php if (!empty($usuario['had_trial_24h'])): ?>
                        <label class="checkbox-label">
                            <input type="checkbox" name="reset_trial_24h" value="1">
                            <span>Permitir novo Trial 24h (já utilizado)</span>
                        </label>
                        <?php endif; ?>
                        
                        <?php if (in_array($usuario['role'] ?? '', ['trial_7d', 'trial_24h'])): ?>
                        <label class="checkbox-label">
                            <input type="checkbox" name="restart_trial" value="1">
                            <span>Reiniciar período do trial atual (zerar contador)</span>
                        </label>
                        <?php endif; ?>
                    </div>
                    <small class="form-help">Marque para liberar novamente os benefícios de trial</small>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Nova Senha
                    </label>
                    <input type="password" id="password" name="password" minlength="6">
                    <small class="form-help">Deixe em branco para manter a senha atual</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="cta-btn">
                        <i class="fas fa-save"></i> Salvar Alterações
                    </button>
                    <a href="usuarios.php" class="page-btn">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>

        <div class="video-card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Informações do Sistema</h3>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <label>ID do Usuário:</label>
                    <span><?php echo $usuario['id']; ?></span>
                </div>
                
                <div class="info-item">
                    <label>Data de Cadastro:</label>
                    <span><?php echo date('d/m/Y H:i', strtotime($usuario['created_at'])); ?></span>
                </div>
                
                <?php if ($usuario['updated_at']): ?>
                <div class="info-item">
                    <label>Última Atualização:</label>
                    <span><?php echo date('d/m/Y H:i', strtotime($usuario['updated_at'])); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($usuario['expires_at']): ?>
                <div class="info-item">
                    <label>Status de Expiração:</label>
                    <?php 
                    $expires = strtotime($usuario['expires_at']);
                    $now = time();
                    $expired = $expires < $now;
                    ?>
                    <span class="status-badge status-<?php echo $expired ? 'cancelled' : 'completed'; ?>">
                        <?php echo $expired ? 'Expirado' : 'Ativo'; ?>
                        <?php if ($expired): ?>
                            <i class="fas fa-exclamation-triangle"></i>
                        <?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="video-card danger-zone">
            <div class="card-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Zona de Perigo</h3>
            </div>
            
            <p>Ações irreversíveis que afetam permanentemente este usuário.</p>
            
            <div class="danger-actions">
                <button type="button" class="danger-btn" onclick="confirmDelete()">
                    <i class="fas fa-trash"></i> Excluir Usuário
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.hero-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

.form-grid {
    display: grid;
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group label {
    font-weight: 600;
    color: white;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-group input,
.form-group select {
    padding: 0.75rem;
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.05);
    color: white;
    font-size: 1rem;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--brand-purple);
    box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.2);
}

.form-help {
    color: var(--text-light);
    font-size: 0.875rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

.info-grid {
    display: grid;
    gap: 1rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    border: 1px solid var(--glass-border);
}

.info-item label {
    font-weight: 600;
    color: var(--text-light);
}

.info-item span {
    color: white;
}

.danger-zone {
    border: 1px solid #ef4444;
    background: rgba(239, 68, 68, 0.1);
}

.danger-zone .card-header h3 {
    color: #ef4444;
}

.danger-actions {
    margin-top: 1rem;
}

.danger-btn {
    background: #ef4444;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
}

.danger-btn:hover {
    background: #dc2626;
    transform: translateY(-2px);
}

.trial-reset-options {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 10px 0;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    color: var(--text-light);
}

.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
}

.trial-approval-box {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-radius: 8px;
    padding: 15px;
}

.approve-checkbox {
    color: #10b981 !important;
    font-weight: 600;
}

.approve-checkbox i {
    color: #10b981;
}

@media (max-width: 768px) {
    .form-actions {
        flex-direction: column;
    }
    
    .info-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}
</style>

<script>
function confirmDelete() {
    if (confirm('Tem certeza que deseja excluir este usuário? Esta ação não pode ser desfeita.')) {
        // Criar form para deletar
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'usuarios.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_user';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'user_id';
        idInput.value = '<?php echo $user_id; ?>';
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>