<?php
/**
 * Endpoint web para testar sincronização de progresso da Hotmart
 * Acesse via: /Hotmart/test_sync.php
 */

// Configurar exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define o tempo máximo de execução (5 minutos)
set_time_limit(300);

// Incluir arquivos necessários do sistema principal
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/hotmart.php';
require_once __DIR__ . '/../hotmart.php';
require_once __DIR__ . '/HotmartProgressSync.php';

// Verificar se é requisição AJAX ou web normal
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
$action = $_GET['action'] ?? 'view';

// Processar ações
if ($action === 'sync' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $hotmartApi = new HotmartAPI();
        $syncManager = new HotmartProgressSync($pdo, $hotmartApi);
        
        $result = $syncManager->syncAllProgress();
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }
    }
}

// Obter estatísticas
try {
    $hotmartApi = new HotmartAPI();
    $syncManager = new HotmartProgressSync($pdo, $hotmartApi);
    $stats = $syncManager->getProgressStats();
} catch (Exception $e) {
    $stats = null;
    $statsError = $e->getMessage();
}

// Se for AJAX e action=stats, retornar apenas estatísticas
if ($action === 'stats' && $isAjax) {
    header('Content-Type: application/json');
    echo json_encode($stats ?? ['error' => $statsError ?? 'Erro desconhecido']);
    exit;
}

// Obter logs recentes
try {
    $stmt = $pdo->query("
        SELECT * FROM hotmart_sync_logs 
        WHERE sync_type = 'PROGRESS'
        ORDER BY started_at DESC 
        LIMIT 10
    ");
    $recentSyncs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentSyncs = [];
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sincronização Hotmart - Progresso de Aulas</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card .value {
            color: #667eea;
            font-size: 36px;
            font-weight: bold;
        }
        
        .action-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .action-card h2 {
            color: #333;
            font-size: 20px;
            margin-bottom: 15px;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        #syncResult {
            margin-top: 20px;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin-left: 10px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }
        
        table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            color: #666;
        }
        
        table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔄 Sincronização Hotmart - Progresso de Aulas</h1>
            <p>Fase 1: Obter e armazenar dados de progresso dos assinantes</p>
        </div>
        
        <?php if (isset($result)): ?>
            <?php if ($result['success']): ?>
                <div class="alert alert-success">
                    <strong>✅ Sincronização concluída com sucesso!</strong><br>
                    Usuários processados: <?= $result['users_processed'] ?><br>
                    Registros de progresso: <?= $result['progress_records'] ?? 0 ?><br>
                    Erros: <?= $result['errors'] ?? 0 ?><br>
                    Duração: <?= $result['duration'] ?? 0 ?>s
                </div>
            <?php else: ?>
                <div class="alert alert-error">
                    <strong>❌ Erro na sincronização:</strong><br>
                    <?= htmlspecialchars($result['message'] ?? 'Erro desconhecido') ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <strong>❌ Erro:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($stats): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total de Registros</h3>
                    <div class="value"><?= number_format($stats['total_records']) ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>Palestras Completadas</h3>
                    <div class="value"><?= number_format($stats['completed']) ?></div>
                </div>
                
                <div class="stat-card">
                    <h3>Usuários com Progresso</h3>
                    <div class="value"><?= number_format($stats['users_with_progress']) ?></div>
                </div>
                
                <?php if ($stats['last_sync']): ?>
                <div class="stat-card">
                    <h3>Última Sincronização</h3>
                    <div class="value" style="font-size: 16px;">
                        <?= date('d/m/Y H:i', strtotime($stats['last_sync']['started_at'])) ?>
                    </div>
                    <div style="margin-top: 10px; font-size: 14px; color: #666;">
                        Status: <span class="badge badge-<?= $stats['last_sync']['status'] === 'SUCCESS' ? 'success' : ($stats['last_sync']['status'] === 'FAILED' ? 'danger' : 'warning') ?>">
                            <?= $stats['last_sync']['status'] ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        <?php elseif (isset($statsError)): ?>
            <div class="alert alert-error">
                <strong>⚠️ Erro ao obter estatísticas:</strong> <?= htmlspecialchars($statsError) ?>
            </div>
        <?php endif; ?>
        
        <div class="action-card">
            <h2>🚀 Iniciar Sincronização</h2>
            <p style="color: #666; margin-bottom: 20px;">
                Este processo irá buscar todos os usuários da Hotmart e sincronizar o progresso de aulas de cada um.
                Pode levar alguns minutos dependendo da quantidade de usuários.
            </p>
            <form method="POST" action="?action=sync" id="syncForm">
                <button type="submit" class="btn" id="syncBtn">
                    Iniciar Sincronização
                </button>
            </form>
            <div id="syncResult"></div>
        </div>
        
        <?php if (!empty($recentSyncs)): ?>
        <div class="table-container">
            <h2 style="margin-bottom: 20px; color: #333;">📄 Histórico de Sincronizações</h2>
            <table>
                <thead>
                    <tr>
                        <th>Início</th>
                        <th>Conclusão</th>
                        <th>Status</th>
                        <th>Usuários</th>
                        <th>Erros</th>
                        <th>Mensagem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentSyncs as $sync): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i:s', strtotime($sync['started_at'])) ?></td>
                        <td><?= $sync['completed_at'] ? date('d/m/Y H:i:s', strtotime($sync['completed_at'])) : '-' ?></td>
                        <td>
                            <span class="badge badge-<?= $sync['status'] === 'SUCCESS' ? 'success' : ($sync['status'] === 'FAILED' ? 'danger' : 'warning') ?>">
                                <?= $sync['status'] ?>
                            </span>
                        </td>
                        <td><?= $sync['users_synced'] ?></td>
                        <td><?= $sync['errors_count'] ?></td>
                        <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?= htmlspecialchars($sync['message'] ?? '-') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="test_api.php" style="color: white; text-decoration: none; opacity: 0.8;">Teste de API</a> | 
            <a href="fix_auth.php" style="color: white; text-decoration: none; opacity: 0.8;">Correção de Autenticação</a> |
            <a href="../logs/hotmart_progress_sync.log" target="_blank" style="color: white; text-decoration: none; opacity: 0.8;">Ver Logs</a>
        </div>
    </div>
    
    <script>
        document.getElementById('syncForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('syncBtn');
            const resultDiv = document.getElementById('syncResult');
            
            btn.disabled = true;
            btn.innerHTML = 'Sincronizando... <span class="loading"></span>';
            resultDiv.innerHTML = '<div class="alert alert-info">Processando sincronização, aguarde...</div>';
            
            fetch('?action=sync', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <strong>✅ Sincronização concluída com sucesso!</strong><br>
                            Usuários processados: ${data.users_processed}<br>
                            Registros de progresso: ${data.progress_records || 0}<br>
                            Erros: ${data.errors || 0}<br>
                            Duração: ${data.duration || 0}s
                        </div>
                    `;
                    // Recarregar página após 2 segundos
                    setTimeout(() => location.reload(), 2000);
                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-error">
                            <strong>❌ Erro na sincronização:</strong><br>
                            ${data.message || 'Erro desconhecido'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `
                    <div class="alert alert-error">
                        <strong>❌ Erro de conexão:</strong><br>
                        ${error.message}
                    </div>
                `;
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = 'Iniciar Sincronização';
            });
        });
    </script>
</body>
</html>