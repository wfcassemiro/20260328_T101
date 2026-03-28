<?php
session_start();
require_once __DIR__ . '/config/database.php';

// Verificar se está logado
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$report_file = $_GET['file'] ?? '';

if (empty($report_file)) {
    header('Location: perfil.php?error=invalid_report');
    exit;
}

// Validar nome do arquivo (segurança) - suportar PNG e PDF
if (!preg_match('/^relatorio_educacao_continuada_' . preg_quote($user_id, '/') . '_[\d\-_]+\.(png|pdf)$/', $report_file)) {
    header('Location: perfil.php?error=access_denied');
    exit;
}

$reports_dir = __DIR__ . '/reports';
$file_path = $reports_dir . '/' . $report_file;

// Verificar se arquivo existe
if (!file_exists($file_path) || !is_readable($file_path)) {
    header('Location: perfil.php?error=report_not_found');
    exit;
}

// Determinar tipo de arquivo
$extension = strtolower(pathinfo($report_file, PATHINFO_EXTENSION));
$content_type = ($extension === 'png') ? 'image/png' : 'application/pdf';

// Configurar headers para download
$filename = 'Relatorio_Educacao_Continuada_' . preg_replace('/[^a-zA-Z0-9]/', '_', $_SESSION['user_name'] ?? 'Usuario') . '_' . date('Y-m-d') . '.' . $extension;

header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: private, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Limpar buffer de saída
if (ob_get_level()) {
    ob_end_clean();
}

// Enviar arquivo
readfile($file_path);

// Log do download (opcional)
try {
    $log_file = __DIR__ . '/certificate_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] [REPORT_DOWNLOAD] User: $user_id downloaded report: $report_file ($extension)\n";
    @file_put_contents($log_file, $log_message, FILE_APPEND);
} catch (Exception $e) {
    // Ignorar erro de log
}

exit;
?>