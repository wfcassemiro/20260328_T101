<?php
session_start();
require_once 'config/database.php';

// Tentar incluir config.php, se não conseguir, definir função generateUUID() diretamente
if (file_exists('config/config.php')) {
    require_once 'config/config.php';
} else {
    // Definir função generateUUID() se não existir
    if (!function_exists('generateUUID')) {
        function generateUUID() {
            return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
        }
    }
}

$page_title = 'Registro';
$error_message = '';
$success_message = '';

// Redirecionar se já estiver logado
if (isLoggedIn()) {
    header('Location: /');
    exit;
}

// Processar registro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validações
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = 'Todos os campos são obrigatórios.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'As senhas não coincidem.';
    } elseif (strlen($password) < 6) {
        $error_message = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Email inválido.';
    } else {
        try {
            // Verificar se email já existe
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error_message = 'Este email já está cadastrado.';
            } else {
                // Determinar role (admin se for o email específico)
                $role = ($email === 'wrbl.traduz@gmail.com') ? 'admin' : 'free';
                
                // Criar usuário
                $stmt = $pdo->prepare("INSERT INTO users (id, email, name, password_hash, role) VALUES (?, ?, ?, ?, ?)");
                $user_id = generateUUID();
                $password_hash = hashPassword($password);
                
                if ($stmt->execute([$user_id, $email, $name, $password_hash, $role])) {
                    $success_message = 'Conta criada com sucesso! Faça login para continuar.';
                    
                    // Log de registro
                    $stmt = $pdo->prepare("INSERT INTO access_logs (user_id, action, ip_address, user_agent) VALUES (?, 'register', ?, ?)");
                    $stmt->execute([$user_id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
                } else {
                    $error_message = 'Erro ao criar conta. Tente novamente.';
                }
            }
        } catch(PDOException $e) {
            $error_message = 'Erro interno. Tente novamente.';
        }
    }
}

include 'includes/header.php';
?>

<div class="min-h-screen px-4 flex items-center justify-center">
    <div class="max-w-md w-full bg-gray-900 rounded-lg p-8">
        <h1 class="text-3xl font-bold mb-8 text-center">Registrar</h1>
        
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
            <div>
                <label for="name" class="block text-sm font-medium mb-2">Nome Completo</label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                    class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                    required
                />
            </div>
            
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
                    minlength="6"
                />
            </div>
            
            <div>
                <label for="confirm_password" class="block text-sm font-medium mb-2">Confirmar Senha</label>
                <input
                    type="password"
                    name="confirm_password"
                    id="confirm_password"
                    class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                    required
                    minlength="6"
                />
            </div>
            
            <button 
                type="submit"
                name="register"
                class="w-full bg-purple-600 hover:bg-purple-700 py-3 rounded-lg font-semibold transition-colors"
            >
                Registrar
            </button>
        </form>
        
        <p class="text-center mt-6 text-gray-400">
            Já tem conta? <a href="/login.php" class="text-purple-400 hover:text-purple-300">Entre aqui</a>
        </p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
