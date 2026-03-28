<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Incluir configuração do banco
require_once '../database.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['user_id'], $input['lecture_id'], $input['watched_seconds'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$user_id = (int)$input['user_id'];
$lecture_id = (int)$input['lecture_id'];
$watched_seconds = (float)$input['watched_seconds'];
$lecture_title = $input['lecture_title'] ?? '';

try {
    // Verificar se já existe registro
    $stmt = $pdo->prepare("
        SELECT id, last_watched_seconds 
        FROM access_logs 
        WHERE user_id = ? AND resource = ? AND action = 'view_lecture'
    ");
    $stmt->execute([$user_id, $lecture_title]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Atualizar apenas se o novo tempo for maior
        if ($watched_seconds > $existing['last_watched_seconds']) {
            $stmt = $pdo->prepare("
                UPDATE access_logs 
                SET last_watched_seconds = ?, 
                    timestamp = NOW()
                WHERE user_id = ? AND resource = ? AND action = 'view_lecture'
            ");
            $stmt->execute([$watched_seconds, $user_id, $lecture_title]);
        }
    } else {
        // Criar novo registro
        $stmt = $pdo->prepare("
            INSERT INTO access_logs 
            (user_id, resource, action, last_watched_seconds, timestamp) 
            VALUES (?, ?, 'view_lecture', ?, NOW())
        ");
        $stmt->execute([$user_id, $lecture_title, $watched_seconds]);
    }

    echo json_encode([
        'success' => true, 
        'watched_seconds' => $watched_seconds,
        'message' => 'Progress saved successfully'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>