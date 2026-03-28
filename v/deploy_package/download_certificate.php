<?php
// Linhas de depuração para exibir erros na tela (REMOVER EM PRODUÇÃO!)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/database.php';
require_once 'includes/certificate_generator_helper.php'; // Inclui o helper para possível regeneração

// Função auxiliar para escrever no arquivo de log customizado.
function writeToCustomLog($message) {
    $log_file = __DIR__ . '/certificate_errors.log'; // Log na raiz do projeto
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] [DOWNLOAD_PDF] $message\n", FILE_APPEND);
}

writeToCustomLog("DEBUG: Script download_certificate.php (para PDF) iniciado.");

// ----------------------------------------------------------------------
// Configuração da Biblioteca TCPDF
// ----------------------------------------------------------------------
// Certifique-se de que este caminho está correto para o seu autoload do Composer.
// Se você instalou o Composer na raiz do seu projeto, esta linha está correta.
require_once __DIR__ . '/vendor/autoload.php';

// Verificação rápida para ter certeza que a classe TCPDF existe
if (!class_exists('TCPDF')) {
    writeToCustomLog("ERRO FATAL: Classe TCPDF não encontrada. Verifique a instalação do Composer e o caminho do autoload.");
    die("Erro interno: Biblioteca PDF não carregada. Contate o suporte.");
}
writeToCustomLog("DEBUG: Biblioteca TCPDF carregada com sucesso.");
// ----------------------------------------------------------------------

// Verificar login
if (!isLoggedIn()) {
    writeToCustomLog("INFO: Usuário não logado. Redirecionando para login.php.");
    header('Location: login.php');
    exit;
}

$certificate_id = $_GET['id'] ?? '';

if (empty($certificate_id)) {
    writeToCustomLog("ERRO: ID do certificado não fornecido para download.");
    header('Location: videoteca.php?error=invalid_certificate');
    exit;
}
writeToCustomLog("DEBUG: ID do certificado recebido para download: " . $certificate_id);

try {
    // Buscar dados do certificado no banco de dados para o nome do usuário e título da palestra
    $certificate_data = null; 
    $user_name = 'Usuário Padrão';
    $lecture_title = 'Título da Palestra Padrão';
    $speaker_name = 'Palestrante Padrão';
    $duration_minutes = 0; // Para regeneração, se necessário

    $stmt = $pdo->prepare("
        SELECT c.id, c.file_path, c.user_id, u.name as user_name, l.title as lecture_title, l.speaker as speaker_name, l.duration_minutes
        FROM certificates c
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN lectures l ON c.lecture_id = l.id
        WHERE c.id = ?
    ");
    $stmt->execute([$certificate_id]);
    $certificate_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt = null;

    if ($certificate_data) {
        $user_name = $certificate_data['user_name'] ?: 'Usuário Padrão';
        $lecture_title = $certificate_data['lecture_title'] ?: 'Título da Palestra Padrão';
        $speaker_name = $certificate_data['speaker_name'] ?: 'Palestrante Padrão';
        $duration_minutes = $certificate_data['duration_minutes'] ?? 0;

        // Verificar permissão
        if ($certificate_data['user_id'] !== $_SESSION['user_id'] && !isAdmin()) {
            writeToCustomLog("ALERTA: Acesso negado para o usuário " . $_SESSION['user_id'] . " ao certificado " . $certificate_id . " para download.");
            header('Location: videoteca.php?error=access_denied');
            exit;
        }
    } else {
        writeToCustomLog("ERRO: Certificado com ID " . $certificate_id . " não encontrado no banco de dados.");
        header('Location: videoteca.php?error=certificate_not_found');
        exit;
    }

    // Caminho para o arquivo PNG original (que generate_certificate.php deve ter gerado)
    $png_filename = 'certificate_' . $certificate_id . '.png';
    $png_filepath = __DIR__ . '/certificates/' . $png_filename;

    // Verificar se o arquivo PNG existe e é válido. Se não, tentar regenerar.
    if (!file_exists($png_filepath) || !filesize($png_filepath) || !getimagesize($png_filepath)) {
        writeToCustomLog("ALERTA: Arquivo PNG do certificado não encontrado ou inválido para o ID: " . $certificate_id . ". Caminho: " . $png_filepath . ". Tentando regenerar o PNG...");
        
        // Preparar dados para o helper de geração de PNG
        $png_generation_data = [
            'user_name' => $user_name,
            'lecture_title' => $lecture_title,
            'speaker_name' => $speaker_name,
            'duration_minutes' => $duration_minutes
        ];

        // Chama o helper para gerar e salvar o PNG
        $regenerated_png_path = generateAndSaveCertificatePng(
            $certificate_id,
            $png_generation_data,
            "REGEN_PNG_DL", // Log prefix for regeneration in download script
            'writeToCustomLog' // Logger function
        );

        if ($regenerated_png_path === false) {
            writeToCustomLog("ERRO: Falha ao regenerar PNG para download para o ID: " . $certificate_id);
            header('Location: videoteca.php?error=certificate_png_regeneration_failed');
            exit;
        }
        $png_filepath = $regenerated_png_path; // Atualiza o caminho para o recém-gerado
        writeToCustomLog("INFO: PNG regenerado com sucesso para download.");
    }
    writeToCustomLog("INFO: Arquivo PNG encontrado: " . $png_filepath . ". Iniciando conversão para PDF.");

    // ----------------------------------------------------------------------
    // Geração do PDF usando TCPDF
    // ----------------------------------------------------------------------
    // Determinar dimensões da imagem PNG para configurar o PDF
    list($img_width_px, $img_height_px) = getimagesize($png_filepath);

    // Formato da página (A4) e orientação (Landscape se a imagem for mais larga que alta)
    $page_format = 'A4';
    $page_orientation = ($img_width_px > $img_height_px) ? 'L' : 'P'; // L para paisagem, P para retrato

    // Cria um novo documento PDF
    // Usamos PDF_UNIT 'mm' (milímetros) para A4.
    $pdf = new TCPDF($page_orientation, 'mm', $page_format, true, 'UTF-8', false);

    // Remover cabeçalhos e rodapés padrão
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Definir margens para 0 para que a imagem ocupe a página inteira
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false, 0); // Desativar quebra de página automática e margem inferior

    // Adicionar uma página
    $pdf->AddPage();

    // Adicionar a imagem PNG ao PDF
    // Para A4 L: width = 297mm, height = 210mm
    // Para A4 P: width = 210mm, height = 297mm
    // Essas são as dimensões padrão da A4 em mm.
    $a4_width_mm = ($page_orientation == 'L') ? 297 : 210;
    $a4_height_mm = ($page_orientation == 'L') ? 210 : 297;
    
    // Image(file, x, y, w, h, type, link, align, resize, dpi, palign, ismask, imgmask, border, fitbox, disp, pdata)
    $pdf->Image($png_filepath, 0, 0, $a4_width_mm, $a4_height_mm, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);

    // Nome do arquivo PDF para download
    $pdf_filename = 'Certificado_' . preg_replace('/[^a-zA-Z0-9_]/', '', $user_name) . '.pdf';
    
    // Limpar qualquer output anterior para evitar corrupção do PDF
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Forçar download do arquivo
    $pdf->Output($pdf_filename, 'D'); // 'D' = Download, 'I' = Inline (abre no navegador)

    writeToCustomLog("INFO: Certificado PDF gerado e enviado para download: " . $pdf_filename);
    exit;

} catch (Exception $e) {
    writeToCustomLog("ERRO FATAL: Exceção no processo de download de certificado (PDF): " . $e->getMessage());
    die("Erro fatal ao gerar o certificado PDF: " . $e->getMessage());
}
?>