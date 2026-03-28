<?php
session_start();
require_once 'config/database.php';
require_once 'config/dash_database.php';

// Page settings
$page_title = 'Faturas';
$page_description = 'Crie e gerencie suas faturas.';
$active_page = 'invoices';
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
            case 'add_invoice':
                try {
                    $pdo->beginTransaction();
                    
                    // Gerar número da fatura
                    $invoice_number = generateInvoiceNumber($user_id);
                    
                    // Calcular valores
                    $subtotal = floatval($_POST['subtotal'] ?? 0);
                    $tax_rate = floatval($_POST['tax_rate'] ?? 0);
                    $tax_amount = $subtotal * ($tax_rate / 100);
                    $total_amount = $subtotal + $tax_amount;
                    
                    // Inserir fatura
                    $stmt = $pdo->prepare("INSERT INTO dash_invoices (user_id, client_id, invoice_number, invoice_date, due_date, subtotal, tax_rate, tax_amount, total_amount, currency, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $result = $stmt->execute([
                        $user_id,
                        $_POST['client_id'],
                        $invoice_number,
                        $_POST['invoice_date'],
                        $_POST['due_date'],
                        $subtotal,
                        $tax_rate,
                        $tax_amount,
                        $total_amount,
                        $_POST['currency'] ?? 'BRL',
                        $_POST['status'],
                        $_POST['notes'] ?? ''
                    ]);
                    
                    if ($result) {
                        $invoice_id = $pdo->lastInsertId();
                        
                        // Inserir itens da fatura se fornecidos
                        if (!empty($_POST['items'])) {
                            foreach ($_POST['items'] as $item) {
                                if (!empty($item['description']) && !empty($item['quantity']) && !empty($item['unit_price'])) {
                                    $quantity = floatval($item['quantity']);
                                    $unit_price = floatval($item['unit_price']);
                                    $total_price = $quantity * $unit_price;
                                    
                                    $stmt = $pdo->prepare("INSERT INTO dash_invoice_items (invoice_id, project_id, description, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
                                    $stmt->execute([
                                        $invoice_id,
                                        $item['project_id'] ?: null,
                                        $item['description'],
                                        $quantity,
                                        $unit_price,
                                        $total_price
                                    ]);
                                }
                            }
                        }
                        
                        $pdo->commit();
                        $message = 'Fatura criada com sucesso! Número: ' . $invoice_number;
                    } else {
                        $pdo->rollback();
                        $error = 'Erro ao criar fatura.';
                    }
                } catch (PDOException $e) {
                    $pdo->rollback();
                    $error = 'Erro: ' . $e->getMessage();
                }
                break;
                
            case 'update_status':
                try {
                    $stmt = $pdo->prepare("UPDATE dash_invoices SET status = ?, payment_date = ?, payment_method = ? WHERE id = ? AND user_id = ?");
                    $payment_date = ($_POST['status'] == 'paid' && !empty($_POST['payment_date'])) ? $_POST['payment_date'] : null;
                    $payment_method = ($_POST['status'] == 'paid' && !empty($_POST['payment_method'])) ? $_POST['payment_method'] : null;
                    
                    $result = $stmt->execute([
                        $_POST['status'],
                        $payment_date,
                        $payment_method,
                        $_POST['invoice_id'],
                        $user_id
                    ]);
                    
                    if ($result) {
                        $message = 'Status da fatura atualizado com sucesso!';
                    } else {
                        $error = 'Erro ao atualizar status da fatura.';
                    }
                } catch (PDOException $e) {
                    $error = 'Erro: ' . $e->getMessage();
                }
                break;
                
            case 'delete_invoice':
                try {
                    $stmt = $pdo->prepare("DELETE FROM dash_invoices WHERE id = ? AND user_id = ?");
                    $result = $stmt->execute([$_POST['invoice_id'], $user_id]);
                    
                    if ($result) {
                        $message = 'Fatura excluída com sucesso!';
                    } else {
                        $error = 'Erro ao excluir fatura.';
                    }
                } catch (PDOException $e) {
                    $error = 'Erro: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Obter lista de clientes para o dropdown
$stmt = $pdo->prepare("SELECT id, COALESCE(company_name, client_name, name, CONCAT(first_name, ' ', last_name)) as display_name FROM dash_clients WHERE user_id = ? ORDER BY display_name ASC");
$stmt->execute([$user_id]);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter lista de projetos concluídos para o dropdown
$stmt = $pdo->prepare("SELECT p.id, p.project_name, p.total_amount, COALESCE(c.company_name, c.client_name, c.name, CONCAT(c.first_name, ' ', c.last_name)) as client_display_name FROM dash_projects p LEFT JOIN dash_clients c ON p.client_id = c.id WHERE p.user_id = ? AND p.status = 'completed' ORDER BY p.project_name ASC");
$stmt->execute([$user_id]);
$completed_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter lista de faturas
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$where_clause = "WHERE i.user_id = ?";
$params = [$user_id];

if ($search) {
    $where_clause .= " AND (i.invoice_number LIKE ? OR COALESCE(c.company_name, c.client_name, c.name, CONCAT(c.first_name, ' ', c.last_name)) LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter) {
    $where_clause .= " AND i.status = ?";
    $params[] = $status_filter;
}

$stmt = $pdo->prepare("SELECT i.*, COALESCE(c.company_name, c.client_name, c.name, CONCAT(c.first_name, ' ', c.last_name)) as client_name, c.contact_name FROM dash_invoices i LEFT JOIN dash_clients c ON i.client_id = c.id $where_clause ORDER BY i.created_at DESC");
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter configurações do usuário
$user_settings = getUserSettings($user_id);

include __DIR__ . '/../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-white mb-2">Gerenciar Faturas</h1>
            <p class="text-gray-400">Crie faturas e controle pagamentos</p>
        </div>
        <a href="index.php" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>Voltar ao Dashboard
        </a>
    </div>

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

    <!-- Formulário de Nova Fatura -->
    <div class="bg-gray-800 rounded-lg p-6 mb-8">
        <h2 class="text-xl font-semibold text-white mb-4">Criar Nova Fatura</h2>
        
        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="invoiceForm">
            <input type="hidden" name="action" value="add_invoice">
            
            <div>
                <label for="client_id" class="block text-sm font-medium text-gray-300 mb-2">Cliente *</label>
                <select name="client_id" id="client_id" required
                        class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
                    <option value="">Selecione um cliente</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?php echo $client['id']; ?>">
                            <?php echo htmlspecialchars($client['display_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="invoice_date" class="block text-sm font-medium text-gray-300 mb-2">Data de Emissão *</label>
                <input type="date" name="invoice_date" id="invoice_date" required
                       value="<?php echo date('Y-m-d'); ?>"
                       class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
            </div>
            
            <div>
                <label for="due_date" class="block text-sm font-medium text-gray-300 mb-2">Data de Vencimento *</label>
                <input type="date" name="due_date" id="due_date" required
                       value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>"
                       class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
            </div>
            
            <div>
                <label for="subtotal" class="block text-sm font-medium text-gray-300 mb-2">Subtotal (R$) *</label>
                <input type="number" name="subtotal" id="subtotal" required min="0" step="0.01"
                       class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white"
                       onchange="calculateInvoiceTotal()">
            </div>
            
            <div>
                <label for="tax_rate" class="block text-sm font-medium text-gray-300 mb-2">Taxa de Imposto (%)</label>
                <input type="number" name="tax_rate" id="tax_rate" min="0" step="0.01"
                       value="<?php echo $user_settings['default_tax_rate'] ?? 0; ?>"
                       class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white"
                       onchange="calculateInvoiceTotal()">
            </div>
            
            <div>
                <label for="total_display" class="block text-sm font-medium text-gray-300 mb-2">Total (R$)</label>
                <input type="text" id="total_display" readonly
                       class="w-full p-3 bg-gray-600 border border-gray-600 rounded-lg text-white">
            </div>
            
            <div>
                <label for="status" class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                <select name="status" id="status"
                        class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
                    <option value="draft">Rascunho</option>
                    <option value="sent" selected>Enviada</option>
                    <option value="paid">Paga</option>
                </select>
            </div>
            
            <div class="lg:col-span-2">
                <label for="notes" class="block text-sm font-medium text-gray-300 mb-2">Observações</label>
                <textarea name="notes" id="notes" rows="3"
                          class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white"></textarea>
            </div>
            
            <!-- Seção de Itens da Fatura -->
            <div class="lg:col-span-3">
                <h3 class="text-lg font-semibold text-white mb-4">Itens da Fatura</h3>
                <div id="invoice-items">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4 invoice-item">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Projeto (Opcional)</label>
                            <select name="items[0][project_id]" class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
                                <option value="">Selecione um projeto</option>
                                <?php foreach ($completed_projects as $project): ?>
                                    <option value="<?php echo $project['id']; ?>" data-amount="<?php echo $project['total_amount']; ?>">
                                        <?php echo htmlspecialchars($project['project_name'] . ' - ' . $project['client_display_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Descrição *</label>
                            <input type="text" name="items[0][description]" required
                                   class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Quantidade</label>
                            <input type="number" name="items[0][quantity]" min="1" step="0.01" value="1"
                                   class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white"
                                   onchange="calculateItemTotal(this)">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Preço Unitário (R$)</label>
                            <input type="number" name="items[0][unit_price]" min="0" step="0.01"
                                   class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white"
                                   onchange="calculateItemTotal(this)">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Total</label>
                            <input type="text" class="item-total w-full p-3 bg-gray-600 border border-gray-600 rounded-lg text-white" readonly>
                        </div>
                    </div>
                </div>
                
                <button type="button" onclick="addInvoiceItem()" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg transition-colors text-white mb-4">
                    <i class="fas fa-plus mr-2"></i>Adicionar Item
                </button>
            </div>
            
            <div class="lg:col-span-3 flex gap-4">
                <button type="submit" class="bg-purple-600 hover:bg-purple-700 px-6 py-3 rounded-lg transition-colors text-white">
                    Criar Fatura
                </button>
            </div>
        </form>
    </div>

    <!-- Filtros e Lista de Faturas -->
    <div class="bg-gray-800 rounded-lg p-6">
        <div class="flex flex-col md:flex-row items-center justify-between mb-6 gap-4">
            <h2 class="text-xl font-semibold text-white">Lista de Faturas</h2>
            
            <!-- Filtros -->
            <div class="flex gap-2">
                <form method="GET" class="flex gap-2">
                    <input type="text" name="search" placeholder="Buscar faturas..."
                           value="<?php echo htmlspecialchars($search); ?>"
                           class="px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
                    <select name="status" class="px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
                        <option value="">Todos os status</option>
                        <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Rascunho</option>
                        <option value="sent" <?php echo $status_filter == 'sent' ? 'selected' : ''; ?>>Enviada</option>
                        <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>Paga</option>
                        <option value="overdue" <?php echo $status_filter == 'overdue' ? 'selected' : ''; ?>>Vencida</option>
                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelada</option>
                    </select>
                    <button type="submit" class="bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if ($search || $status_filter): ?>
                        <a href="invoices.php" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg transition-colors">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if (empty($invoices)): ?>
            <p class="text-gray-400 text-center py-8">
                <?php echo ($search || $status_filter) ? 'Nenhuma fatura encontrada com os critérios de busca.' : 'Nenhuma fatura criada ainda.'; ?>
            </p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="pb-3 text-gray-300">Número</th>
                            <th class="pb-3 text-gray-300">Cliente</th>
                            <th class="pb-3 text-gray-300">Data</th>
                            <th class="pb-3 text-gray-300">Vencimento</th>
                            <th class="pb-3 text-gray-300">Valor</th>
                            <th class="pb-3 text-gray-300">Status</th>
                            <th class="pb-3 text-gray-300">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                            <?php
                            // Verificar se a fatura está vencida
                            $is_overdue = ($invoice['status'] == 'sent' && strtotime($invoice['due_date']) < time());
                            ?>
                            <tr class="border-b border-gray-700 hover:bg-gray-700">
                                <td class="py-4">
                                    <p class="text-white font-medium"><?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
                                </td>
                                <td class="py-4 text-gray-300"><?php echo htmlspecialchars($invoice['client_name']); ?></td>
                                <td class="py-4 text-gray-300"><?php echo date('d/m/Y', strtotime($invoice['invoice_date'])); ?></td>
                                <td class="py-4 text-gray-300">
                                    <span class="<?php echo $is_overdue ? 'text-red-400' : ''; ?>">
                                        <?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?>
                                    </span>
                                </td>
                                <td class="py-4 text-gray-300">R$ <?php echo number_format($invoice['total_amount'], 2, ',', '.'); ?></td>
                                <td class="py-4">
                                    <span class="inline-block px-2 py-1 text-xs rounded-full
                                        <?php 
                                        if ($is_overdue) {
                                            echo 'bg-red-600 text-white';
                                        } else {
                                            switch($invoice['status']) {
                                                case 'paid': echo 'bg-green-600 text-white'; break;
                                                case 'sent': echo 'bg-blue-600 text-white'; break;
                                                case 'draft': echo 'bg-gray-600 text-white'; break;
                                                case 'cancelled': echo 'bg-red-600 text-white'; break;
                                                default: echo 'bg-gray-600 text-white';
                                            }
                                        }
                                        ?>">
                                        <?php 
                                        if ($is_overdue) {
                                            echo 'Vencida';
                                        } else {
                                            $status_labels = [
                                                'draft' => 'Rascunho',
                                                'sent' => 'Enviada',
                                                'paid' => 'Paga',
                                                'cancelled' => 'Cancelada'
                                            ];
                                            echo $status_labels[$invoice['status']] ?? $invoice['status'];
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td class="py-4">
                                    <div class="flex gap-2">
                                        <!-- Modal de Atualizar Status -->
                                        <button onclick="openStatusModal(<?php echo $invoice['id']; ?>, '<?php echo $invoice['status']; ?>')" 
                                                class="bg-blue-600 hover:bg-blue-700 px-3 py-1 rounded text-sm transition-colors">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja excluir esta fatura?')">
                                            <input type="hidden" name="action" value="delete_invoice">
                                            <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
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

<!-- Modal de Atualizar Status -->
<div id="statusModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold text-white mb-4">Atualizar Status da Fatura</h3>
            
            <form method="POST" id="statusForm">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="invoice_id" id="modal_invoice_id">
                
                <div class="mb-4">
                    <label for="modal_status" class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                    <select name="status" id="modal_status" required
                            class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white"
                            onchange="togglePaymentFields()">
                        <option value="draft">Rascunho</option>
                        <option value="sent">Enviada</option>
                        <option value="paid">Paga</option>
                        <option value="cancelled">Cancelada</option>
                    </select>
                </div>
                
                <div id="payment_fields" class="hidden">
                    <div class="mb-4">
                        <label for="payment_date" class="block text-sm font-medium text-gray-300 mb-2">Data do Pagamento</label>
                        <input type="date" name="payment_date" id="payment_date"
                               value="<?php echo date('Y-m-d'); ?>"
                               class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
                    </div>
                    
                    <div class="mb-4">
                        <label for="payment_method" class="block text-sm font-medium text-gray-300 mb-2">Método de Pagamento</label>
                        <select name="payment_method" id="payment_method"
                                class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
                            <option value="">Selecione</option>
                            <option value="Transferência Bancária">Transferência Bancária</option>
                            <option value="PIX">PIX</option>
                            <option value="Cartão de Crédito">Cartão de Crédito</option>
                            <option value="Boleto">Boleto</option>
                            <option value="Dinheiro">Dinheiro</option>
                            <option value="Outro">Outro</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex gap-4">
                    <button type="submit" class="bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg transition-colors text-white">
                        Atualizar
                    </button>
                    <button type="button" onclick="closeStatusModal()" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg transition-colors text-white">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let itemCounter = 1;

function calculateInvoiceTotal() {
    const subtotal = parseFloat(document.getElementById('subtotal').value) || 0;
    const taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;
    const taxAmount = subtotal * (taxRate / 100);
    const total = subtotal + taxAmount;
    
    document.getElementById('total_display').value = 'R$ ' + total.toFixed(2).replace('.', ',');
}

function calculateItemTotal(element) {
    const row = element.closest('.invoice-item');
    const quantity = parseFloat(row.querySelector('input[name*="[quantity]"]').value) || 0;
    const unitPrice = parseFloat(row.querySelector('input[name*="[unit_price]"]').value) || 0;
    const total = quantity * unitPrice;
    
    row.querySelector('.item-total').value = 'R$ ' + total.toFixed(2).replace('.', ',');
    
    // Recalcular subtotal
    updateSubtotal();
}

function updateSubtotal() {
    let subtotal = 0;
    document.querySelectorAll('.invoice-item').forEach(function(row) {
        const quantity = parseFloat(row.querySelector('input[name*="[quantity]"]').value) || 0;
        const unitPrice = parseFloat(row.querySelector('input[name*="[unit_price]"]').value) || 0;
        subtotal += quantity * unitPrice;
    });
    
    document.getElementById('subtotal').value = subtotal.toFixed(2);
    calculateInvoiceTotal();
}

function addInvoiceItem() {
    const container = document.getElementById('invoice-items');
    const newItem = document.createElement('div');
    newItem.className = 'grid grid-cols-1 md:grid-cols-5 gap-4 mb-4 invoice-item';
    newItem.innerHTML = `
        <div>
            <select name="items[${itemCounter}][project_id]" class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
                <option value="">Selecione um projeto</option>
                <?php foreach ($completed_projects as $project): ?>
                    <option value="<?php echo $project['id']; ?>" data-amount="<?php echo $project['total_amount']; ?>">
                        <?php echo htmlspecialchars($project['project_name'] . ' - ' . $project['client_display_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <input type="text" name="items[${itemCounter}][description]" required
                   class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
        </div>
        <div>
            <input type="number" name="items[${itemCounter}][quantity]" min="1" step="0.01" value="1"
                   class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white"
                   onchange="calculateItemTotal(this)">
        </div>
        <div>
            <input type="number" name="items[${itemCounter}][unit_price]" min="0" step="0.01"
                   class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white"
                   onchange="calculateItemTotal(this)">
        </div>
        <div class="flex gap-2">
            <input type="text" class="item-total flex-1 p-3 bg-gray-600 border border-gray-600 rounded-lg text-white" readonly>
            <button type="button" onclick="removeInvoiceItem(this)" class="bg-red-600 hover:bg-red-700 px-3 py-2 rounded-lg transition-colors text-white">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    
    container.appendChild(newItem);
    itemCounter++;
}

function removeInvoiceItem(button) {
    button.closest('.invoice-item').remove();
    updateSubtotal();
}

function openStatusModal(invoiceId, currentStatus) {
    document.getElementById('modal_invoice_id').value = invoiceId;
    document.getElementById('modal_status').value = currentStatus;
    document.getElementById('statusModal').classList.remove('hidden');
    togglePaymentFields();
}

function closeStatusModal() {
    document.getElementById('statusModal').classList.add('hidden');
}

function togglePaymentFields() {
    const status = document.getElementById('modal_status').value;
    const paymentFields = document.getElementById('payment_fields');
    
    if (status === 'paid') {
        paymentFields.classList.remove('hidden');
    } else {
        paymentFields.classList.add('hidden');
    }
}

// Calcular total ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    calculateInvoiceTotal();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
