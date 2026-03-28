<?php
session_start();
require_once __DIR__ . "/../../config/database.php"; 
require_once __DIR__ . "/../auth_check.php"; 

if (!isAdmin()) {
    header('Location: /login.php');
    exit;
}

$redirect_url = '/admin/glossarios.php';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['glossary_id'])) {
    $glossary_id = $_POST['glossary_id'];
    
    try {
        // Buscar informações do arquivo antes de deletar
        $stmt = $pdo->prepare("SELECT * FROM glossary_files WHERE id = ?");
        $stmt->execute([$glossary_id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($file) {
            // Deletar arquivo físico se existir
            $file_path = __DIR__ . '/../../../uploads/glossarios/' . basename($file['download_url']);
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Deletar registro do banco de dados
            $stmt = $pdo->prepare("DELETE FROM glossary_files WHERE id = ?");
            $stmt->execute([$glossary_id]);
            
            $_SESSION["message"] = "Glossário excluído com sucesso!";
            $_SESSION["success"] = true;
        } else {
            $_SESSION["message"] = "Glossário não encontrado.";
            $_SESSION["error"] = true;
        }
        
    } catch (Exception $e) {
        $_SESSION["message"] = "Erro ao excluir glossário: " . $e->getMessage();
        $_SESSION["error"] = true;
    }
    
} else {
    $_SESSION["message"] = "Solicitação inválida.";
    $_SESSION["error"] = true;
}

header("Location: " . $redirect_url);
exit();
?>