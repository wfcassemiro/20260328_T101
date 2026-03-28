<?php
session_start();
require_once __DIR__ . '/config/database.php';

// Função auxiliar para logs
function writeToCustomLog($message) {
    $log_file = __DIR__ . '/certificate_errors.log'; 
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] [GENERATE_SIMPLE] $message\n", FILE_APPEND);
}

writeToCustomLog("Script generate_simple_certificate.php iniciado");

header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isSubscriber()) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autorizado']);
    writeToCustomLog("ERRO: Usuário não autorizado");
    exit;
}

// Obter dados da requisição
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos recebidos']);
    writeToCustomLog("ERRO: Dados inválidos recebidos");
    exit;
}

$lecture_id = $input['lecture_id'] ?? '';
$user_id = $input['user_id'] ?? '';
$watched_seconds = isset($input['watched_seconds']) ? (int)$input['watched_seconds'] : 0;

writeToCustomLog("Dados recebidos - Lecture: $lecture_id, User: $user_id, Watched: {$watched_seconds}s");

// Validação básica
if (empty($lecture_id) || empty($user_id)) {
    echo json_encode(['success' => false, 'message' => 'Dados obrigatórios ausentes']);
    writeToCustomLog("ERRO: Dados obrigatórios ausentes");
    exit;
}

// Verificar se o user_id corresponde à sessão
if ($user_id !== $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'ID de usuário não corresponde à sessão']);
    writeToCustomLog("ERRO: User ID não corresponde - Sessão: " . $_SESSION['user_id'] . ", Enviado: $user_id");
    exit;
}

try {
    // Buscar dados da palestra
    $stmt = $pdo->prepare("SELECT * FROM lectures WHERE id = ?");
    $stmt->execute([$lecture_id]);
    $lecture = $stmt->fetch();
    
    if (!$lecture) {
        echo json_encode(['success' => false, 'message' => 'Palestra não encontrada']);
        writeToCustomLog("ERRO: Palestra não encontrada - ID: $lecture_id");
        exit;
    }
    
    // Buscar dados do usuário
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        writeToCustomLog("ERRO: Usuário não encontrado - ID: $user_id");
        exit;
    }
    
    // VALIDAÇÃO SIMPLIFICADA PARA LIBERAÇÃO DO CERTIFICADO
    writeToCustomLog("Iniciando validações simplificadas");
    
    // 1. Verificar progresso no banco de dados
    $stmt = $pdo->prepare("SELECT last_watched_seconds FROM access_logs WHERE user_id = ? AND resource = ? AND action = 'view_lecture'");
    $stmt->execute([$user_id, $lecture['title']]);
    $progress_data = $stmt->fetch();
    $db_watched_seconds = $progress_data['last_watched_seconds'] ?? 0;
    
    writeToCustomLog("Progresso no DB: {$db_watched_seconds}s, Enviado pelo cliente: {$watched_seconds}s");
    
    // 2. Usar o maior valor entre DB e cliente
    $final_watched_seconds = max($db_watched_seconds, $watched_seconds);
    writeToCustomLog("Tempo final considerado: {$final_watched_seconds}s");
    
    // 3. Validar tempo mínimo (85% da duração da palestra)
    $lecture_duration_seconds = ($lecture['duration_minutes'] ?? 0) * 60;
    $required_seconds = floor($lecture_duration_seconds * 0.85); // 85% da duração total
    
    writeToCustomLog("Duração total: {$lecture_duration_seconds}s, Requerido (85%): {$required_seconds}s");
    
    if ($final_watched_seconds < $required_seconds) {
        $remaining = $required_seconds - $final_watched_seconds;
        echo json_encode([
            'success' => false, 
            'message' => "Tempo insuficiente. Assistido: {$final_watched_seconds}s de {$required_seconds}s necessários. Faltam " . ceil($remaining/60) . " minutos."
        ]);
        writeToCustomLog("ERRO: Tempo insuficiente - Assistido: {$final_watched_seconds}s, Requerido: {$required_seconds}s");
        exit;
    }
    
    // 4. Verificar se já existe certificado
    $stmt = $pdo->prepare("SELECT id FROM certificates WHERE user_id = ? AND lecture_id = ?");
    $stmt->execute([$user_id, $lecture_id]);
    $existing_certificate = $stmt->fetch();
    
    if ($existing_certificate) {
        echo json_encode([
            'success' => true, 
            'message' => 'Certificado já existe',
            'certificate_id' => $existing_certificate['id']
        ]);
        writeToCustomLog("INFO: Certificado já existe - ID: " . $existing_certificate['id']);
        exit;
    }
    
    // GERAR NOVO CERTIFICADO
    writeToCustomLog("Todas as validações passaram - gerando certificado");
    
    // Calcular duração em horas (arredondar para próxima meia hora)
    $duration_hours = $lecture['duration_minutes'] / 60;
    if ($duration_hours <= 0.5) {
        $duration_hours = 0.5;
    } elseif ($duration_hours <= 1.0) {
        $duration_hours = 1.0;
    } elseif ($duration_hours <= 1.5) {
        $duration_hours = 1.5;
    } else {
        $duration_hours = ceil($duration_hours * 2) / 2;
    }
    
    // Gerar UUID para o certificado
    function generateUUID() {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }
    
    $certificate_id = generateUUID();
    
    // Inserir certificado no banco
    $stmt = $pdo->prepare("
        INSERT INTO certificates 
        (id, user_id, lecture_id, user_name, lecture_title, speaker_name, duration_hours) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $certificate_id,
        $user_id,
        $lecture_id,
        $user['name'],
        $lecture['title'],
        $lecture['speaker'],
        $duration_hours
    ]);
    
    writeToCustomLog("SUCESSO: Certificado inserido no banco - ID: $certificate_id");
    
    // GERAR ARQUIVOS FÍSICOS (PNG e PDF)
    $png_generated = false;
    $pdf_generated = false;
    
    // 1. Gerar PNG usando helper existente
    if (file_exists(__DIR__ . '/includes/certificate_generator_helper.php')) {
        require_once __DIR__ . '/includes/certificate_generator_helper.php';
        
        $certificate_data = [
            'user_name' => $user['name'],
            'lecture_title' => $lecture['title'],
            'speaker_name' => $lecture['speaker'],
            'duration_minutes' => $lecture['duration_minutes'],
            'issued_at' => date('Y-m-d H:i:s')
        ];
        
        $png_path = generateAndSaveCertificatePng(
            $certificate_id,
            $certificate_data,
            'GENERATE_SIMPLE',
            function($msg) { writeToCustomLog($msg); }
        );
        
        if ($png_path && file_exists($png_path)) {
            $png_generated = true;
            writeToCustomLog("SUCESSO: PNG gerado - $png_path");
        } else {
            writeToCustomLog("ERRO: Falha na geração PNG");
        }
    } else {
        writeToCustomLog("AVISO: Helper de PNG não encontrado");
    }
    
    // 2. Gerar PDF usando helper existente
    if (file_exists(__DIR__ . '/includes/certificate_pdf_generator.php')) {
        require_once __DIR__ . '/includes/certificate_pdf_generator.php';
        
        $certificate_data = [
            'user_name' => $user['name'],
            'lecture_title' => $lecture['title'],
            'speaker_name' => $lecture['speaker'],
            'duration_minutes' => $lecture['duration_minutes'],
            'issued_at' => date('Y-m-d H:i:s')
        ];
        
        $pdf_path = generateCertificatePDF(
            $certificate_id,
            $certificate_data,
            'GENERATE_SIMPLE',
            function($msg) { writeToCustomLog($msg); }
        );
        
        if ($pdf_path && file_exists($pdf_path)) {
            $pdf_generated = true;
            writeToCustomLog("SUCESSO: PDF gerado - $pdf_path");
        } else {
            writeToCustomLog("ERRO: Falha na geração PDF");
        }
    } else {
        writeToCustomLog("AVISO: Helper de PDF não encontrado");
    }
    
    // Log de sucesso
    writeToCustomLog("SUCESSO: Certificado gerado - ID: $certificate_id, PNG: " . ($png_generated ? 'SIM' : 'NÃO') . ", PDF: " . ($pdf_generated ? 'SIM' : 'NÃO') . ", Assistido: {$final_watched_seconds}s de {$lecture_duration_seconds}s (" . round(($final_watched_seconds/$lecture_duration_seconds)*100, 1) . "%)");
    
    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Certificado gerado com sucesso!',
        'certificate_id' => $certificate_id,
        'files_generated' => [
            'png' => $png_generated,
            'pdf' => $pdf_generated
        ],
        'debug_info' => [
            'watched_seconds_db' => $db_watched_seconds,
            'watched_seconds_client' => $watched_seconds,
            'final_watched_seconds' => $final_watched_seconds,
            'required_seconds' => $required_seconds,
            'duration_hours' => $duration_hours,
            'percentage_watched' => round(($final_watched_seconds/$lecture_duration_seconds)*100, 1)
        ]
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados']);
    writeToCustomLog("ERRO PDO: " . $e->getMessage());
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro inesperado']);
    writeToCustomLog("ERRO: " . $e->getMessage());
}
?>