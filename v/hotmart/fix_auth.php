<?php
/**
 * Script para corrigir autenticação da Hotmart
 * Gera o BASIC_AUTH necessário e testa as APIs
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Incluir configurações
require_once __DIR__ . '/../config/hotmart.php';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Correção de Autenticação Hotmart</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 15px;
        }
        h2 {
            color: #667eea;
            margin-top: 30px;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: #e83e8c;
        }
        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
            border-left: 4px solid #667eea;
        }
        .step {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }
        .step h3 {
            margin-top: 0;
            color: #667eea;
        }
        strong {
            color: #333;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .test-result {
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
        }
        .test-pass {
            background: #d4edda;
            color: #155724;
        }
        .test-fail {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Correção de Autenticação Hotmart</h1>
        
        <div class="alert alert-info">
            <strong>📌 Problema Identificado:</strong> A autenticação OAuth está falhando porque o <code>HOTMART_BASIC_AUTH</code> não está definido.
        </div>
        
        <h2>1. Informações Atuais</h2>
        <?php
        echo "<strong>CLIENT_ID:</strong> " . (defined('HOTMART_CLIENT_ID') ? HOTMART_CLIENT_ID : 'Não definido') . "<br>";
        echo "<strong>CLIENT_SECRET:</strong> " . (defined('HOTMART_CLIENT_SECRET') ? substr(HOTMART_CLIENT_SECRET, 0, 10) . '...' : 'Não definido') . "<br>";
        echo "<strong>HOT_TOKEN:</strong> " . (defined('HOTMART_HOT_TOKEN') ? 'Definido (' . strlen(HOTMART_HOT_TOKEN) . ' caracteres)' : 'Não definido') . "<br>";
        echo "<strong>BASIC_AUTH:</strong> " . (defined('HOTMART_BASIC_AUTH') ? '✓ Definido' : '<span style="color: #dc3545;">✗ NÃO DEFINIDO (Este é o problema!)</span>') . "<br>";
        ?>
        
        <h2>2. Gerar BASIC_AUTH</h2>
        <div class="step">
            <h3>O que é BASIC_AUTH?</h3>
            <p>O BASIC_AUTH é uma string codificada em Base64 no formato <code>CLIENT_ID:CLIENT_SECRET</code>, necessária para autenticação OAuth da Hotmart.</p>
            
            <?php
            $clientId = defined('HOTMART_CLIENT_ID') ? HOTMART_CLIENT_ID : '';
            $clientSecret = defined('HOTMART_CLIENT_SECRET') ? HOTMART_CLIENT_SECRET : '';
            
            if ($clientId && $clientSecret) {
                $basicAuth = base64_encode($clientId . ':' . $clientSecret);
                
                echo '<div class="alert alert-success">';
                echo '<strong>✅ BASIC_AUTH Gerado com Sucesso!</strong><br><br>';
                echo '<strong>Valor a adicionar no arquivo de configuração:</strong><br>';
                echo '<code style="display: block; margin-top: 10px; word-break: break-all;">' . htmlspecialchars($basicAuth) . '</code>';
                echo '</div>';
            } else {
                echo '<div class="alert alert-error">';
                echo '<strong>❌ Erro:</strong> CLIENT_ID ou CLIENT_SECRET não estão definidos.';
                echo '</div>';
            }
            ?>
        </div>
        
        <h2>3. Como Corrigir</h2>
        <div class="step">
            <h3>Opção A: Adicionar no arquivo config/hotmart.php (RECOMENDADO)</h3>
            <p>Edite o arquivo <code>/public_html/v/config/hotmart.php</code> e adicione esta linha após as outras definições:</p>
            <pre><?php
if (isset($basicAuth)) {
    echo "define('HOTMART_BASIC_AUTH', '" . $basicAuth . "');";
}
?></pre>
            
            <p><strong>O arquivo ficará assim:</strong></p>
            <pre>&lt;?php
// Credenciais Hotmart
define('HOTMART_CLIENT_ID', 'f7f05ef5-bb55-46a2-a678-3c27627941d8');
define('HOTMART_CLIENT_SECRET', '1d9e0fe5-efa9-4841-80a5-6e15be63b2e0');
define('HOTMART_HOT_TOKEN', 'okqS9nRS9FXJiOPkijs40T9v2fp2Vz522f1c9c-5f8e-4c6c-aa14-e863b6f34dd2');
define('HOTMART_SUBDOMAIN', 't101');

// ADICIONE ESTA LINHA:
define('HOTMART_BASIC_AUTH', '<?php echo isset($basicAuth) ? $basicAuth : 'SEU_BASIC_AUTH_AQUI'; ?>');

// URLs da API Hotmart
...</pre>
        </div>
        
        <div class="step">
            <h3>Opção B: Usar correção automática</h3>
            <p>Clique no botão abaixo para aplicar a correção automaticamente:</p>
            
            <?php if (isset($basicAuth)): ?>
            <form method="POST" action="">
                <input type="hidden" name="apply_fix" value="1">
                <input type="hidden" name="basic_auth" value="<?php echo htmlspecialchars($basicAuth); ?>">
                <button type="submit" class="btn">Aplicar Correção Automaticamente</button>
            </form>
            <?php endif; ?>
        </div>
        
        <?php
        // Processar correção automática
        if (isset($_POST['apply_fix']) && isset($_POST['basic_auth'])) {
            $configFile = __DIR__ . '/../config/hotmart.php';
            $basicAuthValue = $_POST['basic_auth'];
            
            try {
                $content = file_get_contents($configFile);
                
                // Verificar se já existe
                if (strpos($content, 'HOTMART_BASIC_AUTH') !== false) {
                    echo '<div class="alert alert-warning">';
                    echo '<strong>⚠️ BASIC_AUTH já está definido no arquivo.</strong> Não é necessário adicionar novamente.';
                    echo '</div>';
                } else {
                    // Adicionar após HOTMART_SUBDOMAIN
                    $newLine = "define('HOTMART_BASIC_AUTH', '" . $basicAuthValue . "');\n";
                    $content = str_replace(
                        "define('HOTMART_SUBDOMAIN', 't101');\n",
                        "define('HOTMART_SUBDOMAIN', 't101');\n" . $newLine,
                        $content
                    );
                    
                    if (file_put_contents($configFile, $content)) {
                        echo '<div class="alert alert-success">';
                        echo '<strong>✅ Correção aplicada com sucesso!</strong><br>';
                        echo 'O arquivo de configuração foi atualizado. Por favor, teste novamente a API.';
                        echo '<br><br><a href="test_api.php" class="btn">Testar API Novamente</a>';
                        echo '</div>';
                    } else {
                        echo '<div class="alert alert-error">';
                        echo '<strong>❌ Erro:</strong> Não foi possível escrever no arquivo. Verifique as permissões.';
                        echo '</div>';
                    }
                }
            } catch (Exception $e) {
                echo '<div class="alert alert-error">';
                echo '<strong>❌ Erro:</strong> ' . htmlspecialchars($e->getMessage());
                echo '</div>';
            }
        }
        ?>
        
        <h2>4. Testar Autenticação</h2>
        <div class="step">
            <p>Após aplicar a correção, teste se a autenticação está funcionando:</p>
            
            <?php
            // Testar se BASIC_AUTH está definido agora
            if (defined('HOTMART_BASIC_AUTH') || isset($basicAuth)) {
                echo '<div class="test-result test-pass">';
                echo '✅ <strong>BASIC_AUTH está disponível!</strong> Testando autenticação...';
                echo '</div>';
                
                // Tentar obter token
                try {
                    $authString = defined('HOTMART_BASIC_AUTH') ? HOTMART_BASIC_AUTH : $basicAuth;
                    
                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => 'https://api-sec-vlc.hotmart.com/security/oauth/token',
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 30,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_POSTFIELDS => 'grant_type=client_credentials&client_id=' . urlencode($clientId) . '&client_secret=' . urlencode($clientSecret),
                        CURLOPT_HTTPHEADER => array(
                            'Authorization: Basic ' . $authString,
                            'Content-Type: application/x-www-form-urlencoded'
                        ),
                    ));
                    
                    $response = curl_exec($curl);
                    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                    curl_close($curl);
                    
                    $data = json_decode($response, true);
                    
                    if ($httpCode == 200 && isset($data['access_token'])) {
                        echo '<div class="test-result test-pass">';
                        echo '✅ <strong>Autenticação OAuth funcionando!</strong><br>';
                        echo 'Token obtido com sucesso (válido por ' . ($data['expires_in'] ?? 'N/A') . ' segundos)';
                        echo '</div>';
                        
                        echo '<div class="alert alert-success">';
                        echo '<strong>🎉 Problema Resolvido!</strong><br><br>';
                        echo 'A autenticação está funcionando corretamente. Agora você pode:';
                        echo '<ul>';
                        echo '<li><a href="test_api.php">Executar teste completo da API</a></li>';
                        echo '<li><a href="test_sync.php">Iniciar sincronização de progresso</a></li>';
                        echo '</ul>';
                        echo '</div>';
                    } else {
                        echo '<div class="test-result test-fail">';
                        echo '❌ <strong>Autenticação falhou</strong><br>';
                        echo 'HTTP Code: ' . $httpCode . '<br>';
                        echo 'Resposta: <code>' . htmlspecialchars($response) . '</code>';
                        echo '</div>';
                        
                        echo '<div class="alert alert-warning">';
                        echo '<strong>Possíveis causas:</strong><br>';
                        echo '<ul>';
                        echo '<li>CLIENT_ID ou CLIENT_SECRET estão incorretos</li>';
                        echo '<li>Credenciais expiraram ou foram revogadas</li>';
                        echo '<li>Problema de conectividade com a API Hotmart</li>';
                        echo '</ul>';
                        echo '</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="test-result test-fail">';
                    echo '❌ <strong>Erro ao testar:</strong> ' . htmlspecialchars($e->getMessage());
                    echo '</div>';
                }
            } else {
                echo '<div class="alert alert-warning">';
                echo '⚠️ Aplique a correção primeiro antes de testar.';
                echo '</div>';
            }
            ?>
        </div>
        
        <h2>5. Próximos Passos</h2>
        <div class="step">
            <ol>
                <li><strong>Aplicar a correção</strong> usando uma das opções acima</li>
                <li><strong>Testar a API</strong> em <a href="test_api.php">test_api.php</a></li>
                <li><strong>Executar sincronização</strong> em <a href="test_sync.php">test_sync.php</a></li>
            </ol>
        </div>
        
        <div class="alert alert-info">
            <strong>💡 Dica:</strong> Se a autenticação continuar falhando após aplicar a correção, verifique com a Hotmart se suas credenciais estão ativas e corretas.
        </div>
    </div>
</body>
</html>