<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Log de segurança
$security_log_file = __DIR__ . '/security_debug.log';

function writeSecurityLog($message) {
    global $security_log_file;
    $timestamp = date('Y-m-d H:i:s');
    $userIdLog = isset($_SESSION['user_id']) ? " [User: " . $_SESSION['user_id'] . "]" : "";
    @file_put_contents($security_log_file, "[$timestamp] [SECURE_PROGRESS]$userIdLog " . $message . "\n", FILE_APPEND);
}

writeSecurityLog("SECURE PROGRESS: Script iniciado");

require_once '../../config/database.php';

$response = ['success' => false, 'message' => '', 'security_info' => []];

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Usuário não autenticado.';
    writeSecurityLog("ERRO: Usuário não autenticado");
    echo json_encode($response);
    exit;
}

$raw_input = file_get_contents('php://input');
writeSecurityLog("Raw input: " . $raw_input);

$input = json_decode($raw_input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $response['message'] = 'Erro ao decodificar JSON: ' . json_last_error_msg();
    writeSecurityLog("ERRO: JSON inválido - " . json_last_error_msg());
    echo json_encode($response);
    exit;
}

// Extrair dados com validação de segurança
$lecture_id = $input['lecture_id'] ?? null;
$user_id = $input['user_id'] ?? null;
$watched_seconds = isset($input['watched_seconds']) ? (int)$input['watched_seconds'] : null;
$total_duration_seconds = isset($input['total_duration_seconds']) ? (int)$input['total_duration_seconds'] : null;
$segments_completed = isset($input['segments_completed']) ? (int)$input['segments_completed'] : 0;
$total_segments = isset($input['total_segments']) ? (int)$input['total_segments'] : 0;
$suspicious_activity_count = isset($input['suspicious_activity_count']) ? (int)$input['suspicious_activity_count'] : 0;
$fraud_detected = isset($input['fraud_detected']) ? (bool)$input['fraud_detected'] : false;

writeSecurityLog("Dados recebidos - Lecture: $lecture_id, User: $user_id, Watched: $watched_seconds, Segments: $segments_completed/$total_segments, Suspicious: $suspicious_activity_count, Fraud: " . ($fraud_detected ? 'YES' : 'NO'));

// Validação básica
if (empty($lecture_id) || empty($user_id) || $watched_seconds === null || $watched_seconds < 0) {
    $response['message'] = 'Dados inválidos recebidos.';
    writeSecurityLog("ERRO: Dados inválidos");
    echo json_encode($response);
    exit;
}

// Verificar se user_id da sessão corresponde ao enviado
if ($user_id !== $_SESSION['user_id']) {
    $response['message'] = 'ID de usuário não corresponde à sessão.';
    writeSecurityLog("ALERTA: User ID não corresponde - Sessão: " . $_SESSION['user_id'] . ", Enviado: $user_id");
    echo json_encode($response);
    exit;
}

// VALIDAÇÕES DE SEGURANÇA ANTI-FRAUDE
$security_violations = [];

// 1. Verificar se o progresso não excede a duração
if ($total_duration_seconds > 0 && $watched_seconds > $total_duration_seconds) {
    $security_violations[] = "Tempo assistido excede duração total";
    writeSecurityLog("VIOLAÇÃO: Tempo assistido ($watched_seconds) > duração total ($total_duration_seconds)");
}

// 2. Verificar progressão de segmentos
$expected_segments = $total_segments > 0 ? ceil(($watched_seconds / $total_duration_seconds) * $total_segments) : 0;
if ($segments_completed > $expected_segments + 2) { // Tolerância de 2 segmentos
    $security_violations[] = "Segmentos completados inconsistentes com tempo assistido";
    writeSecurityLog("VIOLAÇÃO: Segmentos ($segments_completed) > esperado ($expected_segments)");
}

// 3. Verificar atividade suspeita
if ($suspicious_activity_count >= 3) {
    $security_violations[] = "Muitas atividades suspeitas detectadas";
    writeSecurityLog("VIOLAÇÃO: Atividades suspeitas: $suspicious_activity_count");
}

// 4. Verificar se fraude foi detectada
if ($fraud_detected) {
    $security_violations[] = "Fraude detectada pelo sistema cliente";
    writeSecurityLog("VIOLAÇÃO: Fraude detectada pelo cliente");
}

// Se há violações de segurança, não salvar progresso
if (!empty($security_violations)) {
    $response['message'] = 'Violações de segurança detectadas: ' . implode(', ', $security_violations);
    $response['security_info'] = [
        'violations' => $security_violations,
        'blocked' => true,
        'suspicious_activity' => $suspicious_activity_count,
        'fraud_detected' => $fraud_detected
    ];
    writeSecurityLog("BLOQUEADO: Violações de segurança - " . implode(', ', $security_violations));
    echo json_encode($response);
    exit;
}

try {
    writeSecurityLog("Iniciando atualização segura no banco de dados");

    // Obter título da palestra
    $stmt_lecture = $pdo->prepare("SELECT title FROM lectures WHERE id = ?");
    $stmt_lecture->execute([$lecture_id]);
    $lecture_data = $stmt_lecture->fetch(PDO::FETCH_ASSOC);
    $lecture_title = $lecture_data['title'] ?? null;
    $stmt_lecture = null;

    if (!$lecture_title) {
        $response['message'] = 'Palestra não encontrada.';
        writeSecurityLog("ERRO: Palestra não encontrada - ID: $lecture_id");
        echo json_encode($response);
        exit;
    }

    writeSecurityLog("Palestra encontrada: '$lecture_title'");

    // Verificar se log existe
    $stmt_check = $pdo->prepare("SELECT id, last_watched_seconds FROM access_logs WHERE user_id = ? AND resource = ? AND action = 'view_lecture'");
    $stmt_check->execute([$user_id, $lecture_title]);
    $existing_log = $stmt_check->fetch(PDO::FETCH_ASSOC);
    $stmt_check = null;

    if ($existing_log) {
        $current_progress = $existing_log['last_watched_seconds'] ?? 0;

        // VALIDAÇÃO ADICIONAL: Progresso só pode aumentar de forma sequencial
        if ($watched_seconds > $current_progress) {
            // Verificar se o aumento é realista (máximo 2x desde a última atualização)
            $max_realistic_increase = $current_progress * 2 + 60; // + 1 minuto de tolerância
            
            if ($watched_seconds > $max_realistic_increase) {
                $response['message'] = 'Aumento de progresso não realista detectado.';
                writeSecurityLog("VIOLAÇÃO: Aumento não realista - Atual: $current_progress, Novo: $watched_seconds, Máximo: $max_realistic_increase");
                echo json_encode($response);
                exit;
            }

            $stmt_update = $pdo->prepare("UPDATE access_logs SET last_watched_seconds = ?, updated_at = NOW() WHERE id = ?");
            $stmt_update->execute([$watched_seconds, $existing_log['id']]);
            $rowCount = $stmt_update->rowCount();
            $stmt_update = null;

            if ($rowCount > 0) {
                $response['success'] = true;
                $response['message'] = 'Progresso seguro atualizado.';
                $response['security_info'] = [
                    'previous_progress' => $current_progress,
                    'new_progress' => $watched_seconds,
                    'segments_completed' => $segments_completed,
                    'security_passed' => true,
                    'suspicious_activity' => $suspicious_activity_count
                ];
                writeSecurityLog("SUCESSO: Progresso seguro atualizado: $current_progress -> $watched_seconds");
            } else {
                $response['message'] = 'Erro ao atualizar progresso seguro.';
                writeSecurityLog("ERRO: Falha ao atualizar progresso no banco");
            }
        } else {
            $response['success'] = true;
            $response['message'] = 'Progresso não mudou (valor menor ou igual).';
            $response['security_info'] = [
                'current_progress' => $current_progress,
                'sent_progress' => $watched_seconds,
                'updated' => false,
                'security_passed' => true
            ];
            writeSecurityLog("INFO: Progresso não atualizado: enviado $watched_seconds <= atual $current_progress");
        }
    } else {
        // Criar novo log
        $stmt_insert = $pdo->prepare("INSERT INTO access_logs (user_id, action, resource, ip_address, user_agent, last_watched_seconds) VALUES (?, 'view_lecture', ?, ?, ?, ?)");
        $stmt_insert->execute([
            $user_id,
            $lecture_title,
            $_SERVER['REMOTE_ADDR'] ?? 'N/A',
            $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
            $watched_seconds
        ]);
        $stmt_insert = null;

        $response['success'] = true;
        $response['message'] = 'Novo log de progresso seguro criado.';
        $response['security_info'] = [
            'progress' => $watched_seconds,
            'created' => true,
            'segments_completed' => $segments_completed,
            'security_passed' => true
        ];
        writeSecurityLog("SUCESSO: Novo log seguro criado com progresso: $watched_seconds");
    }

} catch (PDOException $e) {
    $response['message'] = 'Erro no banco de dados.';
    writeSecurityLog("ERRO PDO: " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Erro inesperado.';
    writeSecurityLog("ERRO: " . $e->getMessage());
}

writeSecurityLog("Resposta final: " . json_encode($response));
echo json_encode($response);
?>
