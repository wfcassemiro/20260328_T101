<?php
session_start();
require_once __DIR__ . 
'/config/database.php';
require_once __DIR__ . 
'/config/config.php'; // Incluir o arquivo config.php que contém a função verifyPassword()

$page_title = 'Login';
$error_message = '';
$success_message = '';

// Redirecionar se já estiver logado
if (isLoggedIn()) {
    $redirect_url = isset($_GET['redirect']) ? $_GET['redirect'] : '/videoteca.php'; // Alterado para /videoteca.php
    header('Location: ' . $redirect_url);
    exit;
}

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error_message = 'Todos os campos são obrigatórios.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && verifyPassword($password, $user['password_hash'])) {
                // Login bem-sucedido
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                
                // Log de acesso
                $stmt = $pdo->prepare("INSERT INTO access_logs (user_id, action, ip_address, user_agent) VALUES (?, 'login', ?, ?)");
                $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
                
                $redirect_url = isset($_POST['redirect']) ? $_POST['redirect'] : '/videoteca.php'; // Alterado para /videoteca.php
                header('Location: ' . $redirect_url);
                exit;
            } else {
                $error_message = 'Email ou senha incorretos.';
            }
        } catch(PDOException $e) {
            $error_message = 'Erro interno. Tente novamente.';
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="min-h-screen px-4 flex items-center justify-center">
    <div class="max-w-md w-full bg-gray-900 rounded-lg p-8">
        <h1 class="text-3xl font-bold mb-8 text-center">Entrar</h1>
        
        <?php if ($error_message): ?>
            <div class="bg-red-600 text-white p-4 rounded-lg mb-6">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="bg-green-600 text-white p-4 rounded-lg mb-6">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-6">
            <?php if (isset($_GET['redirect'])): ?>
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_GET['redirect']); ?>">
            <?php endif; ?>
            <div>
                <label for="email" class="block text-sm font-medium mb-2">Email</label>
                <input
                    type="email"
                    name="email"
                    id="email"
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                    required
                />
            </div>
            
            <div>
                <label for="password" class="block text-sm font-medium mb-2">Senha</label>
                <input
                    type="password"
                    name="password"
                    id="password"
                    class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                    required
                />
            </div>
            
            <button 
                type="submit"
                name="login"
                class="w-full bg-purple-600 hover:bg-purple-700 py-3 rounded-lg font-semibold transition-colors"
            >
                Entrar
            </button>
        </form>
        
        <p class="text-center mt-6 text-gray-400">
            Não tem conta? <a href="/registro.php" class="text-purple-400 hover:text-purple-300">Registre-se</a>
        </p>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
