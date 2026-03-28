<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';
require_once __DIR__ . '/../config/dash_functions.php';

if (!isLoggedIn()) { header('Location: /login.php'); exit; }

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    try {
        $id = $_POST['client_id'];
        // Opcional: Verificar se tem projetos antes de deletar
        $pdo->prepare("DELETE FROM dash_clients WHERE id = ? AND user_id = ?")->execute([$id, $user_id]);
        $_SESSION['temp_message'] = "Cliente removido!";
        header("Location: clients_list.php"); exit;
    } catch (Exception $e) {
        $error = "Erro ao excluir: " . $e->getMessage();
    }
}

if (isset($_SESSION['temp_message'])) { $message = $_SESSION['temp_message']; unset($_SESSION['temp_message']); }

// Busca
$term = trim($_GET['search'] ?? '');
$sql = "SELECT * FROM dash_clients WHERE user_id = ?";
$params = [$user_id];
if ($term) {
    $sql .= " AND (company LIKE ? OR contact_name LIKE ? OR email LIKE ?)";
    $term = "%$term%";
    array_push($params, $term, $term, $term);
}
$sql .= " ORDER BY company ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Lista de Clientes - Dash-T101';
include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<style>
    .main-content { padding-bottom: 100px; }
    .profile-header-card { display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--brand-purple), #4a148c); padding: 20px; margin-bottom: 25px; }
    .header-icon-container { background: rgba(255,255,255,0.1); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .header-text-container { margin-left: 20px; }
    .header-text-container h2 { margin: 0; font-size: 1.5rem; color: #fff; }
    
    .video-card { background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.05)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; margin-bottom: 20px; }
    .video-card h2 { padding: 20px; margin: 0; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 1.2rem; color: #fff; }
    
    .vision-table { width: 100%; border-collapse: collapse; }
    .vision-table th { padding: 15px 20px; text-align: left; color: #aaa; border-bottom: 1px solid rgba(255,255,255,0.1); font-size: 0.9rem; }
    .vision-table td { padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); color: #fff; }
    .vision-table tr:hover { background: rgba(255,255,255,0.02); }
    
    .vision-btn { background: var(--brand-purple); color: #fff; padding: 10px 20px; border-radius: 20px; text-decoration: none; font-weight: 600; display: inline-flex; gap: 5px; align-items: center; border: 0; cursor: pointer; }
    .vision-btn-secondary { background: rgba(255,255,255,0.1); }
    
    .action-btn { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; background: rgba(255,255,255,0.1); color: #fff; margin-right: 5px; transition: 0.2s; border: 0; cursor: pointer; }
    .action-btn:hover { background: var(--brand-purple); }
    .action-btn-del:hover { background: #ff3b30; }
    
    .search-form { padding: 20px; display: flex; gap: 10px; }
    .vision-input { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); padding: 10px 15px; border-radius: 10px; color: #fff; flex: 1; }
</style>

<div class="main-content">
    <div class="video-card profile-header-card" style="background: linear-gradient(135deg, var(--brand-purple), #4a148c); border: none; margin-bottom: 25px;">
        <div class="header-icon-container" style="background: rgba(255, 255, 255, 0.1); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <i class="fas fa-building" style="font-size: 1.8rem; color: #fff;"></i>
        </div>
        <div class="header-text-container" style="margin-left: 20px;">
            <h2 style="margin: 0 0 5px 0; padding: 0; font-size: 1.5rem; color: #fff; font-weight: 600; border: none;">Clientes</h2>
            <p style="margin: 0; color: rgba(255, 255, 255, 0.8); font-size: 1rem;">Lista de empresas</p>
        </div>
    </div>



    <div style="display:flex; gap:10px; margin-bottom:20px;">
        <a href="index.php" class="vision-btn vision-btn-secondary"><i class="fas fa-home"></i> Voltar</a>
        <a href="clients.php" class="vision-btn"><i class="fas fa-plus"></i> Novo Cliente</a>
    </div>

    <?php if ($message): ?><div style="background:#22c55e; color:#fff; padding:15px; border-radius:10px; margin-bottom:20px;"><i class="fas fa-check"></i> <?php echo $message; ?></div><?php endif; ?>

    <div class="video-card">
        <form method="GET" class="search-form">
            <input type="text" name="search" class="vision-input" placeholder="Buscar por nome ou email..." value="<?php echo htmlspecialchars($_GET['search']??''); ?>">
            <button type="submit" class="vision-btn"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <div class="video-card">
        <h2>Clientes Cadastrados (<?php echo count($clients); ?>)</h2>
        <div style="overflow-x:auto;">
            <table class="vision-table">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Contato</th>
                        <th>Email</th>
                        <th>País</th>
                        <th>Moeda</th> <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($clients)): ?>
                        <tr><td colspan="6" style="text-align:center; padding:30px; color:#aaa;">Nenhum cliente encontrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($clients as $c): ?>
                        <tr>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($c['company']); ?></td>
                            <td><?php echo htmlspecialchars($c['contact_name']??'-'); ?></td>
                            <td><?php echo htmlspecialchars($c['email']??'-'); ?></td>
                            <td><?php echo htmlspecialchars($c['country']??'-'); ?></td>
                            <td><span style="background:rgba(255,255,255,0.1); padding:2px 8px; border-radius:4px; font-size:0.8rem;"><?php echo htmlspecialchars($c['currency']??'BRL'); ?></span></td>
                            <td>
                                <a href="clients.php?edit=<?php echo $c['id']; ?>" class="action-btn" title="Editar"><i class="fas fa-edit"></i></a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir cliente?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="client_id" value="<?php echo $c['id']; ?>">
                                    <button type="submit" class="action-btn action-btn-del" title="Excluir"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>