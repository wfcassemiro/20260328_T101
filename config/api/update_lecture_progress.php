<?php
session_start();

// Log de depuração
$log_file = __DIR__ . '/api_debug.log';

function writeToApiDebugLog($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $userIdLog = isset($_SESSION['user_id']) ? " [User: " . $_SESSION['user_id'] . "]" : "";
    @file_put_contents($log_file, "[$timestamp] [API_PROGRESS]$userIdLog " . $message . "\n", FILE_APPEND);
}

writeToApiDebugLog("UPDATE PROGRESS: Script iniciado");

// Ajuste o caminho para database.php. Se estiver em 'config/database.php' na raiz,
// e update_lecture_progress.php está em 'config/api/', então o caminho é '../../config/database.php'
require_once '../../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$response = ['success' => false, 'message' => '', 'debug_info' => []];

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Usuário não autenticado.';
    writeToApiDebugLog("ERRO: Usuário não autenticado");
    echo json_encode($response);
    exit;
}

$raw_input = file_get_contents('php://input');
writeToApiDebugLog("Raw input: " . $raw_input);

$input = json_decode($raw_input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $response['message'] = 'Erro ao decodificar JSON: ' . json_last_error_msg();
    writeToApiDebugLog("ERRO: JSON inválido - " . json_last_error_msg());
    echo json_encode($response);
    exit;
}

$lecture_id = $input['lecture_id'] ?? null;
$user_id = $input['user_id'] ?? null;
$watched_seconds = isset($input['watched_seconds']) ? (int)$input['watched_seconds'] : null;
$total_duration_seconds = isset($input['total_duration_seconds']) ? (int)$input['total_duration_seconds'] : null;

writeToApiDebugLog("Dados recebidos - Lecture: $lecture_id, User: $user_id, Watched: $watched_seconds, Duration: $total_duration_seconds");

// Validação
if (empty($lecture_id) || empty($user_id) || $watched_seconds === null || $watched_seconds < 0) {
    $response['message'] = 'Dados inválidos recebidos.';
    writeToApiDebugLog("ERRO: Dados inválidos");
    echo json_encode($response);
    exit;
}

// Verificar se user_id da sessão corresponde ao enviado
if ($user_id !== $_SESSION['user_id']) {
    $response['message'] = 'ID de usuário não corresponde à sessão.';
    writeToApiDebugLog("ALERTA: User ID não corresponde - Sessão: " . $_SESSION['user_id'] . ", Enviado: $user_id");
    echo json_encode($response);
    exit;
}

try {
    writeToApiDebugLog("Iniciando atualização no banco de dados");

    // Obter título da palestra
    // A coluna 'resource' na tabela 'access_logs' está armazenando o TÍTULO da palestra.
    $stmt_lecture = $pdo->prepare("SELECT title FROM lectures WHERE id = ?");
    $stmt_lecture->execute([$lecture_id]);
    $lecture_data = $stmt_lecture->fetch(PDO::FETCH_ASSOC);
    $lecture_title = $lecture_data['title'] ?? null;
    $stmt_lecture = null;

    if (!$lecture_title) {
        $response['message'] = 'Palestra não encontrada.';
        writeToApiDebugLog("ERRO: Palestra não encontrada - ID: $lecture_id");
        echo json_encode($response);
        exit;
    }

    writeToApiDebugLog("Palestra encontrada: '$lecture_title'");

    // Calcular progresso máximo (limitar a 100% da duração)
    $max_watched_seconds = $watched_seconds;
    if ($total_duration_seconds > 0 && $watched_seconds > $total_duration_seconds) {
        $max_watched_seconds = $total_duration_seconds;
        writeToApiDebugLog("Limitando progresso ao máximo da duração: $max_watched_seconds");
    }

    // Verificar se log existe para este user_id E resource (título da palestra)
    $stmt_check = $pdo->prepare("SELECT id, last_watched_seconds FROM access_logs WHERE user_id = ? AND resource = ? AND action = 'view_lecture'");
    $stmt_check->execute([$user_id, $lecture_title]); // Use $user_id aqui
    $existing_log = $stmt_check->fetch(PDO::FETCH_ASSOC);
    $stmt_check = null;

    if ($existing_log) {
        // Atualizar apenas se o novo progresso for maior
        $current_progress = $existing_log['last_watched_seconds'] ?? 0;

        if ($max_watched_seconds > $current_progress) {
            $stmt_update = $pdo->prepare("UPDATE access_logs SET last_watched_seconds = ?, updated_at = NOW() WHERE id = ?");
            $stmt_update->execute([$max_watched_seconds, $existing_log['id']]);
            $rowCount = $stmt_update->rowCount();
            $stmt_update = null;

            if ($rowCount > 0) {
                $response['success'] = true;
                $response['message'] = 'Progresso atualizado.';
                $response['debug_info'] = [
                    'previous_progress' => $current_progress,
                    'new_progress' => $max_watched_seconds,
                    'updated' => true
                ];
                writeToApiDebugLog("Progresso atualizado: $current_progress -> $max_watched_seconds");
            } else {
                $response['message'] = 'Erro ao atualizar progresso.';
                writeToApiDebugLog("ERRO: Falha ao atualizar progresso no banco (rowCount=0)");
            }
        } else {
            $response['success'] = true;
            $response['message'] = 'Progresso não mudou (valor menor ou igual).';
            $response['debug_info'] = [
                'current_progress' => $current_progress,
                'sent_progress' => $max_watched_seconds,
                'updated' => false
            ];
            writeToApiDebugLog("Progresso não atualizado: enviado $max_watched_seconds <= atual $current_progress");
        }
    } else {
        // Criar novo log, pois não existe um para este user_id e recurso.
        // Isso só deve acontecer se o user_id da sessão for válido.
        if ($user_id !== null) { // Validação redundante, mas reforça
            $stmt_insert = $pdo->prepare("INSERT INTO access_logs (user_id, action, resource, ip_address, user_agent, last_watched_seconds) VALUES (?, 'view_lecture', ?, ?, ?, ?)");
            $stmt_insert->execute([
                $user_id,
                $lecture_title,
                $_SERVER['REMOTE_ADDR'] ?? 'N/A',
                $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
                $max_watched_seconds
            ]);
            $stmt_insert = null;

            $response['success'] = true;
            $response['message'] = 'Novo log de progresso criado.';
            $response['debug_info'] = [
                'progress' => $max_watched_seconds,
                'created' => true
            ];
            writeToApiDebugLog("Novo log criado com progresso: $max_watched_seconds");
        } else {
            $response['message'] = 'Não foi possível criar log de progresso: User ID inválido.';
            writeToApiDebugLog("ALERTA: Não foi possível criar log de progresso: User ID inválido/nulo. Sent: {$user_id}");
        }
    }

} catch (PDOException $e) {
    $response['message'] = 'Erro no banco de dados.';
    writeToApiDebugLog("ERRO PDO: " . $e->getMessage() . " - SQLSTATE: " . $e->getCode() . " - FILE: " . $e->getFile() . " - LINE: " . $e->getLine());
} catch (Exception $e) {
    $response['message'] = 'Erro inesperado.';
    writeToApiDebugLog("ERRO GERAL: " . $e->getMessage() . " - FILE: " . $e->getFile() . " - LINE: " . $e->getLine());
}

writeToApiDebugLog("Resposta final: " . json_encode($response));
echo json_encode($response);
?>