<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Incluir configuração do banco
require_once '../database.php';

if (!isset($_GET['user_id'], $_GET['lecture_title'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing user_id or lecture_title']);
    exit;
}

$user_id = (int)$_GET['user_id'];
$lecture_title = $_GET['lecture_title'];

try {
    $stmt = $pdo->prepare("
        SELECT last_watched_seconds, timestamp
        FROM access_logs 
        WHERE user_id = ? AND resource = ? AND action = 'view_lecture'
        ORDER BY timestamp DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id, $lecture_title]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode([
            'success' => true,
            'watched_seconds' => (float)$result['last_watched_seconds'],
            'last_update' => $result['timestamp']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'watched_seconds' => 0,
            'last_update' => null
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>