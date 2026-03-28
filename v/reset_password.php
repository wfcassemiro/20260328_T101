<?php

// Define o fuso horário para a América de São Paulo.
date_default_timezone_set('America/Sao_Paulo');

// Inclui o arquivo de configuração do banco de dados.
require_once __DIR__ . '/config/database.php';

// Recupera o token da URL. Se não existir, o valor será uma string vazia.
$token = $_GET['token'] ?? '';
$message = '';
$message_type = ''; // 'success' ou 'error'

// Verifica se o método da requisição é POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'];
    $new_password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if ($reset) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hashed, $reset['user_id']]);
        $pdo->prepare("DELETE FROM password_resets WHERE token = ?")->execute([$token]);
        $message = "Senha redefinida!";
        $message_type = 'success';
    } else {
        $message = "Token inválido ou expirado.";
        $message_type = 'error';
    }
}
?>

<?php include __DIR__ . '/vision/includes/head.php'; ?>
<?php include __DIR__ . '/vision/includes/header.php'; ?>

<style>
.alert-success {
    background: linear-gradient(135deg, rgba(52, 199, 89, 0.2), rgba(52, 199, 89, 0.1));
    border: 1px solid rgba(52, 199, 89, 0.3);
    color: #34C759;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    text-align: center;
}

.alert-success i {
    font-size: 2.5rem;
    display: block;
    margin-bottom: 15px;
}

.alert-success p {
    margin: 0 0 20px 0;
    font-size: 1.1rem;
}

.alert-error {
    background: linear-gradient(135deg, rgba(255, 59, 48, 0.2), rgba(255, 59, 48, 0.1));
    border: 1px solid rgba(255, 59, 48, 0.3);
    color: #FF3B30;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    text-align: center;
}

.alert-error i {
    font-size: 2.5rem;
    display: block;
    margin-bottom: 15px;
}

.alert-error p {
    margin: 0;
    font-size: 1.1rem;
}

.btn-login {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, #007AFF, #0056CC);
    color: white;
    padding: 12px 30px;
    border-radius: 25px;
    text-decoration: none;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.btn-login:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(0, 122, 255, 0.4);
    color: white;
    text-decoration: none;
}

.btn-login i {
    font-size: 1rem;
}
</style>

<div class="main-content">
    <div class="video-card" style="max-width: 500px; margin: 0 auto; padding: 30px;">
        <h2><i class="fas fa-key"></i> Redefinir senha</h2>

        <?php if ($message_type === 'success'): ?>
            <!-- Mensagem de sucesso com botão de login -->
            <div class="alert-success">
                <i class="fas fa-check-circle"></i>
                <p><?php echo htmlspecialchars($message); ?></p>
                <a href="login.php" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Fazer login
                </a>
            </div>
        <?php elseif ($message_type === 'error'): ?>
            <!-- Mensagem de erro -->
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($message_type !== 'success'): ?>
        <!-- Formulário de redefinição de senha -->
        <form method="POST" class="vision-form">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Nova senha</label>
                <input type="password" id="password" name="password" required 
                       placeholder="Digite sua nova senha"
                       minlength="6">
            </div>
            <div class="form-actions" style="text-align: center; margin-top: 20px;">
                <button type="submit" class="cta-btn">
                    <i class="fas fa-save"></i> Redefinir senha
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>