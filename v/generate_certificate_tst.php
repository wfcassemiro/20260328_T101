<?php
session_start();
require_once 'config/database.php';

// Função auxiliar para logs
function writeToCustomLog($message) {
    $log_file = __DIR__ . '/certificate_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] [GENERATE_CERT] $message\n", FILE_APPEND);
}

writeToCustomLog("Script generate_certificate.php iniciado");

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
$security_data = $input['security_data'] ?? [];

writeToCustomLog("Dados recebidos - Lecture: $lecture_id, User: $user_id");

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

    // VALIDAÇÕES DE SEGURANÇA ANTI-FRAUDE
    writeToCustomLog("Iniciando validações de segurança anti-fraude");

    // 1. Verificar se há dados de segurança
    if (empty($security_data)) {
        echo json_encode(['success' => false, 'message' => 'Dados de segurança ausentes - certificado bloqueado']);
        writeToCustomLog("ERRO: Dados de segurança ausentes");
        exit;
    }

    // 2. Verificar se fraude foi detectada
    if (isset($security_data['fraud_detected']) && $security_data['fraud_detected']) {
        echo json_encode(['success' => false, 'message' => 'Certificado bloqueado - comportamento suspeito detectado']);
        writeToCustomLog("ERRO: Fraude detectada - certificado bloqueado");
        exit;
    }

    // 3. Verificar progresso sequencial do banco de dados
    $stmt = $pdo->prepare("SELECT last_watched_seconds FROM access_logs WHERE user_id = ? AND resource = ? AND action = 'view_lecture'");
    $stmt->execute([$user_id, $lecture['title']]);
    $progress_data = $stmt->fetch();
    $db_watched_seconds = $progress_data['last_watched_seconds'] ?? 0;

    writeToCustomLog("Progresso no DB: {$db_watched_seconds}s");

    // 4. Validar tempo mínimo (120 segundos para teste)
    $lecture_duration_seconds = ($lecture['duration_minutes'] ?? 0) * 60;
    $required_seconds = 120; // MODO TESTE: Apenas 2 minutos obrigatórios

    writeToCustomLog("MODO TESTE: Duração total: {$lecture_duration_seconds}s, Requerido: {$required_seconds}s (fixo em 2 min)");

    if ($db_watched_seconds < $required_seconds) {
        echo json_encode([
            'success' => false,
            'message' => "TESTE: Tempo insuficiente. Assistido: {$db_watched_seconds}s (mínimo: 120s = 2 min)"
        ]);
        writeToCustomLog("ERRO: Tempo insuficiente - Assistido: {$db_watched_seconds}s, Requerido: {$required_seconds}s");
        exit;
    }

    // 5. Validar dados de segurança adicionais (RELAXADO PARA TESTE)
    $sequential_time = $security_data['sequential_time'] ?? 0;
    $segments_completed = $security_data['segments_completed'] ?? 0;
    $suspicious_activity = $security_data['suspicious_activity'] ?? 0;

    writeToCustomLog("TESTE - Security data - Sequential: {$sequential_time}s, Segments: {$segments_completed}, Suspicious: {$suspicious_activity}");

    // MODO TESTE: Verificações mais permissivas
    if ($sequential_time > 0) {
        // Verificar se tempo sequencial está próximo do tempo do DB (tolerância maior: 50%)
        $time_difference = abs($sequential_time - $db_watched_seconds);
        $tolerance = max(120, $db_watched_seconds * 0.5); // Mínimo 2 minutos ou 50% do tempo assistido

        if ($time_difference > $tolerance) {
            writeToCustomLog("ALERTA TESTE: Diferença temporal grande mas permitindo - DB: {$db_watched_seconds}s, Cliente: {$sequential_time}s, Diferença: {$time_difference}s");
            // No modo teste, apenas logamos mas não bloqueamos
        }
    }

    // 6. Verificar atividade suspeita (mais tolerante no teste)
    if ($suspicious_activity >= 10) { // Muito mais tolerante: 10 ao invés de 3
        echo json_encode(['success' => false, 'message' => 'Muitas atividades suspeitas detectadas - certificado bloqueado']);
        writeToCustomLog("ERRO: Atividades suspeitas: $suspicious_activity");
        exit;
    }

    // 7. Verificar se já existe certificado
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

    // ================================================================
    // INTEGRAÇÃO WATCHLIST - INÍCIO
    // ================================================================
    
    // Incluir o arquivo de integração
    require_once 'certificate_integration_tst.php';
    
    // Marcar palestra como assistida e remover da watchlist
    // Isso acontece quando o usuário se torna elegível para o certificado
    try {
        $watchlist_result = handleCertificateEligible($user_id, $lecture_id);
        
        if ($watchlist_result['success']) {
            if ($watchlist_result['watchlist_cleaned']) {
                writeToCustomLog("Watchlist atualizada: Palestra {$lecture_id} removida da lista do usuário {$user_id}");
            } else {
                writeToCustomLog("Watchlist verificada: Palestra {$lecture_id} não estava na lista do usuário {$user_id}");
            }
        } else {
            // Log do erro, mas não impede a geração do certificado
            writeToCustomLog("AVISO: Erro ao atualizar watchlist - " . ($watchlist_result['error'] ?? 'Erro desconhecido'));
        }
    } catch (Exception $e) {
        // Log do erro, mas não impede a geração do certificado  
        writeToCustomLog("AVISO: Exceção na integração da watchlist - " . $e->getMessage());
    }
    
    // ================================================================
    // INTEGRAÇÃO WATCHLIST - FIM
    // ================================================================

    // GERAR NOVO CERTIFICADO SEGURO
    writeToCustomLog("Todas as validações de segurança passaram - gerando certificado");

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

    // Inserir certificado no banco (SEM COLUNAS QUE NÃO EXISTEM)
    $stmt = $pdo->prepare("
        INSERT INTO certificates
        (id, user_id, lecture_id, user_name, lecture_title, speaker_name, duration_hours)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $final_percentage = ($db_watched_seconds / $lecture_duration_seconds) * 100;

    $stmt->execute([
        $certificate_id,
        $user_id,
        $lecture_id,
        $user['name'],
        $lecture['title'],
        $lecture['speaker'],
        $duration_hours
    ]);

    // Log de sucesso com dados de segurança
    writeToCustomLog("SUCESSO: Certificado TESTE gerado - ID: $certificate_id, Assistido: {$db_watched_seconds}s de {$lecture_duration_seconds}s, Watchlist integrada");

    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Certificado de teste gerado com sucesso!',
        'certificate_id' => $certificate_id,
        'watchlist_updated' => $watchlist_result['watchlist_cleaned'] ?? false,
        'test_info' => [
            'watched_seconds' => $db_watched_seconds,
            'required_seconds' => $required_seconds,
            'sequential_time' => $sequential_time,
            'segments_completed' => $segments_completed,
            'test_mode' => true
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