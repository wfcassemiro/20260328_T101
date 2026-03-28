<?php
session_start();
require_once __DIR__ . '/config/database.php';

$page_title = 'Criar Conta - Translators101';
$page_description = 'Crie sua conta na plataforma Translators101';

$error_message = '';
$success_message = '';

// Se já está logado, redireciona
if (isset($_SESSION['user_id'])) {
    header('Location: /videoteca.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = 'Por favor, preencha todos os campos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Por favor, insira um email válido.';
    } elseif (strlen($password) < 6) {
        $error_message = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'As senhas não coincidem.';
    } else {
        try {
            // Verificar se email já existe
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error_message = 'Este email já está cadastrado.';
            } else {
                // Criar usuário
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, active, created_at) VALUES (?, ?, ?, 1, NOW())");
                $stmt->execute([$name, $email, $password_hash]);
                
                $success_message = 'Conta criada com sucesso! Faça login para continuar.';
            }
        } catch (PDOException $e) {
            $error_message = 'Erro interno. Tente novamente mais tarde.';
        }
    }
}

include __DIR__ . '/vision/includes/head.php';
include __DIR__ . '/vision/includes/header.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-user-plus"></i> Criar nova conta</h1>
            <p>Junte-se à comunidade Translators101</p>
        </div>
    </div>

    <div class="video-card" style="max-width: 500px; margin: 0 auto;">
        <h2><i class="fas fa-user-plus"></i> Registrar-se</h2>
        
        <?php if ($error_message): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="login.php" class="cta-btn">
                    <i class="fas fa-sign-in-alt"></i> Fazer Login
                </a>
            </div>
        <?php else: ?>
            <form method="POST" class="vision-form">
                <div class="form-group">
                    <label for="name">
                        <i class="fas fa-user"></i> Nome Completo
                    </label>
                    <input type="text" id="name" name="name" required 
                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> Email
                    </label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Senha
                    </label>
                    <input type="password" id="password" name="password" required>
                    <small style="color: #ccc; font-size: 0.8rem;">Mínimo 6 caracteres</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">
                        <i class="fas fa-lock"></i> Confirmar Senha
                    </label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="cta-btn">
                        <i class="fas fa-user-plus"></i> Criar Conta
                    </button>
                </div>
            </form>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--glass-border);">
            <p style="color: #ccc; margin-bottom: 15px;">Já tem uma conta?</p>
            <a href="login.php" class="page-btn">
                <i class="fas fa-sign-in-alt"></i> Fazer Login
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>