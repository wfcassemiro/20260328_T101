<?php
/**
 * Classe para sincronização de progresso de aulas da Hotmart
 * Fase 1: Obter dados de progresso dos assinantes
 */

class HotmartProgressSync {
    private $pdo;
    private $hotmartApi;
    private $subdomain;
    private $logFile;
    
    public function __construct($pdo, $hotmartApi) {
        $this->pdo = $pdo;
        $this->hotmartApi = $hotmartApi;
        $this->subdomain = defined('HOTMART_SUBDOMAIN') ? HOTMART_SUBDOMAIN : 'assinaturapremiumplustranslato';
        // Log no diretório v/logs/ que já existe
        $this->logFile = __DIR__ . '/../logs/hotmart_progress_sync.log';
    }
    
    /**
     * Log de mensagens
     */
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
        error_log($logMessage);
    }
    
    /**
     * Sincronizar progresso de todos os assinantes
     */
    public function syncAllProgress() {
        $this->log('========================================');
        $this->log('Iniciando sincronização de progresso');
        
        $startTime = microtime(true);
        $syncId = $this->createSyncLog('PROGRESS');
        
        try {
            // 1. Obter lista de usuários da Hotmart
            $hotmartUsers = $this->getHotmartUsers();
            
            if (empty($hotmartUsers)) {
                $this->log('Nenhum usuário encontrado na Hotmart', 'WARNING');
                $this->updateSyncLog($syncId, 0, 0, 'SUCCESS', 'Nenhum usuário para sincronizar');
                return ['success' => true, 'message' => 'Nenhum usuário para sincronizar', 'users_processed' => 0];
            }
            
            $this->log('Total de usuários encontrados: ' . count($hotmartUsers));
            
            // 2. Processar cada usuário
            $usersProcessed = 0;
            $errorsCount = 0;
            $progressRecords = 0;
            
            foreach ($hotmartUsers as $hotmartUser) {
                try {
                    $result = $this->syncUserProgress($hotmartUser);
                    if ($result['success']) {
                        $usersProcessed++;
                        $progressRecords += $result['progress_records'];
                    } else {
                        $errorsCount++;
                    }
                } catch (Exception $e) {
                    $errorsCount++;
                    $this->log('Erro ao processar usuário: ' . $e->getMessage(), 'ERROR');
                }
            }
            
            $duration = round(microtime(true) - $startTime, 2);
            $this->log("Sincronização concluída em {$duration}s");
            $this->log("Usuários processados: {$usersProcessed}");
            $this->log("Registros de progresso: {$progressRecords}");
            $this->log("Erros: {$errorsCount}");
            
            $status = $errorsCount === 0 ? 'SUCCESS' : ($usersProcessed > 0 ? 'PARTIAL' : 'FAILED');
            $message = "Processados: {$usersProcessed}, Registros: {$progressRecords}, Erros: {$errorsCount}";
            $this->updateSyncLog($syncId, $usersProcessed, $errorsCount, $status, $message);
            
            return [
                'success' => true,
                'users_processed' => $usersProcessed,
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
     * Obter lista de usuários da Hotmart
     */
    private function getHotmartUsers() {
        $users = [];
        
        // Tentar obter usuários do Club
        $this->log('Tentando obter usuários do Hotmart Club...');
        $clubResult = $this->hotmartApi->getClubUsers($this->subdomain);
        
        // LOG DETALHADO DA RESPOSTA DO CLUB
        $this->log('=== RESPOSTA COMPLETA DO CLUB API ===');
        $this->log('Success: ' . ($clubResult['success'] ? 'true' : 'false'));
        $this->log('Response: ' . json_encode($clubResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        if ($clubResult['success']) {
            // Tentar diferentes estruturas de resposta
            if (isset($clubResult['data']['items'])) {
                $users = $clubResult['data']['items'];
                $this->log('Usuários em data->items: ' . count($users));
            } elseif (isset($clubResult['data']) && is_array($clubResult['data'])) {
                $users = $clubResult['data'];
                $this->log('Usuários em data: ' . count($users));
            } elseif (isset($clubResult['items'])) {
                $users = $clubResult['items'];
                $this->log('Usuários em items: ' . count($users));
            } else {
                $this->log('Estrutura não reconhecida. Chaves disponíveis: ' . implode(', ', array_keys($clubResult)));
            }
            
            $this->log('Total de usuários do Club: ' . count($users));
            
            // Se ainda está vazio, logar estrutura completa
            if (empty($users)) {
                $this->log('AVISO: Nenhum usuário encontrado, mas API retornou sucesso!', 'WARNING');
                $this->log('Pode significar que não há usuários no Club ou estrutura diferente', 'WARNING');
            }
        } else {
            $this->log('Falha ao obter usuários do Club, tentando assinaturas...', 'WARNING');
            
            // Fallback: tentar obter assinaturas
            $subsResult = $this->hotmartApi->getSubscriptions('ACTIVE');
            
            // LOG DETALHADO DA RESPOSTA DE SUBSCRIPTIONS
            $this->log('=== RESPOSTA COMPLETA DO SUBSCRIPTIONS API ===');
            $this->log('Success: ' . ($subsResult['success'] ? 'true' : 'false'));
            $this->log('Response: ' . json_encode($subsResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            if ($subsResult['success']) {
                if (isset($subsResult['data']['items'])) {
                    $users = $subsResult['data']['items'];
                    $this->log('Usuários em data->items: ' . count($users));
                } elseif (isset($subsResult['data']) && is_array($subsResult['data'])) {
                    $users = $subsResult['data'];
                    $this->log('Usuários em data: ' . count($users));
                } elseif (isset($subsResult['items'])) {
                    $users = $subsResult['items'];
                    $this->log('Usuários em items: ' . count($users));
                }
                
                $this->log('Total de usuários de assinaturas: ' . count($users));
            } else {
                $this->log('Falha ao obter assinaturas: ' . json_encode($subsResult), 'ERROR');
            }
        }
        
        // LOG FINAL
        $this->log('=== TOTAL DE USUÁRIOS PARA PROCESSAR: ' . count($users) . ' ===');
        
        return $users;
    }
    
    /**
     * Sincronizar progresso de um usuário específico
     */
    private function syncUserProgress($hotmartUser) {
        // Extrair informações do usuário
        $email = strtolower(trim($hotmartUser['email'] ?? ''));
        $name = $hotmartUser['name'] ?? $hotmartUser['subscriber']['name'] ?? 'Nome não informado';
        
        // Tentar obter ucode (UUID) do usuário
        $hotmartUcode = $hotmartUser['ucode'] 
                       ?? $hotmartUser['subscriber']['ucode'] ?? null
                       ?? $hotmartUser['subscriber']['subscriber_code'] ?? null
                       ?? $hotmartUser['subscription_id'] ?? null
                       ?? null;
        
        if (!$hotmartUcode) {
            $this->log("Usuário {$email} sem ID válido, pulando...", 'WARNING');
            return ['success' => false, 'message' => 'ID inválido'];
        }
        
        $this->log("Processando usuário: {$name} ({$email}) - ID: {$hotmartUcode}");
        
        // Verificar se usuário existe no banco local
        $localUser = $this->findOrCreateLocalUser($email, $name, $hotmartUcode);
        
        if (!$localUser) {
            $this->log("Não foi possível encontrar/criar usuário local para {$email}", 'ERROR');
            return ['success' => false, 'message' => 'Usuário local não encontrado'];
        }
        
        // Obter progresso do usuário da API Hotmart
        $progressResult = $this->hotmartApi->getUserProgress($hotmartUcode);
        
        if (!$progressResult['success']) {
            $this->log("Nenhum progresso encontrado para {$email}", 'WARNING');
            return ['success' => true, 'progress_records' => 0];
        }
        
        // Processar dados de progresso
        $progressData = $progressResult['data'];
        $progressRecords = $this->processProgressData($localUser['id'], $hotmartUcode, $progressData);
        
        // Atualizar timestamp de última sincronização
        $this->updateUserSyncTimestamp($localUser['id']);
        
        $this->log("Progresso sincronizado para {$email}: {$progressRecords} registros");
        
        return ['success' => true, 'progress_records' => $progressRecords];
    }
    
    /**
     * Encontrar ou criar usuário local
     */
    private function findOrCreateLocalUser($email, $name, $hotmartUcode) {
        if (empty($email)) {
            return null;
        }
        
        // Buscar usuário por email
        $stmt = $this->pdo->prepare("SELECT id, email, name, hotmart_ucode FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Atualizar hotmart_ucode se não estiver setado
            if (empty($user['hotmart_ucode']) && !empty($hotmartUcode)) {
                $updateStmt = $this->pdo->prepare("UPDATE users SET hotmart_ucode = ? WHERE id = ?");
                $updateStmt->execute([$hotmartUcode, $user['id']]);
                $user['hotmart_ucode'] = $hotmartUcode;
            }
            return $user;
        }
        
        // Se não existe, retornar null (não vamos criar usuários aqui)
        // Isso deve ser feito pelo webhook
        $this->log("Usuário {$email} não encontrado no banco local", 'WARNING');
        return null;
    }
    
    /**
     * Processar dados de progresso e salvar no banco
     */
    private function processProgressData($userId, $hotmartUcode, $progressData) {
        $recordsProcessed = 0;
        
        // A API pode retornar diferentes formatos
        // Tentar extrair itens/páginas/lessons
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
            $this->log("Nenhum item de progresso encontrado para userId: {$userId}", 'DEBUG');
            return 0;
        }
        
        foreach ($items as $item) {
            try {
                $this->saveProgressRecord($userId, $hotmartUcode, $item);
                $recordsProcessed++;
            } catch (Exception $e) {
                $this->log("Erro ao salvar registro de progresso: " . $e->getMessage(), 'ERROR');
            }
        }
        
        return $recordsProcessed;
    }
    
    /**
     * Salvar registro individual de progresso
     */
    private function saveProgressRecord($userId, $hotmartUcode, $item) {
        // Extrair informações do item
        $lessonId = $item['id'] ?? $item['lesson_id'] ?? $item['page_id'] ?? null;
        $lessonTitle = $item['title'] ?? $item['name'] ?? '';
        $isCompleted = $item['completed'] ?? $item['is_completed'] ?? false;
        $progress = $item['progress'] ?? ($isCompleted ? 100 : 0);
        $watchTime = $item['watch_time'] ?? $item['time_watched'] ?? 0;
        $completedAt = $item['completed_at'] ?? $item['finished_at'] ?? null;
        
        // Tentar mapear para lecture_id local
        // Por enquanto, vamos salvar mesmo sem mapeamento
        $lectureId = $this->mapHotmartLessonToLecture($lessonId, $lessonTitle);
        
        if (!$lectureId) {
            // Se não conseguir mapear, vamos criar um registro genérico
            // Isso será melhorado na Fase 2
            $this->log("Não foi possível mapear lesson {$lessonId} para lecture local", 'DEBUG');
            return;
        }
        
        // Inserir ou atualizar progresso
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
            $hotmartUcode,
            $lessonId,
            min(100, max(0, intval($progress))),
            $isCompleted ? 1 : 0,
            intval($watchTime),
            $completedAt,
            json_encode($item)
        ]);
    }
    
    /**
     * Mapear lesson da Hotmart para lecture local
     * Por enquanto, retorna null - será implementado na Fase 2
     */
    private function mapHotmartLessonToLecture($lessonId, $lessonTitle) {
        // Fase 2: Implementar lógica de mapeamento
        // Por enquanto, tentar buscar por título similar
        
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
     * Atualizar timestamp de última sincronização do usuário
     */
    private function updateUserSyncTimestamp($userId) {
        $stmt = $this->pdo->prepare("UPDATE users SET last_progress_sync = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    }
    
    /**
     * Criar registro de log de sincronização
     */
    private function createSyncLog($syncType) {
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
     * Atualizar registro de log de sincronização
     */
    private function updateSyncLog($syncId, $usersSynced, $errorsCount, $status, $message) {
        $stmt = $this->pdo->prepare("
            UPDATE hotmart_sync_logs 
            SET users_synced = ?, errors_count = ?, status = ?, message = ?, completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$usersSynced, $errorsCount, $status, $message, $syncId]);
    }
    
    /**
     * Gerar UUID v4
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
     * Obter estatísticas de progresso
     */
    public function getProgressStats() {
        $stats = [];
        
        // Total de registros
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM hotmart_user_progress");
        $stats['total_records'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Total de palestras completadas
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM hotmart_user_progress WHERE is_completed = 1");
        $stats['completed'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Total de usuários com progresso
        $stmt = $this->pdo->query("SELECT COUNT(DISTINCT user_id) as total FROM hotmart_user_progress");
        $stats['users_with_progress'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Última sincronização
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