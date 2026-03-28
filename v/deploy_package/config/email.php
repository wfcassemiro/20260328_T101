<?php
/**
 * Configurações de Email - Hostinger SMTP
 * Sistema de envio de emails da Translators101
 */

// Configurações SMTP da Hostinger
define('SMTP_HOST', 'br1189.hostgator.com.br'); // Host SMTP da Hostinger
define('SMTP_PORT', 587); // Porta SMTP (587 para TLS, 465 para SSL)
define('SMTP_USERNAME', 'contato@translators101.com'); // Seu email
define('SMTP_PASSWORD', 'r:#D$!r=X1'); // Senha do email (será solicitada para o usuário)
define('SMTP_FROM_EMAIL', 'contato@translators101.com');
define('SMTP_FROM_NAME', 'Translators101');

// Configurações de template
define('EMAIL_CHARSET', 'UTF-8');
define('EMAIL_CONTENT_TYPE', 'text/html');

/**
 * Verificar se o sistema de email está configurado
 */
function isEmailConfigured() {
    return !empty(SMTP_PASSWORD) && SMTP_PASSWORD !== '';
}

/**
 * Classe para envio de emails via SMTP
 */
class EmailSender {
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $from_email;
    private $from_name;
    
    public function __construct() {
        $this->smtp_host = SMTP_HOST;
        $this->smtp_port = SMTP_PORT;
        $this->smtp_username = SMTP_USERNAME;
        $this->smtp_password = SMTP_PASSWORD;
        $this->from_email = SMTP_FROM_EMAIL;
        $this->from_name = SMTP_FROM_NAME;
    }
    
    /**
     * Verificar se PHPMailer está disponível, senão usar mail() nativo
     */
    private function isPHPMailerAvailable() {
        return class_exists('PHPMailer\PHPMailer\PHPMailer');
    }
    
    /**
     * Enviar email usando PHPMailer (se disponível) ou mail() nativo
     */
    public function sendEmail($to, $to_name, $subject, $html_content, $text_content = '') {
        if ($this->isPHPMailerAvailable()) {
            return $this->sendWithPHPMailer($to, $to_name, $subject, $html_content, $text_content);
        } else {
            return $this->sendWithNativeMail($to, $to_name, $subject, $html_content, $text_content);
        }
    }
    
    /**
     * Enviar email usando PHPMailer
     */
    private function sendWithPHPMailer($to, $to_name, $subject, $html_content, $text_content) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Configurações SMTP
            $mail->isSMTP();
            $mail->Host = $this->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_username;
            $mail->Password = $this->smtp_password;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtp_port;
            $mail->CharSet = EMAIL_CHARSET;
            
            // Configurações do email
            $mail->setFrom($this->from_email, $this->from_name);
            $mail->addAddress($to, $to_name);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html_content;
            
            if (!empty($text_content)) {
                $mail->AltBody = $text_content;
            }
            
            return $mail->send();
            
        } catch (Exception $e) {
            error_log("Erro ao enviar email com PHPMailer: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enviar email usando função mail() nativa do PHP
     */
    private function sendWithNativeMail($to, $to_name, $subject, $html_content, $text_content) {
        try {
            // Headers para SMTP
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: ' . EMAIL_CONTENT_TYPE . '; charset=' . EMAIL_CHARSET,
                'From: ' . $this->from_name . ' <' . $this->from_email . '>',
                'Reply-To: ' . $this->from_email,
                'X-Mailer: PHP/' . phpversion(),
                'X-Priority: 3'
            ];
            
            // Configurar SMTP (se o servidor suportar)
            if (function_exists('ini_set')) {
                ini_set('SMTP', $this->smtp_host);
                ini_set('smtp_port', $this->smtp_port);
                ini_set('sendmail_from', $this->from_email);
            }
            
            // Enviar email
            return mail(
                $to_name ? "$to_name <$to>" : $to,
                $subject,
                $html_content,
                implode("\r\n", $headers)
            );
            
        } catch (Exception $e) {
            error_log("Erro ao enviar email com mail(): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Testar envio de email
     */
    public function testEmail($to = null) {
        $test_email = $to ?: $this->from_email;
        
        $subject = 'Teste de Email - Translators101';
        $html_content = $this->getTestEmailTemplate();
        
        $result = $this->sendEmail($test_email, 'Teste', $subject, $html_content);
        
        return [
            'success' => $result,
            'method' => $this->isPHPMailerAvailable() ? 'PHPMailer' : 'mail()',
            'to' => $test_email,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Template de teste
     */
    private function getTestEmailTemplate() {
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;">
            <div style="background-color: #7c3aed; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;">
                <h1 style="margin: 0; font-size: 24px;">Translators101</h1>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">Teste de Envio de Email</p>
            </div>
            
            <div style="background-color: white; padding: 30px; border-radius: 0 0 8px 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <h2 style="color: #333; margin-top: 0;">✅ Email funcionando!</h2>
                
                <p style="color: #666; line-height: 1.6;">
                    Este é um email de teste do sistema Translators101. Se você recebeu esta mensagem, 
                    significa que a configuração de email está funcionando corretamente.
                </p>
                
                <div style="background-color: #f3f4f6; padding: 15px; border-radius: 6px; margin: 20px 0;">
                    <h3 style="color: #7c3aed; margin: 0 0 10px 0; font-size: 16px;">Informações do Teste:</h3>
                    <ul style="margin: 0; padding-left: 20px; color: #666;">
                        <li><strong>Data:</strong> ' . date('d/m/Y H:i:s') . '</li>
                        <li><strong>Sistema:</strong> Hostinger SMTP</li>
                        <li><strong>Status:</strong> Funcionando ✅</li>
                    </ul>
                </div>
                
                <p style="color: #666; margin-bottom: 0;">
                    Atenciosamente,<br>
                    <strong>Equipe Translators101</strong>
                </p>
            </div>
            
            <div style="text-align: center; padding: 20px; color: #888; font-size: 12px;">
                © 2025 Translators101. Todos os direitos reservados.
            </div>
        </div>';
    }
}

/**
 * Classe para templates de email
 */
class EmailTemplates {
    
    /**
     * Template base para todos os emails
     */
    public static function getBaseTemplate($title, $content, $footer_text = '') {
        $footer = $footer_text ?: 'Atenciosamente,<br><strong>Equipe Translators101</strong>';
        
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;">
            <div style="background-color: #7c3aed; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;">
                <h1 style="margin: 0; font-size: 24px;">Translators101</h1>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">' . htmlspecialchars($title) . '</p>
            </div>
            
            <div style="background-color: white; padding: 30px; border-radius: 0 0 8px 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                ' . $content . '
                
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                    <p style="color: #666; margin-bottom: 0;">
                        ' . $footer . '
                    </p>
                </div>
            </div>
            
            <div style="text-align: center; padding: 20px; color: #888; font-size: 12px;">
                <p style="margin: 0;">© 2025 Translators101. Todos os direitos reservados.</p>
                <p style="margin: 5px 0 0 0;">
                    Dúvidas? <a href="https://translators101.com/contato.php" style="color: #7c3aed;">Entre em contato</a>
                </p>
            </div>
        </div>';
    }
    
    /**
     * Template para definição de senha
     */
    public static function getPasswordSetupTemplate($name, $reset_link) {
        $content = '
        <h2 style="color: #333; margin-top: 0;">🔑 Defina sua senha de acesso</h2>
        
        <p style="color: #666; line-height: 1.6;">
            Olá, <strong>' . htmlspecialchars($name) . '</strong>!
        </p>
        
        <p style="color: #666; line-height: 1.6;">
            Receba as boas-vindas à Translators101! 🎉 Estamos felizes por ter você conosco 
            nesta jornada de conhecimento e crescimento profissional.
        </p>
        
        <p style="color: #666; line-height: 1.6;">
            Este é mais um acesso que assinantes Premium a todo nosso conteúdo. Para acessar, é necessário
            definir uma senha personalizada.
            É simples e rápido! Sugiro usar a mesma senha que já usa na Hotmart.
        </p>
        
        <div style="text-align: center; margin: 25px 0;">
            <a href="' . htmlspecialchars($reset_link) . '" 
               style="background-color: #7c3aed; color: white; padding: 12px 25px; text-decoration: none; 
                      border-radius: 6px; font-weight: bold; display: inline-block;">
                ✨ Definir Minha Senha
            </a>
        </div>
        
        <div style="background-color: #fef3c7; border: 1px solid #f59e0b; padding: 15px; border-radius: 6px; margin: 20px 0;">
            <p style="margin: 0; color: #92400e; font-size: 14px;">
                <strong>⏰ Importante:</strong> Este link é válido por 7 dias. Após definir sua senha, 
                você poderá fazer login normalmente no site e também na Hotmart.
            </p>
        </div>
        
        <h3 style="color: #7c3aed; margin: 25px 0 15px 0;">🎯 O que você encontrará na plataforma:</h3>
        <ul style="color: #666; line-height: 1.6; padding-left: 20px;">
            <li>Palestras exclusivas com especialistas em tradução</li>
            <li>Certificados profissionais para cada palestra concluída</li>
            <li>Materiais de apoio e recursos práticos</li>
            <li>Glossários especializados por área</li>
            <li>Acesso 24/7 de qualquer dispositivo</li>
        </ul>
        
        <p style="color: #666; line-height: 1.6; margin-top: 25px;">
            Se você tiver qualquer dúvida ou dificuldade, nossa equipe está sempre pronta para ajudar. 
            É só entrar em contato conosco! 💜
        </p>';
        
        return self::getBaseTemplate('Boas-vindas!', $content);
    }
    
    /**
     * Template para boas-vindas após compra Hotmart
     */
    public static function getWelcomeHotmartTemplate($name, $reset_link) {
        $content = '
        <h2 style="color: #333; margin-top: 0;">🎉 Sua compra foi confirmada!</h2>
        
        <p style="color: #666; line-height: 1.6;">
            Olá, <strong>' . htmlspecialchars($name) . '</strong>!
        </p>
        
        <p style="color: #666; line-height: 1.6;">
            Que alegria ter você na comunidade Translators101! ✨ Sua compra foi processada 
            com sucesso e você já tem acesso total à nossa plataforma educacional.
        </p>
        
        <div style="background-color: #d1fae5; border: 1px solid #10b981; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3 style="color: #059669; margin: 0 0 10px 0; font-size: 18px;">🚀 Próximos passos:</h3>
            <ol style="color: #047857; margin: 0; padding-left: 20px; line-height: 1.6;">
                <li><strong>Defina sua senha</strong> usando o botão abaixo</li>
                <li><strong>Faça login</strong> na Hotmart</li>
                <li><strong>Explore</strong> todo o conteúdo disponível</li>
                <li><strong>Assista</strong> a sua primeira palestra</li>
            </ol>
        </div>
        
        <div style="text-align: center; margin: 25px 0;">
            <a href="' . htmlspecialchars($reset_link) . '" 
               style="background-color: #7c3aed; color: white; padding: 15px 30px; text-decoration: none; 
                      border-radius: 6px; font-weight: bold; display: inline-block; font-size: 16px;">
                🔑 Acessar Minha Conta
            </a>
        </div>
        
        <h3 style="color: #7c3aed; margin: 25px 0 15px 0;">💎 Seus benefícios incluem:</h3>
        <div style="background-color: #f8fafc; padding: 20px; border-radius: 6px; border-left: 4px solid #7c3aed;">
            <ul style="color: #666; line-height: 1.8; margin: 0; padding-left: 20px;">
                <li><strong>Acesso</strong> a todas as palestras gravadas</li>
                <li><strong>Certificados digitais</strong> para cada palestra concluída</li>
                <li><strong>Atualizações gratuitas</strong> de conteúdo</li>
                <li><strong>Comunidade exclusiva</strong> de tradutores</li>
                <li><strong>Materiais extras</strong> e recursos práticos</li>
            </ul>
        </div>
        
        <p style="color: #666; line-height: 1.6; margin-top: 25px;">
            Estamos aqui para apoiar seu crescimento profissional. Qualquer dúvida, é só nos procurar! 
            Vamos construir uma carreira de sucesso na tradução! 💪
        </p>';
        
        return self::getBaseTemplate('Bem-vinda(o) à Translators101!', $content);
    }
    
    /**
     * Template para notificação de senha redefinida
     */
    public static function getPasswordChangedTemplate($name) {
        $content = '
        <h2 style="color: #333; margin-top: 0;">✅ Senha definida com sucesso!</h2>
        
        <p style="color: #666; line-height: 1.6;">
            Olá, <strong>' . htmlspecialchars($name) . '</strong>!
        </p>
        
        <p style="color: #666; line-height: 1.6;">
            Perfeito! Sua senha foi definida com sucesso. Agora você pode fazer login na plataforma 
            Translators101 sempre que quiser acessar nosso conteúdo exclusivo.
        </p>
        
        <div style="text-align: center; margin: 25px 0;">
            <a href="https://translators101.com/login.php" 
               style="background-color: #10b981; color: white; padding: 12px 25px; text-decoration: none; 
                      border-radius: 6px; font-weight: bold; display: inline-block;">
                🚀 Acessar Plataforma
            </a>
        </div>
        
        <div style="background-color: #eff6ff; border: 1px solid #3b82f6; padding: 15px; border-radius: 6px; margin: 20px 0;">
            <h3 style="color: #1d4ed8; margin: 0 0 10px 0; font-size: 16px;">🔐 Dica de Segurança:</h3>
            <p style="margin: 0; color: #1e40af; font-size: 14px;">
                Guarde sua senha em local seguro e nunca a compartilhe com terceiros. 
                Se esquecer sua senha, você pode solicitar uma nova a qualquer momento.
            </p>
        </div>
        
        <p style="color: #666; line-height: 1.6;">
            Aproveite todo o conteúdo da plataforma e lembre-se: estamos aqui para apoiar 
            seu crescimento profissional! 🌟
        </p>';
        
        return self::getBaseTemplate('Acesso liberado!', $content);
    }

    /**
     * Template para email personalizado
     */
    public static function getCustomEmailTemplate($subject, $content) {
        return self::getBaseTemplate($subject, $content);
    }
}

/**
 * Função para enviar email de definição de senha
 */
function sendPasswordSetupEmail($email, $name, $reset_token) {
    try {
        $reset_link = "https://" . $_SERVER['HTTP_HOST'] . "/definir_senha.php?token=" . $reset_token;
        
        $emailSender = new EmailSender();
        $subject = "🔑 Defina sua senha também no site da Translators101";
        $html_content = EmailTemplates::getPasswordSetupTemplate($name, $reset_link);
        
        $result = $emailSender->sendEmail($email, $name, $subject, $html_content);
        
        // Log do envio
        if ($result) {
            error_log("Email de senha enviado para: $email");
        } else {
            error_log("Falha ao enviar email de senha para: $email");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Erro ao enviar email de senha: " . $e->getMessage());
        return false;
    }
}

/**
 * Função para enviar email de boas-vindas Hotmart
 */
function sendWelcomeHotmartEmail($email, $name, $reset_token) {
    try {
        $reset_link = "https://" . $_SERVER['HTTP_HOST'] . "/definir_senha.php?token=" . $reset_token;
        
        $emailSender = new EmailSender();
        $subject = "🎉 Boas-vindas à Translators101 - Acesso liberado!";
        $html_content = EmailTemplates::getWelcomeHotmartTemplate($name, $reset_link);
        
        $result = $emailSender->sendEmail($email, $name, $subject, $html_content);
        
        // Log do envio
        if ($result) {
            error_log("Email de boas-vindas Hotmart enviado para: $email");
        } else {
            error_log("Falha ao enviar email de boas-vindas para: $email");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Erro ao enviar email de boas-vindas: " . $e->getMessage());
        return false;
    }
}

/**
 * Função para enviar notificação de senha alterada
 */
function sendPasswordChangedEmail($email, $name) {
    try {
        $emailSender = new EmailSender();
        $subject = "✅ Senha definida com sucesso - Translators101";
        $html_content = EmailTemplates::getPasswordChangedTemplate($name);
        
        $result = $emailSender->sendEmail($email, $name, $subject, $html_content);
        
        // Log do envio
        if ($result) {
            error_log("Email de confirmação enviado para: $email");
        } else {
            error_log("Falha ao enviar email de confirmação para: $email");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Erro ao enviar email de confirmação: " . $e->getMessage());
        return false;
    }
}
?>