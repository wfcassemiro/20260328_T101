<?php
error_reporting(E_ERROR | E_PARSE);
session_start();
// Caminho corrigido para a pasta 'config'
require_once __DIR__ . "/../../config/database.php"; 
// Caminho corrigido para o arquivo auth_check.php na mesma pasta
require_once __DIR__ . "/auth_check.php"; 

// A página de redirecionamento agora é a de gerenciamento de glossários
$redirect_url = '/admin/glossarios.php';

// Tenta incluir config.php
if (!function_exists('generateUUID')) {
    function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}


// Define o diretório de destino
// Os arquivos serão salvos em public_html/uploads/glossarios/
$uploadDir = __DIR__ . '/../../../uploads/glossarios/';

// Cria o diretório se ele não existir
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Define o tamanho máximo do arquivo (em bytes)
// 100 MB = 100 * 1024 * 1024
$maxFileSize = 104857600;

// Processa o upload do arquivo
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["file"])) {
    $file = $_FILES["file"];

    // Validação do arquivo
    if ($file["error"] !== UPLOAD_ERR_OK) {
        $_SESSION["message"] = "Erro no upload do arquivo. Código de erro: " . $file["error"];
        $_SESSION["error"] = true;
        header("Location: " . $redirect_url);
        exit();
    }
    
    if ($file["size"] > $maxFileSize) {
        $_SESSION["message"] = "O arquivo é muito grande. Tamanho máximo permitido: 100 MB.";
        $_SESSION["error"] = true;
        header("Location: " . $redirect_url);
        exit();
    }
    
    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowedExtensions = ["pdf", "docx", "xlsx", "csv"];
    if (!in_array($ext, $allowedExtensions)) {
        $_SESSION["message"] = "Formato de arquivo não suportado. Apenas PDF, DOCX, XLSX e CSV são permitidos.";
        $_SESSION["error"] = true;
        header("Location: " . $redirect_url);
        exit();
    }

    // Gera um nome de arquivo único e seguro
    $filename = uniqid("glossary_") . "." . $ext;
    $filePath = $uploadDir . $filename;
    
    // Move o arquivo temporário para o destino final
    if (move_uploaded_file($file["tmp_name"], $filePath)) {
        
        // Salva os metadados no banco de dados
        try {
            $title = $_POST['title'] ?? pathinfo($file["name"], PATHINFO_FILENAME);
            $description = $_POST['description'] ?? '';
            $category = $_POST['category'] ?? 'Geral';
            $file_type = strtoupper($ext);
            $file_size = formatFileSize($file['size']);
            
            // A URL de download agora é o caminho relativo ao site
            $download_url = '/uploads/glossarios/' . $filename;

            $stmt = $pdo->prepare("INSERT INTO glossary_files (id, title, description, category, file_type, download_url, file_size) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                generateUUID(),
                $title,
                $description,
                $category,
                $file_type,
                $download_url,
                $file_size
            ]);
            
            $_SESSION["message"] = "Glossário enviado e salvo com sucesso!";
            $_SESSION["success"] = true;
            
        } catch (Exception $e) {
            // Se houver um erro no banco de dados, remova o arquivo
            unlink($filePath);
            $_SESSION["message"] = "Erro ao salvar no banco de dados: " . $e->getMessage();
            $_SESSION["error"] = true;
        }

    } else {
        $_SESSION["message"] = "Erro ao mover o arquivo para o diretório de uploads.";
        $_SESSION["error"] = true;
    }
    
    header("Location: " . $redirect_url);
    exit();

} else {
    // Redireciona se a requisição não for POST ou o arquivo não estiver definido
    header("Location: " . $redirect_url);
    exit();
}
?>