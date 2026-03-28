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
    
    // Verificar permissão: só o dono do certificado ou admin pode visualizar
    if ($certificate['user_id'] !== $current_user_id && !isAdmin()) {
        header('Location: videoteca.php');
        exit;
    }
    
    // Caminho do arquivo - tentar múltiplas possibilidades (PNG primeiro)
    $possible_files = [
        __DIR__ . '/certificates/certificate_' . $certificate_id . '.png',
        __DIR__ . '/certificates/' . $certificate['file_path'],
        __DIR__ . '/certificates/certificate_' . $certificate_id . '.pdf'
    ];
    
    $file_path = null;
    foreach ($possible_files as $test_path) {
        if ($test_path && file_exists($test_path)) {
            $file_path = $test_path;
            break;
        }
    }
    
    if (!$file_path) {
        header('Location: videoteca.php?error=certificate_not_found');
        exit;
    }
    
    // Determinar tipo de arquivo
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $content_type = ($extension === 'png') ? 'image/png' : 'application/pdf';
    $filename = 'Certificado_' . preg_replace('/[^a-zA-Z0-9]/', '_', $certificate['user_name']) . '.' . $extension;
    
    // Limpar qualquer output anterior e configurar headers
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Servir o arquivo para visualização (inline)
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: private, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Verificar se arquivo tem conteúdo
    if (filesize($file_path) == 0) {
        die('Erro: Arquivo de certificado está vazio');
    }
    
    // Enviar arquivo
    readfile($file_path);
    exit;
    
} catch (Exception $e) {
    header('Location: videoteca.php?error=certificate_error');
    exit;
}
?>