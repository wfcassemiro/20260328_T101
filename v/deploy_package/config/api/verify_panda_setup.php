<?php
/**
 * Verificação da API do Panda Video
 * Este script verifica se a integração com Panda Video está funcionando corretamente
 */

session_start();
require_once '../database.php';

// Configurar PDO para usar buffering (resolver erro 2014)
$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

// Função de log para verificação
function writeToVerificationLog($message) {
    $log_file = __DIR__ . '/verification.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] [VERIFICATION] $message\n", FILE_APPEND);
}

writeToVerificationLog("=== INÍCIO DA VERIFICAÇÃO DA API PANDA VIDEO ===");

$response = [
    'success' => false,
    'message' => '',
    'database_ready' => false,
    'api_files_ready' => false,
    'panda_lectures_count' => 0,
    'missing_column' => false,
    'missing_files' => [],
    'timestamp' => date('Y-m-d H:i:s')
];

// Verificar se a base de dados tem a coluna necessária
try {
    // Usar uma única consulta com fetchAll para evitar conflitos
    $stmt = $pdo->prepare("SHOW COLUMNS FROM access_logs LIKE 'last_watched_seconds'");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = null; // Liberar o statement
    
    if (empty($columns)) {
        writeToVerificationLog("ERRO: Coluna 'last_watched_seconds' NÃO encontrada na tabela access_logs");
        writeToVerificationLog("SOLUÇÃO: Execute o arquivo database_migration_video_progress.sql");
        $response['message'] = 'Database missing required column. Run migration SQL first.';
        $response['missing_column'] = true;
        echo json_encode($response);
        exit;
    } else {
        writeToVerificationLog("OK: Coluna 'last_watched_seconds' encontrada na tabela access_logs");
        $response['database_ready'] = true;
    }
    
} catch (PDOException $e) {
    writeToVerificationLog("ERRO: Falha ao verificar estrutura do banco: " . $e->getMessage());
    $response['message'] = 'Database error: ' . $e->getMessage();
    echo json_encode($response);
    exit;
}

// Verificar se os arquivos da API existem
$required_files = [
    __DIR__ . '/update_lecture_progress.php',
    __DIR__ . '/log_message.php'
];

$missing_files = [];
foreach ($required_files as $file) {
    $filename = basename($file);
    if (!file_exists($file)) {
        $missing_files[] = $filename;
        writeToVerificationLog("ERRO: Arquivo necessário não encontrado: $filename");
    } else {
        writeToVerificationLog("OK: Arquivo encontrado: $filename");
    }
}

if (!empty($missing_files)) {
    $response['message'] = 'Missing required API files';
    $response['missing_files'] = $missing_files;
    echo json_encode($response);
    exit;
} else {
    $response['api_files_ready'] = true;
}

// Verificar se há palestras com embed do Panda Video
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM lectures WHERE embed_code LIKE '%panda%'");
    $stmt->execute();
    $panda_lectures = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt = null; // Liberar o statement
    
    $response['panda_lectures_count'] = (int)($panda_lectures['total'] ?? 0);
    
    writeToVerificationLog("INFO: Encontradas {$response['panda_lectures_count']} palestras com código do Panda Video");
    
    if ($response['panda_lectures_count'] == 0) {
        writeToVerificationLog("AVISO: Nenhuma palestra com embed do Panda Video encontrada");
    }
    
} catch (PDOException $e) {
    writeToVerificationLog("ERRO: Falha ao contar palestras Panda: " . $e->getMessage());
    $response['panda_lectures_count'] = 0;
}

writeToVerificationLog("=== VERIFICAÇÃO CONCLUÍDA COM SUCESSO ===");

$response['success'] = true;
$response['message'] = 'Panda Video API verification completed successfully';

echo json_encode($response);
?>