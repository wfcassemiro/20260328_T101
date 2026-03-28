<?php
/**
 * API Time Tracker - VERSÃO DEBUG COM LOGS EXTENSIVOS
 * Use este arquivo temporariamente para descobrir o problema
 */

// Ativar TODOS os erros
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/time_tracker_debug.log');

// Função de log personalizada
function debugLog($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    if ($data !== null) {
        $logMessage .= "\n" . print_r($data, true);
    }
    $logMessage .= "\n" . str_repeat('-', 80) . "\n";
    
    // Log no arquivo
    file_put_contents('/tmp/time_tracker_debug.log', $logMessage, FILE_APPEND);
    
    // Log no error_log do PHP também
    error_log($logMessage);
}

debugLog("===== API TIME TRACKER DEBUG INICIADA =====");
debugLog("Método", $_SERVER['REQUEST_METHOD'] ?? 'DESCONHECIDO');
debugLog("URI", $_SERVER['REQUEST_URI'] ?? 'SEM URI');
debugLog("GET", $_GET);
debugLog("POST", $_POST);

// Iniciar sessão
debugLog("Iniciando sessão...");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    debugLog("Sessão iniciada", [
        'session_id' => session_id(),
        'session_data' => $_SESSION
    ]);
} else {
    debugLog("Sessão já estava ativa", [
        'session_id' => session_id(),
        'session_data' => $_SESSION
    ]);
}

// Tentar incluir arquivos
debugLog("Tentando incluir database.php...");
try {
    require_once __DIR__ . '/config/database.php';
    debugLog("✅ database.php incluído com sucesso");
    debugLog("PDO existe?", isset($pdo) ? 'SIM' : 'NÃO');
    
    if (isset($pdo)) {
        debugLog("Testando conexão PDO...");
        $test = $pdo->query("SELECT 1 as test");
        $result = $test->fetch();
        debugLog("✅ Conexão PDO funcionando", $result);
    }
} catch (Exception $e) {
    debugLog("❌ ERRO ao incluir database.php", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    die(json_encode([
        'success' => false,
        'error' => 'Erro ao carregar configuração do banco de dados',
        'details' => $e->getMessage()
    ]));
}

debugLog("Tentando incluir dash_database.php...");
try {
    require_once __DIR__ . '/config/dash_database.php';
    debugLog("✅ dash_database.php incluído");
} catch (Exception $e) {
    debugLog("❌ ERRO ao incluir dash_database.php", [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

debugLog("Tentando incluir dash_functions.php...");
try {
    require_once __DIR__ . '/config/dash_functions.php';
    debugLog("✅ dash_functions.php incluído");
} catch (Exception $e) {
    debugLog("❌ ERRO ao incluir dash_functions.php", [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

header('Content-Type: application/json; charset=UTF-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

debugLog("Action recebida", $action);
debugLog("User ID da sessão", $user_id);
debugLog("Todas as variáveis de sessão", $_SESSION);

if (!$user_id) {
    debugLog("❌ User ID não definido - retornando erro");
    die(json_encode([
        'success' => false,
        'error' => 'Usuário não autenticado',
        'debug_session' => $_SESSION,
        'debug_cookies' => $_COOKIE
    ]));
}

/**
 * FUNÇÕES AUXILIARES
 */

function sanitizeInput($value) {
    return htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8');
}

function formatDuration($seconds) {
    $seconds = (int)$seconds;
    $hours   = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs    = $seconds % 60;
    return str_pad($hours, 2, '0', STR_PAD_LEFT) . ':' .
           str_pad($minutes, 2, '0', STR_PAD_LEFT) . ':' .
           str_pad($secs, 2, '0', STR_PAD_LEFT);
}

function generateUUID() {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function jsonSuccess($data = [], $message = '') {
    debugLog("Retornando sucesso", ['message' => $message, 'data' => $data]);
    echo json_encode([
        'success' => true,
        'message' => $message,
        'error'   => null,
        'projects' => $data['projects'] ?? null,
        'tasks'    => $data['tasks'] ?? null,
        'entries'  => $data['entries'] ?? null,
        'entry'    => $data['entry'] ?? null,
        'project_id' => $data['project_id'] ?? null,
        'project_name' => $data['project_name'] ?? null,
        'task_id' => $data['task_id'] ?? null,
        'duration' => $data['duration'] ?? null,
        'duration_formatted' => $data['duration_formatted'] ?? null,
        'data'    => $data
    ]);
    exit;
}

function jsonError($message, $code = 400) {
    debugLog("Retornando erro", ['code' => $code, 'message' => $message]);
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => '',
        'error'   => $message
    ]);
    exit;
}

function calculateDuration($start_time, $end_time, $paused_duration = 0) {
    $start = new DateTime($start_time);
    $end   = new DateTime($end_time);
    $diff  = $end->getTimestamp() - $start->getTimestamp() - (int)$paused_duration;
    return max(0, $diff);
}

// PROCESSAR AÇÕES
try {
    debugLog("Processando action: $action");
    
    switch ($action) {
        case 'project_list':
            debugLog("===== ACTION: project_list =====");
            debugLog("User ID para consulta", $user_id);
            
            // Verificar se a tabela existe
            debugLog("Verificando se tabela dash_projects existe...");
            try {
                $checkTable = $pdo->query("SHOW TABLES LIKE 'dash_projects'");
                $tableExists = $checkTable->rowCount() > 0;
                debugLog("Tabela dash_projects existe?", $tableExists ? 'SIM' : 'NÃO');
                
                if (!$tableExists) {
                    jsonError("Tabela dash_projects não existe no banco de dados");
                }
            } catch (Exception $e) {
                debugLog("Erro ao verificar tabela", $e->getMessage());
            }
            
            debugLog("Preparando query SQL...");
            $sql = "
                SELECT
                    id,
                    title as name,
                    client_id,
                    '#7B61FF' as color,
                    status,
                    created_at
                FROM dash_projects
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 50
            ";
            debugLog("SQL preparado", $sql);
            
            debugLog("Executando query...");
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            
            $projects = $stmt->fetchAll();
            debugLog("Projetos encontrados", count($projects));
            debugLog("Primeiros 3 projetos", array_slice($projects, 0, 3));
            
            // Adicionar estatísticas para cada projeto
            foreach ($projects as &$project) {
                debugLog("Processando projeto ID: " . $project['id']);
                
                // Contar tarefas
                try {
                    $stmt_tasks = $pdo->prepare("SELECT COUNT(*) FROM time_tasks WHERE project_id = ?");
                    $stmt_tasks->execute([$project['id']]);
                    $project['task_count'] = $stmt_tasks->fetchColumn();
                    debugLog("  Task count: " . $project['task_count']);
                } catch (Exception $e) {
                    debugLog("  Erro ao contar tarefas", $e->getMessage());
                    $project['task_count'] = 0;
                }
                
                // Contar entries
                try {
                    $stmt_entries = $pdo->prepare("
                        SELECT COUNT(*) as entry_count, COALESCE(SUM(duration), 0) as total_duration
                        FROM time_entries WHERE project_id = ?
                    ");
                    $stmt_entries->execute([$project['id']]);
                    $stats = $stmt_entries->fetch();
                    $project['entry_count'] = $stats['entry_count'];
                    $project['total_duration'] = $stats['total_duration'];
                    $project['duration_formatted'] = formatDuration($stats['total_duration']);
                    debugLog("  Entry count: " . $project['entry_count']);
                    debugLog("  Total duration: " . $project['total_duration']);
                } catch (Exception $e) {
                    debugLog("  Erro ao contar entries", $e->getMessage());
                    $project['entry_count'] = 0;
                    $project['total_duration'] = 0;
                    $project['duration_formatted'] = '00:00:00';
                }
            }
            
            debugLog("Retornando projetos processados", count($projects));
            jsonSuccess(['projects' => $projects]);
            break;
            
        case 'project_create_quick':
            debugLog("===== ACTION: project_create_quick =====");
            
            $name   = sanitizeInput($_POST['name'] ?? '');
            $client = sanitizeInput($_POST['client'] ?? '');
            
            debugLog("Nome do projeto", $name);
            debugLog("Cliente", $client);
            
            if (empty($name)) {
                debugLog("Nome vazio - retornando erro");
                jsonError('Nome do projeto é obrigatório');
            }
            
            debugLog("Verificando se tabela dash_projects existe...");
            $checkTable = $pdo->query("SHOW TABLES LIKE 'dash_projects'");
            if ($checkTable->rowCount() === 0) {
                debugLog("❌ Tabela dash_projects NÃO EXISTE");
                jsonError("Tabela dash_projects não existe. Execute o SQL de criação.");
            }
            
            debugLog("Preparando INSERT...");
            $sql = "
                INSERT INTO dash_projects
                    (user_id, title, client_id, status, created_at)
                VALUES (?, ?, 0, 'in_progress', NOW())
            ";
            debugLog("SQL", $sql);
            debugLog("Parâmetros", [$user_id, $name]);
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$user_id, $name]);
            
            debugLog("INSERT executado", $result ? 'SUCESSO' : 'FALHOU');
            
            $project_id = $pdo->lastInsertId();
            debugLog("ID do projeto criado", $project_id);
            
            jsonSuccess([
                'project_id' => $project_id,
                'project_name' => $name
            ], 'Projeto criado com sucesso');
            break;
            
        default:
            debugLog("Action desconhecida ou não implementada", $action);
            jsonError('Ação inválida ou não implementada nesta versão debug', 400);
    }
    
} catch (PDOException $e) {
    debugLog("===== ERRO PDO =====", [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    jsonError('Erro no banco de dados: ' . $e->getMessage(), 500);
    
} catch (Exception $e) {
    debugLog("===== ERRO GENÉRICO =====", [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    jsonError('Erro no servidor: ' . $e->getMessage(), 500);
}

debugLog("===== FIM DA EXECUÇÃO =====");
