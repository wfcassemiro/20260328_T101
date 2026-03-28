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
$page_title = 'Configurações - Dash-T101';
$message = '';
$error = '';

// Lógica para SALVAR (Adicionar ou Atualizar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Ação: Adicionar Nova Moeda
        if (isset($_POST['action']) && $_POST['action'] === 'add_rate') {
            $code = strtoupper(trim($_POST['new_currency_code']));
            // Arredonda para 2 casas no recebimento
            $rate = round(floatval($_POST['new_currency_rate']), 2); 
            
            if (empty($code) || strlen($code) != 3) {
                throw new Exception("O código da moeda deve ter três caracteres (ex: CAD, JPY).");
            }
            if ($rate <= 0) {
                throw new Exception("A taxa de câmbio deve ser um valor positivo.");
            }
            if ($code === 'BRL') {
                throw new Exception("Não é possível adicionar BRL como uma taxa de câmbio (ela é a moeda base).");
            }

            $setting_key = 'rate_' . strtolower($code);

            // Usamos "INSERT ... ON DUPLICATE KEY UPDATE" para inserir ou atualizar
            $sql = "INSERT INTO dash_settings (user_id, setting_key, setting_value) 
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $setting_key, $rate]);
            
            $message = "Taxa para $code adicionada/atualizada.";
        }
        
        // Ação: Atualizar Taxas Existentes (do formulário da tabela)
        if (isset($_POST['action']) && $_POST['action'] === 'update_rates') {
            if (isset($_POST['rates']) && is_array($_POST['rates'])) {
                
                $pdo->beginTransaction();
                $sql = "UPDATE dash_settings SET setting_value = ? WHERE user_id = ? AND setting_key = ?";
                $stmt = $pdo->prepare($sql);
                
                foreach ($_POST['rates'] as $key => $value) {
                    // Validação rápida (deve começar com 'rate_' e ser do usuário)
                    if (strpos($key, 'rate_') === 0) {
                        // Arredonda para 2 casas ao salvar
                        $stmt->execute([round(floatval($value), 2), $user_id, $key]);
                    }
                }
                $pdo->commit();
                $message = "Taxas de câmbio atualizadas.";
            }
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erro: " . $e->getMessage();
    }
}

// Lógica para EXCLUIR
if (isset($_GET['action']) && $_GET['action'] === 'delete_rate') {
    try {
        $key_to_delete = $_GET['key'] ?? '';
        if (strpos($key_to_delete, 'rate_') === 0) { // Segurança
            $stmt = $pdo->prepare("DELETE FROM dash_settings WHERE user_id = ? AND setting_key = ?");
            $stmt->execute([$user_id, $key_to_delete]);
            $_SESSION['temp_message'] = "Taxa de câmbio removida.";
        } else {
            $_SESSION['temp_error'] = "Chave de configuração inválida.";
        }
    } catch (PDOException $e) {
        $_SESSION['temp_error'] = "Erro ao remover taxa: " . $e->getMessage();
    }
    header("Location: settings.php");
    exit;
}

// Lógica para CARREGAR as configurações
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM dash_settings WHERE user_id = ? AND setting_key LIKE 'rate_%' ORDER BY setting_key ASC");
    $stmt->execute([$user_id]);
    $existing_rates = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Se não houver nenhuma, insere padrões
    if (empty($existing_rates)) {
        $sql_default = "INSERT IGNORE INTO dash_settings (user_id, setting_key, setting_value) VALUES (?, 'rate_usd', '5.20'), (?, 'rate_eur', '5.60')";
        $pdo->prepare($sql_default)->execute([$user_id, $user_id]);
        
        // Recarrega
        $stmt->execute([$user_id]);
        $existing_rates = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    
} catch (PDOException $e) {
    $error = "Erro ao carregar configurações: " . $e->getMessage();
    $existing_rates = [];
}

// Mensagens de feedback (após redirect do delete)
if (isset($_SESSION['temp_message'])) {
    $message = $_SESSION['temp_message'];
    unset($_SESSION['temp_message']);
}
if (isset($_SESSION['temp_error'])) {
    $error = $_SESSION['temp_error'];
    unset($_SESSION['temp_error']);
}


// Incluir cabeçalhos
include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">

    <?php if ($message): ?><div id="success-alert" class="alert-success"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert-error"><i class="fas fa-exclamation-triangle"></i><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="video-card profile-header-card" style="background: linear-gradient(135deg, var(--brand-purple), #4a148c); border: none; margin-bottom: 25px;">
        <div class="header-icon-container" style="background: rgba(255, 255, 255, 0.1); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <i class="fas fa-cog" style="font-size: 1.8rem; color: #fff;"></i>
        </div>
        <div class="header-text-container" style="margin-left: 20px;">
            <h2 style="margin: 0 0 5px 0; padding: 0; font-size: 1.5rem; color: #fff; font-weight: 600; border: none;">Moedas</h2>
            <p style="margin: 0; color: rgba(255, 255, 255, 0.8); font-size: 1rem;">Defina as moedas e taxas de câmbio.</p>
        </div>
    </div>
    
    <div class="video-card">
        <h2><i class="fas fa-plus-circle"></i> Adicionar nova taxa de câmbio</h2>
        <form method="POST" class="vision-form-refined" action="settings.php">
            <input type="hidden" name="action" value="add_rate">
            <p style="color: var(--text-secondary); margin-top: -10px; margin-bottom: 20px; font-size: 0.9rem;">
                Adicione uma nova moeda (ex: CAD, JPY, GBP) e seu valor em BRL.
            </p>
            <div class="form-row" style="grid-template-columns: 1fr 2fr 1fr;">
                <div class="form-group">
                    <label for="new_currency_code">Código da moeda (três letras)</label>
                    <input type="text" id="new_currency_code" name="new_currency_code" class="vision-input" placeholder="Ex: CAD" maxlength="3" required>
                </div>
                
                <div class="form-group">
                    <label for="new_currency_rate">Valor em BRL</label>
                    <input type="number" id="new_currency_rate" name="new_currency_rate" class="vision-input" step="0.01" placeholder="Ex: 3,85" required>
                </div>
                
                <div class="form-group" style="justify-content: flex-end;">
                     <button type="submit" class="vision-btn vision-btn-primary" style="width: 100%;">
                        <i class="fas fa-plus"></i> Adicionar taxa
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="video-card">
        <form method="POST" action="settings.php">
            <input type="hidden" name="action" value="update_rates">
            <div class="card-header-refined">
                <h2><i class="fas fa-exchange-alt"></i> Taxas de câmbio atuais (Base: BRL)</h2>
                <button type="submit" class="vision-btn vision-btn-primary">
                    <i class="fas fa-save"></i> Salvar alterações
                </button>
            </div>
            
            <div class="vision-table-container">
                <table class="vision-table">
                    <thead>
                        <tr>
                            <th>Moeda</th>
                            <th>Câmbio</th>
                            <th style="width: 100px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($existing_rates)): ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: var(--text-muted);">Nenhuma taxa de câmbio configurada. Adicione uma acima.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($existing_rates as $key => $value): 
                                $code = strtoupper(str_replace('rate_', '', $key));
                            ?>
                                <tr>
                                    <td>
                                        <span class="project-name-refined"><?php echo htmlspecialchars($code); ?></span>
                                    </td>
                                    <td>
                                        <input type="number" name="rates[<?php echo htmlspecialchars($key); ?>]" class="vision-input" 
                                               step="0.01" 
                                               value="<?php echo htmlspecialchars(number_format($value, 2, '.', '')); ?>" 
                                               style="max-width: 250px;">
                                    </td>
                                    <td>
                                        <a href="?action=delete_rate&key=<?php echo htmlspecialchars($key); ?>" class="action-btn-refined action-btn-delete" title="Excluir Taxa" onclick="return confirm('Tem certeza que deseja remover a taxa <?php echo $code; ?>?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($existing_rates)): ?>
            <div class="form-actions" style="padding: 0 30px 30px 30px; justify-content: flex-end;">
                <button type="submit" class="vision-btn vision-btn-primary">
                    <i class="fas fa-save"></i> Salvar alterações
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>

</div>

<style>
/* Alertas */
.alert-success { opacity: 1; transition: opacity 1s ease-out; background: #22c55e; color: #fff; padding: 15px 30px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.alert-error { background: #ef4444; color: #fff; padding: 15px 30px; border-radius: 12px; margin-bottom: 20px; }

/* Cabeçalho Roxo */
.profile-header-card {
    display: flex;
    align-items: center;
    justify-content: center; /* Centralizado */
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
.header-icon-container i {
    font-size: 1.8rem; 
    color: #fff;
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

/* Layout do Card */
.main-content .video-card { margin-bottom: 20px; }
.video-card { background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.05)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; }
.video-card > h2 { margin: 0; padding: 25px 30px 20px; font-size: 1.3rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); }

/* Formulário */
.vision-form-refined { padding: 30px; }
.form-row { display: grid; gap: 20px; margin-bottom: 25px; }
.form-group { display: flex; flex-direction: column; }
.form-group label { margin-bottom: 8px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px; font-size: 0.9rem; }
.vision-input, .vision-select { 
    background: rgba(255, 255, 255, 0.05); 
    backdrop-filter: blur(20px); 
    border: 1px solid rgba(255, 255, 255, 0.1); 
    border-radius: 16px; 
    padding: 12px 16px; 
    color: var(--text-primary); 
    font-size: 0.95rem; 
    height: 48px; /* Altura fixa */
    box-sizing: border-box; /* Garante que padding não afete a altura */
}
.vision-input:focus, .vision-select:focus { border-color: var(--brand-purple); box-shadow: 0 0 0 3px rgba(142, 68, 173, 0.2); }
.form-actions { display: flex; gap: 15px; justify-content: flex-start; margin-top: 10px; }

/* Botões */
.vision-btn { 
    background: var(--brand-purple); 
    color: white; 
    border: 1px solid var(--brand-purple); 
    border-radius: 30px; 
    padding: 12px 24px; /* Padding vertical igual ao do input */
    font-weight: 600; 
    text-decoration: none; 
    display: inline-flex; 
    align-items: center; 
    justify-content: center; /* Centraliza texto/ícone no botão */
    gap: 8px; 
    cursor: pointer; 
    transition: all 0.3s ease; 
    font-size: 0.95rem; /* Fonte igual ao do input */
    height: 48px; /* Altura fixa igual ao input */
    box-sizing: border-box; /* Garante que padding não afete a altura */
}
.vision-btn:hover { background: var(--brand-purple-dark); transform: translateY(-1px); box-shadow: 0 6px 16px rgba(142, 68, 173, 0.4); }

.vision-btn-primary { 
    background: var(--brand-purple); 
    border-color: var(--brand-purple); 
}
.vision-btn-secondary { background: rgba(255, 255, 255, 0.1); color: var(--text-primary); border-color: rgba(255, 255, 255, 0.2); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); }
.vision-btn-secondary:hover { background: rgba(255, 255, 255, 0.15); border-color: rgba(255, 255, 255, 0.3); }

/* Cabeçalho da Lista */
.card-header-refined { display: flex; justify-content: space-between; align-items: center; padding: 25px 30px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); }
.card-header-refined h2 { margin: 0 !important; padding: 0 !important; font-size: 1.3rem; border: none; }

/* Tabela */
.vision-table-container { margin: 0; overflow-x: auto; padding-bottom: 10px; }
.vision-table { width: 100%; border-collapse: collapse; }
.vision-table th { background: rgba(255, 255, 255, 0.05); border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding: 18px 20px; font-weight: 600; font-size: 0.9rem; color: var(--text-secondary); text-align: left; }
.vision-table td { padding: 15px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.04); font-size: 0.95rem; vertical-align: middle; }
.vision-table tr:hover { background: rgba(255, 255, 255, 0.03); }
.vision-table tr:last-child td { border-bottom: none; }
.project-name-refined { font-weight: 600; color: var(--text-primary); font-size: 1rem; }
.action-buttons-refined { display: flex; gap: 8px; align-items: center; }
.action-btn-refined { width: 36px; height: 36px; border-radius: 30px; border: 1px solid rgba(255, 255, 255, 0.1); color: var(--text-secondary); display: flex; align-items: center; justify-content: center; text-decoration: none; cursor: pointer; transition: all 0.3s ease; font-size: 0.9rem; background: rgba(255, 255, 255, 0.05); }
.action-btn-refined:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); }
.action-btn-delete:hover { background: #ef4444; color: white; border-color: #ef4444; }

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
.quick-link-card, .filter-btn, .btn-download {
    cursor: pointer !important;
}
</style>

<script>
// Alerta de sucesso
document.addEventListener('DOMContentLoaded', function() {
    const successAlert = document.getElementById('success-alert');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.opacity = '0';
            setTimeout(() => { successAlert.remove(); }, 1000); 
        }, 5000);
    }
});
</script>

<?php
include __DIR__ . '/../vision/includes/footer.php';
?>