<?php
/**
 * SISTEMA DE LOGS PARA DEBUG - CERTIFICADOS TRANSLATORS101
 * Registra cada etapa do processo para identificar falhas
 */

class CertificateLogger {
    private $logFile;
    
    public function __construct() {
        $this->logFile = __DIR__ . '/logs/certificate_debug.log';
        $this->ensureLogDirectory();
    }
    
    private function ensureLogDirectory() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    public function log($message, $level = 'INFO', $data = null) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message";
        
        if ($data !== null) {
            $logEntry .= " | Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        
        $logEntry .= PHP_EOL;
        
        // Escrever no arquivo
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Também mostrar na tela se estivermos em modo debug
        if (isset($_GET['debug']) || isset($_SESSION['debug_mode'])) {
            echo "<div style='background: #1a1a1a; color: #00ff00; padding: 5px; margin: 2px; font-family: monospace; font-size: 12px; border-left: 3px solid #00ff00;'>";
            echo htmlspecialchars($logEntry);
            echo "</div>";
        }
    }
    
    public function error($message, $data = null) {
        $this->log($message, 'ERROR', $data);
    }
    
    public function success($message, $data = null) {
        $this->log($message, 'SUCCESS', $data);
    }
    
    public function debug($message, $data = null) {
        $this->log($message, 'DEBUG', $data);
    }
    
    public function getRecentLogs($lines = 50) {
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        $file = file($this->logFile);
        return array_slice($file, -$lines);
    }
    
    public function clearLogs() {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }
}

// Função global para facilitar o uso
function certificateLog($message, $level = 'INFO', $data = null) {
    static $logger = null;
    if ($logger === null) {
        $logger = new CertificateLogger();
    }
    $logger->log($message, $level, $data);
}

// Função para capturar erros PHP
function certificateErrorHandler($errno, $errstr, $errfile, $errline) {
    certificateLog("PHP Error: $errstr in $errfile:$errline", 'ERROR', [
        'errno' => $errno,
        'file' => basename($errfile),
        'line' => $errline
    ]);
    return false; // Permite que o handler padrão continue
}

// Função para capturar exceções não tratadas
function certificateExceptionHandler($exception) {
    certificateLog("Uncaught Exception: " . $exception->getMessage(), 'ERROR', [
        'file' => basename($exception->getFile()),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
}

// Registrar handlers
set_error_handler('certificateErrorHandler');
set_exception_handler('certificateExceptionHandler');
?>