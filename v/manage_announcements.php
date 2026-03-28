<?php
session_start();

// Verificar se é admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit();
}

require_once 'config/database.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'POST':
            // Adicionar ou atualizar anúncio
            $announcementId = $_POST['lectureId'] ?? null;
            $speaker = $_POST['lectureSpeaker'] ?? '';
            $title = $_POST['lectureTitle'] ?? '';
            $announcementDate = $_POST['lectureDate'] ?? '';
            $description = $_POST['lectureSummary'] ?? '';
            $lectureTime = $_POST['lectureTime'] ?? '';
            
            // Upload da imagem
            $imagePath = '';
            if (isset($_FILES['lectureImage']) && $_FILES['lectureImage']['error'] === UPLOAD_ERR_OK) {
                $imageInfo = getimagesize($_FILES['lectureImage']['tmp_name']);
                if ($imageInfo === false) {
                    throw new Exception('Arquivo não é uma imagem válida');
                }
                
                $fileExtension = strtolower(pathinfo($_FILES['lectureImage']['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                
                if (!in_array($fileExtension, $allowedExtensions)) {
                    throw new Exception('Formato de arquivo não permitido. Use: ' . implode(', ', $allowedExtensions));
                }
                
                $fileName = 'announcement_' . uniqid() . '_' . date('Y-m-d') . '.' . $fileExtension;
                
                $documentRoot = $_SERVER['DOCUMENT_ROOT'];
                $webUploadDir = $documentRoot . '/images/announcements/';
                
                if (!is_dir($webUploadDir)) {
                    mkdir($webUploadDir, 0755, true);
                }
                
                $webTargetPath = $webUploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['lectureImage']['tmp_name'], $webTargetPath)) {
                    $imagePath = '/images/announcements/' . $fileName;
                } else {
                    $localUploadDir = __DIR__ . '/images/announcements/';
                    if (!is_dir($localUploadDir)) {
                        mkdir($localUploadDir, 0755, true);
                    }
                    
                    $localTargetPath = $localUploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['lectureImage']['tmp_name'], $localTargetPath)) {
                        $imagePath = '/images/announcements/' . $fileName;
                        @copy($localTargetPath, $webTargetPath);
                    } else {
                        throw new Exception('Erro ao fazer upload da imagem');
                    }
                }
            }
            
            if (empty($announcementId) || strpos($announcementId, 'default-') === 0) {
                // Inserir novo anúncio
                $newId = bin2hex(random_bytes(16));
                $stmt = $pdo->prepare("
                    INSERT INTO upcoming_announcements (id, title, speaker, announcement_date, lecture_time, description, image_path, display_order) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, (SELECT IFNULL(MAX(display_order), 0) + 1 FROM upcoming_announcements AS ua))
                ");
                $stmt->execute([$newId, $title, $speaker, $announcementDate, $lectureTime, $description, $imagePath]);
                $announcementId = $newId;
            } else {
                // Atualizar anúncio existente
                if (!empty($imagePath)) {
                    $stmt = $pdo->prepare("
                        UPDATE upcoming_announcements 
                        SET title = ?, speaker = ?, announcement_date = ?, lecture_time = ?, description = ?, image_path = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $speaker, $announcementDate, $lectureTime, $description, $imagePath, $announcementId]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE upcoming_announcements 
                        SET title = ?, speaker = ?, announcement_date = ?, lecture_time = ?, description = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $speaker, $announcementDate, $lectureTime, $description, $announcementId]);
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Anúncio de palestra salvo com sucesso!',
                'id' => $announcementId
            ]);
            break;
        
        case 'GET':
            if (isset($_GET['id'])) {
                $stmt = $pdo->prepare("SELECT id, title, speaker, announcement_date, lecture_time, description, image_path FROM upcoming_announcements WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($announcement) {
                    // Converter para formato esperado pelo form
                    $announcement['lecture_date'] = $announcement['announcement_date'];
                    $announcement['lecture_time'] = $announcement['lecture_time'] ?? '19:00';
                    unset($announcement['announcement_date']);
                    echo json_encode($announcement);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Anúncio não encontrado']);
                }
            } else {
                $stmt = $pdo->query("
                    SELECT id, title, speaker, announcement_date, lecture_time, description, image_path
                    FROM upcoming_announcements 
                    WHERE is_active = 1 
                    AND announcement_date >= CURDATE()
                    ORDER BY announcement_date ASC
                    LIMIT 3
                ");
                $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($announcements);
            }
            break;
        
        case 'DELETE':
            if (isset($_GET['id'])) {
                $announcementId = $_GET['id'];
                
                // Buscar caminho da imagem para deletar
                $stmt = $pdo->prepare("SELECT image_path FROM upcoming_announcements WHERE id = ?");
                $stmt->execute([$announcementId]);
                $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Deletar imagem do servidor se existir
                if ($announcement && !empty($announcement['image_path'])) {
                    $documentRoot = $_SERVER['DOCUMENT_ROOT'];
                    $imagePath = $documentRoot . $announcement['image_path'];
                    
                    if (file_exists($imagePath)) {
                        @unlink($imagePath);
                    }
                }
                
                // Deletar anúncio do banco de dados
                $stmt = $pdo->prepare("DELETE FROM upcoming_announcements WHERE id = ?");
                $stmt->execute([$announcementId]);
                
                echo json_encode(['success' => true, 'message' => 'Anúncio deletado com sucesso!']);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'ID do anúncio não fornecido']);
            }
            break;
        
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro: ' . $e->getMessage()]);
}
?>