<?php
session_start();
require_once __DIR__ . '/config/database.php';

// Verificar se usuário está logado
if (!isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Usuário não autenticado']);
    exit;
}

// Verificar se o ID da palestra foi fornecido
if (!isset($_GET['lecture_id'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID da palestra não fornecido']);
    exit;
}

$lectureId = $_GET['lecture_id'];

try {
    // Buscar dados da palestra
    $stmt = $pdo->prepare("
        SELECT id, title, speaker, announcement_date, lecture_time, description
        FROM upcoming_announcements 
        WHERE id = ? AND is_active = 1
    ");
    $stmt->execute([$lectureId]);
    $lecture = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lecture) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Palestra não encontrada']);
        exit;
    }
    
    // Preparar dados para o arquivo ICS
    $title = $lecture['title'];
    $speaker = $lecture['speaker'];
    $date = $lecture['announcement_date'];
    $time = $lecture['lecture_time'] ?: '19:00';
    $description = $lecture['description'] ?: '';
    
    // Criar DateTime objects
    $startDateTime = new DateTime($date . ' ' . $time);
    $endDateTime = clone $startDateTime;
    $endDateTime->add(new DateInterval('PT2H')); // Adicionar 2 horas
    
    // Função para formatar data no padrão ICS
    function formatIcsDate($dateTime) {
        return $dateTime->format('Ymd\THis\Z');
    }
    
    // Gerar UID único
    $uid = 'lecture-' . $lectureId . '-' . time() . '@translators101.com';
    
    // Escapar caracteres especiais para ICS
    function escapeIcsText($text) {
        $text = str_replace(['\\', ',', ';', "\n", "\r"], ['\\\\', '\\,', '\\;', '\\n', ''], $text);
        return $text;
    }
    
    // Criar conteúdo do arquivo ICS
    $icsContent = "BEGIN:VCALENDAR\r\n";
    $icsContent .= "VERSION:2.0\r\n";
    $icsContent .= "PRODID:-//Translators101//Live Stream Calendar//PT\r\n";
    $icsContent .= "CALSCALE:GREGORIAN\r\n";
    $icsContent .= "METHOD:PUBLISH\r\n";
    $icsContent .= "BEGIN:VEVENT\r\n";
    $icsContent .= "UID:" . $uid . "\r\n";
    $icsContent .= "DTSTART:" . formatIcsDate($startDateTime) . "\r\n";
    $icsContent .= "DTEND:" . formatIcsDate($endDateTime) . "\r\n";
    $icsContent .= "SUMMARY:" . escapeIcsText($title) . "\r\n";
    $icsContent .= "DESCRIPTION:Palestrante: " . escapeIcsText($speaker);
    if (!empty($description)) {
        $icsContent .= "\\n\\n" . escapeIcsText($description);
    }
    $icsContent .= "\r\n";
    $icsContent .= "LOCATION:Live Stream - Translators101\r\n";
    $icsContent .= "STATUS:CONFIRMED\r\n";
    $icsContent .= "SEQUENCE:0\r\n";
    $icsContent .= "BEGIN:VALARM\r\n";
    $icsContent .= "TRIGGER:-PT30M\r\n";
    $icsContent .= "ACTION:DISPLAY\r\n";
    $icsContent .= "DESCRIPTION:Lembrete: " . escapeIcsText($title) . " começará em 30 minutos\r\n";
    $icsContent .= "END:VALARM\r\n";
    $icsContent .= "END:VEVENT\r\n";
    $icsContent .= "END:VCALENDAR\r\n";
    
    // Gerar nome do arquivo
    $fileName = preg_replace('/[^a-z0-9\s]/i', '', $title);
    $fileName = preg_replace('/\s+/', '_', trim($fileName));
    $fileName = strtolower(substr($fileName, 0, 50)) . '.ics';
    
    // Enviar headers para download
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . strlen($icsContent));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Enviar conteúdo
    echo $icsContent;
    exit;
    
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erro interno do servidor']);
    exit;
}
?>