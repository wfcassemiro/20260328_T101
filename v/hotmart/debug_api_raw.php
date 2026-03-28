<?php
/**
 * Script de debug para testar a API Hotmart diretamente
 * Mostra EXATAMENTE o que cada endpoint retorna
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Incluir arquivos necessários
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/hotmart.php';
require_once __DIR__ . '/../hotmart.php';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug API Hotmart - RAW Response</title>
    <style>
        body {
            font-family: monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        h1 {
            color: #4ec9b0;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 10px;
        }
        h2 {
            color: #569cd6;
            margin-top: 30px;
        }
        .test-section {
            background: #252526;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            border: 1px solid #3e3e42;
        }
        pre {
            background: #2d2d2d;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            border-left: 3px solid #569cd6;
            max-height: 600px;
            overflow-y: auto;
        }
        .success {
            color: #4ec9b0;
        }
        .error {
            color: #f48771;
        }
        .warning {
            color: #dcdcaa;
        }
        .info {
            color: #569cd6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug RAW API Hotmart</h1>
        <p class="info">Este script mostra a resposta CRUA de cada endpoint da Hotmart</p>
        
        <?php
        $api = new HotmartAPI();
        $subdomain = defined('HOTMART_SUBDOMAIN') ? HOTMART_SUBDOMAIN : 'assinaturapremiumplustranslato';
        ?>
        
        <div class="test-section">
            <h2>1. Club Users API - getClubUsers('<?= $subdomain ?>')</h2>
            <?php
            try {
                $clubResult = $api->getClubUsers($subdomain);
                
                echo '<strong>Success:</strong> ' . ($clubResult['success'] ? '<span class="success">true</span>' : '<span class="error">false</span>') . '<br>';
                echo '<strong>Response completa:</strong>';
                echo '<pre>' . json_encode($clubResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
                
                // Análise da estrutura
                echo '<strong>Análise da Estrutura:</strong><br>';
                if (isset($clubResult['data'])) {
                    echo '- Tem "data"<br>';
                    if (isset($clubResult['data']['items'])) {
                        echo '- Tem "data.items" com ' . count($clubResult['data']['items']) . ' items<br>';
                        
                        if (!empty($clubResult['data']['items'])) {
                            echo '<strong>Exemplo do primeiro item:</strong>';
                            echo '<pre>' . json_encode($clubResult['data']['items'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
                        }
                    } else {
                        echo '- NÃO tem "data.items"<br>';
                        echo '- Chaves em "data": ' . implode(', ', array_keys($clubResult['data'])) . '<br>';
                    }
                } elseif (isset($clubResult['items'])) {
                    echo '- Tem "items" direto com ' . count($clubResult['items']) . ' items<br>';
                } else {
                    echo '- <span class="warning">Estrutura não reconhecida!</span><br>';
                    echo '- Chaves no root: ' . implode(', ', array_keys($clubResult)) . '<br>';
                }
                
            } catch (Exception $e) {
                echo '<span class="error">ERRO: ' . htmlspecialchars($e->getMessage()) . '</span>';
            }
            ?>
        </div>
        
        <div class="test-section">
            <h2>2. Subscriptions API - getSubscriptions('ACTIVE')</h2>
            <?php
            try {
                $subsResult = $api->getSubscriptions('ACTIVE');
                
                echo '<strong>Success:</strong> ' . ($subsResult['success'] ? '<span class="success">true</span>' : '<span class="error">false</span>') . '<br>';
                echo '<strong>Response completa:</strong>';
                echo '<pre>' . json_encode($subsResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
                
                // Análise da estrutura
                echo '<strong>Análise da Estrutura:</strong><br>';
                if (isset($subsResult['data'])) {
                    echo '- Tem "data"<br>';
                    if (isset($subsResult['data']['items'])) {
                        echo '- Tem "data.items" com ' . count($subsResult['data']['items']) . ' items<br>';
                        
                        if (!empty($subsResult['data']['items'])) {
                            echo '<strong>Exemplo do primeiro item:</strong>';
                            echo '<pre>' . json_encode($subsResult['data']['items'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
                        }
                    } else {
                        echo '- NÃO tem "data.items"<br>';
                        echo '- Chaves em "data": ' . implode(', ', array_keys($subsResult['data'])) . '<br>';
                    }
                } else {
                    echo '- <span class="warning">NÃO tem "data"!</span><br>';
                    echo '- Chaves no root: ' . implode(', ', array_keys($subsResult)) . '<br>';
                }
                
            } catch (Exception $e) {
                echo '<span class="error">ERRO: ' . htmlspecialchars($e->getMessage()) . '</span>';
            }
            ?>
        </div>
        
        <div class="test-section">
            <h2>3. Verificar Usuários no Banco Local</h2>
            <?php
            try {
                // Total de usuários
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
                $totalUsers = $stmt->fetch()['total'];
                echo '<strong>Total de usuários no banco:</strong> ' . $totalUsers . '<br><br>';
                
                // Usuários com hotmart_subscription_id
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE hotmart_subscription_id IS NOT NULL AND hotmart_subscription_id != ''");
                $withSub = $stmt->fetch()['total'];
                echo '<strong>Com hotmart_subscription_id:</strong> ' . $withSub . '<br><br>';
                
                // Usuários com hotmart_ucode
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE hotmart_ucode IS NOT NULL AND hotmart_ucode != ''");
                $withUcode = $stmt->fetch()['total'];
                echo '<strong>Com hotmart_ucode:</strong> ' . $withUcode . '<br><br>';
                
                // Usuários ativos
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
                $active = $stmt->fetch()['total'];
                echo '<strong>Usuários ativos:</strong> ' . $active . '<br><br>';
                
                // Alguns exemplos
                echo '<strong>Primeiros 5 usuários com dados Hotmart:</strong><br>';
                $stmt = $pdo->query("
                    SELECT email, name, hotmart_subscription_id, hotmart_ucode, hotmart_status 
                    FROM users 
                    WHERE (hotmart_subscription_id IS NOT NULL OR hotmart_ucode IS NOT NULL)
                    LIMIT 5
                ");
                
                $examples = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($examples)) {
                    echo '<pre>' . json_encode($examples, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
                } else {
                    echo '<span class="warning">Nenhum usuário com dados Hotmart encontrado</span><br>';
                }
                
            } catch (Exception $e) {
                echo '<span class="error">ERRO: ' . htmlspecialchars($e->getMessage()) . '</span>';
            }
            ?>
        </div>
        
        <div class="test-section">
            <h2>4. Informações de Configuração</h2>
            <?php
            echo '<strong>Subdomain:</strong> ' . HOTMART_SUBDOMAIN . '<br>';
            echo '<strong>Client ID:</strong> ' . HOTMART_CLIENT_ID . '<br>';
            echo '<strong>HOT_TOKEN definido:</strong> ' . (defined('HOTMART_HOT_TOKEN') ? 'Sim (' . strlen(HOTMART_HOT_TOKEN) . ' caracteres)' : 'Não') . '<br>';
            echo '<strong>BASIC_AUTH definido:</strong> ' . (defined('HOTMART_BASIC_AUTH') ? 'Sim' : '<span class="error">NÃO</span>') . '<br>';
            ?>
        </div>
        
        <div class="test-section">
            <h2>💡 Interpretação dos Resultados</h2>
            <ul>
                <li><strong>Se Club Users retornar array vazio mas success=true:</strong> Não há usuários cadastrados no Hotmart Club</li>
                <li><strong>Se Subscriptions falhar:</strong> OAuth não está funcionando (precisa BASIC_AUTH)</li>
                <li><strong>Se ambos retornarem vazio:</strong> Pode ser problema de permissões do token ou não há usuários</li>
                <li><strong>Se estrutura for diferente:</strong> A API mudou ou está retornando formato inesperado</li>
            </ul>
        </div>
        
        <div style="margin-top: 30px; padding: 20px; background: #252526; border-radius: 8px;">
            <h3 style="color: #4ec9b0;">📋 Próximos Passos Sugeridos</h3>
            <ol style="color: #d4d4d4;">
                <li>Se não houver usuários no Club, verifique no painel da Hotmart se há assinantes ativos</li>
                <li>Verifique se o subdomain 't101' está correto</li>
                <li>Verifique se o HOT_TOKEN tem permissões para acessar dados de usuários</li>
                <li>Se Subscriptions falhar, corrija o BASIC_AUTH usando fix_auth.php</li>
            </ol>
        </div>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="test_sync.php" style="color: #4ec9b0; text-decoration: none;">← Voltar para Sincronização</a> | 
            <a href="test_api.php" style="color: #4ec9b0; text-decoration: none;">Teste Completo de API</a> |
            <a href="fix_auth.php" style="color: #4ec9b0; text-decoration: none;">Corrigir Autenticação</a>
        </div>
    </div>
</body>
</html>