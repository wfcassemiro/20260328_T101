<?php
/**
 * API Time Tracker - V5.1 (Verificação de Duplicidade)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0); 
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_domain', '.translators101.com');
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

$config_paths = [__DIR__ . '/config/', __DIR__ . '/../config/', $_SERVER['DOCUMENT_ROOT'] . '/dash-t101/config/'];
$loaded = false;
foreach ($config_paths as $path) {
    if (file_exists($path . 'database.php')) {
        require_once $path . 'database.php';
        if (file_exists($path . 'dash_functions.php')) require_once $path . 'dash_functions.php';
        $loaded = true; break;
    }
}

if (!$loaded) { http_response_code(500); die(json_encode(['success' => false, 'error' => 'Erro de configuração'])); }

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { http_response_code(401); die(json_encode(['success' => false, 'error' => 'Sessão expirada'])); }

// Helpers
function jsonSuccess($data=[], $msg=''){ echo json_encode(array_merge(['success'=>true,'message'=>$msg],$data)); exit; }
function jsonError($msg, $code=400, $code_str=null){ 
    http_response_code($code); 
    $resp = ['success'=>false,'error'=>$msg];
    if($code_str) $resp['code'] = $code_str;
    echo json_encode($resp); 
    exit; 
}
function formatDuration($s){ $h=floor($s/3600); $m=floor(($s%3600)/60); $sec=$s%60; return sprintf('%02d:%02d:%02d',$h,$m,$sec); }
function generateUUID(){ $d=random_bytes(16); $d[6]=chr((ord($d[6])&0x0f)|0x40); $d[8]=chr((ord($d[8])&0x3f)|0x80); return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4)); }
function sanitizeInput($v){ return htmlspecialchars(trim((string)$v),ENT_QUOTES,'UTF-8'); }

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'project_list':
            $stmt = $pdo->prepare("SELECT id, title as name, client_id, '#7B61FF' as color, status, is_time_tracker_only FROM dash_projects WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$user_id]);
            jsonSuccess(['projects' => $stmt->fetchAll()]);
            break;

        case 'project_create_quick':
            $name = sanitizeInput($_POST['name'] ?? '');
            $force = isset($_POST['force']) && $_POST['force'] === 'true';
            
            if (empty($name)) jsonError('Nome obrigatório');
            
            // Verifica duplicidade se não for forçado
            if (!$force) {
                $check = $pdo->prepare("SELECT id FROM dash_projects WHERE user_id = ? AND title = ?");
                $check->execute([$user_id, $name]);
                if ($check->fetch()) {
                    jsonError("Já existe um projeto com este nome.", 200, 'DUPLICATE_NAME');
                }
            }
            
            $stmt = $pdo->prepare("INSERT INTO dash_projects (user_id, title, client_id, status, created_at, is_time_tracker_only) VALUES (?, ?, NULL, 'in_progress', NOW(), 1)");
            $stmt->execute([$user_id, $name]);
            jsonSuccess(['project_id' => $pdo->lastInsertId(), 'project_name' => $name], 'Projeto criado!');
            break;

        case 'entry_start':
            $stmt = $pdo->prepare("SELECT id FROM time_entries WHERE user_id = ? AND is_running = 1");
            $stmt->execute([$user_id]);
            if ($stmt->fetch()) jsonError('Pare o cronômetro atual antes de iniciar outro.');
            
            $id = generateUUID();
            $desc = sanitizeInput($_POST['description'] ?? '');
            $pid = !empty($_POST['project_id']) ? $_POST['project_id'] : null;
            $tid = !empty($_POST['task_id']) ? $_POST['task_id'] : null;
            $type = $_POST['entry_type'] ?? 'manual';
            
            $stmt = $pdo->prepare("INSERT INTO time_entries (id, user_id, project_id, task_id, description, start_time, is_running, paused_duration, entry_type) VALUES (?, ?, ?, ?, ?, NOW(), 1, 0, ?)");
            $stmt->execute([$id, $user_id, $pid, $tid, $desc, $type]);
            
            $stmt = $pdo->prepare("SELECT e.*, p.title as project_name FROM time_entries e LEFT JOIN dash_projects p ON e.project_id = p.id WHERE e.id = ?");
            $stmt->execute([$id]);
            $newEntry = $stmt->fetch();
            if ($newEntry) {
                $newEntry['duration'] = 0;
                $newEntry['duration_formatted'] = "00:00:00";
                $newEntry['is_paused'] = false;
            }
            jsonSuccess(['entry_id' => $id, 'entry' => $newEntry], 'Iniciado');
            break;

        case 'entry_running':
            $stmt = $pdo->prepare("SELECT e.*, p.title as project_name FROM time_entries e LEFT JOIN dash_projects p ON e.project_id = p.id WHERE e.user_id = ? AND e.is_running = 1 LIMIT 1");
            $stmt->execute([$user_id]);
            $entry = $stmt->fetch();
            
            if ($entry) {
                $now = time();
                $start = strtotime($entry['start_time']);
                $elapsed = $now - $start - $entry['paused_duration'];
                if ($entry['paused_at']) { $elapsed -= ($now - strtotime($entry['paused_at'])); $entry['is_paused'] = true; } 
                else { $entry['is_paused'] = false; }
                $entry['duration'] = max(0, $elapsed);
                $entry['duration_formatted'] = formatDuration($entry['duration']);
            }
            jsonSuccess(['entry' => $entry]);
            break;

        case 'entry_stop':
            $id = $_POST['id'] ?? '';
            $stmt = $pdo->prepare("SELECT * FROM time_entries WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            $entry = $stmt->fetch();
            if (!$entry || !$entry['is_running']) jsonError('Erro ao parar');
            
            $now = time();
            $total_pause = $entry['paused_duration'];
            if ($entry['paused_at']) $total_pause += ($now - strtotime($entry['paused_at']));
            $duration = max(0, ($now - strtotime($entry['start_time'])) - $total_pause);
            
            $pdo->prepare("UPDATE time_entries SET end_time = NOW(), duration = ?, is_running = 0, paused_at = NULL, paused_duration = ? WHERE id = ?")->execute([$duration, $total_pause, $id]);
            jsonSuccess(['duration_formatted' => formatDuration($duration)], 'Finalizado');
            break;

        case 'entry_list':
            $limit = 50;
            $pid = $_GET['project_id'] ?? '';
            $sql = "SELECT e.*, p.title as project_name FROM time_entries e LEFT JOIN dash_projects p ON e.project_id = p.id WHERE e.user_id = ?";
            if ($pid) $sql .= " AND e.project_id = $pid";
            $sql .= " ORDER BY e.start_time DESC LIMIT $limit";
            $stmt = $pdo->prepare($sql); $stmt->execute([$user_id]);
            $entries = $stmt->fetchAll();
            foreach ($entries as &$e) { 
                $e['duration_formatted'] = formatDuration($e['duration']); 
                if($e['entry_type'] == 'pomodoro_work') $e['type_label'] = '🍅 Pomodoro';
                elseif($e['entry_type'] == 'pomodoro_break') $e['type_label'] = '☕ Pausa';
                else $e['type_label'] = '';
            }
            jsonSuccess(['entries' => $entries]);
            break;
            
        case 'entry_delete':
            $pdo->prepare("DELETE FROM time_entries WHERE id = ? AND user_id = ?")->execute([$_POST['id'], $user_id]);
            jsonSuccess([], 'Apagado');
            break;
            
        case 'entry_pause':
            $id = $_POST['id'] ?? '';
            $stmt = $pdo->prepare("UPDATE time_entries SET paused_at = NOW() WHERE id = ? AND user_id = ? AND is_running = 1 AND paused_at IS NULL");
            $stmt->execute([$id, $user_id]);
            jsonSuccess([], 'Pausado');
            break;

        case 'entry_resume':
            $id = $_POST['id'] ?? '';
            $stmt = $pdo->prepare("SELECT paused_at, paused_duration FROM time_entries WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            $entry = $stmt->fetch();
            if (!$entry || !$entry['paused_at']) jsonError('Não está pausado');
            
            $new_pause = $entry['paused_duration'] + (time() - strtotime($entry['paused_at']));
            $pdo->prepare("UPDATE time_entries SET paused_at = NULL, paused_duration = ? WHERE id = ?")->execute([$new_pause, $id]);
            jsonSuccess([], 'Retomado');
            break;

        case 'task_list':
            $pid = $_GET['project_id'];
            $stmt = $pdo->prepare("SELECT * FROM time_tasks WHERE project_id = ? AND user_id = ? AND is_active = 1");
            $stmt->execute([$pid, $user_id]);
            jsonSuccess(['tasks' => $stmt->fetchAll()]);
            break;

        default: jsonError('Ação inválida');
    }
} catch (Exception $e) { jsonError($e->getMessage(), 500); }
?>