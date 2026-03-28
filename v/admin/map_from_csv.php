<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

$page_title = 'Mapeamento via CSV';
include __DIR__ . '/../vision/includes/head.php';
?>
<style>
    .progress-container{background:rgba(0,0,0,.3);border-radius:8px;padding:20px;margin-top:20px}.progress-bar-container{width:100%;background:rgba(0,0,0,.5);border-radius:10px;overflow:hidden;margin-bottom:10px}.progress-bar{width:0%;background:#007aff;height:20px;text-align:center;line-height:20px;color:#fff;transition:width .5s ease-in-out}.status-log{height:200px;background:rgba(0,0,0,.5);border-radius:6px;padding:10px;overflow-y:auto;font-family:monospace;font-size:14px;border:1px solid rgba(255,255,255,.1)}.status-log p{margin:0 0 5px 0;padding:0;border-bottom:1px solid rgba(255,255,255,.05)}.btn-start{padding:12px 24px;font-size:16px;background:#007aff;color:#fff;border:none;border-radius:8px;cursor:pointer;width:100%}.btn-start:disabled{background:#555;cursor:not-allowed}
</style>
<?php
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>
<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-file-csv"></i> Mapeamento de Aulas via CSV</h1>
            <p>Este processo irá construir um catálogo de todas as suas aulas na Hotmart e depois usar o arquivo <strong>mapeamento_final.csv</strong> para criar as associações.</p>
        </div>
    </div>

    <div class="video-card">
        <button id="startButton" class="btn-start">Iniciar Mapeamento</button>
        <div class="progress-container" style="display: none;">
            <div class="progress-bar-container">
                <div id="progressBar" class="progress-bar">0%</div>
            </div>
            <div class="status-log">
                <p id="statusLog">Aguardando início...</p>
            </div>
        </div>
    </div>
</div>

<script>
    const startButton = document.getElementById('startButton');
    const progressBar = document.getElementById('progressBar');
    const statusLog = document.getElementById('statusLog');
    const progressContainer = document.querySelector('.progress-container');

    startButton.addEventListener('click', () => {
        startButton.disabled = true;
        startButton.textContent = 'Processando...';
        progressContainer.style.display = 'block';
        logMessage('Limpando mapeamentos antigos...');

        // Limpa a tabela antes de começar
        fetch('process_mapping_chunk.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({action: 'clear'})
        }).then(response => response.json())
          .then(data => {
              if(data.success) {
                  logMessage('Tabela limpa. Iniciando busca de aulas...');
                  processChunk(null, 'build_catalog'); // Inicia a primeira etapa
              } else {
                  handleError(data.message || 'Falha ao limpar a tabela.');
              }
          }).catch(error => handleError('Erro de conexão ao limpar a tabela.'));
    });

    function processChunk(pageToken, action) {
        const formData = new URLSearchParams();
        formData.append('action', action);
        if (pageToken) {
            formData.append('page_token', pageToken);
        }

        fetch('process_mapping_chunk.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateProgress(data.progress, data.message);
                if (!data.done) {
                    // Se não terminou, chama o próximo chunk
                    processChunk(data.next_page_token, data.next_action);
                } else {
                    logMessage('Processo concluído com sucesso!');
                    startButton.textContent = 'Concluído!';
                }
            } else {
                handleError(data.message);
            }
        })
        .catch(error => {
            handleError('Erro de conexão. Verifique o console do navegador.');
            console.error('Error:', error);
        });
    }

    function updateProgress(progress, message) {
        progressBar.style.width = progress + '%';
        progressBar.textContent = progress + '%';
        logMessage(message);
    }

    function logMessage(message) {
        const p = document.createElement('p');
        p.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
        statusLog.prepend(p);
    }

    function handleError(message) {
        logMessage(`ERRO: ${message}`);
        startButton.disabled = false;
        startButton.textContent = 'Tentar Novamente';
        progressBar.style.backgroundColor = '#dc3545';
    }
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>