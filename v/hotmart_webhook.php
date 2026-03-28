<?php
// hotmart_webhook.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php'; 
require_once 'config/hotmart.php';
require_once 'includes/hotmart_logger.php';

writeToHotmartApiLog("Requisição de Webhook da Hotmart recebida.", "WEBHOOK");

// 1. Validar Token do Webhook (Segurança)
$receivedToken = $_SERVER['HTTP_X_HOTMART_TOKEN'] ?? ''; 
$expectedToken = defined('HOTMART_WEBHOOK_TOKEN') ? HOTMART_WEBHOOK_TOKEN : '';

if (empty($expectedToken) || $receivedToken !== $expectedToken) {
    header('HTTP/1.1 401 Unauthorized');
    writeToHotmartApiLog("Token de validação do webhook inválido. Acesso negado.", "WEBHOOK_ERROR");
    exit('Unauthorized');
}
writeToHotmartApiLog("Token de validação do webhook OK.", "WEBHOOK");

// 2. Obter e decodificar os dados do Webhook
$input = file_get_contents('php://input');
$payload = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    header('HTTP/1.1 400 Bad Request');
    writeToHotmartApiLog("Erro ao decodificar JSON do webhook: " . json_last_error_msg(), "WEBHOOK_ERROR");
    exit('Invalid JSON');
}

writeToHotmartApiLog("Dados recebidos: " . print_r($payload, true), "WEBHOOK_DATA");

// 3. Processar o Evento
$eventType = $payload['event'] ?? 'unknown';
$data = $payload['data'] ?? $payload;

$buyerEmail = strtolower(trim($data['buyer']['email'] ?? ''));
$buyerName = $data['buyer']['name'] ?? 'Nome não informado';
$purchaseStatus = $data['purchase']['status'] ?? 'N/A';
$subscriptionId = $data['subscription']['subscriber']['code'] ?? ($data['subscription']['id'] ?? null);

if (empty($buyerEmail)) {
    writeToHotmartApiLog("Webhook recebido sem email do comprador. Evento: $eventType.", "WEBHOOK_ERROR");
    header('HTTP/1.1 200 OK');
    exit('OK');
}

writeToHotmartApiLog("Evento: $eventType, Comprador: $buyerEmail, Status: $purchaseStatus", "WEBHOOK_INFO");

// Dados para log na tabela hotmart_logs
$logData = [
    'event_type' => $eventType,
    'status' => 'success',
    'user_email' => $buyerEmail,
    'user_name' => $buyerName,
    'transaction_id' => $data['purchase']['transaction'] ?? null,
    'product_id' => $data['product']['id'] ?? null,
    'product_name' => $data['product']['name'] ?? 'Curso Translators101',
    'price' => $data['purchase']['price']['value'] ?? null,
    'currency' => $data['purchase']['price']['currency_code'] ?? 'BRL',
    'hotmart_status' => $purchaseStatus,
    'webhook_data' => json_encode($payload),
    'response_data' => '',
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
];

try {
    $is_active_subscriber = in_array(strtoupper($purchaseStatus), ['APPROVED', 'COMPLETE', 'ACTIVE']);
    $role = $is_active_subscriber ? 'subscriber' : 'free';
    $is_subscriber_flag = $is_active_subscriber ? 1 : 0;

    // Verificar se o usuário já existe
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE email = ?");
    $stmt->execute([$buyerEmail]);
    $user = $stmt->fetch();

    $responseMessage = '';

    if ($user) {
        // Usuário existe, então ATUALIZA
        $update_role_sql = ($is_active_subscriber && $user['role'] !== 'admin') ? ", role = :role" : "";
        
        $stmt_update_user = $pdo->prepare("
            UPDATE users SET 
                is_subscriber = :is_subscriber, 
                hotmart_status = :hotmart_status,
                hotmart_subscription_id = :hotmart_subscription_id
                $update_role_sql 
            WHERE id = :id
        ");

        $params_to_bind = [
            ':is_subscriber' => $is_subscriber_flag,
            ':hotmart_status' => $purchaseStatus,
            ':hotmart_subscription_id' => $subscriptionId,
            ':id' => $user['id']
        ];
        if ($is_active_subscriber && $user['role'] !== 'admin') {
            $params_to_bind[':role'] = $role;
        }

        $stmt_update_user->execute($params_to_bind);
        $responseMessage = "Usuário existente '$buyerEmail' atualizado via webhook. Status: $purchaseStatus";
        writeToHotmartApiLog($responseMessage, "DB_UPDATE");

    } elseif ($is_active_subscriber) {
        // Usuário NÃO existe E a compra foi aprovada, então CRIA
        $stmt_insert_user = $pdo->prepare(
            "INSERT INTO users (id, name, email, password_hash, role, is_subscriber, hotmart_status, hotmart_subscription_id, is_active, first_login, created_at, updated_at) 
             VALUES (?, ?, ?, NULL, ?, 1, ?, ?, TRUE, TRUE, NOW(), NOW())"
        );
        $user_uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
        $stmt_insert_user->execute([$user_uuid, $buyerName, $buyerEmail, $role, $purchaseStatus, $subscriptionId]);
        $responseMessage = "Novo usuário '$buyerEmail' criado via webhook. Status: $purchaseStatus";
        writeToHotmartApiLog($responseMessage, "DB_INSERT");
    } else {
        $responseMessage = "Evento processado mas usuário não ativado. Status: $purchaseStatus";
        writeToHotmartApiLog($responseMessage, "DB_NO_ACTION");
    }

    // Atualizar dados do log com resposta
    $logData['response_data'] = $responseMessage;

    // Salvar log na tabela hotmart_logs
    try {
        $stmt_log = $pdo->prepare("
            INSERT INTO hotmart_logs (
                event_type, status, user_email, user_name, transaction_id, 
                product_id, product_name, price, currency, hotmart_status, 
                webhook_data, response_data, ip_address, user_agent
            ) VALUES (
                :event_type, :status, :user_email, :user_name, :transaction_id,
                :product_id, :product_name, :price, :currency, :hotmart_status,
                :webhook_data, :response_data, :ip_address, :user_agent
            )
        ");
        $stmt_log->execute($logData);
        writeToHotmartApiLog("Log salvo na tabela hotmart_logs com sucesso.", "LOG_SUCCESS");
    } catch (Exception $logError) {
        // Se falhar ao salvar log, apenas registra no arquivo mas não para o processamento
        writeToHotmartApiLog("Erro ao salvar log na tabela: " . $logError->getMessage(), "LOG_ERROR");
    }

} catch (PDOException $e) {
    $logData['status'] = 'error';
    $logData['error_message'] = $e->getMessage();
    $logData['response_data'] = "FALHA CRÍTICA no processamento do Webhook para $buyerEmail: " . $e->getMessage();
    
    // Tentar salvar log de erro
    try {
        $stmt_error_log = $pdo->prepare("
            INSERT INTO hotmart_logs (
                event_type, status, user_email, user_name, webhook_data, 
                error_message, response_data, ip_address, user_agent
            ) VALUES (
                :event_type, :status, :user_email, :user_name, :webhook_data,
                :error_message, :response_data, :ip_address, :user_agent
            )
        ");
        $stmt_error_log->execute([
            'event_type' => $logData['event_type'],
            'status' => $logData['status'],
            'user_email' => $logData['user_email'],
            'user_name' => $logData['user_name'],
            'webhook_data' => $logData['webhook_data'],
            'error_message' => $logData['error_message'],
            'response_data' => $logData['response_data'],
            'ip_address' => $logData['ip_address'],
            'user_agent' => $logData['user_agent']
        ]);
    } catch (Exception $logError) {
        writeToHotmartApiLog("Erro crítico: falha ao salvar log de erro: " . $logError->getMessage(), "CRITICAL_ERROR");
    }
    
    writeToHotmartApiLog($logData['response_data'], "WEBHOOK_ERROR_DB");
    header('HTTP/1.1 500 Internal Server Error');
    exit('Database processing failed');
}

header('HTTP/1.1 200 OK');
writeToHotmartApiLog("Webhook para '$buyerEmail' processado com sucesso.", "WEBHOOK_SUCCESS");
exit('OK');