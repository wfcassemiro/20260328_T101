<?php
/**
 * Sistema de Email com PHPMailer via SMTP
 * Translators101 - Sistema de Certificados
 * 
 * REQUER: PHPMailer instalado via Composer
 * composer require phpmailer/phpmailer
 */

// Incluir configurações SMTP
if (!defined('SMTP_HOST')) {
    require_once __DIR__ . '/email_config.php';
}

// Carregar PHPMailer via Composer autoload
// Ajuste o caminho conforme a estrutura do seu projeto
$autoload_paths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    dirname(__DIR__) . '/vendor/autoload.php',
];

$autoload_loaded = false;
foreach ($autoload_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoload_loaded = true;
        break;
    }
}

if (!$autoload_loaded) {
    error_log("[Email] AVISO: autoload.php não encontrado. Instale PHPMailer: composer require phpmailer/phpmailer");
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Classe para envio de emails via PHPMailer/SMTP
 */
class EmailSender {
    private $smtp_host;
    private $smtp_port;
    private $smtp_secure;
    private $smtp_username;
    private $smtp_password;
    private $from_email;
    private $from_name;
    private $debug_level;
    
    public function __construct() {
        $this->smtp_host = SMTP_HOST;
        $this->smtp_port = SMTP_PORT;
        $this->smtp_secure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';
        $this->smtp_username = SMTP_USERNAME;
        $this->smtp_password = SMTP_PASSWORD;
        $this->from_email = SMTP_FROM_EMAIL;
        $this->from_name = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Translators101';
        $this->debug_level = defined('SMTP_DEBUG') ? SMTP_DEBUG : 0;
    }
    
    /**
     * Verificar se PHPMailer está disponível
     * @return bool
     */
    public function isPHPMailerAvailable() {
        return class_exists('PHPMailer\PHPMailer\PHPMailer');
    }
    
    /**
     * Enviar email usando PHPMailer via SMTP
     * 
     * @param string $to Email do destinatário
     * @param string $to_name Nome do destinatário
     * @param string $subject Assunto
     * @param string $html_content Conteúdo HTML
     * @param string $text_content Conteúdo texto (opcional)
     * @return bool
     */
    public function sendEmail($to, $to_name, $subject, $html_content, $text_content = '') {
        // Verificar se PHPMailer está disponível
        if (!$this->isPHPMailerAvailable()) {
            error_log("[EmailSender] ERRO: PHPMailer não está instalado!");
            error_log("[EmailSender] Execute: composer require phpmailer/phpmailer");
            return false;
        }
        
        // Verificar configuração
        if (!isEmailConfigured()) {
            error_log("[EmailSender] ERRO: Configurações SMTP incompletas em email_config.php");
            return false;
        }
        
        error_log("[EmailSender] Enviando email para: $to via SMTP ({$this->smtp_host}:{$this->smtp_port})");
        
        try {
            $mail = new PHPMailer(true);
            
            // ================================
            // CONFIGURAÇÕES DO SERVIDOR SMTP
            // ================================
            
            // Nível de debug (0 = off, 1 = client, 2 = client+server)
            $mail->SMTPDebug = $this->debug_level;
            
            // Usar SMTP
            $mail->isSMTP();
            
            // Servidor SMTP
            $mail->Host = $this->smtp_host;
            
            // Autenticação SMTP
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_username;
            $mail->Password = $this->smtp_password;
            
            // Criptografia (TLS ou SSL)
            if ($this->smtp_secure === 'ssl' || $this->smtp_port == 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS
            }
            
            // Porta SMTP
            $mail->Port = $this->smtp_port;
            
            // Charset
            $mail->CharSet = defined('EMAIL_CHARSET') ? EMAIL_CHARSET : 'UTF-8';
            
            // Timeout
            $mail->Timeout = 30;
            
            // ================================
            // CONFIGURAÇÕES DO EMAIL
            // ================================
            
            // Remetente
            $mail->setFrom($this->from_email, $this->from_name);
            
            // Reply-To (opcional)
            $mail->addReplyTo($this->from_email, $this->from_name);
            
            // Destinatário
            $mail->addAddress($to, $to_name);
            
            // Formato HTML
            $mail->isHTML(true);
            
            // Assunto
            $mail->Subject = $subject;
            
            // Corpo HTML
            $mail->Body = $html_content;
            
            // Corpo alternativo (texto puro)
            if (!empty($text_content)) {
                $mail->AltBody = $text_content;
            } else {
                // Gerar versão texto a partir do HTML
                $mail->AltBody = strip_tags(
                    str_replace(
                        ['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>'],
                        ["\n", "\n", "\n", "\n\n", "\n", "\n"],
                        $html_content
                    )
                );
            }
            
            // ================================
            // ENVIAR
            // ================================
            
            $result = $mail->send();
            
            if ($result) {
                error_log("[EmailSender] ✓ Email enviado com sucesso para: $to");
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("[EmailSender] ✗ Erro ao enviar email para $to: " . $mail->ErrorInfo);
            error_log("[EmailSender] Detalhes: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enviar email com anexo
     */
    public function sendEmailWithAttachment($to, $to_name, $subject, $html_content, $attachment_path, $attachment_name = '') {
        if (!$this->isPHPMailerAvailable()) {
            error_log("[EmailSender] ERRO: PHPMailer não está instalado!");
            return false;
        }
        
        if (!file_exists($attachment_path)) {
            error_log("[EmailSender] ERRO: Anexo não encontrado: $attachment_path");
            return false;
        }
        
        try {
            $mail = new PHPMailer(true);
            
            // Configurações SMTP
            $mail->isSMTP();
            $mail->Host = $this->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_username;
            $mail->Password = $this->smtp_password;
            $mail->SMTPSecure = ($this->smtp_secure === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtp_port;
            $mail->CharSet = 'UTF-8';
            
            // Configurações do email
            $mail->setFrom($this->from_email, $this->from_name);
            $mail->addAddress($to, $to_name);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html_content;
            
            // Anexo
            $mail->addAttachment($attachment_path, $attachment_name ?: basename($attachment_path));
            
            return $mail->send();
            
        } catch (Exception $e) {
            error_log("[EmailSender] Erro com anexo: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Templates de Email HTML
 */
class EmailTemplates {
    
    /**
     * Template base com header e footer
     */
    public static function getBaseTemplate($title, $content) {
        return '
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #8e44ad, #9b59b6); padding: 30px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px;">🎓 Translators101</h1>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 30px; color: #333333; line-height: 1.6;">
                            ' . $content . '
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px; text-align: center; color: #666666; font-size: 12px;">
                            <p style="margin: 0;">© ' . date('Y') . ' Translators101 - Educação Continuada</p>
                            <p style="margin: 5px 0 0 0;">Este é um email automático, não responda.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }
    
    /**
     * Template de certificado emitido
     */
    public static function getCertificateTemplate($name, $lecture_title, $certificate_id, $view_url, $download_url, $verification_url) {
        $content = '
            <h2 style="color: #8e44ad; margin-top: 0;">🎉 Parabéns, ' . htmlspecialchars($name) . '!</h2>
            
            <p>Seu certificado de participação foi gerado com sucesso!</p>
            
            <div style="background-color: #f8f9fa; border-left: 4px solid #8e44ad; padding: 15px; margin: 20px 0;">
                <h3 style="color: #8e44ad; margin: 0 0 10px 0;">📋 Detalhes do Certificado:</h3>
                <ul style="margin: 0; padding-left: 20px;">
                    <li><strong>Palestra:</strong> ' . htmlspecialchars($lecture_title) . '</li>
                    <li><strong>Data de Emissão:</strong> ' . date('d/m/Y H:i') . '</li>
                    <li><strong>ID:</strong> ' . htmlspecialchars($certificate_id) . '</li>
                </ul>
            </div>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . $view_url . '" style="display: inline-block; background: linear-gradient(135deg, #c084fc, #a855f7); color: #ffffff; text-decoration: none; padding: 12px 25px; border-radius: 8px; font-weight: bold; margin: 5px;">👁️ Visualizar</a>
                <a href="' . $download_url . '" style="display: inline-block; background: linear-gradient(135deg, #10b981, #059669); color: #ffffff; text-decoration: none; padding: 12px 25px; border-radius: 8px; font-weight: bold; margin: 5px;">📥 Baixar</a>
            </div>
            
            <div style="background-color: #eff6ff; border: 1px solid #3b82f6; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <h3 style="color: #1d4ed8; margin: 0 0 10px 0;">🔍 Verificação de Autenticidade:</h3>
                <p style="margin: 0; font-size: 14px;">Qualquer pessoa pode verificar este certificado em:</p>
                <p style="margin: 10px 0 0 0; text-align: center;">
                    <a href="' . $verification_url . '" style="color: #3b82f6; word-break: break-all;">' . $verification_url . '</a>
                </p>
            </div>
            
            <p style="color: #666; font-size: 13px;"><small>Este link também está disponível via QR Code no certificado.</small></p>';
        
        return self::getBaseTemplate('Seu Certificado T101', $content);
    }
    
    /**
     * Template para definição de senha
     */
    public static function getPasswordSetupTemplate($name, $reset_link) {
        $content = '
            <h2 style="color: #8e44ad; margin-top: 0;">🔑 Defina sua senha</h2>
            
            <p>Olá, <strong>' . htmlspecialchars($name) . '</strong>!</p>
            
            <p>Para acessar a plataforma Translators101, você precisa definir sua senha de acesso.</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . $reset_link . '" style="display: inline-block; background: linear-gradient(135deg, #c084fc, #a855f7); color: #ffffff; text-decoration: none; padding: 15px 30px; border-radius: 8px; font-weight: bold; font-size: 16px;">🔐 Definir Minha Senha</a>
            </div>
            
            <div style="background-color: #fef3c7; border: 1px solid #f59e0b; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <p style="margin: 0; color: #92400e; font-size: 14px;">⏰ <strong>Importante:</strong> Este link expira em 24 horas.</p>
            </div>
            
            <p style="color: #666; font-size: 13px;">Se você não solicitou este email, pode ignorá-lo com segurança.</p>';
        
        return self::getBaseTemplate('Defina sua Senha', $content);
    }
    
    /**
     * Template de boas-vindas Hotmart
     */
    public static function getWelcomeHotmartTemplate($name, $reset_link) {
        $content = '
            <h2 style="color: #8e44ad; margin-top: 0;">🎉 Bem-vindo(a) à Translators101!</h2>
            
            <p>Olá, <strong>' . htmlspecialchars($name) . '</strong>!</p>
            
            <p>Que alegria ter você conosco! Sua compra foi confirmada e seu acesso à plataforma já está liberado.</p>
            
            <div style="background-color: #f0fdf4; border: 1px solid #10b981; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <h3 style="color: #047857; margin: 0 0 10px 0;">✅ O que você terá acesso:</h3>
                <ul style="margin: 0; padding-left: 20px; color: #065f46;">
                    <li>Palestras exclusivas sobre tradução e interpretação</li>
                    <li>Glossários especializados</li>
                    <li>Certificados de participação</li>
                    <li>Comunidade de profissionais</li>
                </ul>
            </div>
            
            <p>Para começar, você precisa definir sua senha de acesso:</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . $reset_link . '" style="display: inline-block; background: linear-gradient(135deg, #c084fc, #a855f7); color: #ffffff; text-decoration: none; padding: 15px 30px; border-radius: 8px; font-weight: bold; font-size: 16px;">🚀 Criar Minha Senha e Acessar</a>
            </div>
            
            <p>Estamos animados para fazer parte da sua jornada profissional! 🌟</p>';
        
        return self::getBaseTemplate('Bem-vindo(a) à Translators101', $content);
    }
    
    /**
     * Template de senha alterada
     */
    public static function getPasswordChangedTemplate($name) {
        $content = '
            <h2 style="color: #10b981; margin-top: 0;">✅ Senha definida com sucesso!</h2>
            
            <p>Olá, <strong>' . htmlspecialchars($name) . '</strong>!</p>
            
            <p>Sua senha foi definida com sucesso. Agora você pode fazer login na plataforma Translators101.</p>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="https://translators101.com/login.php" style="display: inline-block; background: linear-gradient(135deg, #10b981, #059669); color: #ffffff; text-decoration: none; padding: 15px 30px; border-radius: 8px; font-weight: bold; font-size: 16px;">🚀 Acessar Plataforma</a>
            </div>
            
            <div style="background-color: #eff6ff; border: 1px solid #3b82f6; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <p style="margin: 0; color: #1e40af; font-size: 14px;">🔐 <strong>Dica de Segurança:</strong> Nunca compartilhe sua senha com terceiros.</p>
            </div>
            
            <p>Aproveite todo o conteúdo da plataforma! 🌟</p>';
        
        return self::getBaseTemplate('Acesso Liberado', $content);
    }
    
    /**
     * Template personalizado
     */
    public static function getCustomEmailTemplate($subject, $content) {
        return self::getBaseTemplate($subject, '<div style="white-space: pre-wrap;">' . $content . '</div>');
    }
}

// ============================================
// FUNÇÕES HELPER PARA ENVIO DE EMAIL
// ============================================

/**
 * Enviar email de certificado
 */
function sendCertificateEmail($email, $name, $certificate_id, $lecture_title) {
    try {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'translators101.com';
        
        $view_url = "{$protocol}://{$host}/view_certificate_files.php?id={$certificate_id}";
        $download_url = "{$protocol}://{$host}/download_certificate_files.php?id={$certificate_id}";
        $verification_url = "{$protocol}://{$host}/verificar_certificado.php?id={$certificate_id}";
        
        $emailSender = new EmailSender();
        $subject = "🎓 Seu Certificado T101 - " . $lecture_title;
        $html_content = EmailTemplates::getCertificateTemplate($name, $lecture_title, $certificate_id, $view_url, $download_url, $verification_url);
        
        return $emailSender->sendEmail($email, $name, $subject, $html_content);
        
    } catch (Exception $e) {
        error_log("[Email] Erro ao enviar certificado: " . $e->getMessage());
        return false;
    }
}

/**
 * Enviar email de definição de senha
 */
function sendPasswordSetupEmail($email, $name, $reset_token) {
    try {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'translators101.com';
        $reset_link = "{$protocol}://{$host}/definir_senha.php?token={$reset_token}";
        
        $emailSender = new EmailSender();
        $subject = "🔑 Defina sua senha - Translators101";
        $html_content = EmailTemplates::getPasswordSetupTemplate($name, $reset_link);
        
        return $emailSender->sendEmail($email, $name, $subject, $html_content);
        
    } catch (Exception $e) {
        error_log("[Email] Erro ao enviar senha: " . $e->getMessage());
        return false;
    }
}

/**
 * Enviar email de boas-vindas Hotmart
 */
function sendWelcomeHotmartEmail($email, $name, $reset_token) {
    try {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'translators101.com';
        $reset_link = "{$protocol}://{$host}/definir_senha.php?token={$reset_token}";
        
        $emailSender = new EmailSender();
        $subject = "🎉 Boas-vindas à Translators101 - Acesso liberado!";
        $html_content = EmailTemplates::getWelcomeHotmartTemplate($name, $reset_link);
        
        return $emailSender->sendEmail($email, $name, $subject, $html_content);
        
    } catch (Exception $e) {
        error_log("[Email] Erro ao enviar boas-vindas: " . $e->getMessage());
        return false;
    }
}

/**
 * Enviar notificação de senha alterada
 */
function sendPasswordChangedEmail($email, $name) {
    try {
        $emailSender = new EmailSender();
        $subject = "✅ Senha definida com sucesso - Translators101";
        $html_content = EmailTemplates::getPasswordChangedTemplate($name);
        
        return $emailSender->sendEmail($email, $name, $subject, $html_content);
        
    } catch (Exception $e) {
        error_log("[Email] Erro ao enviar confirmação: " . $e->getMessage());
        return false;
    }
}
?>