<?php
/**
 * Teste DIRETO usando a URL EXATA da documentação Hotmart
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/hotmart.php';
require_once __DIR__ . '/../hotmart.php';

echo "=== Teste com URL EXATA da Documentação ===\n\n";

$api = new HotmartAPI();

// 1. Obter token
echo "1. Obtendo access token...\n";
$token = $api->getAccessToken();
if (!$token) {
    die("✗ Erro ao obter token\n");
}
echo "✓ Token obtido\n\n";

// 2. Buscar um usuário ativo
echo "2. Buscando usuário ativo para teste...\n";
$subsResult = $api->getSubscriptions('ACTIVE');

if (!$subsResult['success'] || empty($subsResult['data']['items'])) {
    die("✗ Sem assinaturas ativas\n");
}

$firstSub = $subsResult['data']['items'][0];
$testEmail = $firstSub['subscriber']['email'];
$testUcode = $firstSub['subscriber']['ucode'];
$testName = $firstSub['subscriber']['name'];

echo "Usuário de teste:\n";
echo "  Nome: {$testName}\n";
echo "  Email: {$testEmail}\n";
echo "  Ucode: {$testUcode}\n\n";

// 3. Testar com a URL EXATA da documentação
echo "3. Testando com URL da documentação...\n";
$subdomain = 't101';

// URL EXATA da documentação
$url = "https://developers.hotmart.com/club/api/v1/users/{$testUcode}/lessons?subdomain={$subdomain}";

echo "URL: {$url}\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Resposta:\n";
echo json_encode(json_decode($response), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

$data = json_decode($response, true);

if ($httpCode == 200 && !empty($data)) {
    echo "✓✓ SUCESSO! Dados retornados!\n";
    echo "Estrutura: " . json_encode(array_keys($data), JSON_UNESCAPED_UNICODE) . "\n";
    
    if (is_array($data) && count($data) > 0) {
        echo "\nTotal de itens: " . count($data) . "\n";
        echo "\nPrimeiro item:\n";
        echo json_encode($data[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
} else {
    echo "✗ Sem dados ou erro\n";
}

echo "\n=== Testando com MAIS 4 usuários ===\n\n";

for ($i = 1; $i <= 4; $i++) {
    if (!isset($subsResult['data']['items'][$i])) break;
    
    $sub = $subsResult['data']['items'][$i];
    $ucode = $sub['subscriber']['ucode'];
    $email = $sub['subscriber']['email'];
    $name = $sub['subscriber']['name'];
    
    echo "{$i}. {$name} ({$email})\n";
    echo "   Ucode: {$ucode}\n";
    
    $url = "https://developers.hotmart.com/club/api/v1/users/{$ucode}/lessons?subdomain={$subdomain}";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode == 200 && is_array($data) && count($data) > 0) {
        echo "   ✓✓ COM PROGRESSO! " . count($data) . " itens\n";
    } elseif ($httpCode == 200) {
        echo "   ○ Sem progresso (array vazio)\n";
    } else {
        echo "   ✗ Erro HTTP {$httpCode}\n";
    }
    echo "\n";
}

echo "=== Fim do Teste ===\n";