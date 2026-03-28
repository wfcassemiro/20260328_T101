<?php
session_start();
require_once 'config/database.php';

// Verificar se usuário está logado
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$current_user_id = $_SESSION['user_id'];
$certificate_id = $_GET['id'] ?? null;

if (!$certificate_id) {
    header('Location: videoteca.php');
    exit;
}

try {
    // Verificar se o certificado pertence ao usuário atual ou se é admin
    $stmt = $pdo->prepare("
        SELECT c.id, c.file_path, c.user_id, u.name as user_name, l.title as lecture_title
        FROM certificates c
        JOIN users u ON c.user_id = u.id
        JOIN lectures l ON c.lecture_id = l.id
        WHERE c.id = ?
    ");
    $stmt->execute([$certificate_id]);
    $certificate = $stmt->fetch();
    
    if (!$certificate) {
        header('Location: videoteca.php');
        exit;
    }
    
    // Verificar permissão: só o dono do certificado ou admin pode baixar
    if ($certificate['user_id'] !== $current_user_id && !isAdmin()) {
        header('Location: videoteca.php');
        exit;
    }
    
    // Caminho do arquivo
    $file_path = __DIR__ . '/certificates/' . $certificate['file_path'];
    
    if (!file_exists($file_path)) {
        // Arquivo não existe, talvez precise regenerar
        echo "<!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Erro - Translators101</title>
            <script src='https://cdn.tailwindcss.com'></script>
            <style>body { background: #0f0f0f; color: #ffffff; }</style>
        </head>
        <body class='bg-gray-900 text-white min-h-screen flex items-center justify-center'>
            <div class='text-center'>
                <h1 class='text-2xl font-bold text-red-400 mb-4'>Arquivo não encontrado</h1>
                <p class='text-gray-400 mb-6'>O certificado precisa ser regenerado.</p>
                <a href='videoteca.php' class='bg-purple-600 hover:bg-purple-700 px-6 py-3 rounded-lg font-semibold transition-colors inline-block'>
                    Voltar para Videoteca
                </a>
            </div>
        </body>
        </html>";
        exit;
    }
    
    // Detectar tipo de arquivo
    $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    
    if ($file_extension === 'png') {
        $content_type = 'image/png';
        $file_prefix = 'Certificado_PNG_';
    } else {
        $content_type = 'application/pdf';
        $file_prefix = 'Certificado_';
    }
    
    // Servir o arquivo
    $filename = $file_prefix . preg_replace('/[^a-zA-Z0-9]/', '_', $certificate['user_name']) . '_' . date('Y-m-d') . '.' . $file_extension;
    
    // Headers para download
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: private');
    header('Pragma: private');
    header('Expires: 0');
    
    // Limpar qualquer output anterior
    ob_clean();
    flush();
    
    // Enviar arquivo
    readfile($file_path);
    exit;
    
} catch (Exception $e) {
    header('Location: videoteca.php?error=certificate_error');
    exit;
}
?>