<?php
/**
 * Hotmart API Integration Class
 * Handles authentication and API requests for Hotmart services
 */
class HotmartAPI {
    private $clientId;
    private $clientSecret;
    private $basicAuth;
    private $hotToken;
    private $accessToken;
    private $baseUrl = 'https://developers.hotmart.com';
    private $oauthUrl = 'https://api-sec-vlc.hotmart.com/security/oauth/token';
    private $subscriptionsUrl = 'https://developers.hotmart.com/payments/api/v1';
    private $clubBaseUrl = 'https://developers.hotmart.com/club/api/v1';
    
    public function __construct() {
        $this->clientId = defined('HOTMART_CLIENT_ID') ? HOTMART_CLIENT_ID : '';
        $this->clientSecret = defined('HOTMART_CLIENT_SECRET') ? HOTMART_CLIENT_SECRET : '';
        $this->basicAuth = defined('HOTMART_BASIC_AUTH') ? HOTMART_BASIC_AUTH : '';
        $this->hotToken = defined('HOTMART_HOT_TOKEN') ? HOTMART_HOT_TOKEN : '';
        
        error_log("[HOTMART_CLASS] Credenciais carregadas de constantes.");
        error_log("[HOTMART_CLASS] Hotmart API Base URL: {$this->baseUrl}, OAuth URL: {$this->oauthUrl}, Subscriptions URL: {$this->subscriptionsUrl}, Club URL: {$this->clubBaseUrl}, HOT Token: " . (!empty($this->hotToken) ? 'Configurado' : 'Não configurado'));
    }
    
    /**
     * Get OAuth access token
     */
    public function getAccessToken() {
        if (!empty($this->accessToken)) {
            return $this->accessToken;
        }
        
        error_log("[HOTMART_TOKEN_REQUEST] Solicitando access token para: {$this->oauthUrl}");
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->oauthUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials&client_id=' . urlencode($this->clientId) . '&client_secret=' . urlencode($this->clientSecret),
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic ' . $this->basicAuth,
                'Content-Type: application/x-www-form-urlencoded'
            ),
        ));
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        curl_close($curl);
        
        if ($err) {
            error_log("[HOTMART_TOKEN_ERROR] Erro cURL: $err");
            return false;
        }
        
        error_log("[HOTMART_TOKEN_RESPONSE] Resposta do token HTTP {$httpCode}: " . substr($response, 0, 200) . "...");
        
        $tokenData = json_decode($response, true);
        if (isset($tokenData['access_token'])) {
            $this->accessToken = $tokenData['access_token'];
            error_log("[HOTMART_TOKEN_SUCCESS] Access token obtido com sucesso");
            return $this->accessToken;
        }
        
        error_log("[HOTMART_TOKEN_ERROR] Falha ao obter access token: $response");
        return false;
    }
    
    /**
     * Make API request with proper error handling
     */
    private function _makeRequest($endpoint, $method = 'GET', $data = [], $params = [], $baseUrl = null, $useHotToken = false) {
        $url = ($baseUrl ?: $this->baseUrl) . $endpoint;
        
        // Add query parameters
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        error_log("[HOTMART_API_REQUEST] Fazendo requisição {$method} para: {$url}");
        
        $curl = curl_init();
        $headers = array('Content-Type: application/json');
        
        if ($useHotToken && !empty($this->hotToken)) {
            error_log("[HOTMART_AUTH] Usando HOT Token para autenticação");
            $headers[] = 'Authorization: ' . $this->hotToken;
        } else {
            $token = $this->getAccessToken();
            if (!$token) {
                return ['success' => false, 'message' => 'Falha ao obter token de acesso'];
            }
            error_log("[HOTMART_AUTH] Usando Access Token para autenticação");
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        
        $curlOptions = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        );
        
        if ($method === 'POST' && !empty($data)) {
            $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
        }
        
        curl_setopt_array($curl, $curlOptions);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        curl_close($curl);
        
        if ($err) {
            error_log("[HOTMART_API_ERROR] Erro cURL: $err");
            return ['success' => false, 'message' => "Erro cURL: $err"];
        }
        
        error_log("[HOTMART_API_RESPONSE] Resposta HTTP {$httpCode}: " . substr($response, 0, 200) . "...");
        
        // Handle empty response body
        $decodedResponse = null;
        if ($response === '' || $response === null) {
            // corpo vazio -> tratar como resposta vazia (sem itens)
            $decodedResponse = [];
        } else {
            $decodedResponse = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Não-JSON: manter corpo para debug, mas não tratar como items
                $decodedResponse = ['original_response' => $response];
            }
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $decodedResponse,
                'http_code' => $httpCode
            ];
        }
        
        return [
            'success' => false,
            'message' => "Erro na API ({$httpCode}): " . $response,
            'response' => $decodedResponse,
            'http_code' => $httpCode
        ];
    }
    
    /**
     * Get users from Hotmart Club API
     */
    public function getClubUsers($subdomain) {
        error_log("[HOTMART_CLUB_USERS] Tentando endpoint: /users com parâmetros: " . json_encode(['subdomain' => $subdomain]));
        
        // Try with HOT Token first
        $result = $this->_makeRequest('/users', 'GET', [], ['subdomain' => $subdomain], $this->clubBaseUrl, true);
        
        if ($result['success']) {
            return $result;
        }
        
        error_log("[HOTMART_CLUB_USERS_WARN] Falha no endpoint /users: " . json_encode($result));
        
        // Try alternative endpoint
        error_log("[HOTMART_CLUB_USERS] Tentando endpoint: /subscriptions/subscribers com parâmetros: []");
        $result = $this->_makeRequest('/subscriptions/subscribers', 'GET', [], [], $this->clubBaseUrl, true);
        
        if ($result['success']) {
            return $result;
        }
        
        error_log("[HOTMART_CLUB_USERS_WARN] Falha no endpoint /subscriptions/subscribers: " . json_encode($result));
        
        return $result;
    }
    
    /**
     * Get subscriptions as fallback
     */
    public function getSubscriptions($status = null) {
        $params = ['max_results' => 100];
        
        // Only add status if it's a valid value
        $validStatuses = ['ACTIVE', 'CANCELLED', 'CANCELLED_BY_CUSTOMER', 'CANCELLED_BY_ADMIN', 'OVERDUE', 'GRACE_PERIOD'];
        if ($status && in_array($status, $validStatuses)) {
            $params['status'] = $status;
        }
        
        error_log("[HOTMART_SUBSCRIPTIONS] Buscando assinaturas com parâmetros: " . json_encode($params));
        
        return $this->_makeRequest('/subscriptions', 'GET', [], $params, $this->subscriptionsUrl, false);
    }
    
    /**
     * Get user progress for lessons
     */
    public function getUserProgress($userId, $subdomain = null) {
        // Obter subdomain das constantes se não fornecido
        if (empty($subdomain)) {
            $subdomain = defined('HOTMART_SUBDOMAIN') ? HOTMART_SUBDOMAIN : 't101';
        }
        
        error_log("[HOTMART_USER_PROGRESS] Buscando progresso para usuário: {$userId} no subdomain: {$subdomain}");
        
        // $userId pode ser ucode ou numeric
        $candidates = [];
        
        // se parece UUID (contém '-'), tente como ucode
        if (strpos($userId, '-') !== false) {
            $candidates[] = ['url' => "/users/{$userId}/lessons", 'params' => ['subdomain' => $subdomain], 'useHotToken' => true];
            $candidates[] = ['url' => "/users/{$userId}/modules/pages", 'params' => ['subdomain' => $subdomain, 'status'=>'COMPLETED'], 'useHotToken' => true];
        }
        
        // tentar subscriber_code (curto) e id numérico também
        $candidates[] = ['url' => "/users/{$userId}/lessons", 'params' => ['subdomain' => $subdomain], 'useHotToken' => true];
        $candidates[] = ['url' => "/users/{$userId}/modules/pages", 'params' => ['subdomain' => $subdomain, 'status'=>'COMPLETED'], 'useHotToken' => true];
        
        // depois tentar com access token (useHotToken=false)
        foreach ($candidates as $c) {
            $params = $c['params'] ?? [];
            $res = $this->_makeRequest($c['url'], 'GET', [], $params, $this->clubBaseUrl, $c['useHotToken']);
            if ($res['success'] && isset($res['data']) && !empty($res['data'])) {
                // Check if we have items or pages data
                if (isset($res['data']['items']) || isset($res['data']['pages']) || (is_array($res['data']) && count($res['data']) > 0)) {
                    error_log("[HOTMART_USER_PROGRESS_SUCCESS] Progresso encontrado para usuário {$userId}");
                    return $res;
                }
            }
            error_log("[HOTMART_USER_PROGRESS_ATTEMPT] Tentativa falhou para {$c['url']}: " . json_encode($res));
        }
        
        error_log("[HOTMART_USER_PROGRESS_FAIL] Nenhuma tentativa retornou dados válidos para usuário {$userId}");
        return ['success' => false, 'message' => 'Nenhum dado de progresso encontrado'];
    }
}
?>
