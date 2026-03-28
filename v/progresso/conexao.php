<?php
// Script de Diagnóstico de Conexão Externa
ini_set('display_errors', 1);
error_reporting(E_ALL);

$url = 'https://api-sec-vlc.hotmart.com/security/oauth/token';

echo "<h1>Teste de Conexão Externa</h1>";
echo "<p>Tentando conectar ao host: <strong>" . parse_url($url, PHP_URL_HOST) . "</strong></p>";
echo "<hr>";

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Timeout de conexão

$output = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    // Se houver um erro de cURL, exibe-o.
    $error_msg = curl_error($ch);
    echo "<h2>❌ Falha na Conexão</h2>";
    echo "<p style='color:red; font-family:monospace; border:1px solid #ccc; padding:10px;'>";
    echo "<strong>Erro de cURL:</strong> " . htmlspecialchars($error_msg);
    echo "</p>";
    echo "<p><strong>O que isso significa:</strong> O servidor de hospedagem está bloqueando a tentativa de conexão externa. Isso é geralmente causado por um firewall. Entre em contato com o suporte da sua hospedagem com esta mensagem de erro.</p>";

} else {
    // Se a conexão for bem-sucedida, a Hotmart retornará um erro de aplicação (como 'unauthorized'), o que é esperado.
    echo "<h2>✅ Sucesso na Conexão!</h2>";
    echo "<p>A conexão com o servidor da Hotmart foi estabelecida com sucesso.</p>";
    echo "<p><strong>Código de Status HTTP recebido:</strong> $http_code</p>";
    echo "<p><strong>Resposta do servidor (esperado ser um erro de autenticação):</strong></p>";
    echo "<pre style='border:1px solid #ccc; padding:10px; background-color:#f5f5f5;'>" . htmlspecialchars($output) . "</pre>";
    echo "<p>Se você está vendo esta mensagem, a rede está funcionando e o problema original do erro 502 pode ser outro. No entanto, é muito mais provável que o teste anterior falhe.</p>";
}

curl_close($ch);