<?php
session_start();
require_once 'config/database.php';

// Redireciona se não estiver logado
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Recebe o ID do arquivo
$file_id = $_GET['id'] ?? null;

if (empty($file_id)) {
    header('Location: /glossarios.php');
    exit;
}

try {
    // Busca o arquivo no banco de dados pelo ID
    $stmt = $pdo->prepare("SELECT * FROM glossary_files WHERE id = ? AND is_active = 1");
    $stmt->execute([$file_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        die('Arquivo não encontrado.');
    }

    // CAMINHO CORRIGIDO: arquivo está em pasta parallel a public_html
    $server_file_path = __DIR__ . '/..' . $file['download_url'];

    // Verifica se o arquivo existe fisicamente
    if (!file_exists($server_file_path)) {
        // Log do erro para debug
        error_log("Arquivo não encontrado: " . $server_file_path);
        die('Arquivo não encontrado no servidor. Contate o administrador.');
    }

    // Registra o download (opcional - para estatísticas)
    try {
        $stmt = $pdo->prepare("UPDATE glossary_files SET download_count = download_count + 1 WHERE id = ?");
        $stmt->execute([$file_id]);
    } catch (Exception $e) {
        // Não interrompe o download se falhar ao atualizar contador
        error_log("Erro ao atualizar contador de downloads: " . $e->getMessage());
    }

    // Limpa qualquer saída anterior ANTES dos headers
    if (ob_get_length()) {
        ob_end_clean();
    }

    // Define o nome do arquivo com codificação segura para acentos
    $safe_filename = $file['title'] . '.' . strtolower($file['file_type']);

    // Headers para download do arquivo
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Transfer-Encoding: binary');
    header('Content-Disposition: attachment; filename="' . rawurlencode($safe_filename) . '"; filename*=UTF-8\'\'' . rawurlencode($safe_filename));
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($server_file_path));

    // Envia o arquivo
    readfile($server_file_path);
    exit;

} catch (Exception $e) {
    error_log("Erro no download: " . $e->getMessage());
    die('Erro ao processar a solicitação de download: ' . $e->getMessage());
}
?>