<?php
// live-stream-api.php

if (session_status() === PHP_SESSION_NONE) { session_start(); }
ini_set('display_errors', 0); error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

date_default_timezone_set('America/Sao_Paulo');

// --- HELPER DE ADMIN ---
function isUserAdmin() {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') return true;
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') return true;
    return false;
}

// --- CONEXÃO ---
try {
    $paths = [__DIR__ . '/../config/database.php', __DIR__ . '/../../config/database.php', $_SERVER['DOCUMENT_ROOT'] . '/config/database.php'];
    foreach ($paths as $path) { if (file_exists($path)) { require_once $path; break; } }
    if (!isset($pdo)) throw new Exception("Banco não conectado");
    $pdo->exec("SET NAMES utf8mb4");
} catch (Exception $e) { http_response_code(500); echo json_encode(['error' => $e->getMessage()]); exit; }

$action = $_REQUEST['action'] ?? '';

try {
    // 1. ENVIAR MENSAGEM
    if ($action === 'send_message') {
        if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
        $msg = trim($_POST['message'] ?? '');
        if (empty($msg)) { echo json_encode(['success' => false]); exit; }
        
        $stmt = $pdo->prepare("INSERT INTO chat_messages (username, message, created_at) VALUES (?, ?, NOW())");
        $username = $_SESSION['user_name'] ?? $_SESSION['nome'] ?? 'Usuário';
        $stmt->execute([$username, $msg]);
        echo json_encode(['success' => true, 'message_id' => $pdo->lastInsertId()]);
        exit;
    }

    // 2. REAGIR
    if ($action === 'react') {
        if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
        $msgId = intval($_POST['message_id'] ?? 0);
        $emoji = $_POST['emoji'] ?? '';
        $userId = $_SESSION['user_id'];
        
        if ($msgId > 0 && !empty($emoji)) {
            $check = $pdo->prepare("SELECT id FROM chat_reactions WHERE message_id = ? AND user_id = ? AND emoji = ?");
            $check->execute([$msgId, $userId, $emoji]);
            if ($check->rowCount() > 0) {
                $pdo->prepare("DELETE FROM chat_reactions WHERE message_id = ? AND user_id = ? AND emoji = ?")->execute([$msgId, $userId, $emoji]);
            } else {
                $pdo->prepare("INSERT INTO chat_reactions (message_id, user_id, emoji) VALUES (?, ?, ?)")->execute([$msgId, $userId, $emoji]);
            }
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // 3. POLLING (ATUALIZADO PARA REAÇÕES EM TEMPO REAL)
    if ($action === 'poll') {
        $lastId = intval($_GET['last_id'] ?? 0);
        
        // A. Novas Mensagens
        $stmt = $pdo->prepare("SELECT id, username, message, created_at FROM chat_messages WHERE id > ? ORDER BY id ASC");
        $stmt->execute([$lastId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($messages as &$msg) {
            $sessionUser = $_SESSION['user_name'] ?? $_SESSION['nome'] ?? '';
            $msg['is_admin'] = (isUserAdmin() && $msg['username'] === $sessionUser) ? 1 : 0;
            $msg['user_name'] = $msg['username'];
            // Reações iniciais da mensagem nova
            $rStmt = $pdo->prepare("SELECT emoji, COUNT(*) as c FROM chat_reactions WHERE message_id = ? GROUP BY emoji");
            $rStmt->execute([$msg['id']]);
            $msg['reactions'] = [];
            while($row = $rStmt->fetch(PDO::FETCH_ASSOC)) $msg['reactions'][$row['emoji']] = $row['c'];
        }

        // B. Atualização de Reações (Últimas 50 mensagens para garantir update em tempo real)
        // Isso permite atualizar reações de mensagens que já estão na tela
        $reactionsUpdate = [];
        $stmtR = $pdo->query("SELECT message_id, emoji, COUNT(*) as c FROM chat_reactions 
                              WHERE message_id IN (SELECT id FROM (SELECT id FROM chat_messages ORDER BY id DESC LIMIT 50) as tmp) 
                              GROUP BY message_id, emoji");
        while($row = $stmtR->fetch(PDO::FETCH_ASSOC)) {
            $mid = $row['message_id'];
            if(!isset($reactionsUpdate[$mid])) $reactionsUpdate[$mid] = [];
            $reactionsUpdate[$mid][$row['emoji']] = $row['c'];
        }

        // C. Configs e Status
        $settings = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('active_overlay', 'live_status')")->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // D. Presença
        $presence = [];
        if (isUserAdmin()) {
            $presence = $pdo->query("SELECT user_name, total_minutes FROM live_presence WHERE live_date = CURDATE()")->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'messages' => $messages,
            'reactions_update' => $reactionsUpdate, // Novo campo
            'overlay' => isset($settings['active_overlay']) ? json_decode($settings['active_overlay'], true) : null,
            'live_status' => $settings['live_status'] ?? '0',
            'presence' => $presence
        ]);
        exit;
    }

    // 4. OVERLAY / CLEAR / PRESENCE... (Mantido igual)
    if ($action === 'set_overlay' && isUserAdmin()) {
        $data = $_POST['data'] ?? '';
        $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES ('active_overlay', ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$data]);
        echo json_encode(['success' => true]); exit;
    }
    if ($action === 'clear_all_messages' && isUserAdmin()) {
        $pdo->exec("DELETE FROM chat_messages"); $pdo->exec("DELETE FROM chat_reactions"); $pdo->exec("ALTER TABLE chat_messages AUTO_INCREMENT = 1");
        echo json_encode(['success' => true]); exit;
    }

} catch (Exception $e) { http_response_code(500); echo json_encode(['error' => $e->getMessage()]); }
?>