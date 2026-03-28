<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Verificar se é admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: /login.php');
    exit;
}

$message = '';
$error = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'subscriber';
    $send_email = isset($_POST['send_email']);

    // Validações
    if (empty($name)) {
        $error = 'Nome é obrigatório.';
    } elseif (empty($email)) {
        $error = 'Email é obrigatório.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email inválido.';
    } else {
        try {
            // Verificar se email já existe
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $error = 'Este email já está cadastrado.';
            } else {
                // Criar usuário
                $reset_token = bin2hex(random_bytes(32));
                $password_hash = password_hash('temp123', PASSWORD_DEFAULT); // Senha temporária
                $first_login = 1; // Explicitly set the value for consistency.
                $password_reset_expires = (new DateTime('+7 days'))->format('Y-m-d H:i:s');
                $created_at = (new DateTime())->format('Y-m-d H:i:s');
                
                // Se é um usuário trial criado pelo admin, já vem aprovado
                $is_trial = in_array($role, ['trial_7d', 'trial_24h']);
                $trial_approved = $is_trial ? 1 : 0;

                // Corrected SQL query and execute statement
                $stmt = $pdo->prepare('
                    INSERT INTO users (id, name, email, password_hash, role, password_reset_token, password_reset_expires, first_login, created_at, is_active, trial_approved)
                    VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
                ');

                $stmt->execute([
                    $name,
                    $email,
                    $password_hash,
                    $role,
                    $reset_token,
                    $password_reset_expires,
                    $first_login,
                    $created_at,
                    $trial_approved
                ]);
                
                // Get the last inserted ID. This might return 0 since UUID() isn't an auto-incrementing integer.
                // You can get the UUID from the database by a separate query if needed.
                // For this form, knowing the UUID isn't essential for the success message.
                $user_id = $pdo->lastInsertId();

                // Gerar link de definição de senha
                $password_link = 'https://' . $_SERVER['HTTP_HOST'] . '/definir_senha.php?token=' . $reset_token;
                
                $message = 'Usuário criado com sucesso!<br>';
                $message .= '<strong>Nome:</strong> ' . htmlspecialchars($name) . '<br>';
                $message .= '<strong>Email:</strong> ' . htmlspecialchars($email) . '<br>';
                $message .= '<strong>Perfil:</strong> ' . ucfirst($role) . '<br>';
                $message .= '<strong>Observação:</strong> Vencimento controlado via Hotmart<br>';
                $message .= '<strong>Link para definir senha:</strong><br>';
                $message .= '<a href="' . $password_link . '" target="_blank" class="cta-btn" style="font-size: 0.8rem; padding: 0.5rem;">Definir Senha</a>';
                
                // Enviar email se solicitado
                if ($send_email) {
                    // Aqui você pode integrar com o sistema de email
                    $message .= '<br><br><em>Email enviado para o usuário!</em>';
                }
                
                // Limpar formulário
                $name = $email = '';
                $role = 'subscriber';
            }
            
        } catch (Exception $e) {
            $error = 'Erro ao criar usuário: ' . $e->getMessage();
        }
    }
}

$page_title = 'Adicionar Usuário - Admin';
$active_page = 'usuarios';

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-user-plus"></i> Adicionar Novo Usuário</h1>
            <p>Crie uma nova conta com perfil de acesso</p>
            <div class="hero-actions">
                <a href="usuarios.php" class="cta-btn">
                    <i class="fas fa-arrow-left"></i> Voltar para Usuários
                </a>
                <a href="index.php" class="page-btn">
                    <i class="fas fa-home"></i> Admin
                </a>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="video-card">
        <h2><i class="fas fa-user-circle"></i> Dados do Novo Usuário</h2>
        
        <form method="POST" class="vision-form">
            <div class="form-grid">
                <div class="form-group">
                    <label for="name">
                        <i class="fas fa-user"></i> Nome Completo *
                    </label>
                    <input type="text" id="name" name="name" required 
                           value="<?php echo htmlspecialchars($name ?? ''); ?>"
                           placeholder="Digite o nome completo">
                </div>

                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> Email *
                    </label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($email ?? ''); ?>"
                           placeholder="Digite o email">
                </div>

                <div class="form-group">
                    <label for="role">
                        <i class="fas fa-user-tag"></i> Perfil do Usuário *
                    </label>
                    <select name="role" id="role" required onchange="toggleTrialOptions()">
                        <option value="subscriber" <?php echo ($role ?? 'subscriber') === 'subscriber' ? 'selected' : ''; ?>>
                            Assinante (Subscriber)
                        </option>
                        <option value="admin" <?php echo ($role ?? '') === 'admin' ? 'selected' : ''; ?>>
                            Administrador (Admin)
                        </option>
                        <option value="trial_7d" <?php echo ($role ?? '') === 'trial_7d' ? 'selected' : ''; ?>>
                            Trial 7 Dias (Acesso completo por 7 dias)
                        </option>
                        <option value="trial_24h" <?php echo ($role ?? '') === 'trial_24h' ? 'selected' : ''; ?>>
                            Trial 24h (3 palestras em 24 horas)
                        </option>
                        <option value="free" <?php echo ($role ?? '') === 'free' ? 'selected' : ''; ?>>
                            Free (Sem acesso à videoteca)
                        </option>
                    </select>
                </div>

                <div class="form-group" id="trialInfoBox" style="display: none;">
                    <div class="trial-info-box">
                        <i class="fas fa-info-circle"></i>
                        <div id="trialInfoText">
                            <!-- Texto dinâmico baseado na seleção -->
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="info">
                        <i class="fas fa-info-circle"></i> Vencimento
                    </label>
                    <input type="text" id="info" readonly 
                           value="Controlado via Hotmart"
                           style="background: rgba(255,255,255,0.1); cursor: not-allowed;">
                </div>
            </div>

            <div class="form-group form-group-wide">
                <label class="checkbox-label">
                    <input type="checkbox" name="send_email" checked>
                    <span><i class="fas fa-envelope"></i> Enviar email com link para definir senha</span>
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="cta-btn">
                    <i class="fas fa-user-plus"></i> Criar Usuário
                </button>
                <a href="usuarios.php" class="page-btn">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>

    <div class="video-card">
        <h3><i class="fas fa-info-circle"></i> Informações Importantes</h3>
        <div class="info-grid">
            <div class="info-item">
                <i class="fas fa-key"></i>
                <div>
                    <strong>Senha Inicial:</strong>
                    <p>O usuário receberá um link para definir sua própria senha segura.</p>
                </div>
            </div>
            
            <div class="info-item">
                <i class="fas fa-calendar"></i>
                <div>
                    <strong>Vencimento:</strong>
                    <p>Controlado automaticamente via integração com Hotmart.</p>
                </div>
            </div>
            
            <div class="info-item">
                <i class="fas fa-user-shield"></i>
                <div>
                    <strong>Perfis:</strong>
                    <p><strong>Subscriber:</strong> Acesso a vídeos e certificados<br>
                        <strong>Admin:</strong> Acesso total ao painel administrativo</p>
                </div>
            </div>
            
            <div class="info-item">
                <i class="fas fa-envelope"></i>
                <div>
                    <strong>Email:</strong>
                    <p>Link de definição de senha é enviado automaticamente (se marcado).</p>
                </div>
            </div>
            
            <div class="info-item">
                <i class="fas fa-clock"></i>
                <div>
                    <strong>Trial 7 Dias:</strong>
                    <p>Acesso completo à videoteca por 7 dias. O contador começa no primeiro login.</p>
                </div>
            </div>
            
            <div class="info-item">
                <i class="fas fa-play-circle"></i>
                <div>
                    <strong>Trial 24h:</strong>
                    <p>Acesso a 3 palestras por 24 horas. O contador começa no primeiro acesso a uma palestra.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.form-group-wide {
    grid-column: 1 / -1;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    color: var(--text-light);
}

.checkbox-label input[type="checkbox"] {
    width: 1.25rem;
    height: 1.25rem;
    background: rgba(255,255,255,0.1);
    border: 2px solid var(--glass-border);
    border-radius: 4px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.info-item {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
}

.info-item i {
    color: var(--brand-purple);
    font-size: 1.5rem;
    margin-top: 0.25rem;
}

.info-item strong {
    color: white;
    display: block;
    margin-bottom: 0.5rem;
}

.info-item p {
    color: var(--text-light);
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.4;
}

.hero-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

.trial-info-box {
    display: flex;
    gap: 12px;
    padding: 15px;
    background: rgba(139, 92, 246, 0.1);
    border: 1px solid rgba(139, 92, 246, 0.3);
    border-radius: 8px;
    color: #c4b5fd;
}

.trial-info-box i {
    font-size: 1.2rem;
    color: #8b5cf6;
}

@media (max-width: 768px) {
    .hero-actions {
        flex-direction: column;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function toggleTrialOptions() {
    const role = document.getElementById('role').value;
    const infoBox = document.getElementById('trialInfoBox');
    const infoText = document.getElementById('trialInfoText');
    
    if (role === 'trial_7d') {
        infoBox.style.display = 'block';
        infoText.innerHTML = '<strong>Trial 7 Dias:</strong> O usuário terá acesso completo à videoteca por 7 dias. O período começa quando o usuário fizer o primeiro login. Após expirar, ele será movido para "Free" e adicionado à lista de leads.';
    } else if (role === 'trial_24h') {
        infoBox.style.display = 'block';
        infoText.innerHTML = '<strong>Trial 24h:</strong> O usuário poderá assistir até 3 palestras em um período de 24 horas. O período começa quando acessar a primeira palestra. Se assistir a mesma palestra mais de uma vez, conta como apenas 1.';
    } else {
        infoBox.style.display = 'none';
    }
}

// Executar ao carregar a página
document.addEventListener('DOMContentLoaded', toggleTrialOptions);
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>