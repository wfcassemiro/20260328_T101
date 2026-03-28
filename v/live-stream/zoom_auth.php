<?php
/**
 * Lida com a autenticação Server-to-Server OAuth com a API do Zoom.
 * Gerencia a obtenção e o cache do token de acesso.
 */

// Inclui as credenciais do arquivo de configuração.
require_once __DIR__ . '/zoom_config.php';

/**
 * Obtém um token de acesso válido da Zoom.
 * Primeiro tenta buscar um token válido do cache (sessão PHP).
 * Se não houver um token válido, solicita um novo à API da Zoom.
 *
 * @param bool $forceNew Força a obtenção de um novo token, ignorando o cache.
 * @return string|null O token de acesso ou null em caso de falha.
 */
function getZoomAccessToken($forceNew = false) {
    // Verifica se há um token válido na sessão (cache)
    if (!$forceNew && isset($_SESSION['zoom_access_token']) && time() < $_SESSION['zoom_token_expires_at']) {
        writeToZoomLog("Token válido em cache. Expira em: " . date('Y-m-d H:i:s', $_SESSION['zoom_token_expires_at']));
        return $_SESSION['zoom_access_token'];
    }

    writeToZoomLog("==== NOVA REQUISIÇÃO DE TOKEN ====");

    $url = 'https://zoom.us/oauth/token';
    $accountId = ZOOM_ACCOUNT_ID;
    $clientId = ZOOM_CLIENT_ID;
    $clientSecret = ZOOM_CLIENT_SECRET;

    $base64Credentials = base64_encode("$clientId:$clientSecret");

    $params = http_build_query([
        'grant_type' => 'account_credentials',
        'account_id' => $accountId
    ]);
    
    writeToZoomLog("URL: $url");
    writeToZoomLog("Grant Type: account_credentials");
    writeToZoomLog("Account ID: $accountId");

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . $base64Credentials,
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    writeToZoomLog("HTTP Code: $httpCode");
    writeToZoomLog("Resposta: $response");

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        $accessToken = $data['access_token'];
        $expiresIn = $data['expires_in']; // Geralmente 3600 segundos (1 hora)

        // Armazena o novo token e seu tempo de expiração na sessão
        $_SESSION['zoom_access_token'] = $accessToken;
        $_SESSION['zoom_token_expires_at'] = time() + $expiresIn - 30; // Subtrai 30s por segurança

        writeToZoomLog("✓ Token obtido com sucesso!");
        writeToZoomLog("Escopos: " . ($data['scope'] ?? 'N/A'));
        writeToZoomLog("Expira em: " . date('Y-m-d H:i:s', $_SESSION['zoom_token_expires_at']));
        writeToZoomLog("====");

        return $accessToken;
    } else {
        $errorData = json_decode($response, true);
        $errorMessage = $errorData['reason'] ?? 'Erro desconhecido';
        writeToZoomLog("ERRO: Falha ao obter token. HTTP: $httpCode, Erro: $errorMessage");
        return null;
    }
}

/**
 * Faz uma requisição genérica para a API do Zoom.
 *
 * @param string $endpoint O endpoint da API (ex: '/users/me').
 * @param array $data Os dados a serem enviados no corpo da requisição (para POST/PATCH).
 * @param string $method O método HTTP (GET, POST, PATCH, DELETE).
 * @return array Um array com ['success' => bool, 'data' => mixed, 'error' => string].
 */
function zoomApiRequest($endpoint, $data = [], $method = 'GET') {
    $token = getZoomAccessToken();
    if (!$token) {
        return ['success' => false, 'error' => 'Não foi possível obter o token de acesso do Zoom.'];
    }

    $url = 'https://api.zoom.us/v2' . $endpoint;
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if (!empty($data) && in_array($method, ['POST', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    writeToZoomLog("API Request: $method $endpoint");
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    writeToZoomLog("API Response HTTP: $httpCode");

    if ($httpCode >= 200 && $httpCode < 300) {
        writeToZoomLog("✓ Requisição bem-sucedida");
        // O DELETE retorna 204 No Content (resposta vazia)
        return ['success' => true, 'data' => $httpCode == 204 ? null : json_decode($response, true)];
    } else {
        $errorData = json_decode($response, true);
        $errorMessage = $errorData['message'] ?? 'Erro na requisição da API.';
        writeToZoomLog("✗ Erro na API: $errorMessage");
        return ['success' => false, 'error' => $errorMessage, 'code' => $httpCode];
    }
}