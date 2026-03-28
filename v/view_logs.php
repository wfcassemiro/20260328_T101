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

$page_title = 'Logs de Debug - Certificados';
$page_description = 'Visualização de logs do sistema de certificados';

include __DIR__ . '/vision/includes/head.php';
include __DIR__ . '/vision/includes/header.php';
include __DIR__ . '/vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-list"></i> Logs de Debug - Sistema de Certificados</h1>
            <p>Últimas <?php echo count($logs); ?> entradas de log</p>
            <div class="hero-actions">
                <button onclick="location.reload()" class="cta-btn">
                    <i class="fas fa-sync"></i> Atualizar
                </button>
                <a href="view_logs.php?clear=1" 
                   onclick="return confirm('Tem certeza que deseja limpar todos os logs?')"
                   class="page-btn btn-danger">
                    <i class="fas fa-trash"></i> Limpar Logs
                </a>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['cleared'])): ?>
        <div class="alert-success">
            <i class="fas fa-check"></i>
            Logs limpos com sucesso!
        </div>
    <?php endif; ?>

    <!-- Legenda -->
    <div class="video-card">
        <h3><i class="fas fa-info-circle"></i> Legenda:</h3>
        <div class="legend-grid">
            <div class="legend-item">
                <div class="legend-color legend-info"></div>
                <span>INFO</span>
            </div>
            <div class="legend-item">
                <div class="legend-color legend-success"></div>
                <span>SUCCESS</span>
            </div>
            <div class="legend-item">
                <div class="legend-color legend-error"></div>
                <span>ERROR</span>
            </div>
            <div class="legend-item">
                <div class="legend-color legend-debug"></div>
                <span>DEBUG</span>
            </div>
        </div>
    </div>

    <!-- Logs -->
    <div class="video-card">
        <?php if (empty($logs)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>Nenhum log encontrado.</p>
                <p class="text-light">Acesse a página de geração de certificados para gerar logs.</p>
            </div>
        <?php else: ?>
            <div class="logs-container">
                <?php foreach (array_reverse($logs) as $log): ?>
                    <?php
                    $log = trim($log);
                    if (empty($log)) continue;
                    
                    // Extrair nível do log
                    $level = 'INFO';
                    if (preg_match('/\[(INFO|SUCCESS|ERROR|DEBUG)\]/', $log, $matches)) {
                        $level = $matches[1];
                    }
                    
                    $levelClass = 'log-' . strtolower($level);
                    ?>
                    <div class="log-entry <?php echo $levelClass; ?>">
                        <code><?php echo htmlspecialchars($log); ?></code>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Ferramentas -->
    <div class="video-card">
        <h2><i class="fas fa-tools"></i> Ferramentas de Debug</h2>
        <div class="tools-grid">
            <a href="generate_certificate_debug.php?lecture_id=1" class="cta-btn">
                <i class="fas fa-certificate"></i> Teste Geração
            </a>
            
            <a href="test_certificate_components.php" class="page-btn">
                <i class="fas fa-wrench"></i> Teste Componentes
            </a>
            
            <a href="download_logs.php" class="page-btn">
                <i class="fas fa-download"></i> Baixar Logs
            </a>
            
            <a href="videoteca.php" class="page-btn">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <!-- Auto-refresh -->
    <div class="video-card">
        <div class="auto-refresh-control">
            <label class="form-control">
                <input type="checkbox" id="autoRefresh">
                <span><i class="fas fa-clock"></i> Auto-atualizar a cada 5 segundos</span>
            </label>
        </div>
    </div>
</div>

<style>
.legend-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 1rem;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.legend-color {
    width: 1rem;
    height: 1rem;
    border-radius: 2px;
}

.legend-info { background: var(--brand-purple); }
.legend-success { background: #10b981; }
.legend-error { background: #ef4444; }
.legend-debug { background: #8b5cf6; }

.logs-container {
    max-height: 400px;
    overflow-y: auto;
    padding: 1rem;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 8px;
}

.log-entry {
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    border-left: 4px solid;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.05);
    font-family: 'Courier New', monospace;
    font-size: 0.875rem;
    line-height: 1.4;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.log-info { border-left-color: var(--brand-purple); color: #a78bfa; }
.log-success { border-left-color: #10b981; color: #6ee7b7; }
.log-error { border-left-color: #ef4444; color: #f87171; }
.log-debug { border-left-color: #8b5cf6; color: #c4b5fd; }

.tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.auto-refresh-control {
    text-align: center;
}

.form-control {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    cursor: pointer;
}

.form-control input[type="checkbox"] {
    width: 1.25rem;
    height: 1.25rem;
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid var(--glass-border);
    border-radius: 4px;
}
</style>

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
    const logContainer = document.querySelector('.logs-container');
    if (logContainer) {
        logContainer.scrollTop = logContainer.scrollHeight;
    }
});
</script>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>