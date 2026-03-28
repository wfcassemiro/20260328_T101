<?php
// Configurações do sistema
define('REQUIRED_WATCH_PERCENTAGE', 80);  // Porcentagem mínima para certificado
define('MAX_SKIPS_ALLOWED', 5);           // Máximo de skips permitidos
define('SKIP_THRESHOLD', 10);             // Segundos para detectar skip

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// -----------------------------------------------------------------------------
// Conexão com o banco de dados (usando PDO)
// -----------------------------------------------------------------------------
$host    = 'localhost';
$db      = 'u335416710_t101_db';
$user    = 'u335416710_t101';
$pass    = 'Pa392ap!';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        die("Erro de Conexão com o Banco de Dados: Acesso negado. Verifique usuário e senha. Detalhes: " . $e->getMessage());
    } else {
        die("Erro de Conexão com o Banco de Dados: " . $e->getMessage());
    }
}

// Compatibilidade com scripts antigos que usam getDbConnection()
if (!function_exists('getDbConnection')) {
    function getDbConnection() {
        global $pdo;
        return $pdo;
    }
}

// Funções auxiliares
if (!function_exists('generateUUID')) {
    function generateUUID() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

if (!function_exists('hashPassword')) {
    function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }
}

if (!function_exists('verifyPassword')) {
    function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
}

if (!function_exists('isSubscriber')) {
    function isSubscriber() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'subscriber']);
    }
}

if (!function_exists('isSuperAdmin')) {
    function isSuperAdmin() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    }
}

if (!function_exists('hasVideotecaAccess')) {
    function hasVideotecaAccess() {
        return isAdmin() || isSubscriber();
    }
}