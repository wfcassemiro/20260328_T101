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
if (!isset($data['hotmart_title']) || !isset($data['lecture_id']) || !isset($data['lecture_title'])) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit;
}

$hotmart_title = trim($data['hotmart_title']);
$lecture_id = trim($data['lecture_id']);
$lecture_title = trim($data['lecture_title']);

if (empty($hotmart_title) || empty($lecture_id) || empty($lecture_title)) {
    echo json_encode(['success' => false, 'message' => 'Dados não podem estar vazios']);
    exit;
}

try {
    // Verificar se já existe mapeamento para esta palestra da Hotmart
    $stmt = $pdo->prepare("SELECT id FROM hotmart_lecture_mapping WHERE hotmart_title = ?");
    $stmt->execute([$hotmart_title]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Já existe uma associação para esta palestra da Hotmart']);
        exit;
    }
    
    // Inserir novo mapeamento
    $stmt = $pdo->prepare(
        "INSERT INTO hotmart_lecture_mapping (hotmart_title, lecture_id, lecture_title) 
         VALUES (?, ?, ?)"
    );
    
    $stmt->execute([$hotmart_title, $lecture_id, $lecture_title]);
    
    $mapping_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Associação criada com sucesso',
        'mapping_id' => $mapping_id
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro no banco de dados: ' . $e->getMessage()
    ]);
}
?>