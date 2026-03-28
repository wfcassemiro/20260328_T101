<?php
require_once __DIR__ . '/../config/database.php';

$overlay_message = "Nenhuma mensagem selecionada para exibição.";

try {
    // Buscar o ID da mensagem que está marcada para overlay
    $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'current_overlay_chat_id'");
    $stmt->execute();
    $result = $stmt->fetch();
    $chat_id_for_overlay = $result ? $result['setting_value'] : null;

    if ($chat_id_for_overlay) {
        // Buscar a mensagem completa usando o ID
        $stmt = $pdo->prepare("SELECT username, message FROM chat_messages WHERE id = ?");
        $stmt->execute([$chat_id_for_overlay]);
        $message_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($message_data) {
            $username = htmlspecialchars($message_data['username']);
            $message = nl2br(htmlspecialchars($message_data['message']));
            $overlay_message = "<div class='overlay-display-message'><span class='overlay-username'>{$username}:</span> <span class='overlay-text'>{$message}</span></div>";
        }
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar mensagem de overlay: " . $e->getMessage());
    $overlay_message = "<div class='system-message'><i class='fas fa-exclamation-triangle'></i> Erro ao carregar overlay.</div>";
}

echo $overlay_message;
?>