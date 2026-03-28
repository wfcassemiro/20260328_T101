<?php
error_reporting(E_ERROR | E_PARSE);
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/auth_check.php';

if (!isset($_SESSION['csv_data'])) {
    header('Location: upload.php');
    exit;
}

$csvData = $_SESSION['csv_data'];
$area = $_SESSION['csv_area'] ?? '';
$source_lang = $_SESSION['csv_source_lang'] ?? '';
$target_lang = $_SESSION['csv_target_lang'] ?? '';

$filename = 'glossario_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Escreve cabeçalho personalizado
fputcsv($output, ['Área', 'Idioma de origem', 'Idioma de destino']);
fputcsv($output, [$area, $source_lang, $target_lang]);
fputcsv($output, []); // linha em branco

// Escreve os dados do glossário
foreach ($csvData as $row) {
    if (is_array($row)) {
        fputcsv($output, $row);
    } else {
        fputcsv($output, [$row]);
    }
}

fclose($output);

// Limpa os dados da sessão
unset($_SESSION['csv_data'], $_SESSION['csv_area'], $_SESSION['csv_source_lang'], $_SESSION['csv_target_lang']);
exit;