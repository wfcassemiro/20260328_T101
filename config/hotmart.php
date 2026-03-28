<?php
// Credenciais Hotmart
define('HOTMART_CLIENT_ID', 'f7f05ef5-bb55-46a2-a678-3c27627941d8');
define('HOTMART_CLIENT_SECRET', '1d9e0fe5-efa9-4841-80a5-6e15be63b2e0');
define('HOTMART_HOT_TOKEN', 'okqS9nRS9FXJiOPkijs40T9v2fp2Vz522f1c9c-5f8e-4c6c-aa14-e863b6f34dd2');

// URLs da API Hotmart
define('HOTMART_API_BASE', 'https://api.hotmart.com'); // Base para Sales/Products/Achievements
define('HOTMART_TOKEN_URL', 'https://api-sec-vlc.hotmart.com/security/oauth/token'); // Para autenticação
// A URL para assinaturas será definida diretamente na classe Hotmart para maior controle.
define('HOTMART_SUBSCRIBERS_URL', 'https://developers.hotmart.com/payments/api/v1/subscriptions'); //
define('HOTMART_SALES_URL', 'https://developers.hotmart.com/payments/api/v1/sales'); //

// Configurações do Webhook
define('HOTMART_WEBHOOK_TOKEN', 'translators101_webhook_2024'); // Token para validar webhooks