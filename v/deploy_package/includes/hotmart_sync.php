<?php
// includes/hotmart_sync.php

// Dependências: A classe Hotmart (hotmart.php) e a função writeToHotmartApiLog (includes/hotmart_logger.php)

class HotmartSync {
    private $pdo;
    private $hotmartApi;
    private $logFile;
    private $productIdToSync = 4304019; // ID do produto específico a ser sincronizado

    public function __construct($pdo, Hotmart $hotmartApi) {
        $this->pdo = $pdo;
        $this->hotmartApi = $hotmartApi;
        $this->logFile = __DIR__ . '/../logs/hotmart_sync.log';

        // Crie o diretório de logs se não existir
        $log_dir = dirname($this->logFile);
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }
    }

    private function writeSyncLog($message) {
        writeToHotmartApiLog($message, "HOTMART_SYNC");
    }

    /**
     * Sincroniza assinaturas da Hotmart para um produto específico com o banco de dados local.
     *
     * @return array Resultado da sincronização (success, message, count)
     */
    public function syncSubscriptions() {
        $this->writeSyncLog("Iniciando sincronização de assinaturas para o produto ID: " . $this->productIdToSync);

        // 1. Obter token de acesso
        $tokenResult = $this->hotmartApi->getAccessToken();
        if (!$tokenResult['success']) {
            $this->writeSyncLog("ERRO: Falha ao obter Access Token para sincronização de assinaturas: " . $tokenResult['message']);
            return ['success' => false, 'message' => 'Falha ao obter Access Token Hotmart para sincronização de assinaturas.', 'response' => $tokenResult['response'] ?? []];
        }
        $this->hotmartApi->setAccessToken($tokenResult['access_token']);

        // 2. Definir parâmetros iniciais para a API de assinaturas
        $initialParams = [
            'product_id' => $this->productIdToSync,
            'max_results' => 100, // Número de assinaturas por página
        ];

        $totalSynced = 0;
        $nextPageToken = null;
        $hasMorePages = true;
        $requestCount = 0;

        while ($hasMorePages) {
            $requestCount++;
            $currentParams = $initialParams;
            if ($nextPageToken !== null) {
                $currentParams['page_token'] = $nextPageToken;
            }

            $this->writeSyncLog("DEBUG: Buscando assinaturas (Requisição: $requestCount). Parâmetros: " . json_encode($currentParams));
            $subscriptionsResult = $this->hotmartApi->getSubscriptions($currentParams);

            if (
                $subscriptionsResult['success'] &&
                isset($subscriptionsResult['data']) &&
                isset($subscriptionsResult['data']['items']) &&
                is_array($subscriptionsResult['data']['items'])
            ) {
                $subscriptions = $subscriptionsResult['data']['items'];
                $this->writeSyncLog("INFO: Assinaturas encontradas (Requisição $requestCount): " . count($subscriptions));

                if (empty($subscriptions) && $requestCount == 1 && !isset($subscriptionsResult['data']['page_info']['next_page_token'])) {
                    $this->writeSyncLog("INFO: Nenhuma assinatura encontrada na primeira requisição e sem próximas páginas.");
                    $hasMorePages = false;
                    break;
                }

                foreach ($subscriptions as $subscription) {
                    // Mapeamento dos dados da assinatura para a sua tabela
                    $subscription_id = $subscription['subscription_id'] ?? null;
                    $subscriber_code = $subscription['subscriber_code'] ?? null;
                    $product_id_api = $subscription['product']['id'] ?? null;
                    $product_name_api = $subscription['plan']['name'] ?? null;
                    $buyer_hotmart_id = $subscription['subscriber']['ucode'] ?? null;
                    $buyer_name = $subscription['subscriber']['name'] ?? null;
                    $buyer_email = $subscription['subscriber']['email'] ?? null;
                    $status_api = $subscription['status'] ?? null;

                    $accession_date_ms = $subscription['accession_date'] ?? null;
                    $start_date_formatted = $accession_date_ms ? date('Y-m-d H:i:s', $accession_date_ms / 1000) : null;

                    $end_date_formatted = null;
                    if (strtoupper($status_api) === 'ACTIVE' && isset($subscription['date_next_charge'])) {
                        $date_next_charge_ms = $subscription['date_next_charge'];
                        $end_date_formatted = $date_next_charge_ms ? date('Y-m-d H:i:s', $date_next_charge_ms / 1000) : null;
                    } elseif (isset($subscription['end_accession_date'])) {
                        $end_accession_date_ms = $subscription['end_accession_date'];
                        $end_date_formatted = $end_accession_date_ms ? date('Y-m-d H:i:s', $end_accession_date_ms / 1000) : null;
                    }

                    $access_date_formatted = $start_date_formatted;
                    $raw_data = json_encode($subscription);

                    if (empty($subscription_id) || empty($product_id_api) || empty($buyer_email) || empty($status_api)) {
                        $this->writeSyncLog("ALERTA: Assinatura com dados essenciais (ID, ProdutoID, Email, Status) incompletos, pulando: " . json_encode($subscription));
                        continue;
                    }

                    // INSERIR ou ATUALIZAR usuário na tabela 'users'
                    try {
                        $stmt_find_user = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
                        $stmt_find_user->execute([$buyer_email]);
                        $existing_user = $stmt_find_user->fetch();

                        $is_subscriber_value = (strtoupper($status_api) === 'ACTIVE' || strtoupper($status_api) === 'GRACE_PERIOD') ? 1 : 0;
                        $current_timestamp = date('Y-m-d H:i:s');

                        if ($existing_user) {
                            $stmt_update_user = $this->pdo->prepare("
                                UPDATE users 
                                SET 
                                    is_subscriber = ?,
                                    hotmart_subscription_id = ?,
                                    hotmart_status = ?,
                                    hotmart_synced_at = ?,
                                    name = IFNULL(name, ?),
                                    updated_at = ?
                                WHERE email = ?
                            ");
                            $stmt_update_user->execute([
                                $is_subscriber_value,
                                $subscription_id,
                                $status_api,
                                $current_timestamp,
                                $buyer_name,
                                $current_timestamp,
                                $buyer_email
                            ]);
                            $this->writeSyncLog("INFO: Usuário existente $buyer_email atualizado. is_subscriber: $is_subscriber_value, hotmart_status: $status_api");
                        } else {
                            $user_uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                                mt_rand(0, 0xffff),
                                mt_rand(0, 0x0fff) | 0x4000,
                                mt_rand(0, 0x3fff) | 0x8000,
                                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                            );

                            $stmt_insert_user = $this->pdo->prepare("
                                INSERT INTO users 
                                (id, email, name, password_hash, role, is_subscriber, 
                                hotmart_subscription_id, hotmart_status, hotmart_synced_at, 
                                created_at, updated_at, is_active, first_login)
                                VALUES 
                                (?, ?, ?, NULL, 'subscriber', ?, ?, ?, ?, ?, ?, TRUE, TRUE)
                            ");
                            $stmt_insert_user->execute([
                                $user_uuid,
                                $buyer_email,
                                $buyer_name,
                                $is_subscriber_value,
                                $subscription_id,
                                $status_api,
                                $current_timestamp,
                                $current_timestamp,
                                $current_timestamp
                            ]);
                            $this->writeSyncLog("INFO: Novo usuário $buyer_email criado com ID: $user_uuid. is_subscriber: $is_subscriber_value, hotmart_status: $status_api");
                        }
                    } catch (PDOException $e) {
                        $this->writeSyncLog("ERRO DB (users): Falha ao processar usuário $buyer_email: " . $e->getMessage());
                    }

                    // INSERIR ou ATUALIZAR na tabela 'hotmart_subscriptions'
                    try {
                        $stmt_sub = $this->pdo->prepare("
                            INSERT INTO hotmart_subscriptions (
                                subscription_id, subscriber_code, product_id, product_name, 
                                buyer_hotmart_id, buyer_name, buyer_email, status, 
                                start_date, end_date, access_date, raw_data
                            )
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE
                                subscriber_code = VALUES(subscriber_code),
                                product_name = VALUES(product_name),
                                buyer_hotmart_id = VALUES(buyer_hotmart_id),
                                buyer_name = VALUES(buyer_name),
                                buyer_email = VALUES(buyer_email),
                                status = VALUES(status),
                                start_date = VALUES(start_date),
                                end_date = VALUES(end_date),
                                access_date = VALUES(access_date),
                                raw_data = VALUES(raw_data),
                                updated_at = NOW()
                        ");
                        $stmt_sub->execute([
                            $subscription_id, $subscriber_code, $product_id_api, $product_name_api,
                            $buyer_hotmart_id, $buyer_name, $buyer_email, $status_api,
                            $start_date_formatted, $end_date_formatted, $access_date_formatted, $raw_data
                        ]);
                        $totalSynced++;
                        $this->writeSyncLog("DEBUG: Assinatura $subscription_id para $buyer_email ($status_api) inserida/atualizada em hotmart_subscriptions.");
                    } catch (PDOException $e) {
                        $this->writeSyncLog("ERRO DB (hotmart_subscriptions): Falha ao sincronizar assinatura $subscription_id: " . $e->getMessage());
                    }
                }

                // Paginação
                if (isset($subscriptionsResult['data']['page_info']['next_page_token']) && !empty($subscriptionsResult['data']['page_info']['next_page_token'])) {
                    $nextPageToken = $subscriptionsResult['data']['page_info']['next_page_token'];
                    $this->writeSyncLog("INFO: Mais assinaturas disponíveis. Próximo page_token: " . $nextPageToken);
                    $hasMorePages = true;
                } else {
                    $this->writeSyncLog("INFO: Não há mais next_page_token. Fim da paginação.");
                    $hasMorePages = false;
                }
            } else {
                $this->writeSyncLog("ERRO: Resposta inesperada da API de assinaturas: " . json_encode($subscriptionsResult));
                break;
            }
        }

        $this->writeSyncLog("Sincronização de assinaturas concluída. Total de assinaturas sincronizadas: " . $totalSynced);
        return ['success' => true, 'message' => 'Sincronização de assinaturas concluída.', 'total_synced' => $totalSynced];
    }
}