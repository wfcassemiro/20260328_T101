<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$response = ['success' => false, 'message' => ''];

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Usuário não autenticado.';
    echo json_encode($response);
    exit;
}

$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

$lecture_id = $input['lecture_id'] ?? null;
$user_id = $input['user_id'] ?? null;
$skip_data = $input['skip_data'] ?? null;

// Validação
if (empty($lecture_id) || empty($user_id) || empty($skip_data) || $user_id !== $_SESSION['user_id']) {
    $response['message'] = 'Dados inválidos.';
    echo json_encode($response);
    exit;
}

try {
    // Log do skip para auditoria
    $log_file = __DIR__ . '/../logs/video_skips.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] User: $user_id, Lecture: $lecture_id, Skip: " . json_encode($skip_data) . "
";
    @file_put_contents($log_file, $log_entry, FILE_APPEND);

    // Atualizar contador de skips no banco
    $stmt = $pdo->prepare("SELECT title FROM lectures WHERE id = ?");
    $stmt->execute([$lecture_id]);
    $lecture = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($lecture) {
        $stmt_update = $pdo->prepare("
            UPDATE access_logs 
            SET skip_count = skip_count + 1,
                last_skip_detected_at = NOW()
            WHERE user_id = ? AND resource = ? AND action = 'view_lecture'
        ");
        $stmt_update->execute([$user_id, $lecture['title']]);
    }

    $response['success'] = true;
    $response['message'] = 'Skip registrado.';

} catch (Exception $e) {
    $response['message'] = 'Erro ao registrar skip: ' . $e->getMessage();
}

echo json_encode($response);
?>