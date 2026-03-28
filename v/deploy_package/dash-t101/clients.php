<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';

// Verificar se o usuário está logado
if (!isLoggedIn()) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$page_title = 'Clientes - Dash-T101';
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_client':
                try {
                    // Adicionando vat_number, address_line1, address_line2, address_line3 no INSERT
                    $stmt = $pdo->prepare("INSERT INTO dash_clients (user_id, company, name, email, vat_number, phone, default_currency, address_line1, address_line2, address_line3, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $result = $stmt->execute([
                        $user_id,
                        $_POST['company_name'],
                        $_POST['contact_name'],
                        $_POST['contact_email'],
                        $_POST['vat_number'] ?? '', // Novo campo
                        $_POST['contact_phone'] ?? '',
                        $_POST['default_currency'] ?? 'BRL',
                        $_POST['address_line1'] ?? '', // Novo campo
                        $_POST['address_line2'] ?? '', // Novo campo
                        $_POST['address_line3'] ?? '', // Novo campo
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
                    // Adicionando vat_number, address_line1, address_line2, address_line3 no UPDATE
                    $stmt = $pdo->prepare("UPDATE dash_clients SET company = ?, name = ?, email = ?, vat_number = ?, phone = ?, default_currency = ?, address_line1 = ?, address_line2 = ?, address_line3 = ?, notes = ? WHERE id = ? AND user_id = ?");
                    $result = $stmt->execute([
                        $_POST['company_name'],
                        $_POST['contact_name'],
                        $_POST['contact_email'],
                        $_POST['vat_number'] ?? '', // Novo campo
                        $_POST['contact_phone'] ?? '',
                        $_POST['default_currency'] ?? 'BRL',
                        $_POST['address_line1'] ?? '', // Novo campo
                        $_POST['address_line2'] ?? '', // Novo campo
                        $_POST['address_line3'] ?? '', // Novo campo
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
    $where_clause .= " AND (company LIKE ? OR name LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Selecionando os novos campos
$stmt = $pdo->prepare("SELECT id, user_id, company AS company_name, name AS contact_name, email AS contact_email, vat_number, phone AS contact_phone, default_currency, address_line1, address_line2, address_line3, notes FROM dash_clients $where_clause ORDER BY company ASC");
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter cliente para edição se solicitado
$edit_client = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    // Selecionando os novos campos para edição
    $stmt = $pdo->prepare("SELECT id, user_id, company AS company_name, name AS contact_name, email AS contact_email, vat_number, phone AS contact_phone, default_currency, address_line1, address_line2, address_line3, notes FROM dash_clients WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['edit'], $user_id]);
    $edit_client = $stmt->fetch(PDO::FETCH_ASSOC);
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-white mb-2">Gerenciar Clientes</h1>
            <p class="text-gray-400">Adicione e gerencie informações dos seus clientes</p>
        </div>
        <a href="index.php" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>Voltar ao Dashboard
        </a>
    </div>

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

    <div class="bg-gray-800 rounded-lg p-6 mb-8">
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
                       class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
            </div>
            
            <div>
                <label for="contact_name" class="block text-sm font-medium text-gray-300 mb-2">Nome do Contato *</label>
                <input type="text" name="contact_name" id="contact_name" required
                       value="<?php echo htmlspecialchars($edit_client['contact_name'] ?? ''); ?>"
                       class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
            </div>
            
            <div>
                <label for="contact_email" class="block text-sm font-medium text-gray-300 mb-2">E-mail *</label>
                <input type="email" name="contact_email" id="contact_email" required
                       value="<?php echo htmlspecialchars($edit_client['contact_email'] ?? ''); ?>"
                       class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
            </div>
            
            <div>
                <label for="vat_number" class="block text-sm font-medium text-gray-300 mb-2">VAT / CNPJ / CPF</label>
                <input type="text" name="vat_number" id="vat_number"
                       value="<?php echo htmlspecialchars($edit_client['vat_number'] ?? ''); ?>"
                       class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
            </div>

            <div>
                <label for="contact_phone" class="block text-sm font-medium text-gray-300 mb-2">Telefone</label>
                <input type="text" name="contact_phone" id="contact_phone"
                       value="<?php echo htmlspecialchars($edit_client['contact_phone'] ?? ''); ?>"
                       class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
            </div>
            
            <div>
                <label for="default_currency" class="block text-sm font-medium text-gray-300 mb-2">Moeda Padrão *</label>
                <select name="default_currency" id="default_currency" required
                        class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
                    <?php foreach ($dash_config['currencies'] as $currencyCode): ?>
                        <option value="<?php echo $currencyCode; ?>"
                                <?php echo ($edit_client && $edit_client['default_currency'] == $currencyCode) ? 'selected' : ''; ?>>
                            <?php echo $currencyCode; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="md:col-span-2">
                <label for="address_line1" class="block text-sm font-medium text-gray-300 mb-2">Endereço Campo 1</label>
                <input type="text" name="address_line1" id="address_line1"
                       value="<?php echo htmlspecialchars($edit_client['address_line1'] ?? ''); ?>"
                       class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
            </div>
            
            <div class="md:col-span-2">
                <label for="address_line2" class="block text-sm font-medium text-gray-300 mb-2">Endereço Campo 2</label>
                <input type="text" name="address_line2" id="address_line2"
                       value="<?php echo htmlspecialchars($edit_client['address_line2'] ?? ''); ?>"
                       class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
            </div>

            <div class="md:col-span-2">
                <label for="address_line3" class="block text-sm font-medium text-gray-300 mb-2">País</label> <input type="text" name="address_line3" id="address_line3"
                       value="<?php echo htmlspecialchars($edit_client['address_line3'] ?? ''); ?>"
                       class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
            </div>
            
            <div class="md:col-span-2">
                <label for="notes" class="block text-sm font-medium text-gray-300 mb-2">Observações</label>
                <textarea name="notes" id="notes" rows="3"
                          class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white"><?php echo htmlspecialchars($edit_client['notes'] ?? ''); ?></textarea>
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

    <div class="bg-gray-800 rounded-lg p-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-semibold text-white">Lista de Clientes</h2>
            
            <form method="GET" class="flex gap-2">
                <input type="text" name="search" placeholder="Buscar clientes..."
                       value="<?php echo htmlspecialchars($search); ?>"
                       class="px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
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
                            <th class="pb-3 text-gray-300">VAT</th>
                            <th class="pb-3 text-gray-300">Telefone</th>
                            <th class="pb-3 text-gray-300">Moeda Padrão</th>
                            <th class="pb-3 text-gray-300">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                            <tr class="border-b border-gray-700 hover:bg-gray-700">
                                <td class="py-4 text-white font-medium"><?php echo htmlspecialchars($client['company_name']); ?></td>
                                <td class="py-4 text-gray-300"><?php echo htmlspecialchars($client['contact_name']); ?></td>
                                <td class="py-4 text-gray-300"><?php echo htmlspecialchars($client['contact_email']); ?></td>
                                <td class="py-4 text-gray-300"><?php echo htmlspecialchars($client['vat_number'] ?: '-'); ?></td>
                                <td class="py-4 text-gray-300"><?php echo htmlspecialchars($client['contact_phone'] ?: '-'); ?></td>
                                <td class="py-4 text-gray-300"><?php echo htmlspecialchars($client['default_currency'] ?: 'N/A'); ?></td>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>