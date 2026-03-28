<?php
// admin/auth_check.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Carregar database.php se ainda não foi carregado
if (!function_exists('isAdmin')) {
    require_once __DIR__ . "/../config/database.php";
}

// Carregar config.php se existir
$config_path = __DIR__ . "/../config/config.php";
if (file_exists($config_path)) {
    require_once $config_path;
}

// NÃO redeclarar isAdmin() - já existe em database.php
?>