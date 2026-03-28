<?php
/**
 * Teste de Autenticação - Novas Credenciais Hotmart
 * Atualizado: 30-Oct-2025
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Carregar configurações
require_once __DIR__ . '/../config/hotmart.php';
require_once __DIR__ . '/../hotmart.php';

echo "<!DOCTYPE html>\n<html>\n<head>\n<meta charset='UTF-8'>\n<title>Teste de Autenticação Hotmart</title>\n";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5;}";
echo ".success{color:green;font-weight:bold;}";
echo ".error{color:red;font-weight:bold;}";
echo ".info{color:blue;}";
echo ".box{background:white;padding:20px;margin:10px 0;border-radius:5px;box-shadow:0 2px 4px rgba(0,0,0,0.1);}";
echo "pre{background:#f0f0f0;padding:10px;border-radius:3px;overflow-x:auto;}";
echo "</style>\n</head>\n<body>\n";

echo "<h1>🔐 Teste de Autenticação Hotmart</h1>\n";

echo "<div class='box'>\n";
echo "<h2>1️⃣ Verificação de Credenciais</h2>\n";
echo "<strong>CLIENT_ID:</strong> " . HOTMART_CLIENT_ID . "<br>\n";
echo "<strong>CLIENT_SECRET:</strong> " . substr(HOTMART_CLIENT_SECRET, 0, 10) . "... (truncado por segurança)<br>\n";
echo "<strong>BASIC_AUTH:</strong> " . (defined('HOTMART_BASIC_AUTH') ? '✅ Definido' : '❌ NÃO definido') . "<br>\n";
echo "<strong>HOT_TOKEN:</strong> " . (defined('HOTMART_HOT_TOKEN') && !empty(HOTMART_HOT_TOKEN) ? '✅ Definido' : '❌ NÃO definido') . "<br>\n";
echo "<strong>SUBDOMAIN:</strong> <span class='info'>" . HOTMART_SUBDOMAIN . "</span><br>\n";
echo "</div>\n";

// Testar autenticação OAuth
echo "<div class='box'>\n";
echo "<h2>2️⃣ Teste de Autenticação OAuth 2.0</h2>\n";

$api = new HotmartAPI();

echo "<p>Tentando obter Access Token...</p>\n";
$token = $api->getAccessToken();

if ($token) {
    echo "<p class='success'>✅ Autenticação bem-sucedida!</p>\n";
    echo "<p><strong>Access Token:</strong> " . substr($token, 0, 20) . "...</p>\n";
} else {
    echo "<p class='error'>❌ Falha na autenticação!</p>\n";
    echo "<p>Verifique os logs do PHP para mais detalhes.</p>\n";
}
echo "</div>\n";

// Testar endpoint de Subscriptions
if ($token) {
    echo "<div class='box'>\n";
    echo "<h2>3️⃣ Teste do Endpoint: Subscriptions</h2>\n";
    
    $subsResult = $api->getSubscriptions('ACTIVE');
    
    if ($subsResult['success']) {
        echo "<p class='success'>✅ Endpoint funcionando!</p>\n";
        echo "<p><strong>HTTP Code:</strong> " . $subsResult['http_code'] . "</p>\n";
        
        if (isset($subsResult['data']['items'])) {
            $count = count($subsResult['data']['items']);
            echo "<p><strong>Assinaturas encontradas:</strong> {$count}</p>\n";
            
            if ($count > 0) {
                echo "<h3>Primeira assinatura:</h3>\n";
                $first = $subsResult['data']['items'][0];
                echo "<pre>" . json_encode($first, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>\n";
            }
        } else {
            echo "<p class='info'>ℹ️ Resposta vazia ou sem campo 'items'</p>\n";
            echo "<pre>" . json_encode($subsResult['data'], JSON_PRETTY_PRINT) . "</pre>\n";
        }
    } else {
        echo "<p class='error'>❌ Erro no endpoint</p>\n";
        echo "<p><strong>Mensagem:</strong> " . ($subsResult['message'] ?? 'Sem mensagem') . "</p>\n";
        echo "<p><strong>HTTP Code:</strong> " . ($subsResult['http_code'] ?? 'N/A') . "</p>\n";
    }
    echo "</div>\n";
    
    // Testar endpoint Club Users
    echo "<div class='box'>\n";
    echo "<h2>4️⃣ Teste do Endpoint: Club Users</h2>\n";
    
    $clubResult = $api->getClubUsers(HOTMART_SUBDOMAIN);
    
    if ($clubResult['success']) {
        echo "<p class='success'>✅ Endpoint funcionando!</p>\n";
        echo "<p><strong>HTTP Code:</strong> " . $clubResult['http_code'] . "</p>\n";
        
        if (isset($clubResult['data']) && is_array($clubResult['data'])) {
            $count = count($clubResult['data']);
            echo "<p><strong>Usuários encontrados:</strong> {$count}</p>\n";
            
            if ($count > 0) {
                echo "<h3>Primeiro usuário:</h3>\n";
                echo "<pre>" . json_encode($clubResult['data'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>\n";
            }
        } else {
            echo "<p class='info'>ℹ️ Resposta vazia</p>\n";
        }
    } else {
        echo "<p class='error'>❌ Erro no endpoint</p>\n";
        echo "<p><strong>Mensagem:</strong> " . ($clubResult['message'] ?? 'Sem mensagem') . "</p>\n";
        echo "<p><strong>HTTP Code:</strong> " . ($clubResult['http_code'] ?? 'N/A') . "</p>\n";
    }
    echo "</div>\n";
    
    // Se temos assinaturas, testar getUserProgress
    if (isset($subsResult['data']['items']) && count($subsResult['data']['items']) > 0) {
        $firstSub = $subsResult['data']['items'][0];
        if (isset($firstSub['subscriber']['ucode'])) {
            $ucode = $firstSub['subscriber']['ucode'];
            
            echo "<div class='box'>\n";
            echo "<h2>5️⃣ Teste do Endpoint: User Progress</h2>\n";
            echo "<p><strong>Ucode testado:</strong> {$ucode}</p>\n";
            
            $progressResult = $api->getUserProgress($ucode);
            
            if ($progressResult['success']) {
                echo "<p class='success'>✅ Endpoint funcionando!</p>\n";
                
                if (isset($progressResult['data']['items'])) {
                    $count = count($progressResult['data']['items']);
                    echo "<p class='success'><strong>🎉 Progresso encontrado: {$count} itens!</strong></p>\n";
                    echo "<pre>" . json_encode($progressResult['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>\n";
                } elseif (isset($progressResult['data']['pages'])) {
                    $count = count($progressResult['data']['pages']);
                    echo "<p class='success'><strong>🎉 Páginas encontradas: {$count} páginas!</strong></p>\n";
                    echo "<pre>" . json_encode($progressResult['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>\n";
                } else {
                    echo "<p class='info'>ℹ️ API retornou sucesso mas sem dados de progresso</p>\n";
                    echo "<pre>" . json_encode($progressResult['data'], JSON_PRETTY_PRINT) . "</pre>\n";
                }
            } else {
                echo "<p class='error'>❌ Erro no endpoint</p>\n";
                echo "<p><strong>Mensagem:</strong> " . ($progressResult['message'] ?? 'Sem mensagem') . "</p>\n";
            }
            echo "</div>\n";
        }
    }
}

echo "<div class='box'>\n";
echo "<h2>📋 Logs do Sistema</h2>\n";
echo "<p>Para ver logs detalhados:</p>\n";
echo "<pre>tail -f /var/log/php_errors.log | grep HOTMART</pre>\n";
echo "</div>\n";

echo "<div class='box'>\n";
echo "<h2>✅ Conclusão</h2>\n";
if ($token) {
    echo "<p class='success'>🎉 Autenticação funcionando corretamente com as novas credenciais!</p>\n";
    echo "<p>Próximo passo: Executar a sincronização completa em <a href='test_sync_local.php'>test_sync_local.php</a></p>\n";
} else {
    echo "<p class='error'>❌ Problemas de autenticação detectados.</p>\n";
    echo "<p>Verifique:</p>\n";
    echo "<ul>\n";
    echo "<li>CLIENT_ID e CLIENT_SECRET estão corretos</li>\n";
    echo "<li>BASIC_AUTH está definido corretamente</li>\n";
    echo "<li>Logs do PHP para mensagens de erro detalhadas</li>\n";
    echo "</ul>\n";
}
echo "</div>\n";

echo "</body>\n</html>";
?>