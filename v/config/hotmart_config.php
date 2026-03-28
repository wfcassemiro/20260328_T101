<?php
// Configurações da API Hotmart
// IMPORTANTE: Configure as credenciais abaixo com os dados da sua aplicação Hotmart

return [
    'api' => [
        // Obtido no Painel do Desenvolvedor Hotmart
        'client_id' => getenv('HOTMART_CLIENT_ID') ?: 'f7f05ef5-bb55-46a2-a678-3c27627941d8',
        'client_secret' => getenv('HOTMART_CLIENT_SECRET') ?: '1d9e0fe5-efa9-4841-80a5-6e15be63b2e0',
        'basic_token' => getenv('HOTMART_BASIC_TOKEN') ?: 'okqS9nRS9FXJiOPkijs40T9v2fp2Vz522f1c9c-5f8e-4c6c-aa14-e863b6f34dd2',
        
      
        // URLs da API (não alterar)
        'auth_url' => 'https://api-sec-vlc.hotmart.com/security/oauth/token',
        'sales_url' => 'https://developers.hotmart.com/payments/api/v1/sales',
        'subscriptions_url' => 'https://developers.hotmart.com/payments/api/v1/subscriptions',
    ],
    
    'webhook' => [
        // URL do webhook (ajustar conforme seu domínio)
        'url' => 'https://v.translators101.com/hotmart_webhook.php',
        
        // Eventos configurados no webhook
        'events' => [
            'PURCHASE_COMPLETE',
            'PURCHASE_CANCELED', 
            'PURCHASE_REFUNDED',
            'SUBSCRIPTION_CANCELLATION',
            'SUBSCRIPTION_REACTIVATION'
        ]
    ],
    
    'sync' => [
        // Configurações de sincronização
        'days_to_sync' => 30, // Últimos X dias para sincronizar
        'max_results' => 100, // Máximo de resultados por consulta
        'default_password_length' => 8, // Tamanho da senha padrão para novos usuários
    ]
];
?>