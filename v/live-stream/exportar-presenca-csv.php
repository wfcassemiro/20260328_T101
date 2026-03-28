<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../config/database.php';

// Verificar se é admin
if (!isLoggedIn() || !isAdmin()) {
    die('Acesso negado');
}

$date = $_GET['date'] ?? date('Y-m-d');

// Buscar informações da palestra do dia (SEM duration_minutes)
$lectureSql = "SELECT title, speaker FROM upcoming_announcements 
               WHERE announcement_date = ? AND is_active = 1 
               ORDER BY lecture_time ASC LIMIT 1";
$lectureStmt = $pdo->prepare($lectureSql);
$lectureStmt->execute([$date]);
$lecture = $lectureStmt->fetch(PDO::FETCH_ASSOC);

// Buscar participantes
$sql = "SELECT user_name, user_email, total_minutes 
        FROM live_presence 
        WHERE live_date = ? 
        ORDER BY user_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$date]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Nome do arquivo baseado no título da palestra ou data
if ($lecture) {
    // Limpar o título para usar como nome de arquivo
    $cleanTitle = preg_replace('/[^A-Za-z0-9_\-]/', '_', $lecture['title']);
    $cleanTitle = substr($cleanTitle, 0, 50); // Limitar tamanho
    $filename = 'presenca_' . $cleanTitle . '_' . $date . '.csv';
} else {
    $filename = 'presenca_' . $date . '.csv';
}

// Headers para download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Abrir saída
$output = fopen('php://output', 'w');

// UTF-8 BOM para Excel reconhecer acentuação
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Cabeçalho com metadados da palestra (comentário)
if ($lecture) {
    fputcsv($output, ['# Palestra:', $lecture['title']], ';');
    fputcsv($output, ['# Palestrante:', $lecture['speaker']], ';');
    fputcsv($output, ['# Data:', date('d/m/Y', strtotime($date))], ';');
    fputcsv($output, [''], ';'); // Linha em branco
}

// Cabeçalho do CSV
fputcsv($output, ['Nome', 'Email', 'Tempo Online (minutos)'], ';');

// Dados dos participantes
foreach ($participants as $p) {
    fputcsv($output, [
        $p['user_name'],
        $p['user_email'],
        $p['total_minutes']
    ], ';');
}

fclose($output);
exit;
?>