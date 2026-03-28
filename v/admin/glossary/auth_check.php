<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';

if (!function_exists('isLoggedIn') || !function_exists('isAdmin')) {
    die('Funções de autenticação não encontradas. Verifique os includes.');
}

if (!isLoggedIn() || !isAdmin()) {
    header('Location: /login.php');
    exit;
}