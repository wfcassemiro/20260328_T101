<?php
/**
 * Script de Diagnóstico Rápido do Sistema de Emails
 */

header('Content-Type: text/html; charset=UTF-8');

echo "<h1>🔍 Diagnóstico do Sistema de Emails</h1>";
echo "<style>body{font-family:Arial;max-width:800px;margin:50px auto;padding:20px;}
.ok{color:green;font-weight:bold;}.error{color:red;font-weight:bold;}
.info{background:#f0f0f0;padding:10px;margin:10px 0;border-radius:5px;}</style>";

echo "<h2>1. Verificando PHPMailer</h2>";

// Verificar se autoload existe
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "<p class='ok'>✅ Autoload encontrado</p>";
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    echo "<p class='error'>❌ Autoload NÃO encontrado</p>";
}

// Verificar se PHPMailer pode ser carregado
try {
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        echo "<p class='ok'>✅ PHPMailer DETECTADO!</p>";
        echo "<p class='info'>Classe: PHPMailer\\PHPMailer\\PHPMailer está disponível</p>";
    } else {
        echo "<p class='error'>❌ PHPMailer NÃO detectado</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Erro ao verificar PHPMailer: " . $e->getMessage() . "</p>";
}

echo "<h2>2. Verificando Configurações SMTP</h2>";

if (file_exists(__DIR__ . '/config/email_config.php')) {
    echo "<p class='ok'>✅ Arquivo de configuração encontrado</p>";
    require_once __DIR__ . '/config/email_config.php';
    
    echo "<div class='info'>";
    echo "<strong>Configurações:</strong><br>";
    echo "Host: " . (defined('SMTP_HOST') ? SMTP_HOST : 'NÃO DEFINIDO') . "<br>";
    echo "Porta: " . (defined('SMTP_PORT') ? SMTP_PORT : 'NÃO DEFINIDO') . "<br>";
    echo "Segurança: " . (defined('SMTP_SECURE') ? SMTP_SECURE : 'NÃO DEFINIDO') . "<br>";
    echo "Usuário: " . (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'NÃO DEFINIDO') . "<br>";
    echo "Senha: " . (defined('SMTP_PASSWORD') && !empty(SMTP_PASSWORD) ? '******* (configurada)' : 'NÃO CONFIGURADA') . "<br>";
    echo "Email Remetente: " . (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'NÃO DEFINIDO') . "<br>";
    echo "</div>";
    
    if (function_exists('isEmailConfigured')) {
        if (isEmailConfigured()) {
            echo "<p class='ok'>✅ Configuração SMTP está completa</p>";
        } else {
            echo "<p class='error'>❌ Configuração SMTP incompleta</p>";
        }
    }
} else {
    echo "<p class='error'>❌ Arquivo de configuração NÃO encontrado</p>";
}

echo "<h2>3. Teste de Envio</h2>";

if (class_exists('PHPMailer\\PHPMailer\\PHPMailer') && isEmailConfigured()) {
    echo "<p class='ok'>✅ Sistema pronto para enviar emails!</p>";
    echo "<p class='info'><strong>Próximo passo:</strong> Acesse <a href='/admin/emails.php'>/admin/emails.php</a> e envie um email de teste.</p>";
} else {
    echo "<p class='error'>❌ Sistema NÃO está pronto</p>";
    
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        echo "<p>Problema: PHPMailer não está carregado corretamente</p>";
    }
    
    if (!isEmailConfigured()) {
        echo "<p>Problema: Credenciais SMTP não estão configuradas</p>";
    }
}

echo "<h2>4. Estrutura de Arquivos</h2>";
echo "<div class='info'>";
echo "<strong>Arquivos necessários:</strong><br>";
$files = [
    '/vendor/autoload.php' => file_exists(__DIR__ . '/vendor/autoload.php'),
    '/vendor/phpmailer/phpmailer/src/PHPMailer.php' => file_exists(__DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php'),
    '/config/email_config.php' => file_exists(__DIR__ . '/config/email_config.php'),
    '/admin/emails.php' => file_exists(__DIR__ . '/admin/emails.php')
];

foreach ($files as $file => $exists) {
    $status = $exists ? "<span class='ok'>✅</span>" : "<span class='error'>❌</span>";
    echo "$status $file<br>";
}
echo "</div>";

echo "<hr>";
echo "<p><strong>Resumo:</strong></p>";

$issues = [];
if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    $issues[] = "PHPMailer não instalado/detectado";
}
if (!function_exists('isEmailConfigured') || !isEmailConfigured()) {
    $issues[] = "Credenciais SMTP não configuradas";
}

if (empty($issues)) {
    echo "<p class='ok' style='font-size:1.2em;'>🎉 TUDO PRONTO! O sistema deve funcionar agora!</p>";
    echo "<p><a href='/admin/emails.php' style='background:#4CAF50;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;'>Ir para Sistema de Emails</a></p>";
} else {
    echo "<p class='error'>Problemas encontrados:</p>";
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li>$issue</li>";
    }
    echo "</ul>";
}
?>