<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

$lecture_id = $_POST['lecture_id'] ?? null;
$hotmart_module_id = $_POST['hotmart_module_id'] ?? null;
$hotmart_lesson_id = $_POST['hotmart_lesson_id'] ?? null;
$hotmart_page_id = $_POST['hotmart_page_id'] ?? null;
$hotmart_title = $_POST['hotmart_title'] ?? null;

if (!$lecture_id || !$hotmart_page_id || !$hotmart_title) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
    exit;
}

try {
    // Usamos REPLACE INTO para inserir ou atualizar caso já exista um registro para a lecture_id.
    // Isso evita erros de chave duplicada e permite corrigir um mapeamento se necessário.
    $stmt = $pdo->prepare(
        "REPLACE INTO hotmart_lecture_mapping (lecture_id, hotmart_module_id, hotmart_lesson_id, hotmart_page_id, lecture_title) 
         VALUES (?, ?, ?, ?, ?)"
    );

    $stmt->execute([
        $lecture_id,
        $hotmart_module_id,
        empty($hotmart_lesson_id) ? null : $hotmart_lesson_id, // Garante que seja NULL se estiver vazio
        $hotmart_page_id,
        $hotmart_title
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Mapeamento salvo com sucesso.']);
    } else {
        // rowCount() pode retornar 0 se os dados atualizados forem idênticos aos existentes, 
        // o que não é um erro.
        echo json_encode(['success' => true, 'message' => 'Mapeamento salvo (ou já estava atualizado).']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro de banco de dados: ' . $e->getMessage()]);
}
exit;