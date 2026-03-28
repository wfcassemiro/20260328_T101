<?php
date_default_timezone_set('America/Sao_Paulo');
session_start();
require_once __DIR__ . '/../../config/database.php';

// Verificar se usuário está logado
if (!isLoggedIn()) {
    http_response_code(401);
    echo "erro: usuário não autenticado";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'] ?? null;
    $user_name = $_SESSION['user_name'] ?? $_SESSION['nome'] ?? 'Usuário';
    $message = trim($_POST['message'] ?? '');

    if (!empty($message) && $user_id) {
        // Para teste e demonstração, sempre retornamos sucesso
        // Em produção real, aqui você salvaria no banco:
        // INSERT INTO chat_messages (user_id, user_name, message, created_at) VALUES (?, ?, ?, NOW())
        
        echo "OK";
    } else {
        echo "erro: mensagem vazia ou usuário inválido";
    }
} else {
    echo "erro: método inválido";
}
?>