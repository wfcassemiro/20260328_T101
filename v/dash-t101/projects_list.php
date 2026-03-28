<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';
require_once __DIR__ . '/../config/dash_functions.php';

if (!isLoggedIn()) { header('Location: /login.php'); exit; }

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// --- AÇÕES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Gerar Fatura
    if ($action === 'generate_invoice') {
        $pid = $_POST['project_id'];
        try {
            // Verifica se já existe fatura para evitar duplicidade no clique duplo
            $check = $pdo->prepare("SELECT id FROM dash_invoices WHERE project_id = ?");
            $check->execute([$pid]);
            if ($check->fetch()) {
                throw new Exception("Este projeto já possui uma fatura gerada.");
            }

            $new_invoice_id = generateInvoiceFromProject($pdo, $pid, $user_id);
            if ($new_invoice_id) {
                $_SESSION['temp_message'] = "Fatura criada com sucesso!";
                header("Location: projects_list.php"); exit;
            } else {
                $error = "Erro ao gerar fatura.";
            }
        } catch (Exception $e) {
            $error = "Erro: " . $e->getMessage();
        }
    }
    
    // Excluir Projeto
    if ($action === 'delete') {
        $pid = $_POST['project_id'];
        try {
            $pdo->prepare("DELETE FROM dash_projects WHERE id = ? AND user_id = ?")->execute([$pid, $user_id]);
            $_SESSION['temp_message'] = "Projeto excluído!";
            header("Location: projects_list.php"); exit;
        } catch (Exception $e) {
            $error = "Erro ao excluir: " . $e->getMessage();
        }
    }
}

if (isset($_SESSION['temp_message'])) { $message = $_SESSION['temp_message']; unset($_SESSION['temp_message']); }

// --- BUSCA (Com JOIN para verificar Fatura) ---
$search = trim($_GET['search'] ?? '');
$sql = "SELECT p.*, c.company as client_name, i.id as invoice_id, i.number as invoice_number 
        FROM dash_projects p 
        LEFT JOIN dash_clients c ON p.client_id = c.id 
        LEFT JOIN dash_invoices i ON i.project_id = p.id
        WHERE p.user_id = ?";
$params = [$user_id];

if ($search) {
    $sql .= " AND (p.title LIKE ? OR p.po_number LIKE ? OR c.company LIKE ?)";
    $term = "%$search%";
    array_push($params, $term, $term, $term);
}
$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Meus Projetos - Dash-T101';
include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<style>
    .main-content { padding-bottom: 100px; }
    .profile-header-card { display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--brand-purple), #4a148c); padding: 20px; margin-bottom: 25px; }
    .header-icon-container { background: rgba(255,255,255,0.1); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .header-text-container { margin-left: 20px; }
    .header-text-container h2 { margin: 0; font-size: 1.5rem; color: #fff;}
    
    .video-card { background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.05)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; margin-bottom: 20px; }
    .vision-table { width: 100%; border-collapse: collapse; }
    .vision-table th { padding: 15px 20px; text-align: left; color: #aaa; border-bottom: 1px solid rgba(255,255,255,0.1); font-size: 0.9rem; }
    .vision-table td { padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); color: #fff; }
    .vision-table tr:hover { background: rgba(255,255,255,0.02); }
    
    .vision-btn { background: var(--brand-purple); color: #fff; padding: 10px 20px; border-radius: 20px; text-decoration: none; font-weight: 600; display: inline-flex; gap: 5px; align-items: center; border: 0; cursor: pointer; transition: 0.2s; }
    .vision-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
    .vision-btn-secondary { background: rgba(255,255,255,0.1); }
    
    .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
    .status-pending { background: rgba(255, 193, 7, 0.15); color: #ffca28; }
    .status-in_progress { background: rgba(64, 196, 255, 0.15); color: #40c4ff; }
    .status-completed { background: rgba(102, 187, 106, 0.15); color: #66bb6a; }
    .status-cancelled { background: rgba(239, 83, 80, 0.15); color: #ef5350; }

    .action-btn { width: 34px; height: 34px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.05); color: #ccc; border: 0; cursor: pointer; transition: 0.2s; margin-right: 5px; text-decoration: none; }
    .action-btn:hover { background: rgba(255,255,255,0.15); color: #fff; }
    .btn-invoice { color: #40c4ff; }
    .btn-invoice:hover { background: rgba(64, 196, 255, 0.2); }
    .btn-view { color: #ffca28; } /* Amarelo para visualizar fatura */
    .btn-del:hover { background: rgba(239, 83, 80, 0.2); color: #ef5350; }

    .search-form { padding: 20px; display: flex; gap: 10px; }
    .vision-input { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); padding: 10px 15px; border-radius: 10px; color: #fff; flex: 1; outline: none; }
    .vision-input:focus { border-color: var(--brand-purple); }
</style>

<div class="main-content">
    
    
    <div class="video-card profile-header-card" style="background: linear-gradient(135deg, var(--brand-purple), #4a148c); border: none; margin-bottom: 25px;">
        <div class="header-icon-container" style="background: rgba(255, 255, 255, 0.1); border-radius: 50%; width: 60px; height: 50px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <i class="fas fa-folder-open" style="font-size: 1.8rem; color: #fff;"></i>
        </div>
        <div class="header-text-container" style="margin-left: 20px;">
            <h2 style="margin: 0 0 5px 0; padding: 0; font-size: 1.5rem; color: #fff; font-weight: 600; border: none;">Meus projetos</h2>
            <p style="margin: 0; color: rgba(255, 255, 255, 0.8); font-size: 1rem;">Gerencie seus trabalhos e faturas</p>
        </div>
    </div>
    
    <div style="display:flex; gap:10px; margin-bottom:20px;">
        <a href="index.php" class="vision-btn vision-btn-secondary"><i class="fas fa-home"></i> Voltar</a>
        <a href="projects.php" class="vision-btn"><i class="fas fa-plus"></i> Novo projeto</a>
        <a href="invoices_list.php" class="vision-btn vision-btn-secondary"><i class="fas fa-file-invoice-dollar"></i> Ver todas as faturas</a>
    </div>

    <?php if ($message): ?><div style="background:#22c55e; color:#fff; padding:15px; border-radius:10px; margin-bottom:20px;"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div style="background:#ef4444; color:#fff; padding:15px; border-radius:10px; margin-bottom:20px;"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div><?php endif; ?>

    <div class="video-card">
        <form method="GET" class="search-form">
            <input type="text" name="search" class="vision-input" placeholder="Buscar projeto, cliente ou PO..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="vision-btn"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <div class="video-card">
        <div style="overflow-x:auto;">
            <table class="vision-table">
                <thead>
                    <tr>
                        <th>Projeto</th>
                        <th>Cliente</th>
                        <th>Status</th>
                        <th>Prazo</th>
                        <th>Valor</th>
                        <th style="text-align:right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($projects)): ?>
                        <tr><td colspan="6" style="text-align:center; padding:30px; color:#aaa;">Nenhum projeto encontrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($projects as $p): ?>
                        <tr>
                            <td>
                                <strong style="display:block; margin-bottom:3px;"><?php echo htmlspecialchars($p['title']); ?></strong>
                                <?php if($p['po_number']): ?><small style="color:#888;">PO: <?php echo htmlspecialchars($p['po_number']); ?></small><?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($p['client_name'] ?? '-'); ?></td>
                            <td><span class="status-badge status-<?php echo $p['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $p['status'])); ?></span></td>
                            <td><?php echo date('d/m/Y', strtotime($p['deadline'])); ?></td>
                            <td style="font-family:monospace; font-weight:bold;"><?php echo number_format($p['total_amount'], 2, ',', '.') . ' ' . $p['currency']; ?></td>
                            <td style="text-align:right;">
                                
                                <?php if ($p['invoice_id']): ?>
                                    <a href="view_invoice.php?id=<?php echo $p['invoice_id']; ?>" class="action-btn btn-view" title="Ver Fatura"><i class="fas fa-eye"></i></a>
                                    <a href="view_invoice.php?id=<?php echo $p['invoice_id']; ?>&download=true" target="_blank" class="action-btn btn-invoice" title="Baixar PDF"><i class="fas fa-download"></i></a>
                                <?php else: ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="generate_invoice">
                                        <input type="hidden" name="project_id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" class="action-btn btn-invoice" title="Gerar Fatura" onclick="return confirm('Gerar fatura para este projeto?');"><i class="fas fa-file-invoice-dollar"></i></button>
                                    </form>
                                <?php endif; ?>
                                
                                <a href="projects.php?edit=<?php echo $p['id']; ?>" class="action-btn" title="Editar"><i class="fas fa-edit"></i></a>
                                
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="project_id" value="<?php echo $p['id']; ?>">
                                    <button type="submit" class="action-btn btn-del" title="Excluir" onclick="return confirm('Excluir projeto?');"><i class="fas fa-trash"></i></button>
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