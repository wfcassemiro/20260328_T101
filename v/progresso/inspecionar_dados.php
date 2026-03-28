<?php
// Ativa a exibição de todos os erros para diagnóstico.
ini_set('display_errors', 1);
error_reporting(E_ALL);

// =============================================================================
// CONFIGURAÇÕES E CREDENCIAIS
// =============================================================================
$hotmart_club_subdomain = 'assinaturapremiumplustranslato';
$basic_auth = 'Basic N2UzZDM0MmQtYWY0Zi00MTkwLTk1OWMtNmE5NzU0NmYxNDM3OjZmNjI1NzZmLTQzMzUtNDBkMC04N2FhLThhNThmMDlkZjdmZA==';

$access_token = null;

// =============================================================================
// PARTE 1: OBTER O TOKEN DE ACESSO
// =============================================================================
$token_url = 'https://api-sec-vlc.hotmart.com/security/oauth/token';
$ch_token = curl_init($token_url);
curl_setopt($ch_token, CURLOPT_POST, 1);
curl_setopt($ch_token, CURLOPT_POSTFIELDS, http_build_query(['grant_type' => 'client_credentials']));
curl_setopt($ch_token, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch_token, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'Authorization: ' . $basic_auth
]);
curl_setopt($ch_token, CURLOPT_CONNECTTIMEOUT, 10);
$response_token = curl_exec($ch_token);
$http_code_token = curl_getinfo($ch_token, CURLINFO_HTTP_CODE);
curl_close($ch_token);

if ($http_code_token === 200) {
    $token_data = json_decode($response_token, true);
    $access_token = $token_data['access_token'] ?? null;
} else {
    die("Falha crítica ao obter o token de acesso. Não é possível continuar.");
}

// =============================================================================
// PARTE 2: BUSCAR OS ALUNOS E INSPECIONAR OS DADOS
// =============================================================================
if ($access_token) {
    $query_params = [
        'subdomain' => $hotmart_club_subdomain,
        'max_results' => 500 // Buscamos um bom número para ter uma amostra completa
    ];

    $students_url = 'https://developers.hotmart.com/club/api/v1/users?' . http_build_query($query_params);

    $ch_students = curl_init($students_url);
    curl_setopt($ch_students, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_students, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ]);
    curl_setopt($ch_students, CURLOPT_CONNECTTIMEOUT, 10);
    $response_students = curl_exec($ch_students);
    $http_code_students = curl_getinfo($ch_students, CURLINFO_HTTP_CODE);
    curl_close($ch_students);

    if ($http_code_students === 200) {
        $students_data = json_decode($response_students, true);
        
        // AÇÃO PRINCIPAL: Imprime a estrutura completa dos dados recebidos
        header('Content-Type: text/plain; charset=utf-8'); // Define o cabeçalho para texto puro para melhor visualização
        print_r($students_data);

    } else {
        echo "Erro ao buscar alunos. Código: $http_code_students. Resposta: " . htmlspecialchars($response_students);
    }
}
?>