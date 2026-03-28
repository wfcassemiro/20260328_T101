<?php

require_once __DIR__ . '/includes/hotmart_logger.php';

class Hotmart {
    private $clientId;
    private $clientSecret;
    private $basicToken;
    private $accessToken;
    private $baseUrl;
    private $oauthUrl;
    private $subscriptionsBaseUrl;

    public function __construct($config = null) {
        if (file_exists(__DIR__ . '/config/hotmart.php')) {
            require_once __DIR__ . '/config/hotmart.php';
        }

        if (empty($config) && defined('HOTMART_CLIENT_ID') && defined('HOTMART_CLIENT_SECRET')) {
            $config = [
                'client_id' => HOTMART_CLIENT_ID,
                'client_secret' => HOTMART_CLIENT_SECRET,
                'basic_token' => base64_encode(HOTMART_CLIENT_ID . ':' . HOTMART_CLIENT_SECRET)
            ];
            writeToHotmartApiLog("Credenciais carregadas de constantes.", "HOTMART_CLASS");
        }

        if (empty($config) || !isset($config['client_id']) || !isset($config['client_secret']) || !isset($config['basic_token'])) {
            writeToHotmartApiLog("Credenciais Hotmart API (client_id, client_secret, basic_token) ausentes ou configuradas incorretamente.", "HOTMART_CLASS_ERROR");
            throw new Exception("Hotmart API credentials (client_id, client_secret, basic_token) are missing or incorrectly configured.");
        }

        $this->clientId = $config['client_id'];
        $this->clientSecret = $config['client_secret'];
        $this->basicToken = $config['basic_token'];

        // URLs atualizadas conforme documentação oficial da Hotmart
        $this->baseUrl = defined('HOTMART_API_BASE') ? HOTMART_API_BASE . '/v1' : 'https://api.hotmart.com/v1';
        $this->oauthUrl = defined('HOTMART_TOKEN_URL') ? HOTMART_TOKEN_URL : 'https://api-sec-vlc.hotmart.com/security/oauth/token';
        $this->subscriptionsBaseUrl = 'https://developers.hotmart.com/payments/api/v1';
        
        writeToHotmartApiLog("Hotmart API Base URL: " . $this->baseUrl . ", OAuth URL: " . $this->oauthUrl . ", Subscriptions URL: " . $this->subscriptionsBaseUrl, "HOTMART_CLASS");
    }

    public function setAccessToken($token) {
        $this->accessToken = $token;
    }

    private function _makeRequest($endpoint, $method = 'GET', $data = [], $queryParams = [], $customBaseUrl = null) {
        $baseUrlToUse = $customBaseUrl ?? $this->baseUrl;
        $url = $baseUrlToUse . $endpoint;

        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $headers = [];
        if ($this->accessToken) {
            $headers[] = 'Authorization: Bearer ' . $this->accessToken;
        } else {
            $headers[] = 'Authorization: Basic ' . $this->basicToken;
        }
        $headers[] = 'Content-Type: application/json';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        writeToHotmartApiLog("Fazendo requisição $method para: $url", "HOTMART_API_REQUEST");

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            writeToHotmartApiLog("Erro cURL: $error", "HOTMART_API_ERROR");
            return ['success' => false, 'message' => 'Erro cURL: ' . $error];
        }

        $decodedResponse = json_decode($response, true);

        writeToHotmartApiLog("Resposta HTTP $httpCode: " . substr($response, 0, 500), "HOTMART_API_RESPONSE");

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => $decodedResponse];
        } else {
            return ['success' => false, 'message' => 'Erro na API (' . $httpCode . '): ' . ($decodedResponse['message'] ?? $response), 'response' => $decodedResponse];
        }
    }

    public function getAccessToken() {
        $headers = [
            'Authorization: Basic ' . $this->basicToken,
            'Content-Type: application/x-www-form-urlencoded',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->oauthUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $postData = http_build_query(['grant_type' => 'client_credentials']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        writeToHotmartApiLog("Solicitando access token para: " . $this->oauthUrl, "HOTMART_TOKEN_REQUEST");

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            writeToHotmartApiLog("Erro cURL ao obter token: $error", "HOTMART_TOKEN_ERROR");
            return ['success' => false, 'message' => 'Erro cURL ao obter token: ' . $error];
        }

        $decodedResponse = json_decode($response, true);

        writeToHotmartApiLog("Resposta do token HTTP $httpCode: " . substr($response, 0, 200), "HOTMART_TOKEN_RESPONSE");

        if ($httpCode == 200 && isset($decodedResponse['access_token'])) {
            $this->accessToken = $decodedResponse['access_token'];
            writeToHotmartApiLog("Access token obtido com sucesso", "HOTMART_TOKEN_SUCCESS");
            return ['success' => true, 'access_token' => $this->accessToken];
        } else {
            writeToHotmartApiLog("Erro ao obter token: " . json_encode($decodedResponse), "HOTMART_TOKEN_ERROR");
            return ['success' => false, 'message' => 'Erro ao obter token (' . $httpCode . '): ' . ($decodedResponse['error_description'] ?? ($decodedResponse['error'] ?? $response)), 'response' => $decodedResponse];
        }
    }

    // Faz uma requisição para cada status e retorna todos os resultados juntos
    public function getSubscriptions($params = []) {
        $allResults = [];
        $statuses = [
            'ACTIVE',
            'CANCELLED',
            'CANCELLED_BY_CUSTOMER',
            'CANCELLED_BY_ADMIN',
            'OVERDUE',
            'GRACE_PERIOD'
        ];

        foreach ($statuses as $status) {
            $apiParams = [];
            $apiParams['max_results'] = $params['max_results'] ?? 100;
            $params = [
    'max_results' => 100,
    'accession_date_start' => '2020-01-01T00:00:00Z',
];
            // $apiParams['status'] = $status;
            // Se quiser filtrar por product_id, descomente a linha abaixo:
            // if (isset($params['product_id'])) $apiParams['product_id'] = $params['product_id'];

            $page_token = null;
            do {
                if ($page_token) {
                    $apiParams['page_token'] = $page_token;
                } else {
                    unset($apiParams['page_token']);
                }

                writeToHotmartApiLog("Buscando assinaturas com parâmetros: " . json_encode($apiParams), "HOTMART_SUBSCRIPTIONS");

                $result = $this->_makeRequest('/subscriptions', 'GET', [], $apiParams, $this->subscriptionsBaseUrl);

                if ($result['success'] && isset($result['data']['items']) && is_array($result['data']['items'])) {
                    $allResults = array_merge($allResults, $result['data']['items']);
                    // Paginação
                    if (isset($result['data']['page_info']['next_page_token']) && $result['data']['page_info']['next_page_token']) {
                        $page_token = $result['data']['page_info']['next_page_token'];
                    } else {
                        $page_token = null;
                    }
                } else {
                    // Se der erro, loga e para a paginação desse status
                    writeToHotmartApiLog("Erro ao buscar assinaturas para status $status: " . json_encode($result), "HOTMART_SUBSCRIPTIONS_ERROR");
                    $page_token = null;
                }
            } while ($page_token);
        }

        return ['success' => true, 'data' => ['items' => $allResults]];
    }
}