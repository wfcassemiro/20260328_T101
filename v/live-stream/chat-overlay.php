<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/auth_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id  = (int) $_POST['id'];
    $pdo = getDbConnection();

    try {
        // Buscar mensagem pelo ID no chat
        $stmt = $pdo->prepare("SELECT username, message FROM chat WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if ($row) {
            // Inserir/atualizar no overlay
            $stmt2 = $pdo->prepare("INSERT INTO chat_overlay (username, message, created_at) VALUES (:user, :msg, NOW())");
            $stmt2->execute([
                ':user' => $row['username'],
                ':msg'  => $row['message']
            ]);
            http_response_code(200);
            echo "Mensagem enviada para o overlay!";
        } else {
            http_response_code(404);
            echo "Mensagem não encontrada.";
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo "Erro ao definir overlay: " . $e->getMessage();
    }
} else {
    http_response_code(400);
    echo "Requisição inválida.";
}