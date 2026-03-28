<?php
/**
 * Configurações e funções para envio de WhatsApp
 * 
 * Inicialmente usa links wa.me (manual)
 * Preparado para Evolution API quando configurada
 */

// =====================================================
// CONFIGURAÇÃO DA EVOLUTION API (preencher quando tiver)
// =====================================================
define('EVOLUTION_API_ENABLED', false); // Mudar para true quando configurar
define('EVOLUTION_API_URL', ''); // Ex: https://sua-evolution-api.com
define('EVOLUTION_API_KEY', ''); // Sua chave da API
define('EVOLUTION_API_INSTANCE', ''); // Nome da instância

// =====================================================
// MENSAGENS PADRÃO
// =====================================================
define('WHATSAPP_MSG_TRIAL_7D_APROVADO', 
    "*Olá, [NOME]!*\n\n" .
    "Seu acesso de *sete dias gratuitos* à Translators101 foi liberado!\n\n" .
    "Acesse agora: https://v.translators101.com/login.php\n\n" .
    "Use o email e senha que você cadastrou para entrar.\n\n" .
    "*Importante:* Seu período de sete dias começará a contar a partir de seu primeiro login.\n\n" .
    "Aproveite!"
);

define('WHATSAPP_MSG_TRIAL_24H_APROVADO', 
    "*Olá, [NOME]!*\n\n" .
    "Seu acesso de *degustação* à Translators101 foi liberado!\n\n" .
    "Acesse agora: https://v.translators101.com/login.php\n\n" .
    "Você pode assistir a *três palestras* em *24 horas*.\n\n" .
    "*Importante:* O período de 24h começará a contar quando você acessar a primeira palestra.\n\n" .
    "Aproveite!"
);

/**
 * Envia mensagem via WhatsApp
 * 
 * @param string $phone Número completo com código do país (ex: +5511999999999)
 * @param string $message Mensagem a enviar
 * @param string $name Nome do destinatário (para personalização)
 * @return array ['success' => bool, 'method' => string, 'data' => mixed]
 */
function sendWhatsAppMessage($phone, $message, $name = '') {
    // Personalizar mensagem com nome
    $message = str_replace('[NOME]', $name, $message);
    
    // Limpar número (apenas dígitos)
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    
    // Se Evolution API está configurada, usar ela
    if (EVOLUTION_API_ENABLED && !empty(EVOLUTION_API_URL) && !empty(EVOLUTION_API_KEY)) {
        return sendViaEvolutionAPI($cleanPhone, $message);
    }
    
    // Caso contrário, retornar link wa.me
    return generateWhatsAppLink($cleanPhone, $message);
}

/**
 * Gera link wa.me para envio manual
 * 
 * @param string $phone Número limpo (apenas dígitos)
 * @param string $message Mensagem
 * @return array
 */
function generateWhatsAppLink($phone, $message) {
    $encodedMessage = urlencode($message);
    $link = "https://wa.me/{$phone}?text={$encodedMessage}";
    
    return [
        'success' => true,
        'method' => 'wa.me',
        'link' => $link,
        'phone' => $phone,
        'message' => $message
    ];
}

/**
 * Envia mensagem via Evolution API
 * 
 * @param string $phone Número limpo (apenas dígitos)
 * @param string $message Mensagem
 * @return array
 */
function sendViaEvolutionAPI($phone, $message) {
    try {
        $url = rtrim(EVOLUTION_API_URL, '/') . '/message/sendText/' . EVOLUTION_API_INSTANCE;
        
        $data = [
            'number' => $phone,
            'text' => $message
        ];
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'apikey: ' . EVOLUTION_API_KEY
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("[WhatsApp] Erro cURL: " . $error);
            return [
                'success' => false,
                'method' => 'evolution_api',
                'error' => $error
            ];
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'method' => 'evolution_api',
                'data' => $responseData
            ];
        } else {
            error_log("[WhatsApp] Erro Evolution API: " . $response);
            return [
                'success' => false,
                'method' => 'evolution_api',
                'error' => $responseData['message'] ?? 'Erro desconhecido',
                'http_code' => $httpCode
            ];
        }
        
    } catch (Exception $e) {
        error_log("[WhatsApp] Exceção: " . $e->getMessage());
        return [
            'success' => false,
            'method' => 'evolution_api',
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Formata número de telefone para exibição
 * 
 * @param string $phone Número completo
 * @return string Número formatado
 */
function formatPhoneDisplay($phone) {
    $clean = preg_replace('/[^0-9+]/', '', $phone);
    
    // Se começa com +55 (Brasil)
    if (preg_match('/^\+?55(\d{2})(\d{4,5})(\d{4})$/', $clean, $matches)) {
        return "+55 ({$matches[1]}) {$matches[2]}-{$matches[3]}";
    }
    
    return $clean;
}

/**
 * Valida número de telefone
 * 
 * @param string $countryCode Código do país (ex: +55)
 * @param string $number Número sem código
 * @return array ['valid' => bool, 'phone' => string, 'error' => string]
 */
function validatePhone($countryCode, $number) {
    $countryCode = trim($countryCode);
    $number = preg_replace('/[^0-9]/', '', $number);
    
    if (!preg_match('/^\+\d{1,4}$/', $countryCode)) {
        return ['valid' => false, 'error' => 'Código do país inválido'];
    }
    
    if (strlen($number) < 7 || strlen($number) > 15) {
        return ['valid' => false, 'error' => 'Número de telefone inválido'];
    }
    
    $fullPhone = $countryCode . $number;
    
    return [
        'valid' => true,
        'phone' => $fullPhone,
        'country_code' => $countryCode,
        'number' => $number
    ];
}