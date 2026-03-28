<?php
// save_simple_progress.php - Versão Unificada para Certificados
session_start();
header('Content-Type: application/json');

// Ajuste o caminho do database.php se necessário (baseado no seu arquivo original era ../../config/database.php)
require_once __DIR__ . '/../../config/database.php';

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$lecture_id = $input['lecture_id'] ?? null;
$user_id = $input['user_id'] ?? null;
$watched_seconds = isset($input['watched_seconds']) ? (int)$input['watched_seconds'] : 0;

if (empty($lecture_id) || empty($user_id) || $user_id !== $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos ou divergentes.']);
    exit;
}

try {
    // 1. Buscar dados da palestra para manter o legado (resource/title)
    $stmt = $pdo->prepare("SELECT title FROM lectures WHERE id = ? LIMIT 1");
    $stmt->execute([$lecture_id]);
    $lecture = $stmt->fetch(PDO::FETCH_ASSOC);
    $lecture_title = $lecture['title'] ?? 'Palestra Desconhecida';

    // 2. Buscar log existente por lecture_id (Prioridade) ou resource (Legado)
    // Usamos 'watch' como action para alinhar com o sistema de certificados
    $stmt = $pdo->prepare("
        SELECT id, last_watched_seconds, accumulated_watch_time 
        FROM access_logs 
        WHERE user_id = ? AND (lecture_id = ? OR (resource = ? AND (lecture_id IS NULL OR lecture_id = '')))
        AND action = 'watch'
        ORDER BY lecture_id DESC, updated_at DESC LIMIT 1
    ");
    $stmt->execute([$user_id, $lecture_id, $lecture_title]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    $now = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';

    if ($log) {
        $prev_last = (int)$log['last_watched_seconds'];
        $prev_acc  = (int)$log['accumulated_watch_time'];

        // Cálculo de acúmulo: se o usuário avançou, somamos a diferença
        $delta = $watched_seconds - $prev_last;
        $new_acc = ($delta > 0) ? ($prev_acc + $delta) : $prev_acc;
        $new_last = max($prev_last, $watched_seconds);

        $stmt = $pdo->prepare("
            UPDATE access_logs 
            SET lecture_id = ?, 
                resource = ?, 
                last_watched_seconds = ?, 
                accumulated_watch_time = ?, 
                updated_at = ?,
                ip_address = ?,
                user_agent = ?
            WHERE id = ?
        ");
        $stmt->execute([$lecture_id, $lecture_title, $new_last, $new_acc, $now, $ip, $ua, $log['id']]);
        $response['message'] = "Atualizado. Acc: {$new_acc}s";
    } else {
        // Criar novo registro (usando 'watch' para o certificado encontrar)
        $stmt = $pdo->prepare("
            INSERT INTO access_logs 
            (user_id, action, resource, lecture_id, ip_address, user_agent, created_at, updated_at, last_watched_seconds, accumulated_watch_time, watch_sessions) 
            VALUES (?, 'watch', ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$user_id, $lecture_title, $lecture_id, $ip, $ua, $now, $now, $watched_seconds, $watched_seconds]);
        $response['message'] = "Novo log criado.";
    }

    $response['success'] = true;

} catch (Exception $e) {
    $response['message'] = 'Erro: ' . $e->getMessage();
}

echo json_encode($response);