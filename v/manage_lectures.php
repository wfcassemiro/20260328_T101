<?php
session_start();

// Verificar se é admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit();
}

require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

// Função para gerar UUID v4
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'POST':
            // Adicionar ou atualizar palestra
            $lectureId = $_POST['lectureId'] ?? null;
            $speaker = $_POST['lectureSpeaker'] ?? '';
            $title = $_POST['lectureTitle'] ?? '';
            $lectureDate = $_POST['lectureDate'] ?? '';
            $lectureTime = $_POST['lectureTime'] ?? ''; // NOVO CAMPO
            $summary = $_POST['lectureSummary'] ?? '';
            
            // Upload da imagem
            $imagePath = '';
            if (isset($_FILES['lectureImage']) && $_FILES['lectureImage']['error'] === UPLOAD_ERR_OK) {
                // ... (Lógica de upload de imagem mantida) ...
                $uploadDir = __DIR__ . '/images/lectures/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileExtension = strtolower(pathinfo($_FILES['lectureImage']['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                
                if (!in_array($fileExtension, $allowedExtensions)) {
                    throw new Exception('Formato de arquivo não permitido');
                }
                
                $imageInfo = getimagesize($_FILES['lectureImage']['tmp_name']);
                if ($imageInfo === false) {
                    throw new Exception('Arquivo não é uma imagem válida');
                }
                
                $fileName = 'lecture_' . uniqid() . '.' . $fileExtension;
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['lectureImage']['tmp_name'], $targetPath)) {
                    $imagePath = '/images/lectures/' . $fileName;
                }
            }
            
            if (empty($lectureId)) {
                // Inserir nova palestra
                $newId = generateUUID();
                $stmt = $pdo->prepare("
                    INSERT INTO lectures (id, title, speaker, description, duration_minutes, embed_code, image, category, created_at, is_featured) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                ");
                $stmt->execute([
                    $newId, 
                    $title, 
                    $speaker, 
                    $summary, 
                    0, 
                    '', 
                    $imagePath, 
                    'upcoming', 
                    1
                ]);
                $lectureId = $newId;
                
                // Inserir meta dados (incluindo o novo campo lectureTime)
                $stmt2 = $pdo->prepare("
                    INSERT INTO site_settings (setting_name, setting_value, updated_at) 
                    VALUES (?, ?, NOW())
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
                ");
                $lectureMetaKey = 'upcoming_lecture_' . $newId;
                $lectureMetaValue = json_encode([
                    'lecture_date' => $lectureDate,
                    'lecture_time' => $lectureTime, // NOVO CAMPO AQUI
                    'is_upcoming' => true,
                    'display_on_home' => true
                ]);
                $stmt2->execute([$lectureMetaKey, $lectureMetaValue]);
                
            } else {
                // Atualizar palestra existente
                if (!empty($imagePath)) {
                    $stmt = $pdo->prepare("
                        UPDATE lectures 
                        SET title = ?, speaker = ?, description = ?, image = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $speaker, $summary, $imagePath, $lectureId]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE lectures 
                        SET title = ?, speaker = ?, description = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $speaker, $summary, $lectureId]);
                }
                
                // Atualizar meta dados (incluindo o novo campo lectureTime)
                $lectureMetaKey = 'upcoming_lecture_' . $lectureId;
                $lectureMetaValue = json_encode([
                    'lecture_date' => $lectureDate,
                    'lecture_time' => $lectureTime, // NOVO CAMPO AQUI
                    'is_upcoming' => true,
                    'display_on_home' => true
                ]);
                $stmt2 = $pdo->prepare("
                    INSERT INTO site_settings (setting_name, setting_value, updated_at) 
                    VALUES (?, ?, NOW())
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
                ");
                $stmt2->execute([$lectureMetaKey, $lectureMetaValue]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Palestra salva com sucesso!',
                'lectureId' => $lectureId
            ]);
            break;
            
        case 'GET':
            // Buscar palestras futuras
            if (isset($_GET['id'])) {
                $stmt = $pdo->prepare("
                    SELECT l.*, ss.setting_value as lecture_meta 
                    FROM lectures l
                    LEFT JOIN site_settings ss ON ss.setting_name = CONCAT('upcoming_lecture_', l.id)
                    WHERE l.id = ? AND l.category = 'upcoming'
                ");
                $stmt->execute([$_GET['id']]);
                $lecture = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($lecture) {
                    // Decodificar meta dados
                    if ($lecture['lecture_meta']) {
                        $meta = json_decode($lecture['lecture_meta'], true);
                        $lecture['lecture_date'] = $meta['lecture_date'] ?? '';
                        $lecture['lecture_time'] = $meta['lecture_time'] ?? ''; // NOVO CAMPO AQUI
                        $lecture['is_upcoming'] = $meta['is_upcoming'] ?? false;
                    }
                    unset($lecture['lecture_meta']);
                    echo json_encode($lecture);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Palestra não encontrada']);
                }
            } else {
                $stmt = $pdo->query("
                    SELECT l.*, ss.setting_value as lecture_meta 
                    FROM lectures l
                    LEFT JOIN site_settings ss ON ss.setting_name = CONCAT('upcoming_lecture_', l.id)
                    WHERE l.category = 'upcoming' AND l.is_featured = 1
                    ORDER BY l.created_at DESC
                ");
                $lectures = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Processar meta dados para cada palestra
                foreach ($lectures as &$lecture) {
                    if ($lecture['lecture_meta']) {
                        $meta = json_decode($lecture['lecture_meta'], true);
                        $lecture['lecture_date'] = $meta['lecture_date'] ?? '';
                        $lecture['lecture_time'] = $meta['lecture_time'] ?? ''; // NOVO CAMPO AQUI
                        $lecture['is_upcoming'] = $meta['is_upcoming'] ?? false;
                    }
                    unset($lecture['lecture_meta']);
                }
                
                echo json_encode($lectures);
            }
            break;
            
        case 'DELETE':
            // ... (Lógica de deleção mantida) ...
            if (isset($_GET['id'])) {
                $stmt = $pdo->prepare("SELECT image FROM lectures WHERE id = ? AND category = 'upcoming'");
                $stmt->execute([$_GET['id']]);
                $lecture = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($lecture && !empty($lecture['image'])) {
                    $imagePath = __DIR__ . $lecture['image'];
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }
                
                $stmt = $pdo->prepare("DELETE FROM lectures WHERE id = ? AND category = 'upcoming'");
                $stmt->execute([$_GET['id']]);
                
                $stmt2 = $pdo->prepare("DELETE FROM site_settings WHERE setting_name = ?");
                $stmt2->execute(['upcoming_lecture_' . $_GET['id']]);
                
                echo json_encode(['success' => true, 'message' => 'Palestra deletada com sucesso!']);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'ID da palestra não fornecido']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>