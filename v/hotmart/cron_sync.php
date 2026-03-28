<?php
/**
 * Script para executar sincronização via cron job
 * Execute: php /caminho/para/Hotmart/cron_sync.php
 */

// Configurar exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define o tempo máximo de execução (10 minutos)
set_time_limit(600);

// Incluir arquivos necessários
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/hotmart.php';
require_once __DIR__ . '/../hotmart.php';
require_once __DIR__ . '/HotmartProgressSync.php';

echo "[" . date('Y-m-d H:i:s') . "] Iniciando sincronização automática de progresso Hotmart...\n";

try {
    $hotmartApi = new HotmartAPI();
    $syncManager = new HotmartProgressSync($pdo, $hotmartApi);
    
    $result = $syncManager->syncAllProgress();
    
    if ($result['success']) {
        echo "[" . date('Y-m-d H:i:s') . "] Sincronização concluída com sucesso!\n";
        echo "  - Usuários processados: " . $result['users_processed'] . "\n";
        echo "  - Registros de progresso: " . ($result['progress_records'] ?? 0) . "\n";
        echo "  - Erros: " . ($result['errors'] ?? 0) . "\n";
        echo "  - Duração: " . ($result['duration'] ?? 0) . "s\n";
        exit(0);
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] Erro na sincronização: " . $result['message'] . "\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Erro crítico: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}