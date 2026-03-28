<?php
date_default_timezone_set('America/Sao_Paulo');
// Conexão com o banco de dados
$host = 'localhost';
$db   = 'u335416710_t101_db';
$user = 'u335416710_t101';
$pass = 'Pa392ap!';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Mensagem de erro aprimorada para depuração
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        die("Erro de Conexão com o Banco de Dados: Acesso negado. Verifique o usuário e senha no config/database.php. Detalhes: " . $e->getMessage());
    } else {
        die("Erro de Conexão com o Banco de Dados: " . $e->getMessage());
    }
}

// Funções auxiliares para autenticação
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        // CORREÇÃO: Usar user_role ao invés de role, ou is_admin
        // O sistema usa $_SESSION['user_role'] e $_SESSION['is_admin']
        if (!isLoggedIn()) {
            return false;
        }
        
        // Primeira opção: verificar is_admin (mais direto)
        if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) {
            return true;
        }
        
        // Segunda opção: verificar user_role (fallback)
        if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
            return true;
        }
        
        // Terceira opção: verificar role sem prefixo (compatibilidade)
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            return true;
        }
        
        return false;
    }
}

if (!function_exists('hasVideotecaAccess')) {
    function hasVideotecaAccess() {
        return isLoggedIn();
    }
}
?>