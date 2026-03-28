<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';
require_once __DIR__ . '/../config/dash_functions.php';

// Verificar se o usuário está logado
if (!isLoggedIn()) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$user_id = $_SESSION['user_id'];

// =========================================================================
// || CORREÇÃO 1: Função generateInvoiceNumber() ADICIONADA AQUI          ||
// =========================================================================
/**
 * Gera um novo número de fatura sequencial para o usuário.
 * Ex: FAT-0001, FAT-0002
 */
if (!function_exists('generateInvoiceNumber')) {
    function generateInvoiceNumber($user_id) {
        global $pdo; // Pega a conexão do dash_database.php
        try {
            // 1. Encontrar o número da última fatura para este usuário
            $stmt = $pdo->prepare("SELECT invoice_number FROM dash_invoices WHERE user_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$user_id]);
            $last_invoice = $stmt->fetchColumn();
            
            $new_number = 1;
            if ($last_invoice) {
                // Extrai apenas o número (ex: "FAT-0001" -> 1)
                $last_num = (int)preg_replace('/[^0-9]/', '', $last_invoice);
                $new_number = $last_num + 1;
            }
            
            // Formata com 4 dígitos (ex: "FAT-0001", "FAT-0002")
            return 'FAT-' . str_pad($new_number, 4, '0', STR_PAD_LEFT);

        } catch (Exception $e) {
            // Fallback
            return 'FAT-' . time();
        }
    }
}
// =========================================================================
// || FIM DA CORREÇÃO 1                                                 ||
// =========================================================================


// =========================================================================
// || API ENDPOINT: Buscar projetos não faturados de um cliente           ||
// =========================================================================
if (isset($_GET['api']) && $_GET['api'] === 'get_unbilled_projects') {
    header('Content-Type: application/json');
    
    // Garantir que $pdo e $user_id (da sessão) estejam no escopo.
    global $pdo, $user_id; 

    $client_id = $_POST['client_id'] ?? 0;
    
    if (empty($client_id)) {
        echo json_encode(['error' => 'Client ID é obrigatório']);
        exit;
    }
    
    try {
        
        // Esta query busca projetos que estão "completed" (conforme .sql)
        // e que AINDA NÃO existem na tabela de junção 'dash_invoice_projects'.
        $stmt = $pdo->prepare(
            "SELECT p.id, p.title, p.total_amount, p.currency
             FROM dash_projects p
             LEFT JOIN dash_invoice_projects ip ON p.id = ip.project_id
             WHERE p.user_id = ? 
               AND p.client_id = ? 
               AND p.status = 'completed'
               AND ip.invoice_id IS NULL"
        );
        $stmt->execute([$user_id, $client_id]);
        $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['projects' => $projects]);
        
    } catch (Throwable $e) { 
        // Retorna o erro real do PHP para o JavaScript
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
// =========================================================================
// || FIM DA API                                                        ||
// =========================================================================


$page_title = 'Faturas - Dash-T101';
$message = '';
$error = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            // Ação principal: Criar fatura a partir de projetos selecionados
            case 'add_invoice_from_projects':
                try {
                    $pdo->beginTransaction();
                    
                    $project_ids = $_POST['project_ids'] ?? [];
                    if (empty($project_ids)) {
                        throw new Exception("Nenhum projeto foi selecionado.");
                    }
                    
                    $client_id = $_POST['client_id'];
                    $tax_rate = floatval($_POST['tax_rate'] ?? 0);
                    $subtotal = 0;
                    
                    // Gerar número da fatura
                    $invoice_number = generateInvoiceNumber($user_id);
                    
                    // 1. Buscar projetos e calcular subtotal
                    $projects_to_invoice = [];
                    foreach ($project_ids as $project_id) {
                        $stmt_proj = $pdo->prepare("SELECT title, total_amount, currency FROM dash_projects WHERE id = ? AND user_id = ? AND client_id = ?");
                        $stmt_proj->execute([$project_id, $user_id, $client_id]);
                        $project = $stmt_proj->fetch(PDO::FETCH_ASSOC);
                        
                        if ($project) {
                            $subtotal += $project['total_amount'];
                            $projects_to_invoice[] = $project;
                        }
                    }
                    
                    if (empty($projects_to_invoice)) {
                        throw new Exception("Projetos selecionados não são válidos.");
                    }
                    
                    // Usar a moeda do primeiro projeto como a moeda da fatura
                    $currency = $_POST['currency'] ?? $projects_to_invoice[0]['currency'];
                    
                    // Calcular impostos e total
                    $tax_amount = $subtotal * ($tax_rate / 100);
                    $total_amount = $subtotal + $tax_amount;

                    // Capturar datas do formulário
                    $issue_date = $_POST['issue_date'];
                    $due_date   = $_POST['due_date'];
                    
                    // 2. Inserir a fatura principal (CORRIGIDO: Preenche colunas duplicadas no BD)
                    // Grava em 'invoice_number' e 'number', 'issue_date' e 'date', 'total_amount' e 'total'
                    $stmt = $pdo->prepare("INSERT INTO dash_invoices 
                        (user_id, client_id, invoice_number, number, issue_date, date, due_date, subtotal, tax_rate, tax_amount, total_amount, total, currency, status, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $result = $stmt->execute([
                        $user_id,
                        $client_id,
                        $invoice_number,
                        $invoice_number, // Duplicado para coluna 'number'
                        $issue_date,
                        $issue_date,     // Duplicado para coluna 'date'
                        $due_date,
                        $subtotal,
                        $tax_rate,
                        $tax_amount,
                        $total_amount,
                        $total_amount,   // Duplicado para coluna 'total'
                        $currency,
                        $_POST['status'] ?? 'draft',
                        $_POST['notes'] ?? ''
                    ]);
                    
                    $invoice_id = $pdo->lastInsertId();
                    
                    // 3. Inserir os itens da fatura (baseado nos projetos)
                    $stmt_item = $pdo->prepare("INSERT INTO dash_invoice_items (invoice_id, description, quantity, unit_price) VALUES (?, ?, ?, ?)");
                    foreach ($projects_to_invoice as $project) {
                        $stmt_item->execute([
                            $invoice_id,
                            $project['title'], // description
                            1,                  // quantity
                            $project['total_amount'] // unit_price
                        ]);
                    }
                    
                    // 4. Vincular projetos à fatura na tabela PIVOT (dash_invoice_projects)
                    $stmt_pivot = $pdo->prepare("INSERT INTO dash_invoice_projects (invoice_id, project_id) VALUES (?, ?)");
                    foreach ($project_ids as $project_id) {
                        $stmt_pivot->execute([$invoice_id, $project_id]);
                    }
                    
                    $pdo->commit();
                    $message = "Fatura $invoice_number gerada com sucesso.";
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Erro ao gerar fatura: ' . $e->getMessage();
                }
                break;
            
            case 'update_status':
                try {
                    $invoice_id = $_POST['invoice_id'];
                    $status = $_POST['status'];
                    $payment_date = ($status == 'paid') ? ($_POST['payment_date'] ?: null) : null;
                    
                    $stmt = $pdo->prepare("UPDATE dash_invoices SET status = ?, payment_date = ? WHERE id = ? AND user_id = ?");
                    $stmt->execute([$status, $payment_date, $invoice_id, $user_id]);
                    $message = "Status da fatura atualizado.";
                    
                } catch (Exception $e) {
                    $error = 'Erro ao atualizar status: ' . $e->getMessage();
                }
                break;
            
            case 'delete_invoice':
                try {
                    $invoice_id = $_POST['invoice_id'];
                    // Excluir da tabela PIVOT primeiro (ON DELETE CASCADE pode não estar configurado)
                    $stmt_pivot = $pdo->prepare("DELETE FROM dash_invoice_projects WHERE invoice_id = ?");
                    $stmt_pivot->execute([$invoice_id]);
                    
                    // Excluir a fatura (itens serão excluídos por FK)
                    $stmt = $pdo->prepare("DELETE FROM dash_invoices WHERE id = ? AND user_id = ?");
                    $stmt->execute([$invoice_id, $user_id]);
                    $message = "Fatura excluída com sucesso.";
                } catch (Exception $e) {
                    $error = 'Erro ao excluir fatura: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Obter clientes
$stmt_clients = $pdo->prepare("SELECT id, company AS company_name, default_currency FROM dash_clients WHERE user_id = ? ORDER BY company_name ASC");
$stmt_clients->execute([$user_id]);
$clients = $stmt_clients->fetchAll(PDO::FETCH_ASSOC);

// Obter faturas com busca
$search = $_GET['search'] ?? '';
$where_clause = "WHERE i.user_id = ?";
$params = [$user_id];

if ($search) {
    $where_clause .= " AND (i.invoice_number LIKE ? OR c.company LIKE ? OR i.status LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$stmt_invoices = $pdo->prepare(
    "SELECT i.*, c.company AS client_name 
     FROM dash_invoices i 
     JOIN dash_clients c ON i.client_id = c.id 
     $where_clause
     ORDER BY i.issue_date DESC"
);
$stmt_invoices->execute($params);
$invoices = $stmt_invoices->fetchAll(PDO::FETCH_ASSOC);

// Mapeamento de status
$status_map = [
    'draft' => ['label' => 'Rascunho', 'class' => 'status-pending', 'icon' => 'fa-pencil-alt'],
    'sent' => ['label' => 'Enviada', 'class' => 'status-in_progress', 'icon' => 'fa-paper-plane'],
    'paid' => ['label' => 'Paga', 'class' => 'status-completed', 'icon' => 'fa-check-circle'],
    'overdue' => ['label' => 'Vencida', 'class' => 'status-cancelled', 'icon' => 'fa-exclamation-triangle']
];

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">

    <?php if ($message): ?><div id="success-alert" class="alert-success"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert-error"><i class="fas fa-exclamation-triangle"></i><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="video-card profile-header-card" style="background: linear-gradient(135deg, var(--brand-purple), #4a148c); border: none; margin-bottom: 25px;">
        <div class="header-icon-container" style="background: rgba(255, 255, 255, 0.1); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <i class="fas fa-file-invoice-dollar" style="font-size: 1.8rem; color: #fff;"></i>
        </div>
        <div class="header-text-container" style="margin-left: 20px;">
            <div>
                <h2 style="margin: 0 0 5px 0; padding: 0; font-size: 1.5rem; color: #fff; font-weight: 600; border: none;">Gestão de Faturas</h2>
                <p style="margin: 0; color: rgba(255, 255, 255, 0.8); font-size: 1rem;">Crie e gerencie suas faturas e recebimentos.</p>
            </div>
        </div>
    </div>
    
    <div class="report-nav-buttons">
        <a href="index.php" class="vision-btn vision-btn-secondary"><i class="fas fa-home"></i> Voltar ao Dash-T101</a>
    </div>
    
    <div class="video-card">
        <h2><i class="fas fa-plus-circle"></i> Criar Fatura (Baseado em Projetos)</h2>
        
        <form method="POST" class="vision-form-refined" action="invoices.php">
            <input type="hidden" name="action" value="add_invoice_from_projects">

            <div class="form-row" style="grid-template-columns: 2fr 1fr 1fr;">
                <div class="form-group">
                    <label for="client_id"><i class="fas fa-user-tie"></i> Cliente *</label>
                    <select id="client_id" name="client_id" class="vision-select" required>
                        <option value="">Selecione um cliente...</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>" data-currency="<?php echo htmlspecialchars($client['default_currency']); ?>">
                                <?php echo htmlspecialchars($client['company_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="issue_date"><i class="fas fa-calendar"></i> Data de Emissão *</label>
                    <input type="date" id="issue_date" name="issue_date" class="vision-input" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="due_date"><i class="fas fa-calendar-alt"></i> Data de Vencimento *</label>
                    <input type="date" id="due_date" name="due_date" class="vision-input" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                </div>
            </div>
            
            <div id="projects-container" class="form-group" style="grid-column: 1 / -1; display: none;">
                <label for="project_ids"><i class="fas fa-folder-open"></i> Projetos Concluídos (Não Faturados)</label>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: -5px 0 10px 0;">Selecione um ou mais projetos para adicionar à fatura. O subtotal será calculado automaticamente.</p>
                <div id="projects-checkbox-list" class="project-checkbox-list">
                    <span class="text-muted" style="padding: 10px;">Selecione um cliente para ver os projetos.</span>
                </div>
            </div>

            <hr class="form-divider">

            <div class="form-row" style="grid-template-columns: 1fr 1fr 1fr;">
                <div class="form-group">
                    <label for="currency"><i class="fas fa-money-bill-wave"></i> Moeda</label>
                    <select id="currency" name="currency" class="vision-select">
                        <option value="BRL">BRL</option>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tax_rate"><i class="fas fa-percentage"></i> Imposto (%)</label>
                    <input type="number" id="tax_rate" name="tax_rate" class="vision-input" value="0" step="0.01">
                </div>
                <div class="form-group">
                    <label for="status"><i class="fas fa-tasks"></i> Status Inicial</label>
                    <select id="status" name="status" class="vision-select">
                        <option value="draft">Rascunho</option>
                        <option value="sent">Enviada</option>
                    </select>
                </div>
            </div>

            <div class="form-row" style="grid-template-columns: 1fr;">
                <div class="form-group">
                    <label for="notes"><i class="fas fa-clipboard"></i> Observações</label>
                    <textarea id="notes" name="notes" rows="3" class="vision-textarea" placeholder="Informações bancárias, termos de pagamento, etc."></textarea>
                </div>
            </div>

            <div class="invoice-totals-summary">
                <div class="total-item">
                    <span>Subtotal:</span>
                    <span id="subtotal_display">R$ 0,00</span>
                    </div>
                <div class="total-item">
                    <span>Imposto (<span id="tax_rate_display">0</span>%):</span>
                    <span id="tax_amount_display">R$ 0,00</span>
                </div>
                <div class="total-item grand-total">
                    <span>Valor Total:</span>
                    <span id="total_amount_display">R$ 0,00</span>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="vision-btn vision-btn-primary">
                    <i class="fas fa-save"></i> Salvar Fatura
                </button>
            </div>
        </form>
    </div>

    <div class="video-card">
        <div class="card-header-refined">
            <h2><i class="fas fa-file-invoice-dollar"></i> Faturas Emitidas</h2>
            <div class="search-filters-refined">
                <form method="GET" class="search-form-refined" action="invoices.php">
                    <div class="search-group-refined">
                        <input type="text" name="search" placeholder="Nº da fatura, cliente, status..." value="<?php echo htmlspecialchars($search); ?>" class="vision-search">
                        <button type="submit" class="vision-btn vision-btn-primary"><i class="fas fa-search"></i></button>
                        <?php if ($search): ?><a href="invoices.php" class="vision-btn vision-btn-secondary"><i class="fas fa-times"></i></a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($invoices)): ?>
            <div class="alert-warning" style="margin: 20px 30px;"><i class="fas fa-info-circle"></i>
                <?php echo $search ? 'Nenhuma fatura encontrada.' : 'Nenhuma fatura emitida ainda.'; ?>
            </div>
        <?php else: ?>
            <div class="vision-table-container">
                <table class="vision-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i> Fatura</th>
                            <th><i class="fas fa-user-tie"></i> Cliente</th>
                            <th><i class="fas fa-calendar"></i> Emissão</th>
                            <th><i class="fas fa-calendar-alt"></i> Vencimento</th>
                            <th><i class="fas fa-money-bill-wave"></i> Valor Total</th>
                            <th><i class="fas fa-tasks"></i> Status</th>
                            <th><i class="fas fa-cogs"></i> Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): 
                            $status_info = $status_map[$invoice['status']] ?? ['label' => ucfirst($invoice['status']), 'class' => 'status-pending', 'icon' => 'fa-question-circle'];
                        ?>
                            <tr>
                                <td><span class="project-name-refined"><?php echo htmlspecialchars($invoice['invoice_number']); ?></span></td>
                                <td><?php echo htmlspecialchars($invoice['client_name']); ?></td>
                                <td>
                                    <?php 
                                    // CORREÇÃO NA EXIBIÇÃO: Verifica se a data é válida antes de formatar
                                    if (!empty($invoice['issue_date']) && $invoice['issue_date'] != '0000-00-00') {
                                        echo date('d/m/Y', strtotime($invoice['issue_date'])); 
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?></td>
                                <td class="value-cell-refined" style="color: var(--accent-green);"><?php echo formatCurrency($invoice['total_amount'], $invoice['currency']); ?></td>
                                <td>
                                    <span class="status-badge-refined <?php echo $status_info['class']; ?>">
                                        <i class="fas <?php echo $status_info['icon']; ?>"></i> <?php echo htmlspecialchars($status_info['label']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons-refined">
                                        <button type="button" class="action-btn-refined action-btn-edit" title="Alterar Status" onclick="openStatusModal(<?php echo $invoice['id']; ?>, '<?php echo $invoice['status']; ?>')">
                                            <i class="fas fa-exchange-alt"></i>
                                        </button>
                                        <a href="view_invoice.php?id=<?php echo $invoice['id']; ?>" class="action-btn-refined" title="Visualizar/Imprimir" target="_blank" style="background: rgba(59, 130, 246, 0.2); color: #3b82f6;">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir esta fatura?');">
                                            <input type="hidden" name="action" value="delete_invoice">
                                            <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
                                            <button type="submit" class="action-btn-refined action-btn-delete" title="Excluir">
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
    <div class="report-nav-buttons">
        <a href="index.php" class="vision-btn vision-btn-secondary"><i class="fas fa-home"></i> Voltar ao Dash-T101</a>
    </div>
</div>

<div id="statusModal" class="vision-modal">
    <div class="vision-modal-content" style="max-width: 500px;">
        <div class="vision-modal-header">
            <h3><i class="fas fa-exchange-alt"></i> Alterar Status da Fatura</h3>
            <button type="button" class="vision-modal-close" onclick="closeStatusModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" class="vision-modal-form" action="invoices.php">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="invoice_id" id="modal_invoice_id">
            
            <div class="form-group">
                <label for="modal_status">Novo Status</label>
                <select id="modal_status" name="status" class="vision-select" onchange="togglePaymentFields()">
                    <option value="draft">Rascunho</option>
                    <option value="sent">Enviada</option>
                    <option value="paid">Paga</option>
                    <option value="overdue">Vencida</option>
                </select>
            </div>
            
            <div id="payment_fields" style="display: none;">
                <div class="form-group">
                    <label for="payment_date">Data de Pagamento</label>
                    <input type="date" id="payment_date" name="payment_date" class="vision-input">
                </div>
            </div>
            
            <div class="vision-modal-actions">
                <button type="submit" class="vision-btn vision-btn-primary">
                    <i class="fas fa-save"></i> Salvar Status
                </button>
                <button type="button" class="vision-btn vision-btn-secondary" onclick="closeStatusModal()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Alertas */
.alert-success { opacity: 1; transition: opacity 1s ease-out; background: #22c55e; color: #fff; padding: 15px 30px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.alert-error { background: #ef4444; color: #fff; padding: 15px 30px; border-radius: 12px; margin-bottom: 20px; }
.alert-warning { background: #f9b42d; color: #1a1a1e; padding: 15px 30px; border-radius: 12px; font-weight: 500;}

/* CSS: Header Roxo (Padrão) */
.profile-header-card {
    display: flex;
    align-items: center;
    justify-content: center; /* Centraliza */
}
.header-icon-container {
    background: rgba(255, 255, 255, 0.1); 
    border-radius: 50%; 
    width: 60px; 
    height: 60px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    flex-shrink: 0;
}
.header-text-container {
    margin-left: 20px;
}
.header-text-container h2 {
    margin: 0 0 5px 0; 
    padding: 0; 
    font-size: 1.5rem; 
    color: #fff; 
    font-weight: 600; 
    border: none;
}
.header-text-container p {
    margin: 0; 
    color: rgba(255, 255, 255, 0.8); 
    font-size: 1rem;
}

/* CSS: Botões de Navegação (ADICIONADO) */
.report-nav-buttons {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

/* Layout do Card */
.main-content .video-card { margin-bottom: 20px; }
.video-card { background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.05)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; }
.video-card > h2 { margin: 0; padding: 25px 30px 20px; font-size: 1.3rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); }

/* Formulário */
.vision-form-refined { padding: 30px; }
.form-row { display: grid; gap: 20px; margin-bottom: 25px; }
.form-group { display: flex; flex-direction: column; }
.form-group label { margin-bottom: 8px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px; font-size: 0.9rem; }
.vision-input, .vision-select, .vision-textarea { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; padding: 12px 16px; color: var(--text-primary); font-size: 0.95rem; transition: all 0.3s ease; outline: none; }
.vision-input:focus, .vision-select:focus, .vision-textarea:focus { border-color: var(--brand-purple); box-shadow: 0 0 0 3px rgba(142, 68, 173, 0.2); background: rgba(255, 255, 255, 0.08); }
.vision-input-readonly { background: rgba(255, 255, 255, 0.02); color: var(--text-muted); cursor: not-allowed; opacity: 0.7; }
.vision-textarea { resize: vertical; min-height: 80px; font-family: inherit; }
.form-actions { display: flex; gap: 15px; justify-content: flex-start; margin-top: 10px; }
.form-divider { border: none; height: 1px; background: rgba(255, 255, 255, 0.1); margin: 20px 0; }
.invoice-totals-summary { margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255, 255, 255, 0.1); max-width: 350px; margin-left: auto; }
.total-item { display: flex; justify-content: space-between; font-size: 1rem; color: var(--text-secondary); margin-bottom: 10px; }
.total-item span:last-child { font-weight: 600; color: var(--text-primary); }
.total-item.grand-total span { font-size: 1.2rem; font-weight: 700; color: var(--accent-green); }

/* NOVO CSS: Lista de Checkbox de Projetos */
.project-checkbox-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 200px;
    overflow-y: auto;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 10px;
}
.project-checkbox-item {
    display: flex;
    align-items: center;
    padding: 10px 15px;
    background: rgba(0, 0, 0, 0.1);
    border-radius: 12px;
    transition: background 0.2s ease;
    cursor: pointer;
}
.project-checkbox-item:hover {
    background: rgba(0, 0, 0, 0.2);
}
.project-checkbox-item input[type="checkbox"] {
    margin-right: 12px;
    width: 18px;
    height: 18px;
    accent-color: var(--brand-purple);
    flex-shrink: 0;
}
.project-checkbox-item .checkbox-title {
    flex-grow: 1;
    color: var(--text-primary);
    font-weight: 500;
}
.project-checkbox-item .checkbox-amount {
    color: var(--accent-green);
    font-weight: 600;
    font-size: 0.9rem;
    padding-left: 10px;
}

/* Botões */
.vision-btn { background: var(--brand-purple); color: white; border: 1px solid var(--brand-purple); border-radius: 30px; padding: 12px 24px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: all 0.3s ease; font-size: 0.95rem; box-shadow: 0 4px 12px rgba(142, 68, 173, 0.3); }
.vision-btn:hover { background: var(--brand-purple-dark); transform: translateY(-1px); box-shadow: 0 6px 16px rgba(142, 68, 173, 0.4); }
.vision-btn-primary { background: var(--brand-purple); border-color: var(--brand-purple); padding: 10px 20px; font-size: 0.9rem; }
.vision-btn-secondary { background: rgba(255, 255, 255, 0.1); color: var(--text-primary); border-color: rgba(255, 255, 255, 0.2); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); }
.vision-btn-secondary:hover { background: rgba(255, 255, 255, 0.15); border-color: rgba(255, 255, 255, 0.3); }

/* Cabeçalho da Lista (com Busca) */
.card-header-refined { display: flex; justify-content: space-between; align-items: center; padding: 25px 30px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); }
.card-header-refined h2 { margin: 0 !important; padding: 0 !important; font-size: 1.3rem; border: none; }
.search-filters-refined, .search-form-refined, .search-group-refined { display: flex; align-items: center; gap: 12px; }
.vision-search { background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 30px; padding: 10px 16px; color: var(--text-primary); width: 250px; }

/* Tabela */
.vision-table-container { margin: 0; overflow-x: auto; padding-bottom: 10px; }
.vision-table { width: 100%; border-collapse: collapse; background: transparent; }
.vision-table th { background: rgba(255, 255, 255, 0.05); border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding: 18px 20px; font-weight: 600; font-size: 0.9rem; color: var(--text-secondary); text-align: left; }
.vision-table td { padding: 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.04); font-size: 0.95rem; vertical-align: middle; }
.vision-table tr:hover { background: rgba(255, 255, 255, 0.03); }
.vision-table tr:last-child td { border-bottom: none; }
.project-name-refined { font-weight: 600; color: var(--text-primary); font-size: 1rem; }
.value-cell-refined { font-weight: 600; }
.status-badge-refined { padding: 6px 12px; border-radius: 30px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
.status-badge-refined.status-pending { background: rgba(255, 193, 7, .2); color: #ffd666; }
.status-badge-refined.status-in_progress { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
.status-badge-refined.status-completed { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
.status-badge-refined.status-cancelled { background: rgba(239, 68, 68, 0.2); color: #f87171; }

/* Botões de Ação da Tabela */
.action-buttons-refined { display: flex; gap: 8px; align-items: center; }
.action-btn-refined { width: 36px; height: 36px; border-radius: 30px; border: 1px solid rgba(255, 255, 255, 0.1); color: var(--text-secondary); display: flex; align-items: center; justify-content: center; text-decoration: none; cursor: pointer; transition: all 0.3s ease; font-size: 0.9rem; background: rgba(255, 255, 255, 0.05); }
.action-btn-refined:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); }
.action-btn-edit:hover { background: var(--brand-purple); color: white; border-color: var(--brand-purple); }
.action-btn-delete:hover { background: #ef4444; color: white; border-color: #ef4444; }

/* Modal */
.vision-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(10px); z-index: 1000; align-items: center; justify-content: center; }
.vision-modal.active { display: flex; }
.vision-modal-content { background: rgba(30, 30, 30, 0.95); backdrop-filter: blur(30px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 24px; width: 90%; max-width: 500px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5); }
.vision-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 25px 30px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
.vision-modal-header h3 { margin: 0; color: var(--text-primary); font-size: 1.2rem; display: flex; align-items: center; gap: 8px; }
.vision-modal-close { background: none; border: none; color: var(--text-secondary); font-size: 1.2rem; cursor: pointer; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 16px; transition: all 0.3s ease; }
.vision-modal-close:hover { background: rgba(255, 255, 255, 0.1); color: var(--text-primary); }
.vision-modal-form { padding: 30px; }
.vision-modal-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px; }

/* CSS do Cursor */
body { cursor: default !important; }
a, button, label, select,
input[type="submit"], input[type="button"], input[type="reset"],
input[type="checkbox"], input[type="radio"],
[role="button"], [onclick] {
    cursor: pointer !important;
}
.vision-btn, .btn-primary, .btn-secondary,
.action-btn-refined, .add-service-btn,
.modal-close, .vision-modal-close,
.page-btn, .report-btn-highlight,
.selection-tag, .rate-item-remove,
.vision-modal-backdrop, [data-close-modal],
.quick-link-card, .filter-btn {
    cursor: pointer !important;
}
</style>

<script>
// Objeto para armazenar dados do projeto (para cálculo)
let projectData = {};

/**
 * Formata um valor numérico para a moeda selecionada.
 */
function formatCurrencyJS(value, currency) {
    if (isNaN(value)) value = 0;
    try {
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: currency }).format(value);
    } catch (e) {
        return currency + ' ' + value.toFixed(2);
    }
}

/**
 * Calcula o subtotal, imposto e total com base nos projetos SELECIONADOS (CHECKBOXES).
 */
function calculateProjectTotal() {
    let subtotal = 0;
    const currency = document.getElementById('currency').value;
    
    // Itera sobre os checkboxes marcados
    const checkedProjects = document.querySelectorAll('#projects-checkbox-list input[type="checkbox"]:checked');
    
    checkedProjects.forEach(checkbox => {
        const projectId = checkbox.value;
        if (projectData[projectId]) {
            subtotal += parseFloat(projectData[projectId].amount);
        }
    });
    
    const taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;
    const taxAmount = subtotal * (taxRate / 100);
    const totalAmount = subtotal + taxAmount;
    
    document.getElementById('subtotal_display').textContent = formatCurrencyJS(subtotal, currency);
    document.getElementById('tax_rate_display').textContent = taxRate;
    document.getElementById('tax_amount_display').textContent = formatCurrencyJS(taxAmount, currency);
    document.getElementById('total_amount_display').textContent = formatCurrencyJS(totalAmount, currency);
}

/**
 * Busca projetos não faturados quando um cliente é selecionado.
 */
async function fetchUnbilledProjects(clientId) {
    const projectsContainer = document.getElementById('projects-container');
    const projectCheckboxList = document.getElementById('projects-checkbox-list');
    
    // Reseta e esconde o campo de projetos
    projectCheckboxList.innerHTML = '<span class="text-muted" style="padding: 10px;">Buscando projetos...</span>';
    projectsContainer.style.display = 'block'; // Mantém visível para mostrar "Buscando..."
    projectData = {};
    calculateProjectTotal(); // Reseta os totais
    
    if (!clientId) {
        projectCheckboxList.innerHTML = '<span class="text-muted" style="padding: 10px;">Selecione um cliente para ver os projetos.</span>';
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('client_id', clientId);
        
        // Faz a chamada para a API no topo deste arquivo
        const response = await fetch('invoices.php?api=get_unbilled_projects', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error('Falha na resposta da rede.');
        }
        
        const data = await response.json();
        
        if (data.error) {
            // Lança o erro real vindo do PHP
            throw new Error(data.error); 
        }
        
        // Limpa a lista
        projectCheckboxList.innerHTML = '';
        
        if (data.projects && data.projects.length > 0) {
            // Popula a lista com checkboxes
            data.projects.forEach(project => {
                // Armazena dados no JS para cálculo
                projectData[project.id] = {
                    amount: project.total_amount,
                    currency: project.currency
                };
                
                // Cria o HTML do checkbox
                const itemHTML = `
                    <label class="project-checkbox-item">
                        <input type="checkbox" name="project_ids[]" value="${project.id}" data-amount="${project.total_amount}">
                        <span class="checkbox-title">${escapeHTML(project.title)}</span>
                        <span class="checkbox-amount">${formatCurrencyJS(project.total_amount, project.currency)}</span>
                    </label>
                `;
                projectCheckboxList.innerHTML += itemHTML;
            });
            
            // Adiciona o listener de 'change' a todos os novos checkboxes
            projectCheckboxList.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                checkbox.addEventListener('change', calculateProjectTotal);
            });
            
        } else {
            projectCheckboxList.innerHTML = '<span class="text-muted" style="padding: 10px;">Nenhum projeto concluído para faturar.</span>';
        }
        
        projectsContainer.style.display = 'block'; // Mostra o container
        
    } catch (error) {
        console.error(error);
        projectCheckboxList.innerHTML = `<span class="text-muted" style="padding: 10px; color: #ef4444;">Erro ao carregar projetos: ${escapeHTML(error.message)}</span>`;
        projectsContainer.style.display = 'block';
    }
}

// Função utilitária para evitar XSS
function escapeHTML(str) {
    if (typeof str !== 'string') return '';
    return str.replace(/[&<>"']/g, function(m) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[m];
    });
}


// --- Funções do Modal de Status ---

function openStatusModal(invoiceId, currentStatus) {
    document.getElementById('modal_invoice_id').value = invoiceId;
    document.getElementById('modal_status').value = currentStatus;
    document.getElementById('statusModal').classList.add('active');
    togglePaymentFields();
}

function closeStatusModal() {
    document.getElementById('statusModal').classList.remove('active');
}

function togglePaymentFields() {
    const status = document.getElementById('modal_status').value;
    const paymentFields = document.getElementById('payment_fields');
    
    if (status === 'paid') {
        paymentFields.style.display = 'block';
        // Preenche com a data de hoje se estiver vazio
        const paymentDateInput = document.getElementById('payment_date');
        if (!paymentDateInput.value) {
            paymentDateInput.value = new Date().toISOString().split('T')[0];
        }
    } else {
        paymentFields.style.display = 'none';
    }
}

// --- Event Listeners ---

document.addEventListener('DOMContentLoaded', function() {
    const clientSelect = document.getElementById('client_id');
    const taxInput = document.getElementById('tax_rate');
    const currencySelect = document.getElementById('currency');

    // Listener para carregar projetos
    clientSelect.addEventListener('change', function() {
        fetchUnbilledProjects(this.value);
        
        // Atualiza a moeda padrão da fatura com base no cliente
        const selectedCurrency = this.options[this.selectedIndex].dataset.currency;
        if (selectedCurrency) {
            currencySelect.value = selectedCurrency;
        }
        // Recalcula totais (pode zerar se a moeda mudar)
        calculateProjectTotal();
    });

    // Listeners para recalcular totais
    taxInput.addEventListener('input', calculateProjectTotal);
    currencySelect.addEventListener('change', calculateProjectTotal);

    // Fechar modal de status
    document.getElementById('statusModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeStatusModal();
        }
    });
    
    document.addEventListener("keydown", function(e) {
        if (e.key === "Escape" && document.getElementById('statusModal').classList.contains('active')) {
            closeStatusModal();
        }
    });

    // Alerta de sucesso
    const successAlert = document.getElementById('success-alert');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.opacity = '0';
            setTimeout(() => { successAlert.remove(); }, 1000); 
        }, 5000);
    }
});
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>