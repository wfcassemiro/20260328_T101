<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Log de eventos de segurança
$security_events_log = __DIR__ . '/security_events.log';

function writeSecurityEvent($event_data) {
    global $security_events_log;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] " . json_encode($event_data) . "\n";
    @file_put_contents($security_events_log, $log_entry, FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['event'], $input['userId'], $input['lectureId'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

// Registrar evento de segurança
$security_event = [
    'timestamp' => $input['timestamp'] ?? date('c'),
    'event_type' => $input['event'],
    'user_id' => $input['userId'],
    'lecture_id' => $input['lectureId'],
    'data' => $input['data'] ?? [],
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A'
];

writeSecurityEvent($security_event);

// Resposta de sucesso
echo json_encode([
    'success' => true,
    'message' => 'Security event logged',
    'event_id' => uniqid('sec_', true)
]);
?>
