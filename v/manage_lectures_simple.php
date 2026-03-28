<?php
session_start();

// Verificar se é admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit();
}

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'POST':
            $lectureId = $_POST['lectureId'] ?? null;
            $speaker = $_POST['lectureSpeaker'] ?? '';
            $title = $_POST['lectureTitle'] ?? '';
            $summary = $_POST['lectureSummary'] ?? '';
            
            // Upload da imagem (simplificado)
            $imagePath = '';
            if (isset($_FILES['lectureImage']) && $_FILES['lectureImage']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../images/lectures/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileExtension = strtolower(pathinfo($_FILES['lectureImage']['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                
                if (in_array($fileExtension, $allowedExtensions)) {
                    $fileName = 'lecture_' . uniqid() . '.' . $fileExtension;
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['lectureImage']['tmp_name'], $targetPath)) {
                        $imagePath = '/images/lectures/' . $fileName;
                    }
                }
            }
            
            if (empty($lectureId) || strpos($lectureId, 'default-') === 0) {
                // Inserir nova palestra
                $newId = bin2hex(random_bytes(16));
                $stmt = $pdo->prepare("
                    INSERT INTO lectures (id, title, speaker, description, duration_minutes, embed_code, category, is_featured, image) 
                    VALUES (?, ?, ?, ?, 0, '', 'upcoming', 1, ?)
                ");
                $stmt->execute([$newId, $title, $speaker, $summary, $imagePath]);
                $lectureId = $newId;
            } else {
                // Atualizar palestra existente
                if (!empty($imagePath)) {
                    $stmt = $pdo->prepare("
                        UPDATE lectures 
                        SET title = ?, speaker = ?, description = ?, image = ? 
                        WHERE id = ? AND category = 'upcoming'
                    ");
                    $stmt->execute([$title, $speaker, $summary, $imagePath, $lectureId]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE lectures 
                        SET title = ?, speaker = ?, description = ? 
                        WHERE id = ? AND category = 'upcoming'
                    ");
                    $stmt->execute([$title, $speaker, $summary, $lectureId]);
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'Palestra salva com sucesso!']);
            break;
            
        case 'GET':
            if (isset($_GET['id'])) {
                $stmt = $pdo->prepare("SELECT * FROM lectures WHERE id = ? AND category = 'upcoming'");
                $stmt->execute([$_GET['id']]);
                $lecture = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($lecture) {
                    echo json_encode($lecture);
                } else {
                    echo json_encode(['error' => 'Palestra não encontrada']);
                }
            } else {
                $stmt = $pdo->query("SELECT * FROM lectures WHERE category = 'upcoming' ORDER BY created_at DESC");
                $lectures = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($lectures);
            }
            break;
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro: ' . $e->getMessage()]);
}
?>