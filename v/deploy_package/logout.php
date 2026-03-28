<?php
session_start();
require_once 'config/database.php';

// Log de logout
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO access_logs (user_id, action, ip_address, user_agent) VALUES (?, 'logout', ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
    } catch(PDOException $e) {
        // Falha silenciosa no log
    }
}

// Destruir sessão
session_destroy();

// Redirecionar para home
header('Location: /');
exit;
?>
