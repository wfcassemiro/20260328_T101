<?php
session_start();
header('Content-Type: application/json');

require_once '../../config/database.php';

$response = ['success' => false, 'message' => ''];

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Usuário não autenticado.';
    echo json_encode($response);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$lecture_id = $input['lecture_id'] ?? null;
$user_id = $input['user_id'] ?? null;
$watched_seconds = isset($input['watched_seconds']) ? (int)$input['watched_seconds'] : 0;

if (empty($lecture_id) || empty($user_id) || $user_id !== $_SESSION['user_id']) {
    $response['message'] = 'Dados inválidos.';
    echo json_encode($response);
    exit;
}

try {
    // Buscar título da palestra
    $stmt = $pdo->prepare("SELECT title FROM lectures WHERE id = ?");
    $stmt->execute([$lecture_id]);
    $lecture_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $lecture_title = $lecture_data['title'] ?? null;

    if (!$lecture_title) {
        $response['message'] = 'Palestra não encontrada.';
        echo json_encode($response);
        exit;
    }

    // Verificar se log existe
    $stmt = $pdo->prepare("SELECT id FROM access_logs WHERE user_id = ? AND resource = ? AND action = 'view_lecture'");
    $stmt->execute([$user_id, $lecture_title]);
    $existing_log = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_log) {
        // Atualizar progresso existente
        $stmt = $pdo->prepare("UPDATE access_logs SET last_watched_seconds = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$watched_seconds, $existing_log['id']]);
    } else {
        // Criar novo log
        $stmt = $pdo->prepare("INSERT INTO access_logs (user_id, action, resource, ip_address, user_agent, last_watched_seconds) VALUES (?, 'view_lecture', ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $lecture_title,
            $_SERVER['REMOTE_ADDR'] ?? 'N/A',
            $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
            $watched_seconds
        ]);
    }

    $response['success'] = true;
    $response['message'] = 'Progresso salvo com sucesso.';

} catch (PDOException $e) {
    $response['message'] = 'Erro no banco de dados.';
}

echo json_encode($response);
?>