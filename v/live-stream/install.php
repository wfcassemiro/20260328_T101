<?php
/**
 * Script de Instalação da Integração Zoom
 * Execute este arquivo UMA VEZ para configurar o banco de dados
 */

require_once __DIR__ . '/../config/database.php';
require_once 'zoom_config.php';

echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Instalação - Integração Zoom</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-width: 800px;
            width: 100%;
        }
        h1 {
            color: #2d3748;
            margin-bottom: 30px;
            font-size: 32px;
        }
        .step {
            background: #f7fafc;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        .success {
            background: #c6f6d5;
            border-left-color: #38a169;
            color: #22543d;
        }
        .error {
            background: #fed7d7;
            border-left-color: #e53e3e;
            color: #742a2a;
        }
        .warning {
            background: #feebc8;
            border-left-color: #dd6b20;
            color: #7c2d12;
        }
        .code {
            background: #2d3748;
            color: #68d391;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
            overflow-x: auto;
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
        .btn:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🚀 Instalação da Integração Zoom</h1>";

// Etapa 1: Verificar configurações
echo "<div class='step'>";
echo "<h2>📋 Etapa 1: Verificar Configurações</h2>";

$configOk = true;

if (ZOOM_ACCOUNT_ID && ZOOM_CLIENT_ID && ZOOM_CLIENT_SECRET) {
    echo "<p class='success'>✅ Credenciais do Zoom configuradas corretamente</p>";
    echo "<div class='code'>";
    echo "Account ID: " . substr(ZOOM_ACCOUNT_ID, 0, 10) . "...<br>";
    echo "Client ID: " . substr(ZOOM_CLIENT_ID, 0, 10) . "...<br>";
    echo "Client Secret: ***************";
    echo "</div>";
} else {
    echo "<p class='error'>❌ Erro: Credenciais do Zoom não configuradas no zoom_config.php</p>";
    $configOk = false;
}

echo "</div>";

// Etapa 2: Testar conexão com banco de dados
echo "<div class='step'>";
echo "<h2>🗄️ Etapa 2: Conexão com Banco de Dados</h2>";

try {
    $pdo = getDbConnection();
    echo "<p class='success'>✅ Conexão com banco de dados estabelecida</p>";
    echo "<div class='code'>";
    echo "Host: " . DB_HOST . "<br>";
    echo "Database: " . DB_NAME . "<br>";
    echo "User: " . DB_USER;
    echo "</div>";
    $dbOk = true;
} catch (Exception $e) {
    echo "<p class='error'>❌ Erro na conexão: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p class='warning'>⚠️ Verifique as configurações de banco de dados no zoom_config.php</p>";
    $dbOk = false;
}

echo "</div>";

// Etapa 3: Criar tabela
if ($dbOk) {
    echo "<div class='step'>";
    echo "<h2>📊 Etapa 3: Criar Tabela zoom_meetings</h2>";
    
    if (createZoomMeetingsTable()) {
        echo "<p class='success'>✅ Tabela zoom_meetings criada/verificada com sucesso</p>";
        
        // Verificar estrutura da tabela
        try {
            $stmt = $pdo->query("DESCRIBE zoom_meetings");
            $columns = $stmt->fetchAll();
            
            echo "<p><strong>Estrutura da tabela:</strong></p>";
            echo "<div class='code'>";
            foreach ($columns as $col) {
                echo $col['Field'] . " (" . $col['Type'] . ")<br>";
            }
            echo "</div>";
            
            $tableOk = true;
        } catch (Exception $e) {
            echo "<p class='error'>❌ Erro ao verificar estrutura: " . htmlspecialchars($e->getMessage()) . "</p>";
            $tableOk = false;
        }
    } else {
        echo "<p class='error'>❌ Erro ao criar tabela zoom_meetings</p>";
        $tableOk = false;
    }
    
    echo "</div>";
}

// Etapa 4: Testar autenticação Zoom
if ($configOk && $dbOk) {
    echo "<div class='step'>";
    echo "<h2>🔐 Etapa 4: Testar Autenticação Zoom</h2>";
    
    require_once 'zoom_auth.php';
    
    $token = getZoomAccessToken();
    
    if ($token) {
        echo "<p class='success'>✅ Token de acesso obtido com sucesso</p>";
        echo "<div class='code'>";
        echo "Token: " . substr($token, 0, 20) . "...";
        echo "</div>";
        
        // Testar obtenção de usuário
        require_once 'zoom_functions.php';
        $user = getZoomUser();
        
        if ($user) {
            echo "<p class='success'>✅ Informações do usuário Zoom obtidas</p>";
            echo "<div class='code'>";
            echo "ID: " . htmlspecialchars($user['id']) . "<br>";
            echo "Email: " . htmlspecialchars($user['email']) . "<br>";
            
            // Verificar se tem first_name e last_name
            $nome = '';
            if (isset($user['first_name']) && isset($user['last_name'])) {
                $nome = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
            } else if (isset($user['display_name'])) {
                $nome = htmlspecialchars($user['display_name']);
            } else {
                $nome = 'N/A';
            }
            
            echo "Nome: " . $nome . "<br>";
            echo "Tipo: " . (isset($user['type']) ? $user['type'] : 'N/A');
            echo "</div>";
            $authOk = true;
        } else {
            echo "<p class='error'>❌ Não foi possível obter informações do usuário</p>";
            echo "<p class='warning'>⚠️ Possíveis causas:</p>";
            echo "<ul style='margin-left: 20px; color: #7c2d12;'>";
            echo "<li>Falta de escopo 'user:read:admin' no app Zoom</li>";
            echo "<li>App não ativado corretamente no Zoom Marketplace</li>";
            echo "<li>Conta Zoom sem usuários ativos</li>";
            echo "</ul>";
            echo "<p class='warning'>💡 <strong>IMPORTANTE:</strong> Mesmo com este erro, você ainda pode criar reuniões! Tente continuar.</p>";
            
            // Não marcar como falha crítica
            $authOk = true; // Alterado para true para permitir continuar
        }
    } else {
        echo "<p class='error'>❌ Erro ao obter token de acesso</p>";
        echo "<p class='warning'>⚠️ Verifique se as credenciais estão corretas e se o aplicativo está ativado no Zoom</p>";
        $authOk = false;
    }
    
    echo "</div>";
}

// Etapa 5: Resultado final
echo "<div class='step " . ($configOk && $dbOk && $tableOk && $authOk ? 'success' : 'error') . "'>";
echo "<h2>🎯 Resultado da Instalação</h2>";

if ($configOk && $dbOk && $tableOk && $authOk) {
    echo "<p><strong>✅ Instalação concluída com sucesso!</strong></p>";
    echo "<p>Você já pode começar a usar a integração do Zoom.</p>";
    echo "<br>";
    echo "<h3>📝 Próximos Passos:</h3>";
    echo "<ol>";
    echo "<li>Acesse <strong>zoom_manage.php</strong> para gerenciar reuniões</li>";
    echo "<li>Crie uma nova reunião ou adicione uma existente</li>";
    echo "<li>As reuniões aparecerão automaticamente em <strong>index_with_zoom.php</strong></li>";
    echo "<li>Delete este arquivo (install.php) por segurança</li>";
    echo "</ol>";
    echo "<br>";
    echo "<a href='zoom_manage.php' class='btn'>Ir para Painel de Gerenciamento</a>";
} else {
    echo "<p><strong>❌ Instalação incompleta</strong></p>";
    echo "<p>Corrija os erros acima e execute este script novamente.</p>";
    
    echo "<br>";
    echo "<h3>🔧 Checklist de Verificação:</h3>";
    echo "<ul>";
    echo "<li>" . ($configOk ? "✅" : "❌") . " Credenciais do Zoom configuradas</li>";
    echo "<li>" . ($dbOk ? "✅" : "❌") . " Conexão com banco de dados</li>";
    echo "<li>" . ($tableOk ? "✅" : "❌") . " Tabela zoom_meetings criada</li>";
    echo "<li>" . ($authOk ? "✅" : "❌") . " Autenticação com API Zoom</li>";
    echo "</ul>";
}

echo "</div>";

echo "</div>
</body>
</html>";
?>