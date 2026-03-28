<?php
session_start();
require_once 'includes/certificate_logger.php';
require_once 'config/database.php';

// Função para limpar logs se solicitado
if (isset($_GET['clear']) && $_GET['clear'] === '1') {
    $logger = new CertificateLogger();
    $logger->clearLogs();
    header('Location: view_logs.php?cleared=1');
    exit;
}

$logger = new CertificateLogger();
$logs = $logger->getRecentLogs(100); // Últimas 100 entradas

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de Debug - Certificados</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #0f0f0f; color: #ffffff; }
        .log-info { border-left-color: #3b82f6; }
        .log-success { border-left-color: #10b981; }
        .log-error { border-left-color: #ef4444; }
        .log-debug { border-left-color: #8b5cf6; }
        .log-entry { 
            font-family: 'Courier New', monospace; 
            font-size: 12px; 
            line-height: 1.4;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
</head>
<body class="bg-gray-900 text-white">
    <nav class="bg-gray-800 p-4">
        <div class="container mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <a href="/" class="text-xl font-bold text-purple-400">Translators101</a>
                <span class="text-gray-400">|</span>
                <span class="text-yellow-400 font-semibold">
                    <i class="fas fa-list mr-2"></i>Logs de Debug
                </span>
            </div>
            
            <div class="flex items-center space-x-4">
                <button onclick="location.reload()" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded transition-colors">
                    <i class="fas fa-sync mr-2"></i>Atualizar
                </button>
                <a href="view_logs.php?clear=1" 
                   onclick="return confirm('Tem certeza que deseja limpar todos os logs?')"
                   class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded transition-colors">
                    <i class="fas fa-trash mr-2"></i>Limpar Logs
                </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-yellow-400">
                <i class="fas fa-list mr-3"></i>Logs de Debug - Sistema de Certificados
            </h1>
            <div class="text-sm text-gray-400">
                Últimas <?php echo count($logs); ?> entradas
            </div>
        </div>

        <?php if (isset($_GET['cleared'])): ?>
            <div class="bg-green-900 border border-green-600 p-4 rounded-lg mb-6">
                <p class="text-green-300">
                    <i class="fas fa-check mr-2"></i>Logs limpos com sucesso!
                </p>
            </div>
        <?php endif; ?>

        <!-- Legenda -->
        <div class="bg-gray-800 p-4 rounded-lg mb-6">
            <h3 class="text-lg font-semibold mb-3 text-gray-300">Legenda:</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div class="flex items-center">
                    <div class="w-4 h-4 border-l-4 border-blue-500 mr-2"></div>
                    <span class="text-blue-400">INFO</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 border-l-4 border-green-500 mr-2"></div>
                    <span class="text-green-400">SUCCESS</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 border-l-4 border-red-500 mr-2"></div>
                    <span class="text-red-400">ERROR</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 border-l-4 border-purple-500 mr-2"></div>
                    <span class="text-purple-400">DEBUG</span>
                </div>
            </div>
        </div>

        <!-- Logs -->
        <div class="bg-gray-800 rounded-lg p-6">
            <?php if (empty($logs)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-inbox text-gray-500 text-4xl mb-4"></i>
                    <p class="text-gray-400">Nenhum log encontrado.</p>
                    <p class="text-gray-500 text-sm mt-2">
                        Acesse a página de geração de certificados para gerar logs.
                    </p>
                </div>
            <?php else: ?>
                <div class="space-y-2 max-h-96 overflow-y-auto">
                    <?php foreach (array_reverse($logs) as $log): ?>
                        <?php
                        $log = trim($log);
                        if (empty($log)) continue;
                        
                        // Extrair nível do log
                        $level = 'INFO';
                        if (preg_match('/\[(INFO|SUCCESS|ERROR|DEBUG)\]/', $log, $matches)) {
                            $level = $matches[1];
                        }
                        
                        // Definir classe CSS baseada no nível
                        $levelClass = 'log-' . strtolower($level);
                        $textColor = '';
                        switch ($level) {
                            case 'ERROR': $textColor = 'text-red-300'; break;
                            case 'SUCCESS': $textColor = 'text-green-300'; break;
                            case 'DEBUG': $textColor = 'text-purple-300'; break;
                            default: $textColor = 'text-blue-300'; break;
                        }
                        ?>
                        <div class="log-entry bg-gray-900 border-l-4 <?php echo $levelClass; ?> p-3 rounded-r <?php echo $textColor; ?>">
                            <?php echo htmlspecialchars($log); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Ferramentas -->
        <div class="mt-8 grid grid-cols-1 md:grid-cols-4 gap-4">
            <a href="generate_certificate_debug.php?lecture_id=1" 
               class="bg-purple-600 hover:bg-purple-700 px-4 py-3 rounded text-center transition-colors">
                <i class="fas fa-certificate mr-2"></i>Teste Geração
            </a>
            
            <a href="test_certificate_components.php" 
               class="bg-green-600 hover:bg-green-700 px-4 py-3 rounded text-center transition-colors">
                <i class="fas fa-wrench mr-2"></i>Teste Componentes
            </a>
            
            <a href="download_logs.php" 
               class="bg-blue-600 hover:bg-blue-700 px-4 py-3 rounded text-center transition-colors">
                <i class="fas fa-download mr-2"></i>Baixar Logs
            </a>
            
            <a href="videoteca.php" 
               class="bg-gray-600 hover:bg-gray-700 px-4 py-3 rounded text-center transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Voltar
            </a>
        </div>

        <!-- Auto-refresh -->
        <div class="mt-6 text-center">
            <label class="inline-flex items-center">
                <input type="checkbox" id="autoRefresh" class="form-checkbox bg-gray-700 border-gray-600">
                <span class="ml-2 text-gray-400">Auto-atualizar a cada 5 segundos</span>
            </label>
        </div>
    </div>

    <script>
        // Auto-refresh functionality
        let refreshInterval;
        const autoRefreshCheckbox = document.getElementById('autoRefresh');
        
        autoRefreshCheckbox.addEventListener('change', function() {
            if (this.checked) {
                refreshInterval = setInterval(() => {
                    location.reload();
                }, 5000);
            } else {
                clearInterval(refreshInterval);
            }
        });
        
        // Auto-scroll para o final dos logs
        window.addEventListener('load', function() {
            const logContainer = document.querySelector('.overflow-y-auto');
            if (logContainer) {
                logContainer.scrollTop = logContainer.scrollHeight;
            }
        });
    </script>
</body>
</html>