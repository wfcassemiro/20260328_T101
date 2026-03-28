<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

// Log para debug
error_log('add_to_videoteca.php chamado');
error_log('POST data: ' . print_r($_POST, true));

// Verifica autenticação e permissão de admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    error_log('Acesso negado: usuário não é admin');
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    // Recebe dados do formulário
    $palestra_id = $_POST['palestra_id'] ?? '';
    $title = $_POST['title'] ?? '';
    $speaker = $_POST['speaker'] ?? '';
    $description = $_POST['description'] ?? '';
    $image_path = $_POST['image_path'] ?? '';
    $embed_code = $_POST['embed_code'] ?? '';
    $duration_minutes = intval($_POST['duration_minutes'] ?? 0);
    $category = $_POST['category'] ?? '';
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    error_log("Dados recebidos: palestra_id=$palestra_id, title=$title, duration=$duration_minutes, category=$category");
    
    // Validações
    if (empty($palestra_id) || empty($title) || empty($speaker) || empty($embed_code) || empty($category)) {
        throw new Exception('Todos os campos obrigatórios devem ser preenchidos');
    }
    
    if ($duration_minutes <= 0) {
        throw new Exception('Duração deve ser maior que zero');
    }
    
    // Gera UUID para o ID
    $uuid = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    
    error_log("UUID gerado: $uuid");
    
    // Prepara thumbnail_url (usa image_path se disponível)
    $thumbnail_url = !empty($image_path) ? $image_path : null;
    
    // Insere na tabela lectures
    $sql = "INSERT INTO lectures (
        id, title, speaker, description, duration_minutes, 
        embed_code, thumbnail_url, category, is_featured, 
        created_at, updated_at
    ) VALUES (
        :id, :title, :speaker, :description, :duration_minutes,
        :embed_code, :thumbnail_url, :category, :is_featured,
        NOW(), NOW()
    )";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':id' => $uuid,
        ':title' => $title,
        ':speaker' => $speaker,
        ':description' => $description,
        ':duration_minutes' => $duration_minutes,
        ':embed_code' => $embed_code,
        ':thumbnail_url' => $thumbnail_url,
        ':category' => $category,
        ':is_featured' => $is_featured
    ]);
    
    error_log("Insert na tabela lectures: " . ($result ? 'sucesso' : 'falha'));
    
    // Marca a palestra original como "publicada" (is_active = 2)
    $updateSql = "UPDATE upcoming_announcements SET is_active = 2 WHERE id = :id";
    $updateStmt = $pdo->prepare($updateSql);
    $updateResult = $updateStmt->execute([':id' => $palestra_id]);
    
    error_log("Update na tabela upcoming_announcements: " . ($updateResult ? 'sucesso' : 'falha'));
    
    echo json_encode([
        'success' => true,
        'message' => 'Palestra adicionada à videoteca com sucesso',
        'lecture_id' => $uuid
    ]);
    
} catch (Exception $e) {
    error_log("Erro: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>