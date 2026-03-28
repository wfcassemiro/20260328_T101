<?php
session_start();
require_once __DIR__ . 
'/config/database.php';
require_once __DIR__ . 
'/config/dash_database.php';

$page_title = 'Gerenciar Projetos - Dash-T101';

// Verificar acesso
if (!hasDashAccess()) {
    header('Location: /login.php?redirect=dash-t101');
    exit;
}

$pdo = getDashPDO();
$message = '';
$error = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                try {
                    $total_amount = ($_POST['word_count'] * $_POST['rate_per_word']) + ($_POST['additional_cost'] ?: 0);
                    $stmt = $pdo->prepare("
                        INSERT INTO dash_projects (client_id, title, description, status, start_date, deadline, 
                                                 word_count, rate_per_word, additional_cost, total_amount, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['client_id'],
                        $_POST['title'],
                        $_POST['description'] ?: null,
                        $_POST['status'],
                        $_POST['start_date'],
                        $_POST['deadline'] ?: null,
                        $_POST['word_count'] ?: null,
                        $_POST['rate_per_word'] ?: null,
                        $_POST['additional_cost'] ?: null,
                        $total_amount,
                        $_POST['notes'] ?: null
                    ]);
                    $message = 'Projeto criado com sucesso!';
                } catch (PDOException $e) {
                    $error = 'Erro ao criar projeto: ' . $e->getMessage();
                }
                break;
                
            case 'update':
                try {
                    $total_amount = ($_POST['word_count'] * $_POST['rate_per_word']) + ($_POST['additional_cost'] ?: 0);
                    $stmt = $pdo->prepare("
                        UPDATE dash_projects 
                        SET client_id = ?, title = ?, description = ?, status = ?, start_date = ?, deadline = ?, 
                            word_count = ?, rate_per_word = ?, additional_cost = ?, total_amount = ?, notes = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $_POST['client_id'],
                        $_POST['title'],
                        $_POST['description'] ?: null,
                        $_POST['status'],
                        $_POST['start_date'],
                        $_POST['deadline'] ?: null,
                        $_POST['word_count'] ?: null,
                        $_POST['rate_per_word'] ?: null,
                        $_POST['additional_cost'] ?: null,
                        $total_amount,
                        $_POST['notes'] ?: null,
                        $_POST['project_id']
                    ]);
                    $message = 'Projeto atualizado com sucesso!';
                } catch (PDOException $e) {
                    $error = 'Erro ao atualizar projeto: ' . $e->getMessage();
                }
                break;
                
            case 'delete':
                try {
                    // Verificar se tem faturas
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM dash_invoices WHERE project_id = ?");
                    $stmt->execute([$_POST['project_id']]);
                    $invoiceCount = $stmt->fetch()['count'];
                    
                    if ($invoiceCount > 0) {
                        $error = 'Não é possível deletar projeto com faturas associadas.';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM dash_projects WHERE id = ?");
                        $stmt->execute([$_POST['project_id']]);
                        $message = 'Projeto deletado com sucesso!';
                    }
                } catch (PDOException $e) {
                    $error = 'Erro ao deletar projeto: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Buscar projetos
try {
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $client_filter = $_GET['client_id'] ?? '';
    
    $sql = "
        SELECT p.*, c.name as client_name
        FROM dash_projects p
        LEFT JOIN dash_clients c ON p.client_id = c.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($search) {
        $sql .= " AND (p.title LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    }
    
    if ($status_filter) {
        $sql .= " AND p.status = ?";
        $params[] = $status_filter;
    }
    
    if ($client_filter) {
        $sql .= " AND p.client_id = ?";
        $params[] = $client_filter;
    }
    
    $sql .= " ORDER BY p.start_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $projects = $stmt->fetchAll();
    
    // Buscar clientes para o dropdown
    $stmt = $pdo->query("SELECT id, name FROM dash_clients ORDER BY name");
    $clients = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Erro ao buscar projetos: ' . $e->getMessage();
    $projects = [];
    $clients = [];
}

include __DIR__ . '/includes/header.php';
?>

<div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-7xl mx-auto px-4">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Gerenciar Projetos</h1>
                <p class="text-gray-600">Acompanhe seus projetos e prazos</p>
            </div>
            <div class="flex space-x-4">
                <a href="index.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar ao Dashboard
                </a>
                <button onclick="openModal('createModal')" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-plus mr-2"></i>Novo Projeto
                </button>
            </div>
        </div>

        <!-- Mensagens -->
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="bg-white rounded-lg shadow mb-6 p-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <input 
                        type="text" 
                        name="search" 
                        value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Buscar projetos..."
                        class="w-full p-3 border border-gray-300 rounded-lg focus:border-green-500 focus:outline-none"
                    />
                </div>
                
                <div>
                    <select name="status" class="w-full p-3 border border-gray-300 rounded-lg focus:border-green-500 focus:outline-none">
                        <option value="">Todos os Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pendente</option>
                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>Em Andamento</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Concluído</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                </div>
                
                <div>
                    <select name="client_id" class="w-full p-3 border border-gray-300 rounded-lg focus:border-green-500 focus:outline-none">
                        <option value="">Todos os Clientes</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>" <?php echo $client_filter == $client['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($client['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex space-x-2">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg flex-1">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if ($search || $status_filter || $client_filter): ?>
                        <a href="projects.php" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Lista de Projetos -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    Projetos (<?php echo count($projects); ?>)
                </h3>
            </div>
            
            <?php if (empty($projects)): ?>
                <div class="p-6 text-center">
                    <i class="fas fa-project-diagram text-gray-400 text-4xl mb-4"></i>
                    <p class="text-gray-500">
                        <?php echo ($search || $status_filter || $client_filter) ? 'Nenhum projeto encontrado para os filtros aplicados.' : 'Nenhum projeto cadastrado ainda.'; ?>
                    </p>
                    <?php if (!($search || $status_filter || $client_filter)): ?>
                        <button onclick="openModal('createModal')" class="mt-4 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                            Criar Primeiro Projeto
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Título</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Início</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prazo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Total</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($projects as $project): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($project['title']); ?>
                                        </div>
                                        <?php if ($project['description']): ?>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars(substr($project['description'], 0, 50)); ?>...
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php echo htmlspecialchars($project['client_name']); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
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
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php echo formatDate($project['start_date']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php echo $project['deadline'] ? formatDate($project['deadline']) : 'N/A'; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php echo formatCurrency($project['total_amount']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button 
                                                onclick="editProject(<?php echo htmlspecialchars(json_encode($project)); ?>)"
                                                class="text-green-600 hover:text-green-900"
                                            >
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="invoices.php?project_id=<?php echo $project['id']; ?>" class="text-purple-600 hover:text-purple-900">
                                                <i class="fas fa-file-invoice"></i>
                                            </a>
                                            <?php 
                                                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM dash_invoices WHERE project_id = ?");
                                                $stmt->execute([$project['id']]);
                                                $invoiceCount = $stmt->fetch()['count'];
                                            ?>
                                            <?php if ($invoiceCount == 0): ?>
                                                <button 
                                                    onclick="deleteProject(<?php echo $project['id']; ?>, '<?php echo htmlspecialchars($project['title']); ?>')"
                                                    class="text-red-600 hover:text-red-900"
                                                >
                                                    <i class="fas fa-trash"></i>
                                                </button>
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
</div>

<!-- Modal Criar Projeto -->
<div id="createModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-md w-full">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Novo Projeto</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cliente *</label>
                            <select name="client_id" required class="w-full p-3 border border-gray-300 rounded-lg focus:border-green-500 focus:outline-none">
                                <option value="">Selecione um cliente</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['id']; ?>">
                                        <?php echo htmlspecialchars($client['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Título *</label>
                            <input type="text" name="title" required class="w-full p-3 border border-gray-300 rounded-lg focus:border-green-500 focus:outline-none">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                            <textarea name="description" rows="3" class="w-full p-3 border border-gray-300 rounded-lg focus:border-green-500 focus:outline-none"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                            <select name="status" required class="w-full p-3 border border-gray-300 rounded-lg focus:border-green-500 focus:outline-none">
                                <option value="pending">Pendente</option>
                                <option value="in_progress">Em Andamento</option>
                                <option value="completed">Concluído</option>
                                <option value="cancelled">Cancelado</option>
                            </select>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Data de Início *</label>
                                <input type="date" name="start_date" required class="w-full p-3 border border-gray-300 rounded-lg focus:border-green-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Prazo</label>
                                <input type="date" name="deadline" class="w-full p-3 border border-gray-300 rounded-lg focus:border-green-500 focus:outline-none">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Contagem de Palavras</label>
                                <input type="number" name="word_count" min="0" class="w-full p-3 border border-gray-300 rounded-lg focus:border-green-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Taxa por Palavra (R$)</label>
                                <input type="number" name="rate_per_word" step="0.01" min="0" class="w-full p-3 border border-gray-300 rounded-lg focus:border-green-500 focus:outline-none">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Custo Adicional (R$)</label>
                            <input type="number" name="additional_cost" step="0.01" min="0" value="0" class="w-full p-3 border border-gray-300 rounded-lg focus:border-green-500 focus:outline-none">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                            <textarea name="notes" rows="3" class="w-full p-3 border border-gray-300 rounded-lg focus:border-green-500 focus:outline-none"></textarea>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4 mt-6">
                        <button type="button" onclick="closeModal('createModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                            Criar Projeto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Projeto -->
<div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-md w-full">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Editar Projeto</h3>
                <form method="POST" id="editForm">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="project_id" id="edit_project_id">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cliente *</label>
                            <select name="client_id" id="edit_client_id" required class="w-full p-3 border border-gray-300 rounded-lg focus:border-green-500 focus:outline-none">
                                <option value="">Selecione um cliente</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['id']; ?>">
                                        <?php echo htmlspecialchars($client['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Título *</label>
                            <input type="text" name="title" id="edit_title" required class="w-full p-3 border border-gray-300 rounded-lg focus:border-green-500 focus:outline-none">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                            <textarea name="description" id="edit_description" rows="3" class="w-full p-3 border border-gray-300 rounded-lg focus:border-green-500 focus:outline-none"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                            <select name="status" id="edit_status" required class="w-full p-3 border border-gray-300 rounded-lg focus:border-green-500 focus:outline-none">
                                <option value="pending">Pendente</option>
                                <option value="in_progress">Em Andamento</option>
                                <option value="completed">Concluído</option>
                                <option value="cancelled">Cancelado</option>
                            </select>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Data de Início *</label>
                                <input type="date" name="start_date" id="edit_start_date" required class="w-full p-3 border border-gray-300 rounded-lg focus:border-green-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Prazo</label>
                                <input type="date" name="deadline" id="edit_deadline" class="w-full p-3 border border-gray-300 rounded-lg focus:border-green-500 focus:outline-none">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Contagem de Palavras</label>
                                <input type="number" name="word_count" id="edit_word_count" min="0" class="w-full p-3 border border-gray-300 rounded-lg focus:border-green-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Taxa por Palavra (R$)</label>
                                <input type="number" name="rate_per_word" id="edit_rate_per_word" step="0.01" min="0" class="w-full p-3 border border-gray-300 rounded-lg focus:border-green-500 focus:outline-none">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Custo Adicional (R$)</label>
                            <input type="number" name="additional_cost" id="edit_additional_cost" step="0.01" min="0" class="w-full p-3 border border-gray-300 rounded-lg focus:border-green-500 focus:outline-none">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                            <textarea name="notes" id="edit_notes" rows="3" class="w-full p-3 border border-gray-300 rounded-lg focus:border-green-500 focus:outline-none"></textarea>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4 mt-6">
                        <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                            Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Form para deletar -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="project_id" id="delete_project_id">
</form>

<script>
function openModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function editProject(project) {
    document.getElementById('edit_project_id').value = project.id;
    document.getElementById('edit_client_id').value = project.client_id;
    document.getElementById('edit_title').value = project.title || '';
    document.getElementById('edit_description').value = project.description || '';
    document.getElementById('edit_status').value = project.status;
    document.getElementById('edit_start_date').value = project.start_date;
    document.getElementById('edit_deadline').value = project.deadline || '';
    document.getElementById('edit_word_count').value = project.word_count || '';
    document.getElementById('edit_rate_per_word').value = project.rate_per_word || '';
    document.getElementById('edit_additional_cost').value = project.additional_cost || '';
    document.getElementById('edit_notes').value = project.notes || '';
    openModal('editModal');
}

function deleteProject(projectId, projectTitle) {
    if (confirm(`Tem certeza que deseja deletar o projeto "${projectTitle}"?`)) {
        document.getElementById('delete_project_id').value = projectId;
        document.getElementById('deleteForm').submit();
    }
}

// Fechar modal ao clicar fora
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('bg-gray-600')) {
            e.target.classList.add('hidden');
        }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

