<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/trial_functions.php';

$page_title = 'Login - Translators101';
$page_description = 'Faça login na sua conta Translators101';

$error_message = '';

// Se já está logado, redireciona baseado no role
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? 'free';
    
    // Define redirecionamento baseado no role
    switch ($role) {
    case 'admin':
    $redirect = $_GET['redirect'] ?? '/admin/index.php';
    break;
    case 'subscriber':
    case 'trial_7d':
    case 'trial_24h':
    $redirect = $_GET['redirect'] ?? '/videoteca.php';
    break;
    case 'free':
    default:
    $redirect = $_GET['redirect'] ?? '/faq.php';
    break;
    }
    
    header('Location: ' . $redirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($email) || empty($password)) {
    $error_message = 'Por favor, preencha todos os campos.';
    } else {
    try {
    // Busca o usuário no banco de dados
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Log de debug (remover em produção)
    error_log("Login attempt for email: " . $email);
    error_log("User found: " . ($user ? "Yes (ID: " . $user['id'] . ", Role: " . $user['role'] . ")" : "No"));
    
    // Verifica se o usuário existe e a senha está correta
    $passwordValid = false;
    
    if ($user && !empty($user['password_hash'])) {
    // Verifica a senha usando password_hash
    if (password_verify($password, $user['password_hash'])) {
    $passwordValid = true;
    }
    }
    
    if ($user && $passwordValid) {
    // Verificar se é trial e se foi aprovado
    if (in_array($user['role'], ['trial_7d', 'trial_24h'])) {
        // Verificar se foi aprovado pelo admin
        if (empty($user['trial_approved'])) {
            $error_message = 'Seu acesso ainda não foi liberado. Você receberá uma mensagem no WhatsApp quando for aprovado.';
            $user = null; // Impedir login
        } else {
            // Verificar se expirou
            $user = checkAndUpdateTrialStatus($pdo, $user);
            
            // Se expirou, mostrar mensagem
            if (isset($user['expired']) && $user['expired']) {
                $error_message = 'Seu período de acesso expirou. Para continuar assistindo, assine o plano completo.';
                // Continua o login com role=free
            }
        }
    }
    }
    
    if ($user && $passwordValid) {
    // Login bem-sucedido - define sessões
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['is_subscriber'] = in_array($user['role'], ['subscriber', 'admin', 'trial_7d', 'trial_24h']) ? 1 : 0;
    $_SESSION['is_admin'] = ($user['role'] === 'admin'); // <-- NOVA LINHA
    $_SESSION['subscription_active'] = $user['subscription_active'] ?? false;
    $_SESSION['subscription_expires'] = $user['subscription_expires'] ?? null;
    
    // Armazenar info de trial na sessão
    if (in_array($user['role'], ['trial_7d', 'trial_24h'])) {
        $_SESSION['trial_type'] = $user['role'];
        $_SESSION['trial_started_at'] = $user['trial_started_at'] ?? null;
        $_SESSION['trial_lectures_watched'] = $user['trial_lectures_watched'] ?? '[]';
    }
    
    // Atualiza o último login (verifica se a coluna last_login existe)
    try {
    $updateStmt = $pdo->prepare("UPDATE users SET first_login = 0 WHERE id = ?");
    $updateStmt->execute([$user['id']]);
    } catch (PDOException $e) {
    // Se der erro na atualização, apenas loga mas não interrompe o login
    error_log("Erro ao atualizar last_login: " . $e->getMessage());
    }
    
    // Redireciona baseado no role
    switch ($user['role']) {
    case 'admin':
    $redirect = $_GET['redirect'] ?? '/admin/index.php';
    break;
    case 'subscriber':
    case 'trial_7d':
    case 'trial_24h':
    $redirect = $_GET['redirect'] ?? '/videoteca.php';
    break;
    case 'free':
    default:
    $redirect = $_GET['redirect'] ?? '/faq.php';
    break;
    }
    
    // Log de debug
    error_log("Login successful for user: " . $user['email'] . " (Role: " . $user['role'] . ") - Redirecting to: " . $redirect);
    
    header('Location: ' . $redirect);
    exit;
    } else {
    $error_message = 'Email ou senha inválidos.';
    }
    } catch (PDOException $e) {
    error_log("Erro no login: " . $e->getMessage());
    $error_message = 'Erro interno. Tente novamente.';
    }
    }
}

include __DIR__ . '/vision/includes/head.php';
include __DIR__ . '/vision/includes/header.php';
?>

<style>
/* Estilos para centralizar os botões e dar um tamanho padrão */
.centered-button-container {
  display: flex;
  justify-content: center;
  margin-top: 25px;
}

.cta-btn,
.cta-btn-blue {
  width: 66.666% !important; /* 2/3 do contêiner */
  max-width: 300px; /* Limite para não ficar muito grande em telas largas */
  display: block !important;
  margin: 0 auto !important;
}
/* CSS para garantir o contraste na página de login */
.link-forgot {
  color: var(--accent-gold) !important;
  font-weight: 600 !important;
}

.link-forgot:hover {
  color: #fff !important;
  text-decoration: underline !important;
}
/* Estilo para o botão "Criar conta" - Similar ao Entrar, mas em azul */
.cta-btn-blue {
  display: inline-block !important;
  width: 100% !important;
  padding: 14px 28px !important;
  font-size: 1.1rem !important;
  font-weight: bold !important;
  border-radius: 30px !important;
  border: none !important;
  cursor: pointer !important;
  text-align: center !important;
  transition: all 0.3s ease !important;

  /* Gradiente e Sombra */
  background: linear-gradient(135deg, #3498db, #2980b9) !important;
  color: white !important;
  box-shadow: 0 6px 18px rgba(52, 152, 219, 0.6) !important;
  text-decoration: none !important;
}

.cta-btn-blue:hover {
  transform: scale(1.07) !important;
  box-shadow: 0 8px 25px rgba(52, 152, 219, 0.8) !important;
}

/* NOVO: Estilo para a seção "Não tem uma conta?" */
.create-account-section {
    text-align: center;
    margin-top: 25px;
    padding-top: 25px;
    border-top: 1px solid var(--glass-border);
}
.create-account-section p {
    color: #ccc;
    margin-bottom: 15px;
}
</style>

<div class="main-content">
    <div class="glass-hero">
    <div class="hero-content">
    <h1><i class="fas fa-sign-in-alt"></i> Acesse sua conta</h1>
    <p>Faça login para acessar todo nosso conteúdo</p>
    </div>
    </div>

    <div class="video-card" style="max-width: 520px; margin: 0 auto; padding: 35px 25px; border-radius: 12px;">
    <h2><i class="fas fa-user"></i> Login</h2>

    <?php if ($error_message): ?>
    <div class="alert-error">
    <i class="fas fa-exclamation-triangle"></i>
    <?php echo htmlspecialchars($error_message); ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="vision-form">
    <div class="form-group">
    <label for="email"><i class="fas fa-envelope"></i> Email</label>
    <input type="email" id="email" name="email" required
    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
    </div>

    <div class="form-group">
    <label for="password"><i class="fas fa-lock"></i> Senha</label>
    <input type="password" id="password" name="password" required>
    </div>

    <div class="form-group" style="text-align: right; margin-top: -10px; margin-bottom: 15px;">
    <a href="forgot_password.php" class="link-forgot">
    <i class="fas fa-unlock-alt"></i> Esqueci minha senha
    </a>
    </div>
    <div class="centered-button-container">
    <button type="submit" class="cta-btn">
    <i class="fas fa-sign-in-alt"></i> Entrar
    </button>
    </div>
    </form>
    <div class="create-account-section">
    <p>Ainda não é assinante?</p>
    <a href="/#:~:text=Oferta%20especial-,%3A,-Acesso%20completo%20por" class="cta-btn-blue">
    <i class="fas fa-user-plus"></i> Assinar agora
    </a>
    </div>
    </div>
</div>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>