<?php
session_start();
// [CORRIGIDO] Caminhos de inclusão e funções
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';
require_once __DIR__ . '/../config/dash_functions.php';

// Verificar se o usuário está logado
if (!isLoggedIn()) {
  header('Location: /login.php');
  exit;
}

$user_id = $_SESSION['user_id'];
$project_id_filter = $_GET['project_id'] ?? null;

// [CORRIGIDO] Usar $pdo
// Buscar freelancers do usuário
$stmt_freelancers = $pdo->prepare("SELECT id, name FROM dash_freelancers WHERE user_id = ? AND is_active = 1 ORDER BY name ASC");
$stmt_freelancers->execute([$user_id]);
$freelancers = $stmt_freelancers->fetchAll(PDO::FETCH_ASSOC);

// Buscar projetos do usuário
// [!!! CORREÇÃO APLICADA AQUI !!!] Especificado p.id, p.title, p.po_number, c.company
$stmt_projects = $pdo->prepare("SELECT p.id, p.title, p.po_number, c.company FROM dash_projects p LEFT JOIN dash_clients c ON p.client_id = c.id WHERE p.user_id = ? ORDER BY p.created_at DESC");
$stmt_projects->execute([$user_id]);
$projects = $stmt_projects->fetchAll(PDO::FETCH_ASSOC);

// Mapeamentos
$services = [
  'traducao' => 'Tradução',
  'revisao' => 'Revisão',
  'proofreading' => 'Revisão (Proofreading)',
  'localizacao' => 'Localização',
  'transcricao' => 'Transcrição',
  'interpretacao' => 'Interpretação',
  'mtpe' => 'Pós-edição (MTPE)', // Adicionado
  'copywriting' => 'Copywriting', // Adicionado
  'other' => 'Outro',
];

$units = [
  'palavra' => 'Palavra',
  'hora' => 'Hora',
  'minuto' => 'Minuto',
  'lauda' => 'Lauda',
  'diaria' => 'Diária',
  'projeto' => 'Projeto (Valor Fechado)',
];

$status_map = [
  'pending' => 'Pendente',
  'in_progress' => 'Em Andamento',
  'completed' => 'Concluído',
  'paid' => 'Pago',
];

// Lógica de Ações (CRUD)
try {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    if ($action == 'add' || $action == 'update') {
      $project_id = $_POST['project_id'];
      $freelancer_id = $_POST['freelancer_id'];
      $service_type = $_POST['service_type'];
      $unit_type = $_POST['unit_type'];
      $quantity = (float)$_POST['quantity'];
      $rate = (float)$_POST['rate'];
      $currency = $_POST['currency'];
      $total_cost = $quantity * $rate; // Cálculo automático
      $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
      $status = $_POST['status'];
      $notes = $_POST['notes'] ?? null;
      $job_id = $_POST['job_id'] ?? null;

      if ($action == 'add') {
        // [CORRIGIDO] Usar $pdo
        $stmt = $pdo->prepare("INSERT INTO dash_jobs (user_id, project_id, freelancer_id, service_type, unit_type, quantity, rate, currency, total_cost, deadline, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $project_id, $freelancer_id, $service_type, $unit_type, $quantity, $rate, $currency, $total_cost, $deadline, $status, $notes]);
        $_SESSION['temp_message'] = 'Trabalho adicionado!';
      } elseif ($action == 'update' && $job_id) {
        // [CORRIGIDO] Usar $pdo
        $stmt = $pdo->prepare("UPDATE dash_jobs SET project_id = ?, freelancer_id = ?, service_type = ?, unit_type = ?, quantity = ?, rate = ?, currency = ?, total_cost = ?, deadline = ?, status = ?, notes = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$project_id, $freelancer_id, $service_type, $unit_type, $quantity, $rate, $currency, $total_cost, $deadline, $status, $notes, $job_id, $user_id]);
        $_SESSION['temp_message'] = 'Trabalho atualizado!';
      }
    } elseif ($action == 'delete') {
      $job_id = $_POST['job_id'];
      // [CORRIGIDO] Usar $pdo
      $stmt = $pdo->prepare("DELETE FROM dash_jobs WHERE id = ? AND user_id = ?");
      $stmt->execute([$job_id, $user_id]);
      $_SESSION['temp_message'] = 'Trabalho excluído!';
    } elseif ($action == 'get_job_details' && isset($_POST['job_id'])) {
      header('Content-Type: application/json');
      $job_id = $_POST['job_id'];
      // [CORRIGIDO] Usar $pdo
      $stmt = $pdo->prepare("SELECT * FROM dash_jobs WHERE id = ? AND user_id = ?");
      $stmt->execute([$job_id, $user_id]);
      $job = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($job) {
        echo json_encode($job);
      } else {
        echo json_encode(['error' => 'Job não encontrado.']);
      }
      exit;
    }

    $redirect_url = $project_id_filter ? "jobs.php?project_id=$project_id_filter" : "jobs.php";
    header("Location: $redirect_url");
    exit;
  }
} catch (Exception $e) {
  $_SESSION['temp_error'] = 'Erro: ' . $e->getMessage();
}

// Listar Jobs
$sql_jobs = "
    SELECT 
        j.*,
        p.title as project_title,
        p.po_number,
        f.name as freelancer_name
    FROM dash_jobs j
    JOIN dash_projects p ON j.project_id = p.id
    JOIN dash_freelancers f ON j.freelancer_id = f.id
    WHERE j.user_id = ?
";

$params = [$user_id];

if ($project_id_filter) {
  $sql_jobs .= " AND j.project_id = ?";
  $params[] = $project_id_filter;
}

$sql_jobs .= " ORDER BY j.created_at DESC";

// [CORRIGIDO] Usar $pdo
$stmt_jobs = $pdo->prepare($sql_jobs);
$stmt_jobs->execute($params);
$jobs = $stmt_jobs->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Custos de Fornecedores (Jobs)";
if ($project_id_filter) {
    // [CORRIGIDO] Usar $pdo
    $stmt_project_title = $pdo->prepare("SELECT title FROM dash_projects WHERE id = ? AND user_id = ?");
    $stmt_project_title->execute([$project_id_filter, $user_id]);
    $project_title = $stmt_project_title->fetchColumn();
    if ($project_title) {
        $page_title = "Custos do Projeto: " . htmlspecialchars($project_title);
    }
}

// [CORRIGIDO] Mensagens de sessão
$message = $_SESSION['temp_message'] ?? '';
$error = $_SESSION['temp_error'] ?? '';
unset($_SESSION['temp_message'], $_SESSION['temp_error']);

// [CORRIGIDO] Inclusão dos cabeçalhos do tema "Vision"
include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
  <div class="video-card profile-header-card" style="background: linear-gradient(135deg, var(--brand-purple), #4a148c); border: none; margin-bottom: 25px;">
    <div class="header-icon-container">
      <i class="fas fa-wallet" style="font-size: 1.8rem; color: #fff;"></i>
    </div>
    <div class="header-text-container" style="margin-left: 20px;">
      <h2 style="margin: 0 0 5px 0; padding: 0; font-size: 1.5rem; color: #fff; font-weight: 600; border: none;"><?php echo $page_title; ?></h2>
      <p style="margin: 0; color: rgba(255, 255, 255, 0.8); font-size: 1rem;">Gerencie os custos de seus fornecedores por projeto.</p>
    </div>
  </div>

  <div class="report-nav-buttons">
    <?php if ($project_id_filter): ?>
      <a href="projects.php" class="vision-btn vision-btn-secondary"><i class="fas fa-arrow-left"></i> Voltar a Projetos</a>
    <?php else: ?>
       <a href="index.php" class="vision-btn vision-btn-secondary"><i class="fas fa-home"></i> Voltar ao Dash-T101</a>
    <?php endif; ?>
    <button class="vision-btn vision-btn-primary" onclick="showAddJobModal()">
      <i class="fas fa-plus"></i> Adicionar custo
    </button>
  </div>

  <?php if ($message): ?><div id="success-alert" class="alert-success"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert-error"><i class="fas fa-exclamation-triangle"></i><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <div class="video-card">
    <h2><i class="fas fa-list-ul"></i> Todos os custos</h2>
    <div class="vision-table-container">
      <?php if (empty($jobs)): ?>
          <div class="alert-info" style="margin: 20px 30px;"><i class="fas fa-info-circle"></i> Nenhum custo cadastrado.</div>
      <?php else: ?>
        <table class="vision-table" id="jobs_table">
          <thead>
            <tr>
              <th>Fornecedor</th>
              <?php if (!$project_id_filter): ?>
                <th>Projeto</th>
              <?php endif; ?>
              <th>Serviço/Detalhes</th>
              <th>Custo Total</th>
              <th>Prazo</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($jobs as $job) : ?>
              <tr>
                <td class="client-name-refined"><?php echo htmlspecialchars($job['freelancer_name']); ?></td>
                <?php if (!$project_id_filter): ?>
                  <td>
                    <a href="?project_id=<?php echo $job['project_id']; ?>" style="color: var(--brand-purple); text-decoration: none; font-weight: 500;">
                      <?php echo htmlspecialchars($job['project_title']); ?>
                    </a>
                    <?php if (!empty($job['po_number'])) : ?>
                        <br><small style="color: var(--text-muted); font-size: 0.8rem;">PO: <?php echo htmlspecialchars($job['po_number']); ?></small>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
                <td>
                  <?php echo htmlspecialchars($services[$job['service_type']] ?? $job['service_type']); ?><br>
                  <small style="color: var(--text-muted); font-size: 0.8rem;">
                    <?php echo htmlspecialchars($job['quantity']); ?> <?php echo htmlspecialchars($units[$job['unit_type']] ?? $job['unit_type']); ?> x <?php echo htmlspecialchars($job['currency']); ?> <?php echo number_format($job['rate'], 2, ',', '.'); ?>
                  </small>
                </td>
                <td style="font-weight: 600;"><?php echo htmlspecialchars($job['currency']); ?> <?php echo number_format($job['total_cost'], 2, ',', '.'); ?></td>
                <td><?php echo $job['deadline'] ? date('d/m/Y', strtotime($job['deadline'])) : '-'; ?></td>
                <td>
                  <span class_not_used="badge badge-<?php //echo getStatusClass($job['status']); ?>" 
                        class="status-badge-refined status-<?php echo htmlspecialchars($job['status']); ?>">
                    <?php echo htmlspecialchars($status_map[$job['status']] ?? $job['status']); ?>
                  </span>
                </td>
                <td>
                  <div class="action-buttons-refined">
                    <button class="action-btn-refined action-btn-edit edit-job-btn" data-id="<?php echo $job['id']; ?>" title="Editar trabalho">
                      <i class="fas fa-edit"></i>
                    </button>
                    <form action="jobs.php<?php echo $project_id_filter ? "?project_id=$project_id_filter" : ""; ?>" method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir este trabalho?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                      <button type="submit" class="action-btn-refined action-btn-delete" title="Excluir trabalho">
                        <i class="fas fa-trash"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div id="addJobModal" class="vision-modal">
  <div class="vision-modal-content" style="max-width: 700px;">
    <div class="vision-modal-header">
      <h3><i class="fas fa-plus-circle"></i> Adicionar novo custo</h3>
      <button type="button" class="vision-modal-close" onclick="hideAddJobModal()">
          <i class="fas fa-times"></i>
      </button>
    </div>
    <form action="jobs.php<?php echo $project_id_filter ? "?project_id=$project_id_filter" : ""; ?>" method="POST" id="addJobForm" class="vision-form" style="padding: 20px 30px 30px;">
      <input type="hidden" name="action" value="add">
      
      <div class="form-row-flex">
        <div class="form-group">
          <label for="project_id_add">Projeto</label> <select id="project_id_add" name="project_id" class="vision-input" required>
            <option value="">Selecione...</option>
            <?php foreach ($projects as $project) : ?>
              <option value="<?php echo $project['id']; ?>" <?php echo ($project_id_filter == $project['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($project['title']); ?> (<?php echo htmlspecialchars($project['company']); ?>) <?php echo $project['po_number'] ? "[PO: ".htmlspecialchars($project['po_number'])."]" : ""; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="freelancer_id_add">Fornecedor (Freelancer)</label> <select id="freelancer_id_add" name="freelancer_id" class="vision-input" required>
            <option value="">Selecione...</option>
            <?php foreach ($freelancers as $freelancer) : ?>
              <option value="<?php echo $freelancer['id']; ?>"><?php echo htmlspecialchars($freelancer['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      
      <div class="form-row-flex">
        <div class="form-group">
          <label for="service_type_add">Tipo de serviço</label> <select id="service_type_add" name="service_type" class="vision-input" required>
            <option value="">Selecione...</option>
            <?php foreach ($services as $key => $value) : ?>
              <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="unit_type_add">Unidade de cobrança</label> <select id="unit_type_add" name="unit_type" class="vision-input" required>
            <option value="">Selecione...</option>
            <?php foreach ($units as $key => $value) : ?>
              <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      
      <div class="form-row-flex">
        <div class="form-group">
          <label for="quantity_add">Quantidade</label> <input type="number" class="vision-input" id="quantity_add" name="quantity" step="0.01" required>
        </div>
        <div class="form-group">
          <label for="rate_add">Valor por unidade</label> <input type="number" class="vision-input" id="rate_add" name="rate" step="0.01" required>
        </div>
        <div class="form-group">
          <label for="currency_add">Moeda</label> <select id="currency_add" name="currency" class="vision-input" required>
            <option value="BRL">BRL</option>
            <option value="USD">USD</option>
            <option value="EUR">EUR</option>
            <option value="CAD">CAD</option>
          </select>
        </div>
      </div>
      
      <div class="form-row-flex">
        <div class="form-group">
          <label for="deadline_add">Prazo final</label> <input type="date" class="vision-input" id="deadline_add" name="deadline">
        </div>
        <div class="form-group">
          <label for="status_add">Status</label> <select id="status_add" name="status" class="vision-input" required>
            <option value="pending" selected>Pendente</option>
            <option value="in_progress">Em andamento</option>
            <option value="completed">Concluído</option>
            <option value="paid">Pago</option>
          </select>
        </div>
      </div>
      
      <div class="form-group">
        <label for="notes_add">Observações</label> <textarea class="vision-input" id="notes_add" name="notes" rows="2"></textarea>
      </div>
      
      <div class="vision-modal-actions">
        <button type="button" class="vision-btn vision-btn-secondary" onclick="hideAddJobModal()">Cancelar</button>
        <button type="submit" class="vision-btn vision-btn-primary">Salvar trabalho</button>
      </div>
    </form>
  </div>
</div>

<div id="editJobModal" class="vision-modal">
  <div class="vision-modal-content" style="max-width: 700px;">
    <div class="vision-modal-header">
      <h3><i class="fas fa-edit"></i> Editar custo</h3>
      <button type="button" class="vision-modal-close" onclick="hideEditJobModal()">
          <i class="fas fa-times"></i>
      </button>
    </div>
    <form action="jobs.php<?php echo $project_id_filter ? "?project_id=$project_id_filter" : ""; ?>" method="POST" id="editJobForm" class="vision-form" style="padding: 20px 30px 30px;">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="job_id" id="edit_job_id">
      
      <div class="form-row-flex">
        <div class="form-group">
          <label for="edit_project_id">Projeto</label>
          <select id="edit_project_id" name="project_id" class="vision-input" required>
            <?php foreach ($projects as $project) : ?>
              <option value="<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['title']); ?> (<?php echo htmlspecialchars($project['company']); ?>) <?php echo $project['po_number'] ? "[PO: ".htmlspecialchars($project['po_number'])."]" : ""; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="edit_freelancer_id">Fornecedor (Freelancer)</label>
          <select id="edit_freelancer_id" name="freelancer_id" class="vision-input" required>
            <?php foreach ($freelancers as $freelancer) : ?>
              <option value="<?php echo $freelancer['id']; ?>"><?php echo htmlspecialchars($freelancer['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      
      <div class="form-row-flex">
        <div class="form-group">
          <label for="edit_service_type">Tipo de serviço</label>
          <select id="edit_service_type" name="service_type" class="vision-input" required>
            <?php foreach ($services as $key => $value) : ?>
              <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="edit_unit_type">Unidade de cobrança</label>
          <select id="edit_unit_type" name="unit_type" class="vision-input" required>
            <?php foreach ($units as $key => $value) : ?>
              <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      
      <div class="form-row-flex">
        <div class="form-group">
          <label for="edit_quantity">Quantidade</label>
          <input type="number" class="vision-input" id="edit_quantity" name="quantity" step="0.01" required>
        </div>
        <div class="form-group">
          <label for="edit_rate">Valor por unidade</label>
          <input type="number" class="vision-input" id="edit_rate" name="rate" step="0.01" required>
        </div>
        <div class="form-group">
          <label for="edit_currency">Moeda</label>
          <select id="edit_currency" name="currency" class="vision-input" required>
            <option value="BRL">BRL</option>
            <option value="USD">USD</option>
            <option value="EUR">EUR</option>
            <option value="CAD">CAD</option>
          </select>
        </div>
      </div>
      
      <div class="form-row-flex">
        <div class="form-group">
          <label for="edit_deadline">Prazo final</label>
          <input type="date" class="vision-input" id="edit_deadline" name="deadline">
        </div>
        <div class="form-group">
          <label for="edit_status">Status</label>
          <select id="edit_status" name="status" class="vision-input" required>
            <option value="pending">Pendente</option>
            <option value="in_progress">Em andamento</option>
            <option value="completed">Concluído</option>
            <option value="paid">Pago</option>
          </select>
        </div>
      </div>
      
      <div class="form-group">
        <label for="edit_notes">Observações</label>
        <textarea class="vision-input" id="edit_notes" name="notes" rows="2"></textarea>
      </div>
      
      <div class="vision-modal-actions">
        <button type="button" class="vision-btn vision-btn-secondary" onclick="hideEditJobModal()">Cancelar</button>
        <button type="submit" class="vision-btn vision-btn-primary">Salvar alterações</button>
      </div>
    </form>
  </div>
</div>

<style>
/* (Estilos copiados de outros arquivos 'vision' para consistência) */
.alert-success { opacity: 1; transition: opacity 1s ease-out; background: #22c55e; color: #fff; padding: 15px 30px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.alert-error { background: #ef4444; color: #fff; padding: 15px 30px; border-radius: 12px; margin-bottom: 20px; }
.alert-info { background: var(--accent-blue); color: #fff; padding: 15px 30px; border-radius: 12px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
.main-content .video-card { margin-bottom: 20px; }
.video-card { background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.05)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; }
.video-card > h2 { margin: 0; padding: 25px 30px 20px; font-size: 1.3rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); }

/* Tabela */
.vision-table-container { margin: 20px 30px 30px; overflow-x: auto; padding-bottom: 10px; }
.vision-table { width: 100%; border-collapse: collapse; }
.vision-table th { background: rgba(255, 255, 255, 0.05); border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding: 18px 20px; font-weight: 600; font-size: 0.9rem; color: var(--text-secondary); text-align: left; }
.vision-table td { padding: 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.04); font-size: 0.95rem; }
.vision-table tr:hover { background: rgba(255, 255, 255, 0.03); }
.client-name-refined { color: var(--text-primary); font-weight: 600; }
.action-buttons-refined { display: flex; gap: 8px; align-items: center; }
.action-btn-refined { width: 36px; height: 36px; border-radius: 18px; border: 1px solid rgba(255, 255, 255, 0.1); color: var(--text-secondary); display: flex; align-items: center; justify-content: center; text-decoration: none; cursor: pointer; transition: all 0.3s ease; font-size: 0.9rem; background: rgba(255, 255, 255, 0.05); }
.action-btn-refined:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); }
.action-btn-edit:hover { background: var(--brand-purple); color: white; border-color: var(--brand-purple); }
.action-btn-delete:hover { background: var(--accent-orange); color: white; border-color: var(--accent-orange); }

/* Status Badges */
.status-badge-refined { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: capitalize; background: rgba(255, 255, 255, 0.1); color: var(--text-secondary); }
.status-badge-refined.status-pending { background: rgba(255, 193, 7, 0.1); color: #ffca28; }
.status-badge-refined.status-in_progress { background: rgba(30, 136, 229, 0.1); color: #64b5f6; }
.status-badge-refined.status-completed { background: rgba(67, 160, 71, 0.1); color: #81c784; }
.status-badge-refined.status-paid { background: rgba(0, 200, 83, 0.2); color: #00e676; }

/* Botões */
.report-nav-buttons { display: flex; gap: 15px; margin-bottom: 20px; }
.vision-btn { background: var(--brand-purple); color: white; border: 1px solid var(--brand-purple); border-radius: 20px; padding: 12px 24px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); }
.vision-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3); }
.vision-btn-primary { background: var(--brand-purple); border-color: var(--brand-purple); }
.vision-btn-primary:hover { background: var(--brand-purple-dark); border-color: var(--brand-purple-dark); }
.vision-btn-secondary { background: rgba(255, 255, 255, 0.1); color: var(--text-primary); border-color: rgba(255, 255, 255, 0.2); }
.vision-btn-secondary:hover { background: rgba(255, 255, 255, 0.2) !important; border-color: rgba(255, 255, 255, 0.3) !important; color: #fff !important; }
.vision-btn-danger { background: var(--accent-orange); border-color: var(--accent-orange); }
.vision-btn-danger:hover { background: #d9534f; border-color: #d9534f; }

/* Header */
.profile-header-card { display: flex; align-items: center; justify-content: center; }
.header-icon-container { background: rgba(255, 255, 255, 0.1); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.header-text-container { margin-left: 20px; }
.header-text-container h2 { margin: 0 0 5px 0; padding: 0; font-size: 1.5rem; color: #fff; font-weight: 600; border: none; }
.header-text-container p { margin: 0; color: rgba(255, 255, 255, 0.8); font-size: 1rem; }

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

/* Formulário */
.form-row-flex { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px; }
.form-group { display: flex; flex-direction: column; gap: 8px; flex: 1 1 200px; }
.form-group label { font-weight: 600; color: var(--text-secondary); font-size: 0.9rem; }
.vision-input { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 12px 16px; color: var(--text-primary); font-size: 0.95rem; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- Lógica do Alerta de Sucesso ---
    const successAlert = document.getElementById('success-alert');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.opacity = '0';
            setTimeout(() => { successAlert.remove(); }, 1000); 
        }, 5000);
    }
    
    // --- Lógica dos Modais ---
    const addJobModal = document.getElementById('addJobModal');
    const editJobModal = document.getElementById('editJobModal');
    
    // Funções de controle do Modal de Adicionar
    window.showAddJobModal = function() {
        addJobModal.classList.add('active');
    }
    window.hideAddJobModal = function() {
        addJobModal.classList.remove('active');
    }
    
    // Funções de controle do Modal de Editar
    window.showEditJobModal = function() {
        editJobModal.classList.add('active');
    }
    window.hideEditJobModal = function() {
        editJobModal.classList.remove('active');
    }

    // Fechar modais clicando fora ou no 'x'
    [addJobModal, editJobModal].forEach(modal => {
        if(modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal || e.target.closest('.vision-modal-close')) {
                    modal.classList.remove('active');
                }
            });
        }
    });
    
    // Fechar modais com 'Esc'
    document.addEventListener('keydown', function(e) {
        if (e.key === "Escape") {
            if (addJobModal) addJobModal.classList.remove('active');
            if (editJobModal) editJobModal.classList.remove('active');
        }
    });

    // Lógica para carregar dados de edição (AJAX)
    document.querySelectorAll('.edit-job-btn').forEach(button => {
        button.addEventListener('click', function() {
            var jobId = this.dataset.id;
            
            // Prepara os dados para o POST
            var formData = new FormData();
            formData.append('action', 'get_job_details');
            formData.append('job_id', jobId);

            fetch('jobs.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.error) {
                    alert(data.error);
                    return;
                }
                
                // Preenche o formulário de edição
                document.getElementById('edit_job_id').value = data.id;
                document.getElementById('edit_project_id').value = data.project_id;
                document.getElementById('edit_freelancer_id').value = data.freelancer_id;
                document.getElementById('edit_service_type').value = data.service_type;
                document.getElementById('edit_unit_type').value = data.unit_type;
                document.getElementById('edit_quantity').value = data.quantity;
                document.getElementById('edit_rate').value = data.rate;
                document.getElementById('edit_currency').value = data.currency;
                document.getElementById('edit_deadline').value = data.deadline;
                document.getElementById('edit_status').value = data.status;
                document.getElementById('edit_notes').value = data.notes;
                
                // Mostra o modal de edição
                showEditJobModal();
            })
            .catch(error => {
                console.error("Erro ao buscar dados do job:", error);
                alert("Erro ao carregar dados do job.");
            });
        });
    });
});
</script>

<?php
// [CORRIGIDO] Inclusão do footer "Vision"
include __DIR__ . '/../vision/includes/footer.php';
?>