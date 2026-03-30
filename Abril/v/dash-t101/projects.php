<?php
/**
 * projects.php - Versão Abril 2026
 * Funcionalidades: Múltiplas Tarefas, Monolíngue, Cadastro Rápido de Cliente, Auto-Moeda.
 * NOVO: Modal de Despesas de Interpretação (Viagem, Hospedagem, Alimentação, Equipamento)
 *       com distinção entre despesas do Cliente (faturamento) e do Intérprete (controle interno).
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';
require_once __DIR__ . '/../config/dash_functions.php';

if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';
$edit_project = null;
$existing_jobs = [];
$existing_expenses = [];

// Listas Padrão
$default_services = ['Tradução', 'Revisão', 'Interpretação', 'Localização', 'MTPE', 'Legendagem'];
$default_languages = ['Português', 'Inglês', 'Espanhol', 'Francês', 'Alemão', 'Italiano', 'Chinês'];
$default_units = ['Palavra', 'Hora', 'Lauda', 'Minuto', 'Diária', 'Projeto'];
$default_currencies = ['BRL', 'USD', 'EUR', 'GBP'];

$expense_types = [
    'travel' => 'Viagem',
    'accommodation' => 'Hospedagem',
    'food' => 'Alimentação',
    'equipment' => 'Equipamento'
];

// --- PROCESSAMENTO POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // --- AJAX: CRIAÇÃO RÁPIDA DE CLIENTE ---
    if ($action === 'client_create_quick') {
        header('Content-Type: application/json');
        try {
            $name = trim($_POST['name'] ?? '');
            if (empty($name)) throw new Exception("Nome da empresa obrigatório.");
            
            $curr = $_POST['currency'] ?? 'BRL';

            $stmt = $pdo->prepare("INSERT INTO dash_clients (user_id, company, currency) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $name, $curr]);
            $new_id = $pdo->lastInsertId();
            
            echo json_encode(['success' => true, 'id' => $new_id, 'name' => $name, 'currency' => $curr]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // --- SALVAR PROJETO ---
    if ($action === 'add' || $action === 'edit') {
        $pdo->beginTransaction();
        try {
            // 1. Dados do Projeto
            $title = $_POST['title'];
            $client_id = !empty($_POST['client_id']) ? $_POST['client_id'] : null;
            $po_number = $_POST['po_number'] ?? null;
            $start_date = $_POST['start_date'];
            $deadline = $_POST['deadline'];
            $status = $_POST['status'];
            $currency = $_POST['currency'] ?? 'BRL';
            $description = $_POST['description'] ?? '';
            
            $calculated_total = 0;
            
            if (!isset($_POST['job_service']) || count($_POST['job_service']) == 0) {
                throw new Exception("O projeto deve ter pelo menos uma tarefa.");
            }

            // Calcular total das tarefas
            for ($i = 0; $i < count($_POST['job_service']); $i++) {
                $q = (float)str_replace(',', '.', $_POST['job_quantity'][$i]);
                $p = (float)str_replace(',', '.', $_POST['job_price'][$i]);
                $calculated_total += ($q * $p);
            }

            // Somar despesas de interpretação que são do CLIENTE (faturamento)
            $client_expenses_total = 0;
            if (!empty($_POST['expense_amount'])) {
                for ($i = 0; $i < count($_POST['expense_amount']); $i++) {
                    $exp_amount = (float)str_replace(',', '.', $_POST['expense_amount'][$i]);
                    $exp_paid_by = $_POST['expense_paid_by'][$i] ?? 'client';
                    if ($exp_paid_by === 'client') {
                        $client_expenses_total += $exp_amount;
                    }
                }
            }
            $calculated_total += $client_expenses_total;

            $project_id = null;

            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO dash_projects (user_id, title, client_id, po_number, start_date, deadline, status, currency, total_amount, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $title, $client_id, $po_number, $start_date, $deadline, $status, $currency, $calculated_total, $description]);
                $project_id = $pdo->lastInsertId();
                $msg_success = "Projeto criado com sucesso!";
            } elseif ($action === 'edit') {
                $project_id = $_POST['project_id'];
                $stmt = $pdo->prepare("UPDATE dash_projects SET title=?, client_id=?, po_number=?, start_date=?, deadline=?, status=?, currency=?, total_amount=?, description=? WHERE id=? AND user_id=?");
                $stmt->execute([$title, $client_id, $po_number, $start_date, $deadline, $status, $currency, $calculated_total, $description, $project_id, $user_id]);
                
                // Limpa tarefas antigas
                $stmt_del = $pdo->prepare("DELETE FROM dash_jobs WHERE project_id = ? AND user_id = ?");
                $stmt_del->execute([$project_id, $user_id]);
                
                // Limpa despesas antigas de interpretação
                $stmt_del_exp = $pdo->prepare("DELETE FROM dash_interpretation_expenses WHERE project_id = ? AND user_id = ?");
                $stmt_del_exp->execute([$project_id, $user_id]);
                
                $msg_success = "Projeto atualizado com sucesso!";
            }

            // 3. Salvar Tarefas
            $stmt_job = $pdo->prepare("INSERT INTO dash_jobs (user_id, project_id, service, lang_from, lang_to, quantity, unit, price_per_unit, total_cost, deadline) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            for ($i = 0; $i < count($_POST['job_service']); $i++) {
                $service = $_POST['job_service'][$i];
                
                // Lógica Monolíngue
                $is_mono = isset($_POST['job_is_monolingual'][$i]) && $_POST['job_is_monolingual'][$i] == '1';
                $l_from = $is_mono ? null : ($_POST['job_lang_from'][$i] ?? null);
                
                $l_to   = $_POST['job_lang_to'][$i] ?? null;
                $qty    = (float)str_replace(',', '.', $_POST['job_quantity'][$i]);
                $unit   = $_POST['job_unit'][$i];
                $price  = (float)str_replace(',', '.', $_POST['job_price'][$i]);
                $job_deadline = !empty($_POST['job_deadline'][$i]) ? $_POST['job_deadline'][$i] : $deadline;
                
                $row_total = $qty * $price;

                if (!empty($service) && $qty > 0) {
                    $stmt_job->execute([$user_id, $project_id, $service, $l_from, $l_to, $qty, $unit, $price, $row_total, $job_deadline]);
                }
            }

            // 4. Salvar Despesas de Interpretação
            if (!empty($_POST['expense_type'])) {
                $stmt_exp = $pdo->prepare("INSERT INTO dash_interpretation_expenses (user_id, project_id, expense_type, description, amount, paid_by) VALUES (?, ?, ?, ?, ?, ?)");
                
                for ($i = 0; $i < count($_POST['expense_type']); $i++) {
                    $exp_type = $_POST['expense_type'][$i];
                    $exp_desc = trim($_POST['expense_description'][$i] ?? '');
                    $exp_amount = (float)str_replace(',', '.', $_POST['expense_amount'][$i]);
                    $exp_paid_by = $_POST['expense_paid_by'][$i] ?? 'client';
                    
                    if ($exp_amount > 0) {
                        $stmt_exp->execute([$user_id, $project_id, $exp_type, $exp_desc, $exp_amount, $exp_paid_by]);
                    }
                }
            }

            $pdo->commit();
            $_SESSION['temp_message'] = $msg_success;
            header("Location: projects_list.php");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erro ao salvar: " . $e->getMessage();
        }
    }
}

// --- Carregar Dados para Edição ---
if (isset($_GET['edit'])) {
    $pid = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM dash_projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$pid, $user_id]);
    $edit_project = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($edit_project) {
        $stmt_jobs = $pdo->prepare("SELECT * FROM dash_jobs WHERE project_id = ? ORDER BY id ASC");
        $stmt_jobs->execute([$pid]);
        $existing_jobs = $stmt_jobs->fetchAll(PDO::FETCH_ASSOC);
        
        // Carregar despesas de interpretação existentes
        $stmt_exp = $pdo->prepare("SELECT * FROM dash_interpretation_expenses WHERE project_id = ? AND user_id = ? ORDER BY id ASC");
        $stmt_exp->execute([$pid, $user_id]);
        $existing_expenses = $stmt_exp->fetchAll(PDO::FETCH_ASSOC);
    } else { header("Location: projects.php"); exit; }
}

// Carregar Clientes
$stmt_cli = $pdo->prepare("SELECT id, company, currency FROM dash_clients WHERE user_id = ? ORDER BY company ASC");
$stmt_cli->execute([$user_id]);
$clients = $stmt_cli->fetchAll(PDO::FETCH_ASSOC);

$page_title = $edit_project ? 'Editar Projeto' : 'Novo Projeto';
include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<style>
    .main-content { padding-bottom: 100px; }
    
    .video-card { background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.05)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; margin-bottom: 20px; }
    .video-card > h2 { margin: 0; padding: 25px 30px 20px; font-size: 1.3rem; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid rgba(255, 255, 255, 0.06); display: flex; gap: 10px; align-items: center; }

    .profile-header-card { display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--brand-purple), #4a148c); padding: 20px; }
    .header-icon-container { background: rgba(255,255,255,0.1); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .header-text-container { margin-left: 20px; }
    .header-text-container h2 { margin: 0; font-size: 1.5rem; color: #fff; }
    
    .vision-form { padding: 30px; }
    .form-row-flex { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px; }
    .form-group { display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 200px; }
    .form-group label { font-weight: 600; color: var(--text-secondary); font-size: 0.9rem; }
    .vision-input, .vision-select { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 12px 16px; color: var(--text-primary); font-size: 0.95rem; width: 100%; box-sizing: border-box; }
    
    .input-with-btn { display: flex; gap: 5px; width: 100%; }
    .btn-add-mini { 
        width: 42px; height: 42px; flex-shrink: 0;
        background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.1); 
        color: #fff; border-radius: 10px; cursor: pointer; 
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem; transition: 0.2s;
    }
    .btn-add-mini:hover { background: var(--brand-purple); border-color: var(--brand-purple); }

    .tasks-section { margin-top: 30px; }
    .tasks-container { background: rgba(0,0,0,0.1); border-radius: 16px; padding: 20px; border: 1px solid rgba(255,255,255,0.05); }
    
    .task-row { 
        display: flex; 
        flex-wrap: wrap; 
        gap: 15px; 
        align-items: flex-end;
        padding: 20px; 
        background: rgba(255,255,255,0.03); 
        border-radius: 12px; 
        border: 1px solid rgba(255,255,255,0.05); 
        margin-bottom: 15px;
        animation: fadeIn 0.3s;
    }
    
    .task-row .vision-input, .task-row .vision-select {
        height: 42px;
        padding: 0 12px;
        font-size: 0.9rem;
        display: flex; align-items: center;
    }

    .task-group { flex: 1; min-width: 140px; display: flex; flex-direction: column; gap: 5px; }
    .task-group-large { flex: 2; min-width: 220px; }
    .task-group-small { flex: 0.8; min-width: 100px; }
    
    .task-label { font-size: 0.8rem; color: #aaa; font-weight: 500; }

    .mono-check-wrapper { margin-top: 8px; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #aaa; cursor: pointer; }
    .mono-check-wrapper input { accent-color: var(--brand-purple); width: 16px; height: 16px; cursor: pointer; }

    .vision-btn { background: var(--brand-purple); color: #fff; border: 0; border-radius: 20px; padding: 12px 24px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
    .vision-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
    .vision-btn-secondary { background: rgba(255,255,255,0.1); color: #fff; }
    .vision-btn-secondary:hover { background: rgba(255,255,255,0.2); }
    
    .btn-remove-task {
        width: 42px; height: 42px;
        background: rgba(255, 59, 48, 0.1); border: 1px solid rgba(255, 59, 48, 0.3);
        color: #ff3b30; border-radius: 10px; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: 0.2s; flex-shrink: 0;
    }
    .btn-remove-task:hover { background: #ff3b30; color: #fff; }

    .project-total-area { display: flex; justify-content: flex-end; align-items: center; margin-top: 20px; font-size: 1.2rem; color: #fff; background: rgba(124, 77, 255, 0.1); padding: 15px 25px; border-radius: 12px; }
    .total-value { font-weight: 800; font-size: 1.5rem; margin-left: 10px; color: var(--brand-purple-light); }

    @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

    /* Modal Base */
    .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; display: none; align-items: center; justify-content: center; backdrop-filter: blur(5px); }
    .modal.active { display: flex; }
    .modal-box { background: #1e1e2d; padding: 25px; border-radius: 16px; width: 100%; max-width: 400px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
    .modal-box h3 { margin-top: 0; color: #fff; margin-bottom: 20px; }
    .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }

    /* ============================================== */
    /* MODAL DE DESPESAS DE INTERPRETACAO             */
    /* ============================================== */
    .modal-box-large { max-width: 750px; max-height: 85vh; overflow-y: auto; }

    .expenses-table-wrapper { overflow-x: auto; margin: 15px 0; }
    
    .expenses-table { width: 100%; border-collapse: collapse; }
    .expenses-table th { 
        text-align: left; padding: 10px 8px; font-size: 0.8rem; color: #aaa; 
        font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .expenses-table td { padding: 8px; vertical-align: middle; }
    .expenses-table .vision-input, .expenses-table .vision-select { 
        height: 38px; padding: 0 10px; font-size: 0.85rem; min-width: 100px;
    }

    .expense-row { animation: fadeIn 0.3s; }
    .expense-row:hover { background: rgba(255,255,255,0.02); }

    .btn-remove-expense {
        width: 34px; height: 34px;
        background: rgba(255, 59, 48, 0.1); border: 1px solid rgba(255, 59, 48, 0.3);
        color: #ff3b30; border-radius: 8px; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: 0.2s; flex-shrink: 0; font-size: 0.8rem;
    }
    .btn-remove-expense:hover { background: #ff3b30; color: #fff; }

    /* Toggle Cliente/Intérprete */
    .paid-by-toggle { display: flex; border-radius: 8px; overflow: hidden; border: 1px solid rgba(255,255,255,0.15); }
    .paid-by-toggle label { 
        padding: 6px 12px; font-size: 0.78rem; cursor: pointer; 
        color: #aaa; background: rgba(0,0,0,0.2); transition: 0.2s;
        display: flex; align-items: center; white-space: nowrap;
    }
    .paid-by-toggle input { display: none; }
    .paid-by-toggle input:checked + label { 
        color: #fff; font-weight: 600;
    }
    .paid-by-toggle input[value="client"]:checked + label { 
        background: rgba(76, 175, 80, 0.3); color: #81c784;
    }
    .paid-by-toggle input[value="interpreter"]:checked + label { 
        background: rgba(255, 152, 0, 0.3); color: #ffb74d;
    }

    .expenses-summary { 
        background: rgba(255,255,255,0.03); border-radius: 10px; 
        padding: 12px 16px; margin-top: 10px; font-size: 0.9rem; 
    }
    .expenses-summary-row { 
        display: flex; justify-content: space-between; padding: 4px 0; color: #ccc;
    }
    .expenses-summary-row.total { 
        border-top: 1px solid rgba(255,255,255,0.1); padding-top: 8px; margin-top: 4px;
        font-weight: 700; color: #fff;
    }
    .expenses-summary-row .label-client { color: #81c784; }
    .expenses-summary-row .label-interpreter { color: #ffb74d; }

    /* Botão Despesas de Interpretação - full width abaixo dos campos */
    .btn-interpretation-expenses {
        display: none;
        width: 100%; flex-basis: 100%;
        margin-top: 2px; padding: 10px 16px; font-size: 0.85rem;
        background: rgba(255, 152, 0, 0.12); border: 1px dashed rgba(255, 152, 0, 0.35);
        color: #ffb74d; border-radius: 10px; cursor: pointer; transition: 0.2s;
        align-items: center; justify-content: center; gap: 8px; white-space: nowrap;
        text-align: center;
    }
    .btn-interpretation-expenses.visible { display: flex; }
    .btn-interpretation-expenses:hover { background: rgba(255, 152, 0, 0.25); border-style: solid; }
    .btn-interpretation-expenses .expense-badge {
        background: #ff9800; color: #000; font-size: 0.7rem; font-weight: 700;
        padding: 1px 6px; border-radius: 10px; margin-left: 4px;
    }

    /* Seção de Despesas (dentro do form, hidden fields) */
    #expensesHiddenContainer { display: none; }
</style>

<div class="main-content">

    <div class="video-card profile-header-card">
        <div class="header-icon-container"><i class="fas fa-folder-open" style="font-size: 1.8rem; color: #fff;"></i></div>
        <div class="header-text-container">
            <h2><?php echo $edit_project ? 'Editar Projeto' : 'Novo Projeto'; ?></h2>
            <p>Gerencie as informações e tarefas do projeto.</p>
        </div>
    </div>

    <div class="report-nav-buttons" style="margin-bottom: 20px; display: flex; gap: 10px;">
        <a href="index.php" class="vision-btn vision-btn-secondary"><i class="fas fa-home"></i> Voltar</a>
        <a href="projects_list.php" class="vision-btn vision-btn-secondary"><i class="fas fa-list-ul"></i> Ver Projetos</a>
    </div>

    <?php if ($error): ?><div class="alert-error" style="background:#ef4444;color:#fff;padding:15px;border-radius:10px;margin-bottom:20px;"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div><?php endif; ?>

    <div class="video-card">
        <h2><i class="fas fa-info-circle"></i> Dados do Projeto</h2>
        <form method="POST" action="projects.php" class="vision-form" id="projectForm">
            <input type="hidden" name="action" value="<?php echo $edit_project ? 'edit' : 'add'; ?>">
            <?php if ($edit_project): ?>
                <input type="hidden" name="project_id" value="<?php echo $edit_project['id']; ?>">
            <?php endif; ?>

            <div class="form-row-flex">
                <div class="form-group" style="flex: 2;">
                    <label>Nome do Projeto</label>
                    <input type="text" name="title" class="vision-input" required value="<?php echo htmlspecialchars($edit_project['title'] ?? ''); ?>" placeholder="Ex: Tradução Manual Técnico">
                </div>
                <div class="form-group">
                    <label>Cliente</label>
                    <div class="input-with-btn">
                        <select name="client_id" id="clientSelect" class="vision-select">
                            <option value="" data-currency="BRL">Selecione...</option>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?php echo $c['id']; ?>" data-currency="<?php echo $c['currency']; ?>" <?php echo ($edit_project && $edit_project['client_id'] == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['company']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn-add-mini" id="btnAddClient" title="Novo Cliente">+</button>
                    </div>
                </div>
                <div class="form-group">
                    <label>PO Number</label>
                    <input type="text" name="po_number" class="vision-input" value="<?php echo htmlspecialchars($edit_project['po_number'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row-flex">
                <div class="form-group">
                    <label>Início</label>
                    <input type="date" name="start_date" class="vision-input" required value="<?php echo $edit_project ? date('Y-m-d', strtotime($edit_project['start_date'])) : date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Prazo (Deadline)</label>
                    <input type="date" name="deadline" class="vision-input" required value="<?php echo $edit_project ? date('Y-m-d', strtotime($edit_project['deadline'])) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="vision-select">
                        <option value="pending" <?php echo ($edit_project && $edit_project['status'] == 'pending') ? 'selected' : ''; ?>>Pendente</option>
                        <option value="in_progress" <?php echo ($edit_project && $edit_project['status'] == 'in_progress') ? 'selected' : ''; ?> <?php echo !$edit_project ? 'selected' : ''; ?>>Em Andamento</option>
                        <option value="completed" <?php echo ($edit_project && $edit_project['status'] == 'completed') ? 'selected' : ''; ?>>Concluído</option>
                        <option value="cancelled" <?php echo ($edit_project && $edit_project['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Moeda</label>
                    <select name="currency" id="projectCurrency" class="vision-select" onchange="updateTotalDisplay()">
                        <?php foreach ($default_currencies as $curr): ?>
                            <option value="<?php echo $curr; ?>" <?php echo ($edit_project && $edit_project['currency'] == $curr) ? 'selected' : ''; ?>><?php echo $curr; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Descrição</label>
                <textarea name="description" class="vision-input" rows="2"><?php echo htmlspecialchars($edit_project['description'] ?? ''); ?></textarea>
            </div>

            <div class="tasks-section">
                <h3 style="margin: 30px 0 15px 0; color:#fff; font-size: 1.1rem;"><i class="fas fa-tasks"></i> Tarefas do Projeto</h3>
                
                <div id="jobs_container">
                    <?php if (!empty($existing_jobs)): ?>
                        <?php foreach ($existing_jobs as $job): 
                            $is_mono = empty($job['lang_from']);
                            $is_interpretation = ($job['service'] === 'Interpretação');
                        ?>
                        <div class="task-row">
                            <div class="task-group task-group-large">
                                <label class="task-label">Serviço</label>
                                <div class="input-with-btn">
                                    <select name="job_service[]" class="vision-select service-select" required>
                                        <?php foreach ($default_services as $s): ?>
                                            <option value="<?php echo $s; ?>" <?php echo ($job['service'] == $s) ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                        <?php endforeach; ?>
                                        <?php if (!in_array($job['service'], $default_services)): ?>
                                            <option value="<?php echo htmlspecialchars($job['service']); ?>" selected><?php echo htmlspecialchars($job['service']); ?></option>
                                        <?php endif; ?>
                                    </select>
                                    <button type="button" class="btn-add-mini btn-add-service">+</button>
                                </div>
                                <label class="mono-check-wrapper">
                                    <input type="checkbox" class="mono-check" name="job_is_monolingual_chk[]" <?php echo $is_mono ? 'checked' : ''; ?>>
                                    <input type="hidden" name="job_is_monolingual[]" class="mono-val" value="<?php echo $is_mono ? '1' : '0'; ?>">
                                    <span>Monolíngue</span>
                                </label>
                            </div>

                            <div class="task-group group-from" style="<?php echo $is_mono ? 'display:none' : ''; ?>">
                                <label class="task-label">De (Origem)</label>
                                <select name="job_lang_from[]" class="vision-select">
                                    <option value="">-</option>
                                    <?php foreach ($default_languages as $l): ?>
                                        <option value="<?php echo $l; ?>" <?php echo ($job['lang_from'] == $l) ? 'selected' : ''; ?>><?php echo $l; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="task-group group-to">
                                <label class="task-label label-to"><?php echo $is_mono ? 'Idioma' : 'Para (Destino)'; ?></label>
                                <select name="job_lang_to[]" class="vision-select">
                                    <option value="">-</option>
                                    <?php foreach ($default_languages as $l): ?>
                                        <option value="<?php echo $l; ?>" <?php echo ($job['lang_to'] == $l) ? 'selected' : ''; ?>><?php echo $l; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="task-group task-group-small">
                                <label class="task-label">Qtd</label>
                                <input type="number" name="job_quantity[]" class="vision-input job-qty" placeholder="Qtd" step="0.01" value="<?php echo $job['quantity']; ?>" oninput="calculateRow(this)">
                            </div>

                            <div class="task-group">
                                <label class="task-label">Unidade</label>
                                <div class="input-with-btn">
                                    <select name="job_unit[]" class="vision-select unit-select">
                                        <?php foreach ($default_units as $u): ?>
                                            <option value="<?php echo $u; ?>" <?php echo ($job['unit'] == $u) ? 'selected' : ''; ?>><?php echo $u; ?></option>
                                        <?php endforeach; ?>
                                        <?php if (!in_array($job['unit'], $default_units)): ?>
                                            <option value="<?php echo htmlspecialchars($job['unit']); ?>" selected><?php echo htmlspecialchars($job['unit']); ?></option>
                                        <?php endif; ?>
                                    </select>
                                    <button type="button" class="btn-add-mini btn-add-unit">+</button>
                                </div>
                            </div>

                            <div class="task-group task-group-small">
                                <label class="task-label">Preço Unit.</label>
                                <input type="text" name="job_price[]" class="vision-input job-price" placeholder="0.00" value="<?php echo number_format($job['price_per_unit'], 2, ',', '.'); ?>" oninput="calculateRow(this)">
                            </div>
                            <button type="button" class="btn-remove-task" title="Remover"><i class="fas fa-trash"></i></button>
                            <button type="button" class="btn-interpretation-expenses <?php echo $is_interpretation ? 'visible' : ''; ?>" title="Gerenciar despesas de interpretação">
                                <i class="fas fa-receipt"></i> Incluir despesas da interpretação
                                <span class="expense-badge" style="display:none;">0</span>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <button type="button" class="vision-btn vision-btn-secondary" id="btnAddJob" style="margin-top: 10px;">
                    <i class="fas fa-plus-circle"></i> Adicionar Tarefa
                </button>

                <div class="project-total-area">
                    <span>Total do Projeto:</span>
                    <span class="total-value" id="displayTotal">0,00</span>
                    <span style="margin-left:5px; font-size:1rem; color:#aaa;" id="displayCurrency">BRL</span>
                </div>
            </div>

            <!-- Hidden container para os campos de despesas (enviados com o form) -->
            <div id="expensesHiddenContainer"></div>

            <div class="form-actions" style="margin-top: 30px; text-align: right;">
                <button type="submit" class="vision-btn"><i class="fas fa-save"></i> Salvar Projeto</button>
            </div>
        </form>
    </div>
</div>

<!-- Template de Tarefa -->
<template id="job_row_template">
    <div class="task-row">
        <div class="task-group task-group-large">
            <label class="task-label">Serviço</label>
            <div class="input-with-btn">
                <select name="job_service[]" class="vision-select service-select" required>
                    <option value="">Serviço...</option>
                    <?php foreach ($default_services as $s): ?><option value="<?php echo $s; ?>"><?php echo $s; ?></option><?php endforeach; ?>
                </select>
                <button type="button" class="btn-add-mini btn-add-service">+</button>
            </div>
            <label class="mono-check-wrapper">
                <input type="checkbox" class="mono-check">
                <input type="hidden" name="job_is_monolingual[]" class="mono-val" value="0">
                <span>Monolíngue</span>
            </label>
        </div>
        <div class="task-group group-from">
            <label class="task-label">De</label>
            <select name="job_lang_from[]" class="vision-select">
                <option value="">Origem</option>
                <?php foreach ($default_languages as $l): ?><option value="<?php echo $l; ?>"><?php echo $l; ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="task-group group-to">
            <label class="task-label label-to">Para</label>
            <select name="job_lang_to[]" class="vision-select">
                <option value="">Destino</option>
                <?php foreach ($default_languages as $l): ?><option value="<?php echo $l; ?>"><?php echo $l; ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="task-group task-group-small">
            <label class="task-label">Qtd</label>
            <input type="number" name="job_quantity[]" class="vision-input job-qty" placeholder="Qtd" step="0.01" oninput="calculateRow(this)">
        </div>
        <div class="task-group">
            <label class="task-label">Unidade</label>
            <div class="input-with-btn">
                <select name="job_unit[]" class="vision-select unit-select">
                    <?php foreach ($default_units as $u): ?><option value="<?php echo $u; ?>"><?php echo $u; ?></option><?php endforeach; ?>
                </select>
                <button type="button" class="btn-add-mini btn-add-unit">+</button>
            </div>
        </div>
        <div class="task-group task-group-small">
            <label class="task-label">Preço Unit.</label>
            <input type="text" name="job_price[]" class="vision-input job-price" placeholder="0.00" oninput="calculateRow(this)">
        </div>
        <button type="button" class="btn-remove-task" title="Remover"><i class="fas fa-trash"></i></button>
        <button type="button" class="btn-interpretation-expenses" title="Gerenciar despesas de interpretação">
            <i class="fas fa-receipt"></i> Incluir despesas da interpretação
            <span class="expense-badge" style="display:none;">0</span>
        </button>
    </div>
</template>

<!-- Modal: Custom Item (Serviço / Unidade) -->
<div id="customItemModal" class="modal">
    <div class="modal-box">
        <h3 id="modalTitle">Adicionar Novo</h3>
        <div class="form-group">
            <label>Nome do item</label>
            <input type="text" id="newItemName" class="vision-input">
        </div>
        <div class="modal-actions">
            <button type="button" class="vision-btn vision-btn-secondary" onclick="closeCustomModal()">Cancelar</button>
            <button type="button" class="vision-btn" id="btnSaveCustomItem">Adicionar</button>
        </div>
    </div>
</div>

<!-- Modal: Novo Cliente -->
<div id="newClientModal" class="modal">
    <div class="modal-box">
        <h3>Novo Cliente</h3>
        <div class="form-group">
            <label>Nome da Empresa</label>
            <input type="text" id="newClientName" class="vision-input">
        </div>
        <div class="form-group">
            <label>Moeda Padrão</label>
            <select id="newClientCurrency" class="vision-select">
                <?php foreach ($default_currencies as $cur): ?>
                    <option value="<?php echo $cur; ?>"><?php echo $cur; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="modal-actions">
            <button type="button" class="vision-btn vision-btn-secondary" onclick="closeClientModal()">Cancelar</button>
            <button type="button" class="vision-btn" id="btnSaveNewClient">Salvar Cliente</button>
        </div>
    </div>
</div>

<!-- ======================================================= -->
<!-- MODAL: DESPESAS DE INTERPRETACAO                        -->
<!-- ======================================================= -->
<div id="interpretationExpensesModal" class="modal">
    <div class="modal-box modal-box-large">
        <h3><i class="fas fa-receipt" style="color:#ffb74d;"></i> Despesas de Interpretação</h3>
        <p style="color:#aaa; font-size:0.85rem; margin-top:-10px; margin-bottom:15px;">
            Despesas do <strong style="color:#81c784;">Cliente</strong> são somadas ao faturamento. 
            Despesas do <strong style="color:#ffb74d;">Intérprete</strong> são apenas controle interno.
        </p>

        <div class="expenses-table-wrapper">
            <table class="expenses-table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Descrição</th>
                        <th>Valor</th>
                        <th>Responsável</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="expensesTableBody">
                    <!-- Linhas dinâmicas -->
                </tbody>
            </table>
        </div>

        <button type="button" class="vision-btn vision-btn-secondary" id="btnAddExpense" style="margin-top:5px;">
            <i class="fas fa-plus-circle"></i> Adicionar Despesa
        </button>

        <div class="expenses-summary" id="expensesSummary">
            <div class="expenses-summary-row">
                <span class="label-client">Cliente (faturamento):</span>
                <span id="summaryClientTotal">0,00</span>
            </div>
            <div class="expenses-summary-row">
                <span class="label-interpreter">Intérprete (controle):</span>
                <span id="summaryInterpreterTotal">0,00</span>
            </div>
            <div class="expenses-summary-row total">
                <span>Total Despesas:</span>
                <span id="summaryExpensesTotal">0,00</span>
            </div>
        </div>

        <div class="modal-actions">
            <button type="button" class="vision-btn vision-btn-secondary" onclick="closeExpensesModal()">Cancelar</button>
            <button type="button" class="vision-btn" id="btnSaveExpenses"><i class="fas fa-check"></i> Confirmar Despesas</button>
        </div>
    </div>
</div>

<script>
// =============================================
// DADOS DE DESPESAS EXISTENTES (para edição)
// =============================================
const existingExpenses = <?php echo json_encode($existing_expenses); ?>;

// Armazenamento global das despesas
let interpretationExpenses = [];
let currentExpenseTaskIndex = null;

// Mapa de tipos de despesa
const expenseTypeLabels = {
    'travel': 'Viagem',
    'accommodation': 'Hospedagem',
    'food': 'Alimentação',
    'equipment': 'Equipamento'
};

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('jobs_container');
    const template = document.getElementById('job_row_template');
    const btnAdd = document.getElementById('btnAddJob');

    const clientSelect = document.getElementById('clientSelect');
    const projectCurrency = document.getElementById('projectCurrency');
    const displayCurrency = document.getElementById('displayCurrency');

    // Carregar despesas existentes
    if (existingExpenses && existingExpenses.length > 0) {
        interpretationExpenses = existingExpenses.map(e => ({
            type: e.expense_type,
            description: e.description || '',
            amount: parseFloat(e.amount) || 0,
            paid_by: e.paid_by || 'client'
        }));
    }

    // Moeda automática do Cliente
    if (clientSelect) {
        clientSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const currency = selectedOption.getAttribute('data-currency');
            if (currency && projectCurrency) {
                projectCurrency.value = currency;
                if(displayCurrency) displayCurrency.textContent = currency;
            } else if (projectCurrency) {
                projectCurrency.value = 'BRL';
                if(displayCurrency) displayCurrency.textContent = 'BRL';
            }
        });
    }

    // Inicializar
    if (container.children.length === 0) {
        addJobRow();
    }
    updateAllInterpretationButtons();
    calculateTotal();

    function addJobRow() {
        const clone = template.content.cloneNode(true);
        container.appendChild(clone);
    }

    btnAdd.addEventListener('click', () => addJobRow());

    // Delegation de cliques
    container.addEventListener('click', (e) => {
        if (e.target.closest('.btn-remove-task')) {
            const row = e.target.closest('.task-row');
            if (container.querySelectorAll('.task-row').length > 1) {
                row.remove();
            } else {
                row.querySelectorAll('input').forEach(i => i.value = '');
                row.querySelectorAll('.mono-check').forEach(c => c.checked = false);
            }
            calculateTotal();
        }
        if (e.target.closest('.btn-add-service')) {
            openCustomModal('Serviço', e.target.closest('.input-with-btn').querySelector('select'));
        }
        if (e.target.closest('.btn-add-unit')) {
            openCustomModal('Unidade', e.target.closest('.input-with-btn').querySelector('select'));
        }
        // Botão Despesas de Interpretação
        if (e.target.closest('.btn-interpretation-expenses')) {
            const row = e.target.closest('.task-row');
            const rows = Array.from(container.querySelectorAll('.task-row'));
            currentExpenseTaskIndex = rows.indexOf(row);
            openExpensesModal();
        }
    });

    // Lógica Monolíngue + detecção de Interpretação
    container.addEventListener('change', (e) => {
        if (e.target.classList.contains('mono-check')) {
            const row = e.target.closest('.task-row');
            const valInput = row.querySelector('.mono-val');
            valInput.value = e.target.checked ? '1' : '0';
            
            const groupFrom = row.querySelector('.group-from');
            const labelTo = row.querySelector('.label-to');
            
            if (e.target.checked) {
                groupFrom.style.display = 'none';
                labelTo.textContent = 'Idioma';
            } else {
                groupFrom.style.display = 'flex';
                labelTo.textContent = 'Para';
            }
        }
        
        // Quando muda o serviço, mostrar/ocultar botão de despesas + auto-selecionar Diária
        if (e.target.classList.contains('service-select')) {
            const row = e.target.closest('.task-row');
            const btn = row.querySelector('.btn-interpretation-expenses');
            const unitSelect = row.querySelector('.unit-select');
            if (e.target.value === 'Interpretação') {
                btn.classList.add('visible');
                // Auto-selecionar "Diária" na unidade
                if (unitSelect) {
                    const diariaOpt = Array.from(unitSelect.options).find(o => o.value === 'Diária');
                    if (diariaOpt) unitSelect.value = 'Diária';
                }
            } else {
                btn.classList.remove('visible');
            }
        }
    });

    // --- MODAL NOVO CLIENTE ---
    const clientModal = document.getElementById('newClientModal');
    const btnAddClient = document.getElementById('btnAddClient');
    const btnSaveClient = document.getElementById('btnSaveNewClient');

    if (btnAddClient) {
        btnAddClient.addEventListener('click', () => {
            clientModal.classList.add('active');
            document.getElementById('newClientName').focus();
        });
    }

    window.closeClientModal = function() {
        clientModal.classList.remove('active');
        document.getElementById('newClientName').value = '';
    };

    if (btnSaveClient) {
        btnSaveClient.addEventListener('click', () => {
            const name = document.getElementById('newClientName').value.trim();
            const curr = document.getElementById('newClientCurrency').value;
            
            if (!name) return alert('Digite o nome da empresa');

            const fd = new FormData();
            fd.append('action', 'client_create_quick');
            fd.append('name', name);
            fd.append('currency', curr);

            fetch('projects.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const opt = new Option(data.name, data.id, true, true);
                        opt.setAttribute('data-currency', data.currency);
                        clientSelect.add(opt);
                        projectCurrency.value = data.currency;
                        displayCurrency.textContent = data.currency;
                        closeClientModal();
                    } else {
                        alert('Erro: ' + data.error);
                    }
                })
                .catch(e => alert('Erro de conexão'));
        });
    }

    // --- MODAL CUSTOM (Serviço / Unidade) ---
    const customModal = document.getElementById('customItemModal');
    const customTitle = document.getElementById('modalTitle');
    const customInput = document.getElementById('newItemName');
    const customSaveBtn = document.getElementById('btnSaveCustomItem');
    let currentSelectTarget = null;

    window.openCustomModal = function(type, selectElement) {
        customTitle.textContent = 'Adicionar ' + type;
        customInput.value = '';
        currentSelectTarget = selectElement;
        customModal.classList.add('active');
        customInput.focus();
    };

    window.closeCustomModal = function() {
        customModal.classList.remove('active');
        currentSelectTarget = null;
    };

    customSaveBtn.addEventListener('click', function() {
        const val = customInput.value.trim();
        if (val && currentSelectTarget) {
            const opt = new Option(val, val, true, true);
            currentSelectTarget.add(opt);
            closeCustomModal();
            // Verificar se o item novo é "Interpretação"
            if (val === 'Interpretação') {
                const row = currentSelectTarget.closest('.task-row');
                if (row) {
                    row.querySelector('.btn-interpretation-expenses').classList.add('visible');
                }
            }
        }
    });
    
    window.onclick = function(event) {
        if (event.target == customModal) closeCustomModal();
        if (event.target == clientModal) closeClientModal();
        if (event.target == document.getElementById('interpretationExpensesModal')) closeExpensesModal();
    };

    // =============================================
    // MODAL DE DESPESAS DE INTERPRETAÇÃO
    // =============================================
    const expensesModal = document.getElementById('interpretationExpensesModal');
    const expensesBody = document.getElementById('expensesTableBody');
    const btnAddExpense = document.getElementById('btnAddExpense');
    const btnSaveExpenses = document.getElementById('btnSaveExpenses');

    window.openExpensesModal = function() {
        renderExpensesTable();
        updateExpensesSummary();
        expensesModal.classList.add('active');
    };

    window.closeExpensesModal = function() {
        expensesModal.classList.remove('active');
    };

    btnAddExpense.addEventListener('click', () => {
        interpretationExpenses.push({
            type: 'travel',
            description: '',
            amount: 0,
            paid_by: 'client'
        });
        renderExpensesTable();
        updateExpensesSummary();
    });

    btnSaveExpenses.addEventListener('click', () => {
        // Ler valores atuais da tabela
        syncExpensesFromTable();
        // Atualizar hidden fields e fechar
        syncExpensesToHiddenFields();
        updateAllInterpretationButtons();
        calculateTotal();
        closeExpensesModal();
    });

    function renderExpensesTable() {
        expensesBody.innerHTML = '';
        
        if (interpretationExpenses.length === 0) {
            expensesBody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:#666; padding:20px;">Nenhuma despesa adicionada. Clique em "Adicionar Despesa".</td></tr>';
            return;
        }
        
        interpretationExpenses.forEach((exp, idx) => {
            const row = document.createElement('tr');
            row.className = 'expense-row';
            row.setAttribute('data-expense-index', idx);
            
            const clientChecked = exp.paid_by === 'client' ? 'checked' : '';
            const interpreterChecked = exp.paid_by === 'interpreter' ? 'checked' : '';
            
            row.innerHTML = 
                '<td>' +
                    '<select class="vision-select exp-type" data-idx="' + idx + '">' +
                        '<option value="travel"' + (exp.type === 'travel' ? ' selected' : '') + '>Viagem</option>' +
                        '<option value="accommodation"' + (exp.type === 'accommodation' ? ' selected' : '') + '>Hospedagem</option>' +
                        '<option value="food"' + (exp.type === 'food' ? ' selected' : '') + '>Alimentação</option>' +
                        '<option value="equipment"' + (exp.type === 'equipment' ? ' selected' : '') + '>Equipamento</option>' +
                    '</select>' +
                '</td>' +
                '<td>' +
                    '<input type="text" class="vision-input exp-desc" data-idx="' + idx + '" value="' + escapeHtml(exp.description) + '" placeholder="Ex: Passagem aérea SP-RJ">' +
                '</td>' +
                '<td>' +
                    '<input type="text" class="vision-input exp-amount" data-idx="' + idx + '" value="' + (exp.amount > 0 ? formatNumber(exp.amount) : '') + '" placeholder="0,00" oninput="onExpenseAmountChange()">' +
                '</td>' +
                '<td>' +
                    '<div class="paid-by-toggle">' +
                        '<input type="radio" name="exp_paid_' + idx + '" value="client" id="exp_client_' + idx + '" ' + clientChecked + ' onchange="onExpensePaidByChange()">' +
                        '<label for="exp_client_' + idx + '">Cliente</label>' +
                        '<input type="radio" name="exp_paid_' + idx + '" value="interpreter" id="exp_interp_' + idx + '" ' + interpreterChecked + ' onchange="onExpensePaidByChange()">' +
                        '<label for="exp_interp_' + idx + '">Intérprete</label>' +
                    '</div>' +
                '</td>' +
                '<td>' +
                    '<button type="button" class="btn-remove-expense" onclick="removeExpense(' + idx + ')" title="Remover"><i class="fas fa-times"></i></button>' +
                '</td>';
            
            expensesBody.appendChild(row);
        });
    }

    window.removeExpense = function(idx) {
        interpretationExpenses.splice(idx, 1);
        renderExpensesTable();
        updateExpensesSummary();
    };

    window.onExpenseAmountChange = function() {
        syncExpensesFromTable();
        updateExpensesSummary();
    };

    window.onExpensePaidByChange = function() {
        syncExpensesFromTable();
        updateExpensesSummary();
    };

    function syncExpensesFromTable() {
        const rows = expensesBody.querySelectorAll('.expense-row');
        rows.forEach((row, idx) => {
            if (interpretationExpenses[idx]) {
                const typeEl = row.querySelector('.exp-type');
                const descEl = row.querySelector('.exp-desc');
                const amountEl = row.querySelector('.exp-amount');
                const paidByEl = row.querySelector('input[name="exp_paid_' + idx + '"]:checked');
                
                interpretationExpenses[idx].type = typeEl ? typeEl.value : 'travel';
                interpretationExpenses[idx].description = descEl ? descEl.value : '';
                interpretationExpenses[idx].amount = amountEl ? (parseFloat(amountEl.value.replace(',', '.')) || 0) : 0;
                interpretationExpenses[idx].paid_by = paidByEl ? paidByEl.value : 'client';
            }
        });
    }

    function updateExpensesSummary() {
        let clientTotal = 0;
        let interpreterTotal = 0;
        
        interpretationExpenses.forEach(exp => {
            if (exp.paid_by === 'client') {
                clientTotal += exp.amount;
            } else {
                interpreterTotal += exp.amount;
            }
        });
        
        document.getElementById('summaryClientTotal').textContent = formatCurrency(clientTotal);
        document.getElementById('summaryInterpreterTotal').textContent = formatCurrency(interpreterTotal);
        document.getElementById('summaryExpensesTotal').textContent = formatCurrency(clientTotal + interpreterTotal);
    }

    // Sincronizar despesas para hidden fields no form
    function syncExpensesToHiddenFields() {
        const hiddenContainer = document.getElementById('expensesHiddenContainer');
        hiddenContainer.innerHTML = '';
        
        interpretationExpenses.forEach((exp, idx) => {
            if (exp.amount > 0) {
                hiddenContainer.innerHTML +=
                    '<input type="hidden" name="expense_type[]" value="' + exp.type + '">' +
                    '<input type="hidden" name="expense_description[]" value="' + escapeHtml(exp.description) + '">' +
                    '<input type="hidden" name="expense_amount[]" value="' + exp.amount.toFixed(2) + '">' +
                    '<input type="hidden" name="expense_paid_by[]" value="' + exp.paid_by + '">';
            }
        });
    }

    // Atualizar botão/badge de despesas em cada task row
    function updateAllInterpretationButtons() {
        const rows = container.querySelectorAll('.task-row');
        
        // Contar despesas e total
        let expenseCount = interpretationExpenses.filter(e => e.amount > 0).length;
        let clientExpTotal = 0;
        interpretationExpenses.forEach(e => {
            if (e.paid_by === 'client') clientExpTotal += e.amount;
        });
        
        rows.forEach(row => {
            const serviceSelect = row.querySelector('.service-select');
            const btn = row.querySelector('.btn-interpretation-expenses');
            const badge = btn ? btn.querySelector('.expense-badge') : null;
            
            if (serviceSelect && serviceSelect.value === 'Interpretação') {
                btn.classList.add('visible');
                if (badge && expenseCount > 0) {
                    badge.style.display = 'inline';
                    badge.textContent = expenseCount;
                } else if (badge) {
                    badge.style.display = 'none';
                }
            } else if (btn) {
                btn.classList.remove('visible');
            }
        });
        
        // Sincronizar hidden fields no load
        syncExpensesToHiddenFields();
    }

    // Submissão do form: garantir que despesas estejam sincronizadas
    document.getElementById('projectForm').addEventListener('submit', function() {
        syncExpensesToHiddenFields();
    });
});

// Funções Globais
function calculateRow(input) {
    calculateTotal();
}

function calculateTotal() {
    let total = 0;
    const rows = document.querySelectorAll('.task-row');
    rows.forEach(row => {
        const qty = parseFloat(row.querySelector('.job-qty').value.replace(',', '.')) || 0;
        const price = parseFloat(row.querySelector('.job-price').value.replace(',', '.')) || 0;
        total += (qty * price);
    });
    
    // Somar despesas do Cliente
    if (typeof interpretationExpenses !== 'undefined') {
        interpretationExpenses.forEach(exp => {
            if (exp.paid_by === 'client') {
                total += exp.amount;
            }
        });
    }
    
    const el = document.getElementById('displayTotal');
    if(el) el.textContent = total.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function updateTotalDisplay() {
    const el = document.getElementById('displayCurrency');
    const sel = document.getElementById('projectCurrency');
    if(el && sel) el.textContent = sel.value;
}

function formatCurrency(val) {
    return val.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatNumber(val) {
    return val.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>
