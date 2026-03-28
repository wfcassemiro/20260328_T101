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

try {
    $is_active_subscriber = in_array(strtoupper($purchaseStatus), ['APPROVED', 'COMPLETE', 'ACTIVE']);
    $role = $is_active_subscriber ? 'subscriber' : 'free';
    $is_subscriber_flag = $is_active_subscriber ? 1 : 0;

    // Verificar se o usuário já existe
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE email = ?");
    $stmt->execute([$buyerEmail]);
    $user = $stmt->fetch();

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
        writeToHotmartApiLog("Usuário existente '$buyerEmail' atualizado via webhook. Status: $purchaseStatus", "DB_UPDATE");

    } elseif ($is_active_subscriber) {
        // Usuário NÃO existe E a compra foi aprovada, então CRIA
        $stmt_insert_user = $pdo->prepare(
            "INSERT INTO users (id, name, email, password_hash, role, is_subscriber, hotmart_status, hotmart_subscription_id, is_active, first_login, created_at, updated_at) 
             VALUES (?, ?, ?, NULL, ?, 1, ?, ?, TRUE, TRUE, NOW(), NOW())"
        );
        $user_uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
        $stmt_insert_user->execute([$user_uuid, $buyerName, $buyerEmail, $role, $purchaseStatus, $subscriptionId]);
        writeToHotmartApiLog("Novo usuário '$buyerEmail' criado via webhook. Status: $purchaseStatus", "DB_INSERT");
    }

} catch (PDOException $e) {
    writeToHotmartApiLog("FALHA CRÍTICA no processamento do Webhook para $buyerEmail: " . $e->getMessage(), "WEBHOOK_ERROR_DB");
    header('HTTP/1.1 500 Internal Server Error');
    exit('Database processing failed');
}

header('HTTP/1.1 200 OK');
writeToHotmartApiLog("Webhook para '$buyerEmail' processado com sucesso.", "WEBHOOK_SUCCESS");
exit('OK');