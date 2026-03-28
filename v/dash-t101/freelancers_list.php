<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';
require_once __DIR__ . '/../config/dash_functions.php';

// Verificar se o usuário está logado
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// --- Processamento de POST (Apenas Excluir) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    $pdo->beginTransaction();
    try {
        if ($action === 'delete') {
            $freelancer_id = $_POST['freelancer_id'];
            
            // 1. Desvincular de jobs
            $stmt_jobs = $pdo->prepare("UPDATE dash_jobs SET freelancer_id = NULL WHERE freelancer_id = ? AND user_id = ?");
            $stmt_jobs->execute([$freelancer_id, $user_id]);

            // 2. Excluir tarifas
            $stmt_rates = $pdo->prepare("DELETE FROM dash_freelancer_rates WHERE freelancer_id = ? AND user_id = ?");
            $stmt_rates->execute([$freelancer_id, $user_id]);
            
            // 3. Excluir fornecedor
            $stmt = $pdo->prepare("DELETE FROM dash_freelancers WHERE id = ? AND user_id = ?");
            $stmt->execute([$freelancer_id, $user_id]);
            
            $_SESSION['temp_message'] = "Fornecedor excluído com sucesso!";
            $pdo->commit();
        }
    } catch (PDOException $e) {
        $pdo->rollBack(); 
        if ($e->getCode() == '23000') {
             $error = "<b>Erro:</b> Não foi possível excluir. Fornecedor em uso por registros dependentes.";
        } else {
            $error = "<b>Erro de Banco de Dados:</b> " . $e->getMessage();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erro: " . $e->getMessage();
    }
    
    if (empty($error) && $action === 'delete') {
        header("Location: freelancers_list.php");
        exit;
    }
}

// --- GET Logic ---
if (isset($_SESSION['temp_message'])) {
    $message = $_SESSION['temp_message'];
    unset($_SESSION['temp_message']);
}

// --- Busca ---
$search_term = trim($_GET['search_term'] ?? '');
$params = [$user_id];
$sql_search_where = '';

if (!empty($search_term)) {
    $sql_search_where = " AND (
                            f.name LIKE ? 
                            OR f.email LIKE ? 
                            OR f.country LIKE ?
                            OR r.service LIKE ?
                            OR r.lang_from LIKE ?
                            OR r.lang_to LIKE ?
                         )";
    $search_like = "%" . $search_term . "%";
    array_push($params, $search_like, $search_like, $search_like, $search_like, $search_like, $search_like);
}

// --- Buscar Fornecedores (SQL CORRIGIDO PARA MONOLÍNGUE) ---
$freelancers = [];
try {
    // O CASE abaixo garante que se lang_from for NULL, a concatenação não falha e exibe formato correto
    $sql = "
        SELECT 
            f.*,
            GROUP_CONCAT(
                DISTINCT CONCAT(
                    r.service, 
                    CASE 
                        WHEN r.lang_from IS NULL OR r.lang_from = '' THEN CONCAT(' (', r.lang_to, ')')
                        ELSE CONCAT(' (', r.lang_from, ' > ', r.lang_to, ')')
                    END
                ) SEPARATOR '|||'
            ) as services_tags
        FROM dash_freelancers f
        LEFT JOIN dash_freelancer_rates r ON f.id = r.freelancer_id AND r.user_id = f.user_id
        WHERE f.user_id = ?
        $sql_search_where
        GROUP BY f.id
        ORDER BY f.name ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $freelancers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "<b>Erro de Banco de Dados:</b> " . $e->getMessage();
}

$page_title = 'Lista de Fornecedores - Dash-T101';
include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <?php if ($message): ?><div id="success-alert" class="alert-success"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert-error"><i class="fas fa-exclamation-triangle"></i><?php echo $error; ?></div><?php endif; ?>

    <div class="video-card profile-header-card" style="background: linear-gradient(135deg, var(--brand-purple), #4a148c); border: none; margin-bottom: 25px;">
        <div class="header-icon-container" style="background: rgba(255, 255, 255, 0.1); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <i class="fas fa-user-friends" style="font-size: 1.8rem; color: #fff;"></i>
        </div>
        <div class="header-text-container" style="margin-left: 20px;">
            <h2 style="margin: 0 0 5px 0; padding: 0; font-size: 1.5rem; color: #fff; font-weight: 600; border: none;">Lista de Fornecedores</h2>
            <p style="margin: 0; color: rgba(255, 255, 255, 0.8); font-size: 1rem;">Gerencie, busque e adicione novos fornecedores.</p>
        </div>
    </div>
    
    <div class="report-nav-buttons">
        <a href="index.php" class="vision-btn vision-btn-secondary"><i class="fas fa-home"></i> Voltar ao Dash-T101</a>
        <a href="freelancers.php" class="vision-btn vision-btn-secondary"><i class="fas fa-plus"></i> Adicionar novo fornecedor</a>
    </div>

    <div class="video-card">
         <form method="GET" class="vision-form-refined" action="freelancers_list.php" style="padding: 25px 30px 30px;">
            <div class="form-row" style="grid-template-columns: 1fr auto; margin-bottom: 0;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="search_term"><i class="fas fa-search"></i> Buscar Fornecedor</label>
                    <input type="text" id="search_term" name="search_term" class="vision-input" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Nome, e-mail, serviço, idioma...">
                </div>
                <div class="form-group" style="margin-bottom: 0; justify-content: flex-end;">
                    <button type="submit" class="vision-btn vision-btn-primary" style="padding-left: 20px; padding-right: 20px;"><i class="fas fa-filter"></i> Buscar</button>
                    <?php if (!empty($search_term)): ?>
                        <a href="freelancers_list.php" class="vision-btn vision-btn-secondary" style="padding-left: 20px; padding-right: 20px;"><i class="fas fa-times"></i> Limpar</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <div class="video-card">
        <div class="card-header-refined">
            <h2><i class="fas fa-list"></i> Fornecedores Encontrados (<?php echo count($freelancers); ?>)</h2>
        </div>
        
        <?php if (empty($freelancers)): ?>
            <div class="alert-warning" style="margin: 20px 30px;">
                <i class="fas fa-info-circle"></i>
                <?php echo !empty($search_term) ? 'Nenhum fornecedor encontrado.' : 'Nenhum fornecedor cadastrado ainda.'; ?>
            </div>
        <?php else: ?>
            <div class="vision-table-container">
                <table class="vision-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-user"></i> Nome</th>
                            <th><i class="fas fa-envelope"></i> Contato</th>
                            <th><i class="fas fa-globe"></i> País</th>
                            <th><i class="fas fa-money-bill-wave"></i> Moeda</th>
                            <th><i class="fas fa-tags"></i> Serviços</th>
                            <th><i class="fas fa-tools"></i> Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($freelancers as $freelancer): ?>
                            <tr>
                                <td>
                                    <a href="freelancers.php?edit=<?php echo $freelancer['id']; ?>" class="project-name-refined" style="color: var(--brand-purple-light); text-decoration: none;">
                                        <?php echo htmlspecialchars($freelancer['name']); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="project-type-refined"><?php echo htmlspecialchars($freelancer['email']); ?></span>
                                    <?php if ($freelancer['phone']): ?>
                                        <span class="po-number-refined"><i class="fas fa-phone-alt" style="font-size:0.7rem;"></i> <?php echo htmlspecialchars($freelancer['phone']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($freelancer['country'] ?? '-'); ?></td>
                                <td><span class="status-badge-refined status-pending" style="font-weight: 600;"><?php echo htmlspecialchars($freelancer['currency'] ?? 'BRL'); ?></span></td>
                                <td class="value-cell-refined" style="width: 35%;">
                                    <?php 
                                    if (!empty($freelancer['services_tags'])) {
                                        $tags = explode('|||', $freelancer['services_tags']);
                                        echo '<div class="services-container">';
                                        foreach($tags as $tag) {
                                            echo '<span class="service-tag">' . htmlspecialchars($tag) . '</span>';
                                        }
                                        echo '</div>';
                                    } else {
                                        echo '<span class="text-muted" style="font-size:0.8rem;">Sem serviços cadastrados</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="action-buttons-refined">
                                        <a href="freelancers.php?edit=<?php echo $freelancer['id']; ?>" class="action-btn-refined action-btn-edit" title="Editar"><i class="fas fa-edit"></i></a>
                                        
                                        <form method="POST" action="freelancers_list.php" style="display: inline;" onsubmit="return confirm('Deseja realmente excluir?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="freelancer_id" value="<?php echo $freelancer['id']; ?>">
                                            <button type="submit" class="action-btn-refined action-btn-delete" title="Excluir"><i class="fas fa-trash"></i></button>
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
        <a href="freelancers.php" class="vision-btn vision-btn-secondary"><i class="fas fa-plus"></i> Adicionar novo fornecedor</a>
    </div>
</div>

<style>
/* Container de Tags de Serviço */
.services-container {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.service-tag {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #fff;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 500;
    display: inline-block;
    white-space: nowrap;
}

/* CSS Base do Layout (Reciclado para consistência) */
.main-content { padding-bottom: 100px; }
.profile-header-card { display: flex; align-items: center; justify-content: center; }
.header-icon-container { background: rgba(255, 255, 255, 0.1); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.header-icon-container i { font-size: 1.8rem; color: #fff; }
.header-text-container { margin-left: 20px; }
.header-text-container h2 { margin: 0 0 5px 0; padding: 0; font-size: 1.5rem; color: #fff; font-weight: 600; border: none; }
.header-text-container p { margin: 0; color: rgba(255, 255, 255, 0.8); font-size: 1rem; }
.report-nav-buttons { display: flex; gap: 15px; margin-bottom: 20px; }
.alert-success { opacity: 1; transition: opacity 1s ease-out; background: #22c55e; color: #fff; padding: 15px 30px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.alert-error { background: #ef4444; color: #fff; padding: 15px 30px; border-radius: 12px; margin-bottom: 20px; }
.alert-warning { background: rgba(255, 152, 0, 0.1); border: 1px solid rgba(255, 152, 0, 0.3); color: #ffb74d; padding: 15px 30px; border-radius: 12px; display: flex; align-items: center; gap: 10px; }
.main-content .video-card { margin-bottom: 20px; }
.video-card { background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.05)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; }
.video-card > h2 { margin: 0; padding: 25px 30px 20px; font-size: 1.3rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); }

.vision-form-refined { padding: 0px 30px 0px; }
.vision-form-refined .form-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px; }
.vision-form-refined .form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 0; }
.vision-form-refined .form-group label { font-weight: 600; color: var(--text-secondary); font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
.vision-input, .vision-select { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 12px 16px; color: var(--text-primary); font-size: 0.95rem; width: 100%; }

.vision-btn { background: var(--brand-purple); color: white; border: 1px solid var(--brand-purple); border-radius: 20px; padding: 12px 24px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); }
.vision-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3); }
.vision-btn-primary { background: var(--brand-purple); border-color: var(--brand-purple); }
.vision-btn-primary:hover { background: var(--brand-purple-dark); border-color: var(--brand-purple-dark); }
.vision-btn-secondary { background: rgba(255, 255, 255, 0.1); color: var(--text-primary); border-color: rgba(255, 255, 255, 0.2); }
.vision-btn-secondary:hover { background: rgba(255, 255, 255, 0.2) !important; border-color: rgba(255, 255, 255, 0.3) !important; color: #fff !important; }

.vision-table-container { margin: 0 30px 30px; overflow-x: auto; padding-bottom: 10px; }
.vision-table { width: 100%; border-collapse: collapse; }
.vision-table th { background: rgba(255, 255, 255, 0.05); border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding: 18px 20px; font-weight: 600; font-size: 0.9rem; color: var(--text-secondary); text-align: left; }
.vision-table td { padding: 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.04); font-size: 0.95rem; vertical-align: top; }
.vision-table tr:hover { background: rgba(255, 255, 255, 0.03); }

.status-badge-refined { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: capitalize; background: rgba(255, 255, 255, 0.1); color: var(--text-secondary); }
.status-badge-refined.status-pending { background: rgba(255, 193, 7, 0.1); color: #ffca28; }

.project-name-refined { font-weight: 600; color: var(--text-primary); display: block; margin-bottom: 4px; }
.project-type-refined { font-size: 0.85rem; color: var(--text-secondary); }
.po-number-refined { font-size: 0.8rem; color: var(--text-muted); background: rgba(255, 255, 255, 0.05); padding: 3px 8px; border-radius: 8px; margin-top: 5px; display: inline-block; }
.action-buttons-refined { display: flex; gap: 8px; align-items: center; }
.action-btn-refined { width: 36px; height: 36px; border-radius: 18px; border: 1px solid rgba(255, 255, 255, 0.1); color: var(--text-secondary); display: flex; align-items: center; justify-content: center; text-decoration: none; cursor: pointer; transition: all 0.3s ease; font-size: 0.9rem; background: rgba(255, 255, 255, 0.05); }
.action-btn-refined:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); }
.action-btn-edit:hover { background: var(--brand-purple); color: white; border-color: var(--brand-purple); }
.action-btn-delete:hover { background: var(--accent-orange); color: white; border-color: var(--accent-orange); }

.card-header-refined { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; padding: 25px 30px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); }
.card-header-refined h2 { margin: 0; font-size: 1.3rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 12px; }
</style>

<script>
(function() {
    "use strict";
    const successAlert = document.getElementById('success-alert');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.opacity = '0';
            setTimeout(() => { successAlert.remove(); }, 1000); 
        }, 5000);
    }
})();
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>