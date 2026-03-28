<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Função para gerar UUID
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Verificar se está logado
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuario nao logado']);
    exit;
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo nao permitido']);
    exit;
}

// Obter dados JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados invalidos']);
    exit;
}

$user_id = $_SESSION['user_id'];
$lecture_id = $data['lecture_id'] ?? '';
$action = $data['action'] ?? '';

if (empty($lecture_id) || empty($action)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parametros obrigatorios em falta']);
    exit;
}

try {
    // Verificar se a palestra existe
    $stmt = $pdo->prepare('SELECT id FROM lectures WHERE id = ?');
    $stmt->execute([$lecture_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Palestra nao encontrada']);
        exit;
    }

    if ($action === 'add') {
        // Verificar se já existe na lista
        $stmt = $pdo->prepare('SELECT id FROM user_watchlist WHERE user_id = ? AND lecture_id = ?');
        $stmt->execute([$user_id, $lecture_id]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Palestra ja esta na lista']);
            exit;
        }

        // Adicionar à lista
        $watchlist_id = generateUUID();
        $stmt = $pdo->prepare('INSERT INTO user_watchlist (id, user_id, lecture_id, added_at) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$watchlist_id, $user_id, $lecture_id]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Palestra adicionada a sua lista',
            'action' => 'added'
        ]);

    } elseif ($action === 'remove') {
        // Remover da lista
        $stmt = $pdo->prepare('DELETE FROM user_watchlist WHERE user_id = ? AND lecture_id = ?');
        $stmt->execute([$user_id, $lecture_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'Palestra removida da sua lista',
                'action' => 'removed'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Palestra nao estava na lista']);
        }

    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Acao invalida']);
    }

} catch (Exception $e) {
    error_log('Erro na API watchlist: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
?>