<?php
/**
 * editar_radar.php
 * * Interface para revisão, edição e publicação da curadoria do Radar T101.
 * Permite salvar como rascunho, publicar apenas no site, ou publicar e enviar e-mails.
 */

session_start();
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/email_config.php';
require_once __DIR__ . '/config/email.php';

$newsletter = null;
$message = '';
$msgType = '';
$isValidAccess = false;
$tokenId = null;

// 1. VALIDAÇÃO DE ACESSO (Via Token do e-mail OU via Admin Logado)
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $stmt = $pdo->prepare("
        SELECT t.*, n.* FROM radar_approval_tokens t
        INNER JOIN weekly_newsletters n ON t.newsletter_id = n.id
        WHERE t.token = ? AND t.action = 'edit' AND t.used = 0 AND t.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $newsletter = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($newsletter) {
        $isValidAccess = true;
        $tokenId = $newsletter['token'];
    } else {
        $message = "Link de edição inválido, expirado ou já utilizado.";
        $msgType = "error";
    }
} elseif (isset($_GET['direct_edit']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
    $id = $_GET['direct_edit'];
    $stmt = $pdo->prepare("SELECT * FROM weekly_newsletters WHERE id = ?");
    $stmt->execute([$id]);
    $newsletter = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($newsletter) {
        $isValidAccess = true;
    } else {
        $message = "Newsletter não encontrada.";
        $msgType = "error";
    }
} else {
    die("Acesso negado.");
}

// ============================================
// PROCESSAMENTO DO FORMULÁRIO
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isValidAccess) {
    $action = $_POST['action_type'] ?? '';
    $content = trim($_POST['compiled_newsletter'] ?? '');
    $linkedin = trim($_POST['linkedin_content'] ?? '');
    $twitter = trim($_POST['twitter_content'] ?? '');
    $instagram = trim($_POST['instagram_content'] ?? '');
    $newsletterId = $newsletter['newsletter_id'] ?? $newsletter['id'];

    if (empty($content)) {
        $message = "O conteúdo principal da newsletter não pode estar vazio.";
        $msgType = "error";
    } else {
        try {
            // Se a ação for salvar rascunho, mantém o status atual. Se for publicar, muda para 'posted'
            $newStatus = ($action === 'save_draft') ? $newsletter['status'] : 'posted';

            $stmtUpdate = $pdo->prepare("
                UPDATE weekly_newsletters 
                SET compiled_newsletter = ?, 
                    newsletter_content = ?, 
                    linkedin_content = ?, 
                    twitter_content = ?, 
                    instagram_content = ?, 
                    status = ?
                WHERE id = ?
            ");
            $stmtUpdate->execute([$content, $content, $linkedin, $twitter, $instagram, $newStatus, $newsletterId]);

            // Se for publicar, marca o token como usado (se houver)
            if ($action !== 'save_draft' && $tokenId) {
                $pdo->prepare("UPDATE radar_approval_tokens SET used = 1 WHERE token = ?")->execute([$tokenId]);
            }

            // Ações específicas dependendo do botão clicado
            if ($action === 'save_draft') {
                $message = "Rascunho salvo com sucesso!";
                $msgType = "success";
                // Atualiza variável para refletir na tela
                $newsletter['compiled_newsletter'] = $content;
            } 
            elseif ($action === 'publish_site') {
                // Publicou apenas no site
                header("Location: /arquivo_radar.php?id=" . $newsletterId . "&msg=publicado_site");
                exit;
            } 
            elseif ($action === 'publish_email') {
                // Publicou e enviou e-mail (Lógica similar ao aprovar_radar)
                $stmtRecipients = $pdo->query("
                    SELECT id, email, name FROM users 
                    WHERE is_active = 1 AND (is_subscriber = 1 OR role = 'subscriber' OR (subscription_expires IS NOT NULL AND subscription_expires > NOW()))
                    UNION
                    SELECT l.id, l.email, l.nome AS name 
                    FROM leads l 
                    INNER JOIN lead_interesses li ON l.id = li.lead_id 
                    WHERE li.interesse = 'radar_t101' AND COALESCE(l.pode_receber_email, 1) = 1
                ");
                $recipients = $stmtRecipients->fetchAll(PDO::FETCH_ASSOC);

                $sentCount = 0;
                $failedCount = 0;

                if (isEmailConfigured() && !empty($recipients)) {
                    $emailSender = new EmailSender();
                    $emailSubject = "Radar T101: Curadoria Semanal (" . date('d/m') . ")";
                    $formattedContent = nl2br(htmlspecialchars($content));
                    $weekNum = $newsletter['week_number'] ?? date('W');

                    // Template do e-mail
                    $emailTemplate = '
                    <!DOCTYPE html>
                    <html lang="pt-BR">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title>Radar T101</title>
                    </head>
                    <body style="margin: 0; padding: 0; background-color: #1a1a2e; font-family: Arial, Helvetica, sans-serif;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #1a1a2e; padding: 40px 20px;">
                            <tr>
                                <td align="center">
                                    <table width="600" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #16213e 0%, #1a1a2e 100%); border-radius: 16px; border: 1px solid rgba(192, 132, 252, 0.3); overflow: hidden;">
                                        <tr>
                                            <td style="background: linear-gradient(90deg, #c084fc, #8b5cf6); padding: 25px 30px; text-align: center;">
                                                <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: bold;">
                                                    Radar T101 - Curadoria Semanal
                                                </h1>
                                                <p style="margin: 8px 0 0 0; color: rgba(255,255,255,0.9); font-size: 14px;">
                                                    Semana ' . $weekNum . ' | ' . date('d/m/Y') . '
                                                </p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 30px;">
                                                <p style="color: #e2e8f0; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">Olá [NOME],</p>
                                                <div style="color: #e2e8f0; font-size: 15px; line-height: 1.8;">
                                                    ' . $formattedContent . '
                                                </div>
                                                <div style="text-align: center; margin-top: 30px;">
                                                    <a href="https://v.translators101.com/arquivo_radar.php?id=' . $newsletterId . '" 
                                                       style="display: inline-block; background: linear-gradient(90deg, #c084fc, #8b5cf6); color: #ffffff; text-decoration: none; padding: 14px 35px; border-radius: 50px; font-weight: bold; font-size: 15px;">
                                                        Ver no Site
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="background: rgba(0,0,0,0.3); padding: 20px 30px; text-align: center; border-top: 1px solid rgba(255,255,255,0.1);">
                                                <p style="color: rgba(255,255,255,0.5); font-size: 12px; margin: 0;">
                                                    Você recebeu este e-mail por ser assinante do Radar T101.<br>
                                                    <a href="https://v.translators101.com" style="color: #c084fc;">Translators101</a>
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </body>
                    </html>';

                    foreach ($recipients as $recipient) {
                        try {
                            $personalizedEmail = str_replace('[NOME]', $recipient['name'] ?? 'Assinante', $emailTemplate);
                            $result = $emailSender->sendEmail($recipient['email'], $recipient['name'] ?? 'Assinante', $emailSubject, $personalizedEmail);
                            if ($result) $sentCount++; else $failedCount++;
                            usleep(150000); // Pausa de 150ms
                        } catch (Exception $e) {
                            $failedCount++;
                        }
                    }

                    // Registra no log
                    $pdo->prepare("INSERT INTO email_logs (subject, message, recipient_count, recipient_type, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())")
                        ->execute([$emailSubject, 'Radar T101 - Disparo de curadoria editada', count($recipients), 'subscribers_and_radar', $sentCount > 0 ? 'sent' : 'failed']);
                }

                // Redireciona com stats
                header("Location: /arquivo_radar.php?id=" . $newsletterId . "&msg=publicado_email&sent={$sentCount}&failed={$failedCount}");
                exit;
            }

        } catch (Exception $e) {
            $message = "Erro ao salvar no banco de dados: " . $e->getMessage();
            $msgType = "error";
        }
    }
}

// Configurações da página
$page_title = "Editor do Radar T101";
include __DIR__ . '/vision/includes/head.php';
include __DIR__ . '/vision/includes/header.php';
include __DIR__ . '/vision/includes/sidebar.php';
?>

<style>
.editor-container { max-width: 1000px; margin: 30px auto; padding: 20px; }
.editor-header { text-align: center; margin-bottom: 30px; }
.editor-header h1 { font-size: 2rem; color: #fff; margin-bottom: 10px; display: flex; align-items: center; justify-content: center; gap: 15px; }
.editor-header h1 i { color: #f59e0b; }
.editor-header p { color: rgba(255, 255, 255, 0.6); }

.alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; font-weight: 500; }
.alert-error { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
.alert-success { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }

.editor-card { background: rgba(30, 30, 50, 0.6); border-radius: 16px; border: 1px solid rgba(255, 255, 255, 0.1); padding: 30px; margin-bottom: 30px; }
.form-group { margin-bottom: 25px; }
.form-group label { display: block; margin-bottom: 10px; color: #c084fc; font-weight: 600; font-size: 1.1rem; }
.form-group p.desc { margin-top: -5px; margin-bottom: 10px; font-size: 0.85rem; color: rgba(255, 255, 255, 0.5); }

textarea.editor-input { 
    width: 100%; 
    background: rgba(0, 0, 0, 0.3); 
    border: 1px solid rgba(255, 255, 255, 0.2); 
    border-radius: 10px; 
    color: #e2e8f0; 
    padding: 15px; 
    font-size: 1rem; 
    line-height: 1.6; 
    font-family: inherit;
    resize: vertical;
}
textarea.editor-input:focus { outline: none; border-color: #c084fc; }

.social-inputs { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media (max-width: 768px) { .social-inputs { grid-template-columns: 1fr; } }

.action-buttons { 
    display: flex; 
    flex-wrap: wrap; 
    gap: 15px; 
    background: rgba(0, 0, 0, 0.2); 
    padding: 20px; 
    border-radius: 12px; 
    border: 1px solid rgba(255, 255, 255, 0.05);
    margin-top: 30px;
}

.btn-action {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 14px 25px; border: none; border-radius: 8px; font-weight: 600; font-size: 1rem;
    cursor: pointer; transition: all 0.3s; color: white;
}
.btn-action:hover { transform: translateY(-2px); }

.btn-draft { background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); }
.btn-draft:hover { background: rgba(255, 255, 255, 0.2); }

.btn-site { background: linear-gradient(90deg, #10b981, #059669); box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2); }
.btn-email { background: linear-gradient(90deg, #c084fc, #8b5cf6); box-shadow: 0 4px 15px rgba(192, 132, 252, 0.2); flex-grow: 1; justify-content: center; }

/* Loader Overlay */
#loader-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.9); z-index: 9999;
    display: none; flex-direction: column; align-items: center; justify-content: center;
    color: white; font-size: 1.2rem;
}
.spinner {
    border: 4px solid rgba(255, 255, 255, 0.1); border-left-color: #c084fc;
    border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin-bottom: 20px;
}
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

</style>

<div id="loader-overlay">
    <div class="spinner"></div>
    <div id="loader-text">Processando, por favor aguarde...</div>
</div>

<div class="main-content">
    <div class="editor-container">
        
        <div class="editor-header">
            <h1><i class="fas fa-edit"></i> Editor do Radar T101</h1>
            <p>Revise a curadoria gerada pela IA e decida como deseja publicá-la.</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $msgType; ?>">
                <i class="fas <?php echo $msgType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i> 
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($isValidAccess && $newsletter): ?>
            <?php 
                // Prioriza compiled_newsletter, se vazio usa newsletter_content
                $mainText = !empty($newsletter['compiled_newsletter']) ? $newsletter['compiled_newsletter'] : ($newsletter['newsletter_content'] ?? '');
            ?>
            <form method="POST" id="editorForm" onsubmit="showLoader()">
                <div class="editor-card">
                    
                    <div class="form-group">
                        <label><i class="fas fa-envelope-open-text"></i> Texto Principal (Newsletter / Página)</label>
                        <p class="desc">Este é o texto que será renderizado no site do Radar e enviado por e-mail. Ajuste quebras de linha e formatação conforme necessário.</p>
                        <textarea name="compiled_newsletter" class="editor-input" rows="20" required><?php echo htmlspecialchars($mainText); ?></textarea>
                    </div>

                    <h4 style="color: #fff; margin: 40px 0 20px 0; padding-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.1);"><i class="fas fa-share-alt"></i> Textos para Redes Sociais (Opcional)</h4>
                    
                    <div class="social-inputs">
                        <div class="form-group">
                            <label><i class="fab fa-linkedin" style="color: #0a66c2;"></i> LinkedIn</label>
                            <textarea name="linkedin_content" class="editor-input" rows="8"><?php echo htmlspecialchars($newsletter['linkedin_content'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label><i class="fab fa-instagram" style="color: #e1306c;"></i> Instagram</label>
                            <textarea name="instagram_content" class="editor-input" rows="8"><?php echo htmlspecialchars($newsletter['instagram_content'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label><i class="fab fa-twitter" style="color: #1da1f2;"></i> X (Twitter Thread)</label>
                            <textarea name="twitter_content" class="editor-input" rows="6"><?php echo htmlspecialchars($newsletter['twitter_content'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <button type="submit" name="action_type" value="save_draft" class="btn-action btn-draft" onclick="setLoaderText('Salvando rascunho...')">
                            <i class="fas fa-save"></i> Salvar Rascunho
                        </button>
                        
                        <button type="submit" name="action_type" value="publish_site" class="btn-action btn-site" onclick="return confirmAction('publish_site')">
                            <i class="fas fa-globe"></i> Publicar (Somente Site)
                        </button>
                        
                        <button type="submit" name="action_type" value="publish_email" class="btn-action btn-email" onclick="return confirmAction('publish_email')">
                            <i class="fas fa-paper-plane"></i> Publicar e Enviar E-mail para Base
                        </button>
                    </div>

                </div>
            </form>
        <?php endif; ?>

    </div>
</div>

<script>
function showLoader() {
    document.getElementById('loader-overlay').style.display = 'flex';
}

function setLoaderText(text) {
    document.getElementById('loader-text').innerText = text;
}

function confirmAction(type) {
    if (type === 'publish_site') {
        if (confirm("Publicar a newsletter apenas na página do Radar agora? Nenhum e-mail será disparado.")) {
            setLoaderText('Publicando no site...');
            return true;
        }
    } else if (type === 'publish_email') {
        if (confirm("ATENÇÃO: Você está prestes a publicar no site E enviar um e-mail para TODOS os assinantes da sua base. Continuar?")) {
            setLoaderText('Publicando e enviando e-mails. Isso pode levar alguns minutos...');
            return true;
        }
    }
    return false;
}
</script>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>