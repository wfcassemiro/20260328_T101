<?php
set_time_limit(600);
session_start();
require_once __DIR__ . '/../config/database.php';

// Verificação de autenticação e função de acesso
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

// --- Configurações de Credenciais e API ---
define('HOTMART_BASIC_AUTH', 'Basic N2UzZDM0MmQtYWY0Zi00MTkwLTk1OWMtNmE5NzU0NmYxNDM3OjZmNjI1NzZmLTQzMzUtNDBkMC04N2FhLThhNThmMDlkZjdmZA==');
define('HOTMART_ACCESS_TOKEN_URL', 'https://api-sec-vlc.hotmart.com/security/oauth/token');
// HOST CORRETO: O host de segurança que funciona para o seu outro script
define('HOTMART_API_BASE_URL', 'https://api-sec-vlc.hotmart.com'); 

// ID do Produto e Subdomínio
define('HOTMART_PRODUCT_ID', '4304019');
$hotmart_club_subdomain = 'assinaturapremiumplustranslato';


// =============================================================================
// FUNÇÃO DE CHAMADA DE API (Copiada do seu arquivo de sucesso)
// =============================================================================
/**
 * Faz chamadas à API da Hotmart usando a lógica robusta.
 */
function call_hotmart_api($url, $access_token = null) {
    $headers = ['Content-Type: application/json'];
    if ($access_token) {
        $headers[] = 'Authorization: Bearer ' . $access_token;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => true, 
        CURLOPT_MAXREDIRS => 5 
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);
    curl_close($ch);

    if ($http_code === 200) {
        return ['error' => false, 'body' => json_decode($response, true)];
    }
    
    return [
        'error' => true, 
        'http_code' => $http_code, 
        'response' => $response,
        'curl_error' => $curl_error,
        'curl_errno' => $curl_errno
    ];
}


// --- Funções de Autenticação ---

/**
 * Obtém o token de acesso da Hotmart usando Basic Auth.
 * @return string|false Token de acesso ou false em caso de falha.
 */
function get_hotmart_access_token() {
    $ch = curl_init(HOTMART_ACCESS_TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'client_credentials']),
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . HOTMART_BASIC_AUTH, 
            'Content-Type: application/x-www-form-urlencoded'
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $data = json_decode($response, true);
        return $data['access_token'] ?? false;
    }
    
    if ($http_code === 400 || $http_code === 401) {
        die("<h2>Erro de Credencial (Basic Auth)</h2><p>O servidor Hotmart rejeitou o Basic Auth. Verifique se a chave de Basic Auth está correta e atualizada.</p>");
    }
    
    error_log("Hotmart Token Error: HTTP $http_code | Response: " . $response);
    return false;
}

// --------------------------------------------------------------------------
// LÓGICA PRINCIPAL
// --------------------------------------------------------------------------

// 1. Obter token
$access_token = get_hotmart_access_token();
if (!$access_token) {
    die("<h2>Erro de Autenticação Hotmart</h2><p>Não foi possível obter o token de acesso da Hotmart. Verifique as credenciais Basic Auth e tente novamente.</p>");
}

// 2. Obter lições da Hotmart Club
// Usando o endpoint V1 de MÓDULOS no Host de Segurança (o mais provável para funcionar)
$api_url = HOTMART_API_BASE_URL . "/club/api/v1/modules?subdomain=" . urlencode($hotmart_club_subdomain);

$api_call_result = call_hotmart_api($api_url, $access_token);
$hotmart_lessons_response = $api_call_result['body'] ?? null;
$raw_json_output = json_encode($hotmart_lessons_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);


if ($api_call_result['error']) {
    $error_message = "Código HTTP: " . $api_call_result['http_code'] . ". ";
    if ($api_call_result['curl_errno'] !== 0) {
        $error_message .= "Erro cURL (" . $api_call_result['curl_errno'] . "): " . $api_call_result['curl_error'] . ". ";
    }
    
    $output = "<h2>Erro de API da Hotmart Club</h2><p>Falha ao buscar lições. " . $error_message . "</p>";
    $output .= "<p>Resposta Bruta da API:</p><pre>" . htmlspecialchars($api_call_result['response']) . "</pre>";
    $output .= "<p>O Host de segurança/Token não aceitou o endpoint do Club. Verifique o URL no seu arquivo de sucesso e tente novamente.</p>";
    
    die($output); 
}


// 3. Processar a resposta e obter os módulos da Hotmart
$hotmart_lessons = [];
$total_pages_count = 0;

if (is_array($hotmart_lessons_response)) {
    // Tenta extrair de todas as estruturas conhecidas
    if (isset($hotmart_lessons_response['items']) && is_array($hotmart_lessons_response['items'])) {
        $hotmart_lessons = $hotmart_lessons_response['items'];
    } elseif (isset($hotmart_lessons_response['data']) && is_array($hotmart_lessons_response['data'])) {
        if (isset($hotmart_lessons_response['data']['modules']) && is_array($hotmart_lessons_response['data']['modules'])) {
            $hotmart_lessons = $hotmart_lessons_response['data']['modules'];
        } elseif (isset($hotmart_lessons_response['data']['items']) && is_array($hotmart_lessons_response['data']['items'])) {
            $hotmart_lessons = $hotmart_lessons_response['data']['items'];
        } else {
             $hotmart_lessons = $hotmart_lessons_response['data'];
        }
    } else {
        // Assume que a resposta é diretamente a lista de módulos (V2)
        $hotmart_lessons = $hotmart_lessons_response;
    }
}


// 4. Obter lições locais já mapeadas do BD
try {
    $stmt = $pdo->query("SELECT hotmart_page_id FROM lectures WHERE hotmart_page_id IS NOT NULL");
    $mapped_lessons_db = $stmt->fetchAll(PDO::FETCH_COLUMN); 
    $mapped_page_ids = array_map('strval', $mapped_lessons_db);
} catch (PDOException $e) {
    die("Erro ao consultar o banco de dados para lições mapeadas: " . $e->getMessage());
}

// 5. Filtrar lições da Hotmart Club que AINDA NÃO estão mapeadas
$unmapped_lessons = [];

if (is_array($hotmart_lessons)) {
    foreach ($hotmart_lessons as $module) {
        if (isset($module['pages']) && is_array($module['pages'])) {
            foreach ($module['pages'] as $lesson) {
                $total_pages_count++;
                
                $page_id = (string)($lesson['pageId'] ?? null);
                $lesson_title = $lesson['title'] ?? 'Título Desconhecido';
                $lesson_code = (string)($lesson['lessonCode'] ?? $page_id); 

                if ($page_id && !in_array($page_id, $mapped_page_ids)) {
                    $unmapped_lessons[] = [
                        'module_id' => (string)($module['id'] ?? 'N/A'),
                        'title' => $lesson_title,
                        'page_id' => $page_id,
                        'lesson_id' => $lesson_code 
                    ];
                }
            }
        }
    }
}


// 6. Obter lições locais para o dropdown (aquelas sem mapeamento hotmart)
try {
    $stmt = $pdo->query("SELECT id, title FROM lectures WHERE hotmart_page_id IS NULL ORDER BY title ASC");
    $local_lectures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao consultar o banco de dados para lições locais: " . $e->getMessage());
}

// HTML DE EXIBIÇÃO
$page_title = "Mapeamento Manual de Aulas Hotmart";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #004d40; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        select { padding: 8px; border-radius: 4px; border: 1px solid #ccc; width: 100%; box-sizing: border-box; }
        button { 
            padding: 10px 15px; background-color: #4db6ac; color: white; border: none; 
            border-radius: 4px; cursor: pointer; transition: background-color 0.3s;
        }
        button:hover { background-color: #004d40; }
        button:disabled { background-color: #ccc; cursor: not-allowed; }
        .loading { background-color: #ff9800; }
        .saved-row { background-color: #e0f7fa !important; }
        .alert-message { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert-info { background-color: #e3f2fd; color: #1565c0; border: 1px solid #90caf9; }
        .alert-warning { background-color: #fff3e0; color: #ef6c00; border: 1px solid #ffcc80; }
        pre { background-color: #eee; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 0.9em; text-align: left; }
    </style>
</head>
<body>

<div class="container">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>

    <?php if ($total_pages_count === 0): ?>
        <div class="alert-warning alert-message">
            A API da Hotmart retornou dados, mas a lista de páginas/aulas está vazia (<?php echo count($hotmart_lessons); ?> módulos no nível superior). Isso indica que **o subdomínio** (<?php echo htmlspecialchars($hotmart_club_subdomain); ?>) está incorreto ou a API não retornou módulos na estrutura que o script espera.
        </div>
        
        <?php if (!empty($hotmart_lessons_response)): ?>
            <h2>Resposta JSON Bruta da API:</h2>
            <pre style="color: #d35400; border: 1px solid #f1c40f;"><?php echo htmlspecialchars($raw_json_output); ?></pre>
            <p>Se o JSON acima **não for vazio**, o problema está na extração. Caso contrário, o problema é o subdomínio.</p>
        <?php else: ?>
             <p>A API retornou uma resposta vazia (<code>null</code> ou <code>false</code>) ou não retornou módulos no nível superior. Tente a solução de subdomínio.</p>
        <?php endif; ?>
        
    <?php elseif (empty($unmapped_lessons)): ?>
        <div class="alert-info alert-message">
            ✓ Todas as lições da Hotmart Club parecem estar mapeadas no seu banco de dados. Total de lições na Hotmart: <?php echo $total_pages_count; ?>.
        </div>
    <?php else: ?>
        <h2>Lições da Hotmart não mapeadas (Total: <?php echo count($unmapped_lessons); ?>)</h2>
        <p>Associe cada lição Hotmart à sua respectiva palestra local (palestras sem *ID da Página Hotmart*).</p>
        
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Lição Hotmart (Título Club)</th>
                        <th>ID da Página Hotmart</th>
                        <th>Lição Local para Mapear</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($local_lectures)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center;" class="alert-warning">
                                Não há palestras locais disponíveis no seu BD para mapeamento (coluna 'hotmart_page_id' está NULL). Crie as palestras na tabela `lectures` primeiro.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($unmapped_lessons as $lesson): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($lesson['title']); ?></td>
                                <td><?php echo htmlspecialchars($lesson['page_id']); ?></td>
                                <td>
                                    <select name="local_lecture_id">
                                        <option value="">-- Selecione a Palestra Local --</option>
                                        <?php foreach ($local_lectures as $local): ?>
                                            <option value="<?php echo htmlspecialchars($local['id']); ?>">
                                                <?php echo htmlspecialchars($local['title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <button 
                                        onclick="saveMapping(this)"
                                        data-hotmart-page-id="<?php echo htmlspecialchars($lesson['page_id']); ?>"
                                        data-hotmart-lesson-id="<?php echo htmlspecialchars($lesson['lesson_id']); ?>"
                                        data-hotmart-module-id="<?php echo htmlspecialchars($lesson['module_id']); ?>"
                                        data-hotmart-title="<?php echo htmlspecialchars($lesson['title']); ?>"
                                    >
                                        Salvar Mapeamento
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function saveMapping(button) {
    const row = button.closest('tr');
    const select = row.querySelector('select');
    const localId = select.value;

    if (!localId) {
        alert('Por favor, selecione uma palestra local para associar.');
        return;
    }

    button.disabled = true;
    button.classList.add('loading');

    const formData = new FormData();
    formData.append('hotmart_page_id', button.dataset.hotmartPageId);
    formData.append('hotmart_lesson_id', button.dataset.hotmartLessonId);
    formData.append('hotmart_module_id', button.dataset.hotmartModuleId);
    formData.append('hotmart_title', button.dataset.hotmartTitle);
    formData.append('lecture_id', localId);

    // NOTA: O script espera uma API REST em 'save_mapping.php' para processar o mapeamento.
    fetch('save_mapping.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.innerHTML = 'Salvo!';
            row.classList.add('saved-row');
            // Desativa a lista após salvar para evitar novos cliques
            select.disabled = true; 
        } else {
            alert('Erro: ' + data.message);
            button.disabled = false;
            button.classList.remove('loading');
        }
    })
    .catch(error => {
        alert('Erro de conexão. Tente novamente.');
        button.disabled = false;
        button.classList.remove('loading');
    });
}
</script>

</body>
</html>