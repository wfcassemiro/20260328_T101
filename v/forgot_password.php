<?php
/**
 * Recuperação de Senha - Translators101
 * Versão corrigida com credenciais SMTP corretas
 */

// Define o fuso horário correto para o Brasil
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/config/database.php';

// Carrega PHPMailer diretamente
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$message = '';
$message_type = '';
$debug_output = '';

// ============================================
// 🔧 MODO DEBUG - Mude para FALSE em produção
// ============================================
$DEBUG_MODE = false;

// ============================================
// 📧 CREDENCIAIS SMTP CORRETAS
// ============================================
$SMTP_HOST     = 'smtp.hostinger.com';
$SMTP_PORT     = 465;
$SMTP_USERNAME = 'contato@translators101.com';
$SMTP_PASSWORD = 'r:#D$!r=X1';  // ← SENHA CORRETA!
$SMTP_FROM     = 'contato@translators101.com';
$SMTP_NAME     = 'Translators101';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!empty($email)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Por favor, informe um e-mail válido.";
            $message_type = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($DEBUG_MODE) {
                    $debug_output .= "✅ Conexão com banco OK\n";
                    $debug_output .= "🔍 Buscando usuário: $email\n";
                    $debug_output .= $user ? "✅ Usuário encontrado (ID: {$user['id']})\n" : "❌ Usuário NÃO encontrado\n";
                }

                if ($user) {
                    $token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+5 hour'));

                    $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user['id']]);
                    $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
                    $stmt->execute([$user['id'], $token, $expires_at]);

                    if ($DEBUG_MODE) {
                        $debug_output .= "✅ Token gerado e salvo no banco\n";
                    }

                    $reset_link = "https://v.translators101.com/reset_password.php?token=" . urlencode($token);
                    $user_name = $user['name'] ?? 'Usuário';

                    // Configura PHPMailer
                    $mail = new PHPMailer(true);
                    $emailSent = false;

                    $mail->CharSet = 'UTF-8';
                    $mail->Encoding = 'base64';

                    if ($DEBUG_MODE) {
                        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
                        $mail->Debugoutput = function($str, $level) use (&$debug_output) {
                            $debug_output .= "SMTP[$level]: $str\n";
                        };
                    }

                    // Tentativa SSL/465
                    try {
                        if ($DEBUG_MODE) {
                            $debug_output .= "\n📧 Tentativa: SSL na porta 465...\n";
                            $debug_output .= "🔑 Usando credenciais de: $SMTP_USERNAME\n";
                        }

                        $mail->isSMTP();
                        $mail->Host       = $SMTP_HOST;
                        $mail->SMTPAuth   = true;
                        $mail->Username   = $SMTP_USERNAME;
                        $mail->Password   = $SMTP_PASSWORD;
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                        $mail->Port       = $SMTP_PORT;
                        $mail->Timeout    = 30;

                        $mail->setFrom($SMTP_FROM, $SMTP_NAME);
                        $mail->addAddress($email);
                        $mail->addReplyTo($SMTP_FROM, $SMTP_NAME);

                        $mail->isHTML(true);
                        $mail->Subject = "Redefina sua senha - Translators101";
                        $mail->Body    = getEmailBody($reset_link, $user_name);
                        $mail->AltBody = getEmailAltBody($reset_link, $user_name);

                        $mail->send();
                        $emailSent = true;

                        if ($DEBUG_MODE) {
                            $debug_output .= "✅ E-mail enviado com SUCESSO!\n";
                        }

                    } catch (Exception $e) {
                        if ($DEBUG_MODE) {
                            $debug_output .= "❌ FALHOU: {$mail->ErrorInfo}\n";
                            $debug_output .= "📋 Exceção: {$e->getMessage()}\n";
                        }
                        $message = "Não foi possível enviar o e-mail. Tente novamente mais tarde.";
                        $message_type = 'error';
                    }

                    if ($emailSent) {
                        $message = "Um link de redefinição foi enviado para seu e-mail. Verifique também a caixa de spam.";
                        $message_type = 'success';
                    }

                } else {
                    $message = "E-mail não encontrado em nossa base de dados.";
                    $message_type = 'error';
                }

            } catch (PDOException $e) {
                if ($DEBUG_MODE) {
                    $debug_output .= "❌ ERRO DE BANCO: " . $e->getMessage() . "\n";
                }
                $message = "Ocorreu um erro interno. Tente novamente mais tarde.";
                $message_type = 'error';
            }
        }
    } else {
        $message = "Por favor, preencha seu e-mail.";
        $message_type = 'error';
    }
}

function getEmailBody($reset_link, $user_name) {
    return "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;'>
        <div style='background: linear-gradient(135deg, #1a1a1a, #2d2d2d); padding: 30px; border-radius: 12px; color: white; text-align: center;'>
            <h2 style='color: #FFD700; margin-bottom: 20px;'>🔐 Redefinição de Senha</h2>
            <p style='font-size: 16px; line-height: 1.6; margin-bottom: 25px;'>
                Olá, <strong>{$user_name}</strong>!<br><br>
                Você solicitou a redefinição de sua senha na <strong>Translators101</strong>.
            </p>
            <p style='margin-bottom: 30px;'>
                <a href='{$reset_link}' style='display: inline-block; background: linear-gradient(135deg, #007AFF, #0056CC); color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px; font-weight: bold; font-size: 16px;'>
                    ✨ Redefinir Senha
                </a>
            </p>
            <p style='font-size: 14px; color: #ccc; margin-bottom: 10px;'>
                ⏰ Este link expira em <strong>1 hora</strong>.
            </p>
            <p style='font-size: 14px; color: #ccc;'>
                Se você não solicitou esta redefinição, ignore este e-mail.
            </p>
            <hr style='border: none; border-top: 1px solid #444; margin: 25px 0;'>
            <p style='font-size: 12px; color: #999;'>
                Equipe <strong>Translators101</strong><br>
                <a href='https://v.translators101.com' style='color: #FFD700;'>v.translators101.com</a>
            </p>
        </div>
    </div>
    ";
}

function getEmailAltBody($reset_link, $user_name) {
    return "Olá, {$user_name}!\n\n" .
           "Você solicitou a redefinição de sua senha na Translators101.\n\n" .
           "Clique no link abaixo para redefinir sua senha:\n" .
           "{$reset_link}\n\n" .
           "Este link expira em 1 hora.\n\n" .
           "Se você não solicitou esta redefinição, ignore este e-mail.\n\n" .
           "Equipe Translators101\n" .
           "https://v.translators101.com";
}
?>

<?php include __DIR__ . '/vision/includes/head.php'; ?>
<?php include __DIR__ . '/vision/includes/header.php'; ?>

<style>
.alert-success {
    background: linear-gradient(135deg, rgba(52, 199, 89, 0.2), rgba(52, 199, 89, 0.1));
    border: 1px solid rgba(52, 199, 89, 0.3);
    color: #34C759;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-error {
    background: linear-gradient(135deg, rgba(255, 59, 48, 0.2), rgba(255, 59, 48, 0.1));
    border: 1px solid rgba(255, 59, 48, 0.3);
    color: #FF3B30;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-actions {
    text-align: center;
    margin-top: 20px;
}

.back-link {
    display: inline-block;
    margin-top: 15px;
    color: var(--accent-gold);
    text-decoration: none;
    font-weight: 600;
}

.back-link:hover {
    color: #fff;
    text-decoration: underline;
}

/* DEBUG BOX */
.debug-box {
    background: #1a1a2e;
    border: 2px solid #e94560;
    border-radius: 8px;
    padding: 15px;
    margin-top: 20px;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    color: #00ff00;
    max-height: 400px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.debug-box h4 {
    color: #e94560;
    margin: 0 0 10px 0;
    font-family: Arial, sans-serif;
}

/* LOADING OVERLAY */
.loading-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 9999;
    justify-content: center;
    align-items: center;
    flex-direction: column;
}

.loading-overlay.active {
    display: flex;
}

.loading-spinner {
    width: 60px;
    height: 60px;
    border: 4px solid rgba(255, 215, 0, 0.3);
    border-top: 4px solid #FFD700;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loading-text {
    color: #fff;
    margin-top: 20px;
    font-size: 16px;
    text-align: center;
}

.loading-subtext {
    color: #ccc;
    margin-top: 8px;
    font-size: 13px;
}

.cta-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
    <div class="loading-text">📧 Enviando e-mail de recuperação...</div>
    <div class="loading-subtext">Isso pode levar alguns segundos</div>
</div>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-unlock-alt"></i> Recuperar Senha</h1>
            <p>Enviaremos um link seguro para redefinir sua senha</p>
        </div>
    </div>

    <div class="video-card" style="max-width: 520px; margin: 0 auto; padding: 35px 25px;">
        <h2><i class="fas fa-envelope"></i> Esqueci minha senha</h2>

        <?php if ($message): ?>
        <div class="alert-<?php echo $message_type; ?>">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" class="vision-form" id="forgotForm">
            <div class="form-group">
                <label for="email">
                    <i class="fas fa-envelope"></i> Seu e-mail cadastrado
                </label>
                <input type="email" id="email" name="email" required 
                       placeholder="Digite seu e-mail"
                       autocomplete="email"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <div class="form-actions">
                <button type="submit" class="cta-btn" id="submitBtn">
                    <i class="fas fa-paper-plane"></i> Enviar link de recuperação
                </button>
            </div>
        </form>

        <?php if ($DEBUG_MODE && !empty($debug_output)): ?>
        <div class="debug-box">
            <h4>🔧 DEBUG MODE - Informações de Diagnóstico</h4>
<?php echo htmlspecialchars($debug_output); ?>
        </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 25px; padding-top: 20px; border-top: 1px solid var(--glass-border);">
            <a href="login.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Voltar ao login
            </a>
        </div>
    </div>
</div>

<script>
document.getElementById('forgotForm').addEventListener('submit', function(e) {
    var email = document.getElementById('email').value.trim();
    if (email) {
        document.getElementById('loadingOverlay').classList.add('active');
        document.getElementById('submitBtn').disabled = true;
    }
});

window.addEventListener('load', function() {
    document.getElementById('loadingOverlay').classList.remove('active');
    document.getElementById('submitBtn').disabled = false;
});
</script>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>