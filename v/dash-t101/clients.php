<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';
require_once __DIR__ . '/../config/dash_functions.php';

if (!isLoggedIn()) { header('Location: /login.php'); exit; }

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';
$edit_client = null;

$default_currencies = ['BRL', 'USD', 'EUR', 'GBP'];

// --- POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $company = $_POST['company'];
        $contact_name = $_POST['contact_name'] ?? null;
        $email = $_POST['email'] ?? null;
        $phone = $_POST['phone'] ?? null;
        $country = $_POST['country'] ?? null;
        $currency = $_POST['currency'] ?? 'BRL'; // Novo campo

        if (empty($company)) throw new Exception("Nome da empresa é obrigatório.");

        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO dash_clients (user_id, company, contact_name, email, phone, country, currency) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $company, $contact_name, $email, $phone, $country, $currency]);
            $_SESSION['temp_message'] = "Cliente adicionado!";
            header("Location: clients.php?edit=" . $pdo->lastInsertId());
            exit;
        } elseif ($action === 'edit') {
            $id = $_POST['client_id'];
            $stmt = $pdo->prepare("UPDATE dash_clients SET company=?, contact_name=?, email=?, phone=?, country=?, currency=? WHERE id=? AND user_id=?");
            $stmt->execute([$company, $contact_name, $email, $phone, $country, $currency, $id, $user_id]);
            $_SESSION['temp_message'] = "Cliente atualizado!";
            header("Location: clients.php?edit=" . $id);
            exit;
        }
    } catch (Exception $e) {
        $error = "Erro: " . $e->getMessage();
    }
}

// --- GET ---
if (isset($_SESSION['temp_message'])) { $message = $_SESSION['temp_message']; unset($_SESSION['temp_message']); }

if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM dash_clients WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $edit_client = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$edit_client) { header("Location: clients.php"); exit; }
}

$page_title = 'Clientes - Dash-T101';
include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<style>
    .main-content { padding-bottom: 100px; }
    .video-card { background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.05)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; margin-bottom: 20px; }
    .video-card > h2 { margin: 0; padding: 25px 30px 20px; font-size: 1.3rem; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid rgba(255, 255, 255, 0.06); display: flex; gap: 10px; align-items: center; }
    
    .profile-header-card { display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--brand-purple), #4a148c); padding: 20px; margin-bottom: 25px; }
    .header-icon-container { background: rgba(255,255,255,0.1); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .header-text-container { margin-left: 20px; }
    .header-text-container h2 { margin: 0; font-size: 1.5rem; color: #fff; }
    
    .vision-form { padding: 30px; }
    .form-row-flex { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px; }
    .form-group { display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 200px; }
    .form-group label { font-weight: 600; color: var(--text-secondary); font-size: 0.9rem; }
    .vision-input, .vision-select { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 12px 16px; color: var(--text-primary); font-size: 0.95rem; width: 100%; box-sizing: border-box; }
    option { background: #1a1a2e; color: #fff; }

    .vision-btn { background: var(--brand-purple); color: #fff; border: 0; border-radius: 20px; padding: 12px 24px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
    .vision-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
    .vision-btn-secondary { background: rgba(255,255,255,0.1); color: #fff; }
    .vision-btn-secondary:hover { background: rgba(255,255,255,0.2); }
    
    .report-nav-buttons { display: flex; gap: 15px; margin-bottom: 20px; }
    .alert-success { background: #22c55e; color: #fff; padding: 15px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
    .alert-error { background: #ef4444; color: #fff; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
</style>

<div class="main-content">

    <div class="video-card profile-header-card">
        <div class="header-icon-container"><i class="fas fa-user-tie" style="font-size: 1.8rem; color: #fff;"></i></div>
        <div class="header-text-container">
            <h2>Cadastro de Clientes</h2>
            <p>Gerencie as empresas e contatos.</p>
        </div>
    </div>
    
    <div class="report-nav-buttons">
        <a href="index.php" class="vision-btn vision-btn-secondary"><i class="fas fa-home"></i> Voltar</a>
        <a href="clients_list.php" class="vision-btn vision-btn-secondary"><i class="fas fa-list-ul"></i> Ver Lista</a>
    </div>

    <?php if ($message): ?><div class="alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div><?php endif; ?>

    <div class="video-card">
        <h2><i class="fas <?php echo $edit_client ? 'fa-edit' : 'fa-user-plus'; ?>"></i> <?php echo $edit_client ? 'Editar Cliente' : 'Adicionar Cliente'; ?></h2>
        
        <form method="POST" action="clients.php" class="vision-form">
            <input type="hidden" name="action" value="<?php echo $edit_client ? 'edit' : 'add'; ?>">
            <?php if ($edit_client): ?><input type="hidden" name="client_id" value="<?php echo $edit_client['id']; ?>"><?php endif; ?>
            
            <div class="form-row-flex">
                <div class="form-group" style="flex: 2;">
                    <label>Nome da Empresa</label>
                    <input type="text" name="company" class="vision-input" required value="<?php echo htmlspecialchars($edit_client['company'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Nome do Contato</label>
                    <input type="text" name="contact_name" class="vision-input" value="<?php echo htmlspecialchars($edit_client['contact_name'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-row-flex">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="vision-input" value="<?php echo htmlspecialchars($edit_client['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Telefone</label>
                    <input type="text" name="phone" class="vision-input" value="<?php echo htmlspecialchars($edit_client['phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>País</label>
                    <input type="text" name="country" class="vision-input" value="<?php echo htmlspecialchars($edit_client['country'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row-flex">
                <div class="form-group" style="max-width: 200px;">
                    <label>Moeda Padrão</label>
                    <select name="currency" class="vision-select">
                        <?php foreach ($default_currencies as $cur): ?>
                            <option value="<?php echo $cur; ?>" <?php echo (($edit_client['currency'] ?? 'BRL') == $cur) ? 'selected' : ''; ?>>
                                <?php echo $cur; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-actions" style="margin-top: 30px; text-align: right;">
                <button type="submit" class="vision-btn"><i class="fas fa-save"></i> Salvar Cliente</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>