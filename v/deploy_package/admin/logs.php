<?php
session_start();
require_once '../config/database.php';

// Verificar se é admin
if (!isAdmin()) {
    header('Location: /login.php');
    exit;
}

$active_page = 'logs';
$page_title = 'Admin - Logs de Acesso';

// Processar limpeza de logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    $days_to_keep = intval($_POST['days_to_keep']);
    if ($days_to_keep > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM access_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->execute([$days_to_keep]);
            $deleted_count = $stmt->rowCount();
            $success_message = "Logs mais antigos que $days_to_keep dias foram removidos ($deleted_count registros).";
        } catch (Exception $e) {
            $error_message = "Erro ao limpar logs: " . $e->getMessage();
        }
    }
}

// Parâmetros de busca
$search = $_GET['search'] ?? '';
$action_filter = $_GET['action'] ?? '';
$user_filter = $_GET['user'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Construir query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(l.action LIKE ? OR l.resource LIKE ? OR l.ip_address LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($action_filter)) {
    $where_conditions[] = "l.action = ?";
    $params[] = $action_filter;
}

if (!empty($user_filter)) {
    $where_conditions[] = "u.name LIKE ?";
    $params[] = "%$user_filter%";
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(l.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(l.created_at) <= ?";
    $params[] = $date_to;
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Contar total
    $count_sql = "SELECT COUNT(*) FROM access_logs l LEFT JOIN users u ON l.user_id = u.id $where_sql";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_logs = $stmt->fetchColumn();
    
    // Buscar logs
    $sql = "SELECT l.*, u.name as user_name, u.email as user_email, u.role as user_role 
    FROM access_logs l 
    LEFT JOIN users u ON l.user_id = u.id 
    $where_sql 
    ORDER BY l.created_at DESC 
    LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    // Estatísticas
    $stmt = $pdo->query("SELECT COUNT(*) FROM access_logs WHERE DATE(created_at) = CURDATE()");
    $today_logs = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM access_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $week_logs = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM access_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $month_logs = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM access_logs WHERE user_id IS NOT NULL AND DATE(created_at) = CURDATE()");
    $unique_users_today = $stmt->fetchColumn();
    
    // Top ações
    $stmt = $pdo->query("SELECT action, COUNT(*) as count 
    FROM access_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY action 
    ORDER BY count DESC 
    LIMIT 10");
    $top_actions = $stmt->fetchAll();
    
    // Top IPs
    $stmt = $pdo->query("SELECT ip_address, COUNT(*) as count 
    FROM access_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND ip_address IS NOT NULL
    GROUP BY ip_address 
    ORDER BY count DESC 
    LIMIT 10");
    $top_ips = $stmt->fetchAll();
    
    // Ações disponíveis para filtro
    $stmt = $pdo->query("SELECT DISTINCT action FROM access_logs ORDER BY action");
    $available_actions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $total_pages = ceil($total_logs / $per_page);
    
} catch (Exception $e) {
    $logs = [];
    $top_actions = [];
    $top_ips = [];
    $available_actions = [];
    $total_logs = $today_logs = $week_logs = $month_logs = $unique_users_today = 0;
    $total_pages = 0;
}

include '../includes/header.php';
?>

<div class="flex min-h-screen bg-gray-900">
    <!-- Menu lateral -->
    <?php include 'admin_sidebar.php'; ?>

    <!-- Conteúdo principal -->
    <main class="flex-1 p-8 bg-gray-900">
        <div class="min-h-screen px-4 py-8">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-8">
                    <h1 class="text-3xl font-bold">Logs de Acesso</h1>
                    <div class="space-x-4">
                        <button onclick="showClearLogsModal()" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg">
                            <i class="fas fa-trash mr-2"></i>Limpar Logs Antigos
                        </button>
                        <a href="/admin/" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg">
                            ← Voltar ao Dashboard
                        </a>
                    </div>
                </div>
                
                <?php if (isset($success_message)): ?>
                <div class="bg-green-600 bg-opacity-20 border border-green-600 border-opacity-30 rounded-lg p-4 mb-6">
                    <p class="text-green-300"><?php echo htmlspecialchars($success_message); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                <div class="bg-red-600 bg-opacity-20 border border-red-600 border-opacity-30 rounded-lg p-4 mb-6">
                    <p class="text-red-300"><?php echo htmlspecialchars($error_message); ?></p>
                </div>
                <?php endif; ?>
                
                <!-- Estatísticas -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-gray-900 rounded-lg p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-400 text-sm">Acessos Hoje</p>
                                <p class="text-2xl font-bold"><?php echo $today_logs; ?></p>
                            </div>
                            <i class="fas fa-calendar-day text-blue-400 text-2xl"></i>
                        </div>
                    </div>
                    
                    <div class="bg-gray-900 rounded-lg p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-400 text-sm">Últimos 7 Dias</p>
                                <p class="text-2xl font-bold"><?php echo $week_logs; ?></p>
                            </div>
                            <i class="fas fa-calendar-week text-green-400 text-2xl"></i>
                        </div>
                    </div>
                    
                    <div class="bg-gray-900 rounded-lg p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-400 text-sm">Últimos 30 Dias</p>
                                <p class="text-2xl font-bold"><?php echo $month_logs; ?></p>
                            </div>
                            <i class="fas fa-calendar-alt text-purple-400 text-2xl"></i>
                        </div>
                    </div>
                    
                    <div class="bg-gray-900 rounded-lg p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-400 text-sm">Usuários Únicos Hoje</p>
                                <p class="text-2xl font-bold"><?php echo $unique_users_today; ?></p>
                            </div>
                            <i class="fas fa-users text-yellow-400 text-2xl"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Análises -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <!-- Top Ações -->
                    <div class="bg-gray-900 rounded-lg p-6">
                        <h2 class="text-xl font-bold mb-4">Ações Mais Frequentes (7 dias)</h2>
                        <div class="space-y-3">
                            <?php foreach (array_slice($top_actions, 0, 8) as $action): ?>
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium"><?php echo htmlspecialchars($action['action']); ?></span>
                                <div class="flex items-center">
                                    <div class="bg-gray-700 rounded-full h-2 w-20 mr-3">
                                        <div class="bg-purple-600 h-2 rounded-full" style="width: <?php echo min(100, ($action['count'] / ($top_actions[0]['count'] ?? 1)) * 100); ?>%"></div>
                                    </div>
                                    <span class="text-sm text-gray-400"><?php echo $action['count']; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Top IPs -->
                    <div class="bg-gray-900 rounded-lg p-6">
                        <h2 class="text-xl font-bold mb-4">IPs Mais Ativos (24h)</h2>
                        <div class="space-y-3">
                            <?php foreach (array_slice($top_ips, 0, 8) as $ip): ?>
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-mono"><?php echo htmlspecialchars($ip['ip_address']); ?></span>
                                <div class="flex items-center">
                                    <div class="bg-gray-700 rounded-full h-2 w-16 mr-3">
                                        <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo min(100, ($ip['count'] / ($top_ips[0]['count'] ?? 1)) * 100); ?>%"></div>
                                    </div>
                                    <span class="text-sm text-gray-400"><?php echo $ip['count']; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Filtros -->
                <div class="bg-gray-900 rounded-lg p-6 mb-8">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
                        <div>
                            <label for="search" class="block text-sm font-medium mb-2">Busca Geral</label>
                            <input
                                type="text"
                                name="search"
                                id="search"
                                value="<?php echo htmlspecialchars($search); ?>"
                                placeholder="Ação, recurso, IP ou usuário..."
                                class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                            />
                        </div>
                        
                        <div>
                            <label for="action" class="block text-sm font-medium mb-2">Ação</label>
                            <select
                                name="action"
                                id="action"
                                class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                            >
                                <option value="">Todas as ações</option>
                                <?php foreach ($available_actions as $action): ?>
                                <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $action_filter === $action ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($action); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="user" class="block text-sm font-medium mb-2">Usuário</label>
                            <input
                                type="text"
                                name="user"
                                id="user"
                                value="<?php echo htmlspecialchars($user_filter); ?>"
                                placeholder="Nome do usuário..."
                                class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                            />
                        </div>
                        
                        <div>
                            <label for="date_from" class="block text-sm font-medium mb-2">Data Inicial</label>
                            <input
                                type="date"
                                name="date_from"
                                id="date_from"
                                value="<?php echo htmlspecialchars($date_from); ?>"
                                class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                            />
                        </div>
                        
                        <div>
                            <label for="date_to" class="block text-sm font-medium mb-2">Data Final</label>
                            <input
                                type="date"
                                name="date_to"
                                id="date_to"
                                value="<?php echo htmlspecialchars($date_to); ?>"
                                class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                            />
                        </div>
                        
                        <div class="lg:col-span-5 flex justify-end space-x-4">
                            <a href="?" class="bg-gray-600 hover:bg-gray-700 px-6 py-3 rounded-lg">
                                Limpar Filtros
                            </a>
                            <button type="submit" class="bg-purple-600 hover:bg-purple-700 px-6 py-3 rounded-lg font-semibold">
                                <i class="fas fa-search mr-2"></i>Buscar
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Lista de Logs -->
                <div class="bg-gray-900 rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-700">
                        <h2 class="text-xl font-bold">Logs de Acesso (<?php echo $total_logs; ?> registros)</h2>
                    </div>
                    
                    <?php if (!empty($logs)): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-800">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">Data/Hora</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">Usuário</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">Ação</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">Recurso</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">IP</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">User Agent</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <?php foreach ($logs as $log): ?>
                                <tr class="hover:bg-gray-800">
                                    <td class="px-6 py-4 text-sm">
                                        <div><?php echo date('d/m/Y', strtotime($log['created_at'])); ?></div>
                                        <div class="text-gray-400"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($log['user_name']): ?>
                                        <div class="flex items-center">
                                            <div class="w-6 h-6 bg-purple-600 rounded-full flex items-center justify-center mr-2">
                                                <span class="text-white font-bold text-xs">
                                                    <?php echo strtoupper(substr($log['user_name'], 0, 1)); ?>
                                                </span>
                                            </div>
                                            <div>
                                                <div class="font-medium text-sm"><?php echo htmlspecialchars($log['user_name']); ?></div>
                                                <div class="text-xs text-gray-400"><?php echo htmlspecialchars($log['user_email']); ?></div>
                                            </div>
                                        </div>
                                        <?php else: ?>
                                        <span class="text-gray-400 text-sm">Visitante</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php
                                        $action_colors = [
                                            'login' => 'bg-green-600 text-green-100',
                                            'logout' => 'bg-red-600 text-red-100',
                                            'register' => 'bg-blue-600 text-blue-100',
                                            'view_lecture' => 'bg-purple-600 text-purple-100',
                                            'generate_certificate' => 'bg-yellow-600 text-yellow-100',
                                            'contact_form' => 'bg-orange-600 text-orange-100'
                                        ];
                                        $color = $action_colors[$log['action']] ?? 'bg-gray-600 text-gray-100';
                                        ?>
                                        <span class="px-2 py-1 rounded text-xs font-semibold <?php echo $color; ?>">
                                            <?php echo htmlspecialchars($log['action']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($log['resource']): ?>
                                        <div class="text-sm line-clamp-2" title="<?php echo htmlspecialchars($log['resource']); ?>">
                                            <?php echo htmlspecialchars(strlen($log['resource']) > 50 ? substr($log['resource'], 0, 50) . '...' : $log['resource']); ?>
                                        </div>
                                        <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-mono text-sm"><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($log['user_agent']): ?>
                                        <div class="text-xs text-gray-400 line-clamp-2 max-w-xs" title="<?php echo htmlspecialchars($log['user_agent']); ?>">
                                            <?php echo htmlspecialchars(strlen($log['user_agent']) > 60 ? substr($log['user_agent'], 0, 60) . '...' : $log['user_agent']); ?>
                                        </div>
                                        <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                        <?php endif; ?>
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
                        <div class="text-4xl mb-4">📊</div>
                        <p class="text-gray-400">Nenhum log encontrado.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal de Limpeza de Logs -->
<div id="clearLogsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-gray-900 rounded-lg p-8 max-w-md w-full mx-4">
        <h3 class="text-xl font-bold mb-6">Limpar Logs Antigos</h3>
        
        <form method="POST">
            <div class="mb-6">
                <label for="days_to_keep" class="block text-sm font-medium mb-2">
                    Manter logs dos últimos (dias):
                </label>
                <input
                    type="number"
                    name="days_to_keep"
                    id="days_to_keep"
                    min="1"
                    max="365"
                    value="30"
                    class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                    required
                />
                <p class="text-xs text-gray-400 mt-2">
                    Logs mais antigos que este período serão excluídos permanentemente.
                </p>
            </div>
            
            <div class="flex justify-end space-x-4">
                <button type="button" onclick="closeClearLogsModal()" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg">
                    Cancelar
                </button>
                <button type="submit" name="clear_logs" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg" onclick="return confirm('Tem certeza? Esta ação não pode ser desfeita.')">
                    <i class="fas fa-trash mr-2"></i>Limpar Logs
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showClearLogsModal() {
    document.getElementById('clearLogsModal').classList.remove('hidden');
}

function closeClearLogsModal() {
    document.getElementById('clearLogsModal').classList.add('hidden');
}

// Fechar modal clicando fora
document.getElementById('clearLogsModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeClearLogsModal();
    }
});

// Auto-refresh a cada 30 segundos se não houver filtros
<?php if (empty($search) && empty($action_filter) && empty($user_filter) && empty($date_from) && empty($date_to)): ?>
setTimeout(() => {
    if (!document.hidden) {
        window.location.reload();
    }
}, 30000);
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>