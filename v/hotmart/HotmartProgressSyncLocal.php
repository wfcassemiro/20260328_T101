<?php
/**
 * Sincronização alternativa - Baseada nos usuários do banco local
 * Em vez de buscar usuários da API, usa os que já existem no banco
 */

class HotmartProgressSyncLocal {
    private $pdo;
    private $hotmartApi;
    private $logFile;
    private $dbConfig;
    
    public function __construct($pdo, $hotmartApi) {
        $this->pdo = $pdo;
        $this->hotmartApi = $hotmartApi;
        $this->logFile = __DIR__ . '/../logs/hotmart_progress_sync_local.log';
        
        // Salvar configuração do banco para reconexão
        // Usar as mesmas variáveis do config/database.php
        $this->dbConfig = [
            'host' => 'localhost',
            'name' => 'u335416710_t101_db',
            'user' => 'u335416710_t101',
            'pass' => 'Pa392ap!'
        ];
    }
    
    /**
     * Reconectar ao banco se conexão caiu
     */
    private function reconnectIfNeeded() {
        try {
            // Tentar fazer um ping simples
            $this->pdo->query('SELECT 1');
        } catch (PDOException $e) {
            $this->log('Conexão perdida, reconectando...', 'WARNING');
            try {
                $this->pdo = new PDO(
                    "mysql:host={$this->dbConfig['host']};dbname={$this->dbConfig['name']};charset=utf8mb4",
                    $this->dbConfig['user'],
                    $this->dbConfig['pass'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                    ]
                );
                $this->log('Reconexão bem-sucedida!', 'INFO');
            } catch (PDOException $e2) {
                $this->log('Falha na reconexão: ' . $e2->getMessage(), 'ERROR');
                throw $e2;
            }
        }
    }
    
    /**
     * Log de mensagens
     */
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        // Garantir UTF-8 adequadamente
        if (!mb_check_encoding($message, 'UTF-8')) {
            $message = utf8_encode($message);
        }
        $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        error_log($logMessage);
    }
    
    /**
     * Sincronizar progresso de usuários locais
     */
    public function syncAllProgress() {
        $this->log('========================================');
        $this->log('Iniciando sincronização baseada em usuários locais');
        
        $startTime = microtime(true);
        $syncId = $this->createSyncLog('PROGRESS');
        
        try {
            // 0. Buscar TODAS as assinaturas da Hotmart uma vez (para mapear subscription_id -> ucode)
            $this->log('Buscando todas as assinaturas da Hotmart para mapear IDs...');
            $subscriptionMap = $this->buildSubscriptionMap();
            $this->log("Mapeamento criado com " . count($subscriptionMap) . " assinaturas");
            
            // 1. Buscar usuários do banco local que têm dados Hotmart
            $localUsers = $this->getLocalUsersWithHotmart();
            
            if (empty($localUsers)) {
                $this->log('Nenhum usuário local com dados Hotmart encontrado', 'WARNING');
                $this->updateSyncLog($syncId, 0, 0, 'SUCCESS', 'Nenhum usuário para sincronizar');
                return ['success' => true, 'message' => 'Nenhum usuário para sincronizar', 'users_processed' => 0];
            }
            
            $this->log('Total de usuários locais com dados Hotmart: ' . count($localUsers));
            
            // 2. Processar em lotes de 50 usuários para evitar timeout
            $batchSize = 50;
            $totalBatches = ceil(count($localUsers) / $batchSize);
            
            $usersProcessed = 0;
            $progressRecords = 0;
            $errorsCount = 0;
            $usersWithProgress = 0;
            
            for ($batch = 0; $batch < $totalBatches; $batch++) {
                $offset = $batch * $batchSize;
                $batchUsers = array_slice($localUsers, $offset, $batchSize);
                
                $this->log("Processando lote " . ($batch + 1) . "/{$totalBatches} (" . count($batchUsers) . " usuários)");
                
                foreach ($batchUsers as $user) {
                    try {
                        // Verificar conexão a cada 10 usuários
                        if ($usersProcessed % 10 === 0) {
                            $this->reconnectIfNeeded();
                        }
                        
                        $result = $this->syncUserProgressLocal($user, $subscriptionMap);
                        if ($result['success']) {
                            $usersProcessed++;
                            $progressRecords += $result['progress_records'];
                            if ($result['progress_records'] > 0) {
                                $usersWithProgress++;
                            }
                        } else {
                            $errorsCount++;
                        }
                    } catch (Exception $e) {
                        $errorsCount++;
                        $this->log('Erro ao processar usuário ' . $user['email'] . ': ' . $e->getMessage(), 'ERROR');
                    }
                    
                    // Pequena pausa para não sobrecarregar a API
                    usleep(100000); // 0.1 segundo
                }
                
                // Log de progresso do lote
                $this->log("Lote " . ($batch + 1) . " concluído: {$usersProcessed} processados, {$usersWithProgress} com progresso");
                
                // Atualizar log intermediário
                $this->updateSyncLog($syncId, $usersProcessed, $errorsCount, 'RUNNING', 
                    "Em progresso: {$usersProcessed}/{" . count($localUsers) . "} usuários");
            }
            
            $duration = round(microtime(true) - $startTime, 2);
            $this->log("Sincronização concluída em {$duration}s");
            $this->log("Usuários processados: {$usersProcessed}");
            $this->log("Usuários com progresso: {$usersWithProgress}");
            $this->log("Registros de progresso: {$progressRecords}");
            $this->log("Erros: {$errorsCount}");
            
            $status = $errorsCount === 0 ? 'SUCCESS' : ($usersProcessed > 0 ? 'PARTIAL' : 'FAILED');
            $message = "Processados: {$usersProcessed}, Com progresso: {$usersWithProgress}, Registros: {$progressRecords}, Erros: {$errorsCount}";
            $this->updateSyncLog($syncId, $usersProcessed, $errorsCount, $status, $message);
            
            return [
                'success' => true,
                'users_processed' => $usersProcessed,
                'users_with_progress' => $usersWithProgress,
                'progress_records' => $progressRecords,
                'errors' => $errorsCount,
                'duration' => $duration
            ];
            
        } catch (Exception $e) {
            $this->log('Erro crítico na sincronização: ' . $e->getMessage(), 'ERROR');
            $this->updateSyncLog($syncId, 0, 1, 'FAILED', $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Construir mapeamento de email -> ucode usando Club Users
     */
    private function buildSubscriptionMap() {
        $map = [];
        
        try {
            // Tentar buscar do Club Users (recomendado pela documentação)
            $subdomain = defined('HOTMART_SUBDOMAIN') ? HOTMART_SUBDOMAIN : 'assinaturapremiumplustranslato';
            $this->log("  → Chamando getClubUsers({$subdomain})...");
            $result = $this->hotmartApi->getClubUsers($subdomain);
            
            $this->log('  ← Resposta da API Club Users:');
            $this->log('    - Success: ' . ($result['success'] ? 'true' : 'false'));
            
            if ($result['success'] && isset($result['data']) && is_array($result['data'])) {
                $users = $result['data'];
                $this->log('    - Usuários encontrados no Club: ' . count($users));
                
                if (!empty($users)) {
                    // Mostrar exemplo
                    $this->log('    - Exemplo (primeiro): ' . substr(json_encode($users[0], JSON_UNESCAPED_UNICODE), 0, 500));
                    
                    foreach ($users as $user) {
                        $email = strtolower(trim($user['email'] ?? ''));
                        $ucode = $user['ucode'] ?? $user['subscriber_code'] ?? $user['code'] ?? null;
                        
                        if ($email && $ucode) {
                            $map[$email] = $ucode;
                            $this->log("    + Mapeado: {$email} → {$ucode}");
                        }
                    }
                }
            }
            
            // Se Club Users não funcionou ou retornou vazio, tentar Subscriptions
            if (empty($map)) {
                $this->log('  → Club Users vazio, tentando Subscriptions...');
                $subsResult = $this->hotmartApi->getSubscriptions('ACTIVE');
                
                $this->log('  ← Resposta da API Subscriptions:');
                $this->log('    - Success: ' . ($subsResult['success'] ? 'true' : 'false'));
                
                if ($subsResult['success'] && isset($subsResult['data']['items'])) {
                    $subs = $subsResult['data']['items'];
                    $this->log('    - Assinaturas encontradas: ' . count($subs));
                    
                    if (!empty($subs)) {
                        // Mostrar exemplo
                        $this->log('    - Exemplo (primeira): ' . substr(json_encode($subs[0], JSON_UNESCAPED_UNICODE), 0, 500));
                        
                        foreach ($subs as $subscription) {
                            // Mapear por EMAIL (mais confiável que subscription_id)
                            $email = strtolower(trim($subscription['subscriber']['email'] ?? ''));
                            $ucode = $subscription['subscriber']['ucode'] 
                                    ?? $subscription['subscriber']['subscriber_code']
                                    ?? $subscription['subscriber']['code']
                                    ?? null;
                            
                            if ($email && $ucode) {
                                $map[$email] = $ucode;
                                $this->log("    + Mapeado: {$email} → {$ucode}");
                            }
                        }
                    }
                }
            }
            
            $this->log("  ✓ Total mapeado: " . count($map) . " emails → ucode");
            
            if (count($map) === 0) {
                $this->log("  ⚠️ ATENÇÃO: Nenhum usuário foi mapeado!", 'WARNING');
                $this->log("  As APIs não retornaram usuários com ucode", 'WARNING');
            }
            
        } catch (Exception $e) {
            $this->log("  ✗ Erro ao criar mapeamento: " . $e->getMessage(), 'ERROR');
        }
        
        return $map;
    }
    
    /**
     * Buscar usuários do banco local que têm dados Hotmart
     */
    private function getLocalUsersWithHotmart() {
        $this->log('Buscando usuários locais com dados Hotmart...');
        
        $stmt = $this->pdo->query("
            SELECT 
                id, 
                email, 
                name, 
                hotmart_subscription_id, 
                hotmart_ucode,
                hotmart_status
            FROM users 
            WHERE 
                is_active = 1
                AND (
                    (hotmart_subscription_id IS NOT NULL AND hotmart_subscription_id != '')
                    OR (hotmart_ucode IS NOT NULL AND hotmart_ucode != '')
                )
            ORDER BY 
                CASE 
                    WHEN hotmart_ucode IS NOT NULL THEN 1
                    WHEN hotmart_subscription_id IS NOT NULL THEN 2
                    ELSE 3
                END,
                created_at DESC
            LIMIT 500
        ");
        
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->log('Usuários encontrados: ' . count($users));
        
        // Estatísticas
        $withSubId = 0;
        $withUcode = 0;
        $withBoth = 0;
        
        foreach ($users as $user) {
            $hasSubId = !empty($user['hotmart_subscription_id']);
            $hasUcode = !empty($user['hotmart_ucode']);
            
            if ($hasSubId) $withSubId++;
            if ($hasUcode) $withUcode++;
            if ($hasSubId && $hasUcode) $withBoth++;
        }
        
        $this->log("  - Com hotmart_subscription_id: {$withSubId}");
        $this->log("  - Com hotmart_ucode: {$withUcode}");
        $this->log("  - Com ambos: {$withBoth}");
        
        if (count($users) === 0) {
            $this->log("⚠️ ATENÇÃO: Nenhum usuário tem hotmart_ucode ou hotmart_subscription_id!", 'WARNING');
            $this->log("Os usuários precisam desses IDs para buscar progresso na API", 'WARNING');
        }
        
        return $users;
    }
    
    /**
     * Sincronizar progresso de um usuário específico do banco local
     */
    private function syncUserProgressLocal($user, $subscriptionMap = []) {
        $userId = $user['id'];
        $email = strtolower(trim($user['email']));
        $name = $user['name'];
        
        // Prioridade: hotmart_ucode > mapeamento por email > hotmart_subscription_id
        $hotmartUcode = $user['hotmart_ucode'] ?? null;
        
        // Log detalhado dos IDs disponíveis
        $this->log("Processando usuário: {$name} ({$email})");
        $this->log("  - hotmart_ucode: " . ($hotmartUcode ?: 'NULL'));
        $this->log("  - email: {$email}");
        
        // Se tem ucode, usar diretamente
        if ($hotmartUcode) {
            $this->log("  ✓ Usando ucode existente: {$hotmartUcode}");
            return $this->fetchAndSaveProgress($userId, $hotmartUcode, $email);
        }
        
        // Tentar buscar ucode no mapeamento por email
        if (isset($subscriptionMap[$email])) {
            $ucode = $subscriptionMap[$email];
            $this->log("  ✓ Ucode encontrado no mapeamento por email: {$ucode}");
            
            // Salvar o ucode no banco para uso futuro
            $this->updateUserHotmartUcode($userId, $ucode);
            
            // Buscar progresso com o ucode
            return $this->fetchAndSaveProgress($userId, $ucode, $email);
        }
        
        $this->log("  ✗ Email não encontrado no mapeamento", 'WARNING');
        $this->log("  O usuário não está ativo no Club ou Subscriptions", 'WARNING');
        return ['success' => false, 'message' => 'Email não encontrado no mapeamento'];
    }
    
    /**
     * Obter ucode a partir do subscription_id
     */
    private function getUcodeFromSubscription($subscriptionId) {
        try {
            // Buscar detalhes da assinatura
            $this->log("    → Buscando detalhes da subscription {$subscriptionId}");
            
            // A API de subscriptions retorna os dados do subscriber
            $result = $this->hotmartApi->getSubscriptions('ACTIVE');
            
            if ($result['success'] && isset($result['data']['items'])) {
                foreach ($result['data']['items'] as $subscription) {
                    if (isset($subscription['subscription_id']) && $subscription['subscription_id'] == $subscriptionId) {
                        // Encontrou a assinatura, extrair ucode do subscriber
                        $ucode = $subscription['subscriber']['ucode'] 
                                ?? $subscription['subscriber']['subscriber_code']
                                ?? $subscription['subscriber']['code']
                                ?? null;
                        
                        if ($ucode) {
                            $this->log("    ✓ Ucode encontrado: {$ucode}");
                            return $ucode;
                        }
                    }
                }
            }
            
            $this->log("    ✗ Subscription {$subscriptionId} não encontrada ou sem ucode");
            return null;
            
        } catch (Exception $e) {
            $this->log("    ✗ Erro ao buscar subscription: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }
    
    /**
     * Buscar progresso e salvar
     */
    private function fetchAndSaveProgress($userId, $ucode, $email) {
        $this->log("  → Chamando getUserProgress({$ucode})...");
        $progressResult = $this->hotmartApi->getUserProgress($ucode);
        
        // LOG DETALHADO DA RESPOSTA DA API
        $this->log("  ← Resposta da API:");
        $this->log("    - Success: " . ($progressResult['success'] ? 'true' : 'false'));
        $this->log("    - HTTP Code: " . ($progressResult['http_code'] ?? 'N/A'));
        
        if (isset($progressResult['message'])) {
            $this->log("    - Message: " . $progressResult['message']);
        }
        
        if (isset($progressResult['data'])) {
            $dataCount = is_array($progressResult['data']) ? count($progressResult['data']) : 'N/A';
            $this->log("    - Data count: " . $dataCount);
            
            if (is_array($progressResult['data']) && count($progressResult['data']) > 0) {
                $this->log("    - Estrutura: " . json_encode(array_keys($progressResult['data']), JSON_UNESCAPED_UNICODE));
                $this->log("    - Exemplo (primeiros 500 chars): " . substr(json_encode($progressResult['data'], JSON_UNESCAPED_UNICODE), 0, 500));
            } else {
                $this->log("    - Data está vazio ou não é array");
            }
        } else {
            $this->log("    - Sem 'data' na resposta");
        }
        
        if (!$progressResult['success']) {
            $this->log("  ✗ API retornou falha", 'WARNING');
            return ['success' => true, 'progress_records' => 0];
        }
        
        $progressData = $progressResult['data'] ?? null;
        
        if (!$progressData || empty($progressData)) {
            $this->log("  ✗ Nenhum dado de progresso retornado");
            return ['success' => true, 'progress_records' => 0];
        }
        
        // Processar e salvar dados de progresso
        $progressRecords = $this->processProgressData($userId, $ucode, $progressData);
        
        // Atualizar timestamp
        $this->updateUserSyncTimestamp($userId);
        
        $this->log("  ✓ Progresso sincronizado: {$progressRecords} registros");
        
        return ['success' => true, 'progress_records' => $progressRecords];
    }
    
    /**
     * Processar dados de progresso
     */
    private function processProgressData($userId, $hotmartId, $progressData) {
        $recordsProcessed = 0;
        
        // Extrair itens de diferentes estruturas possíveis
        $items = [];
        
        if (isset($progressData['items'])) {
            $items = $progressData['items'];
        } elseif (isset($progressData['pages'])) {
            $items = $progressData['pages'];
        } elseif (isset($progressData['lessons'])) {
            $items = $progressData['lessons'];
        } elseif (is_array($progressData) && !empty($progressData)) {
            $items = $progressData;
        }
        
        if (empty($items)) {
            return 0;
        }
        
        $this->log("    Processando " . count($items) . " itens de progresso");
        
        foreach ($items as $item) {
            try {
                if ($this->saveProgressRecord($userId, $hotmartId, $item)) {
                    $recordsProcessed++;
                }
            } catch (Exception $e) {
                $this->log("    Erro ao salvar item: " . $e->getMessage(), 'ERROR');
            }
        }
        
        return $recordsProcessed;
    }
    
    /**
     * Salvar registro de progresso
     */
    private function saveProgressRecord($userId, $hotmartId, $item) {
        // Extrair informações do item
        $lessonId = $item['id'] ?? $item['lesson_id'] ?? $item['page_id'] ?? null;
        $lessonTitle = $item['title'] ?? $item['name'] ?? '';
        $isCompleted = $item['completed'] ?? $item['is_completed'] ?? false;
        $progress = $item['progress'] ?? ($isCompleted ? 100 : 0);
        $watchTime = $item['watch_time'] ?? $item['time_watched'] ?? 0;
        $completedAt = $item['completed_at'] ?? $item['finished_at'] ?? null;
        
        // Tentar mapear para lecture local
        $lectureId = $this->mapToLocalLecture($lessonId, $lessonTitle);
        
        if (!$lectureId) {
            $this->log("      Não foi possível mapear: {$lessonTitle}", 'DEBUG');
            return false;
        }
        
        // Inserir ou atualizar
        $sql = "
            INSERT INTO hotmart_user_progress 
            (user_id, lecture_id, hotmart_user_id, hotmart_lesson_id, progress_percent, 
             is_completed, watch_time_seconds, completed_at, raw_data, last_synced_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                hotmart_lesson_id = VALUES(hotmart_lesson_id),
                progress_percent = VALUES(progress_percent),
                is_completed = VALUES(is_completed),
                watch_time_seconds = VALUES(watch_time_seconds),
                completed_at = VALUES(completed_at),
                raw_data = VALUES(raw_data),
                last_synced_at = NOW()
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $userId,
            $lectureId,
            $hotmartId,
            $lessonId,
            min(100, max(0, intval($progress))),
            $isCompleted ? 1 : 0,
            intval($watchTime),
            $completedAt,
            json_encode($item)
        ]);
        
        return true;
    }
    
    /**
     * Mapear lesson Hotmart para lecture local
     */
    private function mapToLocalLecture($lessonId, $lessonTitle) {
        if (empty($lessonTitle)) {
            return null;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT id FROM lectures 
            WHERE LOWER(title) LIKE LOWER(CONCAT('%', ?, '%'))
            LIMIT 1
        ");
        $stmt->execute([$lessonTitle]);
        $lecture = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $lecture ? $lecture['id'] : null;
    }
    
    /**
     * Atualizar timestamp de sincronização
     */
    private function updateUserSyncTimestamp($userId) {
        $stmt = $this->pdo->prepare("UPDATE users SET last_progress_sync = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    }
    
    /**
     * Atualizar hotmart_ucode
     */
    private function updateUserHotmartUcode($userId, $ucode) {
        $stmt = $this->pdo->prepare("UPDATE users SET hotmart_ucode = ? WHERE id = ?");
        $stmt->execute([$ucode, $userId]);
    }
    
    /**
     * Criar log de sincronização
     */
    private function createSyncLog($syncType) {
        $this->reconnectIfNeeded();
        $syncId = $this->generateUUID();
        $stmt = $this->pdo->prepare("
            INSERT INTO hotmart_sync_logs 
            (id, sync_type, users_synced, errors_count, status, started_at)
            VALUES (?, ?, 0, 0, 'RUNNING', NOW())
        ");
        $stmt->execute([$syncId, $syncType]);
        return $syncId;
    }
    
    /**
     * Atualizar log de sincronização
     */
    private function updateSyncLog($syncId, $usersSynced, $errorsCount, $status, $message) {
        try {
            $this->reconnectIfNeeded();
            $stmt = $this->pdo->prepare("
                UPDATE hotmart_sync_logs 
                SET users_synced = ?, errors_count = ?, status = ?, message = ?, completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$usersSynced, $errorsCount, $status, $message, $syncId]);
        } catch (PDOException $e) {
            $this->log('Erro ao atualizar sync log: ' . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * Gerar UUID
     */
    private function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Obter estatísticas
     */
    public function getProgressStats() {
        $stats = [];
        
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM hotmart_user_progress");
        $stats['total_records'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM hotmart_user_progress WHERE is_completed = 1");
        $stats['completed'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt = $this->pdo->query("SELECT COUNT(DISTINCT user_id) as total FROM hotmart_user_progress");
        $stats['users_with_progress'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $stmt = $this->pdo->query("
            SELECT started_at, status, users_synced, errors_count, message 
            FROM hotmart_sync_logs 
            WHERE sync_type = 'PROGRESS'
            ORDER BY started_at DESC 
            LIMIT 1
        ");
        $stats['last_sync'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $stats;
    }
}