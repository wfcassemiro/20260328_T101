<?php
// Define o nome da página para fins de título/feedback
$page_title = "Teste de Credenciais Hotmart Club (Basic Auth)";

// --- Configurações de Credenciais ---
// ATENÇÃO: COLOQUE AQUI O VALOR DA SUA HOTMART BASIC AUTH
// Formato: Basic [base64_encoded_client_id:client_secret]
// Por exemplo: define('HOTMART_BASIC_AUTH', 'Basic dXNlcm5hbWU6cGFzc3dvcmQ=');
define('HOTMART_BASIC_AUTH', 'Basic N2UzZDM0MmQtYWY0Zi00MTkwLTk1OWMtNmE5NzU0NmYxNDM3OjZmNjI1NzZmLTQzMzUtNDBkMC04N2FhLThhNThmMDlkZjdmZA==');
define('HOTMART_ACCESS_TOKEN_URL', 'https://api-sec-vlc.hotmart.com/security/oauth/token');

$result_status = 'PENDENTE';
$access_token = null;
$http_code = 0;
$response_data = '';

/**
 * Tenta obter o token de acesso da Hotmart.
 * @return string|false Token de acesso ou false em caso de falha.
 */
function get_hotmart_access_token_test() {
    global $http_code, $response_data;
    
    $ch = curl_init(HOTMART_ACCESS_TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, 
        CURLOPT_POST => 1,
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
    
    if ($response === false) {
        $response_data = "Erro cURL (" . curl_errno($ch) . "): " . curl_error($ch);
    } else {
        $response_data = $response;
    }
    
    curl_close($ch);

    if ($http_code === 200) {
        $data = json_decode($response, true);
        return $data['access_token'] ?? 'Token Recebido (mas nulo/inválido)';
    }
    
    return false;
}

// Executar o teste
$access_token = get_hotmart_access_token_test();

if ($access_token && $http_code === 200) {
    $result_status = 'SUCESSO';
} elseif ($http_code === 400 || $http_code === 401) {
    $result_status = 'FALHA DE AUTENTICAÇÃO';
} elseif ($http_code > 0) {
    $result_status = 'FALHA DE RESPOSTA HTTP';
} else {
    $result_status = 'FALHA DE CONEXÃO/CURL';
}

// Início do Output HTML
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
        h1 { color: #004d40; text-align: center; margin-bottom: 20px; }
        .result-box { padding: 20px; border-radius: 6px; font-size: 1.1em; font-weight: bold; text-align: center; margin-bottom: 20px; }
        .success { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .failure { background-color: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
        .warning { background-color: #fff3e0; color: #ef6c00; border: 1px solid #ffcc80; }
        pre { background-color: #eee; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 0.9em; text-align: left; }
    </style>
</head>
<body>

<div class="container">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>

    <div class="result-box <?php 
        if ($result_status === 'SUCESSO') echo 'success'; 
        else if ($result_status === 'FALHA DE AUTENTICAÇÃO') echo 'failure';
        else echo 'warning'; 
    ?>">
        Status do Teste: <?php echo htmlspecialchars($result_status); ?>
    </div>

    <h3>Detalhes da Requisição:</h3>
    <ul>
        <li>URL do Token: <?php echo HOTMART_ACCESS_TOKEN_URL; ?></li>
        <li>Código HTTP Recebido: <strong><?php echo $http_code; ?></strong></li>
        <?php if ($access_token): ?>
            <li style="color: #2e7d32;">Token de Acesso (Início): <strong><?php echo substr($access_token, 0, 15) . '... (Recebido)'; ?></strong></li>
        <?php else: ?>
            <li style="color: #c62828;">Token: Não Recebido</li>
        <?php endif; ?>
    </ul>

    <h3>Resposta Bruta:</h3>
    <pre><?php echo htmlspecialchars($response_data); ?></pre>

    <?php if ($result_status === 'FALHA DE AUTENTICAÇÃO'): ?>
        <div class="warning result-box" style="text-align: left;">
            A Basic Auth está inválida ou expirada. Você deve gerar um novo Client ID e Secret no painel de desenvolvedor da Hotmart e atualizar a constante <code>HOTMART_BASIC_AUTH</code> no seu arquivo.
        </div>
    <?php endif; ?>
    
    <?php if ($result_status === 'SUCESSO'): ?>
        <div class="success result-box" style="text-align: left;">
            As credenciais estão corretas. O problema de lista vazia no outro script não é causado por falha de token. A causa deve ser o **subdomínio** ou a **estrutura da resposta da API de dados**.
        </div>
    <?php endif; ?>

</div>

</body>
</html>