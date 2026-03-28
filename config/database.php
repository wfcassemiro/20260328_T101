<?php
// config/database.php

// Conexão com o banco de dados
// **** DADOS DE CONEXÃO CORRIGIDOS ****
$host = 'localhost'; 
$db   = 'u335416710_t101_db'; // Corrigido de $dbname para $db
$user = 'u335416710_t101'; // Corrigido de $username para $user
$pass = 'Pa392ap!'; // Corrigido de $password para $pass
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

// Funções auxiliares de autenticação (garantir que estão definidas globalmente)
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
}

if (!function_exists('isSubscriber')) {
    function isSubscriber() {
        // CORREÇÃO: Admin tem acesso completo, incluindo videoteca
        if (isAdmin()) {
            return true;
        }
        return isLoggedIn() && isset($_SESSION['is_subscriber']) && $_SESSION['is_subscriber'] == 1;
    }
}

// Função adicional para verificar acesso à videoteca especificamente
if (!function_exists('hasVideotecaAccess')) {
    function hasVideotecaAccess() {
        // Admin ou assinante têm acesso à videoteca
        return isAdmin() || isSubscriber();
    }
}

?>
