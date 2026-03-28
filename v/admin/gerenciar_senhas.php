<?php
session_start();

// Configurar fuso horário
date_default_timezone_set('America/Sao_Paulo');

// Adicionar as classes do PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP; 

require_once '../config/database.php';
require_once '../config/email_config.php';
require_once '../vendor/autoload.php';

// Verificar se é admin
if (!isset($_SESSION['user_id']) || (function_exists('isAdmin') && !isAdmin())) {
    if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        header('Location: ../login.php');
        exit;
    }
}

// =================================================================================
// PROCESSAMENTO AJAX (Lotes)
// =================================================================================
if (isset($_POST['action']) && $_POST['action'] === 'batch_process') {
    header('Content-Type: application/json');
    
    $user_ids = $_POST['user_ids'] ?? [];
    $generate_only = isset($_POST['generate_only']) && $_POST['generate_only'] == '1';
    
    $response = [
        'success_count' => 0,
        'error_count' => 0,
        'details' => []
    ];

    if (empty($user_ids)) {
        echo json_encode(['error' => 'Nenhum ID recebido']);
        exit;
    }

    // Preparar statement
    $stmtUser = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");

    foreach ($user_ids as $user_id) {
        try {
            $stmtUser->execute([$user_id]);
            $user = $stmtUser->fetch();

            if ($user) {
                $token = generatePasswordResetToken($user_id, $pdo);

                if ($generate_only) {
                    // Apenas gera
                    $response['success_count']++;
                } else {
                    // Envia email
                    $link = sendPasswordEmail($user, $token, $response['success_count'], $response['error_count']);
                }
            }
        } catch (Exception $e) {
            $response['error_count']++;
        }
    }

    echo json_encode($response);
    exit; // Encerra aqui para não carregar o HTML
}

$message = '';
$message_type = '';

// Função para gerar token de reset
function generatePasswordResetToken($user_id, $pdo) {
    $reset_token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("
        UPDATE users 
        SET password_reset_token = ?, password_reset_expires = DATE_ADD(NOW(), INTERVAL 7 DAY), first_login = TRUE 
        WHERE id = ?
    ");
    $stmt->execute([$reset_token, $user_id]);
    return $reset_token;
}

// Função para enviar email usando PHPMailer
function sendPasswordEmail($user, $token, &$success_count, &$error_count) {
    if (!class_exists(PHPMailer::class) || !defined('SMTP_HOST')) {
        error_log("Erro: PHPMailer não encontrado ou constantes SMTP não definidas.");
        $error_count++;
        return null;
    }

    $link = "https://" . $_SERVER['HTTP_HOST'] . "/definir_senha.php?token=" . $token;
    
    try {
        $mail = new PHPMailer(true);
        
        // Configurações do servidor
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 10;

        // Destinatário
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($user['email'], $user['name']);
        
        // Conteúdo
        $mail->isHTML(true);
        $mail->Subject = "Definição de Senha - Translators101";
        
        $email_body = "
        <div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #4a148c;'>Olá, {$user['name']}!</h2>
            <p>Você solicitou (ou foi solicitado pela administração) a definição de sua senha de acesso à plataforma <strong>Translators101</strong>.</p>
            <p>Clique no botão abaixo para criar sua nova senha:</p>
            <p style='margin: 30px 0;'>
                <a href='{$link}' style='background-color: #4a148c; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Definir Minha Senha</a>
            </p>
            <p>Ou copie e cole este link no seu navegador:</p>
            <p style='background: #f5f5f5; padding: 10px; word-break: break-all; font-size: 12px;'>{$link}</p>
            <hr style='border: 1px solid #eee; margin-top: 30px;'>
            <p style='font-size: 12px; color: #999;'>Este link é válido por 7 dias. Se você não solicitou isso, ignore este e-mail.</p>
        </div>
        ";

        $mail->Body = $email_body;
        $mail->AltBody = "Olá {$user['name']}. Use o link a seguir para definir sua senha no Translators101: $link";
        
        $mail->send();
        $success_count++;
        return $link;

    } catch (Exception $e) {
        error_log("Erro ao enviar senha para {$user['email']}: " . $mail->ErrorInfo);
        $error_count++;
        return null;
    }
}

// Buscar todos os usuários
try {
    $stmt = $pdo->prepare("
        SELECT id, name, email, hotmart_status, created_at, password_reset_expires, password_reset_token,
               CASE WHEN password_reset_token IS NOT NULL THEN TRUE ELSE FALSE END as has_pending_reset,
               CASE 
                   WHEN password_reset_token IS NULL THEN 'no_link'
                   WHEN password_reset_expires > NOW() THEN 'valid_link' 
                   ELSE 'expired_link'
               END as link_status,
               CASE
                   WHEN hotmart_status = 'ACTIVE' THEN 'active'
                   WHEN hotmart_status = 'INACTIVE' THEN 'inactive'
                   ELSE 'other'
               END as status_class
        FROM users 
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $all_users = $stmt->fetchAll();
    
    // Estatísticas
    $stats = [
        'total' => count($all_users),
        'no_link' => count(array_filter($all_users, fn($u) => $u['link_status'] === 'no_link')),
        'valid_link' => count(array_filter($all_users, fn($u) => $u['link_status'] === 'valid_link')),
        'expired_link' => count(array_filter($all_users, fn($u) => $u['link_status'] === 'expired_link')),
        'active_users' => count(array_filter($all_users, fn($u) => $u['hotmart_status'] === 'ACTIVE'))
    ];
    
} catch (Exception $e) {
    $all_users = [];
    $stats = ['total' => 0, 'no_link' => 0, 'valid_link' => 0, 'expired_link' => 0, 'active_users' => 0];
}

$page_title = "Gerenciar Senhas";
$active_page = 'gerenciar_senhas';

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-key"></i> Gerenciar Senhas - Interface Avançada</h1>
            <p>Sistema inteligente para envio de links de definição de senha</p>
        </div>
    </div>
    
    <?php if ($message): ?>
        <div class="<?php echo $message_type === 'success' ? 'success-alert' : ($message_type === 'warning' ? 'warning-alert' : 'error-alert'); ?>">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'times-circle'); ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="video-card glass-card">
        <h3><i class="fas fa-chart-bar"></i> Estatísticas</h3>
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total de Usuários</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $stats['active_users']; ?></div>
                <div class="stat-label">Usuários Ativos</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $stats['no_link']; ?></div>
                <div class="stat-label">Sem Link</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $stats['valid_link']; ?></div>
                <div class="stat-label">Links Válidos</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $stats['expired_link']; ?></div>
                <div class="stat-label">Links Expirados</div>
            </div>
        </div>
    </div>

    <div class="admin-form-section">
        <h3><i class="fas fa-paper-plane"></i> Enviar Links de Definição de Senha</h3>
        
        <form method="POST" class="admin-form" id="mainForm">
            
            <div class="action-buttons-section">
                <div class="action-group">
                    <button type="button" id="send_filtered_btn" class="cta-btn mass-action-btn">
                        <i class="fas fa-broadcast-tower"></i> Enviar para TODOS os filtrados (Visíveis)
                    </button>
                    <p class="action-info">
                        <i class="fas fa-info-circle"></i> 
                        Envia para todos os usuários que atendem aos filtros abaixo
                    </p>
                </div>
                
                <div class="action-group">
                    <button type="button" id="send_selected_btn" class="cta-btn selected-action-btn" disabled>
                        <i class="fas fa-paper-plane"></i> Selecione usuários para enviar
                    </button>
                    <p class="action-info">
                        <i class="fas fa-check-square"></i> 
                        Envia apenas para os usuários selecionados abaixo
                    </p>
                </div>
                
                <div class="action-group">
                    <button type="button" id="generate_only_btn" class="cta-btn generate-only-btn" disabled>
                        <i class="fas fa-link"></i> Gerar links sem enviar
                    </button>
                    <p class="action-info">
                        <i class="fas fa-link"></i> 
                        Apenas gera os links sem enviar emails
                    </p>
                </div>
            </div>

            <div class="filters-section">
                <h4><i class="fas fa-filter"></i> Filtros Avançados</h4>
                
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="search_filter">
                            <i class="fas fa-search"></i> Buscar por nome/email
                        </label>
                        <input type="text" id="search_filter" name="search_filter" placeholder="Digite nome ou email..." class="filter-input">
                    </div>
                    
                    <div class="filter-group">
                        <label for="hotmart_status_filter">
                            <i class="fas fa-star"></i> Status Hotmart
                        </label>
                        <select id="hotmart_status_filter" name="hotmart_status_filter" class="filter-input">
                            <option value="">Todos os status</option>
                            <option value="ACTIVE">ACTIVE - Ativos</option>
                            <option value="INACTIVE">INACTIVE - Inativos</option>
                            <option value="PENDING">PENDING - Pendentes</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="link_status_filter">
                            <i class="fas fa-link"></i> Status do Link
                        </label>
                        <select id="link_status_filter" name="link_status_filter" class="filter-input">
                            <option value="">Todos os links</option>
                            <option value="no_link">Sem link gerado</option>
                            <option value="valid_link">Link válido</option>
                            <option value="expired_link">Link expirado</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="period_filter">
                            <i class="fas fa-calendar"></i> Criados nos últimos
                        </label>
                        <select id="period_filter" name="period_filter" class="filter-input">
                            <option value="">Qualquer período</option>
                            <option value="1">1 dia</option>
                            <option value="7">7 dias</option>
                            <option value="30">30 dias</option>
                            <option value="90">90 dias</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="button" id="apply_filters" class="filter-btn">
                        <i class="fas fa-search"></i> Aplicar Filtros
                    </button>
                    <button type="button" id="clear_filters" class="filter-btn secondary">
                        <i class="fas fa-times"></i> Limpar
                    </button>
                    <span class="filter-results" id="filter_results">
                        Mostrando <?php echo count($all_users); ?> usuários
                    </span>
                </div>
            </div>

            <div class="users-section">
                <div class="section-header">
                    <h4><i class="fas fa-users"></i> Selecionar Usuários</h4>
                    <div class="selection-controls">
                        <label class="checkbox-label">
                            <input type="checkbox" id="select_all_users"> 
                            <span id="select_all_text">Selecionar todos os usuários visíveis</span>
                        </label>
                        <span class="users-count" id="users_count">
                            <span id="visible_count"><?php echo count($all_users); ?></span> usuários
                        </span>
                    </div>
                </div>

                <div class="users-cards-grid" id="users_grid">
                    <?php foreach ($all_users as $user): ?>
                        <div class="user-card" 
                             data-id="<?php echo $user['id']; ?>"
                             data-name="<?php echo strtolower(htmlspecialchars($user['name'])); ?>" 
                             data-email="<?php echo strtolower(htmlspecialchars($user['email'])); ?>"
                             data-hotmart-status="<?php echo $user['hotmart_status']; ?>"
                             data-link-status="<?php echo $user['link_status']; ?>"
                             data-created="<?php echo strtotime($user['created_at']); ?>">
                             
                            <div class="user-card-header">
                                <input type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>" 
                                       class="user-checkbox" id="user_<?php echo $user['id']; ?>">
                                <label for="user_<?php echo $user['id']; ?>" class="user-card-label">
                                    <div class="user-info">
                                        <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                                        <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                        <div class="user-meta">
                                            <span class="status-badge status-<?php echo $user['status_class']; ?>">
                                                <?php echo $user['hotmart_status']; ?>
                                            </span>
                                            <span class="link-badge link-<?php echo $user['link_status']; ?>">
                                                <?php 
                                                switch($user['link_status']) {
                                                    case 'no_link': echo 'Sem link'; break;
                                                    case 'valid_link': echo 'Link válido'; break;
                                                    case 'expired_link': echo 'Expirado'; break;
                                                }
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="no-results" id="no_results" style="display: none;">
                    <i class="fas fa-search"></i>
                    <p>Nenhum usuário encontrado com os filtros aplicados.</p>
                    <button type="button" onclick="clearAllFilters()" class="clear-filters-btn">
                        <i class="fas fa-times"></i> Limpar todos os filtros
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div id="progressModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <h3><i class="fas fa-sync fa-spin"></i> Processando...</h3>
        <p>Enviando em lotes para evitar sobrecarga do servidor.</p>
        
        <div class="progress-container">
            <div class="progress-bar" id="progressBar" style="width: 0%"></div>
        </div>
        
        <div class="progress-stats">
            <p>Processado: <span id="processedCount">0</span> de <span id="totalCount">0</span></p>
            <p class="text-success">Sucessos: <span id="successCount">0</span></p>
            <p class="text-error">Erros: <span id="errorCount">0</span></p>
        </div>
        
        <div class="modal-actions" id="modalActions" style="display: none;">
            <button onclick="closeModal()" class="cta-btn">Fechar</button>
        </div>
    </div>
</div>

<style>
/* Modal Styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(5px);
    z-index: 1000;
    display: flex;
    justify-content: center;
    align-items: center;
}

.modal-content {
    background: #1a1a1a;
    border: 1px solid #333;
    border-radius: 15px;
    padding: 30px;
    width: 90%;
    max-width: 500px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
}

.progress-container {
    background: #333;
    height: 20px;
    border-radius: 10px;
    margin: 20px 0;
    overflow: hidden;
}

.progress-bar {
    background: linear-gradient(90deg, #c084fc, #8b5cf6);
    height: 100%;
    transition: width 0.3s ease;
}

.progress-stats {
    display: flex;
    justify-content: space-around;
    margin-bottom: 20px;
    font-size: 0.9rem;
}

.text-success { color: #10b981; }
.text-error { color: #ef4444; }

/* Forçar cor branca para botões de ação */
.mass-action-btn,
.selected-action-btn,
.generate-only-btn {
    color: #ffffff !important;
}

.mass-action-btn i,
.selected-action-btn i,
.generate-only-btn i {
    color: #ffffff !important;
}

/* Alertas melhorados */
.success-alert {
    background: rgba(16, 185, 129, 0.2);
    border: 1px solid rgba(16, 185, 129, 0.5);
    color: #10f981;
    padding: 15px 20px;
    border-radius: 12px;
    margin: 20px 0;
    font-weight: 500;
    backdrop-filter: blur(10px);
}

.warning-alert {
    background: rgba(245, 158, 11, 0.2);
    border: 1px solid rgba(245, 158, 11, 0.5);
    color: #fbbf24;
    padding: 15px 20px;
    border-radius: 12px;
    margin: 20px 0;
    font-weight: 500;
    backdrop-filter: blur(10px);
}

.error-alert {
    background: rgba(239, 68, 68, 0.2);
    border: 1px solid rgba(239, 68, 68, 0.5);
    color: #ff6b6b;
    padding: 15px 20px;
    border-radius: 12px;
    margin: 20px 0;
    font-weight: 500;
    backdrop-filter: blur(10px);
}

/* Estatísticas */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.stat-item {
    text-align: center;
    padding: 20px;
    background: rgba(142, 68, 173, 0.2);
    border-radius: 15px;
    border: 1px solid rgba(142, 68, 173, 0.3);
    transition: transform 0.2s ease;
    backdrop-filter: blur(10px);
}

.stat-item:hover {
    transform: translateY(-2px);
    background: rgba(142, 68, 173, 0.3);
}

.stat-number {
    font-size: 2.2rem;
    font-weight: bold;
    color: #c084fc;
    margin-bottom: 8px;
}

.stat-label {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.8);
    font-weight: 500;
}

/* Seção de ações */
.action-buttons-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
    padding: 25px;
    background: rgba(142, 68, 173, 0.1);
    border: 1px solid rgba(142, 68, 173, 0.2);
    border-radius: 15px;
}

.action-group {
    text-align: center;
}

.mass-action-btn {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    margin-bottom: 10px;
    width: 100%;
    border: none;
    padding: 12px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
}

.mass-action-btn:hover {
    background: linear-gradient(135deg, #1d4ed8, #1e40af);
}

.selected-action-btn {
    background: linear-gradient(135deg, #c084fc, #a855f7);
    margin-bottom: 10px;
    width: 100%;
    border: none;
    padding: 12px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
}

.selected-action-btn:hover {
    background: linear-gradient(135deg, #a855f7, #9333ea);
}

.selected-action-btn:disabled {
    background: rgba(100, 100, 100, 0.3) !important;
    color: rgba(255, 255, 255, 0.5) !important;
    cursor: not-allowed;
}

.generate-only-btn {
    background: linear-gradient(135deg, #10b981, #059669);
    margin-bottom: 10px;
    width: 100%;
    border: none;
    padding: 12px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
}

.generate-only-btn:hover {
    background: linear-gradient(135deg, #059669, #047857);
}

.generate-only-btn:disabled {
    background: rgba(100, 100, 100, 0.3) !important;
    color: rgba(255, 255, 255, 0.5) !important;
    cursor: not-allowed;
}

.action-info {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.85rem;
    margin: 0;
    line-height: 1.3;
}

.action-info i {
    color: #c084fc;
    margin-right: 5px;
}

/* Seção de filtros */
.filters-section {
    margin-bottom: 30px;
    padding: 25px;
    background: rgba(30, 30, 30, 0.6);
    border-radius: 15px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.filters-section h4 {
    color: #c084fc;
    margin-bottom: 20px;
    font-size: 1.2rem;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.filter-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: white;
    font-size: 0.9rem;
}

.filter-group label i {
    color: #c084fc;
    margin-right: 8px;
}

.filter-input {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.3);
    color: white;
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.filter-input:focus {
    outline: none;
    border-color: #c084fc;
    box-shadow: 0 0 0 3px rgba(192, 132, 252, 0.1);
    background: rgba(0, 0, 0, 0.5);
}

.filter-input option {
    background: #1a1a1a;
    color: white;
}

.filter-actions {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.filter-btn {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
    color: white;
}

.filter-btn:hover {
    background: linear-gradient(135deg, #7c3aed, #6d28d9);
}

.filter-btn.secondary {
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.filter-btn.secondary:hover {
    background: rgba(255, 255, 255, 0.2);
    color: white;
}

.filter-results {
    color: #c084fc;
    font-weight: 600;
    font-size: 0.9rem;
}

/* Seção de usuários */
.users-section {
    margin-top: 20px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.section-header h4 {
    color: #c084fc;
    font-size: 1.2rem;
    margin: 0;
}

.selection-controls {
    display: flex;
    align-items: center;
    gap: 20px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-size: 0.95rem;
    color: white;
}

.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #c084fc;
}

.users-count {
    color: #c084fc;
    font-size: 0.9rem;
    font-weight: 600;
}

/* Grid de cards de usuários */
.users-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.user-card {
    background: rgba(25, 25, 25, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    transition: all 0.3s ease;
    cursor: pointer;
    backdrop-filter: blur(10px);
}

.user-card:hover {
    background: rgba(40, 40, 40, 0.9);
    border-color: rgba(192, 132, 252, 0.3);
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(192, 132, 252, 0.1);
}

.user-card-header {
    padding: 18px;
    display: flex;
    align-items: flex-start;
    gap: 15px;
}

.user-card input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #c084fc;
    margin-top: 3px;
    flex-shrink: 0;
}

.user-card-label {
    flex: 1;
    cursor: pointer;
    display: block;
}

.user-info {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.user-name {
    color: white;
    font-size: 1rem;
    font-weight: 600;
    line-height: 1.3;
}

.user-email {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

.user-meta {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-active {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-inactive {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.status-other {
    background: rgba(156, 163, 175, 0.2);
    color: #9ca3af;
    border: 1px solid rgba(156, 163, 175, 0.3);
}

.link-badge {
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
}

.link-no_link {
    background: rgba(156, 163, 175, 0.2);
    color: #9ca3af;
    border: 1px solid rgba(156, 163, 175, 0.3);
}

.link-valid_link {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.link-expired_link {
    background: rgba(245, 158, 11, 0.2);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

/* Estado sem resultados */
.no-results {
    text-align: center;
    padding: 40px 20px;
    color: rgba(255, 255, 255, 0.7);
    background: rgba(0, 0, 0, 0.2);
    border: 1px dashed rgba(255, 255, 255, 0.2);
    border-radius: 12px;
}

.no-results i {
    font-size: 2.5rem;
    color: #f39c12;
    margin-bottom: 15px;
    display: block;
}

.no-results p {
    font-size: 1rem;
    margin-bottom: 15px;
}

.clear-filters-btn {
    background: rgba(192, 132, 252, 0.2);
    color: #c084fc;
    border: 1px solid rgba(192, 132, 252, 0.3);
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.clear-filters-btn:hover {
    background: rgba(192, 132, 252, 0.3);
    border-color: rgba(192, 132, 252, 0.5);
}

/* Responsivo */
@media (max-width: 768px) {
    .action-buttons-section {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .filters-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .users-cards-grid {
        grid-template-columns: 1fr;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .selection-controls {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .filter-actions {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .user-card-header {
        padding: 15px;
    }
    
    .stat-number {
        font-size: 1.8rem;
    }
}
</style>

<script>
// Atualizar contadores e interface
function updateActionButtons() {
    const selectedCheckboxes = document.querySelectorAll('.user-checkbox:checked');
    const sendSelectedBtn = document.getElementById('send_selected_btn');
    const generateOnlyBtn = document.getElementById('generate_only_btn');
    const count = selectedCheckboxes.length;
    
    if (count > 0) {
        sendSelectedBtn.disabled = false;
        generateOnlyBtn.disabled = false;
        sendSelectedBtn.innerHTML = `<i class="fas fa-paper-plane"></i> Enviar para ${count} usuário${count > 1 ? 's' : ''} selecionado${count > 1 ? 's' : ''}`;
        generateOnlyBtn.innerHTML = `<i class="fas fa-link"></i> Gerar ${count} link${count > 1 ? 's' : ''} sem enviar`;
    } else {
        sendSelectedBtn.disabled = true;
        generateOnlyBtn.disabled = true;
        sendSelectedBtn.innerHTML = `<i class="fas fa-paper-plane"></i> Selecione usuários para enviar`;
        generateOnlyBtn.innerHTML = `<i class="fas fa-link"></i> Selecione usuários para gerar links`;
    }
}

function updateCounters() {
    const visibleCards = document.querySelectorAll('.user-card:not([style*="display: none"])');
    const visibleCount = visibleCards.length;
    const totalCount = document.querySelectorAll('.user-card').length;
    
    document.getElementById('visible_count').textContent = visibleCount;
    document.getElementById('filter_results').textContent = `Mostrando ${visibleCount} usuários`;
    document.getElementById('select_all_text').textContent = `Selecionar todos os usuários ${visibleCount < totalCount ? 'visíveis' : ''}`;
    
    // Atualiza botão de enviar filtrados
    const sendFilteredBtn = document.getElementById('send_filtered_btn');
    sendFilteredBtn.innerHTML = `<i class="fas fa-broadcast-tower"></i> Enviar para TODOS os filtrados (${visibleCount})`;
}

function applyFilters() {
    const searchTerm = document.getElementById('search_filter').value.toLowerCase().trim();
    const hotmartStatus = document.getElementById('hotmart_status_filter').value;
    const linkStatus = document.getElementById('link_status_filter').value;
    const period = document.getElementById('period_filter').value;
    
    const cards = document.querySelectorAll('.user-card');
    const noResults = document.getElementById('no_results');
    let visibleCount = 0;
    const now = Math.floor(Date.now() / 1000);
    
    cards.forEach(card => {
        const name = card.dataset.name || '';
        const email = card.dataset.email || '';
        const cardHotmartStatus = card.dataset.hotmartStatus || '';
        const cardLinkStatus = card.dataset.linkStatus || '';
        const cardCreated = parseInt(card.dataset.created) || 0;
        
        let isVisible = true;
        if (searchTerm && !name.includes(searchTerm) && !email.includes(searchTerm)) isVisible = false;
        if (hotmartStatus && cardHotmartStatus !== hotmartStatus) isVisible = false;
        if (linkStatus && cardLinkStatus !== linkStatus) isVisible = false;
        if (period) {
            const days = parseInt(period);
            const cutoff = now - (days * 24 * 60 * 60);
            if (cardCreated < cutoff) isVisible = false;
        }
        
        card.style.display = isVisible ? 'block' : 'none';
        if (isVisible) visibleCount++;
    });
    
    noResults.style.display = visibleCount === 0 ? 'block' : 'none';
    updateCounters();
    
    document.getElementById('select_all_users').checked = false;
    document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
    updateActionButtons();
}

function clearAllFilters() {
    document.getElementById('search_filter').value = '';
    document.getElementById('hotmart_status_filter').value = '';
    document.getElementById('link_status_filter').value = '';
    document.getElementById('period_filter').value = '';
    applyFilters();
}

// LÓGICA DE ENVIO EM LOTES (BATCH)
const BATCH_SIZE = 15; // Aumentado para 15 (mais rápido)
let processingQueue = [];
let processedCount = 0;
let successCount = 0;
let errorCount = 0;
let totalToProcess = 0;
let isGenerateOnly = false;

function showModal() {
    document.getElementById('progressModal').style.display = 'flex';
    document.getElementById('modalActions').style.display = 'none';
    document.getElementById('progressBar').style.width = '0%';
    document.getElementById('processedCount').textContent = '0';
    // Removida a linha que zerava o totalCount aqui
    document.getElementById('successCount').textContent = '0';
    document.getElementById('errorCount').textContent = '0';
}

function closeModal() {
    document.getElementById('progressModal').style.display = 'none';
    location.reload(); // Recarrega para atualizar status
}

async function processBatch() {
    if (processingQueue.length === 0) {
        // Finalizado
        document.getElementById('modalActions').style.display = 'block';
        document.querySelector('#progressModal h3').innerHTML = '<i class="fas fa-check-circle text-success"></i> Concluído!';
        document.getElementById('progressBar').style.width = '100%';
        return;
    }

    const batch = processingQueue.splice(0, BATCH_SIZE);
    
    try {
        const formData = new FormData();
        formData.append('action', 'batch_process');
        if (isGenerateOnly) formData.append('generate_only', '1');
        
        batch.forEach(id => formData.append('user_ids[]', id));

        const response = await fetch('', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        
        successCount += data.success_count;
        errorCount += data.error_count;
        processedCount += batch.length;

        // Atualiza UI
        const percent = (processedCount / totalToProcess) * 100;
        document.getElementById('progressBar').style.width = percent + '%';
        document.getElementById('processedCount').textContent = processedCount;
        document.getElementById('successCount').textContent = successCount;
        document.getElementById('errorCount').textContent = errorCount;

        // Próximo lote
        processBatch();

    } catch (err) {
        console.error('Erro no lote:', err);
        errorCount += batch.length;
        processedCount += batch.length;
        document.getElementById('errorCount').textContent = errorCount;
        processBatch();
    }
}

function startBatchProcessing(ids, generateOnly = false) {
    if (ids.length === 0) return;
    
    processingQueue = [...ids];
    totalToProcess = ids.length;
    processedCount = 0;
    successCount = 0;
    errorCount = 0;
    isGenerateOnly = generateOnly;
    
    showModal();
    
    // Define o total APÓS abrir o modal para garantir que não seja sobrescrito
    document.getElementById('totalCount').textContent = totalToProcess;
    
    processBatch();
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    // Filtros e UI
    document.getElementById('apply_filters').addEventListener('click', applyFilters);
    document.getElementById('clear_filters').addEventListener('click', clearAllFilters);
    
    document.querySelectorAll('.filter-input').forEach(input => {
        input.addEventListener('input', applyFilters);
        input.addEventListener('change', applyFilters);
    });
    
    document.getElementById('select_all_users').addEventListener('change', function() {
        const visibleCheckboxes = document.querySelectorAll('.user-card:not([style*="display: none"]) .user-checkbox');
        visibleCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateActionButtons();
    });
    
    document.querySelectorAll('.user-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateActionButtons);
    });

    document.querySelectorAll('.user-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (e.target.type === 'checkbox' || e.target.tagName === 'LABEL' || e.target.closest('label')) return;
            const checkbox = this.querySelector('input[type="checkbox"]');
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change'));
            }
        });
    });

    // BOTÕES DE AÇÃO (Agora chamam o processamento em lote)
    
    // 1. Enviar Selecionados
    document.getElementById('send_selected_btn').addEventListener('click', function() {
        const selected = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
        if (confirm(`Deseja enviar email para ${selected.length} usuários selecionados?`)) {
            startBatchProcessing(selected, false);
        }
    });

    // 2. Gerar Apenas Links (Selecionados)
    document.getElementById('generate_only_btn').addEventListener('click', function() {
        const selected = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
        if (confirm(`Deseja gerar links para ${selected.length} usuários selecionados?`)) {
            startBatchProcessing(selected, true);
        }
    });

    // 3. Enviar para Todos os Filtrados (Visíveis)
    document.getElementById('send_filtered_btn').addEventListener('click', function() {
        const visibleCards = document.querySelectorAll('.user-card:not([style*="display: none"])');
        const ids = Array.from(visibleCards).map(card => card.dataset.id);
        
        if (ids.length === 0) {
            alert('Nenhum usuário visível para enviar.');
            return;
        }

        if (confirm(`ATENÇÃO: Você vai enviar email para ${ids.length} usuários listados abaixo. Confirmar?`)) {
            startBatchProcessing(ids, false);
        }
    });

    // Inicialização
    updateCounters();
    updateActionButtons();
});
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>