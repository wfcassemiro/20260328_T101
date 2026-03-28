<?php
/**
 * Definir Senha - Para usuários criados pelo admin
 * Usa password_reset_token da tabela users (diferente do forgot_password que usa tabela password_resets)
 */

session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/config/database.php';

$page_title = 'Definir senha - Translators101';
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$message = '';
$message_type = '';
$user = null;
$show_form = false;

// Buscar usuário pelo token
if ($token) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, name, email, password_reset_token, password_reset_expires, role 
            FROM users 
            WHERE password_reset_token = ? 
            AND deleted_at IS NULL
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $message = 'Token inválido ou não encontrado.';
            $message_type = 'error';
        } elseif ($user['password_reset_expires'] && strtotime($user['password_reset_expires']) < time()) {
            $message = 'Este link expirou. Solicite um novo link ao administrador.';
            $message_type = 'error';
        } else {
            $show_form = true;
        }
    } catch (PDOException $e) {
        $message = 'Erro ao verificar token.';
        $message_type = 'error';
        error_log("Erro ao verificar token definir_senha: " . $e->getMessage());
    }
} else {
    $message = 'Nenhum token fornecido.';
    $message_type = 'error';
}

// Processar definição de senha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $show_form && $user) {
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    if (empty($password)) {
        $message = 'Por favor, digite sua nova senha.';
        $message_type = 'error';
    } elseif (strlen($password) < 6) {
        $message = 'A senha deve ter pelo menos 6 caracteres.';
        $message_type = 'error';
    } elseif ($password !== $password_confirm) {
        $message = 'As senhas não coincidem.';
        $message_type = 'error';
    } else {
        try {
            // Atualizar senha e limpar token
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password_hash = ?, 
                    password_reset_token = NULL, 
                    password_reset_expires = NULL,
                    first_login = 0,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$password_hash, $user['id']]);
            
            $message = 'Senha definida com sucesso! Você já pode fazer login.';
            $message_type = 'success';
            $show_form = false;
            
            error_log("[Senha] Usuário {$user['email']} definiu sua senha com sucesso.");
            
        } catch (PDOException $e) {
            $message = 'Erro ao definir senha. Tente novamente.';
            $message_type = 'error';
            error_log("Erro ao definir senha: " . $e->getMessage());
        }
    }
}

include __DIR__ . '/vision/includes/head.php';
include __DIR__ . '/vision/includes/header.php';
?>

<style>
.password-card {
    max-width: 500px;
    margin: 0 auto;
    padding: 40px;
}

.password-card h2 {
    text-align: center;
    margin-bottom: 10px;
    color: white;
}

.password-card .subtitle {
    text-align: center;
    color: rgba(255, 255, 255, 0.7);
    margin-bottom: 30px;
}

.user-welcome {
    background: rgba(139, 92, 246, 0.1);
    border: 1px solid rgba(139, 92, 246, 0.3);
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 25px;
    text-align: center;
}

.user-welcome .user-name {
    font-size: 1.2rem;
    font-weight: 600;
    color: #c4b5fd;
}

.user-welcome .user-email {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.6);
    margin-top: 5px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: white;
}

.form-group input {
    width: 100%;
    padding: 14px 16px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.3);
    color: white;
    font-size: 1rem;
    box-sizing: border-box;
}

.form-group input:focus {
    outline: none;
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
}

.form-group small {
    display: block;
    margin-top: 5px;
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.85rem;
}

.submit-btn {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, #8b5cf6, #6366f1);
    border: none;
    border-radius: 10px;
    color: white;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(139, 92, 246, 0.3);
}

.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
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

.success-content {
    text-align: center;
}

.success-icon {
    font-size: 4rem;
    color: #10b981;
    margin-bottom: 20px;
}

.success-content h3 {
    color: #10b981;
    margin-bottom: 10px;
}

.success-content p {
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 25px;
}

.login-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 30px;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    text-decoration: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 1.1rem;
    transition: all 0.3s;
}

.login-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
    color: white;
}

.back-link {
    display: block;
    text-align: center;
    margin-top: 20px;
    color: rgba(255, 255, 255, 0.6);
}

.back-link a {
    color: #8b5cf6;
    text-decoration: none;
    font-weight: 600;
}

.back-link a:hover {
    text-decoration: underline;
}

.password-requirements {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.password-requirements h4 {
    color: white;
    font-size: 0.9rem;
    margin-bottom: 10px;
}

.password-requirements ul {
    margin: 0;
    padding-left: 20px;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.85rem;
}

.password-requirements li {
    margin: 5px 0;
}
</style>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-key"></i> Definir sua senha</h1>
            <p>Crie uma senha segura para acessar sua conta</p>
        </div>
    </div>

    <div class="video-card password-card">
        <?php if ($message_type === 'success'): ?>
            <div class="success-content">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3>Senha definida com sucesso!</h3>
                <p>Sua conta está pronta. Agora você pode fazer login.</p>
                <a href="login.php" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> Fazer login
                </a>
            </div>
            
        <?php elseif ($message_type === 'error'): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
            <p class="back-link">
                <a href="login.php"><i class="fas fa-arrow-left"></i> Voltar ao login</a>
            </p>
            
        <?php elseif ($show_form && $user): ?>
            <h2><i class="fas fa-lock"></i> Criar sua senha</h2>
            <p class="subtitle">Defina uma senha segura para sua conta</p>
            
            <div class="user-welcome">
                <div class="user-name">Olá, <?php echo htmlspecialchars($user['name']); ?>!</div>
                <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Nova senha</label>
                    <input type="password" id="password" name="password" required minlength="6"
                           placeholder="Digite sua nova senha">
                    <small>Mínimo de 6 caracteres</small>
                </div>
                
                <div class="form-group">
                    <label for="password_confirm"><i class="fas fa-lock"></i> Confirmar senha</label>
                    <input type="password" id="password_confirm" name="password_confirm" required
                           placeholder="Digite a senha novamente">
                </div>
                
                <div class="password-requirements">
                    <h4><i class="fas fa-shield-alt"></i> Dicas para uma senha segura:</h4>
                    <ul>
                        <li>Use pelo menos 6 caracteres</li>
                        <li>Combine letras, números e símbolos</li>
                        <li>Evite informações pessoais óbvias</li>
                    </ul>
                </div>
                
                <button type="submit" class="submit-btn">
                    <i class="fas fa-check"></i> Definir minha senha
                </button>
            </form>
            
            <p class="back-link">
                Já tem uma senha? <a href="login.php">Fazer login</a>
            </p>
        <?php else: ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                Link inválido ou expirado.
            </div>
            <p class="back-link">
                <a href="login.php"><i class="fas fa-arrow-left"></i> Voltar ao login</a>
            </p>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>