<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Verificação de autenticação
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Receber dados JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validar dados
if (!isset($data['id']) || empty($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
    exit;
}

$mapping_id = intval($data['id']);

try {
    // Deletar mapeamento
    $stmt = $pdo->prepare("DELETE FROM hotmart_lecture_mapping WHERE id = ?");
    $stmt->execute([$mapping_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Associação deletada com sucesso'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Associação não encontrada'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro no banco de dados: ' . $e->getMessage()
    ]);
}
?>