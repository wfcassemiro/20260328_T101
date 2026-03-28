<?php
// includes/hotmart_logger.php

// Função de log exclusiva para a Hotmart API
// Esta função será incluída por hotmart.php e admin/hotmart.php
// para evitar redeclaração.

// Garante que o diretório de logs existe
$hotmartApiLogDir = __DIR__ . '/../logs/'; // Pasta logs na raiz
if (!is_dir($hotmartApiLogDir)) {
    @mkdir($hotmartApiLogDir, 0755, true);
}
$hotmartApiLogFile = $hotmartApiLogDir . 'hotmart_api_debug.log'; // Local do log

if (!function_exists('writeToHotmartApiLog')) {
    function writeToHotmartApiLog($message, $prefix = "GENERAL") {
        global $hotmartApiLogFile; // Acessa a variável global do caminho do arquivo de log
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($hotmartApiLogFile, "[$timestamp] [$prefix] " . $message . "\n", FILE_APPEND);
    }
}
?>