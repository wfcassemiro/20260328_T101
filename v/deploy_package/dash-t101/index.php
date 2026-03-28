<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php'; // Inclui dash_functions.php

// Verificar se o usuário está logado
if (!isLoggedIn()) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$page_title = 'Dashboard - Dash-T101';
$user_id = $_SESSION['user_id'];

// Obter estatísticas do dashboard
$stats = getDashboardStats($user_id);
$recent_projects = getRecentProjects($user_id, 5);
$recent_invoices = getRecentInvoices($user_id, 5);

// Obter configurações do usuário (necessário para a moeda padrão do dashboard, se ainda usar um único)
$user_settings = getUserSettings($user_id);

include __DIR__ . '/../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-white mb-2">Dashboard - Dash-T101</h1>
        <p class="text-gray-400">Bem-vindo ao seu painel de controle de projetos e negócios</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-gray-800 rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-400 text-sm">Total de Clientes</p>
                    <p class="text-2xl font-bold text-white"><?php echo number_format($stats['total_clients']); ?></p>
                </div>
                <div class="bg-blue-600 p-3 rounded-full">
                    <i class="fas fa-users text-white"></i>
                </div>
            </div>
        </div>

        <div class="bg-gray-800 rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-400 text-sm">Projetos Ativos</p>
                    <p class="text-2xl font-bold text-white"><?php echo number_format($stats['active_projects']); ?></p>
                    <p class="text-xs text-gray-500">de <?php echo number_format($stats['total_projects']); ?> total</p>
                </div>
                <div class="bg-green-600 p-3 rounded-full">
                    <i class="fas fa-project-diagram text-white"></i>
                </div>
            </div>
        </div>

        <!-- CARD RECEITA TOTAL POR MOEDA CORRIGIDO -->
        <div class="bg-gray-800 rounded-lg p-6 lg:col-span-2 flex flex-col justify-center">
            <div class="flex items-center justify-between h-full">
                <div>
                    <p class="text-gray-400 text-sm mb-2">Receita Total por Moeda</p>
                </div>
                <div class="flex flex-col items-end flex-1">
                    <?php foreach ($dash_config['currencies'] as $currency_code): ?>
                        <p class="text-xl font-bold text-white text-right">
                            <?php echo formatCurrency($stats['total_revenue_' . $currency_code], $currency_code); ?>
                        </p>
                    <?php endforeach; ?>
                </div>
                <div class="bg-purple-600 p-3 rounded-full self-start ml-4">
                    <i class="fas fa-dollar-sign text-white"></i>
                </div>
            </div>
        </div>
        <!-- FIM DO CARD RECEITA TOTAL POR MOEDA -->

        <div class="bg-gray-800 rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-400 text-sm">Alertas</p>
                    <p class="text-2xl font-bold text-white"><?php echo $stats['upcoming_deadlines'] + $stats['overdue_invoices']; ?></p>
                    <p class="text-xs text-gray-500"><?php echo $stats['upcoming_deadlines']; ?> prazos + <?php echo $stats['overdue_invoices']; ?> vencidas</p>
                </div>
                <div class="bg-red-600 p-3 rounded-full">
                    <i class="fas fa-exclamation-triangle text-white"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <a href="clients.php" class="bg-gray-800 hover:bg-gray-700 rounded-lg p-6 transition-colors">
            <div class="flex items-center">
                <div class="bg-blue-600 p-3 rounded-full mr-4">
                    <i class="fas fa-users text-white"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white">Gerenciar Clientes</h3>
                    <p class="text-gray-400 text-sm">Adicionar e editar informações de clientes</p>
                </div>
            </div>
        </a>

        <a href="projects.php" class="bg-gray-800 hover:bg-gray-700 rounded-lg p-6 transition-colors">
            <div class="flex items-center">
                <div class="bg-green-600 p-3 rounded-full mr-4">
                    <i class="fas fa-project-diagram text-white"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white">Gerenciar Projetos</h3>
                    <p class="text-gray-400 text-sm">Criar e acompanhar projetos de tradução</p>
                </div>
            </div>
        </a>

        <a href="invoices.php" class="bg-gray-800 hover:bg-gray-700 rounded-lg p-6 transition-colors">
            <div class="flex items-center">
                <div class="bg-purple-600 p-3 rounded-full mr-4">
                    <i class="fas fa-file-invoice text-white"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white">Gerenciar Faturas</h3>
                    <p class="text-gray-400 text-sm">Criar faturas e controlar pagamentos</p>
                </div>
            </div>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="bg-gray-800 rounded-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-white">Projetos Recentes</h2>
                <a href="projects.php" class="text-purple-400 hover:text-purple-300 text-sm">Ver todos</a>
            </div>
            
            <?php if (empty($recent_projects)): ?>
                <p class="text-gray-400 text-center py-8">Nenhum projeto encontrado</p>
            <?php else: ?>
                <div class="space-y-4">
                <?php foreach ($recent_projects as $project): ?>
                    <div class="border-l-4 border-purple-600 bg-gray-700 p-4 rounded-r-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-semibold text-white"><?php echo htmlspecialchars($project['project_name']); ?></h3>
                                <p class="text-sm text-gray-400"><?php echo htmlspecialchars($project['company_name']); ?></p>
                                <p class="text-xs text-gray-500">
                                    <?php echo $dash_config['languages'][$project['source_language']] ?? $project['source_language']; ?> → <?php echo $dash_config['languages'][$project['target_language']] ?? $project['target_language']; ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="inline-block px-2 py-1 text-xs rounded-full
                                <?php echo getStatusColor($project['status'], 'project'); ?>">
                                    <?php echo getStatusLabel($project['status'], 'project'); ?>
                                </span>
                                <?php if ($project['deadline']): ?>
                                    <p class="text-xs text-gray-400 mt-1">
                                        Prazo: <?php echo date('d/m/Y', strtotime($project['deadline'])); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-gray-800 rounded-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-white">Faturas Recentes</h2>
                <a href="invoices.php" class="text-purple-400 hover:text-purple-300 text-sm">Ver todas</a>
            </div>
            
            <?php if (empty($recent_invoices)): ?>
                <p class="text-gray-400 text-center py-8">Nenhuma fatura encontrada</p>
            <?php else: ?>
                <div class="space-y-4">
                <?php foreach ($recent_invoices as $invoice): ?>
                    <div class="border-l-4 border-green-600 bg-gray-700 p-4 rounded-r-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-semibold text-white"><?php echo htmlspecialchars($invoice['invoice_number']); ?></h3>
                                <p class="text-sm text-gray-400"><?php echo htmlspecialchars($invoice['company_name']); ?></p>
                                <p class="text-xs text-gray-500">
                                    Emitida em <?php echo date('d/m/Y', strtotime($invoice['invoice_date'])); ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-white"><?php echo formatCurrency($invoice['total_amount'], $invoice['currency']); ?></p>
                                <span class="inline-block px-2 py-1 text-xs rounded-full
                                <?php 
                                switch($invoice['status']) {
                                    case 'paid': echo 'bg-green-600 text-white'; break;
                                    case 'sent': echo 'bg-blue-600 text-white'; break;
                                    case 'overdue': echo 'bg-red-600 text-white'; break;
                                    case 'draft': echo 'bg-gray-600 text-white'; break;
                                    default: echo 'bg-gray-600 text-white';
                                }
                                ?>">
                                    <?php 
                                    $status_labels = [
                                        'draft' => 'Rascunho',
                                        'sent' => 'Enviada',
                                        'paid' => 'Paga',
                                        'overdue' => 'Vencida',
                                        'cancelled' => 'Cancelada'
                                    ];
                                    echo $status_labels[$invoice['status']] ?? $invoice['status'];
                                    ?>
                                </span>
                                <?php if ($invoice['due_date']): ?>
                                    <p class="text-xs text-gray-400 mt-1">
                                        Vence em <?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>