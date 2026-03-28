<?php
// Ativa a exibição de todos os erros para diagnóstico.
ini_set('display_errors', 1);
error_reporting(E_ALL);

// =============================================================================
// CONFIGURAÇÕES E CREDENCIAIS
// =============================================================================
$hotmart_club_subdomain = 'assinaturapremiumplustranslato'; // Subdomínio correto
$basic_auth = 'Basic N2UzZDM0MmQtYWY0Zi00MTkwLTk1OWMtNmE5NzU0NmYxNDM3OjZmNjI1NzZmLTQzMzUtNDBkMC04N2FhLThhNThmMDlkZjdmZA==';

// Variáveis para armazenar os resultados
$error_message = null;
$access_token = null;

echo "<h1>Teste de Busca Limitada de Alunos</h1>";
echo "<p><strong>Subdomínio:</strong> " . htmlspecialchars($hotmart_club_subdomain) . "</p><hr>";

// =============================================================================
// PARTE 1: OBTER O TOKEN DE ACESSO
// =============================================================================
echo "<h2>Passo 1: Obtendo Token de Acesso...</h2>";

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
    echo "<p style='color:green;'><b>Sucesso!</b> Token de acesso obtido.</p>";
} else {
    $error_message = "Falha ao obter token. Código: $http_code_token. Resposta: " . htmlspecialchars($response_token);
}

echo "<hr>";

// =============================================================================
// PARTE 2: BUSCAR OS 5 PRIMEIROS ALUNOS
// =============================================================================
if ($access_token) {
    echo "<h2>Passo 2: Buscando os 5 primeiros alunos...</h2>";

    $query_params = [
        'subdomain' => $hotmart_club_subdomain,
        'max_results' => 5 // <<<--- ESTE É O PARÂMETRO DO TESTE
    ];
    $students_url = 'https://api-club.hotmart.com/club/api/v1/users?' . http_build_query($query_params);
    
    echo "<p><b>URL da Requisição:</b> " . htmlspecialchars($students_url) . "</p>";

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

    echo "<h3>Resultado da Requisição:</h3>";

    if ($http_code_students === 200) {
        echo "<p style='color:green;'><b>Sucesso!</b> Código HTTP 200 recebido.</p>";
        $students_data = json_decode($response_students, true);
        $students = $students_data['items'] ?? [];
        
        echo "<h4>Alunos Encontrados:</h4>";
        if (empty($students)) {
            echo "<p>Nenhum aluno retornado pela API.</p>";
        } else {
            echo "<pre style='background-color:#f0f0f0; border:1px solid #ccc; padding:10px; border-radius:5px;'>";
            print_r($students);
            echo "</pre>";
        }
    } else {
        $error_message = "Falha ao buscar alunos. Código: $http_code_students. Resposta: " . htmlspecialchars($response_students);
    }
}

// Exibe a mensagem de erro final, se houver alguma.
if ($error_message) {
    echo "<h2 style='color:red;'>ERRO FINAL</h2>";
    echo "<p style='background-color:#f8d7da; color:#721c24; border:1px solid #f5c6cb; padding:15px; border-radius:5px;'>";
    echo $error_message;
    echo "</p>";
}
?>