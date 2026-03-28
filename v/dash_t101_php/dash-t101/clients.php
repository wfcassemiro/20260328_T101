<?php
session_start();
require_once __DIR__ . 
'/config/database.php';
require_once __DIR__ . 
'/config/dash_database.php';

$page_title = 'Gerenciar Clientes - Dash-T101';

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
                    $stmt = $pdo->prepare("
                        INSERT INTO dash_clients (name, email, phone, company, address, notes) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['email'] ?: null,
                        $_POST['phone'] ?: null,
                        $_POST['company'] ?: null,
                        $_POST['address'] ?: null,
                        $_POST['notes'] ?: null
                    ]);
                    $message = 'Cliente criado com sucesso!';
                } catch (PDOException $e) {
                    $error = 'Erro ao criar cliente: ' . $e->getMessage();
                }
                break;
                
            case 'update':
                try {
                    $stmt = $pdo->prepare("
                        UPDATE dash_clients 
                        SET name = ?, email = ?, phone = ?, company = ?, address = ?, notes = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $_POST['name'],
                        $_POST['email'] ?: null,
                        $_POST['phone'] ?: null,
                        $_POST['company'] ?: null,
                        $_POST['address'] ?: null,
                        $_POST['notes'] ?: null,
                        $_POST['client_id']
                    ]);
                    $message = 'Cliente atualizado com sucesso!';
                } catch (PDOException $e) {
                    $error = 'Erro ao atualizar cliente: ' . $e->getMessage();
                }
                break;
                
            case 'delete':
                try {
                    // Verificar se tem projetos
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM dash_projects WHERE client_id = ?");
                    $stmt->execute([$_POST['client_id']]);
                    $projectCount = $stmt->fetch()['count'];
                    
                    if ($projectCount > 0) {
                        $error = 'Não é possível deletar cliente com projetos associados.';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM dash_clients WHERE id = ?");
                        $stmt->execute([$_POST['client_id']]);
                        $message = 'Cliente deletado com sucesso!';
                    }
                } catch (PDOException $e) {
                    $error = 'Erro ao deletar cliente: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Buscar clientes
try {
    $search = $_GET['search'] ?? '';
    $sql = "
        SELECT c.*, 
               COUNT(p.id) as project_count,
               SUM(CASE WHEN p.status = 'completed' THEN p.total_amount ELSE 0 END) as total_revenue
        FROM dash_clients c
        LEFT JOIN dash_projects p ON c.id = p.client_id
    ";
    
    if ($search) {
        $sql .= " WHERE c.name LIKE ? OR c.email LIKE ? OR c.company LIKE ?";
    }
    
    $sql .= " GROUP BY c.id ORDER BY c.name";
    
    $stmt = $pdo->prepare($sql);
    
    if ($search) {
        $searchParam = "%$search%";
        $stmt->execute([$searchParam, $searchParam, $searchParam]);
    } else {
        $stmt->execute();
    }
    
    $clients = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Erro ao buscar clientes: ' . $e->getMessage();
    $clients = [];
}

include __DIR__ . '/includes/header.php';
?>

<div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-7xl mx-auto px-4">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Gerenciar Clientes</h1>
                <p class="text-gray-600">Gerencie sua base de clientes</p>
            </div>
            <div class="flex space-x-4">
                <a href="index.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar ao Dashboard
                </a>
                <button onclick="openModal('createModal')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-plus mr-2"></i>Novo Cliente
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

        <!-- Busca -->
        <div class="bg-white rounded-lg shadow mb-6 p-6">
            <form method="GET" class="flex gap-4">
                <div class="flex-1">
                    <input 
                        type="text" 
                        name="search" 
                        value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Buscar por nome, email ou empresa..."
                        class="w-full p-3 border border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none"
                    />
                </div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg">
                    <i class="fas fa-search"></i>
                </button>
                <?php if ($search): ?>
                    <a href="clients.php" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Lista de Clientes -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    Clientes (<?php echo count($clients); ?>)
                </h3>
            </div>
            
            <?php if (empty($clients)): ?>
                <div class="p-6 text-center">
                    <i class="fas fa-users text-gray-400 text-4xl mb-4"></i>
                    <p class="text-gray-500">
                        <?php echo $search ? 'Nenhum cliente encontrado para a busca.' : 'Nenhum cliente cadastrado ainda.'; ?>
                    </p>
                    <?php if (!$search): ?>
                        <button onclick="openModal('createModal')" class="mt-4 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                            Cadastrar Primeiro Cliente
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contato</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Projetos</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Receita</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($clients as $client): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($client['name']); ?>
                                            </div>
                                            <?php if ($client['company']): ?>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($client['company']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            <?php if ($client['email']): ?>
                                                <div><i class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($client['email']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($client['phone']): ?>
                                                <div><i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($client['phone']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <?php echo $client['project_count']; ?> projeto(s)
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php echo formatCurrency($client['total_revenue'] ?? 0); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button 
                                                onclick="editClient(<?php echo htmlspecialchars(json_encode($client)); ?>)"
                                                class="text-blue-600 hover:text-blue-900"
                                            >
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="projects.php?client_id=<?php echo $client['id']; ?>" class="text-green-600 hover:text-green-900">
                                                <i class="fas fa-project-diagram"></i>
                                            </a>
                                            <?php if ($client['project_count'] == 0): ?>
                                                <button 
                                                    onclick="deleteClient(<?php echo $client['id']; ?>, '<?php echo htmlspecialchars($client['name']); ?>')"
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

<!-- Modal Criar Cliente -->
<div id="createModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-md w-full">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Novo Cliente</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nome *</label>
                            <input type="text" name="name" required class="w-full p-3 border border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="email" class="w-full p-3 border border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
                            <input type="text" name="phone" class="w-full p-3 border border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Empresa</label>
                            <input type="text" name="company" class="w-full p-3 border border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Endereço</label>
                            <textarea name="address" rows="2" class="w-full p-3 border border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                            <textarea name="notes" rows="3" class="w-full p-3 border border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none"></textarea>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4 mt-6">
                        <button type="button" onclick="closeModal('createModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                            Criar Cliente
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Cliente -->
<div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-md w-full">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Editar Cliente</h3>
                <form method="POST" id="editForm">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="client_id" id="edit_client_id">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nome *</label>
                            <input type="text" name="name" id="edit_name" required class="w-full p-3 border border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="email" id="edit_email" class="w-full p-3 border border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
                            <input type="text" name="phone" id="edit_phone" class="w-full p-3 border border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Empresa</label>
                            <input type="text" name="company" id="edit_company" class="w-full p-3 border border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Endereço</label>
                            <textarea name="address" id="edit_address" rows="2" class="w-full p-3 border border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                            <textarea name="notes" id="edit_notes" rows="3" class="w-full p-3 border border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none"></textarea>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4 mt-6">
                        <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
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
    <input type="hidden" name="client_id" id="delete_client_id">
</form>

<script>
function openModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function editClient(client) {
    document.getElementById('edit_client_id').value = client.id;
    document.getElementById('edit_name').value = client.name || '';
    document.getElementById('edit_email').value = client.email || '';
    document.getElementById('edit_phone').value = client.phone || '';
    document.getElementById('edit_company').value = client.company || '';
    document.getElementById('edit_address').value = client.address || '';
    document.getElementById('edit_notes').value = client.notes || '';
    openModal('editModal');
}

function deleteClient(clientId, clientName) {
    if (confirm(`Tem certeza que deseja deletar o cliente "${clientName}"?`)) {
        document.getElementById('delete_client_id').value = clientId;
        document.getElementById('deleteForm').submit();
    }
}

// Fechar modal ao clicar fora
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('bg-gray-600')) {
        e.target.classList.add('hidden');
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

