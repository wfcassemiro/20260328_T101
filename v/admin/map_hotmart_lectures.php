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
define('HOTMART_API_BASE_URL', 'https://developers.hotmart.com');

function get_hotmart_access_token() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, HOTMART_ACCESS_TOKEN_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['grant_type' => 'client_credentials']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: ' . HOTMART_BASIC_AUTH, 'Content-Type: application/x-www-form-urlencoded']);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($error || $http_code !== 200) {
        return ['error' => "Falha ao obter token. HTTP Code: {$http_code}. Resposta: " . $response];
    }
    $data = json_decode($response, true);
    return $data['access_token'] ?? ['error' => 'Token de acesso não encontrado na resposta.'];
}

// **NOVA FUNÇÃO:** Extrai o código SxxExx do título
function get_episode_code($title) {
    if (preg_match('/(s\d{2}e\d{2})/i', $title, $matches)) {
        return strtolower($matches[1]);
    }
    return null; // Retorna nulo se não encontrar o padrão
}

$access_token = get_hotmart_access_token();
if (is_array($access_token)) {
    echo json_encode(['success' => false, 'message' => 'Erro de autenticação: ' . $access_token['error']]);
    exit;
}

$headers = ['Authorization: Bearer ' . $access_token, 'Content-Type: application/json'];

$mapped_count = 0;
$already_mapped_count = 0;
$not_found_count = 0;
$hotmart_modules = [];

try {
    // 1. Buscar palestras do BD local e mapear pelo código do episódio
    $stmt_local = $pdo->query("SELECT id, title FROM lectures");
    $local_lectures = $stmt_local->fetchAll(PDO::FETCH_ASSOC);
    $local_lectures_map = [];
    foreach ($local_lectures as $lecture) {
        $episode_code = get_episode_code($lecture['title']);
        if ($episode_code) {
            $local_lectures_map[$episode_code] = $lecture['id'];
        }
    }

    // 2. Buscar TODOS os módulos da Hotmart (com paginação)
    $hotmart_club_subdomain = 'assinaturapremiumplustranslato';
    $next_page_token = null;
    do {
        $query_params = ['subdomain' => $hotmart_club_subdomain, 'max_results' => 100];
        if ($next_page_token) $query_params['page_token'] = $next_page_token;
        
        // **CORREÇÃO: Usando o endpoint /modules que funcionou no teste**
        $modules_url = HOTMART_API_BASE_URL . '/club/api/v1/modules?' . http_build_query($query_params);
        
        $ch = curl_init($modules_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) throw new Exception("Falha ao buscar módulos da Hotmart. Código: {$http_code}");
        
        $data = json_decode($response, true);
        
        if (!empty($data)) {
            // A API retorna um array diretamente, não dentro de 'items'
            $hotmart_modules = array_merge($hotmart_modules, $data);
        }
        // A paginação para este endpoint pode ser diferente ou inexistente.
        // Por segurança, vamos assumir que não há paginação se a chave não vier.
        $next_page_token = $data['page_info']['next_page_token'] ?? null; 
    } while ($next_page_token);

    if (empty($hotmart_modules)) {
        throw new Exception('Nenhum módulo encontrado na Hotmart. A resposta da API estava vazia.');
    }

    // 3. Comparar e inserir no banco de dados
    foreach ($hotmart_modules as $module) {
        $hotmart_title = $module['name'];
        $hotmart_id = $module['module_id']; // ID do módulo
        
        // **LÓGICA DE MAPEAMENTO CORRIGIDA**
        $episode_code = get_episode_code($hotmart_title);

        if ($episode_code && isset($local_lectures_map[$episode_code])) {
            $lecture_id = $local_lectures_map[$episode_code];
            
            // Para o mapeamento, o ID da lição (page/class) é o que importa. Usamos o primeiro que aparece.
            if (isset($module['classes'][0])) {
                $hotmart_lesson_id = $module['classes'][0];

                $stmt_check = $pdo->prepare("SELECT lecture_id FROM hotmart_lecture_mapping WHERE hotmart_lesson_id = ?");
                $stmt_check->execute([$hotmart_lesson_id]);
                
                if ($stmt_check->fetch()) {
                    $already_mapped_count++;
                } else {
                    $stmt_insert = $pdo->prepare(
                        "INSERT INTO hotmart_lecture_mapping (lecture_id, hotmart_lesson_id, lecture_title, sync_enabled) VALUES (?, ?, ?, 1)"
                    );
                    $stmt_insert->execute([$lecture_id, $hotmart_lesson_id, $hotmart_title]);
                    $mapped_count++;
                }
            }
        } else {
            $not_found_count++;
        }
    }
    
    $message = "Mapeamento concluído! <br>Novos mapeamentos: {$mapped_count}. <br>Já existentes: {$already_mapped_count}. <br>Não encontradas/sem código: {$not_found_count}.";
    $_SESSION['admin_message'] = $message;
    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    $_SESSION['admin_error'] = 'Erro no mapeamento: ' . $e->getMessage();
    echo json_encode(['success' => false, 'message' => 'Erro no mapeamento: ' . $e->getMessage()]);
}
exit;