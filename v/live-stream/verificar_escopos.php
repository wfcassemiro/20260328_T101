<?php
/**
 * Script para verificar escopos e permissões do Zoom
 * Execute este script após configurar os escopos no painel Zoom
 */

// Deleta o arquivo de log antigo para começar um novo
if (file_exists(__DIR__ . '/zoom_debug_log.txt')) {
    unlink(__DIR__ . '/zoom_debug_log.txt');
}

require_once 'zoom_config.php';
require_once 'zoom_auth.php';

// Limpa o cache de token se ?refresh=1 for passado na URL
if (isset($_GET['refresh'])) {
    $cacheFile = sys_get_temp_dir() . '/zoom_token_cache.json';
    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de Escopos Zoom</title>
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; color: #2d3748; }
    .container { max-width: 900px; margin: 0 auto; background: white; border-radius: 20px; padding: 40px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); }
    h1 { color: #667eea; margin-bottom: 10px; font-size: 32px; }
    .subtitle { color: #718096; margin-bottom: 30px; font-size: 16px; }
    .status-box { padding: 20px; border-radius: 12px; margin-bottom: 20px; border-left: 5px solid; }
    .status-success { background: #c6f6d5; border-color: #38a169; color: #22543d; }
    .status-error { background: #fed7d7; border-color: #e53e3e; color: #742a2a; }
    .status-warning { background: #feebc8; border-color: #ed8936; color: #7c2d12; }
    .status-info { background: #bee3f8; border-color: #4299e1; color: #2c5282; }
    .status-box h3 { margin-bottom: 10px; font-size: 18px; }
    .status-box p, .status-box li { line-height: 1.6; }
    .test-section { background: #f7fafc; padding: 25px; border-radius: 12px; margin-top: 20px; }
    .test-section h2 { color: #2d3748; margin-bottom: 15px; font-size: 22px; }
    .test-item { padding: 15px; background: white; border-radius: 8px; margin-bottom: 12px; border-left: 4px solid #e2e8f0; }
    .test-item.success { border-color: #38a169; }
    .test-item.error { border-color: #e53e3e; }
    .test-title { font-weight: 600; color: #2d3748; margin-bottom: 5px; font-size: 16px; }
    .test-desc { color: #718096; font-size: 14px; margin-bottom: 8px; }
    .test-result { font-size: 14px; font-weight: 600; }
    .test-result.success { color: #38a169; }
    .test-result.error { color: #e53e3e; }
    .scope-list { background: white; padding: 20px; border-radius: 8px; margin-top: 15px; }
    .scope-item { padding: 10px 15px; background: #f7fafc; border-radius: 6px; margin-bottom: 8px; font-family: 'Courier New', monospace; font-size: 14px; color: #4a5568; border-left: 3px solid #667eea; }
    .btn { display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; margin-top: 20px; transition: all 0.3s ease; }
    .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4); }
    </style>
</head>
<body>
    <div class="container">
    <h1>🔍 Verificação de Escopos Zoom</h1>
    <p class="subtitle">Diagnóstico automático de configuração e permissões</p>
    
    <div class="status-box status-info">
    <h3>📝 Gerando Log de Diagnóstico</h3>
    <p>Um arquivo de log chamado <strong>zoom_debug_log.txt</strong> foi criado no mesmo diretório deste script. Se o erro persistir, o conteúdo deste arquivo será crucial para a análise.</p>
    </div>

    <?php
    // A lógica de verificação permanece a mesma
    echo '<div class="test-section"><h2>🔐 1. Autenticação OAuth</h2>';
    $token = getZoomAccessToken(true);
    if ($token) {
        echo '<div class="status-box status-success"><h3>✅ Token obtido com sucesso</h3><p>A autenticação com a Zoom está funcionando.</p></div>';
        $tokenOk = true;
    } else {
        echo '<div class="status-box status-error"><h3>❌ Falha ao obter token</h3><p>Verifique as credenciais e o log de diagnóstico.</p></div>';
        $tokenOk = false;

        // Mostrar últimos logs para ajudar no diagnóstico
        $logFile = __DIR__ . '/zoom_debug_log.txt';
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            echo '<div class="test-section"><h2>🗒️ Log de diagnóstico</h2>';
            echo '<pre style="background:#f7fafc;padding:15px;border-radius:8px;max-height:400px;overflow:auto;">' . htmlspecialchars($logContent) . '</pre>';
            echo '</div>';
        } else {
            echo '<p style="color:#718096;margin-top:12px;">Log não encontrado. Verifique permissões de escrita no diretório do script.</p>';
        }
    }
    echo '</div>';

    if ($tokenOk) {
        echo '<div class="test-section"><h2>🎯 2. Teste de Escopos (Permissões)</h2>';
        $scopeTests = [
            ['name' => 'Obter Informações do Usuário', 'scope' => 'user:read:user:admin', 'endpoint' => '/users/me', 'method' => 'GET'],
            ['name' => 'Listar Reuniões', 'scope' => 'meeting:read:meeting:admin', 'endpoint' => '/users/me/meetings?type=scheduled&page_size=1', 'method' => 'GET']
        ];
        
        $allScopesOk = true;
        foreach ($scopeTests as $test) {
            echo '<div class="test-item';
            $result = zoomApiRequest($test['endpoint'], $test['method']);
            if ($result['success']) {
                echo ' success"><div class="test-title">✅ ' . htmlspecialchars($test['name']) . '</div>';
                echo '<div class="test-desc">Permissão testada com sucesso.</div>';
            } else {
                echo ' error"><div class="test-title">❌ ' . htmlspecialchars($test['name']) . '</div>';
                echo '<div class="test-desc">Escopo requerido (conforme documentação): <code>' . htmlspecialchars($test['scope']) . '</code></div>';
                echo '<div class="test-result error">FALHOU - ' . htmlspecialchars($result['error'] ?? json_encode($result['response'] ?? '')) . '</div>';
                $allScopesOk = false;
            }
            echo '</div>';
        }
        echo '</div>';
    }
    ?>
    <p style="margin-top:20px;"><a class="btn" href="?refresh=1">Forçar refresh do token e re-executar</a></p>
    </div>
</body>
</html>