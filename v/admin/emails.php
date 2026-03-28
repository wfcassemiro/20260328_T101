<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email_config.php';
require_once __DIR__ . '/../config/email.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: /login.php');
    exit;
}

// Lógica para preenchimento via sessão (Radar)
$prefill_subject = '';
$prefill_message = '';
if (isset($_SESSION['draft_to_send'])) {
    $prefill_subject = $_SESSION['draft_to_send']['subject'] ?? '';
    $prefill_message = $_SESSION['draft_to_send']['content'] ?? '';
    unset($_SESSION['draft_to_send']);
}

// Libera a trava de sessão do PHP para evitar travamento de requisições simultâneas
session_write_close();

// ==========================================
// AJAX ENDPOINTS PARA ENVIO ANTI-TIMEOUT
// ==========================================
$ajax_action = $_POST['action'] ?? '';
$json_input = [];

// Identifica se a requisição veio via JSON (fetch com application/json)
if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $json_input = json_decode(file_get_contents('php://input'), true) ?: [];
    if (isset($json_input['action'])) {
        $ajax_action = $json_input['action'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($ajax_action, 'ajax_') === 0) {
    // Dá tempo de sobra para a execução do lote e limpa outputs HTML indesejados
    set_time_limit(300);
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');

    // 1. Obter lista de destinatários
    if ($ajax_action === 'ajax_get_recipients') {
        $recipient_type    = $_POST['recipient_type'] ?? 'all';
        $selected_users    = $_POST['selected_users'] ?? [];
        $custom_emails     = trim($_POST['custom_emails'] ?? '');
        $selected_lead_ids = isset($_POST['selected_lead_ids']) && !empty($_POST['selected_lead_ids']) ? json_decode($_POST['selected_lead_ids'], true) : [];

        $recipients = [];

        try {
            if ($recipient_type === 'selected' && !empty($selected_users)) {
                $placeholders = implode(',', array_fill(0, count($selected_users), '?'));
                $stmt = $pdo->prepare("SELECT id, email, name FROM users WHERE id IN ($placeholders) AND is_active = 1");
                $stmt->execute($selected_users);
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            } elseif ($recipient_type === 'all') {
                $stmt = $pdo->query("SELECT id, email, name FROM users WHERE is_active = 1");
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            } elseif ($recipient_type === 'subscribers') {
                $stmt = $pdo->query("SELECT id, email, name FROM users WHERE is_active = 1 AND (is_subscriber = 1 OR role = 'subscriber' OR (subscription_expires IS NOT NULL AND subscription_expires > NOW()))");
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            } elseif ($recipient_type === 'non_subscribers') {
                $stmt = $pdo->query("SELECT id, email, name FROM users WHERE is_active = 1 AND (is_subscriber = 0 OR is_subscriber IS NULL) AND role != 'subscriber' AND (subscription_expires IS NULL OR subscription_expires <= NOW())");
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            } elseif ($recipient_type === 'with_password') {
                $stmt = $pdo->query("SELECT id, email, name FROM users WHERE is_active = 1 AND password_hash IS NOT NULL AND password_hash != ''");
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            } elseif ($recipient_type === 'without_password') {
                $stmt = $pdo->query("SELECT id, email, name FROM users WHERE is_active = 1 AND (password_hash IS NULL OR password_hash = '')");
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            } elseif ($recipient_type === 'leads_radar') {
                $stmt = $pdo->query("SELECT l.id, l.email, l.nome AS name FROM leads l INNER JOIN lead_interesses li ON l.id = li.lead_id WHERE li.interesse = 'radar_t101' AND COALESCE(l.pode_receber_email, 1) = 1");
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            } elseif ($recipient_type === 'leads_agenda') {
                $stmt = $pdo->query("SELECT l.id, l.email, l.nome AS name FROM leads l INNER JOIN lead_interesses li ON l.id = li.lead_id WHERE li.interesse = 'agenda' AND COALESCE(l.pode_receber_email, 1) = 1");
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            } elseif ($recipient_type === 'leads_agenda_radar') {
                $stmt = $pdo->query("SELECT l.id, l.email, l.nome AS name FROM leads l INNER JOIN lead_interesses li_a ON l.id = li_a.lead_id AND li_a.interesse = 'agenda' INNER JOIN lead_interesses li_r ON l.id = li_r.lead_id AND li_r.interesse = 'radar_t101' WHERE COALESCE(l.pode_receber_email, 1) = 1");
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            } elseif ($recipient_type === 'leads_fundo') {
                $stmt = $pdo->query("SELECT id, email, nome as name FROM leads_fundo WHERE pode_receber_email = 1");
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            } elseif ($recipient_type === 'leads_fundo_7d') {
                $stmt = $pdo->query("SELECT id, email, nome as name FROM leads_fundo WHERE pode_receber_email = 1 AND trial_type = 'trial_7d'");
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            } elseif ($recipient_type === 'leads_fundo_24h') {
                $stmt = $pdo->query("SELECT id, email, nome as name FROM leads_fundo WHERE pode_receber_email = 1 AND trial_type = 'trial_24h'");
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            } elseif ($recipient_type === 'leads_fundo_selected') {
                if (!empty($selected_lead_ids)) {
                    $placeholders = implode(',', array_fill(0, count($selected_lead_ids), '?'));
                    $stmt = $pdo->prepare("SELECT id, email, name FROM users WHERE id IN ($placeholders)");
                    $stmt->execute($selected_lead_ids);
                    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }

            // Adicionar emails avulsos
            if (!empty($custom_emails)) {
                $custom_list = preg_split('/[\s,;\n\r]+/', $custom_emails);
                foreach ($custom_list as $email) {
                    $email = trim($email);
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $exists = false;
                        foreach ($recipients as $r) {
                            if (strtolower($r['email']) === strtolower($email)) { $exists = true; break; }
                        }
                        if (!$exists) {
                            $recipients[] = ['id' => null, 'email' => $email, 'name' => explode('@', $email)[0]];
                        }
                    }
                }
            }

            if (empty($recipients)) {
                echo json_encode(['success' => false, 'error' => 'Nenhum destinatário encontrado para este filtro.']);
                exit;
            }

            echo json_encode(['success' => true, 'recipients' => $recipients]);
            exit;

        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => 'Erro SQL: ' . $e->getMessage()]);
            exit;
        }
    }

    // 2. Processar lote de envios
    if ($ajax_action === 'ajax_send_batch') {
        $batch = $json_input['batch'] ?? [];
        $subject = $json_input['subject'] ?? '';
        $message_body = $json_input['message'] ?? '';
        $access_link = $json_input['access_link'] ?? '';
        $recipient_type = $json_input['recipient_type'] ?? '';

        if (!isEmailConfigured()) {
            echo json_encode(['success' => false, 'error' => 'SMTP não configurado.', 'sent' => 0, 'failed' => count($batch)]);
            exit;
        }

        $emailSender = new EmailSender();
        $sent_count = 0;
        $failed_count = 0;
        $failed_emails = [];
        $lead_ids_to_update = [];

        foreach ($batch as $recipient) {
            try {
                $personalized_message = str_replace('[NOME]', $recipient['name'], $message_body);
                if (!empty($access_link)) {
                    $personalized_message = str_replace('[LINK]', $access_link, $personalized_message);
                }

                if ($personalized_message !== strip_tags($personalized_message)) {
                    $final_body = $personalized_message;
                } else {
                    $final_body = nl2br(htmlspecialchars($personalized_message));
                }

                $html_content = EmailTemplates::getCustomEmailTemplate($subject, $final_body);

                $result = $emailSender->sendEmail(
                    $recipient['email'],
                    $recipient['name'],
                    $subject,
                    $html_content
                );

                if ($result) {
                    $sent_count++;
                    if (strpos($recipient_type, 'leads_fundo') !== false && !empty($recipient['id'])) {
                        $lead_ids_to_update[] = $recipient['id'];
                    }
                } else {
                    $failed_count++;
                    $failed_emails[] = $recipient['email'];
                }

                usleep(150000); // Aguarda 0.15s entre cada email do lote

            } catch (Throwable $e) { // Usar Throwable pega até erros fatais do PHP
                $failed_count++;
                $failed_emails[] = $recipient['email'] . ' (Erro: ' . $e->getMessage() . ')';
            }
        }

        if ($sent_count > 0 && !empty($lead_ids_to_update)) {
            try {
                $placeholders = implode(',', array_fill(0, count($lead_ids_to_update), '?'));
                $stmt = $pdo->prepare("UPDATE leads_fundo SET email_sent_at = NOW(), email_sent_count = COALESCE(email_sent_count, 0) + 1 WHERE id IN ($placeholders)");
                $stmt->execute($lead_ids_to_update);
            } catch (Throwable $e) {
                error_log("[Emails] Erro update leads_fundo: " . $e->getMessage());
            }
        }

        echo json_encode([
            'success' => true,
            'sent' => $sent_count,
            'failed' => $failed_count,
            'errors' => $failed_emails
        ]);
        exit;
    }

    // 3. Salvar Log no Banco
    if ($ajax_action === 'ajax_log') {
        $subject = $_POST['subject'] ?? '';
        $message_body = $_POST['message'] ?? '';
        $recipient_count = $_POST['recipient_count'] ?? 0;
        $recipient_type = $_POST['recipient_type'] ?? '';
        $lecture_id = !empty($_POST['lecture_id']) ? $_POST['lecture_id'] : null;
        $access_link = $_POST['access_link'] ?? '';
        $status = $_POST['status'] ?? 'sent';

        try {
            $stmt = $pdo->prepare("INSERT INTO email_logs (subject, message, recipient_count, recipient_type, sent_by, status, lecture_id, access_link, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$subject, $message_body, $recipient_count, $recipient_type, $_SESSION['user_id'], $status, $lecture_id, $access_link]);
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// ====
// GERENCIAMENTO DA UI E TEMPLATES
// ====
$page_title = 'Sistema de E-mails - Admin';
$message = '';
$error = '';

$templates_file = __DIR__ . '/../config/email_templates.json';
$saved_templates = [];
if (file_exists($templates_file)) {
    $saved_templates = json_decode(file_get_contents($templates_file), true) ?: [];
}

// Processar salvamento de template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_template') {
    $template_name = trim($_POST['template_name'] ?? '');
    $template_subject = trim($_POST['template_subject'] ?? '');
    $template_message = trim($_POST['template_message'] ?? '');

    if (!empty($template_name) && !empty($template_subject) && !empty($template_message)) {
        $saved_templates[$template_name] = [
            'subject'    => $template_subject,
            'message'    => $template_message,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        if (file_put_contents($templates_file, json_encode($saved_templates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            $message = "✅ Template '$template_name' salvo com sucesso!";
        } else {
            $error = "❌ Erro ao salvar template. Verifique as permissões do diretório.";
        }
    } else {
        $error = "❌ Nome, assunto e mensagem do template são obrigatórios.";
    }
}

// Processar exclusão de template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_template') {
    $template_name = $_POST['template_name'] ?? '';
    if (isset($saved_templates[$template_name])) {
        unset($saved_templates[$template_name]);
        file_put_contents($templates_file, json_encode($saved_templates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $message = "🗑️ Template '$template_name' excluído!";
    }
}

// Processar envio de TESTE de email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_email') {
    $test_email = trim($_POST['test_email'] ?? '');
    if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email inválido.';
    } else {
        if (isEmailConfigured()) {
            try {
                $emailSender  = new EmailSender();
                $html_content = EmailTemplates::getCustomEmailTemplate(
                    'Teste de Email - Translators101',
                    '<h2>🎉 Teste bem-sucedido!</h2><p>HTML sendo renderizado corretamente.</p>'
                );
                $result = $emailSender->sendEmail($test_email, 'Admin', '🧪 Teste HTML', $html_content);
                if ($result) $message = "✅ Teste enviado para: {$test_email}";
                else $error = "❌ Falha no envio de teste.";
            } catch (Exception $e) { $error = "❌ Erro: " . $e->getMessage(); }
        } else { $error = "⚠️ SMTP não configurado."; }
    }
}

// ====
// ESTATÍSTICAS
// ====
$total_users = 0; $total_subscribers = 0; $total_sent = 0;
$users_with_password = 0; $users_without_password = 0;
$total_leads = 0; $total_leads_radar = 0; $total_leads_agenda_radar = 0;
$total_leads_fundo = 0; $total_leads_fundo_7d = 0; $total_leads_fundo_24h = 0;
$recent_emails = []; $next_lecture = null; $all_lectures = []; $all_users = [];

try { $total_users            = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(); } catch (PDOException $e) {}
try { $total_subscribers      = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1 AND (is_subscriber = 1 OR role = 'subscriber' OR (subscription_expires IS NOT NULL AND subscription_expires > NOW()))")->fetchColumn(); } catch (PDOException $e) {}
try { $users_with_password    = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1 AND password_hash IS NOT NULL AND password_hash != ''")->fetchColumn(); } catch (PDOException $e) {}
try { $users_without_password = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1 AND (password_hash IS NULL OR password_hash = '')")->fetchColumn(); } catch (PDOException $e) {}

try { $total_leads = $pdo->query("SELECT COUNT(DISTINCT li.lead_id) FROM lead_interesses li INNER JOIN leads l ON l.id = li.lead_id WHERE li.interesse = 'agenda' AND COALESCE(l.pode_receber_email, 1) = 1")->fetchColumn(); } catch (PDOException $e) {}
try { $total_leads_radar = $pdo->query("SELECT COUNT(DISTINCT li.lead_id) FROM lead_interesses li INNER JOIN leads l ON l.id = li.lead_id WHERE li.interesse = 'radar_t101' AND COALESCE(l.pode_receber_email, 1) = 1")->fetchColumn(); } catch (PDOException $e) {}
try { $total_leads_agenda_radar = $pdo->query("SELECT COUNT(DISTINCT l.id) FROM leads l INNER JOIN lead_interesses li_a ON l.id = li_a.lead_id AND li_a.interesse = 'agenda' INNER JOIN lead_interesses li_r ON l.id = li_r.lead_id AND li_r.interesse = 'radar_t101' WHERE COALESCE(l.pode_receber_email, 1) = 1")->fetchColumn(); } catch (PDOException $e) {}
try {
    $total_leads_fundo     = $pdo->query("SELECT COUNT(*) FROM leads_fundo WHERE pode_receber_email = 1")->fetchColumn();
    $total_leads_fundo_7d  = $pdo->query("SELECT COUNT(*) FROM leads_fundo WHERE pode_receber_email = 1 AND trial_type = 'trial_7d'")->fetchColumn();
    $total_leads_fundo_24h = $pdo->query("SELECT COUNT(*) FROM leads_fundo WHERE pode_receber_email = 1 AND trial_type = 'trial_24h'")->fetchColumn();
} catch (PDOException $e) {}

try { $total_sent    = $pdo->query("SELECT COUNT(*) FROM email_logs WHERE status = 'sent'")->fetchColumn(); } catch (PDOException $e) {}
try { $recent_emails = $pdo->query("SELECT * FROM email_logs ORDER BY created_at DESC LIMIT 10")->fetchAll() ?: []; } catch (PDOException $e) {}
try { $next_lecture  = $pdo->query("SELECT id, title, speaker, description, announcement_date, lecture_time FROM upcoming_announcements WHERE announcement_date >= CURDATE() AND is_active = 1 ORDER BY announcement_date ASC, lecture_time ASC LIMIT 1")->fetch(); } catch (PDOException $e) {}
try { $all_lectures  = $pdo->query("SELECT id, title, speaker, announcement_date, lecture_time, description FROM upcoming_announcements WHERE is_active = 1 ORDER BY announcement_date DESC")->fetchAll() ?: []; } catch (PDOException $e) {}

try {
    $stmt = $pdo->query("
        SELECT id, name, email, role,
               COALESCE(is_subscriber, 0) as is_subscriber,
               CASE WHEN password_hash IS NOT NULL AND password_hash != '' THEN 1 ELSE 0 END as has_password,
               created_at,
               CASE
                   WHEN role = 'admin' THEN 'Admin'
                   WHEN COALESCE(is_subscriber, 0) = 1 OR role = 'subscriber' THEN 'Assinante'
                   ELSE 'Free'
               END as user_type
        FROM users
        WHERE COALESCE(is_active, 1) = 1
        ORDER BY name ASC
    ");
    $all_users = $stmt->fetchAll() ?: [];
} catch (PDOException $e) {}

$email_configured = isEmailConfigured();

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-envelope"></i> Sistema de E-mails</h1>
            <p>Envio de emails em massa para usuários da plataforma</p>
        </div>
    </div>

    <div id="progressContainer" class="video-card glass-card" style="display: none; border: 1px solid #3b82f6; margin-bottom: 20px;">
        <h3 class="section-title" style="color: #3b82f6;"><i class="fas fa-satellite-dish fa-spin"></i> Processando Envios...</h3>
        <div style="background: rgba(0,0,0,0.5); border-radius: 10px; height: 24px; width: 100%; overflow: hidden; margin-bottom: 12px; border: 1px solid rgba(255,255,255,0.1);">
            <div id="progressBar" style="background: linear-gradient(90deg, #c084fc, #3b82f6); width: 0%; height: 100%; transition: width 0.4s ease; box-shadow: 0 0 10px rgba(59, 130, 246, 0.5);"></div>
        </div>
        <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 1.1rem; color: #fff;">
            <span id="progressText">0 / 0 enviados</span>
            <span id="progressPercentage" style="color: #c084fc;">0%</span>
        </div>
        <div id="progressErrors" style="color: #ef4444; font-size: 0.9rem; margin-top: 15px; padding: 10px; background: rgba(239, 68, 68, 0.1); border-radius: 6px; display: none;"></div>
    </div>

    <div id="statusAlertContainer">
        <?php if ($message): ?><div class="success-alert"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error-alert"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div><?php endif; ?>
    </div>

    <div id="importAlert" class="import-alert" style="display: none;">
        <i class="fas fa-check-circle"></i> <span id="importAlertText"></span>
    </div>

    <div class="video-card glass-card">
        <h3 class="section-title"><i class="fas fa-chart-bar"></i> Estatísticas</h3>
        <div class="stats-row">
            <div class="stat-item"><div class="stat-number"><?php echo $total_users; ?></div><div class="stat-label">Total de Usuários</div></div>
            <div class="stat-item"><div class="stat-number"><?php echo $total_subscribers; ?></div><div class="stat-label">Assinantes</div></div>
            <div class="stat-item"><div class="stat-number"><?php echo $total_users - $total_subscribers; ?></div><div class="stat-label">Não Assinantes</div></div>
        </div>
        <div class="stats-row stats-row-5">
            <div class="stat-item stat-green">
                <div class="stat-number"><?php echo $users_with_password; ?></div>
                <div class="stat-label">Com Senha</div>
            </div>
            <div class="stat-item stat-blue">
                <div class="stat-number"><?php echo $total_leads_radar; ?></div>
                <div class="stat-label">Leads Radar T101</div>
            </div>
            <div class="stat-item stat-pink">
                <div class="stat-number"><?php echo $total_leads; ?></div>
                <div class="stat-label">Leads Agenda</div>
            </div>
            <div class="stat-item stat-purple">
                <div class="stat-number"><?php echo $total_leads_agenda_radar; ?></div>
                <div class="stat-label">Agenda + Radar 🔥</div>
            </div>
            <div class="stat-item stat-orange">
                <div class="stat-number"><?php echo $total_leads_fundo; ?></div>
                <div class="stat-label">Leads Fundo</div>
            </div>
        </div>
    </div>

    <div class="three-column-grid">
        <div class="video-card glass-card compact-card">
            <h3 class="section-title"><i class="fas fa-cog"></i> Status SMTP</h3>
            <div class="config-status-mini <?php echo $email_configured ? 'configured' : 'not-configured'; ?>">
                <i class="fas <?php echo $email_configured ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
                <span><?php echo $email_configured ? 'Configurado' : 'Não Configurado'; ?></span>
            </div>
            <?php if ($email_configured): ?><p class="smtp-info"><?php echo SMTP_HOST; ?>:<?php echo SMTP_PORT; ?></p><?php endif; ?>
        </div>
        <div class="video-card glass-card compact-card">
            <h3 class="section-title"><i class="fas fa-flask"></i> Testar Email</h3>
            <form method="POST" class="test-form">
                <input type="hidden" name="action" value="test_email">
                <input type="email" name="test_email" class="form-control" placeholder="seu@email.com" required>
                <button type="submit" class="cta-btn btn-small" <?php echo !$email_configured ? 'disabled' : ''; ?>><i class="fas fa-paper-plane"></i> Testar</button>
            </form>
        </div>
        <div class="video-card glass-card compact-card">
            <h3 class="section-title"><i class="fas fa-user-circle"></i> Remetente</h3>
            <?php if ($email_configured): ?>
                <p class="sender-info"><?php echo SMTP_FROM_NAME; ?></p>
                <p class="sender-email"><?php echo SMTP_FROM_EMAIL; ?></p>
            <?php else: ?>
                <p class="sender-info">Não configurado</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($next_lecture): ?>
    <div class="video-card glass-card">
        <h3 class="section-title"><i class="fas fa-calendar-alt"></i> Próxima Palestra</h3>
        <div class="next-lecture-info">
            <div class="lecture-details">
                <h4><?php echo htmlspecialchars($next_lecture['title']); ?></h4>
                <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($next_lecture['speaker']); ?></p>
                <p><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($next_lecture['announcement_date'])); ?> às <?php echo date('H:i', strtotime($next_lecture['lecture_time'])); ?>h</p>
            </div>
            <button type="button" class="cta-btn" onclick="useNextLectureTemplate()"><i class="fas fa-envelope"></i> Criar Email</button>
        </div>
    </div>
    <?php endif; ?>

    <div class="two-column-grid">
        <div class="video-card glass-card">
            <h3 class="section-title"><i class="fas fa-users"></i> Destinatários</h3>
            <div class="form-group">
                <label><i class="fas fa-users"></i> Selecionar grupo</label>
                <select name="recipient_type" id="recipient_type" form="emailForm" class="form-control" onchange="toggleUserSelection()">
                    <option value="all">Todos os usuários (<?php echo $total_users; ?>)</option>
                    <option value="subscribers">Apenas assinantes (<?php echo $total_subscribers; ?>)</option>
                    <option value="non_subscribers">Não assinantes (<?php echo $total_users - $total_subscribers; ?>)</option>
                    <option value="with_password">Com senha (<?php echo $users_with_password; ?>)</option>
                    <option value="without_password">Sem senha (<?php echo $users_without_password; ?>)</option>
                    <optgroup label="📋 Leads">
                        <option value="leads_radar">Leads do Radar T101 (<?php echo $total_leads_radar; ?>)</option>
                        <option value="leads_agenda">Leads da Agenda (<?php echo $total_leads; ?>)</option>
                        <option value="leads_agenda_radar">🔥 Leads Agenda + Radar (<?php echo $total_leads_agenda_radar; ?>)</option>
                        <option value="leads_fundo">Leads fundo de funil - Todos (<?php echo $total_leads_fundo; ?>)</option>
                        <option value="leads_fundo_7d">Leads fundo - Trial 7 Dias (<?php echo $total_leads_fundo_7d; ?>)</option>
                        <option value="leads_fundo_24h">Leads fundo - Trial 24h (<?php echo $total_leads_fundo_24h; ?>)</option>
                    </optgroup>
                    <option value="selected">Selecionar da lista</option>
                    <option value="custom">Apenas e-mails avulsos</option>
                </select>
            </div>
        </div>
        <div class="video-card glass-card">
            <h3 class="section-title"><i class="fas fa-at"></i> E-mails adicionais</h3>
            <div class="form-group">
                <label><i class="fas fa-envelope-open-text"></i> Lista Manual</label>
                <textarea name="custom_emails" id="custom_emails" form="emailForm" class="form-control" rows="3" placeholder="email1@dominio.com, email2@dominio.com"></textarea>
            </div>
        </div>
    </div>

    <form class="admin-form" id="emailForm">
        <input type="hidden" name="selected_lead_ids" id="selected_lead_ids" value="">
        <input type="hidden" name="force_resend" id="force_resend" value="0">

        <div id="userSelectionContainer" class="video-card glass-card" style="display: none;">
            <h3 class="section-title"><i class="fas fa-user-check"></i> Selecionar usuários</h3>
            <div class="two-column-grid">
                <div class="form-group"><label>Buscar</label><input type="text" id="userSearch" class="form-control" placeholder="Nome ou email..."></div>
                <div class="form-group"><label>Filtrar</label>
                    <select id="filterUserType" class="form-control" onchange="filterUsers()">
                        <option value="">Todos</option>
                        <option value="admin">Admins</option>
                        <option value="assinante">Assinantes</option>
                        <option value="free">Free</option>
                    </select>
                </div>
            </div>
            <div class="selection-controls">
                <button type="button" class="btn-secondary" onclick="selectAllUsers()">Selecionar visíveis</button>
                <button type="button" class="btn-secondary" onclick="deselectAllUsers()">Desmarcar</button>
                <span class="selected-count"><span id="selectedCount">0</span> selecionado(s)</span>
            </div>
            <div class="users-list" id="usersList">
                <?php foreach ($all_users as $user): ?>
                <div class="user-item"
                     data-name="<?php echo strtolower(htmlspecialchars($user['name'])); ?>"
                     data-email="<?php echo strtolower(htmlspecialchars($user['email'])); ?>"
                     data-type="<?php echo strtolower($user['user_type']); ?>">
                    <label class="user-checkbox-label">
                        <input type="checkbox" name="selected_users[]" value="<?php echo $user['id']; ?>" class="user-checkbox" onchange="updateSelectedCount()">
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($user['name']); ?></span>
                            <span class="user-email"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <span class="user-type-badge <?php echo strtolower($user['user_type']); ?>"><?php echo $user['user_type']; ?></span>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="two-column-grid">
            <div class="video-card glass-card">
                <h3 class="section-title"><i class="fas fa-layer-group"></i> Lote</h3>
                <div class="form-group">
                    <label>Tamanho do envio simultâneo</label>
                    <select name="batch_size" id="batch_size" class="form-control">
                        <option value="5">5 (Mais seguro - evita Timeout)</option>
                        <option value="10" selected>10 (Recomendado)</option>
                        <option value="25">25 (Rápido)</option>
                        <option value="50">50 (Pode causar timeout novamente)</option>
                    </select>
                </div>
            </div>
            <div class="video-card glass-card">
                <h3 class="section-title"><i class="fas fa-chalkboard-teacher"></i> Palestra</h3>
                <div class="form-group">
                    <label>Preencher com dados de palestra</label>
                    <select name="lecture_id" id="lecture_id" class="form-control" onchange="fillLectureTemplate()">
                        <option value="">Selecione...</option>
                        <?php foreach ($all_lectures as $lecture): ?>
                        <option value="<?php echo $lecture['id']; ?>"
                                data-title="<?php echo htmlspecialchars($lecture['title'], ENT_QUOTES); ?>"
                                data-speaker="<?php echo htmlspecialchars($lecture['speaker'], ENT_QUOTES); ?>"
                                data-date="<?php echo date('d/m/Y', strtotime($lecture['announcement_date'])); ?>"
                                data-time="<?php echo date('H:i', strtotime($lecture['lecture_time'])); ?>"
                                data-description="<?php echo htmlspecialchars($lecture['description'] ?? '', ENT_QUOTES); ?>">
                            <?php echo date('d/m/Y', strtotime($lecture['announcement_date'])); ?> - <?php echo htmlspecialchars($lecture['title']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="two-column-grid">
            <div class="video-card glass-card">
                <h3 class="section-title"><i class="fas fa-heading"></i> Assunto</h3>
                <div class="form-group">
                    <input type="text" name="subject" id="subject" class="form-control" placeholder="Assunto do e-mail" required>
                </div>
            </div>
            <div class="video-card glass-card">
                <h3 class="section-title"><i class="fas fa-link"></i> Link (Opcional)</h3>
                <div class="form-group">
                    <input type="url" name="access_link" id="access_link" class="form-control" placeholder="URL para [LINK]">
                </div>
            </div>
        </div>

        <div class="video-card glass-card">
            <h3 class="section-title"><i class="fas fa-align-left"></i> Mensagem</h3>
            <div class="form-group">
                <textarea name="message" id="message" class="form-control" rows="12" placeholder="Digite a mensagem ou HTML aqui..." required></textarea>
            </div>
            <div id="forceResendContainer" style="display: none; margin-top: 15px; padding: 12px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0;">
                    <input type="checkbox" id="forceResendCheckbox" onchange="document.getElementById('force_resend').value = this.checked ? '1' : '0';">
                    <span style="color: #ef4444; font-weight: 600;"><i class="fas fa-exclamation-triangle"></i> Forçar reenvio</span>
                </label>
            </div>
            <div class="form-actions">
                <button type="submit" class="cta-btn" id="btnSendEmails" <?php echo !$email_configured ? 'disabled' : ''; ?>><i class="fas fa-paper-plane"></i> Iniciar Envio</button>
                <button type="button" class="cta-btn btn-secondary-large" onclick="openSaveTemplateModal()"><i class="fas fa-save"></i> Salvar Template</button>
            </div>
        </div>
    </form>

    <div class="video-card glass-card">
        <h3 class="section-title">
            <i class="fas fa-magic"></i> Templates
            <button type="button" class="cta-btn" onclick="openManageTemplatesModal()"><i class="fas fa-edit"></i> Editar</button>
        </h3>
        <div class="quick-actions-grid">
            <div class="quick-action-card" onclick="useTemplate('welcome')"><div class="quick-action-icon" style="color: #3b82f6;"><i class="fas fa-hand-wave"></i></div><h4>Boas-vindas</h4></div>
            <div class="quick-action-card" onclick="useTemplate('newsletter')"><div class="quick-action-icon" style="color: #8b5cf6;"><i class="fas fa-newspaper"></i></div><h4>Newsletter</h4></div>
            <div class="quick-action-card" onclick="useTemplate('promotion')"><div class="quick-action-icon" style="color: #10b981;"><i class="fas fa-percentage"></i></div><h4>Promoção</h4></div>
            <div class="quick-action-card" onclick="useTemplate('reminder')"><div class="quick-action-icon" style="color: #ef4444;"><i class="fas fa-bell"></i></div><h4>Lembrete</h4></div>
            <div class="quick-action-card" onclick="useTemplate('lecture')"><div class="quick-action-icon" style="color: #f59e0b;"><i class="fas fa-video"></i></div><h4>Palestra</h4></div>
            <div class="quick-action-card" onclick="useTemplate('leads_sorteio')"><div class="quick-action-icon" style="color: #ec4899;"><i class="fas fa-gift"></i></div><h4>Sorteio</h4></div>
            <div class="quick-action-card" onclick="useTemplate('planos_assinatura')"><div class="quick-action-icon" style="color: #06b6d4;"><i class="fas fa-star"></i></div><h4>Planos</h4></div>
            <?php foreach ($saved_templates as $name => $template): ?>
            <div class="quick-action-card saved-template" onclick="useSavedTemplate('<?php echo htmlspecialchars($name, ENT_QUOTES); ?>')">
                <div class="quick-action-icon" style="color: #06b6d4;"><i class="fas fa-bookmark"></i></div>
                <h4><?php echo htmlspecialchars($name); ?></h4>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!empty($recent_emails)): ?>
    <div class="video-card glass-card">
        <h3 class="section-title"><i class="fas fa-history"></i> Histórico Recente</h3>
        <div class="table-container">
            <table class="certificates-table">
                <thead><tr><th>Data</th><th>Assunto</th><th>Qtd</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($recent_emails as $email): ?>
                    <tr>
                        <td><?php echo date('d/m/Y H:i', strtotime($email['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars(substr($email['subject'], 0, 40)); ?></td>
                        <td><?php echo $email['recipient_count']; ?></td>
                        <td>
                            <?php if ($email['status'] === 'sent'): ?>
                                <span class="status-sent"><i class="fas fa-check"></i></span>
                            <?php else: ?>
                                <span class="status-failed"><i class="fas fa-times"></i></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<div id="saveTemplateModal" class="modal">
    <div class="modal-content glass-modal">
        <span class="close" onclick="closeSaveTemplateModal()">&times;</span>
        <h3>Salvar Template</h3>
        <form method="POST">
            <input type="hidden" name="action" value="save_template">
            <div class="form-group"><label>Nome</label><input type="text" name="template_name" class="form-control" required></div>
            <div class="form-group"><label>Assunto</label><input type="text" name="template_subject" id="save_template_subject" class="form-control" required></div>
            <div class="form-group"><label>Mensagem</label><textarea name="template_message" id="save_template_message" class="form-control" rows="8" required></textarea></div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeSaveTemplateModal()">Cancelar</button>
                <button type="submit" class="cta-btn">Salvar</button>
            </div>
        </form>
    </div>
</div>

<div id="manageTemplatesModal" class="modal">
    <div class="modal-content glass-modal modal-large">
        <span class="close" onclick="closeManageTemplatesModal()">&times;</span>
        <h3>Gerenciar Templates</h3>
        <?php if (empty($saved_templates)): ?>
            <p>Nenhum template salvo.</p>
        <?php else: ?>
        <div class="templates-list">
            <?php foreach ($saved_templates as $name => $template): ?>
            <div class="template-item">
                <div class="template-info">
                    <strong><?php echo htmlspecialchars($name); ?></strong>
                    <span><?php echo htmlspecialchars(substr($template['subject'], 0, 50)); ?></span>
                </div>
                <div class="template-actions">
                    <button type="button" class="btn-icon btn-primary" onclick="editTemplate('<?php echo htmlspecialchars($name, ENT_QUOTES); ?>')"><i class="fas fa-edit"></i></button>
                    <button type="button" class="btn-icon" onclick="useSavedTemplate('<?php echo htmlspecialchars($name, ENT_QUOTES); ?>'); closeManageTemplatesModal();"><i class="fas fa-play"></i></button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir?')">
                        <input type="hidden" name="action" value="delete_template">
                        <input type="hidden" name="template_name" value="<?php echo htmlspecialchars($name); ?>">
                        <button class="btn-icon btn-danger"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="modal-actions">
            <button type="button" class="btn-secondary" onclick="closeManageTemplatesModal()">Fechar</button>
        </div>
    </div>
</div>

<script>
// ==========================================
// FUNÇÃO PARA TRATAR A RESPOSTA DO SERVIDOR
// ==========================================
async function fetchWithSafeJson(url, options) {
    const response = await fetch(url, options);
    const textData = await response.text();
    try {
        return JSON.parse(textData);
    } catch (err) {
        console.error("Resposta Inválida do Servidor:", textData);
        throw new Error("O servidor demorou muito e enviou uma página de erro ao invés de prosseguir (Timeout/Limites). Diminua o 'Tamanho do Lote' e tente novamente.");
    }
}

// ==========================================
// LÓGICA DE ENVIO AJAX (ANTI-TIMEOUT)
// ==========================================
document.getElementById('emailForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const type = document.getElementById('recipient_type').value;
    if (type === 'selected' && document.querySelectorAll('.user-checkbox:checked').length === 0 && !document.getElementById('custom_emails').value.trim()) {
        alert('Selecione usuários ou adicione emails.'); return false;
    }
    if (type === 'custom' && !document.getElementById('custom_emails').value.trim()) {
        alert('Adicione emails.'); return false;
    }
    if (!confirm(`Iniciar disparo em lote para: ${document.querySelector(`#recipient_type option[value="${type}"]`)?.textContent || type}?`)) {
        return false;
    }

    const btn = document.getElementById('btnSendEmails');
    const progContainer = document.getElementById('progressContainer');
    const progBar = document.getElementById('progressBar');
    const progText = document.getElementById('progressText');
    const progPercent = document.getElementById('progressPercentage');
    const progErrors = document.getElementById('progressErrors');
    const statusAlert = document.getElementById('statusAlertContainer');
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparando...';
    progContainer.style.display = 'block';
    progErrors.style.display = 'none';
    progErrors.innerHTML = '';
    statusAlert.innerHTML = '';
    
    const formData = new FormData(this);
    formData.append('action', 'ajax_get_recipients');

    try {
        // Obter Destinatários (Com tratamento seguro do Json)
        const data = await fetchWithSafeJson(window.location.href, { method: 'POST', body: formData });

        if (!data.success) throw new Error(data.error);
        
        const recipients = data.recipients;
        const total = recipients.length;
        let sentTotal = 0;
        let failedTotal = 0;
        const batchSize = parseInt(document.getElementById('batch_size').value) || 10;

        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Disparando...';

        // Lidar com o envio de pacotes em lotes controlados
        for (let i = 0; i < total; i += batchSize) {
            const batch = recipients.slice(i, i + batchSize);
            
            const batchPayload = {
                action: 'ajax_send_batch',
                batch: batch,
                subject: document.getElementById('subject').value,
                message: document.getElementById('message').value,
                access_link: document.getElementById('access_link').value,
                recipient_type: type
            };

            const batchData = await fetchWithSafeJson(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(batchPayload)
            });

            if(!batchData.success && typeof batchData.success !== "undefined") {
                 throw new Error(batchData.error);
            }

            sentTotal += batchData.sent;
            failedTotal += batchData.failed;

            if (batchData.errors && batchData.errors.length > 0) {
                progErrors.style.display = 'block';
                progErrors.innerHTML += batchData.errors.join('<br>') + '<br>';
            }

            const currentProcessed = Math.min(i + batchSize, total);
            const percent = Math.round((currentProcessed / total) * 100);
            progBar.style.width = percent + '%';
            progText.innerText = `${currentProcessed} / ${total} processados...`;
            progPercent.innerText = percent + '%';
        }

        // Registrar no Histórico do BD
        const logData = new FormData();
        logData.append('action', 'ajax_log');
        logData.append('subject', document.getElementById('subject').value);
        logData.append('message', document.getElementById('message').value);
        logData.append('recipient_count', total);
        logData.append('recipient_type', type);
        logData.append('lecture_id', document.getElementById('lecture_id').value);
        logData.append('access_link', document.getElementById('access_link').value);
        logData.append('status', sentTotal > 0 ? 'sent' : 'failed');

        await fetch(window.location.href, { method: 'POST', body: logData });

        btn.innerHTML = '<i class="fas fa-check"></i> Envio Finalizado';
        progText.innerText = `Concluído! Total da lista: ${total}`;
        statusAlert.innerHTML = `<div class="success-alert"><i class="fas fa-check-circle"></i> Disparo finalizado! <b>${sentTotal} sucesso(s)</b> e <b>${failedTotal} falha(s)</b>.</div>`;

    } catch (err) {
        statusAlert.innerHTML = `<div class="error-alert"><i class="fas fa-exclamation-circle"></i> Erro na operação: ${err.message}</div>`;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Tentar Novamente';
        btn.disabled = false;
    }
});

// ==========================================
// FUNÇÕES AUXILIARES DA UI MANTIDAS
// ==========================================
const defaultTemplates = {
    welcome:           { subject: 'Bem-vindo(a) à Translators101!',  message: `Olá [NOME],\n\nSeja bem-vindo(a)!\n\nEquipe Translators101` },
    newsletter:        { subject: 'Novidades da Semana',              message: `Olá [NOME],\n\nConfira as novidades!\n\nEquipe Translators101` },
    promotion:         { subject: 'Oferta Especial',                  message: `Olá [NOME],\n\nOferta: [LINK]\n\nEquipe Translators101` },
    reminder:          { subject: 'Lembrete Importante',              message: `Olá [NOME],\n\nLembrete.\n\nEquipe Translators101` },
    lecture:           { subject: 'Nova Palestra',                    message: `Olá [NOME],\n\nNova palestra disponível.\n\nEquipe Translators101` },
    leads_sorteio:     { subject: 'Resultado do Sorteio',             message: `Olá [NOME],\n\nVocê ganhou!\nAcesse: [LINK]\n\nWilliam Cassemiro` },
    planos_assinatura: { subject: 'Continue sua evolução',            message: `Olá [NOME],\n\nAssine agora:\n[LINK]\n\nWilliam Cassemiro` }
};
const savedTemplates = <?php echo json_encode($saved_templates); ?>;
<?php if ($next_lecture): ?>
const nextLecture = {
    title:   <?php echo json_encode($next_lecture['title']); ?>,
    speaker: <?php echo json_encode($next_lecture['speaker']); ?>,
    date:    <?php echo json_encode(date('d/m/Y', strtotime($next_lecture['announcement_date']))); ?>,
    time:    <?php echo json_encode(date('H:i', strtotime($next_lecture['lecture_time']))); ?>
};
<?php else: ?>
const nextLecture = null;
<?php endif; ?>

window.addEventListener('DOMContentLoaded', function() {
    const radarSubject = <?php echo json_encode($prefill_subject); ?>;
    const radarMessage = <?php echo json_encode($prefill_message); ?>;
    if (radarSubject || radarMessage) {
        if (radarSubject) document.getElementById('subject').value = radarSubject;
        if (radarMessage) document.getElementById('message').value = radarMessage;
        setTimeout(() => { document.getElementById('message').scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 500);
    }

    const selectedLeads = localStorage.getItem('t101_leads_fundo_selected');
    const source        = localStorage.getItem('t101_source');
    if (selectedLeads && source === 'leads_fundo') {
        try {
            const leads = JSON.parse(selectedLeads);
            if (leads && leads.length > 0) {
                document.getElementById('selected_lead_ids').value = JSON.stringify(leads.map(l => l.id));
                const recipientType = document.getElementById('recipient_type');
                const option = document.createElement('option');
                option.value = 'leads_fundo_selected';
                option.textContent = `✅ Leads selecionados (${leads.length})`;
                option.selected = true;
                recipientType.insertBefore(option, recipientType.firstChild);
                document.getElementById('custom_emails').value = leads.map(l => l.email).join('\n');
                if (!radarSubject && !radarMessage && !document.getElementById('subject').value) useTemplate('planos_assinatura');
                document.getElementById('importAlertText').textContent = `${leads.length} leads importados!`;
                document.getElementById('importAlert').style.display = 'flex';
                localStorage.removeItem('t101_leads_fundo_selected');
                localStorage.removeItem('t101_source');
                setTimeout(() => { document.getElementById('recipient_type').scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 500);
            }
        } catch (e) {}
    }
});

function useTemplate(type)       { if (defaultTemplates[type]) { document.getElementById('subject').value = defaultTemplates[type].subject; document.getElementById('message').value = defaultTemplates[type].message; } }
function useSavedTemplate(name)  { if (savedTemplates[name])   { document.getElementById('subject').value = savedTemplates[name].subject;  document.getElementById('message').value = savedTemplates[name].message; } }

function fillLectureTemplate() {
    const opt = document.getElementById('lecture_id').selectedOptions[0];
    if (!opt.value) return;
    document.getElementById('subject').value = `🎬 ${opt.dataset.title} - Hoje às ${opt.dataset.time}h`;
    document.getElementById('message').value  = `Olá [NOME],\n\nHoje, ${opt.dataset.date}, às ${opt.dataset.time}h: "${opt.dataset.title}" com ${opt.dataset.speaker}.\n\n${opt.dataset.description}\n\nAcesse: translators101.com\n\nAbraço,\nWilliam Cassemiro`;
}

function useNextLectureTemplate() {
    if (nextLecture) {
        document.getElementById('subject').value = `🎬 ${nextLecture.title} - Hoje às ${nextLecture.time}h`;
        document.getElementById('message').value  = `Olá [NOME],\n\nHoje, ${nextLecture.date}, às ${nextLecture.time}h: "${nextLecture.title}" com ${nextLecture.speaker}.\n\nAcesse: translators101.com`;
    }
}

function toggleUserSelection() {
    const type = document.getElementById('recipient_type').value;
    document.getElementById('userSelectionContainer').style.display = (type === 'selected') ? 'block' : 'none';
    if (type !== 'selected') { document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false); updateSelectedCount(); }
}
function selectAllUsers()    { document.querySelectorAll('.user-item:not([style*="display: none"]) .user-checkbox').forEach(cb => cb.checked = true); updateSelectedCount(); }
function deselectAllUsers()  { document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false); updateSelectedCount(); }
function updateSelectedCount() { document.getElementById('selectedCount').textContent = document.querySelectorAll('.user-checkbox:checked').length; }

function filterUsers() {
    const search = (document.getElementById('userSearch')?.value || '').toLowerCase();
    const type   = (document.getElementById('filterUserType')?.value || '').toLowerCase();
    document.querySelectorAll('.user-item').forEach(item => {
        const matchSearch = !search || item.dataset.name.includes(search) || item.dataset.email.includes(search);
        const matchType   = !type   || item.dataset.type === type;
        item.style.display = (matchSearch && matchType) ? 'block' : 'none';
    });
}
document.getElementById('userSearch')?.addEventListener('input', filterUsers);

function openSaveTemplateModal()  { document.getElementById('save_template_subject').value = document.getElementById('subject').value; document.getElementById('save_template_message').value = document.getElementById('message').value; document.getElementById('saveTemplateModal').style.display = 'flex'; }
function closeSaveTemplateModal() { document.getElementById('saveTemplateModal').style.display = 'none'; }
function openManageTemplatesModal()  { document.getElementById('manageTemplatesModal').style.display = 'flex'; }
function closeManageTemplatesModal() { document.getElementById('manageTemplatesModal').style.display = 'none'; }

function editTemplate(name) {
    if (savedTemplates[name]) {
        document.getElementById('subject').value = savedTemplates[name].subject;
        document.getElementById('message').value = savedTemplates[name].message;
        closeManageTemplatesModal();
        openSaveTemplateModal();
        document.querySelector('#saveTemplateModal input[name="template_name"]').value = name;
    }
}

window.onclick = function(e) { if (e.target.classList.contains('modal')) e.target.style.display = 'none'; };
</script>

<style>
/* CSS original preservado integralmente */
.section-title { margin: 0 0 15px 0; padding-left: 20px; color: #c084fc; font-size: 1.1rem; display: flex; align-items: center; gap: 10px; }
.three-column-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px; }
.two-column-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px; }
.video-card.glass-card { padding: 20px; margin-bottom: 20px; }
.compact-card { padding: 15px 20px !important; }
.config-status-mini { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; }
.config-status-mini.configured { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.config-status-mini.not-configured { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.smtp-info, .sender-email { font-size: 0.8rem; color: rgba(255,255,255,0.6); padding-left: 20px; margin-top: 5px; }
.sender-info { font-weight: 600; color: white; padding-left: 20px; margin: 0; }
.test-form { display: flex; gap: 10px; }
.form-control { width: 100%; padding: 10px 14px; border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 8px; background: rgba(0, 0, 0, 0.3); color: white; }
.form-control:focus { outline: none; border-color: #c084fc; }
.stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 15px; }
.stats-row:last-child { grid-template-columns: repeat(4, 1fr); margin-bottom: 0; }
.stat-item { text-align: center; padding: 18px 12px; background: rgba(142, 68, 173, 0.2); border-radius: 12px; border: 1px solid rgba(142, 68, 173, 0.3); }
.stat-item.stat-green { background: rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.3); } .stat-item.stat-green .stat-number { color: #10b981; }
.stat-item.stat-red { background: rgba(239, 68, 68, 0.15); border-color: rgba(239, 68, 68, 0.3); } .stat-item.stat-red .stat-number { color: #ef4444; }
.stat-item.stat-blue { background: rgba(59, 130, 246, 0.15); border-color: rgba(59, 130, 246, 0.3); } .stat-item.stat-blue .stat-number { color: #3b82f6; }
.stat-item.stat-pink { background: rgba(236, 72, 153, 0.15); border-color: rgba(236, 72, 153, 0.3); } .stat-item.stat-pink .stat-number { color: #ec4899; }
.stat-item.stat-orange { background: rgba(249, 115, 22, 0.15); border-color: rgba(249, 115, 22, 0.3); } .stat-item.stat-orange .stat-number { color: #f97316; }
.stat-number { font-size: 1.8rem; font-weight: bold; color: #c084fc; }
.stat-label { font-size: 0.8rem; color: rgba(255, 255, 255, 0.7); margin-top: 5px; }
.next-lecture-info { display: flex; justify-content: space-between; align-items: center; gap: 20px; padding: 15px 20px; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 10px; }
.lecture-details h4 { color: #f59e0b; margin: 0 0 5px 0; }
.lecture-details p { margin: 2px 0; color: rgba(255,255,255,0.8); font-size: 0.9rem; }
.form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: white; padding-left: 20px; }
.form-actions { text-align: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); }
.cta-btn { padding: 12px 25px; cursor: pointer; } .btn-secondary-large { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); margin-left: 10px; color: white; }
.users-list { max-height: 300px; overflow-y: auto; background: rgba(0,0,0,0.2); border-radius: 8px; }
.user-item { border-bottom: 1px solid rgba(255,255,255,0.05); padding: 10px; }
.user-checkbox-label { display: flex; align-items: center; gap: 10px; cursor: pointer; }
.user-info { flex: 1; } .user-name { display: block; font-weight: 500; } .user-email { display: block; font-size: 0.8rem; opacity: 0.6; }
.user-type-badge { padding: 3px 8px; border-radius: 10px; font-size: 0.7rem; font-weight: 600; }
.quick-actions-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px; }
.quick-action-card { padding: 15px 10px; text-align: center; background: rgba(30,30,30,0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; cursor: pointer; transition: all 0.2s; }
.quick-action-card:hover { transform: translateY(-2px); border-color: #c084fc; }
.quick-action-icon { font-size: 1.5rem; margin-bottom: 8px; }
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); justify-content: center; align-items: center; }
.modal-content { background: #191919; padding: 25px; border-radius: 15px; width: 90%; max-width: 500px; position: relative; border: 1px solid rgba(255,255,255,0.1); }
.close { position: absolute; right: 15px; top: 10px; font-size: 28px; cursor: pointer; color: #aaa; }
.modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
.template-item { display: flex; justify-content: space-between; padding: 10px; background: rgba(255,255,255,0.05); margin-bottom: 5px; border-radius: 5px; }
.btn-icon { width: 30px; height: 30px; border: none; border-radius: 5px; background: rgba(255,255,255,0.1); color: white; cursor: pointer; margin-left: 5px; }
.success-alert { background: rgba(16, 185, 129, 0.15); color: #10b981; padding: 15px; border-radius: 8px; border: 1px solid #10b981; margin-bottom: 20px; }
.error-alert { background: rgba(239, 68, 68, 0.15); color: #ef4444; padding: 15px; border-radius: 8px; border: 1px solid #ef4444; margin-bottom: 20px; }
@media (max-width: 992px) { .three-column-grid, .two-column-grid, .stats-row { grid-template-columns: 1fr; } .quick-actions-grid { grid-template-columns: repeat(3, 1fr); } }
</style>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>