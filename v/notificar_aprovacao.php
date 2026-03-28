<?php
/**
 * notificar_aprovacao.php (ATUALIZADO)
 * * Este arquivo é chamado pelo n8n após o envio da curadoria para o painel.
 * Envia um e-mail para o administrador com apenas o botão de EDITAR,
 * permitindo revisar o texto completo antes de decidir a publicação.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/email_config.php';
require_once __DIR__ . '/config/email.php';

date_default_timezone_set('America/Sao_Paulo');

// Configura resposta JSON
header('Content-Type: application/json');

try {
    // 1. Busca a newsletter mais recente com status 'draft' ou 'pendente'
    $stmt = $pdo->query("
        SELECT id, week_number, compiled_newsletter, newsletter_content, created_at, status 
        FROM weekly_newsletters 
        WHERE status IN ('pendente', 'draft') 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $newsletter = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$newsletter) {
        echo json_encode([
            'success' => false, 
            'error' => 'Nenhuma newsletter pendente encontrada.',
            'debug' => 'Verifique se existe registro com status = pendente ou draft'
        ]);
        exit;
    }

    $newsletterId = $newsletter['id'];
    $weekNumber = $newsletter['week_number'];
    $createdAt = date('d/m/Y H:i', strtotime($newsletter['created_at']));

    // 2. Gera token seguro para edição
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

    // 3. Salva o token na tabela (cria se não existir)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS radar_approval_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            newsletter_id INT NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            action ENUM('approve', 'edit') NOT NULL,
            used TINYINT(1) DEFAULT 0,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token),
            INDEX idx_newsletter (newsletter_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Insere apenas o token de edição
    $stmtToken = $pdo->prepare("
        INSERT INTO radar_approval_tokens (newsletter_id, token, action, expires_at) 
        VALUES (?, ?, 'edit', ?)
    ");
    $tokenEdit = bin2hex(random_bytes(32));
    $stmtToken->execute([$newsletterId, $tokenEdit, $expiresAt]);

    // 4. Monta a URL com token
    $baseUrl = 'https://v.translators101.com';
    $editUrl = "{$baseUrl}/editar_radar.php?token={$tokenEdit}";

    // 5. Prepara o preview do conteúdo (primeiros 500 caracteres)
    $rawContent = !empty($newsletter['compiled_newsletter']) ? $newsletter['compiled_newsletter'] : ($newsletter['newsletter_content'] ?? '');
    $contentPreview = strip_tags($rawContent);
    $contentPreview = substr($contentPreview, 0, 500) . (strlen($contentPreview) > 500 ? '...' : '');

    // 6. Monta o HTML do e-mail
    $emailSubject = "Radar T101 - Curadoria Pronta para Revisão (Semana {$weekNumber})";
    
    $emailBody = '
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Revisão Radar T101</title>
    </head>
    <body style="margin: 0; padding: 0; background-color: #1a1a2e; font-family: Arial, Helvetica, sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #1a1a2e; padding: 40px 20px;">
            <tr>
                <td align="center">
                    <table width="600" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #16213e 0%, #1a1a2e 100%); border-radius: 16px; border: 1px solid rgba(192, 132, 252, 0.3); overflow: hidden;">
                        
                        <tr>
                            <td style="background: linear-gradient(90deg, #c084fc, #8b5cf6); padding: 25px 30px; text-align: center;">
                                <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: bold;">
                                    Radar T101 - Revisão Necessária
                                </h1>
                                <p style="margin: 8px 0 0 0; color: rgba(255,255,255,0.9); font-size: 14px;">
                                    Semana ' . htmlspecialchars($weekNumber) . ' | Gerado em ' . $createdAt . '
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <td style="padding: 30px;">
                                <p style="color: #e2e8f0; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                    Olá William,
                                </p>
                                <p style="color: #e2e8f0; font-size: 16px; line-height: 1.6; margin: 0 0 25px 0;">
                                    A curadoria semanal do <strong style="color: #c084fc;">Radar T101</strong> gerada pela IA está pronta. 
                                    Clique no botão abaixo para ler o texto completo, fazer seus ajustes e escolher como deseja publicá-la.
                                </p>
                                
                                <div style="background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 20px; margin-bottom: 30px;">
                                    <h3 style="color: #c084fc; margin: 0 0 15px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">
                                        Início do texto gerado:
                                    </h3>
                                    <p style="color: rgba(255,255,255,0.7); font-size: 14px; line-height: 1.6; margin: 0; white-space: pre-wrap;">
                                        ' . htmlspecialchars($contentPreview) . '
                                    </p>
                                </div>
                                
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td align="center" style="padding: 10px;">
                                            <a href="' . $editUrl . '" 
                                               style="display: inline-block; background: linear-gradient(90deg, #f59e0b, #d97706); color: #ffffff; text-decoration: none; padding: 16px 40px; border-radius: 50px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4);">
                                                ✎ REVISAR E EDITAR CONTEÚDO
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                                
                                <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 8px; padding: 15px; margin-top: 25px;">
                                    <p style="color: #93c5fd; font-size: 13px; margin: 0; line-height: 1.5;">
                                        Na tela de edição você poderá escolher se deseja salvar como rascunho, publicar apenas no site ou publicar e já disparar o e-mail para a base.
                                    </p>
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <td style="background: rgba(0,0,0,0.3); padding: 20px 30px; text-align: center; border-top: 1px solid rgba(255,255,255,0.1);">
                                <p style="color: rgba(255,255,255,0.5); font-size: 12px; margin: 0;">
                                    Este é um e-mail automático do sistema Translators101.<br>
                                    Link válido por 7 dias. ID da Newsletter: #' . $newsletterId . '
                                </p>
                            </td>
                        </tr>
                        
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';

    // 7. Envia o e-mail
    if (!isEmailConfigured()) {
        echo json_encode(['success' => false, 'error' => 'SMTP não configurado.']);
        exit;
    }

    $emailSender = new EmailSender();
    $result = $emailSender->sendEmail('wrbl.traduz@gmail.com', 'William', $emailSubject, $emailBody);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'E-mail de revisão enviado com sucesso.',
            'newsletter_id' => $newsletterId
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Falha ao enviar e-mail.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}