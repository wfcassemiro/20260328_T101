<?php
set_time_limit(600);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

define('HOTMART_BASIC_AUTH', 'Basic N2UzZDM0MmQtYWY0Zi00MTkwLTk1OWMtNmE5NzU0NmYxNDM3OjZmNjI1NzZmLTQzMzUtNDBkMC04N2FhLThhNThmMDlkZjdmZA==');
define('HOTMART_ACCESS_TOKEN_URL', 'https://api-sec-vlc.hotmart.com/security/oauth/token');
define('HOTMART_API_BASE_URL', 'https://developers.hotmart.com'); // Corrigido de volta para o correto
$hotmart_club_subdomain = 'assinaturapremiumplustranslato';

function get_hotmart_access_token() {
    $ch = curl_init(HOTMART_ACCESS_TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'client_credentials']),
        CURLOPT_HTTPHEADER => ['Authorization: ' . HOTMART_BASIC_AUTH, 'Content-Type: application/x-www-form-urlencoded']
    ]);
    $response = curl_exec($ch); $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($http_code !== 200) return null;
    return json_decode($response, true)['access_token'] ?? null;
}

function call_hotmart_api($url, $access_token) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $access_token, 'Content-Type: application/json']
    ]);
    $response = curl_exec($ch); $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($http_code !== 200) return null;
    return json_decode($response, true);
}

function normalize_title($str) {
    return trim(mb_strtolower($str, 'UTF-8'));
}

try {
    $csv_path = __DIR__ . '/mapeamento_final.csv';
    if (!file_exists($csv_path)) {
        throw new Exception("Arquivo 'mapeamento_final.csv' não encontrado na pasta /admin/.");
    }

    $access_token = get_hotmart_access_token();
    if (!$access_token) throw new Exception("Falha na autenticação com a Hotmart.");

    // 1. Buscar todas as palestras locais
    $stmt_local = $pdo->query("SELECT id, title FROM lectures");
    $local_lectures_map = [];
    foreach ($stmt_local->fetchAll(PDO::FETCH_ASSOC) as $lecture) {
        $local_lectures_map[normalize_title($lecture['title'])] = $lecture['id'];
    }
    
    // 2. Buscar TODAS as aulas/páginas da Hotmart
    $hotmart_pages_map = [];
    $next_page_token = null;
    do {
        $params = ['subdomain' => $hotmart_club_subdomain, 'max_results' => 100];
        if ($next_page_token) $params['page_token'] = $next_page_token;
        
        $pages_data = call_hotmart_api(HOTMART_API_BASE_URL . '/club/api/v1/pages?' . http_build_query($params), $access_token);
        
        if (!empty($pages_data['items'])) {
            foreach ($pages_data['items'] as $page) {
                if (isset($page['id']) && isset($page['name'])) {
                    $hotmart_pages_map[normalize_title($page['name'])] = [
                        'module_id' => $page['module_id'],
                        'lesson_id' => $page['lesson_id'] ?? null,
                        'page_id' => $page['id']
                    ];
                }
            }
        }
        $next_page_token = $pages_data['page_info']['next_page_token'] ?? null;
    } while ($next_page_token);

    if (empty($hotmart_pages_map)) throw new Exception('Nenhuma página/aula retornada pela API da Hotmart. Verifique as permissões da sua credencial da API.');

    // 3. Buscar mapeamentos existentes
    $stmt_mapped = $pdo->query("SELECT lecture_id FROM hotmart_lecture_mapping WHERE lecture_id IS NOT NULL");
    $mapped_lecture_ids = $stmt_mapped->fetchAll(PDO::FETCH_COLUMN, 0);

    // 4. Ler o CSV e processar
    $pdo->beginTransaction();
    $stmt_insert = $pdo->prepare(
        "INSERT INTO hotmart_lecture_mapping (lecture_id, hotmart_module_id, hotmart_lesson_id, hotmart_page_id, lecture_title) VALUES (?, ?, ?, ?, ?)"
    );

    $mapped_count = 0; $already_mapped_count = 0; $unmatched_count = 0;
    
    $handle = fopen($csv_path, "r");
    fgetcsv($handle); // Pular cabeçalho

    while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if(count($row) < 2) continue;
        
        $local_title_from_csv = normalize_title($row[0]);
        $hotmart_title_from_csv = normalize_title($row[1]);

        if (isset($local_lectures_map[$local_title_from_csv]) && isset($hotmart_pages_map[$hotmart_title_from_csv])) {
            $local_id = $local_lectures_map[$local_title_from_csv];
            $hotmart_info = $hotmart_pages_map[$hotmart_title_from_csv];
            
            if (in_array($local_id, $mapped_lecture_ids)) {
                $already_mapped_count++;
            } else {
                $stmt_insert->execute([
                    $local_id, 
                    $hotmart_info['module_id'], 
                    $hotmart_info['lesson_id'],
                    $hotmart_info['page_id'],
                    $row[1] // Título original da Hotmart
                ]);
                $mapped_count++;
                $mapped_lecture_ids[] = $local_id;
            }
        } else {
            $unmatched_count++;
        }
    }
    fclose($handle);
    $pdo->commit();

    $message = "Mapeamento final concluído!<br>Novos mapeamentos: {$mapped_count}.<br>Já existentes: {$already_mapped_count}.<br>Não encontrados/Incompatíveis: {$unmatched_count}.";
    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erro no mapeamento: ' . $e->getMessage()]);
}
exit;