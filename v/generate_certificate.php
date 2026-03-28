<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/certificate_generator_helper.php';

function writeToCustomLog(string $message): void {
    $log_file = __DIR__ . '/certificate_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] [GENERATE_CERT] $message\n", FILE_APPEND);
}

function json_error(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = (string) file_get_contents('php://input');
$input = json_decode($raw, true) ?: $_POST;

$lecture_id = trim((string)($input['lecture_id'] ?? ''));
// Pega o e-mail da sessão para garantir que achamos o usuário real
$session_email = trim((string)($_SESSION['user_email'] ?? ''));

if (!$lecture_id) {
    json_error('ID da palestra não informado.');
}

try {
    // 1. Achar o usuário real pelo e-mail (já que o ID na sessão está vindo como 'debug-user')
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$session_email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        json_error("Usuário não encontrado no banco com o e-mail: $session_email");
    }
    
    $user_id = $user['id']; // Agora temos o ID real (ex: 123 ou UUID)

    // 2. Buscar dados da palestra
    $stmt = $pdo->prepare("SELECT id, title, speaker, duration_minutes FROM lectures WHERE id = ? LIMIT 1");
    $stmt->execute([$lecture_id]);
    $lecture = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lecture) {
        json_error('Palestra não encontrada.');
    }

    // 3. Verificar se já existe certificado
    $stmt = $pdo->prepare("SELECT id FROM certificates WHERE user_id = ? AND lecture_id = ? LIMIT 1");
    $stmt->execute([$user_id, $lecture_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => true, 'already_exists' => true]);
        exit;
    }

    // 4. Gerar UUID e Dados para o Helper
    $cert_uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff),
        random_int(0, 0x0fff) | 0x4000, random_int(0, 0x3fff) | 0x8000,
        random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
    );

    $duration_hours = ceil(($lecture['duration_minutes'] / 60) * 2) / 2;

    $certData = [
        'user_name' => $user['name'],
        'lecture_title' => $lecture['title'],
        'speaker_name' => $lecture['speaker'],
        'duration_minutes' => $lecture['duration_minutes']
    ];

    // 5. Salvar no Banco
    $pdo->beginTransaction();
    $ins = $pdo->prepare("INSERT INTO certificates (id, user_id, lecture_id, user_name, lecture_title, speaker_name, duration_hours, certificate_code, user_email, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $ins->execute([$cert_uuid, $user_id, $lecture_id, $user['name'], $lecture['title'], $lecture['speaker'], $duration_hours, $cert_uuid, $user['email']]);

    // 6. Gerar o PNG (Usando os 4 argumentos que o seu helper exige)
    $png = generateAndSaveCertificatePng($cert_uuid, $certData, 'GENERATE_CERT', 'writeToCustomLog');

    if (!$png) {
        throw new Exception("Falha ao criar arquivo PNG do certificado.");
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'certificate_id' => $cert_uuid]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    writeToCustomLog("ERRO: " . $e->getMessage());
    json_error($e->getMessage(), 500);
}