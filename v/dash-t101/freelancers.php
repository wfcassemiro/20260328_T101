<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';
require_once __DIR__ . '/../config/dash_functions.php';

if (!isLoggedIn()) { header('Location: /login.php'); exit; }

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';
$edit_freelancer = null;
$edit_rates = []; 

// Listas Padrão (Arrays PHP)
$default_services = ['Tradução', 'Revisão', 'Interpretação', 'Localização', 'Transcrição', 'Legendagem', 'MTPE'];
$default_languages = ['Português', 'Inglês', 'Espanhol', 'Francês', 'Alemão', 'Italiano', 'Chinês', 'Japonês'];
$default_units = ['Palavra', 'Hora', 'Diária', 'Minuto', 'Lauda', 'Projeto'];
$default_currencies = ['BRL', 'USD', 'EUR', 'GBP'];

// --- POST Processing ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    $pdo->beginTransaction();
    try {
        if ($action === 'add' || $action === 'edit') {
            $name = $_POST['name'];
            $email = $_POST['email'] ?? null;
            $phone = $_POST['phone'] ?? null;
            $country = $_POST['country'] ?? null;
            $currency = $_POST['currency'] ?? 'BRL';
            $notes = $_POST['notes'] ?? null;

            if (empty($name)) throw new Exception("O nome do fornecedor é obrigatório.");

            $freelancer_id = null;

            if ($action === 'add') {
                $stmt = $pdo->prepare("INSERT INTO dash_freelancers (user_id, name, email, phone, country, currency, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $name, $email, $phone, $country, $currency, $notes]);
                $freelancer_id = $pdo->lastInsertId();
                $_SESSION['temp_message'] = "Fornecedor adicionado com sucesso!";
            } else {
                $freelancer_id = $_POST['freelancer_id'];
                $stmt = $pdo->prepare("UPDATE dash_freelancers SET name = ?, email = ?, phone = ?, country = ?, currency = ?, notes = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$name, $email, $phone, $country, $currency, $notes, $freelancer_id, $user_id]);
                $_SESSION['temp_message'] = "Fornecedor atualizado!";
                
                $stmt_del = $pdo->prepare("DELETE FROM dash_freelancer_rates WHERE freelancer_id = ? AND user_id = ?");
                $stmt_del->execute([$freelancer_id, $user_id]);
            }
            
            // --- Salvar Tarifas ---
            $stmt_rate = $pdo->prepare("INSERT INTO dash_freelancer_rates (user_id, freelancer_id, service, lang_from, lang_to, rate, unit, currency) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            if (isset($_POST['rates_service'])) {
                for ($i = 0; $i < count($_POST['rates_service']); $i++) {
                    // Dados básicos
                    $service = $_POST['rates_service'][$i];
                    $unit = $_POST['rates_unit'][$i];
                    
                    // Monolíngue Check
                    $is_mono = isset($_POST['rates_is_monolingual'][$i]) && $_POST['rates_is_monolingual'][$i] == '1';
                    
                    // Idiomas
                    $lang_from = null;
                    if (!$is_mono) {
                        $lang_from = $_POST['rates_lang_from'][$i];
                        if ($lang_from === 'other') $lang_from = trim($_POST['rates_lang_from_other'][$i]);
                    }
                    
                    $lang_to = $_POST['rates_lang_to'][$i];
                    if ($lang_to === 'other') $lang_to = trim($_POST['rates_lang_to_other'][$i]);
                    
                    // Valor
                    $rate_str = str_replace(',', '.', $_POST['rates_rate'][$i]);
                    $rate = (float)$rate_str;
                    
                    // Validação mínima: Precisa de Serviço, Idioma Alvo (To) e Valor
                    // Se for monolingue, lang_from é NULL (o banco precisa aceitar NULL em lang_from)
                    if (!empty($service) && !empty($lang_to) && $rate >= 0) {
                        $stmt_rate->execute([
                            $user_id,
                            $freelancer_id,
                            $service,
                            $lang_from, 
                            $lang_to,
                            $rate,
                            $unit,
                            $currency // Usa a moeda principal do fornecedor
                        ]);
                    }
                }
            }
            
            $pdo->commit();
            header("Location: freelancers.php?edit=" . $freelancer_id);
            exit;
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erro ao salvar: " . $e->getMessage();
    }
}

// --- GET Logic ---
if (isset($_SESSION['temp_message'])) { $message = $_SESSION['temp_message']; unset($_SESSION['temp_message']); }

if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM dash_freelancers WHERE id = ? AND user_id = ?");
    $stmt->execute([$edit_id, $user_id]);
    $edit_freelancer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$edit_freelancer) { header("Location: freelancers.php"); exit; }
    
    $stmt_rates = $pdo->prepare("SELECT * FROM dash_freelancer_rates WHERE freelancer_id = ? AND user_id = ?");
    $stmt_rates->execute([$edit_id, $user_id]);
    $edit_rates = $stmt_rates->fetchAll(PDO::FETCH_ASSOC);
}

$page_title = 'Fornecedores - Dash-T101';
include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<style>
    /* Layout Geral */
    .main-content { padding-bottom: 100px; }
    .video-card { background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.05)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; margin-bottom: 20px; }
    .video-card > h2 { margin: 0; padding: 25px 30px 20px; font-size: 1.3rem; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid rgba(255, 255, 255, 0.06); display: flex; gap: 10px; align-items: center; }
    
    /* Header Roxo */
    .profile-header-card { display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--brand-purple), #4a148c); margin-bottom: 25px; padding: 20px; }
    .header-icon-container { background: rgba(255,255,255,0.1); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .header-text-container { margin-left: 20px; }
    .header-text-container h2 { margin: 0 0 5px 0; font-size: 1.5rem; color: #fff; }
    .header-text-container p { margin: 0; color: rgba(255,255,255,0.8); }

    /* Formulário */
    .vision-form { padding: 30px; }
    .form-row-flex { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px; }
    .form-group { display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 200px; margin-bottom: 15px; }
    .form-group label { font-weight: 600; color: var(--text-secondary); font-size: 0.9rem; }
    .vision-input, .vision-select { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 12px 16px; color: var(--text-primary); font-size: 0.95rem; width: 100%; box-sizing: border-box; }
    
    /* Botões */
    .vision-btn { background: var(--brand-purple); color: #fff; border: 0; border-radius: 20px; padding: 12px 24px; font-weight: 600; cursor: pointer; display: inline-flex; gap: 8px; align-items: center; text-decoration: none; transition: 0.2s; }
    .vision-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
    .vision-btn-secondary { background: rgba(255,255,255,0.1); color: #fff; }
    .vision-btn-secondary:hover { background: rgba(255,255,255,0.2); }
    .report-nav-buttons { display: flex; gap: 15px; margin-bottom: 20px; }

    /* Área de Tarifas - Layout Refinado */
    .rates-section { border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 20px; margin-top: 30px; background: rgba(0,0,0,0.1); }
    .section-label { font-size: 1.1rem; font-weight: 700; color: #fff; margin-bottom: 20px; display: block; }
    
    .rate-row { 
        display: flex; 
        align-items: flex-end; /* Alinha lixeira na base dos inputs */
        gap: 15px; 
        padding: 20px; 
        margin-bottom: 15px; 
        background: rgba(255,255,255,0.03); 
        border-radius: 12px; 
        border: 1px solid rgba(255,255,255,0.05);
        flex-wrap: wrap;
    }
    
    .rate-inputs-wrapper { 
        display: flex; 
        flex-wrap: wrap; 
        gap: 15px; 
        flex: 1; 
        width: 100%; 
    }
    
    /* Distribuição de Colunas */
    .rate-group-service { flex: 3; min-width: 220px; }
    .rate-group-lang { flex: 2; min-width: 150px; }
    .rate-group-value { flex: 1.5; min-width: 130px; }
    .rate-group-unit { flex: 2; min-width: 150px; }

    /* Botão (+) ao lado do select */
    .input-with-btn { display: flex; gap: 5px; }
    .btn-add-mini { 
        width: 42px; height: 42px; flex-shrink: 0;
        background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.1); 
        color: #fff; border-radius: 10px; cursor: pointer; 
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem; transition: 0.2s;
    }
    .btn-add-mini:hover { background: var(--brand-purple); border-color: var(--brand-purple); }

    /* Checkbox Monolingue */
    .mono-check-wrapper { margin-top: 8px; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #aaa; cursor: pointer; }
    .mono-check-wrapper input { accent-color: var(--brand-purple); width: 16px; height: 16px; cursor: pointer; }

    /* Botão Lixeira Alinhado */
    .btn-delete-rate {
        width: 42px; height: 42px;
        background: rgba(255, 59, 48, 0.1); border: 1px solid rgba(255, 59, 48, 0.3);
        color: #ff3b30; border-radius: 10px; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: 0.2s; 
        margin-bottom: 1px; /* Ajuste fino para alinhar com input */
        flex-shrink: 0;
    }
    .btn-delete-rate:hover { background: #ff3b30; color: #fff; }

    /* Modal */
    .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; display: none; align-items: center; justify-content: center; backdrop-filter: blur(5px); }
    .modal.active { display: flex; }
    .modal-box { background: #1e1e2d; padding: 25px; border-radius: 16px; width: 100%; max-width: 400px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
    .modal-box h3 { margin-top: 0; color: #fff; margin-bottom: 20px; }
    .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }

</style>

<div class="main-content">

    <div class="video-card profile-header-card">
        <div class="header-icon-container"><i class="fas fa-user-tie" style="font-size: 1.8rem; color: #fff;"></i></div>
        <div class="header-text-container">
            <h2>Cadastro de fornecedores</h2>
            <p>Gerencie seus tradutores, revisores e intérpretes.</p>
        </div>
    </div>
    
    <div class="report-nav-buttons">
        <a href="index.php" class="vision-btn vision-btn-secondary"><i class="fas fa-home"></i> Voltar</a>
        <a href="freelancers_list.php" class="vision-btn vision-btn-secondary"><i class="fas fa-list-ul"></i> Ver lista</a>
    </div>

    <?php if ($message): ?><div class="alert-success" style="background:#22c55e;color:#fff;padding:15px;border-radius:10px;margin-bottom:20px;"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert-error" style="background:#ef4444;color:#fff;padding:15px;border-radius:10px;margin-bottom:20px;"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div><?php endif; ?>

    <div class="video-card">
        <h2><i class="fas <?php echo $edit_freelancer ? 'fa-edit' : 'fa-user-plus'; ?>"></i> <?php echo $edit_freelancer ? 'Editar fornecedor' : 'Adicionar fornecedor'; ?></h2>
        
        <form method="POST" action="freelancers.php" class="vision-form">
            <input type="hidden" name="action" value="<?php echo $edit_freelancer ? 'edit' : 'add'; ?>">
            <?php if ($edit_freelancer): ?>
                <input type="hidden" name="freelancer_id" value="<?php echo $edit_freelancer['id']; ?>">
            <?php endif; ?>
            
            <div class="form-row-flex">
                <div class="form-group" style="flex: 2;">
                    <label for="name">Nome</label>
                    <input type="text" id="name" name="name" class="vision-input" value="<?php echo htmlspecialchars($edit_freelancer['name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="country">País</label>
                    <input type="text" id="country" name="country" class="vision-input" value="<?php echo htmlspecialchars($edit_freelancer['country'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-row-flex">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="vision-input" value="<?php echo htmlspecialchars($edit_freelancer['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="phone">Telefone</label>
                    <input type="text" id="phone" name="phone" class="vision-input" value="<?php echo htmlspecialchars($edit_freelancer['phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="currency">Moeda Padrão</label>
                    <select id="currency" name="currency" class="vision-input">
                        <?php foreach ($default_currencies as $cur): ?>
                            <option value="<?php echo $cur; ?>" <?php echo (($edit_freelancer['currency'] ?? 'BRL') == $cur) ? 'selected' : ''; ?>><?php echo $cur; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="notes">Observações</label>
                <textarea id="notes" name="notes" class="vision-input" rows="2"><?php echo htmlspecialchars($edit_freelancer['notes'] ?? ''); ?></textarea>
            </div>
            
            <div class="rates-section">
                <label class="section-label"><i class="fas fa-tags"></i> Serviços e Tarifas</label>
                
                <div id="rates_container">
                    <?php if (!empty($edit_rates)): ?>
                        <?php foreach ($edit_rates as $rate): 
                            $is_mono = empty($rate['lang_from']);
                            $is_lang_to_other = !in_array($rate['lang_to'], $default_languages) && !empty($rate['lang_to']);
                        ?>
                        <div class="rate-row">
                            <div class="rate-inputs-wrapper">
                                <div class="form-group rate-group-service">
                                    <label>Serviço</label>
                                    <div class="input-with-btn">
                                        <select name="rates_service[]" class="vision-input service-select">
                                            <?php foreach ($default_services as $service): ?>
                                                <option value="<?php echo $service; ?>" <?php echo ($rate['service'] == $service) ? 'selected' : ''; ?>><?php echo $service; ?></option>
                                            <?php endforeach; ?>
                                            <?php if (!in_array($rate['service'], $default_services)): ?>
                                                <option value="<?php echo htmlspecialchars($rate['service']); ?>" selected><?php echo htmlspecialchars($rate['service']); ?></option>
                                            <?php endif; ?>
                                        </select>
                                        <button type="button" class="btn-add-mini btn-add-service" title="Novo Serviço">+</button>
                                    </div>
                                    <label class="mono-check-wrapper">
                                        <input type="checkbox" class="mono-check" name="rates_is_monolingual_chk[]" <?php echo $is_mono ? 'checked' : ''; ?>>
                                        <input type="hidden" name="rates_is_monolingual[]" class="mono-val" value="<?php echo $is_mono ? '1' : '0'; ?>">
                                        <span>Monolíngue</span>
                                    </label>
                                </div>

                                <div class="form-group rate-group-lang group-from" style="<?php echo $is_mono ? 'display:none' : ''; ?>">
                                    <label>De (Origem)</label>
                                    <select name="rates_lang_from[]" class="vision-input lang-select">
                                        <option value="">Selecione</option>
                                        <?php foreach ($default_languages as $lang): ?>
                                            <option value="<?php echo $lang; ?>" <?php echo ($rate['lang_from'] == $lang) ? 'selected' : ''; ?>><?php echo $lang; ?></option>
                                        <?php endforeach; ?>
                                        <option value="other" <?php echo (!in_array($rate['lang_from'], $default_languages) && !$is_mono) ? 'selected' : ''; ?>>Outro...</option>
                                    </select>
                                    <input type="text" name="rates_lang_from_other[]" class="vision-input lang-other-input" placeholder="Qual?" style="<?php echo (!in_array($rate['lang_from'], $default_languages) && !$is_mono) ? 'display:block;margin-top:5px' : 'display:none'; ?>" value="<?php echo (!in_array($rate['lang_from'], $default_languages)) ? htmlspecialchars($rate['lang_from']) : ''; ?>">
                                </div>

                                <div class="form-group rate-group-lang group-to">
                                    <label class="label-to"><?php echo $is_mono ? 'Idioma' : 'Para (Destino)'; ?></label>
                                    <select name="rates_lang_to[]" class="vision-input lang-select">
                                        <?php foreach ($default_languages as $lang): ?>
                                            <option value="<?php echo $lang; ?>" <?php echo ($rate['lang_to'] == $lang) ? 'selected' : ''; ?>><?php echo $lang; ?></option>
                                        <?php endforeach; ?>
                                        <option value="other" <?php echo $is_lang_to_other ? 'selected' : ''; ?>>Outro...</option>
                                    </select>
                                    <input type="text" name="rates_lang_to_other[]" class="vision-input lang-other-input" placeholder="Qual?" style="<?php echo $is_lang_to_other ? 'display:block;margin-top:5px' : 'display:none'; ?>" value="<?php echo $is_lang_to_other ? htmlspecialchars($rate['lang_to']) : ''; ?>">
                                </div>

                                <div class="form-group rate-group-value">
                                    <label>Valor</label>
                                    <input type="text" name="rates_rate[]" class="vision-input" placeholder="0.00" value="<?php echo number_format($rate['rate'], 2, ',', '.'); ?>">
                                </div>

                                <div class="form-group rate-group-unit">
                                    <label>Unidade</label>
                                    <div class="input-with-btn">
                                        <select name="rates_unit[]" class="vision-input unit-select">
                                            <?php foreach ($default_units as $unit): ?>
                                                <option value="<?php echo $unit; ?>" <?php echo ($rate['unit'] == $unit) ? 'selected' : ''; ?>><?php echo $unit; ?></option>
                                            <?php endforeach; ?>
                                            <?php if (!in_array($rate['unit'], $default_units)): ?>
                                                <option value="<?php echo htmlspecialchars($rate['unit']); ?>" selected><?php echo htmlspecialchars($rate['unit']); ?></option>
                                            <?php endif; ?>
                                        </select>
                                        <button type="button" class="btn-add-mini btn-add-unit" title="Nova Unidade">+</button>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="button" class="btn-delete-rate"><i class="fas fa-trash-alt"></i></button>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <button type="button" id="add_rate_btn" class="vision-btn vision-btn-secondary" style="margin-top:15px;"><i class="fas fa-plus"></i> Nova Tarifa</button>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="vision-btn"><i class="fas fa-save"></i> Salvar</button>
            </div>
        </form>
    </div>
    <div class="report-nav-buttons">
        <a href="index.php" class="vision-btn vision-btn-secondary"><i class="fas fa-home"></i> Voltar</a>
        <a href="freelancers_list.php" class="vision-btn vision-btn-secondary"><i class="fas fa-list-ul"></i> Ver lista</a>
    </div>
</div>

<template id="rate_row_template">
    <div class="rate-row">
        <div class="rate-inputs-wrapper">
            <div class="form-group rate-group-service">
                <label>Serviço</label>
                <div class="input-with-btn">
                    <select name="rates_service[]" class="vision-input service-select">
                        <?php foreach ($default_services as $service): ?><option value="<?php echo $service; ?>"><?php echo $service; ?></option><?php endforeach; ?>
                    </select>
                    <button type="button" class="btn-add-mini btn-add-service">+</button>
                </div>
                <label class="mono-check-wrapper">
                    <input type="checkbox" class="mono-check">
                    <input type="hidden" name="rates_is_monolingual[]" class="mono-val" value="0">
                    <span>Monolíngue</span>
                </label>
            </div>

            <div class="form-group rate-group-lang group-from">
                <label>De (Origem)</label>
                <select name="rates_lang_from[]" class="vision-input lang-select">
                    <option value="" disabled selected>Selecione</option>
                    <?php foreach ($default_languages as $lang): ?><option value="<?php echo $lang; ?>"><?php echo $lang; ?></option><?php endforeach; ?>
                    <option value="other">Outro...</option>
                </select>
                <input type="text" name="rates_lang_from_other[]" class="vision-input lang-other-input" placeholder="Qual?" style="display:none; margin-top:5px;">
            </div>

            <div class="form-group rate-group-lang group-to">
                <label class="label-to">Para (Destino)</label>
                <select name="rates_lang_to[]" class="vision-input lang-select">
                    <option value="" disabled selected>Selecione</option>
                    <?php foreach ($default_languages as $lang): ?><option value="<?php echo $lang; ?>"><?php echo $lang; ?></option><?php endforeach; ?>
                    <option value="other">Outro...</option>
                </select>
                <input type="text" name="rates_lang_to_other[]" class="vision-input lang-other-input" placeholder="Qual?" style="display:none; margin-top:5px;">
            </div>

            <div class="form-group rate-group-value">
                <label>Valor</label>
                <input type="text" name="rates_rate[]" class="vision-input" placeholder="0.00">
            </div>

            <div class="form-group rate-group-unit">
                <label>Unidade</label>
                <div class="input-with-btn">
                    <select name="rates_unit[]" class="vision-input unit-select">
                        <?php foreach ($default_units as $unit): ?><option value="<?php echo $unit; ?>"><?php echo $unit; ?></option><?php endforeach; ?>
                    </select>
                    <button type="button" class="btn-add-mini btn-add-unit">+</button>
                </div>
            </div>
        </div>
        <button type="button" class="btn-delete-rate"><i class="fas fa-trash-alt"></i></button>
    </div>
</template>

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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('rates_container');
    const template = document.getElementById('rate_row_template');
    
    // Adicionar Linha
    document.getElementById('add_rate_btn').addEventListener('click', () => {
        const clone = template.content.cloneNode(true);
        container.appendChild(clone);
    });

    // Event Delegation
    container.addEventListener('click', function(e) {
        if (e.target.closest('.btn-delete-rate')) {
            e.target.closest('.rate-row').remove();
        }
        if (e.target.closest('.btn-add-service')) {
            openCustomModal('Serviço', e.target.closest('.input-with-btn').querySelector('select'));
        }
        if (e.target.closest('.btn-add-unit')) {
            openCustomModal('Unidade', e.target.closest('.input-with-btn').querySelector('select'));
        }
    });

    container.addEventListener('change', function(e) {
        const target = e.target;
        
        // Monolíngue Toggle
        if (target.classList.contains('mono-check')) {
            const row = target.closest('.rate-row');
            const valInput = row.querySelector('.mono-val');
            valInput.value = target.checked ? '1' : '0';
            
            const groupFrom = row.querySelector('.group-from');
            const labelTo = row.querySelector('.label-to');
            
            if (target.checked) {
                groupFrom.style.display = 'none';
                labelTo.textContent = 'Idioma';
            } else {
                groupFrom.style.display = 'flex';
                labelTo.textContent = 'Para (Destino)';
            }
        }
        
        // Select "Outro"
        if (target.classList.contains('lang-select')) {
            const otherInput = target.nextElementSibling;
            if (target.value === 'other') {
                otherInput.style.display = 'block';
                otherInput.focus();
            } else {
                otherInput.style.display = 'none';
                otherInput.value = '';
            }
        }
    });

    // Modal Custom
    const modal = document.getElementById('customItemModal');
    const title = document.getElementById('modalTitle');
    const input = document.getElementById('newItemName');
    const saveBtn = document.getElementById('btnSaveCustomItem');
    let currentSelectTarget = null; 

    window.openCustomModal = function(type, selectElement) {
        title.textContent = `Adicionar ${type}`;
        input.value = '';
        currentSelectTarget = selectElement;
        modal.classList.add('active');
        input.focus();
    };

    window.closeCustomModal = function() {
        modal.classList.remove('active');
        currentSelectTarget = null;
    };

    saveBtn.addEventListener('click', function() {
        const val = input.value.trim();
        if (val && currentSelectTarget) {
            const opt = new Option(val, val, true, true);
            currentSelectTarget.add(opt);
            closeCustomModal();
        }
    });
    
    modal.addEventListener('click', e => { if(e.target === modal) closeCustomModal(); });
});
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>