<?php
/**
 * Proxy para testar credenciais do Zoom
 * Evita problemas de CORS
 *
 * Observação: envia agora os parâmetros no corpo POST (form-urlencoded),
 * conforme exigido pela API de token do Zoom.
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $accountId = $data['accountId'] ?? '';
    $clientId = $data['clientId'] ?? '';
    $clientSecret = $data['clientSecret'] ?? '';
    
    if (empty($accountId) || empty($clientId) || empty($clientSecret)) {
        echo json_encode([
            'success' => false,
            'error' => 'Todas as credenciais são obrigatórias'
        ]);
        exit;
    }
    
    // Criar credenciais base64
    $credentials = base64_encode($clientId . ':' . $clientSecret);
    
    // Preparar body form-urlencoded
    $postFields = http_build_query([
        'grant_type' => 'account_credentials',
        'account_id' => $accountId
    ]);

    // Fazer requisição ao Zoom
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://zoom.us/oauth/token",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    curl_close($ch);
    
    if ($curlError) {
        echo json_encode([
            'success' => false,
            'error' => 'Erro de conexão: ' . $curlError
        ]);
        exit;
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode === 200 && isset($responseData['access_token'])) {
        echo json_encode([
            'success' => true,
            'data' => [
                'access_token' => $responseData['access_token'],
                'expires_in' => $responseData['expires_in'] ?? 3600,
                'token_preview' => substr($responseData['access_token'], 0, 30) . '...'
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'http_code' => $httpCode,
            'error' => $responseData['error'] ?? 'Erro desconhecido',
            'reason' => $responseData['reason'] ?? 'Não especificado',
            'message' => $responseData['message'] ?? '',
            'raw_response' => $response // inclui resposta bruta para diagnóstico
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Método não permitido'
    ]);
}
?>