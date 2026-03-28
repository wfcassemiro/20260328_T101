<?php
// Script baseado no seu index (6).php, mas modificado para ser rápido
// e apenas listar os títulos das aulas.

set_time_limit(300); // 5 minutos (mas deve demorar segundos)
header('Content-Type: text/plain; charset=utf-8');
ob_implicit_flush(true);

echo "--- INICIANDO DIAGNÓSTICO FINAL (Baseado no index.php funcional) ---\n";
echo "A usar o token Basic N2Uz... que sabemos que funciona.\n\n";

// =============================================================================
// FUNÇÃO PARA FAZER CHAMADAS À API (do seu index.php)
// =============================================================================
function call_hotmart_api($url, $access_token = null) {
    $headers = ['Content-Type: application/json'];
    if ($access_token) {
        $headers[] = 'Authorization: Bearer ' . $access_token;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        return ['error' => false, 'body' => json_decode($response, true)];
    }
    return ['error' => true, 'http_code' => $http_code, 'response' => $response];
}

// =============================================================================
// FUNÇÃO PRINCIPAL (Modificada para ser rápida)
// =============================================================================
function fetch_lesson_list() {
    // --- Configurações (do seu index.php) ---
    $hotmart_club_subdomain = 'assinaturapremiumplustranslato';
    $basic_auth = 'Basic N2UzZDM0MmQtYWY0Zi00MTkwLTk1OWMtNmE5NzU0NmYxNDM3OjZmNjI1NzZmLTQzMzUtNDBkMC04N2FhLThhNThmMDlkZjdmZA==';

    // --- Parte 1: Obter Token (do seu index.php) ---
    $token_url = 'https://api-sec-vlc.hotmart.com/security/oauth/token';
    $ch_token = curl_init($token_url);
    curl_setopt($ch_token, CURLOPT_POST, 1);
    curl_setopt($ch_token, CURLOPT_POSTFIELDS, http_build_query(['grant_type' => 'client_credentials']));
    curl_setopt($ch_token, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_token, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded', 'Authorization: ' . $basic_auth]);
    $response_token = curl_exec($ch_token);
    $http_code_token = curl_getinfo($ch_token, CURLINFO_HTTP_CODE);
    curl_close($ch_token);

    if ($http_code_token !== 200) { 
        return ['error' => 'Falha ao obter token de acesso. Código: ' . $http_code_token . ' (O token Basic N2Uz... falhou!)']; 
    }
    $token_data = json_decode($response_token, true);
    $access_token = $token_data['access_token'] ?? null;
    if (!$access_token) { 
        return ['error' => 'Token de acesso não encontrado.']; 
    }
    echo "Access Token obtido com sucesso!\n";
    $bearer_auth = 'Authorization: Bearer ' . $access_token;


    // --- Parte 2: Buscar APENAS 1 Aluno (Modificação Rápida) ---
    $query_params = ['subdomain' => $hotmart_club_subdomain, 'max_results' => 1];
    $students_url = 'https://developers.hotmart.com/club/api/v1/users?' . http_build_query($query_params);
    $students_response = call_hotmart_api($students_url, $access_token);
    
    if ($students_response['error']) { 
        return ['error' => 'Falha ao buscar o primeiro aluno. Código: ' . $students_response['http_code']];
    }
    if (empty($students_response['body']['items'])) {
        return ['error' => 'Nenhum aluno encontrado na lista.'];
    }
    
    $first_student = $students_response['body']['items'][0];
    $user_id = $first_student['user_id'];
    echo "Utilizador de referência obtido: " . $first_student['email'] . " (ID: $user_id)\n";


    // --- Parte 3: Buscar o CATÁLOGO de Lições (do seu index.php) ---
    $all_lesson_titles = [];
    $lessons_url = 'https://developers.hotmart.com/club/api/v1/users/' . $user_id . '/lessons?subdomain=' . $hotmart_club_subdomain;
    $lessons_response = call_hotmart_api($lessons_url, $access_token);

    if ($lessons_response['error']) {
        return ['error' => "Erro ao buscar a lista de lições. Código: " . $lessons_response['http_code']];
    } 
    
    if (isset($lessons_response['body']['lessons']) && !empty($lessons_response['body']['lessons'])) {
        foreach ($lessons_response['body']['lessons'] as $lesson) {
            $all_lesson_titles[$lesson['page_id']] = $lesson['page_name']; // Usa page_name, como no seu script
        }
    }

    $hotmart_titles = array_values($all_lesson_titles);
    sort($hotmart_titles);
    return ['titles' => $hotmart_titles];
}

// =============================================================================
// EXECUTAR E IMPRIMIR
// =============================================================================
$data = fetch_lesson_list();
$hotmart_titles = $data['titles'] ?? [];
$error_message = $data['error'] ?? null;

if ($error_message) {
    echo "\nOcorreu um erro: " . $error_message . "\n";
} elseif (empty($hotmart_titles)) {
    echo "\nNenhum título foi retornado pela API.\n";
} else {
    echo "\n===================================================================\n";
    echo " TÍTULOS REAIS ENCONTRADOS NA HOTMART (Total: " . count($hotmart_titles) . ")\n";
    echo "===================================================================\n\n";
    foreach ($hotmart_titles as $title) {
        echo $title . "\n";
    }
}

echo "\n\n--- FIM DO DIAGNÓSTICO ---\n";
?>