<?php
// Aumenta o tempo máximo de execução para garantir que o script não seja interrompido
set_time_limit(300);

// =============================================================================
// FUNÇÃO PARA FAZER CHAMADAS À API
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
    
    // Retorna a resposta completa para diagnóstico
    return ['http_code' => $http_code, 'body' => json_decode($response, true)];
}

// =============================================================================
// LÓGICA PRINCIPAL DE DIAGNÓSTICO
// =============================================================================
function inspect_lessons_data() {
    // --- Configurações ---
    $hotmart_club_subdomain = 'assinaturapremiumplustranslato';
    $basic_auth = 'Basic N2UzZDM0MmQtYWY0Zi00MTkwLTk1OWMtNmE5NzU0NmYxNDM3OjZmNjI1NzZmLTQzMzUtNDBkMC04N2FhLThhNThmMDlkZjdmZA==';

    // --- Parte 1: Obter Token ---
    $token_url = 'https://api-sec-vlc.hotmart.com/security/oauth/token';
    $ch_token = curl_init($token_url);
    curl_setopt($ch_token, CURLOPT_POST, 1);
    curl_setopt($ch_token, CURLOPT_POSTFIELDS, http_build_query(['grant_type' => 'client_credentials']));
    curl_setopt($ch_token, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_token, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded', 'Authorization: ' . $basic_auth]);
    $response_token = curl_exec($ch_token);
    curl_close($ch_token);
    $token_data = json_decode($response_token, true);
    $access_token = $token_data['access_token'] ?? null;
    if (!$access_token) { die("Falha crítica ao obter o token de acesso."); }

    // --- Parte 2: Buscar Alunos ---
    $students_url = 'https://developers.hotmart.com/club/api/v1/users?subdomain=' . $hotmart_club_subdomain . '&max_results=100';
    $students_response = call_hotmart_api($students_url, $access_token);
    $students = $students_response['body']['items'] ?? [];
    if (empty($students)) { die("Nenhum aluno encontrado para inspecionar."); }

    // --- Parte 3: Encontrar o primeiro aluno com progresso e buscar suas lições ---
    foreach ($students as $student) {
        $has_progress = isset($student['progress']['completed']) && $student['progress']['completed'] > 0;
        
        if ($has_progress && isset($student['user_id'])) {
            $user_id = $student['user_id'];
            $lessons_url = 'https://developers.hotmart.com/club/api/v1/users/' . $user_id . '/lessons?subdomain=' . $hotmart_club_subdomain;
            
            // Faz a chamada para a API de lições
            $lessons_response = call_hotmart_api($lessons_url, $access_token);

            // AÇÃO PRINCIPAL: Imprime os resultados para diagnóstico e para o script
            header('Content-Type: text/plain; charset=utf-8');
            echo "================================================\n";
            echo "  INSPEÇÃO DE DADOS - API DE LIÇÕES DA HOTMART \n";
            echo "================================================\n\n";
            echo "Encontrado o primeiro aluno com progresso:\n";
            echo "-------------------------------------------\n";
            print_r($student);
            echo "\n\n";
            echo "URL da Requisição de Lições:\n";
            echo "---------------------------\n";
            echo $lessons_url . "\n\n";
            echo "Resposta Completa da API de Lições:\n";
            echo "-----------------------------------\n";
            print_r($lessons_response);

            // Para o script após o primeiro resultado para não sobrecarregar.
            exit;
        }
    }
    
    // Se o loop terminar e ninguém tiver progresso
    echo "Nenhum aluno com progresso encontrado na primeira página de resultados para inspecionar.";
}

// Executa a função de inspeção
inspect_lessons_data();
?>