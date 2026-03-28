<?php
// REMOVIDO: Linhas de depuração para exibir erros na tela.

session_start();
require_once 'config/database.php';
require_once 'includes/certificate_generator_helper.php'; // Inclui o helper

// Função auxiliar para escrever no arquivo de log customizado.
function writeToCustomLog($message) {
    $log_file = __DIR__ . '/certificate_errors.log'; 
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] [VIEW] $message\n", FILE_APPEND);
}

writeToCustomLog("DEBUG: Script view_certificate_files.php iniciado.");


// UUID generator function - moved here as it might be missing
// Make sure this matches your actual generateUUID function if it's external
if (!function_exists('generateUUID')) {
    function generateUUID() {
        return sprintf( '%04x%04x%04x%04x%04x%04x%04x%04x',
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }
}


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
    // Buscar dados do certificado no banco de dados
    $certificate_data = null; 
    $user_name = 'Usuário Padrão'; 
    $lecture_title = 'Título da Palestra Padrão';
    $speaker_name = 'Palestrante Padrão';
    $duration_minutes = 0; // Para o helper de geração

    try {
        $stmt = $pdo->prepare("
            SELECT c.id, c.file_path, c.user_id, u.name as user_name, l.title as lecture_title, l.speaker as speaker_name, l.duration_minutes
            FROM certificates c
            LEFT JOIN users u ON c.user_id = u.id
            LEFT JOIN lectures l ON c.lecture_id = l.id
            WHERE c.id = ?
        ");
        $stmt->execute([$certificate_id]);
        $certificate_data = $stmt->fetch();
        
        if ($certificate_data) {
            $user_name = $certificate_data['user_name'] ?: 'Usuário Padrão';
            $lecture_title = $certificate_data['lecture_title'] ?: 'Título da Palestra Padrão';
            $speaker_name = $certificate_data['speaker_name'] ?: 'Palestrante Padrão';
            $duration_minutes = $certificate_data['duration_minutes'] ?? 0;
            
            writeToCustomLog("DEBUG: Dados do certificado encontrados no banco de dados para ID: " . $certificate_id);

            // Verificar permissão
            if ($certificate_data['user_id'] !== $_SESSION['user_id'] && !isAdmin()) {
                writeToCustomLog("ALERTA: Acesso negado para o usuário " . $_SESSION['user_id'] . " ao certificado " . $certificate_id);
                header('Location: videoteca.php?error=access_denied');
                exit;
            }
        } else {
            writeToCustomLog("INFO: Certificado ID " . $certificate_id . " não encontrado no banco de dados. Tentando arquivo físico.");
        }
    } catch (Exception $e) {
        writeToCustomLog("ERRO: Erro ao buscar certificado no banco de dados: " . $e->getMessage());
    }
    
    // Tentar buscar arquivo físico existente
    $possible_files = [
        __DIR__ . '/certificates/certificate_' . $certificate_id . '.png',
        __DIR__ . '/certificates/Certificate_' . $certificate_id . '.png', 
        __DIR__ . '/certificates/certificate_' . $certificate_id . '.pdf', 
        __DIR__ . '/certificates/Certificate_' . $certificate_id . '.pdf'
    ];
    
    // Se o banco retornou um caminho, inclua-o na lista de busca prioritária
    if ($certificate_data && $certificate_data['file_path']) {
        array_unshift($possible_files, __DIR__ . '/certificates/' . $certificate_data['file_path']);
    }
    
    $file_path = null;
    writeToCustomLog("DEBUG: Procurando arquivo físico do certificado...");
    foreach ($possible_files as $test_path) {
        if ($test_path && file_exists($test_path) && filesize($test_path) > 0) {
            $file_path = $test_path;
            writeToCustomLog("DEBUG: Arquivo físico encontrado em: " . $file_path);
            break;
        }
    }
    
    // Se o arquivo PNG não foi encontrado, tenta gerar o PNG via helper
    if (!$file_path || strtolower(pathinfo($file_path, PATHINFO_EXTENSION)) !== 'png') {
        if (!$file_path) {
             writeToCustomLog("INFO: Arquivo físico não encontrado. Tentando gerar o certificado PNG via helper.");
        } else {
             writeToCustomLog("INFO: Arquivo encontrado mas não é PNG ou não é o esperado. Tentando gerar o certificado PNG via helper.");
        }

        $png_generation_data = [
            'user_name' => $user_name,
            'lecture_title' => $lecture_title,
            'speaker_name' => $speaker_name,
            'duration_minutes' => $duration_minutes
        ];

        $generated_png_path = generateAndSaveCertificatePng(
            $certificate_id,
            $png_generation_data,
            "VIEW_GEN_PNG", // Log prefix for regeneration in view script
            'writeToCustomLog' // Logger function
        );

        if ($generated_png_path === false) {
            writeToCustomLog("ERRO: Falha ao gerar PNG do certificado via helper para visualização.");
            header('Location: videoteca.php?error=certificate_png_generation_failed');
            exit;
        }
        $file_path = $generated_png_path; // Usa o caminho do PNG recém-gerado
    }
    
    // Determinar tipo de arquivo para o cabeçalho (agora sempre será PNG, a menos que haja um PDF existente)
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $content_type = 'application/octet-stream'; // Default seguro
    
    if ($extension === 'png') {
        $content_type = 'image/png';
    } elseif ($extension === 'pdf') {
        $content_type = 'application/pdf';
    }
    
    $filename_display = 'Certificado_' . preg_replace('/[^a-zA-Z0-9_]/', '', $user_name) . '.' . $extension;
    writeToCustomLog("DEBUG: Preparando para visualização: " . $filename_display . " (Tipo: " . $content_type . ") - Arquivo físico: " . $file_path);

    // Limpar qualquer output anterior
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Headers para visualização
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: inline; filename="' . $filename_display . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: private, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Verificar se arquivo tem conteúdo
    if (filesize($file_path) == 0) {
        writeToCustomLog("ERRO: Arquivo de certificado está vazio antes de enviar: " . $file_path);
        die('Erro: Arquivo de certificado está vazio');
    }
    
    // Enviar arquivo
    readfile($file_path);
    writeToCustomLog("INFO: Arquivo " . $filename_display . " enviado para visualização.");
    exit;
    
} catch (Exception $e) {
    writeToCustomLog("ERRO FATAL: Exceção no processo de visualização do certificado: " . $e->getMessage());
    header('Location: videoteca.php?error=certificate_error');
    exit;
}
?>