<?php
// Teste Final: Tenta realizar a autenticação mínima.
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Teste Final de Autenticação</h1>";
echo "<p>Iniciando a tentativa de obter o token de acesso da Hotmart...</p>";
echo "<hr>";

// Suas credenciais
$basic_auth = 'Basic N2UzZDM0MmQtYWY0Zi00MTkwLTk1OWMtNmE5NzU0NmYxNDM3OjZmNjI1NzZmLTQzMzUtNDBkMC04N2FhLThhNThmMDlkZjdmZA==';
$url = 'https://api-sec-vlc.hotmart.com/security/oauth/token';

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['grant_type' => 'client_credentials']));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'Authorization: ' . $basic_auth
]);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);

if ($curl_error) {
    echo "<h2>❌ Falha na Requisição cURL</h2>";
    echo "<p style='color:red;'>Ocorreu um erro durante a execução da requisição: " . htmlspecialchars($curl_error) . "</p>";
} else {
    echo "<h2>✅ Requisição Concluída</h2>";
    echo "<p>A comunicação com o servidor da Hotmart foi realizada.</p>";
    echo "<p><strong>Código de Status HTTP:</strong> $http_code</p>";
    echo "<p><strong>Resposta do Servidor:</strong></p>";
    echo "<pre style='border:1px solid #ccc; padding:10px; background-color:#f5f5f5;'>" . htmlspecialchars($response) . "</pre>";
}

curl_close($ch);

echo "<hr>";
echo "<p>Teste concluído.</p>";

?>