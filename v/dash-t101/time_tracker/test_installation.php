<?php
/**
 * Script de Verificação da Instalação do Time Tracker
 * Execute este arquivo para verificar se tudo está configurado corretamente
 */

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Verificação do Time Tracker</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-result {
            background: white;
            padding: 20px;
            margin: 10px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .success { border-left: 4px solid #28a745; }
        .error { border-left: 4px solid #dc3545; }
        .warning { border-left: 4px solid #ffc107; }
        h1 { color: #333; }
        h2 { color: #666; margin-top: 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .icon { font-size: 24px; margin-right: 10px; }
    </style>
</head>
<body>
    <h1>🔍 Verificação da Instalação do Time Tracker</h1>
    
    <?php
    $tests = [];
    
    // Teste 1: Verificar arquivos necessários
    echo '<div class="test-result">';
    echo '<h2>1. Verificando Arquivos</h2>';
    
    $required_files = [
        'time-tracker.php' => __DIR__ . '/time-tracker.php',
        'api_time_tracker.php' => __DIR__ . '/api_time_tracker.php',
        'includes/auth_check.php' => __DIR__ . '/includes/auth_check.php',
        'config/database.php' => __DIR__ . '/config/database.php',
        'config/dash_database.php' => __DIR__ . '/config/dash_database.php',
        'config/dash_functions.php' => __DIR__ . '/config/dash_functions.php',
        'vision/assets/js/time-tracker-v2.js' => __DIR__ . '/vision/assets/js/time-tracker-v2.js',
    ];
    
    $all_files_ok = true;
    foreach ($required_files as $name => $path) {
        if (file_exists($path)) {
            echo "<p class='success'><span class='icon'>✅</span> $name - <b>OK</b></p>";
        } else {
            echo "<p class='error'><span class='icon'>❌</span> $name - <b>NÃO ENCONTRADO</b></p>";
            $all_files_ok = false;
        }
    }
    
    if ($all_files_ok) {
        echo "<p><b>✅ Todos os arquivos estão presentes!</b></p>";
    } else {
        echo "<p><b>⚠️ Alguns arquivos estão faltando. Verifique a instalação.</b></p>";
    }
    echo '</div>';
    
    // Teste 2: Conexão com banco de dados
    echo '<div class="test-result">';
    echo '<h2>2. Testando Conexão com Banco de Dados</h2>';
    
    try {
        require_once __DIR__ . '/config/database.php';
        
        if (isset($pdo)) {
            echo "<p class='success'><span class='icon'>✅</span> Conexão com banco de dados - <b>OK</b></p>";
            
            // Verificar tabelas
            $tables_to_check = ['dash_projects', 'time_tasks', 'time_entries'];
            echo "<h3>Verificando Tabelas:</h3>";
            
            foreach ($tables_to_check as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) {
                    echo "<p class='success'><span class='icon'>✅</span> Tabela '$table' - <b>EXISTE</b></p>";
                    
                    // Contar registros
                    $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
                    echo "<p style='margin-left: 40px;'>📊 Registros: $count</p>";
                } else {
                    echo "<p class='error'><span class='icon'>❌</span> Tabela '$table' - <b>NÃO EXISTE</b></p>";
                    echo "<p style='margin-left: 40px;'>⚠️ Execute o SQL: sql/create_time_tracker_tables.sql</p>";
                }
            }
        } else {
            echo "<p class='error'><span class='icon'>❌</span> Erro na conexão com banco de dados</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'><span class='icon'>❌</span> Erro: " . $e->getMessage() . "</p>";
    }
    echo '</div>';
    
    // Teste 3: Verificar funções
    echo '<div class="test-result">';
    echo '<h2>3. Verificando Funções Auxiliares</h2>';
    
    $functions = ['isLoggedIn', 'isAdmin', 'isSubscriber', 'getCurrentUserId'];
    foreach ($functions as $func) {
        if (function_exists($func)) {
            echo "<p class='success'><span class='icon'>✅</span> Função '$func()' - <b>DEFINIDA</b></p>";
        } else {
            echo "<p class='error'><span class='icon'>❌</span> Função '$func()' - <b>NÃO DEFINIDA</b></p>";
        }
    }
    echo '</div>';
    
    // Teste 4: Verificar permissões
    echo '<div class="test-result">';
    echo '<h2>4. Verificando Permissões de Arquivos</h2>';
    
    foreach ($required_files as $name => $path) {
        if (file_exists($path)) {
            $perms = substr(sprintf('%o', fileperms($path)), -4);
            if (is_readable($path)) {
                echo "<p class='success'><span class='icon'>✅</span> $name - Permissões: $perms - <b>LEGÍVEL</b></p>";
            } else {
                echo "<p class='error'><span class='icon'>❌</span> $name - Permissões: $perms - <b>NÃO LEGÍVEL</b></p>";
            }
        }
    }
    echo '</div>';
    
    // Teste 5: Informações do sistema
    echo '<div class="test-result">';
    echo '<h2>5. Informações do Sistema</h2>';
    echo "<p><b>PHP Version:</b> " . phpversion() . "</p>";
    echo "<p><b>Server:</b> " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
    echo "<p><b>Document Root:</b> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
    echo "<p><b>Script Path:</b> " . __DIR__ . "</p>";
    echo '</div>';
    
    // Teste 6: URLs de acesso
    echo '<div class="test-result">';
    echo '<h2>6. URLs de Acesso</h2>';
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    echo "<p><b>Interface do Time Tracker:</b><br>";
    echo "<a href='{$protocol}://{$host}/dash-t101/time-tracker.php' target='_blank'>";
    echo "{$protocol}://{$host}/dash-t101/time-tracker.php</a></p>";
    
    echo "<p><b>API do Time Tracker:</b><br>";
    echo "<a href='{$protocol}://{$host}/dash-t101/api_time_tracker.php?action=project_list' target='_blank'>";
    echo "{$protocol}://{$host}/dash-t101/api_time_tracker.php</a></p>";
    
    echo '</div>';
    
    // Resumo Final
    echo '<div class="test-result">';
    echo '<h2>📋 Resumo da Verificação</h2>';
    
    if ($all_files_ok && isset($pdo)) {
        echo "<p class='success'><span class='icon'>🎉</span> <b>Instalação parece estar correta!</b></p>";
        echo "<p>Você pode acessar o Time Tracker através do link acima.</p>";
        echo "<p><b>Próximos passos:</b></p>";
        echo "<ol>";
        echo "<li>Faça login no sistema</li>";
        echo "<li>Acesse o Time Tracker</li>";
        echo "<li>Crie um projeto de teste</li>";
        echo "<li>Inicie e pare o cronômetro</li>";
        echo "<li>Verifique se os registros aparecem no histórico</li>";
        echo "</ol>";
    } else {
        echo "<p class='error'><span class='icon'>⚠️</span> <b>Há problemas na instalação.</b></p>";
        echo "<p>Revise os erros acima e corrija-os antes de usar o sistema.</p>";
        echo "<p>Consulte o arquivo README.md para instruções detalhadas de instalação.</p>";
    }
    
    echo '</div>';
    ?>
    
    <div class="test-result">
        <p><small>Desenvolvido para Translators 101 - v.translators101.com</small></p>
    </div>
</body>
</html>
