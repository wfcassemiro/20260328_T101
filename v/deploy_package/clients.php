<?php
session_start();
require_once 'config/database.php';
require_once 'config/dash_database.php';

// Page settings
$page_title = 'Clientes';
$page_description = 'Adicione e gerencie seus clientes.';
$active_page = 'clients';
$hide_top_menu = true;

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_client':
                try {
                    $stmt = $pdo->prepare("INSERT INTO dash_clients (user_id, company_name, contact_name, contact_email, contact_phone, address, country, city, postal_code, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $result = $stmt->execute([
                        $user_id,
                        $_POST['company_name'],
                        $_POST['contact_name'],
                        $_POST['contact_email'],
                        $_POST['contact_phone'] ?? '',
                        $_POST['address'] ?? '',
                        $_POST['country'] ?? '',
                        $_POST['city'] ?? '',
                        $_POST['postal_code'] ?? '',
                        $_POST['notes'] ?? ''
                    ]);
                    
                    if ($result) {
                        $message = 'Cliente adicionado com sucesso!';
                    } else {
                        $error = 'Erro ao adicionar cliente.';
                    }
                } catch (PDOException $e) {
                    $error = 'Erro: ' . $e->getMessage();
                }
                break;
                
            case 'edit_client':
                try {
                    $stmt = $pdo->prepare("UPDATE dash_clients SET company_name = ?, contact_name = ?, contact_email = ?, contact_phone = ?, address = ?, country = ?, city = ?, postal_code = ?, notes = ? WHERE id = ? AND user_id = ?");
                    $result = $stmt->execute([
                        $_POST['company_name'],
                        $_POST['contact_name'],
                        $_POST['contact_email'],
                        $_POST['contact_phone'] ?? '',
                        $_POST['address'] ?? '',
                        $_POST['country'] ?? '',
                        $_POST['city'] ?? '',
                        $_POST['postal_code'] ?? '',
                        $_POST['notes'] ?? '',
                        $_POST['client_id'],
                        $user_id
                    ]);
                    
                    if ($result) {
                        $message = 'Cliente atualizado com sucesso!';
                    } else {
                        $error = 'Erro ao atualizar cliente.';
                    }
                } catch (PDOException $e) {
                    $error = 'Erro: ' . $e->getMessage();
                }
                break;
                
            case 'delete_client':
                try {
                    // Verificar se o cliente tem projetos associados
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dash_projects WHERE client_id = ? AND user_id = ?");
                    $stmt->execute([$_POST['client_id'], $user_id]);
                    $project_count = $stmt->fetchColumn();
                    
                    if ($project_count > 0) {
                        $error = 'Não é possível excluir este cliente pois ele possui projetos associados.';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM dash_clients WHERE id = ? AND user_id = ?");
                        $result = $stmt->execute([$_POST['client_id'], $user_id]);
                        
                        if ($result) {
                            $message = 'Cliente excluído com sucesso!';
                        } else {
                            $error = 'Erro ao excluir cliente.';
                        }
                    }
                } catch (PDOException $e) {
                    $error = 'Erro: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Obter lista de clientes
$search = $_GET['search'] ?? '';
$where_clause = "WHERE user_id = ?";
$params = [$user_id];

if ($search) {
    $where_clause .= " AND (company_name LIKE ? OR contact_name LIKE ? OR contact_email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$stmt = $pdo->prepare("SELECT * FROM dash_clients $where_clause ORDER BY company_name ASC");
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter cliente para edição se solicitado
$edit_client = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM dash_clients WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['edit'], $user_id]);
    $edit_client = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<?php include 'includes/head.php'; ?>
<body class="bg-gray-950 text-white font-inter">
<div class="flex min-h-screen">

    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 px-4 py-8 bg-gray-950">
        <div class="max-w-7xl mx-auto">
            <h1 class="text-4xl font-bold mb-2">Gerenciar Clientes</h1>
            <p class="text-gray-400 mb-8">Adicione e gerencie informações dos seus clientes.</p>

    <!-- Mensagens -->
    <?php if ($message): ?>
        <div class="bg-green-600 text-white p-4 rounded-lg mb-6">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-600 text-white p-4 rounded-lg mb-6">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Formulário de Adicionar/Editar Cliente -->
    <div class="bg-gray-900 rounded-lg p-6 mb-8">
        <h2 class="text-xl font-semibold text-white mb-4">
            <?php echo $edit_client ? 'Editar Cliente' : 'Adicionar Novo Cliente'; ?>
        </h2>
        
        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <input type="hidden" name="action" value="<?php echo $edit_client ? 'edit_client' : 'add_client'; ?>">
            <?php if ($edit_client): ?>
                <input type="hidden" name="client_id" value="<?php echo $edit_client['id']; ?>">
            <?php endif; ?>
            
            <div>
                <label for="company_name" class="block text-sm font-medium text-gray-300 mb-2">Nome da Empresa *</label>
                <input type="text" name="company_name" id="company_name" required
                       value="<?php echo htmlspecialchars($edit_client['company_name'] ?? ''); ?>"
                       class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none text-white">
            </div>
            
            <div>
                <label for="contact_name" class="block text-sm font-medium text-gray-300 mb-2">Nome do Contato *</label>
                <input type="text" name="contact_name" id="contact_name" required
                       value="<?php echo htmlspecialchars($edit_client['contact_name'] ?? ''); ?>"
                       class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none text-white">
            </div>
            
            <div>
                <label for="contact_email" class="block text-sm font-medium text-gray-300 mb-2">E-mail *</label>
                <input type="email" name="contact_email" id="contact_email" required
                       value="<?php echo htmlspecialchars($edit_client['contact_email'] ?? ''); ?>"
                       class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none text-white">
            </div>
            
            <div>
                <label for="contact_phone" class="block text-sm font-medium text-gray-300 mb-2">Telefone</label>
                <input type="text" name="contact_phone" id="contact_phone"
                       value="<?php echo htmlspecialchars($edit_client['contact_phone'] ?? ''); ?>"
                       class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none text-white">
            </div>
            
            <div class="md:col-span-2">
                <label for="address" class="block text-sm font-medium text-gray-300 mb-2">Endereço</label>
                <textarea name="address" id="address" rows="3"
                          class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none text-white"><?php echo htmlspecialchars($edit_client['address'] ?? ''); ?></textarea>
            </div>
            
            <div>
                <label for="city" class="block text-sm font-medium text-gray-300 mb-2">Cidade</label>
                <input type="text" name="city" id="city"
                       value="<?php echo htmlspecialchars($edit_client['city'] ?? ''); ?>"
                       class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none text-white">
            </div>
            
            <div>
                <label for="country" class="block text-sm font-medium text-gray-300 mb-2">País</label>
                <input type="text" name="country" id="country"
                       value="<?php echo htmlspecialchars($edit_client['country'] ?? ''); ?>"
                       class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none text-white">
            </div>
            
            <div>
                <label for="postal_code" class="block text-sm font-medium text-gray-300 mb-2">CEP</label>
                <input type="text" name="postal_code" id="postal_code"
                       value="<?php echo htmlspecialchars($edit_client['postal_code'] ?? ''); ?>"
                       class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none text-white">
            </div>
            
            <div class="md:col-span-2">
                <label for="notes" class="block text-sm font-medium text-gray-300 mb-2">Observações</label>
                <textarea name="notes" id="notes" rows="3"
                          class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none text-white"><?php echo htmlspecialchars($edit_client['notes'] ?? ''); ?></textarea>
            </div>
            
            <div class="md:col-span-2 flex gap-4">
                <button type="submit" class="bg-purple-600 hover:bg-purple-700 px-6 py-3 rounded-lg transition-colors text-white">
                    <?php echo $edit_client ? 'Atualizar Cliente' : 'Adicionar Cliente'; ?>
                </button>
                <?php if ($edit_client): ?>
                    <a href="clients.php" class="bg-gray-600 hover:bg-gray-700 px-6 py-3 rounded-lg transition-colors text-white">
                        Cancelar
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Busca e Lista de Clientes -->
    <div class="bg-gray-900 rounded-lg p-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-semibold text-white">Lista de Clientes</h2>
            
            <!-- Busca -->
            <form method="GET" class="flex gap-2">
                <input type="text" name="search" placeholder="Buscar clientes..."
                       value="<?php echo htmlspecialchars($search); ?>"
                       class="px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none text-white">
                <button type="submit" class="bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg transition-colors">
                    <i class="fas fa-search"></i>
                </button>
                <?php if ($search): ?>
                    <a href="clients.php" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($clients)): ?>
            <p class="text-gray-400 text-center py-8">
                <?php echo $search ? 'Nenhum cliente encontrado com os critérios de busca.' : 'Nenhum cliente cadastrado ainda.'; ?>
            </p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="pb-3 text-gray-300">Empresa</th>
                            <th class="pb-3 text-gray-300">Contato</th>
                            <th class="pb-3 text-gray-300">E-mail</th>
                            <th class="pb-3 text-gray-300">Telefone</th>
                            <th class="pb-3 text-gray-300">Cidade</th>
                            <th class="pb-3 text-gray-300">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                            <tr class="border-b border-gray-700 hover:bg-gray-700">
                                <td class="py-4 text-white font-medium"><?php echo htmlspecialchars($client['company_name']); ?></td>
                                <td class="py-4 text-gray-300"><?php echo htmlspecialchars($client['contact_name']); ?></td>
                                <td class="py-4 text-gray-300"><?php echo htmlspecialchars($client['contact_email']); ?></td>
                                <td class="py-4 text-gray-300"><?php echo htmlspecialchars($client['contact_phone'] ?: '-'); ?></td>
                                <td class="py-4 text-gray-300"><?php echo htmlspecialchars($client['city'] ?: '-'); ?></td>
                                <td class="py-4">
                                    <div class="flex gap-2">
                                        <a href="?edit=<?php echo $client['id']; ?>" 
                                           class="bg-blue-600 hover:bg-blue-700 px-3 py-1 rounded text-sm transition-colors">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja excluir este cliente?')">
                                            <input type="hidden" name="action" value="delete_client">
                                            <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                            <button type="submit" class="bg-red-600 hover:bg-red-700 px-3 py-1 rounded text-sm transition-colors">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
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
</div>
<?php include 'includes/footer.php'; ?>
</body>
</html>
