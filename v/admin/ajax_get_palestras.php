<?php
session_start();

// Incluir conexão PDO
require_once __DIR__ . '/../config/database.php';

// Segurança: Verificar se o usuário está logado e é admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403); // Proibido
    echo json_encode(['error' => 'Acesso não autorizado']);
    exit;
}

// Garantir que a resposta seja JSON
header('Content-Type: application/json');

// Obter o nome do palestrante enviado via POST
$speaker_name = filter_input(INPUT_POST, 'speaker_name', FILTER_SANITIZE_STRING);

if (empty($speaker_name)) {
    echo json_encode([]); // Retorna um array vazio se nenhum palestrante foi enviado
    exit;
}

try {
    // Buscar palestras APENAS do palestrante selecionado
    $stmt = $pdo->prepare("
        SELECT id, title, created_at 
        FROM lectures 
        WHERE speaker = :speaker_name 
        ORDER BY created_at DESC
    ");
    $stmt->execute([':speaker_name' => $speaker_name]);
    $palestras = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Retornar os dados como JSON
    echo json_encode($palestras);

} catch (PDOException $e) {
    // Em caso de erro no banco, retornar um erro 500
    http_response_code(500);
    echo json_encode(['error' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
exit;
?>