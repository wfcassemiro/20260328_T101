<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (!function_exists('isLoggedIn') || !isLoggedIn()) {
    die('Acesso negado.');
}

$user_id = $_SESSION['user_id'];
$report_id = $_GET['id'] ?? null;

if (!$report_id) {
    die('Relatório não especificado.');
}

try {
    $stmt = $pdo->prepare("SELECT file_path, report_type FROM dash_generated_reports WHERE id = ? AND user_id = ?");
    $stmt->execute([$report_id, $user_id]);
    $report = $stmt->fetch();

    if ($report && $report['report_type'] === 'PDF' && file_exists($report['file_path'])) {
        $content = file_get_contents($report['file_path']);
        // Adiciona um botão de imprimir/salvar PDF e um script para auto-impressão
        $print_script = '<script>window.onload = function() { window.print(); };</script>';
        $print_button = '<button onclick="window.print()" style="position:fixed; top:10px; right:10px; padding:10px; border-radius:5px; background:#28a745; color:white; border:none; cursor:pointer;">Imprimir / Salvar como PDF</button>';
        
        echo str_replace('</body>', $print_button . $print_script . '</body>', $content);
    } else {
        die('Relatório não encontrado ou inválido.');
    }
} catch (Exception $e) {
    die('Erro ao carregar relatório: ' . $e->getMessage());
}