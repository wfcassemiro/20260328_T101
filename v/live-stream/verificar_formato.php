<?php
/**
 * Verificar formato e validade das credenciais Zoom
 */

require_once 'zoom_config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Credenciais Zoom</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        h1 {
            color: #2d3748;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #718096;
            margin-bottom: 30px;
        }
        .credential-box {
            background: #f7fafc;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        .credential-box h3 {
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 18px;
        }
        .credential-value {
            background: #2d3748;
            color: #68d391;
            padding: 10px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            word-break: break-all;
            margin: 10px 0;
        }
        .info {
            color: #4a5568;
            font-size: 14px;
            margin-top: 5px;
        }
        .status {
            padding: 10px 15px;
            border-radius: 8px;
            margin: 10px 0;
            font-weight: 600;
        }
        .success {
            background: #c6f6d5;
            color: #22543d;
            border-left: 4px solid #38a169;
        }
        .error {
            background: #fed7d7;
            color: #742a2a;
            border-left: 4px solid #e53e3e;
        }
        .warning {
            background: #feebc8;
            color: #7c2d12;
            border-left: 4px solid #dd6b20;
        }
        .summary {
            background: #ebf8ff;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #4299e1;
            margin-top: 30px;
        }
        .summary h2 {
            color: #2c5282;
            margin-bottom: 15px;
        }
        .summary ul {
            margin-left: 20px;
            color: #2d3748;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Verificar Formato das Credenciais</h1>
        <p class="subtitle">Diagnóstico detalhado das suas credenciais Zoom</p>

        <?php
        $issues = [];
        $hasSpaces = false;
        $allGood = true;

        // Account ID
        echo '<div class="credential-box">';
        echo '<h3>Account ID</h3>';
        echo '<div class="credential-value">' . htmlspecialchars(ZOOM_ACCOUNT_ID) . '</div>';
        echo '<div class="info">Tamanho: ' . strlen(ZOOM_ACCOUNT_ID) . ' caracteres</div>';
        
        if (trim(ZOOM_ACCOUNT_ID) !== ZOOM_ACCOUNT_ID) {
            echo '<div class="status error">❌ Contém espaços extras no início ou fim!</div>';
            $issues[] = 'Account ID tem espaços extras';
            $hasSpaces = true;
            $allGood = false;
        } elseif (strlen(ZOOM_ACCOUNT_ID) < 15) {
            echo '<div class="status warning">⚠️ Parece muito curto (esperado ~22 caracteres)</div>';
            $issues[] = 'Account ID parece incompleto';
            $allGood = false;
        } else {
            echo '<div class="status success">✅ Formato OK</div>';
        }
        
        if (preg_match('/[^a-zA-Z0-9_-]/', ZOOM_ACCOUNT_ID)) {
            echo '<div class="status warning">⚠️ Contém caracteres especiais incomuns</div>';
        }
        echo '</div>';

        // Client ID
        echo '<div class="credential-box">';
        echo '<h3>Client ID</h3>';
        echo '<div class="credential-value">' . htmlspecialchars(ZOOM_CLIENT_ID) . '</div>';
        echo '<div class="info">Tamanho: ' . strlen(ZOOM_CLIENT_ID) . ' caracteres</div>';
        
        if (trim(ZOOM_CLIENT_ID) !== ZOOM_CLIENT_ID) {
            echo '<div class="status error">❌ Contém espaços extras no início ou fim!</div>';
            $issues[] = 'Client ID tem espaços extras';
            $hasSpaces = true;
            $allGood = false;
        } elseif (strlen(ZOOM_CLIENT_ID) < 15) {
            echo '<div class="status warning">⚠️ Parece muito curto (esperado ~21 caracteres)</div>';
            $issues[] = 'Client ID parece incompleto';
            $allGood = false;
        } else {
            echo '<div class="status success">✅ Formato OK</div>';
        }
        
        if (preg_match('/[^a-zA-Z0-9_-]/', ZOOM_CLIENT_ID)) {
            echo '<div class="status warning">⚠️ Contém caracteres especiais incomuns</div>';
        }
        echo '</div>';

        // Client Secret
        echo '<div class="credential-box">';
        echo '<h3>Client Secret</h3>';
        echo '<div class="credential-value">' . htmlspecialchars(ZOOM_CLIENT_SECRET) . '</div>';
        echo '<div class="info">Tamanho: ' . strlen(ZOOM_CLIENT_SECRET) . ' caracteres</div>';
        
        if (trim(ZOOM_CLIENT_SECRET) !== ZOOM_CLIENT_SECRET) {
            echo '<div class="status error">❌ Contém espaços extras no início ou fim!</div>';
            $issues[] = 'Client Secret tem espaços extras';
            $hasSpaces = true;
            $allGood = false;
        } elseif (strlen(ZOOM_CLIENT_SECRET) < 20) {
            echo '<div class="status warning">⚠️ Parece muito curto (esperado ~32 caracteres)</div>';
            $issues[] = 'Client Secret parece incompleto';
            $allGood = false;
        } else {
            echo '<div class="status success">✅ Formato OK</div>';
        }
        
        if (preg_match('/[^a-zA-Z0-9_-]/', ZOOM_CLIENT_SECRET)) {
            echo '<div class="status warning">⚠️ Contém caracteres especiais incomuns</div>';
        }
        echo '</div>';

        // Resumo
        echo '<div class="summary">';
        echo '<h2>📋 Resumo do Diagnóstico</h2>';
        
        if ($allGood) {
            echo '<div class="status success">';
            echo '<strong>✅ Formato das Credenciais: OK</strong><br>';
            echo 'Todas as credenciais parecem estar no formato correto.';
            echo '</div>';
            echo '<p style="margin-top: 15px;">Se você ainda está recebendo erro "invalid_client", o problema pode ser:</p>';
            echo '<ul>';
            echo '<li>As credenciais estão corretas mas são de um app diferente/deletado</li>';
            echo '<li>O Client Secret foi regenerado e você está usando o antigo</li>';
            echo '<li>O app foi desativado no Zoom Marketplace</li>';
            echo '</ul>';
            echo '<p style="margin-top: 15px;"><strong>Recomendação:</strong></p>';
            echo '<ol>';
            echo '<li>Acesse <a href="https://marketplace.zoom.us/user/build" target="_blank">Zoom Marketplace</a></li>';
            echo '<li>Encontre seu app Server-to-Server OAuth</li>';
            echo '<li>Verifique se está "Active"</li>';
            echo '<li>Regenere o Client Secret</li>';
            echo '<li>Atualize zoom_config.php com o novo secret</li>';
            echo '<li>Teste em <a href="testar_credenciais.html">testar_credenciais.html</a></li>';
            echo '</ol>';
        } else {
            echo '<div class="status error">';
            echo '<strong>❌ Problemas Encontrados:</strong>';
            echo '<ul style="margin-top: 10px;">';
            foreach ($issues as $issue) {
                echo '<li>' . htmlspecialchars($issue) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
            
            if ($hasSpaces) {
                echo '<p style="margin-top: 15px;"><strong>⚠️ Espaços Extras Detectados!</strong></p>';
                echo '<p>Isso é um problema comum. Para corrigir:</p>';
                echo '<ol>';
                echo '<li>Acesse o Zoom Marketplace</li>';
                echo '<li>Use o botão "Copy" ao lado de cada credencial</li>';
                echo '<li>Cole em um editor de texto (Notepad)</li>';
                echo '<li>Verifique visualmente se não há espaços</li>';
                echo '<li>Copie dali para o zoom_config.php</li>';
                echo '</ol>';
            }
        }
        echo '</div>';
        ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="testar_credenciais.html" class="btn">🧪 Testar Credenciais</a>
            <a href="test_zoom_auth.php" class="btn">🔍 Diagnóstico Completo</a>
        </div>

        <div style="margin-top: 30px; padding: 20px; background: #f7fafc; border-radius: 10px;">
            <h3 style="color: #2d3748; margin-bottom: 10px;">💡 Dicas</h3>
            <ul style="color: #4a5568; line-height: 1.8;">
                <li><strong>Sempre use o botão "Copy"</strong> do Zoom Marketplace para copiar credenciais</li>
                <li><strong>Nunca copie diretamente</strong> selecionando o texto (pode pegar espaços invisíveis)</li>
                <li><strong>Se o Client Secret estiver oculto,</strong> regenere-o e copie imediatamente</li>
                <li><strong>Verifique se o app está "Active"</strong> no Zoom Marketplace</li>
                <li><strong>Teste sempre após atualizar</strong> as credenciais</li>
            </ul>
        </div>
    </div>
</body>
</html>
