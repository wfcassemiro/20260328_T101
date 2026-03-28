<?php
session_start();
require_once '../config/database.php';
require_once '../config/email.php';

// Verificar se é admin
if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$page_title = 'Gerenciar Usuários';
$success_message = '';
$error_message = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_user_role':
            $user_id = $_POST['user_id'];
            $new_role = $_POST['role'];
            $subscription_type = $_POST['subscription_type'] ?? null;
            $subscription_expires = $_POST['subscription_expires'] ?? null;
            
            try {
                $stmt = $pdo->prepare("UPDATE users SET role = ?, subscription_type = ?, subscription_expires = ? WHERE id = ?");
                $stmt->execute([$new_role, $subscription_type, $subscription_expires, $user_id]);
                $success_message = "Usuário atualizado com sucesso!";
            } catch (Exception $e) {
                $error_message = "Erro ao atualizar usuário: " . $e->getMessage();
            }
            break;
        
        case 'toggle_user_status':
            $user_id = $_POST['user_id'];
            $new_status = $_POST['is_active'] === '1' ? 0 : 1;
            
            try {
                $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
                $stmt->execute([$new_status, $user_id]);
                $status_text = $new_status ? 'ativado' : 'desativado';
                $success_message = "Usuário $status_text com sucesso!";
            } catch (Exception $e) {
                $error_message = "Erro ao alterar status: " . $e->getMessage();
            }
            break;
        
        case 'delete_user':
            $user_id = $_POST['user_id'];
            $user_email = $_POST['user_email'];
            
            if ($user_email === 'wrbl.traduz@gmail.com') {
                $error_message = "Não é possível excluir o usuário administrador principal.";
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $success_message = "Usuário excluído com sucesso!";
                } catch (Exception $e) {
                    $error_message = "Erro ao excluir usuário: " . $e->getMessage();
                }
            }
            break;
        
        case 'send_password_email':
            $user_id = $_POST['user_id'];
            try {
                $stmt = $pdo->prepare("SELECT name, email, password_reset_token FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                if ($user && !empty($user['password_reset_token'])) {
                    $result = sendPasswordSetupEmail($user['email'], $user['name'], $user['password_reset_token']);
                    
                    if ($result) {
                        $success_message = "✅ Email de definição de senha enviado para {$user['name']}!";
                    } else {
                        $error_message = "❌ Falha ao enviar email para {$user['name']}.";
                    }
                } else {
                    $error_message = "Usuário não possui token de senha pendente.";
                }
            } catch (Exception $e) {
                $error_message = "Erro: " . $e->getMessage();
            }
            break;
        
        case 'send_welcome_email':
            $user_id = $_POST['user_id'];
            try {
                $stmt = $pdo->prepare("SELECT name, email, password_reset_token FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                if ($user && !empty($user['password_reset_token'])) {
                    $result = sendWelcomeHotmartEmail($user['email'], $user['name'], $user['password_reset_token']);
                    
                    if ($result) {
                        $success_message = "✅ Email de boas-vindas enviado para {$user['name']}!";
                    } else {
                        $error_message = "❌ Falha ao enviar email de boas-vindas.";
                    }
                } else {
                    $error_message = "Usuário não possui token de senha pendente.";
                }
            } catch (Exception $e) {
                $error_message = "Erro: " . $e->getMessage();
            }
            break;
        
        case 'bulk_send_password':
            $selected_users = $_POST['selected_users'] ?? [];
            if (empty($selected_users)) {
                $error_message = "❌ Nenhum usuário selecionado.";
            } else {
                $success_count = 0;
                $total_count = count($selected_users);
                
                foreach ($selected_users as $user_id) {
                    try {
                        $stmt = $pdo->prepare("SELECT name, email, password_reset_token FROM users WHERE id = ? AND password_reset_token IS NOT NULL");
                        $stmt->execute([$user_id]);
                        $user = $stmt->fetch();
                        
                        if ($user) {
                            if (sendPasswordSetupEmail($user['email'], $user['name'], $user['password_reset_token'])) {
                                $success_count++;
                            }
                        }
                    } catch (Exception $e) {
                        // Log error but continue
                    }
                }
                
                if ($success_count === $total_count) {
                    $success_message = "✅ Emails de senha enviados para {$total_count} usuários!";
                } else {
                    $success_message = "⚠️ {$success_count} de {$total_count} emails enviados com sucesso.";
                }
            }
            break;
        
        case 'bulk_send_welcome':
            $selected_users = $_POST['selected_users'] ?? [];
            if (empty($selected_users)) {
                $error_message = "❌ Nenhum usuário selecionado.";
            } else {
                $success_count = 0;
                $total_count = count($selected_users);
                
                foreach ($selected_users as $user_id) {
                    try {
                        $stmt = $pdo->prepare("SELECT name, email, password_reset_token, hotmart_status FROM users WHERE id = ? AND password_reset_token IS NOT NULL AND hotmart_status = 'ACTIVE'");
                        $stmt->execute([$user_id]);
                        $user = $stmt->fetch();
                        
                        if ($user) {
                            if (sendWelcomeHotmartEmail($user['email'], $user['name'], $user['password_reset_token'])) {
                                $success_count++;
                            }
                        }
                    } catch (Exception $e) {
                        // Log error but continue
                    }
                }
                
                if ($success_count === $total_count) {
                    $success_message = "✅ Emails de boas-vindas enviados para {$total_count} usuários!";
                } else {
                    $success_message = "⚠️ {$success_count} de {$total_count} emails enviados com sucesso.";
                }
            }
            break;
    }
}

// Parâmetros de busca
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$token_filter = $_GET['token'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Construir query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

if ($status_filter !== '') {
    $where_conditions[] = "is_active = ?";
    $params[] = $status_filter;
}

if ($token_filter === 'pending') {
    $where_conditions[] = "password_reset_token IS NOT NULL AND password_reset_expires > NOW()";
} elseif ($token_filter === 'expired') {
    $where_conditions[] = "password_reset_token IS NOT NULL AND password_reset_expires <= NOW()";
} elseif ($token_filter === 'none') {
    $where_conditions[] = "password_reset_token IS NULL";
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Contar total
    $count_sql = "SELECT COUNT(*) FROM users $where_sql";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_users = $stmt->fetchColumn();
    
    // Buscar usuários
    $sql = "SELECT *, 
            CASE 
                WHEN password_reset_token IS NOT NULL AND password_reset_expires > NOW() THEN 'pending'
                WHEN password_reset_token IS NOT NULL AND password_reset_expires <= NOW() THEN 'expired'
                ELSE 'none'
            END as token_status
            FROM users $where_sql 
            ORDER BY created_at DESC 
            LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    // Estatísticas gerais
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $role_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1");
    $active_users = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE password_reset_token IS NOT NULL AND password_reset_expires > NOW()");
    $pending_tokens = $stmt->fetchColumn();
    
    $total_pages = ceil($total_users / $per_page);
    
} catch (Exception $e) {
    $users = [];
    $role_stats = [];
    $total_users = $active_users = $pending_tokens = 0;
    $total_pages = 0;
}

$active_page = 'usuarios';
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
                <p class="text-gray-400">Gerencie usuários, permissões e envie emails</p>
            </div>
            
            <!-- Messages -->
            <?php if ($success_message): ?>
                <div class="mb-6 p-4 rounded-lg bg-green-600 bg-opacity-20 border border-green-600 text-green-400">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="mb-6 p-4 rounded-lg bg-red-600 bg-opacity-20 border border-red-600 text-red-400">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Estatísticas -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-gray-800 rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Total de Usuários</p>
                            <p class="text-2xl font-bold"><?php echo $total_users; ?></p>
                        </div>
                        <i class="fas fa-users text-blue-400 text-2xl"></i>
                    </div>
                </div>
                
                <div class="bg-gray-800 rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Usuários Ativos</p>
                            <p class="text-2xl font-bold"><?php echo $active_users; ?></p>
                        </div>
                        <i class="fas fa-user-check text-green-400 text-2xl"></i>
                    </div>
                </div>
                
                <div class="bg-gray-800 rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Tokens Pendentes</p>
                            <p class="text-2xl font-bold"><?php echo $pending_tokens; ?></p>
                        </div>
                        <i class="fas fa-key text-yellow-400 text-2xl"></i>
                    </div>
                </div>
                
                <div class="bg-gray-800 rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Admins</p>
                            <p class="text-2xl font-bold"><?php echo $role_stats['admin'] ?? 0; ?></p>
                        </div>
                        <i class="fas fa-user-shield text-purple-400 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Filtros -->
            <div class="bg-gray-800 rounded-lg p-6 mb-8">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label for="search" class="block text-sm font-medium mb-2">Buscar Usuário</label>
                        <input
                            type="text"
                            name="search"
                            id="search"
                            value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Nome ou email..."
                            class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none"
                        />
                    </div>
                    
                    <div>
                        <label for="role" class="block text-sm font-medium mb-2">Tipo de Usuário</label>
                        <select
                            name="role"
                            id="role"
                            class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none"
                        >
                            <option value="">Todos os tipos</option>
                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                            <option value="subscriber" <?php echo $role_filter === 'subscriber' ? 'selected' : ''; ?>>Assinante</option>
                            <option value="free" <?php echo $role_filter === 'free' ? 'selected' : ''; ?>>Usuário Gratuito</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="status" class="block text-sm font-medium mb-2">Status</label>
                        <select
                            name="status"
                            id="status"
                            class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none"
                        >
                            <option value="">Todos os status</option>
                            <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Ativo</option>
                            <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inativo</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="token" class="block text-sm font-medium mb-2">Token de Senha</label>
                        <select
                            name="token"
                            id="token"
                            class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none"
                        >
                            <option value="">Todos</option>
                            <option value="pending" <?php echo $token_filter === 'pending' ? 'selected' : ''; ?>>Pendente</option>
                            <option value="expired" <?php echo $token_filter === 'expired' ? 'selected' : ''; ?>>Expirado</option>
                            <option value="none" <?php echo $token_filter === 'none' ? 'selected' : ''; ?>>Sem Token</option>
                        </select>
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 px-6 py-3 rounded-lg font-semibold">
                            <i class="fas fa-search mr-2"></i>Buscar
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Ações em Massa -->
            <div class="bg-gray-800 rounded-lg p-4 mb-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <label class="flex items-center">
                            <input type="checkbox" id="selectAll" class="mr-2">
                            <span class="text-sm">Selecionar todos</span>
                        </label>
                        <span id="selectedCount" class="text-sm text-gray-400">0 selecionados</span>
                    </div>
                    
                    <div class="flex space-x-2">
                        <form method="POST" class="inline" id="bulkPasswordForm">
                            <input type="hidden" name="action" value="bulk_send_password">
                            <div id="bulkPasswordUsers"></div>
                            <button type="submit" class="bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg text-sm font-semibold" disabled id="bulkPasswordBtn">
                                <i class="fas fa-key mr-1"></i>Enviar Senhas
                            </button>
                        </form>
                        
                        <form method="POST" class="inline" id="bulkWelcomeForm">
                            <input type="hidden" name="action" value="bulk_send_welcome">
                            <div id="bulkWelcomeUsers"></div>
                            <button type="submit" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded-lg text-sm font-semibold" disabled id="bulkWelcomeBtn">
                                <i class="fas fa-heart mr-1"></i>Boas-vindas
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Lista de Usuários -->
            <div class="bg-gray-800 rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-700">
                    <h2 class="text-xl font-bold">Usuários Cadastrados (<?php echo $total_users; ?>)</h2>
                </div>
                
                <?php if (!empty($users)): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">
                                        <input type="checkbox" id="selectAllTable" class="mr-2">
                                        Usuário
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">Tipo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">Token</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">Cadastro</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <?php foreach ($users as $user): ?>
                                    <tr class="hover:bg-gray-700">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <input type="checkbox" class="user-checkbox mr-3" value="<?php echo $user['id']; ?>" data-token="<?php echo $user['token_status']; ?>" data-hotmart="<?php echo $user['hotmart_status']; ?>">
                                                <div class="w-10 h-10 bg-purple-600 rounded-full flex items-center justify-center mr-3">
                                                    <span class="text-white font-bold text-sm">
                                                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                                    </span>
                                                </div>
                                                <div>
                                                    <div class="font-medium"><?php echo htmlspecialchars($user['name']); ?></div>
                                                    <div class="text-sm text-gray-400"><?php echo htmlspecialchars($user['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php
                                            $role_colors = [
                                                'admin' => 'bg-red-600 text-red-100',
                                                'subscriber' => 'bg-green-600 text-green-100',
                                                'free' => 'bg-gray-600 text-gray-100'
                                            ];
                                            $role_names = [
                                                'admin' => 'Admin',
                                                'subscriber' => 'Assinante',
                                                'free' => 'Gratuito'
                                            ];
                                            ?>
                                            <span class="px-2 py-1 rounded text-xs font-semibold <?php echo $role_colors[$user['role']]; ?>">
                                                <?php echo $role_names[$user['role']]; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 rounded text-xs font-semibold <?php echo $user['is_active'] ? 'bg-green-600 text-green-100' : 'bg-red-600 text-red-100'; ?>">
                                                <?php echo $user['is_active'] ? 'Ativo' : 'Inativo'; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php
                                            $token_colors = [
                                                'pending' => 'bg-yellow-600 text-yellow-100',
                                                'expired' => 'bg-red-600 text-red-100',
                                                'none' => 'bg-gray-600 text-gray-100'
                                            ];
                                            $token_names = [
                                                'pending' => 'Pendente',
                                                'expired' => 'Expirado',
                                                'none' => 'Sem Token'
                                            ];
                                            ?>
                                            <span class="px-2 py-1 rounded text-xs font-semibold <?php echo $token_colors[$user['token_status']]; ?>">
                                                <?php echo $token_names[$user['token_status']]; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-400">
                                            <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex space-x-2">
                                                <!-- Editar -->
                                                <button onclick="editUser('<?php echo $user['id']; ?>')" class="text-blue-400 hover:text-blue-300" title="Editar usuário">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <!-- Toggle Status -->
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="action" value="toggle_user_status">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="is_active" value="<?php echo $user['is_active']; ?>">
                                                    <button type="submit" class="text-yellow-400 hover:text-yellow-300" title="<?php echo $user['is_active'] ? 'Desativar' : 'Ativar'; ?> usuário">
                                                        <i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check'; ?>"></i>
                                                    </button>
                                                </form>
                                                
                                                <!-- Email de Senha -->
                                                <?php if ($user['token_status'] === 'pending'): ?>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="action" value="send_password_email">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="text-purple-400 hover:text-purple-300" title="Enviar email de senha">
                                                            <i class="fas fa-key"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <!-- Email de Boas-vindas -->
                                                <?php if ($user['token_status'] === 'pending' && $user['hotmart_status'] === 'ACTIVE'): ?>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="action" value="send_welcome_email">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="text-green-400 hover:text-green-300" title="Enviar email de boas-vindas">
                                                            <i class="fas fa-heart"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <!-- Excluir -->
                                                <?php if ($user['email'] !== 'wrbl.traduz@gmail.com'): ?>
                                                    <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja excluir este usuário?')">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="user_email" value="<?php echo $user['email']; ?>">
                                                        <button type="submit" class="text-red-400 hover:text-red-300" title="Excluir usuário">
                                                            <i class="fas fa-trash"></i>
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
                    
                    <!-- Paginação -->
                    <?php if ($total_pages > 1): ?>
                        <div class="px-6 py-4 border-t border-gray-700">
                            <div class="flex justify-center">
                                <nav class="flex space-x-2">
                                    <?php if ($page > 1): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                           class="bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded-lg">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                           class="<?php echo $i === $page ? 'bg-purple-600' : 'bg-gray-700 hover:bg-gray-600'; ?> px-4 py-2 rounded-lg">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                           class="bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded-lg">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-12">
                        <div class="text-4xl mb-4">👥</div>
                        <p class="text-gray-400">Nenhum usuário encontrado.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal de Edição -->
    <div id="editUserModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-gray-800 rounded-lg p-8 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold mb-6">Editar Usuário</h3>
            
            <form method="POST" id="editUserForm">
                <input type="hidden" name="action" value="update_user_role">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Tipo de Usuário</label>
                        <select name="role" id="edit_role" class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none">
                            <option value="free">Usuário Gratuito</option>
                            <option value="subscriber">Assinante</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    
                    <div id="subscriptionFields" class="space-y-4 hidden">
                        <div>
                            <label class="block text-sm font-medium mb-2">Tipo de Assinatura</label>
                            <select name="subscription_type" id="edit_subscription_type" class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none">
                                <option value="">Nenhuma</option>
                                <option value="monthly">Mensal</option>
                                <option value="quarterly">Trimestral</option>
                                <option value="biannual">Semestral</option>
                                <option value="annual">Anual</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">Data de Expiração</label>
                            <input type="datetime-local" name="subscription_expires" id="edit_subscription_expires" 
                                   class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none">
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 mt-6">
                    <button type="button" onclick="closeEditModal()" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg">
                        Cancelar
                    </button>
                    <button type="submit" class="bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg">
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Dados dos usuários para o modal
        const users = <?php echo json_encode($users); ?>;

        // Modal functions
        function editUser(userId) {
            const user = users.find(u => u.id === userId);
            if (!user) return;
            
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_subscription_type').value = user.subscription_type || '';
            
            if (user.subscription_expires) {
                const date = new Date(user.subscription_expires);
                document.getElementById('edit_subscription_expires').value = date.toISOString().slice(0, 16);
            }
            
            toggleSubscriptionFields();
            document.getElementById('editUserModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editUserModal').classList.add('hidden');
        }

        function toggleSubscriptionFields() {
            const role = document.getElementById('edit_role').value;
            const fields = document.getElementById('subscriptionFields');
            
            if (role === 'subscriber') {
                fields.classList.remove('hidden');
            } else {
                fields.classList.add('hidden');
            }
        }

        document.getElementById('edit_role').addEventListener('change', toggleSubscriptionFields);

        // Bulk selection
        const selectAllCheckbox = document.getElementById('selectAll');
        const selectAllTableCheckbox = document.getElementById('selectAllTable');
        const userCheckboxes = document.querySelectorAll('.user-checkbox');
        const selectedCountSpan = document.getElementById('selectedCount');
        const bulkPasswordBtn = document.getElementById('bulkPasswordBtn');
        const bulkWelcomeBtn = document.getElementById('bulkWelcomeBtn');

        function updateBulkActions() {
            const selectedUsers = Array.from(userCheckboxes).filter(cb => cb.checked);
            const count = selectedUsers.length;
            
            selectedCountSpan.textContent = `${count} selecionados`;
            
            // Update bulk action buttons
            bulkPasswordBtn.disabled = count === 0;
            bulkWelcomeBtn.disabled = count === 0;
            
            // Update hidden inputs for bulk actions
            const bulkPasswordUsers = document.getElementById('bulkPasswordUsers');
            const bulkWelcomeUsers = document.getElementById('bulkWelcomeUsers');
            
            bulkPasswordUsers.innerHTML = '';
            bulkWelcomeUsers.innerHTML = '';
            
            selectedUsers.forEach(cb => {
                const passwordInput = document.createElement('input');
                passwordInput.type = 'hidden';
                passwordInput.name = 'selected_users[]';
                passwordInput.value = cb.value;
                bulkPasswordUsers.appendChild(passwordInput);
                
                const welcomeInput = document.createElement('input');
                welcomeInput.type = 'hidden';
                welcomeInput.name = 'selected_users[]';
                welcomeInput.value = cb.value;
                bulkWelcomeUsers.appendChild(welcomeInput);
            });
        }

        selectAllCheckbox.addEventListener('change', function() {
            userCheckboxes.forEach(cb => cb.checked = this.checked);
            selectAllTableCheckbox.checked = this.checked;
            updateBulkActions();
        });

        selectAllTableCheckbox.addEventListener('change', function() {
            userCheckboxes.forEach(cb => cb.checked = this.checked);
            selectAllCheckbox.checked = this.checked;
            updateBulkActions();
        });

        userCheckboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                const allChecked = Array.from(userCheckboxes).every(checkbox => checkbox.checked);
                const noneChecked = Array.from(userCheckboxes).every(checkbox => !checkbox.checked);
                
                selectAllCheckbox.checked = allChecked;
                selectAllTableCheckbox.checked = allChecked;
                selectAllCheckbox.indeterminate = !allChecked && !noneChecked;
                selectAllTableCheckbox.indeterminate = !allChecked && !noneChecked;
                
                updateBulkActions();
            });
        });

        // Close modal on outside click
        document.getElementById('editUserModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        // Initialize
        updateBulkActions();
    </script>
</body>
</html>