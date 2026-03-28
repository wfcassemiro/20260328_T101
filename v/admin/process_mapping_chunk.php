<?php
set_time_limit(240);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

// Configurações e Funções da API
define('HOTMART_BASIC_AUTH', 'Basic N2UzZDM0MmQtYWY0Zi00MTkwLTk1OWMtNmE5NzU0NmYxNDM3OjZmNjI1NzZmLTQzMzUtNDBkMC04N2FhLThhNThmMDlkZjdmZA==');
define('HOTMART_ACCESS_TOKEN_URL', 'https://api-sec-vlc.hotmart.com/security/oauth/token');
define('HOTMART_API_BASE_URL', 'https://developers.hotmart.com');
$hotmart_club_subdomain = 'assinaturapremiumplustranslato';
$catalog_file = __DIR__ . '/hotmart_lesson_catalog.json';
$csv_path = __DIR__ . '/mapeamento_final.csv';
$report_file = __DIR__ . '/mapping_report.json'; 

function get_hotmart_access_token() {
    $ch = curl_init(HOTMART_ACCESS_TOKEN_URL);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => 1, CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'client_credentials']), CURLOPT_HTTPHEADER => ['Authorization: ' . HOTMART_BASIC_AUTH, 'Content-Type: application/x-www-form-urlencoded'], CURLOPT_CONNECTTIMEOUT => 30, CURLOPT_TIMEOUT => 120]);
    $response = curl_exec($ch); $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $curl_error = curl_error($ch); curl_close($ch);
    if ($http_code !== 200) { throw new Exception("Falha na autenticação. Código HTTP: {$http_code}. Erro cURL: {$curl_error}. Resposta: {$response}"); }
    return json_decode($response, true)['access_token'] ?? null;
}

function call_hotmart_api($url, $access_token) {
    if (empty($url) || strpos($url, 'https://') !== 0) { throw new Exception("Tentativa de chamada de API com URL malformada: '{$url}'"); }
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $access_token, 'Content-Type: application/json'], CURLOPT_CONNECTTIMEOUT => 30, CURLOPT_TIMEOUT => 120, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
    $response = curl_exec($ch); $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $curl_error = curl_error($ch); curl_close($ch);
    if ($curl_error) { throw new Exception("Erro de cURL (HTTP 0): {$curl_error}. Isso indica um problema de SSL/TLS ou rede no seu servidor. URL: {$url}."); }
    if ($http_code !== 200) { throw new Exception("A chamada à API falhou. URL: {$url}. Código HTTP: {$http_code}. Resposta: " . ($response ?: 'Nenhuma resposta.')); }
    return json_decode($response, true);
}

function normalize_title($str) {
    return trim(mb_strtolower($str, 'UTF-8'));
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'clear':
            $pdo->exec("TRUNCATE TABLE hotmart_lecture_mapping");
            if(file_exists($catalog_file)) unlink($catalog_file);
            if(file_exists($report_file)) unlink($report_file);
            file_put_contents($report_file, json_encode(['mapped' => 0, 'unmatched' => 0])); // Zera o relatório
            echo json_encode(['success' => true]);
            break;

        case 'build_catalog':
            build_catalog($pdo);
            break;

        case 'map_csv':
            map_csv($pdo);
            break;

        default:
            throw new Exception('Ação inválida.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;


function build_catalog($pdo) {
    global $catalog_file, $hotmart_club_subdomain;
    $access_token = get_hotmart_access_token();
    $page_token = $_POST['page_token'] ?? null;
    $params = ['subdomain' => $hotmart_club_subdomain, 'max_results' => 50];
    if ($page_token) $params['page_token'] = $page_token;
    
    $url_para_chamar = HOTMART_API_BASE_URL . '/club/api/v1/users?' . http_build_query($params);
    $users_data = call_hotmart_api($url_para_chamar, $access_token);
    
    $catalog = file_exists($catalog_file) ? json_decode(file_get_contents($catalog_file), true) : [];
    if (!empty($users_data['items'])) {
        foreach ($users_data['items'] as $user) {
            if (!isset($user['user_id'])) continue;
            $lessons_url = HOTMART_API_BASE_URL . "/club/api/v1/users/{$user['user_id']}/lessons?" . http_build_query(['subdomain' => $hotmart_club_subdomain]);
            $lessons_data = call_hotmart_api($lessons_url, $access_token);
            if (isset($lessons_data['lessons']) && !empty($lessons_data['lessons'])) {
                foreach ($lessons_data['lessons'] as $lesson) {
                    if (isset($lesson['lesson_id']) && !isset($catalog[$lesson['lesson_id']])) {
                        $catalog[$lesson['lesson_id']] = $lesson;
                    }
                }
            }
        }
    }
    file_put_contents($catalog_file, json_encode($catalog));
    $next_page_token = $users_data['page_info']['next_page_token'] ?? null;
    if ($next_page_token) {
        echo json_encode(['success' => true, 'done' => false, 'progress' => 25, 'message' => 'Construindo catálogo de aulas...', 'next_action' => 'build_catalog', 'next_page_token' => $next_page_token]);
    } else {
        echo json_encode(['success' => true, 'done' => false, 'progress' => 50, 'message' => 'Catálogo construído. Iniciando mapeamento.', 'next_action' => 'map_csv', 'next_page_token' => 0]);
    }
}


function map_csv($pdo) {
    global $catalog_file, $csv_path, $report_file;
    
    if (!file_exists($catalog_file)) throw new Exception('Arquivo de catálogo não encontrado.');
    if (!file_exists($csv_path)) throw new Exception('Arquivo CSV não encontrado.');

    $report = json_decode(file_get_contents($report_file), true);
    $catalog = json_decode(file_get_contents($catalog_file), true);
    $hotmart_pages_map = [];
    foreach ($catalog as $lesson) {
        if (isset($lesson['page_id']) && isset($lesson['name'])) {
            $hotmart_pages_map[normalize_title($lesson['name'])] = ['module_id' => $lesson['module_id'] ?? null, 'lesson_id' => $lesson['lesson_id'] ?? null, 'page_id' => $lesson['page_id']];
        }
    }
    
    $stmt_local = $pdo->query("SELECT id, title FROM lectures");
    $local_lectures_map = [];
    foreach ($stmt_local->fetchAll(PDO::FETCH_ASSOC) as $lecture) {
        $local_lectures_map[normalize_title($lecture['title'])] = $lecture['id'];
    }

    $csv_lines = file($csv_path, FILE_IGNORE_NEW_LINES);
    $total_rows = count($csv_lines) - 1;
    if ($total_rows <= 0) throw new Exception("Arquivo CSV está vazio ou contém apenas o cabeçalho.");
    
    $start_row = (int)($_POST['page_token'] ?? 0);
    $chunk_size = 25;
    
    $rows_to_process = array_slice($csv_lines, $start_row + 1, $chunk_size);
    
    $pdo->beginTransaction();
    $stmt_insert = $pdo->prepare("INSERT INTO hotmart_lecture_mapping (lecture_id, hotmart_module_id, hotmart_lesson_id, hotmart_page_id, lecture_title) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($rows_to_process as $line) {
        $row = str_getcsv($line, ","); // Usando vírgula como separador
        if(count($row) < 2) continue;
        
        $local_title_from_csv = normalize_title($row[0]);
        $hotmart_title_from_csv = normalize_title($row[1]);

        // 1. Verifica se o título local do CSV existe no seu banco
        if (!isset($local_lectures_map[$local_title_from_csv])) {
            $report['unmatched']++;
            continue; 
        }
        $local_id = $local_lectures_map[$local_title_from_csv];
        
        // 2. Verifica se esta palestra local já foi mapeada em um chunk anterior
        $stmt_check_local = $pdo->prepare("SELECT lecture_id FROM hotmart_lecture_mapping WHERE lecture_id = ?");
        $stmt_check_local->execute([$local_id]);
        if ($stmt_check_local->fetch()) {
            continue; // Já mapeado, pular
        }

        // *** LÓGICA DE FUZZY MATCHING (70%) ***
        $best_match_percent = 0;
        $best_match_info = null;

        foreach ($hotmart_pages_map as $hotmart_api_title => $hotmart_info) {
            similar_text($hotmart_title_from_csv, $hotmart_api_title, $percent);
            
            if ($percent > $best_match_percent) {
                $best_match_percent = $percent;
                $best_match_info = $hotmart_info;
            }
        }

        // 3. Verifica se a melhor correspondência encontrada é boa o suficiente (> 70%)
        if ($best_match_percent > 70) {
            
            // 4. Verifica se esta aula da Hotmart já foi reivindicada por outra palestra
            $stmt_check_hotmart = $pdo->prepare("SELECT hotmart_page_id FROM hotmart_lecture_mapping WHERE hotmart_page_id = ?");
            $stmt_check_hotmart->execute([$best_match_info['page_id']]);
            if ($stmt_check_hotmart->fetch()) {
                // Aula já mapeada para outra palestra. Marcar esta como não correspondida.
                $report['unmatched']++;
                continue;
            }
            
            // SUCESSO! Mapeamento encontrado e validado.
            $stmt_insert->execute([
                $local_id, 
                $best_match_info['module_id'], 
                $best_match_info['lesson_id'],
                $best_match_info['page_id'],
                $row[1] // Título original do CSV
            ]);
            $report['mapped']++;
        } else {
            // A melhor correspondência foi abaixo de 70%
            $report['unmatched']++;
        }
    }
    $pdo->commit();
    file_put_contents($report_file, json_encode($report));
    
    $processed_rows = $start_row + count($rows_to_process);
    $progress = 50 + round(($processed_rows / $total_rows) * 50);

    if ($processed_rows < $total_rows) {
        echo json_encode([
            'success' => true,
            'done' => false,
            'progress' => $progress,
            'message' => "Processando CSV... ({$processed_rows}/{$total_rows})",
            'next_action' => 'map_csv',
            'next_page_token' => $processed_rows
        ]);
    } else {
        unlink($catalog_file); 
        unlink($report_file); 
        $final_message = "Mapeamento concluído! Mapeamentos criados: {$report['mapped']}. Linhas não correspondidas: {$report['unmatched']}.";
        echo json_encode([
            'success' => true,
            'done' => true,
            'progress' => 100,
            'message' => $final_message
        ]);
    }
}