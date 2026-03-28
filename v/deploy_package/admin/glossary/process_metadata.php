<?php
session_start();
require_once __DIR__ . "/../../config/database.php"; 
require_once __DIR__ . "/../auth_check.php"; 

if (!isAdmin()) {
    header('Location: /login.php');
    exit;
}

$redirect_url = '/admin/glossarios.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['files'])) {
    
    $files = $_POST['files'];
    $titles = $_POST['title'];
    $descriptions = $_POST['description'];
    $categories = $_POST['category'];

    $count_success = 0;
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO glossary_files (id, title, description, category, file_type, download_url, file_size) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($files as $index => $filename) {
            // Recalcula os metadados do arquivo
            $file_path = __DIR__ . '/../../../uploads/glossarios/' . $filename;
            if (file_exists($file_path)) {
                $file_size = filesize($file_path);
                $file_type = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
                $download_url = '/uploads/glossarios/' . $filename;
                
                // Insere no banco de dados
                $stmt->execute([
                    generateUUID(),
                    $titles[$index],
                    $descriptions[$index],
                    $categories[$index],
                    $file_type,
                    $download_url,
                    formatFileSize($file_size)
                ]);
                $count_success++;
            }
        }
        
        $pdo->commit();
        $_SESSION["message"] = "Metadados de $count_success arquivos foram salvos com sucesso!";
        $_SESSION["success"] = true;

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION["message"] = "Erro ao salvar os metadados: " . $e->getMessage();
        $_SESSION["error"] = true;
    }
    
} else {
    $_SESSION["message"] = "Nenhum arquivo para processar.";
    $_SESSION["error"] = true;
}

header("Location: " . $redirect_url);
exit();

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function formatFileSize($bytes) {
    if ($bytes === 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

?>