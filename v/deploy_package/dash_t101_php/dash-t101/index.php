<?php
session_start();
require_once __DIR__ . 
'/config/database.php'; // Configuração original do translators101
require_once __DIR__ . 
'/config/dash_database.php'; // Configuração do Dash-T101

$page_title = 'Dash-T101 - Dashboard do Freelancer';

// Verificar se o usuário está logado e tem acesso
if (!hasDashAccess()) {
    header('Location: /login.php?redirect=dash-t101');
    exit;
}

// Buscar estatísticas do dashboard
try {
    $pdo = getDashPDO();
    
    // Total de clientes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM dash_clients");
    $totalClients = $stmt->fetch()['total'];
    
    // Total de projetos
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM dash_projects");
    $totalProjects = $stmt->fetch()['total'];
    
    // Projetos em andamento
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM dash_projects WHERE status = 'in_progress'");
    $activeProjects = $stmt->fetch()['total'];
    
    // Faturas pendentes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM dash_invoices WHERE status = 'pending'");
    $pendingInvoices = $stmt->fetch()['total'];
    
    // Receita total (faturas pagas)
    $stmt = $pdo->query("SELECT SUM(total_amount) as total FROM dash_invoices WHERE status = 'paid'");
    $totalRevenue = $stmt->fetch()['total'] ?? 0;
    
    // Projetos recentes
    $stmt = $pdo->query("
        SELECT p.*, c.name as client_name 
        FROM dash_projects p 
        LEFT JOIN dash_clients c ON p.client_id = c.id 
        ORDER BY p.updated_at DESC 
        LIMIT 5
    ");
    $recentProjects = $stmt->fetchAll();
    
    // Faturas vencendo em 7 dias
    $stmt = $pdo->query("
        SELECT i.*, p.title as project_title, c.name as client_name
        FROM dash_invoices i
        LEFT JOIN dash_projects p ON i.project_id = p.id
        LEFT JOIN dash_clients c ON p.client_id = c.id
        WHERE i.status IN ('pending', 'sent') 
        AND i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY i.due_date ASC
    ");
    $upcomingInvoices = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error_message = 'Erro ao carregar dados do dashboard: ' . $e->getMessage();
}

include __DIR__ . 
'/includes/header.php';
?>

<div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-7xl mx-auto px-4">
        <!-- Header do Dashboard -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Dash-T101</h1>
            <p class="text-gray-600">Seu dashboard de gerenciamento freelancer</p>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Cards de Estatísticas -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <i class="fas fa-users text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Clientes</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $totalClients; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 rounded-lg">
                        <i class="fas fa-project-diagram text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Projetos</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $totalProjects; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-yellow-100 rounded-lg">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Em Andamento</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $activeProjects; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-red-100 rounded-lg">
                        <i class="fas fa-file-invoice text-red-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Faturas Pendentes</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $pendingInvoices; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-purple-100 rounded-lg">
                        <i class="fas fa-dollar-sign text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Receita Total</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo formatCurrency($totalRevenue); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Menu de Navegação -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <a href="clients.php" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-lg">
                        <i class="fas fa-users text-blue-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900">Gerenciar Clientes</h3>
                        <p class="text-gray-600">Adicionar, editar e visualizar clientes</p>
                    </div>
                </div>
            </a>

            <a href="projects.php" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-lg">
                        <i class="fas fa-project-diagram text-green-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900">Gerenciar Projetos</h3>
                        <p class="text-gray-600">Controlar projetos e prazos</p>
                    </div>
                </div>
            </a>

            <a href="invoices.php" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-lg">
                        <i class="fas fa-file-invoice text-purple-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900">Gerenciar Faturas</h3>
                        <p class="text-gray-600">Criar e acompanhar faturas</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Seções de Informações -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Projetos Recentes -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Projetos Recentes</h3>
                </div>
                <div class="p-6">
                    <?php if (empty($recentProjects)): ?>
                        <p class="text-gray-500 text-center py-4">Nenhum projeto encontrado</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recentProjects as $project): ?>
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                    <div>
                                        <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($project['title']); ?></h4>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($project['client_name']); ?></p>
                                        <span class="inline-block px-2 py-1 text-xs rounded-full 
                                            <?php 
                                            switch($project['status']) {
                                                case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'in_progress': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'completed': echo 'bg-green-100 text-green-800'; break;
                                                case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                                            }
                                            ?>">
                                            <?php 
                                            switch($project['status']) {
                                                case 'pending': echo 'Pendente'; break;
                                                case 'in_progress': echo 'Em Andamento'; break;
                                                case 'completed': echo 'Concluído'; break;
                                                case 'cancelled': echo 'Cancelado'; break;
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <div class="text-right">
                                        <?php if ($project['total_amount']): ?>
                                            <p class="font-medium text-gray-900"><?php echo formatCurrency($project['total_amount']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($project['deadline']): ?>
                                            <p class="text-sm text-gray-600"><?php echo formatDate($project['deadline']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Faturas Vencendo -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Faturas Vencendo (7 dias)</h3>
                </div>
                <div class="p-6">
                    <?php if (empty($upcomingInvoices)): ?>
                        <p class="text-gray-500 text-center py-4">Nenhuma fatura vencendo</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($upcomingInvoices as $invoice): ?>
                                <div class="flex items-center justify-between p-4 bg-red-50 rounded-lg border border-red-200">
                                    <div>
                                        <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($invoice['invoice_number']); ?></h4>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($invoice['client_name']); ?></p>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($invoice['project_title']); ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-medium text-gray-900"><?php echo formatCurrency($invoice['total_amount']); ?></p>
                                        <p class="text-sm text-red-600">Vence: <?php echo formatDate($invoice['due_date']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

