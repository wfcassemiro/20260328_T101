<?php
/**
 * DOWNLOAD CERTIFICATE - VERSÃO SEM DEPENDÊNCIA DO BANCO
 * Funciona apenas com arquivos físicos existentes
 */

session_start();
require_once 'config/database.php';

// Função auxiliar para escrever no arquivo de log customizado.
function writeToCustomLog($message) {
    // O log ficará na mesma pasta deste arquivo
    $log_file = __DIR__ . '/certificate_errors.log'; 
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] [DOWNLOAD] $message\n", FILE_APPEND);
}

writeToCustomLog("DEBUG: Script download_certificate_files.php iniciado.");

// Verificar login
if (!isLoggedIn()) {
    writeToCustomLog("INFO: Usuário não logado. Redirecionando para login.php.");
    header('Location: login.php');
    exit;
}

$certificate_id = $_GET['id'] ?? '';

if (empty($certificate_id)) {
    writeToCustomLog("ERRO: ID do certificado não fornecido.");
    header('Location: videoteca.php?error=invalid_certificate');
    exit;
}
writeToCustomLog("DEBUG: ID do certificado recebido: " . $certificate_id);

try {
    // Primeira tentativa: buscar no banco para o nome
    $user_name = 'Usuario';
    $user_id_from_db = null; // Para verificar permissão

    try {
        $stmt = $pdo->prepare("
            SELECT c.id, c.file_path, c.user_id, u.name as user_name
            FROM certificates c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$certificate_id]);
        $certificate_data = $stmt->fetch(); // Renomeado para evitar conflito com $certificate_id
        
        if ($certificate_data) {
            $user_name = $certificate_data['user_name'] ?: 'Usuario';
            $user_id_from_db = $certificate_data['user_id'];
            writeToCustomLog("DEBUG: Dados do certificado encontrados no banco de dados.");

            // Verificar permissão
            if ($user_id_from_db !== $_SESSION['user_id'] && !isAdmin()) {
                writeToCustomLog("ALERTA: Acesso negado para o usuário " . $_SESSION['user_id'] . " ao certificado " . $certificate_id);
                header('Location: videoteca.php?error=access_denied');
                exit;
            }
        } else {
            writeToCustomLog("INFO: Certificado não encontrado no banco de dados, tentando apenas arquivo físico.");
        }
    } catch (Exception $e) {
        writeToCustomLog("ERRO: Erro ao buscar certificado no banco de dados: " . $e->getMessage());
        // Ignorar erro do banco e continuar com arquivos físicos
    }
    
    // Buscar arquivo físico
    $possible_files = [
        __DIR__ . '/certificates/certificate_' . $certificate_id . '.png',
        __DIR__ . '/certificates/Certificate_' . $certificate_id . '.png', // Caso específico
        __DIR__ . '/certificates/certificate_' . $certificate_id . '.pdf',
        __DIR__ . '/certificates/Certificate_' . $certificate_id . '.pdf'
    ];
    
    // Se tem dados do banco, incluir o file_path também na busca
    if ($certificate_data && $certificate_data['file_path']) {
        array_unshift($possible_files, __DIR__ . '/certificates/' . $certificate_data['file_path']);
    }

    $file_path = null;
    writeToCustomLog("DEBUG: Procurando arquivo físico para o certificado...");
    foreach ($possible_files as $test_path) {
        if ($test_path && file_exists($test_path) && filesize($test_path) > 0) {
            $file_path = $test_path;
            writeToCustomLog("DEBUG: Arquivo físico encontrado em: " . $file_path);
            break;
        }
    }
    
    if (!$file_path) {
        writeToCustomLog("ERRO: Arquivo físico do certificado " . $certificate_id . " não encontrado.");
        header('Location: videoteca.php?error=certificate_not_found');
        exit;
    }
    
    // Determinar tipo de arquivo
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $content_type = ($extension === 'png') ? 'image/png' : 'application/pdf';
    $filename = 'Certificado_' . preg_replace('/[^a-zA-Z0-9]/', '_', $user_name) . '_' . date('Y-m-d') . '.' . $extension;
    writeToCustomLog("DEBUG: Preparando para download: " . $filename . " (Tipo: " . $content_type . ")");

    // Limpar qualquer output anterior
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Headers para download
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: private, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Verificar se arquivo tem conteúdo
    if (filesize($file_path) == 0) {
        writeToCustomLog("ERRO: Arquivo de certificado está vazio: " . $file_path);
        die('Erro: Arquivo de certificado está vazio');
    }
    
    // Enviar arquivo
    readfile($file_path);
    writeToCustomLog("INFO: Arquivo " . $filename . " enviado para download.");
    exit;
    
} catch (Exception $e) {
    writeToCustomLog("ERRO FATAL: Exceção na geração/download do certificado: " . $e->getMessage());
    header('Location: videoteca.php?error=certificate_error');
    exit;
}
?>
