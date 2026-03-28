<?php
/**
 * Autoloader para PHPMailer
 */

spl_autoload_register(function ($class) {
    // Namespace do PHPMailer
    $prefix = 'PHPMailer\\PHPMailer\\';
    
    // Base directory para o namespace
    $base_dir = __DIR__ . '/phpmailer/phpmailer/src/';
    
    // Verifica se a classe usa o namespace
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    // Pega o nome relativo da classe
    $relative_class = substr($class, $len);
    
    // Substitui namespace separadores por separadores de diretório
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // Se o arquivo existe, inclui
    if (file_exists($file)) {
        require $file;
    }
});