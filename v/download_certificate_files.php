<?php
session_start();
require_once 'config/database.php';

// Função auxiliar para logs - Mantida do seu original
function writeToCustomLog($message) {
    $log_file = __DIR__ . '/certificate_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] [DOWNLOAD_FILES] $message\n", FILE_APPEND);
}

writeToCustomLog("DEBUG: Script download_certificate_files.php iniciado.");

$certificate_id = $_GET['id'] ?? '';
$format = $_GET['format'] ?? 'png'; // 'png' ou 'pdf'

if (empty($certificate_id)) {
    writeToCustomLog("ERRO: ID do certificado não fornecido.");
    header('Location: perfil.php?error=invalid_certificate');
    exit;
}

try {
    // Buscar dados do certificado
    $stmt = $pdo->prepare("
        SELECT c.*, u.name as user_name_db, u.email as user_email
        FROM certificates c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$certificate_id]);
    $certificate = $stmt->fetch();

    if (!$certificate) {
        writeToCustomLog("ERRO: Certificado $certificate_id não encontrado.");
        header('Location: perfil.php?error=certificate_not_found');
        exit;
    }

    /**
     * MUDANÇA DE ACESSO:
     * Removida a obrigatoriedade de login. 
     * O UUID serve como autorização para o portador do link.
     */
    
    // Definir nome do arquivo
    $display_name = $certificate['user_name'] ? $certificate['user_name'] : $certificate['user_name_db'];
    $safe_name = preg_replace('/[^a-zA-Z0-9_]/', '_', $display_name);
    $filename_base = "Certificado_T101_" . $safe_name;

    // Caminho da imagem PNG
    $png_path = __DIR__ . '/certificates/certificate_' . $certificate_id . '.png';

    if (!file_exists($png_path)) {
        writeToCustomLog("ERRO: Arquivo físico não encontrado: $png_path");
        header('Location: perfil.php?error=file_not_found');
        exit;
    }

    // --- DOWNLOAD PNG ---
    if ($format === 'png') {
        writeToCustomLog("INFO: Iniciando download PNG para: $filename_base");
        header('Content-Description: File Transfer');
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="' . $filename_base . '.png"');
        header('Content-Length: ' . filesize($png_path));
        readfile($png_path);
        exit;
    }

    // --- DOWNLOAD PDF ---
    if ($format === 'pdf') {
        writeToCustomLog("INFO: Iniciando geração de PDF para: $filename_base");
        
        // Tentar carregar FPDF (ajuste o caminho conforme sua estrutura)
        $fpdf_path = __DIR__ . '/fpdf/fpdf.php';
        if (file_exists($fpdf_path)) {
            require_once($fpdf_path);
        } else {
            writeToCustomLog("ERRO: Biblioteca FPDF não encontrada em $fpdf_path");
            die("Erro interno: Biblioteca de PDF não encontrada.");
        }

        $pdf = new FPDF('L', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        
        // A4 Paisagem: 297x210mm
        $pdf->Image($png_path, 0, 0, 297, 210);
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename_base . '.pdf"');
        $pdf->Output('D', $filename_base . '.pdf');
        exit;
    }

} catch (Exception $e) {
    writeToCustomLog("ERRO CRÍTICO: " . $e->getMessage());
    header('Location: perfil.php?error=system_error');
    exit;
}