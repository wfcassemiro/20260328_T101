<?php
/**
 * aprovar_radar.php (ATUALIZADO E CORRIGIDO PARA ENUM)
 * * Processa a aprovação do Radar T101:
 * 1. Valida o token de segurança
 * 2. Atualiza o status da newsletter para 'posted'
 * 3. Envia e-mail para todos os assinantes e leads do Radar
 * 4. Exibe página de confirmação
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/email_config.php';
require_once __DIR__ . '/config/email.php';

date_default_timezone_set('America/Sao_Paulo');
session_start();

// Função para exibir página de erro
function showError($title, $message) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                min-height: 100vh; 
                background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                display: flex; 
                align-items: center; 
                justify-content: center;
                font-family: 'Segoe UI', Arial, sans-serif;
                padding: 20px;
            }
            .card {
                background: rgba(30, 30, 50, 0.9);
                border: 1px solid rgba(239, 68, 68, 0.4);
                border-radius: 20px;
                padding: 50px;
                max-width: 500px;
                text-align: center;
                box-shadow: 0 25px 50px rgba(0,0,0,0.5);
            }
            .icon { font-size: 64px; margin-bottom: 25px; }
            h1 { color: #ef4444; font-size: 28px; margin-bottom: 15px; }
            p { color: rgba(255,255,255,0.7); font-size: 16px; line-height: 1.6; }
            .btn {
                display: inline-block;
                margin-top: 30px;
                padding: 14px 35px;
                background: rgba(255,255,255,0.1);
                border: 1px solid rgba(255,255,255,0.2);
                border-radius: 50px;
                color: white;
                text-decoration: none;
                font-weight: 600;
                transition: all 0.3s;
            }
            .btn:hover { background: rgba(255,255,255,0.2); }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon">⚠️</div>
            <h1><?php echo htmlspecialchars($title); ?></h1>
            <p><?php echo htmlspecialchars($message); ?></p>
            <a href="https://v.translators101.com" class="btn">Voltar ao Site</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Função para exibir página de sucesso
function showSuccess($newsletterId, $sentCount, $failedCount) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Radar T101 Aprovado!</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                min-height: 100vh; 
                background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                display: flex; 
                align-items: center; 
                justify-content: center;
                font-family: 'Segoe UI', Arial, sans-serif;
                padding: 20px;
            }
            .card {
                background: rgba(30, 30, 50, 0.9);
                border: 1px solid rgba(16, 185, 129, 0.4);
                border-radius: 20px;
                padding: 50px;
                max-width: 600px;
                text-align: center;
                box-shadow: 0 25px 50px rgba(0,0,0,0.5);
            }
            .icon { font-size: 80px; margin-bottom: 25px; }
            h1 { color: #10b981; font-size: 32px; margin-bottom: 15px; }
            p { color: rgba(255,255,255,0.8); font-size: 18px; line-height: 1.6; margin-bottom: 10px; }
            .stats {
                display: flex;
                justify-content: center;
                gap: 30px;
                margin: 30px 0;
            }
            .stat {
                background: rgba(0,0,0,0.3);
                padding: 20px 30px;
                border-radius: 12px;
                border: 1px solid rgba(255,255,255,0.1);
            }
            .stat-number { font-size: 36px; font-weight: bold; color: #c084fc; }
            .stat-label { font-size: 14px; color: rgba(255,255,255,0.6); margin-top: 5px; }
            .stat.success .stat-number { color: #10b981; }
            .stat.failed .stat-number { color: #ef4444; }
            .btn {
                display: inline-block;
                margin: 10px;
                padding: 14px 35px;
                border-radius: 50px;
                text-decoration: none;
                font-weight: 600;
                transition: all 0.3s;
            }
            .btn-primary {
                background: linear-gradient(90deg, #c084fc, #8b5cf6);
                color: white;
            }
            .btn-secondary {
                background: rgba(255,255,255,0.1);
                border: 1px solid rgba(255,255,255,0.2);
                color: white;
            }
            .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(0,0,0,0.3); }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon">✅</div>
            <h1>Radar T101 Aprovado!</h1>
            <p>O conteúdo foi publicado com sucesso na página do Radar.</p>
            <p>Os e-mails estão sendo enviados para os assinantes.</p>
            
            <div class="stats">
                <div class="stat success">
                    <div class="stat-number"><?php echo $sentCount; ?></div>
                    <div class="stat-label">E-mails Enviados</div>
                </div>
                <?php if ($failedCount > 0): ?>
                <div class="stat failed">
                    <div class="stat-number"><?php echo $failedCount; ?></div>
                    <div class="stat-label">Falhas</div>
                </div>
                <?php endif; ?>
            </div>
            
            <div>
                <a href="https://v.translators101.com/arquivo_radar.php?id=<?php echo $newsletterId; ?>" class="btn btn-primary">
                    Ver Publicação
                </a>
                <a href="https://v.translators101.com/admin/emails_b.php" class="btn btn-secondary">
                    Painel de E-mails
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================
// INÍCIO DO PROCESSAMENTO
// ============================================

$token = $_GET['token'] ?? '';

if (empty($token)) {
    showError('Token Inválido', 'Nenhum token de aprovação foi fornecido.');
}

try {
    // 1. Valida o token
    $stmt = $pdo->prepare("
        SELECT t.*, n.id as newsletter_id, n.compiled_newsletter, n.newsletter_content, n.week_number, n.status
        FROM radar_approval_tokens t
        INNER JOIN weekly_newsletters n ON t.newsletter_id = n.id
        WHERE t.token = ? AND t.action = 'approve'
    ");
    $stmt->execute([$token]);
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tokenData) {
        showError('Token Inválido', 'Este link de aprovação não é válido ou não existe.');
    }

    // Verifica se o token expirou
    if (strtotime($tokenData['expires_at']) < time()) {
        showError('Link Expirado', 'Este link de aprovação expirou. Solicite um novo e-mail de aprovação.');
    }

    // Verifica se o token já foi usado
    if ($tokenData['used']) {
        showError('Link Já Utilizado', 'Este link de aprovação já foi utilizado anteriormente.');
    }

    // Verifica se já foi aprovado ('posted' alinhado ao banco)
    if ($tokenData['status'] === 'posted') {
        showError('Já Aprovado', 'Esta newsletter já foi aprovada anteriormente.');
    }

    $newsletterId = $tokenData['newsletter_id'];
    // GARANTE QUE O CONTEÚDO NÃO SEJA NULL
    $compiledNewsletter = !empty($tokenData['compiled_newsletter']) ? $tokenData['compiled_newsletter'] : $tokenData['newsletter_content'];
    $weekNumber = $tokenData['week_number'];

    // 2. Marca o token como usado
    $stmtUsed = $pdo->prepare("UPDATE radar_approval_tokens SET used = 1 WHERE token = ?");
    $stmtUsed->execute([$token]);

    // 3. Atualiza o status da newsletter para 'posted' e move conteúdo pro campo oficial de exibição
    $stmtApprove = $pdo->prepare("
        UPDATE weekly_newsletters 
        SET status = 'posted', compiled_newsletter = ?
        WHERE id = ?
    ");
    $stmtApprove->execute([$compiledNewsletter, $newsletterId]);

    // 4. Busca os destinatários (Assinantes T101 + Leads Radar)
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

    // 5. Envia os e-mails
    $sentCount = 0;
    $failedCount = 0;

    if (isEmailConfigured() && !empty($recipients)) {
        $emailSender = new EmailSender();
        
        // Prepara o assunto e corpo do e-mail
        $emailSubject = "Radar T101: Curadoria Semanal (" . date('d/m') . ")";
        
        // Formata o conteúdo para HTML
        $formattedContent = nl2br(htmlspecialchars($compiledNewsletter));
        
        // Template do e-mail para assinantes
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
                                        Semana ' . $weekNumber . ' | ' . date('d/m/Y') . '
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <td style="padding: 30px;">
                                    <p style="color: #e2e8f0; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                        Olá [NOME],
                                    </p>
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
                
                $result = $emailSender->sendEmail(
                    $recipient['email'],
                    $recipient['name'] ?? 'Assinante',
                    $emailSubject,
                    $personalizedEmail
                );

                if ($result) {
                    $sentCount++;
                } else {
                    $failedCount++;
                }

                usleep(150000); // Pausa de 150ms entre envios

            } catch (Exception $e) {
                $failedCount++;
                error_log("[Radar Approval] Erro ao enviar para {$recipient['email']}: " . $e->getMessage());
            }
        }

        // 6. Registra no log de e-mails
        $stmtLog = $pdo->prepare("
            INSERT INTO email_logs (subject, message, recipient_count, recipient_type, sent_by, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmtLog->execute([
            $emailSubject,
            'Radar T101 - Aprovação automática via link',
            count($recipients),
            'subscribers_and_radar',
            null, // Sistema automático
            $sentCount > 0 ? 'sent' : 'failed'
        ]);
    }

    // 7. Exibe página de sucesso
    showSuccess($newsletterId, $sentCount, $failedCount);

} catch (Exception $e) {
    error_log("[Radar Approval] Erro: " . $e->getMessage());
    showError('Erro no Sistema', 'Ocorreu um erro ao processar a aprovação. Por favor, tente novamente.');
}