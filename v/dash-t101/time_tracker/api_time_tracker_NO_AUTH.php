<?php
/**
 * API Time Tracker - VERSÃO SEM AUTENTICAÇÃO (APENAS PARA TESTES)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0); // Desativar output de erros no corpo para não quebrar JSON
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Tentativa robusta de incluir configurações
// Procura arquivos de config em vários lugares comuns
$config_paths = [
    __DIR__ . '/config/',
    __DIR__ . '/../config/', // Se estiver em subpasta
    $_SERVER['DOCUMENT_ROOT'] . '/dash-t101/config/'
];

$loaded = false;
foreach ($config_paths as $path) {
    if (file_exists($path . 'database.php')) {
        require_once $path . 'database.php';
        
        if (file_exists($path . 'dash_database.php')) {
            require_once $path . 'dash_database.php';
        }
        
        if (file_exists($path . 'dash_functions.php')) {
            require_once $path . 'dash_functions.php';
        }
        
        $loaded = true;
        break;
    }
}

header('Content-Type: application/json; charset=UTF-8');

if (!$loaded) {
    die(json_encode([
        'success' => false,
        'error' => 'Erro Crítico: Arquivos de configuração (database.php) não encontrados. Verifique se a pasta /config/ existe.'
    ]));
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

// ⚠️ FAKE USER ID PARA TESTES SE NENHUM ESTIVER LOGADO
if (!$user_id) {
    try {
        if (isset($pdo)) {
            $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
            $user = $stmt->fetch();
            if ($user) {
                $user_id = $user['id'];
            }
        }
    } catch (Exception $e) {
        // Silencioso
    }
    
    if (!$user_id) {
        // Último recurso
        $user_id = 1; 
    }
}

/**
 * FUNÇÕES AUXILIARES (Redefinidas caso include falhe)
 */
if (!function_exists('jsonSuccess')) {
    function jsonSuccess($data = [], $message = '') {
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            // Campos de compatibilidade
            'projects' => $data['projects'] ?? null,
            'entries'  => $data['entries'] ?? null,
            'tasks'    => $data['tasks'] ?? null,
            'entry'    => $data['entry'] ?? null,
            'duration_formatted' => $data['duration_formatted'] ?? null
        ]);
        exit;
    }
}

if (!function_exists('jsonError')) {
    function jsonError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error'   => $message
        ]);
        exit;
    }
}

if (!function_exists('formatDuration')) {
    function formatDuration($seconds) {
        $seconds = (int)$seconds;
        $hours   = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs    = $seconds % 60;
        return str_pad($hours, 2, '0', STR_PAD_LEFT) . ':' .
               str_pad($minutes, 2, '0', STR_PAD_LEFT) . ':' .
               str_pad($secs, 2, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('sanitizeInput')) {
    function sanitizeInput($value) {
        return htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('generateUUID')) {
    function generateUUID() {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

// PROCESSAR AÇÕES
try {
    switch ($action) {
        case 'project_list':
            $stmt = $pdo->prepare("
                SELECT id, title as name, client_id, '#7B61FF' as color, status 
                FROM dash_projects WHERE user_id = ? ORDER BY created_at DESC LIMIT 50
            ");
            $stmt->execute([$user_id]);
            $projects = $stmt->fetchAll();
            
            // Adicionar contagens básicas se tabelas existirem
            foreach ($projects as &$project) {
                $project['task_count'] = 0;
                $project['entry_count'] = 0;
                $project['duration_formatted'] = '00:00:00';
            }
            
            jsonSuccess(['projects' => $projects]);
            break;
            
        case 'project_create_quick':
            $name = sanitizeInput($_POST['name'] ?? '');
            if (empty($name)) jsonError('Nome obrigatório');
            
            $stmt = $pdo->prepare("INSERT INTO dash_projects (user_id, title, client_id, status, created_at) VALUES (?, ?, 0, 'in_progress', NOW())");
            $stmt->execute([$user_id, $name]);
            
            jsonSuccess(['project_id' => $pdo->lastInsertId()], 'Projeto criado');
            break;
            
        case 'entry_list':
            // Query simplificada para evitar erros de JOIN complexos se tabelas faltarem
            $stmt = $pdo->prepare("
                SELECT e.*, p.title as project_name 
                FROM time_entries e 
                LEFT JOIN dash_projects p ON e.project_id = p.id 
                WHERE e.user_id = ? 
                ORDER BY e.start_time DESC LIMIT 50
            ");
            $stmt->execute([$user_id]);
            $entries = $stmt->fetchAll();
            
            foreach ($entries as &$entry) {
                $entry['duration_formatted'] = formatDuration($entry['duration'] ?? 0);
            }
            
            jsonSuccess(['entries' => $entries]);
            break;

        case 'entry_running':
            $stmt = $pdo->prepare("SELECT * FROM time_entries WHERE user_id = ? AND is_running = 1 LIMIT 1");
            $stmt->execute([$user_id]);
            $entry = $stmt->fetch();
            
            if ($entry) {
                $now = new DateTime();
                $start = new DateTime($entry['start_time']);
                $elapsed = $now->getTimestamp() - $start->getTimestamp();
                $entry['duration'] = $elapsed - ($entry['paused_duration'] ?? 0);
                $entry['duration_formatted'] = formatDuration($entry['duration']);
            }
            
            jsonSuccess(['entry' => $entry]);
            break;
            
        case 'entry_start':
            // Verificar timer ativo
            $stmt = $pdo->prepare("SELECT id FROM time_entries WHERE user_id = ? AND is_running = 1");
            $stmt->execute([$user_id]);
            if ($stmt->fetch()) jsonError('Já existe um timer rodando');
            
            $id = generateUUID();
            $desc = sanitizeInput($_POST['description'] ?? '');
            $pid = $_POST['project_id'] ?? null;
            $tid = $_POST['task_id'] ?? null;
            
            $stmt = $pdo->prepare("INSERT INTO time_entries (id, user_id, project_id, task_id, description, start_time, is_running) VALUES (?, ?, ?, ?, ?, NOW(), 1)");
            $stmt->execute([$id, $user_id, $pid, $tid, $desc]);
            
            jsonSuccess(['entry_id' => $id], 'Iniciado');
            break;

        case 'entry_stop':
            $id = $_POST['id'] ?? '';
            $stmt = $pdo->prepare("SELECT * FROM time_entries WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            $entry = $stmt->fetch();
            
            if (!$entry) jsonError('Registro não encontrado');
            
            $end = new DateTime();
            $start = new DateTime($entry['start_time']);
            $duration = $end->getTimestamp() - $start->getTimestamp() - ($entry['paused_duration'] ?? 0);
            
            $stmt = $pdo->prepare("UPDATE time_entries SET end_time = NOW(), duration = ?, is_running = 0 WHERE id = ?");
            $stmt->execute([$duration, $id]);
            
            jsonSuccess(['duration_formatted' => formatDuration($duration)], 'Parado');
            break;
            
        default:
            // Action vazia ou desconhecida? Retorna lista de projetos para teste
            if (empty($action)) {
                 jsonError('Ação não definida na API');
            }
            jsonError('Ação desconhecida: ' . $action);
    }
    
} catch (Exception $e) {
    jsonError('Erro no servidor: ' . $e->getMessage(), 500);
}