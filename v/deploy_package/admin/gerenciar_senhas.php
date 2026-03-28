<?php
session_start();
require_once '../config/database.php';

// Verificar se é admin
if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$message = '';
$message_type = '';

// Gerar link de senha para usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_link'])) {
    $user_id = $_POST['user_id'] ?? '';
    
    if (!empty($user_id)) {
        try {
            // Buscar usuário
            $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Gerar novo token
                $reset_token = bin2hex(random_bytes(32));
                
                // Atualizar usuário com token
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET password_reset_token = ?, password_reset_expires = DATE_ADD(NOW(), INTERVAL 7 DAY), first_login = TRUE 
                    WHERE id = ?
                ");
                $stmt->execute([$reset_token, $user_id]);
                
                // Gerar link
                $link = "https://" . $_SERVER['HTTP_HOST'] . "/definir_senha.php?token=" . $reset_token;
                
                // Enviar email automaticamente
                require_once '../config/email.php';
                $email_sent = sendPasswordSetupEmail($user['email'], $user['name'], $reset_token);
                
                $email_status = $email_sent ? "Email enviado!" : "Falha no email.";
                $message = "Link gerado para {$user['name']}: <a href='{$link}' target='_blank' class='underline'>{$link}</a><br><small>{$email_status}</small>";
                $message_type = 'success';
            } else {
                $message = "Usuário não encontrado.";
                $message_type = 'error';
            }
        } catch (PDOException $e) {
            $message = "Erro ao gerar link: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = "Selecione um usuário.";
        $message_type = 'error';
    }
}

// Buscar usuários que precisam definir senha
try {
    $stmt = $pdo->prepare("
        SELECT id, name, email, hotmart_status, created_at, password_reset_expires,
               CASE WHEN password_reset_token IS NOT NULL THEN TRUE ELSE FALSE END as has_pending_reset
        FROM users 
        WHERE first_login = TRUE OR password_reset_token IS NOT NULL
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $pending_users = $stmt->fetchAll();
    
    // Buscar todos os usuários para dropdown
    $stmt = $pdo->prepare("SELECT id, name, email FROM users ORDER BY name");
    $stmt->execute();
    $all_users = $stmt->fetchAll();
    
} catch (Exception $e) {
    $pending_users = [];
    $all_users = [];
}

$page_title = "Gerenciar Senhas";
$active_page = 'gerenciar_senhas';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Translators101 Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        purple: {
                            600: '#7c3aed',
                            700: '#6d28d9'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { background: #0f0f0f; color: #ffff; }
    </style>
</head>
<body class="bg-gray-900 text-white">
    <div class="flex min-h-screen">
        <?php include 'admin_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 ml-64 p-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold mb-2"><?php echo $page_title; ?></h1>
                <p class="text-gray-400">Gerencie links de definição de senha para usuários</p>
            </div>
            
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-600 bg-opacity-20 border border-green-600 text-green-400' : 'bg-red-600 bg-opacity-20 border border-red-600 text-red-400'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Informações importantes -->
            <div class="bg-blue-600 bg-opacity-20 border border-blue-600 border-opacity-30 rounded-lg p-4 mb-8">
                <h3 class="font-semibold text-blue-400 mb-2">
                    <i class="fas fa-info-circle mr-2"></i>Como funciona o sistema de senhas:
                </h3>
                <ul class="text-blue-300 text-sm space-y-1">
                    <li>• <strong>Usuários importados/webhook:</strong> Recebem link para definir senha</li>
                    <li>• <strong>Token expira em:</strong> 7 dias após geração</li>
                    <li>• <strong>Senha temporária:</strong> Não permite login (apenas com link)</li>
                    <li>• <strong>Após definir senha:</strong> Login normal funcionará</li>
                </ul>
            </div>
            
            <!-- Gerar Link para Usuário -->
            <div class="bg-gray-800 rounded-lg p-6 mb-8">
                <h2 class="text-xl font-semibold mb-4">
                    <i class="fas fa-link mr-2"></i>Gerar Link de Definição de Senha
                </h2>
                
                <form method="POST" class="flex gap-4 items-end">
                    <div class="flex-1">
                        <label class="block text-sm font-medium mb-2">Selecionar Usuário</label>
                        <select name="user_id" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white">
                            <option value="">Escolha um usuário...</option>
                            <?php foreach($all_users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['name'] . ' (' . $user['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" name="generate_link" class="bg-purple-600 hover:bg-purple-700 px-6 py-2 rounded-lg font-semibold transition-colors">
                        <i class="fas fa-link mr-2"></i>Gerar Link
                    </button>
                </form>
            </div>
            
            <!-- Usuários Pendentes -->
            <div class="bg-gray-800 rounded-lg p-6">
                <h2 class="text-xl font-semibold mb-4">
                    <i class="fas fa-clock mr-2"></i>Usuários com Senha Pendente
                </h2>
                
                <?php if (empty($pending_users)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-check-circle text-4xl text-green-600 mb-4"></i>
                        <p class="text-gray-400">Todos os usuários definiram suas senhas!</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-700">
                                    <th class="text-left py-3 px-2">Usuário</th>
                                    <th class="text-left py-3 px-2">Status Hotmart</th>
                                    <th class="text-left py-3 px-2">Criado em</th>
                                    <th class="text-left py-3 px-2">Link expira</th>
                                    <th class="text-left py-3 px-2">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($pending_users as $user): ?>
                                    <tr class="border-b border-gray-700 hover:bg-gray-700">
                                        <td class="py-3 px-2">
                                            <div>
                                                <div class="font-medium"><?php echo htmlspecialchars($user['name']); ?></div>
                                                <div class="text-xs text-gray-400"><?php echo htmlspecialchars($user['email']); ?></div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-2">
                                            <span class="px-2 py-1 rounded text-xs <?php echo $user['hotmart_status'] === 'ACTIVE' ? 'bg-green-600' : 'bg-gray-600'; ?>">
                                                <?php echo $user['hotmart_status']; ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-2">
                                            <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>
                                        </td>
                                        <td class="py-3 px-2">
                                            <?php if ($user['password_reset_expires']): ?>
                                                <?php 
                                                $expires = strtotime($user['password_reset_expires']);
                                                $now = time();
                                                $expired = $expires < $now;
                                                ?>
                                                <span class="<?php echo $expired ? 'text-red-400' : 'text-green-400'; ?>">
                                                    <?php echo date('d/m/Y H:i', $expires); ?>
                                                    <?php if ($expired): ?>
                                                        <i class="fas fa-exclamation-triangle ml-1"></i>
                                                    <?php endif; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-400">Não gerado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-2">
                                            <?php if ($user['has_pending_reset']): ?>
                                                <span class="px-2 py-1 rounded text-xs bg-yellow-600">Aguardando</span>
                                            <?php else: ?>
                                                <span class="px-2 py-1 rounded text-xs bg-gray-600">Sem link</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>