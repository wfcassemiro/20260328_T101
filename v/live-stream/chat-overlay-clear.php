<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/auth_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';

$pdo = getDbConnection();

try {
    $pdo->exec("DELETE FROM chat_overlay");
    http_response_code(200);
    echo "Overlay limpo!";
} catch (PDOException $e) {
    http_response_code(500);
    echo "Erro ao limpar overlay: " . $e->getMessage();
}