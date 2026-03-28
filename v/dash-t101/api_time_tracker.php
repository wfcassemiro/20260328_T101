<?php
/**
 * API Time Tracker
 * Integrado com dash_projects existente
 */

// DEBUG: Log de início
error_log("==== API TIME TRACKER CHAMADA ====");
error_log("Método: " . ($_SERVER['REQUEST_METHOD'] ?? 'CLI'));
error_log("URI: " . ($_SERVER['REQUEST_URI'] ?? 'sem URI'));
error_log("GET: " . json_encode($_GET));
error_log("POST: " . json_encode($_POST));

// Iniciar sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Incluir arquivos de configuração (mesmo caminho do projects.php)
    require_once __DIR__ . '/../config/database.php';
    error_log("database.php incluído com sucesso");
    
    require_once __DIR__ . '/../config/dash_database.php';
    error_log("dash_database.php incluído com sucesso");
    
    require_once __DIR__ . '/../config/dash_functions.php';
    error_log("dash_functions.php incluído com sucesso");
} catch (Exception $e) {
    error_log("ERRO ao incluir arquivos de configuração: " . $e->getMessage());
    die(json_encode(['success' => false, 'error' => 'Erro de configuração: ' . $e->getMessage()]));
}

header('Content-Type: application/json; charset=UTF-8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

error_log("Action: " . $action);
error_log("User ID: " . ($user_id ?? 'NÃO DEFINIDO'));

if (!$user_id) {
    error_log("ERRO: User ID não definido na sessão");
    die(json_encode(['success' => false, 'error' => 'Usuário não autenticado']));
}

/**
 * FUNÇÕES AUXILIARES LOCAIS
 */

function sanitizeInput($value)
{
    return htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8');
}

function formatDuration($seconds)
{
    $seconds = (int)$seconds;
    $hours   = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs    = $seconds % 60;

    return str_pad($hours, 2, '0', STR_PAD_LEFT) . ':' .
           str_pad($minutes, 2, '0', STR_PAD_LEFT) . ':' .
           str_pad($secs, 2, '0', STR_PAD_LEFT);
}

function generateUUID()
{
    // UUID v4 simples
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// Helpers de resposta JSON
function jsonSuccess($data = [], $message = '')
{
    echo json_encode([
        'success' => true,
        'message' => $message,
        'error'   => null,
        'projects' => $data['projects'] ?? null,
        'tasks'    => $data['tasks'] ?? null,
        'entries'  => $data['entries'] ?? null,
        'entry'    => $data['entry'] ?? null,
        'project_id' => $data['project_id'] ?? null,
        'task_id' => $data['task_id'] ?? null,
        'duration' => $data['duration'] ?? null,
        'duration_formatted' => $data['duration_formatted'] ?? null,
        'data'    => $data
    ]);
    exit;
}

function jsonError($message, $code = 400)
{
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => '',
        'error'   => $message
    ]);
    exit;
}

function calculateDuration($start_time, $end_time, $paused_duration = 0)
{
    $start = new DateTime($start_time);
    $end   = new DateTime($end_time);
    $diff  = $end->getTimestamp() - $start->getTimestamp() - (int)$paused_duration;
    return max(0, $diff);
}

try {
    switch ($action) {
        // ==== PROJETOS (usando dash_projects) ====
        case 'project_list':
            error_log("[TT API] project_list chamado para user_id: " . $user_id);
            
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    project_name as name,
                    client_name,
                    '#7B61FF' as color,
                    status
                FROM dash_projects
                WHERE user_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$user_id]);
            $projects = $stmt->fetchAll();
            
            error_log("[TT API] project_list - projetos encontrados: " . count($projects));
            
            foreach ($projects as &$project) {
                $stmt_tasks = $pdo->prepare("SELECT COUNT(*) FROM time_tasks WHERE project_id = ?");
                $stmt_tasks->execute([$project['id']]);
                $project['task_count'] = $stmt_tasks->fetchColumn();
                
                $stmt_entries = $pdo->prepare("
                    SELECT COUNT(*) as entry_count, COALESCE(SUM(duration), 0) as total_duration
                    FROM time_entries WHERE project_id = ?
                ");
                $stmt_entries->execute([$project['id']]);
                $stats = $stmt_entries->fetch();
                $project['entry_count'] = $stats['entry_count'];
                $project['total_duration'] = $stats['total_duration'];
                $project['duration_formatted'] = formatDuration($stats['total_duration']);
            }
            
            jsonSuccess(['projects' => $projects]);
            break;

        case 'project_create_quick':
            $name   = sanitizeInput($_POST['name'] ?? '');
            $client = sanitizeInput($_POST['client'] ?? '');
            
            if (empty($name)) {
                jsonError('Nome do projeto é obrigatório');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO dash_projects 
                    (user_id, project_name, client_name, status, created_at)
                VALUES (?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([$user_id, $name, $client]);
            $project_id = $pdo->lastInsertId();
            
            jsonSuccess(['project_id' => $project_id, 'project_name' => $name], 'Projeto criado com sucesso');
            break;

        // ==== TAREFAS ====
        case 'task_list':
            $project_id = $_GET['project_id'] ?? '';
            
            if (empty($project_id)) {
                jsonError('ID do projeto não fornecido');
            }
            
            $stmt = $pdo->prepare("
                SELECT t.*, 
                    COUNT(e.id) as entry_count,
                    COALESCE(SUM(e.duration), 0) as total_duration
                FROM time_tasks t
                LEFT JOIN time_entries e ON t.id = e.task_id
                WHERE t.project_id = ? AND t.user_id = ? AND t.is_active = 1
                GROUP BY t.id
                ORDER BY t.created_at DESC
            ");
            $stmt->execute([$project_id, $user_id]);
            $tasks = $stmt->fetchAll();
            
            foreach ($tasks as &$task) {
                $task['duration_formatted'] = formatDuration($task['total_duration']);
            }
            
            jsonSuccess(['tasks' => $tasks]);
            break;

        case 'task_create':
            $project_id = $_POST['project_id'] ?? '';
            $name       = sanitizeInput($_POST['name'] ?? '');
            
            if (empty($project_id) || empty($name)) {
                jsonError('Dados incompletos');
            }
            
            $id = generateUUID();
            $stmt = $pdo->prepare("
                INSERT INTO time_tasks (id, project_id, user_id, name)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$id, $project_id, $user_id, $name]);
            
            jsonSuccess(['task_id' => $id], 'Tarefa criada com sucesso');
            break;

        case 'task_delete':
            $id = $_POST['id'] ?? '';
            
            if (empty($id)) {
                jsonError('ID da tarefa não fornecido');
            }
            
            $stmt = $pdo->prepare("UPDATE time_tasks SET is_active = 0 WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            
            jsonSuccess([], 'Tarefa deletada com sucesso');
            break;

        // ==== REGISTROS DE TEMPO ====
        case 'entry_list':
            $limit      = intval($_GET['limit'] ?? 50);
            $offset     = intval($_GET['offset'] ?? 0);
            $project_id = $_GET['project_id'] ?? '';
            
            $sql = "
                SELECT e.*, 
                    p.project_name, 
                    t.name as task_name
                FROM time_entries e
                LEFT JOIN dash_projects p ON e.project_id = p.id
                LEFT JOIN time_tasks t ON e.task_id = t.id
                WHERE e.user_id = ?
            ";
            
            $params = [$user_id];
            
            if (!empty($project_id)) {
                $sql .= " AND e.project_id = ?";
                $params[] = $project_id;
            }
            
            $sql .= " ORDER BY e.start_time DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $entries = $stmt->fetchAll();
            
            foreach ($entries as &$entry) {
                if ($entry['is_running']) {
                    $now   = new DateTime();
                    $start = new DateTime($entry['start_time']);
                    $elapsed = $now->getTimestamp() - $start->getTimestamp();
                    
                    if ($entry['paused_at']) {
                        $pausedTime = $now->getTimestamp() - (new DateTime($entry['paused_at']))->getTimestamp();
                        $entry['duration'] = $elapsed - $pausedTime - $entry['paused_duration'];
                    } else {
                        $entry['duration'] = $elapsed - $entry['paused_duration'];
                    }
                }
                
                $entry['duration_formatted'] = formatDuration($entry['duration']);
                $entry['project_color'] = '#7B61FF';
            }
            
            jsonSuccess(['entries' => $entries]);
            break;

        case 'entry_running':
            $stmt = $pdo->prepare("
                SELECT e.*, 
                    p.project_name, 
                    t.name as task_name
                FROM time_entries e
                LEFT JOIN dash_projects p ON e.project_id = p.id
                LEFT JOIN time_tasks t ON e.task_id = t.id
                WHERE e.user_id = ? AND e.is_running = 1
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            $entry = $stmt->fetch();
            
            if ($entry) {
                $now   = new DateTime();
                $start = new DateTime($entry['start_time']);
                $elapsed = $now->getTimestamp() - $start->getTimestamp();
                
                if ($entry['paused_at']) {
                    $pausedTime = $now->getTimestamp() - (new DateTime($entry['paused_at']))->getTimestamp();
                    $entry['duration'] = $elapsed - $pausedTime - $entry['paused_duration'];
                    $entry['is_paused'] = true;
                } else {
                    $entry['duration'] = $elapsed - $entry['paused_duration'];
                    $entry['is_paused'] = false;
                }
                
                $entry['duration_formatted'] = formatDuration($entry['duration']);
                $entry['project_color'] = '#7B61FF';
            }
            
            jsonSuccess(['entry' => $entry]);
            break;

        case 'entry_start':
            $stmt = $pdo->prepare("SELECT id FROM time_entries WHERE user_id = ? AND is_running = 1");
            $stmt->execute([$user_id]);
            if ($stmt->fetch()) {
                jsonError('Já existe um cronômetro ativo. Pare-o antes de iniciar outro.');
            }
            
            $project_id  = $_POST['project_id'] ?? null;
            $task_id     = $_POST['task_id'] ?? null;
            $description = sanitizeInput($_POST['description'] ?? '');
            
            $id         = generateUUID();
            $start_time = date('Y-m-d H:i:s');
            
            $stmt = $pdo->prepare("
                INSERT INTO time_entries (id, user_id, project_id, task_id, description, start_time, is_running)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$id, $user_id, $project_id ?: null, $task_id ?: null, $description, $start_time]);
            
            jsonSuccess(['entry_id' => $id], 'Cronômetro iniciado');
            break;

        case 'entry_pause':
            $id = $_POST['id'] ?? '';
            
            if (empty($id)) {
                jsonError('ID do registro não fornecido');
            }
            
            $stmt = $pdo->prepare("
                UPDATE time_entries 
                SET paused_at = NOW()
                WHERE id = ? AND user_id = ? AND is_running = 1 AND paused_at IS NULL
            ");
            $stmt->execute([$id, $user_id]);
            
            if ($stmt->rowCount() === 0) {
                jsonError('Registro não encontrado ou já pausado');
            }
            
            jsonSuccess([], 'Cronômetro pausado');
            break;

        case 'entry_resume':
            $id = $_POST['id'] ?? '';
            
            if (empty($id)) {
                jsonError('ID do registro não fornecido');
            }
            
            $stmt = $pdo->prepare("
                SELECT paused_at, paused_duration 
                FROM time_entries 
                WHERE id = ? AND user_id = ? AND is_running = 1 AND paused_at IS NOT NULL
            ");
            $stmt->execute([$id, $user_id]);
            $entry = $stmt->fetch();
            
            if (!$entry) {
                jsonError('Registro não encontrado ou não pausado');
            }
            
            $pausedDuration = (new DateTime())->getTimestamp() - (new DateTime($entry['paused_at']))->getTimestamp();
            $totalPaused = $entry['paused_duration'] + $pausedDuration;
            
            $stmt = $pdo->prepare("
                UPDATE time_entries 
                SET paused_at = NULL, paused_duration = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$totalPaused, $id, $user_id]);
            
            jsonSuccess([], 'Cronômetro retomado');
            break;

        case 'entry_stop':
            $id = $_POST['id'] ?? '';
            
            if (empty($id)) {
                jsonError('ID do registro não fornecido');
            }
            
            $stmt = $pdo->prepare("
                SELECT start_time, paused_at, paused_duration 
                FROM time_entries 
                WHERE id = ? AND user_id = ? AND is_running = 1
            ");
            $stmt->execute([$id, $user_id]);
            $entry = $stmt->fetch();
            
            if (!$entry) {
                jsonError('Registro não encontrado ou já parado');
            }
            
            $end_time = date('Y-m-d H:i:s');
            
            $pausedDuration = $entry['paused_duration'];
            if ($entry['paused_at']) {
                $additionalPause = (new DateTime())->getTimestamp() - (new DateTime($entry['paused_at']))->getTimestamp();
                $pausedDuration += $additionalPause;
            }
            
            $duration = calculateDuration($entry['start_time'], $end_time, $pausedDuration);
            
            $stmt = $pdo->prepare("
                UPDATE time_entries 
                SET end_time = ?, duration = ?, is_running = 0, paused_at = NULL, paused_duration = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$end_time, $duration, $pausedDuration, $id, $user_id]);
            
            jsonSuccess(['duration' => $duration, 'duration_formatted' => formatDuration($duration)], 'Cronômetro parado');
            break;

        case 'entry_delete':
            $id = $_POST['id'] ?? '';
            
            if (empty($id)) {
                jsonError('ID do registro não fornecido');
            }
            
            $stmt = $pdo->prepare("DELETE FROM time_entries WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            
            jsonSuccess([], 'Registro deletado com sucesso');
            break;

        default:
            jsonError('Ação inválida', 400);
    }
    
} catch (PDOException $e) {
    error_log("Time Tracker API Error (PDO): " . $e->getMessage());
    jsonError('Erro no servidor. Tente novamente.', 500);
} catch (Exception $e) {
    error_log("Time Tracker API Error (Exception): " . $e->getMessage());
    jsonError($e->getMessage(), 400);
}