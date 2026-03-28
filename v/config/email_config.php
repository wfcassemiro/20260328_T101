<?php
/**
 * Configuração de Email para Translators101
 * Este arquivo apenas define constantes e funções auxiliares
 * As classes EmailSender e EmailTemplates estão definidas em email.php
 */

// Configurações SMTP (caso não estejam definidas em outro lugar)
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'smtp.hostinger.com');
    define('SMTP_PORT', 465);
    define('SMTP_USERNAME', 'contato@translators101.com');
    define('SMTP_PASSWORD', 'r:#D$!r=X1');
    define('SMTP_FROM_EMAIL', 'contato@translators101.com');
    define('SMTP_FROM_NAME', 'Translators101');
}

/**
 * Verifica se o email está configurado
 */
if (!function_exists('isEmailConfigured')) {
    function isEmailConfigured() {
        return defined('SMTP_HOST') && !empty(SMTP_HOST) && 
               defined('SMTP_USERNAME') && !empty(SMTP_USERNAME) && 
               defined('SMTP_PASSWORD') && !empty(SMTP_PASSWORD);
    }
}
