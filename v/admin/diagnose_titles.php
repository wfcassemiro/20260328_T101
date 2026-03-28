<?php
set_time_limit(1800); // 30 minutos, pois precisa buscar todos os usuários
header('Content-Type: text/plain; charset=utf-8');

echo "--- INICIANDO DIAGNÓSTICO DE TÍTULOS ---\n";
echo "Isso pode demorar alguns minutos...\n\n";

require_once __DIR__ . '/../config/database.php';

// Configurações e Funções da API
define('HOTMART_BASIC_AUTH', 'Basic N2UzZDM0MmQtYWY0Zi00MTkwLTk1OWMtNmE5NzU0NmYxNDM3OjZmNjI1NzZmLTQzMzUtNDBkMC04N2FhLThhNThmMDlkZjdmZA==');
define('HOTMART_ACCESS_TOKEN_URL', 'https://api-sec-vlc.hotmart.com/security/oauth/token');
define('HOTMART_API_BASE_URL', 'https://developers.hotmart.com');
$hotmart_club_subdomain = 'assinaturapremiumplustranslato';
$csv_path = __DIR__ . '/mapeamento_final.csv';

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
    if ($curl_error) { throw new Exception("Erro de cURL (HTTP 0): {$curl_error}. URL: {$url}."); }
    if ($http_code !== 200) { throw new Exception("A chamada à API falhou. URL: {$url}. Código HTTP: {$http_code}. Resposta: " . ($response ?: 'Nenhuma resposta.')); }
    return json_decode($response, true);
}

function normalize_title($str) {
    return trim(mb_strtolower($str, 'UTF-8'));
}

try {
    if (!file_exists($csv_path)) {
        throw new Exception("Arquivo 'mapeamento_final.csv' não encontrado na pasta /admin/.");
    }

    $access_token = get_hotmart_access_token();

    // === ETAPA 1: CONSTRUIR CATÁLOGO DA HOTMART (Lógica do script funcional) ===
    echo "Etapa 1: Buscando catálogo de aulas da Hotmart...\n";
    $all_hotmart_users = [];
    $next_page_token = null;
    do {
        $params = ['subdomain' => $hotmart_club_subdomain, 'max_results' => 100];
        if ($next_page_token) $params['page_token'] = $next_page_token;
        $users_data = call_hotmart_api(HOTMART_API_BASE_URL . '/club/api/v1/users?' . http_build_query($params), $access_token);
        if (!empty($users_data['items'])) {
            $all_hotmart_users = array_merge($all_hotmart_users, $users_data['items']);
        }
        $next_page_token = $users_data['page_info']['next_page_token'] ?? null;
    } while ($next_page_token);
    
    if (empty($all_hotmart_users)) {
        throw new Exception('Nenhum usuário encontrado na Hotmart.');
    }

    $all_lessons_catalog = [];
    foreach ($all_hotmart_users as $user) {
        if (!isset($user['user_id'])) continue;
        $lessons_url = HOTMART_API_BASE_URL . "/club/api/v1/users/{$user['user_id']}/lessons?" . http_build_query(['subdomain' => $hotmart_club_subdomain]);
        $lessons_data = call_hotmart_api($lessons_url, $access_token);
        if (isset($lessons_data['lessons']) && !empty($lessons_data['lessons'])) {
            foreach ($lessons_data['lessons'] as $lesson) {
                if (isset($lesson['lesson_id']) && !isset($all_lessons_catalog[$lesson['lesson_id']])) {
                    $all_lessons_catalog[$lesson['lesson_id']] = $lesson['name'];
                }
            }
        }
    }
    
    if (empty($all_lessons_catalog)) {
        throw new Exception('Nenhuma aula encontrada no catálogo de usuários ativos.');
    }
    
    $hotmart_titles = array_values($all_lessons_catalog);
    sort($hotmart_titles);

    // === ETAPA 2: LER TÍTULOS DO CSV ===
    echo "Etapa 2: Lendo títulos do arquivo CSV...\n\n";
    $csv_titles = [];
    $handle = fopen($csv_path, "r");
    fgetcsv($handle); // Pular cabeçalho
    while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if (isset($row[1])) {
            $csv_titles[] = $row[1];
        }
    }
    fclose($handle);
    sort($csv_titles);

    // === ETAPA 3: IMPRIMIR RESULTADOS ===
    echo "===================================================================\n";
    echo " TÍTULOS REAIS ENCONTRADOS NA HOTMART (Total: " . count($hotmart_titles) . ")\n";
    echo "===================================================================\n";
    foreach ($hotmart_titles as $title) {
        echo $title . "\n";
    }

    echo "\n\n";
    echo "=================================================================\n";
    echo " TÍTULOS LIDOS DO SEU CSV (Coluna B) (Total: " . count($csv_titles) . ")\n";
    echo "=================================================================\n";
    foreach ($csv_titles as $title) {
        echo $title . "\n";
    }
    
    echo "\n\n--- FIM DO DIAGNÓSTICO ---\n";
    echo "Compare as duas listas. Para o mapeamento funcionar, os títulos do CSV devem ser idênticos (ou >70% similares) aos títulos da Hotmart.\n";

} catch (Exception $e) {
    echo "\n\n!!! ERRO NO DIAGNÓSTICO !!!\n";
    echo $e->getMessage();
}
exit;