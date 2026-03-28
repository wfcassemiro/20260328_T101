<?php
session_start();
require_once '../config/database.php';
require_once '../config/email.php';

// Verificar se é admin
if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$message = '';
$message_type = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'test_email': {
            $test_email = $_POST['test_email'] ?? '';
            if (!empty($test_email) && filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
                $emailSender = new EmailSender();
                $result = $emailSender->testEmail($test_email);
                
                if ($result['success']) {
                    $message = "✅ Email de teste enviado com sucesso para {$test_email}! Método: {$result['method']}";
                    $message_type = 'success';
                } else {
                    $message = "❌ Falha ao enviar email de teste para {$test_email}. Verifique as configurações SMTP.";
                    $message_type = 'error';
                }
            } else {
                $message = "Email inválido.";
                $message_type = 'error';
            }
            break;
        }
        
        case 'send_custom_email': {
            $selected_users = $_POST['selected_users'] ?? [];
            $email_subject = $_POST['email_subject'] ?? '';
            $email_content = $_POST['email_content'] ?? '';
            
            if (empty($selected_users)) {
                $message = "❌ Nenhum usuário selecionado.";
                $message_type = 'error';
            } elseif (empty($email_subject) || empty($email_content)) {
                $message = "❌ Assunto e conteúdo são obrigatórios.";
                $message_type = 'error';
            } else {
                try {
                    $emailSender = new EmailSender();
                    $success_count = 0;
                    $total_count = count($selected_users);
                    
                    foreach ($selected_users as $user_id) {
                        // Buscar dados do usuário
                        $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $user = $stmt->fetch();
                        
                        if ($user) {
                            // Processar variáveis no conteúdo
                            $processed_content = processEmailVariables($email_content, $user, $pdo);
                            $processed_subject = processEmailVariables($email_subject, $user, $pdo);
                            
                            // Criar HTML com template base
                            $html_content = EmailTemplates::getCustomEmailTemplate($processed_subject, $processed_content);
                            
                            // Enviar email
                            if ($emailSender->sendEmail($user['email'], $user['name'], $processed_subject, $html_content)) {
                                $success_count++;
                            }
                        }
                    }
                    
                    if ($success_count === $total_count) {
                        $message = "✅ Todos os {$total_count} emails foram enviados com sucesso!";
                        $message_type = 'success';
                    } else {
                        $message = "⚠️ {$success_count} de {$total_count} emails foram enviados. Alguns falharam.";
                        $message_type = 'warning';
                    }
                    
                } catch (Exception $e) {
                    $message = "❌ Erro ao enviar emails: " . $e->getMessage();
                    $message_type = 'error';
                }
            }
            break;
        }
        
        case 'send_password_email': {
            $user_id = $_POST['user_id'] ?? '';
            if (!empty($user_id)) {
                try {
                    // Buscar usuário
                    $stmt = $pdo->prepare("SELECT name, email, password_reset_token FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    
                    if ($user && !empty($user['password_reset_token'])) {
                        $result = sendPasswordSetupEmail($user['email'], $user['name'], $user['password_reset_token']);
                        
                        if ($result) {
                            $message = "✅ Email de definição de senha enviado para {$user['name']} ({$user['email']})!";
                            $message_type = 'success';
                        } else {
                            $message = "❌ Falha ao enviar email para {$user['name']}. Verifique as configurações.";
                            $message_type = 'error';
                        }
                    } else {
                        $message = "Usuário não encontrado ou não possui token de senha pendente.";
                        $message_type = 'error';
                    }
                } catch (Exception $e) {
                    $message = "Erro: " . $e->getMessage();
                    $message_type = 'error';
                }
            }
            break;
        }
        
        case 'send_welcome_email': {
            $user_id = $_POST['user_id'] ?? '';
            if (!empty($user_id)) {
                try {
                    // Buscar usuário
                    $stmt = $pdo->prepare("SELECT name, email, password_reset_token FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    
                    if ($user && !empty($user['password_reset_token'])) {
                        $result = sendWelcomeHotmartEmail($user['email'], $user['name'], $user['password_reset_token']);
                        
                        if ($result) {
                            $message = "✅ Email de boas-vindas enviado para {$user['name']} ({$user['email']})!";
                            $message_type = 'success';
                        } else {
                            $message = "❌ Falha ao enviar email de boas-vindas para {$user['name']}.";
                            $message_type = 'error';
                        }
                    } else {
                        $message = "Usuário não encontrado ou não possui token de senha pendente.";
                        $message_type = 'error';
                    }
                } catch (Exception $e) {
                    $message = "Erro: " . $e->getMessage();
                    $message_type = 'error';
                }
            }
            break;
        }
    }
}

// Buscar usuários ativos para composição de emails
try {
    $stmt = $pdo->prepare("
        SELECT id, name, email, role, hotmart_status, created_at
        FROM users 
        WHERE email IS NOT NULL AND email != ''
        ORDER BY name ASC
    ");
    $stmt->execute();
    $all_users = $stmt->fetchAll();
} catch (Exception $e) {
    $all_users = [];
}

// Buscar usuários com tokens pendentes
try {
    $stmt = $pdo->prepare("
        SELECT id, name, email, created_at, hotmart_status, password_reset_expires
        FROM users 
        WHERE password_reset_token IS NOT NULL AND password_reset_expires > NOW()
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $pending_users = $stmt->fetchAll();
} catch (Exception $e) {
    $pending_users = [];
}

// Buscar palestras para variáveis
try {
    $stmt = $pdo->prepare("SELECT id, title FROM lectures ORDER BY title ASC");
    $stmt->execute();
    $lectures = $stmt->fetchAll();
} catch (Exception $e) {
    $lectures = [];
}

/**
 * Processar variáveis no conteúdo do email
 */
function processEmailVariables($content, $user, $pdo) {
    // Variáveis básicas do usuário
    $content = str_replace('[nome]', $user['name'], $content);
    $content = str_replace('[email]', $user['email'], $content);
    
    // Variáveis de data
    $content = str_replace('[data]', date('d/m/Y'), $content);
    $content = str_replace('[ano]', date('Y'), $content);
    
    // Variáveis do sistema
    $content = str_replace('[plataforma]', 'Translators101', $content);
    $content = str_replace('[site]', 'https://translators101.com', $content);
    
    // Contar palestras
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lectures");
        $stmt->execute();
        $total_lectures = $stmt->fetchColumn();
        $content = str_replace('[total_palestras]', $total_lectures, $content);
    } catch (Exception $e) {
        $content = str_replace('[total_palestras]', '0', $content);
    }
    
    return $content;
}

$page_title = "Sistema de Emails";
$active_page = 'emails';

// Verificar qual tab deve estar ativa
$active_tab = $_GET['tab'] ?? 'compose';
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
                <p class="text-gray-400">Gerencie toda a comunicação por email da plataforma</p>
            </div>
            
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?php 
                    echo $message_type === 'success' ? 'bg-green-600 bg-opacity-20 border border-green-600 text-green-400' : 
                         ($message_type === 'warning' ? 'bg-yellow-600 bg-opacity-20 border border-yellow-600 text-yellow-400' :
                          'bg-red-600 bg-opacity-20 border border-red-600 text-red-400'); 
                ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Verificação de configuração -->
            <?php if (!isEmailConfigured()): ?>
                <div class="bg-orange-600 bg-opacity-20 border border-orange-600 border-opacity-30 rounded-lg p-4 mb-8">
                    <h3 class="font-semibold text-orange-400 mb-2">
                        <i class="fas fa-exclamation-triangle mr-2"></i>Configuração Necessária:
                    </h3>
                    <p class="text-orange-300 text-sm mb-3">
                        Para que os emails funcionem, você precisa configurar a senha do email <strong>contato@translators101.com</strong> 
                        no arquivo <code>/config/email.php</code>.
                    </p>
                    <div class="bg-gray-800 p-3 rounded text-xs font-mono text-gray-300">
                        define('SMTP_PASSWORD', 'sua_senha_aqui');
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-green-600 bg-opacity-20 border border-green-600 border-opacity-30 rounded-lg p-4 mb-8">
                    <h3 class="font-semibold text-green-400 mb-2">
                        <i class="fas fa-check-circle mr-2"></i>Sistema Configurado:
                    </h3>
                    <p class="text-green-300 text-sm">
                        O sistema de email está devidamente configurado e pronto para uso!
                    </p>
                </div>
            <?php endif; ?>
            
            <!-- Tabs -->
            <div class="mb-8">
                <div class="flex space-x-1 bg-gray-800 p-1 rounded-lg">
                    <button onclick="showTab('compose')" id="tab-compose" class="tab-button flex-1 py-2 px-4 rounded-md text-sm font-medium transition-colors <?php echo $active_tab === 'compose' ? 'bg-purple-600 text-white' : 'text-gray-400 hover:text-white'; ?>">
                        <i class="fas fa-edit mr-2"></i>Compor Email
                    </button>
                    <button onclick="showTab('quick')" id="tab-quick" class="tab-button flex-1 py-2 px-4 rounded-md text-sm font-medium transition-colors <?php echo $active_tab === 'quick' ? 'bg-purple-600 text-white' : 'text-gray-400 hover:text-white'; ?>">
                        <i class="fas fa-bolt mr-2"></i>Ações Rápidas
                    </button>
                    <button onclick="showTab('test')" id="tab-test" class="tab-button flex-1 py-2 px-4 rounded-md text-sm font-medium transition-colors <?php echo $active_tab === 'test' ? 'bg-purple-600 text-white' : 'text-gray-400 hover:text-white'; ?>">
                        <i class="fas fa-vial mr-2"></i>Testar Sistema
                    </button>
                </div>
            </div>
            
            <!-- Tab: Compor Email -->
            <div id="content-compose" class="tab-content <?php echo $active_tab !== 'compose' ? 'hidden' : ''; ?>">
                <div class="bg-gray-800 rounded-lg p-6">
                    <h2 class="text-xl font-semibold mb-6">
                        <i class="fas fa-edit mr-2"></i>Compor Email Personalizado
                    </h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="send_custom_email">
                        
                        <!-- Seleção de Usuários -->
                        <div class="mb-6">
                            <div class="flex justify-between items-center mb-4">
                                <label class="block text-sm font-medium">Destinatários</label>
                                <div class="flex gap-2">
                                    <button type="button" onclick="selectAllUsers()" class="text-xs bg-blue-600 hover:bg-blue-700 px-3 py-1 rounded transition-colors">
                                        <i class="fas fa-check-double mr-1"></i>Selecionar Todos
                                    </button>
                                    <button type="button" onclick="clearAllUsers()" class="text-xs bg-gray-600 hover:bg-gray-700 px-3 py-1 rounded transition-colors">
                                        <i class="fas fa-times mr-1"></i>Limpar Seleção
                                    </button>
                                </div>
                            </div>
                            
                            <div class="max-h-60 overflow-y-auto bg-gray-700 rounded-lg p-4 space-y-2">
                                <?php foreach($all_users as $user): ?>
                                    <label class="flex items-center space-x-3 p-2 hover:bg-gray-600 rounded cursor-pointer">
                                        <input type="checkbox" name="selected_users[]" value="<?php echo $user['id']; ?>" class="user-checkbox">
                                        <div class="flex-1">
                                            <div class="font-medium"><?php echo htmlspecialchars($user['name']); ?></div>
                                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($user['email']); ?></div>
                                        </div>
                                        <span class="text-xs px-2 py-1 rounded <?php echo $user['role'] === 'admin' ? 'bg-red-600' : 'bg-purple-600'; ?>">
                                            <?php echo strtoupper($user['role']); ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Assunto -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2">Assunto do Email</label>
                            <input 
                                type="text" 
                                name="email_subject" 
                                required 
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white"
                                placeholder="Ex: Novidades da plataforma Translators101"
                                value=""
                            >
                        </div>
                        
                        <!-- Conteúdo -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2">Conteúdo do Email</label>
                            <textarea 
                                name="email_content" 
                                required 
                                rows="12"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white"
                                placeholder="Digite o conteúdo do seu email aqui..."
                            ></textarea>
                        </div>
                        
                        <!-- Variáveis Disponíveis -->
                        <div class="mb-6 p-4 bg-blue-600 bg-opacity-20 border border-blue-600 border-opacity-30 rounded-lg">
                            <h4 class="font-semibold text-blue-400 mb-3">
                                <i class="fas fa-magic mr-2"></i>Variáveis Disponíveis:
                            </h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-xs">
                                <div>
                                    <strong class="text-blue-300">Usuário:</strong>
                                    <div class="text-blue-200 space-y-1 mt-1">
                                        <div><code>[nome]</code> - Nome do usuário</div>
                                        <div><code>[email]</code> - Email do usuário</div>
                                    </div>
                                </div>
                                <div>
                                    <strong class="text-blue-300">Sistema:</strong>
                                    <div class="text-blue-200 space-y-1 mt-1">
                                        <div><code>[plataforma]</code> - Translators101</div>
                                        <div><code>[site]</code> - URL do site</div>
                                        <div><code>[total_palestras]</code> - Total de palestras</div>
                                    </div>
                                </div>
                                <div>
                                    <strong class="text-blue-300">Data:</strong>
                                    <div class="text-blue-200 space-y-1 mt-1">
                                        <div><code>[data]</code> - Data atual</div>
                                        <div><code>[ano]</code> - Ano atual</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Botão Enviar -->
                        <div class="text-right">
                            <button type="submit" class="bg-purple-600 hover:bg-purple-700 px-6 py-3 rounded-lg font-semibold transition-colors">
                                <i class="fas fa-paper-plane mr-2"></i>Enviar Emails
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Tab: Ações Rápidas -->
            <div id="content-quick" class="tab-content <?php echo $active_tab !== 'quick' ? 'hidden' : ''; ?>">
                <div class="bg-gray-800 rounded-lg p-6">
                    <h2 class="text-xl font-semibold mb-4">
                        <i class="fas fa-bolt mr-2"></i>Usuários com Links Pendentes
                    </h2>
                    
                    <?php if (empty($pending_users)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-inbox text-4xl text-gray-600 mb-4"></i>
                            <p class="text-gray-400">Nenhum usuário com links de senha pendentes.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-700">
                                        <th class="text-left py-3 px-2">Usuário</th>
                                        <th class="text-left py-3 px-2">Status</th>
                                        <th class="text-left py-3 px-2">Link expira</th>
                                        <th class="text-left py-3 px-2">Ações de Email</th>
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
                                                    <?php echo $user['hotmart_status'] ?: 'MANUAL'; ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-2">
                                                <?php 
                                                $expires = strtotime($user['password_reset_expires']);
                                                $now = time();
                                                $expired = $expires < $now;
                                                ?>
                                                <span class="<?php echo $expired ? 'text-red-400' : 'text-green-400'; ?>">
                                                    <?php echo date('d/m/Y H:i', $expires); ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-2">
                                                <div class="flex gap-2">
                                                    <!-- Email de Senha -->
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="action" value="send_password_email">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button 
                                                            type="submit" 
                                                            class="bg-purple-600 hover:bg-purple-700 px-3 py-1 rounded text-xs font-semibold transition-colors"
                                                            title="Enviar email com link para definir senha"
                                                        >
                                                            <i class="fas fa-key mr-1"></i>Senha
                                                        </button>
                                                    </form>
                                                    
                                                    <!-- Email de Boas-vindas (só para usuários Hotmart) -->
                                                    <?php if ($user['hotmart_status'] === 'ACTIVE'): ?>
                                                        <form method="POST" class="inline">
                                                            <input type="hidden" name="action" value="send_welcome_email">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button 
                                                                type="submit" 
                                                                class="bg-green-600 hover:bg-green-700 px-3 py-1 rounded text-xs font-semibold transition-colors"
                                                                title="Enviar email de boas-vindas Hotmart"
                                                            >
                                                                <i class="fas fa-heart mr-1"></i>Boas-vindas
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Tab: Teste de Sistema -->
            <div id="content-test" class="tab-content <?php echo $active_tab !== 'test' ? 'hidden' : ''; ?>">
                <div class="bg-gray-800 rounded-lg p-6">
                    <h2 class="text-xl font-semibold mb-4">
                        <i class="fas fa-vial mr-2"></i>Testar Envio de Email
                    </h2>
                    
                    <form method="POST" class="flex gap-4 items-end">
                        <input type="hidden" name="action" value="test_email">
                        <div class="flex-1">
                            <label class="block text-sm font-medium mb-2">Email de Teste</label>
                            <input 
                                type="email" 
                                name="test_email" 
                                required 
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white"
                                placeholder="seu@email.com"
                                value="contato@translators101.com"
                            >
                        </div>
                        
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded-lg font-semibold transition-colors">
                            <i class="fas fa-paper-plane mr-2"></i>Enviar Teste
                        </button>
                    </form>
                    
                    <p class="text-xs text-gray-400 mt-2">
                        Este teste verifica se o sistema de email está funcionando corretamente.
                    </p>
                    
                    <!-- Informações de Sistema -->
                    <div class="mt-8 p-4 bg-gray-700 rounded-lg">
                        <h3 class="font-semibold text-purple-400 mb-3">
                            <i class="fas fa-info-circle mr-2"></i>Informações do Sistema
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="font-semibold text-gray-300 mb-2">📧 Configuração SMTP:</h4>
                                <ul class="text-sm text-gray-400 space-y-1">
                                    <li><strong>Host:</strong> br1189.hostgator.com.br</li>
                                    <li><strong>Porta:</strong> 587 (TLS)</li>
                                    <li><strong>Email:</strong> contato@translators101.com</li>
                                    <li><strong>Método:</strong> <?php echo class_exists('PHPMailer\\PHPMailer\\PHPMailer') ? 'PHPMailer' : 'mail() nativo'; ?></li>
                                    <li><strong>Status:</strong> <span class="<?php echo isEmailConfigured() ? 'text-green-400' : 'text-red-400'; ?>"><?php echo isEmailConfigured() ? 'Configurado' : 'Não configurado'; ?></span></li>
                                </ul>
                            </div>
                            
                            <div>
                                <h4 class="font-semibold text-gray-300 mb-2">📨 Tipos de Email:</h4>
                                <ul class="text-sm text-gray-400 space-y-1">
                                    <li><strong>Personalizado:</strong> Composição livre</li>
                                    <li><strong>Definir Senha:</strong> Link para novos usuários</li>
                                    <li><strong>Boas-vindas:</strong> Compras via Hotmart</li>
                                    <li><strong>Confirmação:</strong> Senha definida com sucesso</li>
                                    <li><strong>Teste:</strong> Verificação do sistema</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Esconder todos os conteúdos
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remover classe ativa de todos os botões
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('bg-purple-600', 'text-white');
                button.classList.add('text-gray-400');
            });
            
            // Mostrar conteúdo ativo
            document.getElementById('content-' + tabName).classList.remove('hidden');
            
            // Ativar botão
            document.getElementById('tab-' + tabName).classList.add('bg-purple-600', 'text-white');
            document.getElementById('tab-' + tabName).classList.remove('text-gray-400');
        }
        
        function selectAllUsers() {
            document.querySelectorAll('.user-checkbox').forEach(checkbox => {
                checkbox.checked = true;
            });
        }
        
        function clearAllUsers() {
            document.querySelectorAll('.user-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
        }

        // Inicializar tab ativa baseada no parâmetro da URL
        document.addEventListener('DOMContentLoaded', function() {
            const activeTab = '<?php echo $active_tab; ?>';
            if (activeTab && activeTab !== 'compose') {
                showTab(activeTab);
            }
        });
    </script>
</body>
</html>