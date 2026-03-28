<?php
/**
 * Time Tracker - Interface Principal
 * Sistema de rastreamento de tempo para tradutores
 * INTEGRADO com dash_projects existente
 */

// Ativar exibição de erros (remover em produção)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Tenta incluir o auth_check de várias formas para evitar erro de path
$paths_to_check = [
    __DIR__ . '/includes/auth_check.php', // Se estiver na pasta time_tracker
    __DIR__ . '/auth_check.php',          // Se estiver na raiz junto com os includes
    $_SERVER['DOCUMENT_ROOT'] . '/dash-t101/includes/auth_check.php' // Caminho absoluto comum
];

$auth_loaded = false;
foreach ($paths_to_check as $path) {
    if (file_exists($path)) {
        require_once $path;
        $auth_loaded = true;
        break;
    }
}

// Se não achou o auth_check, tenta carregar o database diretamente
if (!$auth_loaded && file_exists(__DIR__ . '/config/database.php')) {
    require_once __DIR__ . '/config/database.php';
}

$page_title = 'Time Tracker - Rastreamento de Tempo';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>

    <link rel="stylesheet" href="/vision/assets/css/style.css">
    <link rel="stylesheet" href="vision/assets/css/time-tracker.css" onerror="this.href='/vision/assets/css/time-tracker.css'">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            min-width: 300px;
            max-width: 500px;
            padding: 16px 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            transform: translateX(120%);
            transition: transform 0.3s ease, opacity 0.3s ease;
            opacity: 0;
            z-index: 10000;
            cursor: pointer;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        .toast-notification.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        .toast-icon {
            font-size: 24px;
            flex-shrink: 0;
        }
        
        .toast-message {
            flex: 1;
            font-size: 14px;
            line-height: 1.4;
            color: #333;
        }
        
        .toast-success {
            border-left: 4px solid #28a745;
        }
        
        .toast-error {
            border-left: 4px solid #dc3545;
        }
        
        .toast-warning {
            border-left: 4px solid #ffc107;
        }
        
        .toast-info {
            border-left: 4px solid #17a2b8;
        }
        
        .toast-notification:hover {
            box-shadow: 0 6px 16px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>

<?php 
$header_path = $_SERVER['DOCUMENT_ROOT'] . '/vision/includes/header.php';
if (file_exists($header_path)) {
    include $header_path;
}
?>

<div class="time-tracker-container">
    <div class="tracker-header">
        <div class="header-content">
            <h1><i class="fas fa-stopwatch"></i> Time Tracker</h1>
            <div class="header-actions">
                <a href="/dash-t101/report_time_tracker.php" class="btn btn-secondary">
                    <i class="fas fa-chart-bar"></i> Relatórios
                </a>
                <a href="/dash-t101/projects.php" class="btn btn-secondary">
                    <i class="fas fa-folder"></i> Gerenciar Projetos
                </a>
            </div>
        </div>
    </div>

    <div class="timer-section glass-card">
        <div class="timer-display" id="timerDisplay">
            <div class="time-digits">00:00:00</div>
            <div class="timer-info" id="timerInfo"></div>
        </div>

        <div class="timer-controls">
            <div class="timer-input-group">
                <input
                    type="text"
                    id="timerDescription"
                    class="timer-input"
                    placeholder="O que você está fazendo?">

                <select id="timerProject" class="timer-select">
                    <option value="">Selecione um projeto</option>
                </select>

                <select id="timerTask" class="timer-select" disabled>
                    <option value="">Selecione uma tarefa</option>
                </select>

                <button
                    class="btn btn-icon btn-primary"
                    onclick="openQuickProjectModal()"
                    title="Criar Projeto Rápido">
                    <i class="fas fa-plus"></i>
                </button>
            </div>

            <div class="timer-buttons">
                <button id="startButton" class="btn btn-primary btn-timer">
                    <i class="fas fa-play"></i> Iniciar
                </button>
                <button id="pauseButton" class="btn btn-warning btn-timer" style="display: none;">
                    <i class="fas fa-pause"></i> Pausar
                </button>
                <button id="resumeButton" class="btn btn-success btn-timer" style="display: none;">
                    <i class="fas fa-play"></i> Retomar
                </button>
                <button id="stopButton" class="btn btn-danger btn-timer" style="display: none;">
                    <i class="fas fa-stop"></i> Parar
                </button>
            </div>
        </div>
    </div>

    <div class="content-tabs">
        <div class="tabs-header">
            <button class="tab-btn active" data-tab="history">
                <i class="fas fa-history"></i> Histórico
            </button>
        </div>

        <div class="tab-content active" id="historyTab">
            <div class="section-header">
                <h2>Registros Recentes</h2>
                <div class="filter-group">
                    <select id="filterProject" class="filter-select">
                        <option value="">Todos os projetos</option>
                    </select>
                    <button class="btn btn-sm btn-primary" onclick="loadEntries()">
                        <i class="fas fa-sync"></i> Atualizar
                    </button>
                </div>
            </div>

            <div id="entriesList" class="entries-list">
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin"></i> Carregando...
                </div>
            </div>
        </div>
    </div>
</div>

<div id="quickProjectModal" class="modal">
    <div class="modal-content modal-small">
        <div class="modal-header">
            <h3>Criar Projeto Rápido</h3>
            <button class="modal-close" onclick="closeQuickProjectModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="quickProjectForm">
                <div class="form-group">
                    <label for="quickProjectName">Nome do Projeto *</label>
                    <input
                        type="text"
                        id="quickProjectName"
                        class="form-control"
                        placeholder="Ex: Tradução de Manual Técnico"
                        required>
                </div>

                <div class="form-group">
                    <label for="quickClientName">Cliente (Opcional)</label>
                    <input
                        type="text"
                        id="quickClientName"
                        class="form-control"
                        placeholder="Nome do cliente">
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeQuickProjectModal()">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Criar e Selecionar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="tasksModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="tasksModalTitle">Tarefas do Projeto</h3>
            <button class="modal-close" onclick="closeTasksModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="tasks-header">
                <input
                    type="text"
                    id="newTaskName"
                    class="form-control"
                    placeholder="Nova tarefa">
                <button class="btn btn-primary" onclick="createTask()">
                    <i class="fas fa-plus"></i> Adicionar
                </button>
            </div>

            <div id="tasksList" class="tasks-list">
                </div>
        </div>
    </div>
</div>

<script>
    // ⚠️ DETECÇÃO AUTOMÁTICA DO CAMINHO DA API
    // Isso garante que funcione independente de estar em /dash-t101/ ou /dash-t101/time_tracker/
    
    // 1. Pega o caminho atual (ex: /dash-t101/)
    let currentPath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
    if (currentPath === '/') currentPath = '';
    
    // 2. Define a API na MESMA pasta onde este arquivo está rodando
    window.API_URL = window.location.origin + currentPath + '/api_time_tracker_NO_AUTH.php';

    console.log('[TT CONFIG] URL Atual:', window.location.href);
    console.log('[TT CONFIG] Pasta Base Detectada:', currentPath);
    console.log('[TT CONFIG] API URL Definida:', window.API_URL);
    console.log('[TT CONFIG] ⚠️ Se der erro 404, verifique se "api_time_tracker_NO_AUTH.php" está na mesma pasta que este arquivo.');
</script>

<script src="/vision/assets/js/time-tracker-v2.js?v=<?php echo time(); ?>"></script>
<script>
    // Fallback se o JS não carregar do caminho acima
    if (typeof initializeApp === 'undefined') {
        console.warn('[TT] JS principal não carregou de /vision/. Tentando caminho local...');
        document.write('<script src="vision/assets/js/time-tracker-v2.js?v=<?php echo time(); ?>"><\/script>');
    }
</script>

<?php 
$footer_path = $_SERVER['DOCUMENT_ROOT'] . '/vision/includes/footer.php';
if (file_exists($footer_path)) {
    include $footer_path;
}
?>

</body>
</html>