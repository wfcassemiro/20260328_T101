<?php
session_start();
require_once __DIR__ . 
'/config/database.php';
require_once __DIR__ . 
'/config/dash_database.php';

$page_title = 'Gerenciar Faturas - Dash-T101';

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
                    $invoice_number = generateInvoiceNumber();
                    $tax_amount = $_POST['tax_amount'] ?: 0;
                    $total_amount = $_POST['amount'] + $tax_amount;
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO dash_invoices (project_id, invoice_number, amount, tax_amount, total_amount, 
                                                 status, issue_date, due_date, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['project_id'],
                        $invoice_number,
                        $_POST['amount'],
                        $tax_amount,
                        $total_amount,
                        $_POST['status'],
                        $_POST['issue_date'],
                        $_POST['due_date'],
                        $_POST['notes'] ?: null
                    ]);
                    $message = 'Fatura criada com sucesso! Número: ' . $invoice_number;
                } catch (PDOException $e) {
                    $error = 'Erro ao criar fatura: ' . $e->getMessage();
                }
                break;
                
            case 'update':
                try {
                    $tax_amount = $_POST['tax_amount'] ?: 0;
                    $total_amount = $_POST['amount'] + $tax_amount;
                    
                    $paid_date = null;
                    if ($_POST['status'] === 'paid' && !$_POST['paid_date']) {
                        $paid_date = date('Y-m-d');
                    } elseif ($_POST['paid_date']) {
                        $paid_date = $_POST['paid_date'];
                    }
                    
                    $stmt = $pdo->prepare("
                        UPDATE dash_invoices 
                        SET project_id = ?, amount = ?, tax_amount = ?, total_amount = ?, status = ?, 
                            issue_date = ?, due_date = ?, paid_date = ?, notes = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $_POST['project_id'],
                        $_POST['amount'],
                        $tax_amount,
                        $total_amount,
                        $_POST['status'],
                        $_POST['issue_date'],
                        $_POST['due_date'],
                        $paid_date,
                        $_POST['notes'] ?: null,
                        $_POST['invoice_id']
                    ]);
                    $message = 'Fatura atualizada com sucesso!';
                } catch (PDOException $e) {
                    $error = 'Erro ao atualizar fatura: ' . $e->getMessage();
                }
                break;
                
            case 'delete':
                try {
                    $stmt = $pdo->prepare("DELETE FROM dash_invoices WHERE id = ?");
                    $stmt->execute([$_POST['invoice_id']]);
                    $message = 'Fatura deletada com sucesso!';
                } catch (PDOException $e) {
                    $error = 'Erro ao deletar fatura: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Buscar faturas
try {
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $project_filter = $_GET['project_id'] ?? '';
    
    $sql = "
        SELECT i.*, p.title as project_title, c.name as client_name
        FROM dash_invoices i
        LEFT JOIN dash_projects p ON i.project_id = p.id
        LEFT JOIN dash_clients c ON p.client_id = c.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($search) {
        $sql .= " AND (i.invoice_number LIKE ? OR p.title LIKE ? OR c.name LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    }
    
    if ($status_filter) {
        $sql .= " AND i.status = ?";
        $params[] = $status_filter;
    }
    
    if ($project_filter) {
        $sql .= " AND i.project_id = ?";
        $params[] = $project_filter;
    }
    
    $sql .= " ORDER BY i.issue_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll();
    
    // Buscar projetos para o dropdown
    $stmt = $pdo->query("
        SELECT p.id, p.title, c.name as client_name, p.total_amount
        FROM dash_projects p
        LEFT JOIN dash_clients c ON p.client_id = c.id
        ORDER BY p.title
    ");
    $projects = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Erro ao buscar faturas: ' . $e->getMessage();
    $invoices = [];
    $projects = [];
}

include __DIR__ . '/includes/header.php';
?>

<div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-7xl mx-auto px-4">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Gerenciar Faturas</h1>
                <p class="text-gray-600">Controle suas faturas e pagamentos</p>
            </div>
            <div class="flex space-x-4">
                <a href="index.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar ao Dashboard
                </a>
                <button onclick="openModal('createModal')" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-plus mr-2"></i>Nova Fatura
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
                        placeholder="Buscar faturas..."
                        class="w-full p-3 border border-gray-300 rounded-lg focus:border-purple-500 focus:outline-none"
                    />
                </div>
                
                <div>
                    <select name="status" class="w-full p-3 border border-gray-300 rounded-lg focus:border-purple-500 focus:outline-none">
                        <option value="">Todos os Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pendente</option>
                        <option value="sent" <?php echo $status_filter === 'sent' ? 'selected' : ''; ?>>Enviada</option>
                        <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paga</option>
                        <option value="overdue" <?php echo $status_filter === 'overdue' ? 'selected' : ''; ?>>Vencida</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelada</option>
                    </select>
                </div>
                
                <div>
                    <select name="project_id" class="w-full p-3 border border-gray-300 rounded-lg focus:border-purple-500 focus:outline-none">
                        <option value="">Todos os Projetos</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>" <?php echo $project_filter == $project['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($project['title'] . ' - ' . $project['client_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex space-x-2">
                    <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg flex-1">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if ($search || $status_filter || $project_filter): ?>
                        <a href="invoices.php" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Lista de Faturas -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    Faturas (<?php echo count($invoices); ?>)
                </h3>
            </div>
            
            <?php if (empty($invoices)): ?>
                <div class="p-6 text-center">
                    <i class="fas fa-file-invoice text-gray-400 text-4xl mb-4"></i>
                    <p class="text-gray-500">
                        <?php echo ($search || $status_filter || $project_filter) ? 'Nenhuma fatura encontrada para os filtros aplicados.' : 'Nenhuma fatura cadastrada ainda.'; ?>
                    </p>
                    <?php if (!($search || $status_filter || $project_filter)): ?>
                        <button onclick="openModal('createModal')" class="mt-4 bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg">
                            Criar Primeira Fatura
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Número</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Projeto</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Emissão</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vencimento</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($invoices as $invoice): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php echo htmlspecialchars($invoice['project_title']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php echo htmlspecialchars($invoice['client_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <div>
                                            <div class="font-medium"><?php echo formatCurrency($invoice['total_amount']); ?></div>
                                            <?php if ($invoice['tax_amount'] > 0): ?>
                                                <div class="text-xs text-gray-500">
                                                    (Base: <?php echo formatCurrency($invoice['amount']); ?> + Impostos: <?php echo formatCurrency($invoice['tax_amount']); ?>)
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            <?php 
                                            switch($invoice['status']) {
                                                case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'sent': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'paid': echo 'bg-green-100 text-green-800'; break;
                                                case 'overdue': echo 'bg-red-100 text-red-800'; break;
                                                case 'cancelled': echo 'bg-gray-100 text-gray-800'; break;
                                            }
                                            ?>">
                                            <?php 
                                            switch($invoice['status']) {
                                                case 'pending': echo 'Pendente'; break;
                                                case 'sent': echo 'Enviada'; break;
                                                case 'paid': echo 'Paga'; break;
                                                case 'overdue': echo 'Vencida'; break;
                                                case 'cancelled': echo 'Cancelada'; break;
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php echo formatDate($invoice['issue_date']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php 
                                        $due_date = new DateTime($invoice['due_date']);
                                        $today = new DateTime();
                                        $isOverdue = $due_date < $today && $invoice['status'] !== 'paid';
                                        ?>
                                        <span class="<?php echo $isOverdue ? 'text-red-600 font-bold' : ''; ?>">
                                            <?php echo formatDate($invoice['due_date']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button 
                                                onclick="editInvoice(<?php echo htmlspecialchars(json_encode($invoice)); ?>)"
                                                class="text-blue-600 hover:text-blue-900"
                                            >
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button 
                                                onclick="deleteInvoice(<?php echo $invoice['id']; ?>, '<?php echo htmlspecialchars($invoice['invoice_number']); ?>')"
                                                class="text-red-600 hover:text-red-900"
                                            >
                                                <i class="fas fa-trash"></i>
                                            </button>
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

<!-- Modal Criar Fatura -->
<div id="createModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-md w-full">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Nova Fatura</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Projeto *</label>
                            <select name="project_id" required class="w-full p-3 border border-gray-300 rounded-lg focus:border-purple-500 focus:outline-none">
                                <option value="">Selecione um projeto</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['id']; ?>" data-amount="<?php echo $project['total_amount']; ?>">
                                        <?php echo htmlspecialchars($project['title'] . ' - ' . $project['client_name'] . ' (' . formatCurrency($project['total_amount']) . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Valor Base (R$) *</label>
                            <input type="number" name="amount" step="0.01" min="0" required class="w-full p-3 border border-gray-300 rounded-lg focus:border-purple-500 focus:outline-none">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Impostos (R$)</label>
                            <input type="number" name="tax_amount" step="0.01" min="0" value="0" class="w-full p-3 border border-gray-300 rounded-lg focus:border-purple-500 focus:outline-none">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                            <select name="status" required class="w-full p-3 border border-gray-300 rounded-lg focus:border-purple-500 focus:outline-none">
                                <option value="pending">Pendente</option>
                                <option value="sent">Enviada</option>
                                <option value="paid">Paga</option>
                                <option value="overdue">Vencida</option>
                                <option value="cancelled">Cancelada</option>
                            </select>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Data de Emissão *</label>
                                <input type="date" name="issue_date" required class="w-full p-3 border border-gray-300 rounded-lg focus:border-purple-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Data de Vencimento *</label>
                                <input type="date" name="due_date" required class="w-full p-3 border border-gray-300 rounded-lg focus:border-purple-500 focus:outline-none">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                            <textarea name="notes" rows="3" class="w-full p-3 border border-gray-300 rounded-lg focus:border-purple-500 focus:outline-none"></textarea>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4 mt-6">
                        <button type="button" onclick="closeModal('createModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg">
                            Criar Fatura
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Fatura -->
<div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg max-w-md w-full">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Editar Fatura</h3>
                <form method="POST" id="editForm">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="invoice_id" id="edit_invoice_id">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Projeto *</label>
                            <select name="project_id" id="edit_project_id" required class="w-full p-3 border border-gray-300 rounded-lg focus:border-purple-500 focus:outline-none">
                                <option value="">Selecione um projeto</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['id']; ?>">
                                        <?php echo htmlspecialchars($project['title'] . ' - ' . $project['client_name'] . ' (' . formatCurrency($project['total_amount']) . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Número da Fatura</label>
                            <input type="text" name="invoice_number" id="edit_invoice_number" readonly class="w-full p-3 border border-gray-300 rounded-lg bg-gray-50 cursor-not-allowed">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Valor Base (R$) *</label>
                            <input type="number" name="amount" id="edit_amount" step="0.01" min="0" required class="w-full p-3 border border-gray-300 rounded-lg focus:border-purple-500 focus:outline-none">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Impostos (R$)</label>
                            <input type="number" name="tax_amount" id="edit_tax_amount" step="0.01" min="0" class="w-full p-3 border border-gray-300 rounded-lg focus:border-purple-500 focus:outline-none">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                            <select name="status" id="edit_status" required class="w-full p-3 border border-gray-300 rounded-lg focus:border-purple-500 focus:outline-none">
                                <option value="pending">Pendente</option>
                                <option value="sent">Enviada</option>
                                <option value="paid">Paga</option>
                                <option value="overdue">Vencida</option>
                                <option value="cancelled">Cancelada</option>
                            </select>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Data de Emissão *</label>
                                <input type="date" name="issue_date" id="edit_issue_date" required class="w-full p-3 border border-gray-300 rounded-lg focus:border-purple-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Data de Vencimento *</label>
                                <input type="date" name="due_date" id="edit_due_date" required class="w-full p-3 border border-gray-300 rounded-lg focus:border-purple-500 focus:outline-none">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Data de Pagamento</label>
                            <input type="date" name="paid_date" id="edit_paid_date" class="w-full p-3 border border-gray-300 rounded-lg focus:border-purple-500 focus:outline-none">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                            <textarea name="notes" id="edit_notes" rows="3" class="w-full p-3 border border-gray-300 rounded-lg focus:border-purple-500 focus:outline-none"></textarea>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4 mt-6">
                        <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                            Cancelar
                        </button>
                        <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg">
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
    <input type="hidden" name="invoice_id" id="delete_invoice_id">
</form>

<script>
function openModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function editInvoice(invoice) {
    document.getElementById('edit_invoice_id').value = invoice.id;
    document.getElementById('edit_project_id').value = invoice.project_id;
    document.getElementById('edit_invoice_number').value = invoice.invoice_number;
    document.getElementById('edit_amount').value = invoice.amount;
    document.getElementById('edit_tax_amount').value = invoice.tax_amount;
    document.getElementById('edit_status').value = invoice.status;
    document.getElementById('edit_issue_date').value = invoice.issue_date;
    document.getElementById('edit_due_date').value = invoice.due_date;
    document.getElementById('edit_paid_date').value = invoice.paid_date || '';
    document.getElementById('edit_notes').value = invoice.notes || '';
    openModal('editModal');
}

function deleteInvoice(invoiceId, invoiceNumber) {
    if (confirm(`Tem certeza que deseja deletar a fatura "${invoiceNumber}"?`)) {
        document.getElementById('delete_invoice_id').value = invoiceId;
        document.getElementById('deleteForm').submit();
    }
}

// Preencher valor base da fatura automaticamente ao selecionar projeto
document.addEventListener('DOMContentLoaded', function() {
    const createProjectSelect = document.querySelector('#createModal select[name="project_id"]');
    const createAmountInput = document.querySelector('#createModal input[name="amount"]');

    if (createProjectSelect && createAmountInput) {
        createProjectSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const projectAmount = selectedOption.dataset.amount;
            if (projectAmount) {
                createAmountInput.value = parseFloat(projectAmount).toFixed(2);
            } else {
                createAmountInput.value = '';
            }
        });
    }

    const editProjectSelect = document.querySelector('#editModal select[name="project_id"]');
    const editAmountInput = document.querySelector('#editModal input[name="amount"]');

    if (editProjectSelect && editAmountInput) {
        editProjectSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const projectAmount = selectedOption.dataset.amount;
            if (projectAmount) {
                editAmountInput.value = parseFloat(projectAmount).toFixed(2);
            } else {
                editAmountInput.value = '';
            }
        });
    }

    // Fechar modal ao clicar fora
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('bg-gray-600')) {
            e.target.classList.add('hidden');
        }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

