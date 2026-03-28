<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

$response = ['success' => false, 'watched_seconds' => 0];

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Usuário não autenticado.';
    echo json_encode($response);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$lecture_id = $input['lecture_id'] ?? null;
$user_id = $input['user_id'] ?? null;

if (empty($lecture_id) || empty($user_id) || $user_id !== $_SESSION['user_id']) {
    $response['message'] = 'Dados inválidos.';
    echo json_encode($response);
    exit;
}

try {
    // Buscar título da palestra
    $stmt_lecture = $pdo->prepare("SELECT title FROM lectures WHERE id = ?");
    $stmt_lecture->execute([$lecture_id]);
    $lecture_data = $stmt_lecture->fetch(PDO::FETCH_ASSOC);
    $lecture_title = $lecture_data['title'] ?? null;

    if (!$lecture_title) {
        $response['message'] = 'Palestra não encontrada.';
        echo json_encode($response);
        exit;
    }

    // Buscar progresso atual
    $stmt_check = $pdo->prepare("SELECT last_watched_seconds FROM access_logs WHERE user_id = ? AND resource = ? AND action = 'view_lecture'");
    $stmt_check->execute([$user_id, $lecture_title]);
    $progress_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['watched_seconds'] = $progress_data['last_watched_seconds'] ?? 0;
    $response['lecture_title'] = $lecture_title;

} catch (PDOException $e) {
    $response['message'] = 'Erro no banco de dados.';
}

echo json_encode($response);
?>