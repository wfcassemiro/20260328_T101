<?php

// Reforço de sessão segura
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Use apenas se seu site for HTTPS
ini_set('session.use_strict_mode', 1);


session_start();
require_once '../config/database.php';


// Desabilitar exibição de erros em produção
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Verificar se é admin
if (!isAdmin()) {
    header('Location: /login.php');
    exit;
}

$active_page = 'dashboard';
$page_title = 'Admin - Painel de Controle';

// Ajustar timezone para GMT-3 (America/Sao_Paulo)
date_default_timezone_set('America/Sao_Paulo');

// Buscar estatísticas
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $total_users = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM lectures");
    $total_lectures = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM glossary_files WHERE is_active = 1");
    $total_glossaries = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM certificates");
    $total_certificates = $stmt->fetchColumn();

    // Palestras assistidas (certificados emitidos) nos últimos 30 dias
    $stmt = $pdo->query("SELECT COUNT(*) FROM certificates WHERE issued_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $lectures_watched_30d = $stmt->fetchColumn();

    // Últimas atividades
    $stmt = $pdo->query("SELECT * FROM access_logs ORDER BY created_at DESC LIMIT 10");
    $recent_activities = $stmt->fetchAll();

} catch(PDOException $e) {
    $total_users = $total_lectures = $total_glossaries = $total_certificates = $lectures_watched_30d = 0;
    $recent_activities = [];
}

include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="bg-gray-900">
<div class="flex min-h-screen bg-gray-900">
    <!-- Menu lateral -->
    <?php include 'admin_sidebar.php'; ?>

    <!-- Conteúdo principal -->
    <main class="flex-1 p-8 bg-gray-900">
    <div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-center mb-8">
        <h1 class="text-3xl font-bold mb-2 md:mb-0 text-white">Painel Administrativo</h1>
        <div class="text-right">
            <p class="text-gray-400">Bem-vindo, Administrador!</p>
            <p class="text-sm text-purple-400">
                <?php echo date('d/m/Y H:i', time()); ?> (GMT-3)
            </p>
        </div>
    </div>

    <!-- Estatísticas -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
        <div class="bg-gradient-to-r from-blue-700 to-blue-900 rounded-lg p-6 text-white shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-200 text-sm">Total de Usuários</p>
                    <p class="text-3xl font-bold"><?php echo htmlspecialchars($total_users); ?></p>
                </div>
                <div class="text-4xl opacity-80">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
        <div class="bg-gradient-to-r from-green-700 to-green-900 rounded-lg p-6 text-white shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-200 text-sm">Total de Palestras</p>
                    <p class="text-3xl font-bold"><?php echo htmlspecialchars($total_lectures); ?></p>
                </div>
                <div class="text-4xl opacity-80">
                    <i class="fas fa-video"></i>
                </div>
            </div>
        </div>
        <div class="bg-gradient-to-r from-purple-700 to-purple-900 rounded-lg p-6 text-white shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-200 text-sm">Glossários Ativos</p>
                    <p class="text-3xl font-bold"><?php echo htmlspecialchars($total_glossaries); ?></p>
                </div>
                <div class="text-4xl opacity-80">
                    <i class="fas fa-book"></i>
                </div>
            </div>
        </div>
        <div class="bg-gradient-to-r from-yellow-700 to-yellow-900 rounded-lg p-6 text-white shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-yellow-200 text-sm">Certificados Emitidos</p>
                    <p class="text-3xl font-bold"><?php echo htmlspecialchars($total_certificates); ?></p>
                </div>
                <div class="text-4xl opacity-80">
                    <i class="fas fa-certificate"></i>
                </div>
            </div>
        </div>
        <div class="bg-gradient-to-r from-pink-700 to-pink-900 rounded-lg p-6 text-white shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-pink-200 text-sm">Palestras Assistidas <br> (últimos 30 dias)</p>
                    <p class="text-3xl font-bold"><?php echo htmlspecialchars($lectures_watched_30d); ?></p>
                </div>
                <div class="text-4xl opacity-80">
                    <i class="fas fa-play-circle"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Grade de Funcionalidades -->
    <div class="mb-10">
        <h2 class="text-2xl font-bold mb-4 text-purple-300">Funcionalidades do Painel</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <a href="/admin/palestras.php" class="block bg-gray-900 hover:bg-gray-800 rounded-lg p-6 transition-colors shadow focus:outline-none focus:ring-2 focus:ring-purple-500">
                <div class="flex items-center mb-4">
                    <div class="bg-blue-600 rounded-full p-3 mr-4">
                        <i class="fas fa-video text-white"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-white">Gerenciar Palestras</h3>
                </div>
                <p class="text-gray-400">Adicione, edite e organize palestras do catálogo, além de acompanhar estatísticas de visualização.</p>
            </a>
            <a href="/admin/glossarios.php" class="block bg-gray-900 hover:bg-gray-800 rounded-lg p-6 transition-colors shadow focus:outline-none focus:ring-2 focus:ring-purple-500">
                <div class="flex items-center mb-4">
                    <div class="bg-purple-600 rounded-full p-3 mr-4">
                        <i class="fas fa-book text-white"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-white">Gerenciar Glossários</h3>
                </div>
                <p class="text-gray-400">Faça upload, organize e mantenha arquivos de glossários disponíveis para os usuários.</p>
            </a>
            <a href="/admin/usuarios.php" class="block bg-gray-900 hover:bg-gray-800 rounded-lg p-6 transition-colors shadow focus:outline-none focus:ring-2 focus:ring-green-500">
                <div class="flex items-center mb-4">
                    <div class="bg-green-600 rounded-full p-3 mr-4">
                        <i class="fas fa-users text-white"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-white">Gerenciar Usuários</h3>
                </div>
                <p class="text-gray-400">Visualize, edite e gerencie contas de usuários, permissões e acessos.</p>
            </a>
            <a href="/admin/certificados.php" class="block bg-gray-900 hover:bg-gray-800 rounded-lg p-6 transition-colors shadow focus:outline-none focus:ring-2 focus:ring-yellow-500">
                <div class="flex items-center mb-4">
                    <div class="bg-yellow-600 rounded-full p-3 mr-4">
                        <i class="fas fa-certificate text-white"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-white">Certificados</h3>
                </div>
                <p class="text-gray-400">Acompanhe e gerencie certificados emitidos para os participantes das palestras.</p>
            </a>
            <a href="/admin/gerenciar_senhas.php" class="block bg-gray-900 hover:bg-gray-800 rounded-lg p-6 transition-colors shadow focus:outline-none focus:ring-2 focus:ring-purple-500">
                <div class="flex items-center mb-4">
                    <div class="bg-purple-600 rounded-full p-3 mr-4">
                        <i class="fas fa-key text-white"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-white">Gerenciar Senhas</h3>
                </div>
                <p class="text-gray-400">Gere links de redefinição de senha e auxilie usuários com problemas de acesso.</p>
            </a>
            <a href="/admin/emails.php" class="block bg-gray-900 hover:bg-gray-800 rounded-lg p-6 transition-colors shadow focus:outline-none focus:ring-2 focus:ring-red-500">
                <div class="flex items-center mb-4">
                    <div class="bg-red-600 rounded-full p-3 mr-4">
                        <i class="fas fa-envelope text-white"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-white">Sistema de Emails</h3>
                </div>
                <p class="text-gray-400">Envie comunicados, gerencie templates e acompanhe o histórico de emails enviados.</p>
            </a>
            <a href="/admin/importar_usuarios.php" class="block bg-gray-900 hover:bg-gray-800 rounded-lg p-6 transition-colors shadow focus:outline-none focus:ring-2 focus:ring-purple-500">
                <div class="flex items-center mb-4">
                    <div class="bg-purple-600 rounded-full p-3 mr-4">
                        <i class="fas fa-upload text-white"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-white">Importar Usuários</h3>
                </div>
                <p class="text-gray-400">Realize importação em massa de usuários via CSV ou adicione individualmente.</p>
            </a>
            <a href="/admin/logs.php" class="block bg-gray-900 hover:bg-gray-800 rounded-lg p-6 transition-colors shadow focus:outline-none focus:ring-2 focus:ring-red-500">
                <div class="flex items-center mb-4">
                    <div class="bg-red-600 rounded-full p-3 mr-4">
                        <i class="fas fa-list text-white"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-white">Logs de Acesso</h3>
                </div>
                <p class="text-gray-400">Monitore atividades dos usuários, acessos e ações realizadas no sistema.</p>
            </a>
            <a href="/admin/hotmart.php" class="block bg-gray-900 hover:bg-gray-800 rounded-lg p-6 transition-colors shadow focus:outline-none focus:ring-2 focus:ring-orange-500">
                <div class="flex items-center mb-4">
                    <div class="bg-orange-600 rounded-full p-3 mr-4">
                        <i class="fas fa-credit-card text-white"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-white">Integração Hotmart</h3>
                </div>
                <p class="text-gray-400">Configure webhooks, sincronize vendas e acompanhe integrações com a Hotmart.</p>
            </a>
        </div>
    </div>

    <!-- Atividades Recentes -->
    <div class="bg-gray-900 rounded-lg p-6 shadow">
        <h2 class="text-xl font-bold mb-6 text-purple-300">Atividades Recentes</h2>
        <?php if (!empty($recent_activities)): ?>
            <div class="space-y-3">
                <?php foreach($recent_activities as $activity): ?>
                    <div class="flex items-center justify-between py-3 border-b border-gray-700 last:border-b-0">
                        <div class="flex items-center">
                            <div class="w-2 h-2 bg-purple-400 rounded-full mr-3"></div>
                            <div>
                                <p class="font-medium text-white"><?php echo htmlspecialchars($activity['action']); ?></p>
                                <?php if ($activity['resource']): ?>
                                    <p class="text-sm text-gray-400"><?php echo htmlspecialchars($activity['resource']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-sm text-gray-500">
                            <?php echo date('d/m H:i', strtotime($activity['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-gray-400 text-center py-8">Nenhuma atividade recente.</p>
        <?php endif; ?>
    </div>
    </div>
    </main>
</div>
</body>
</html>

<?php include '../includes/footer.php'; ?>