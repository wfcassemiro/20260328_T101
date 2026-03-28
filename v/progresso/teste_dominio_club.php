<?php
// Teste final e definitivo: Isolar a conexão com o domínio problemático.
ini_set('display_errors', 1);
error_reporting(E_ALL);

$url_problematica = 'https://api-club.hotmart.com/club/api/v1/users?subdomain=assinaturapremiumplustranslato';

echo "<h1>Diagnóstico Final: Conexão com api-club.hotmart.com</h1>";
echo "<p>Tentando fazer uma requisição GET simples para: <strong>$url_problematica</strong></p>";
echo "<p>Se esta página resultar em um erro 502, a causa é um bloqueio de firewall no servidor de hospedagem para este domínio específico.</p>";
echo "<hr>";

$ch = curl_init($url_problematica);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo "<h2>❌ Falha na Requisição cURL</h2>";
    echo "<p style='color:red;'>Ocorreu um erro na conexão: " . htmlspecialchars($curl_error) . "</p>";
} else {
    echo "<h2>✅ Requisição Concluída com Sucesso!</h2>";
    echo "<p>A comunicação com o servidor foi realizada (isto é inesperado).</p>";
    echo "<p><strong>Código de Status HTTP:</strong> $http_code (esperado 401 ou 403)</p>";
    echo "<p><strong>Resposta do Servidor:</strong></p>";
    echo "<pre style='border:1px solid #ccc; padding:10px; background-color:#f5f5f5;'>" . htmlspecialchars($response) . "</pre>";
}
?>