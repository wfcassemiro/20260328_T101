<?php
// Configurações do sistema
define('REQUIRED_WATCH_PERCENTAGE', 80);  // Porcentagem mínima para certificado
define('MAX_SKIPS_ALLOWED', 5);    // Máximo de skips permitidos
define('SKIP_THRESHOLD', 10);    // Segundos para detectar skip

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Função para gerar UUID (só definir se não existir)
if (!function_exists('generateUUID')) {
    function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

// Funções auxiliares (só definir se não existirem)
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

// Função para verificar se usuário está logado
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']);
    }
}

// Função para verificar se é admin
if (!function_exists('isAdmin')) {
    function isAdmin() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
}

// Função para verificar se é assinante
if (!function_exists('isSubscriber')) {
    function isSubscriber() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'subscriber']);
    }
}
?>