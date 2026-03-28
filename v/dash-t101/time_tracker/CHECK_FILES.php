<?php
/**
 * Verificador Rápido - Onde estão os arquivos?
 * Acesse este arquivo para saber se os arquivos estão nos locais corretos
 */

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Verificador de Arquivos - Time Tracker</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .box {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .box.error { border-left: 4px solid #dc3545; }
        .box.success { border-left: 4px solid #28a745; }
        .box.warning { border-left: 4px solid #ffc107; }
        h1 { color: #333; }
        h2 { color: #666; margin-top: 0; }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            border-left: 3px solid #007bff;
        }
        .file-list { list-style: none; padding: 0; }
        .file-list li {
            padding: 8px;
            margin: 5px 0;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .file-list li.found { background: #d4edda; }
        .file-list li.missing { background: #f8d7da; }
        code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>🔍 Verificador de Arquivos - Time Tracker</h1>
    
    <?php
    echo '<div class="box">';
    echo '<h2>📍 Localização Atual deste Arquivo</h2>';
    echo '<p><b>Caminho completo:</b></p>';
    echo '<pre>' . __FILE__ . '</pre>';
    echo '<p><b>Diretório:</b></p>';
    echo '<pre>' . __DIR__ . '</pre>';
    echo '<p><b>URL deste arquivo:</b></p>';
    echo '<pre>' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '</pre>';
    echo '</div>';
    
    // Verificar arquivos necessários
    echo '<div class="box">';
    echo '<h2>📄 Verificando Arquivos</h2>';
    
    $files_to_check = [
        'api_time_tracker_debug.php' => __DIR__ . '/api_time_tracker_debug.php',
        'api_time_tracker.php' => __DIR__ . '/api_time_tracker.php',
        'time-tracker.php' => __DIR__ . '/time-tracker.php',
        'view_logs.php' => __DIR__ . '/view_logs.php',
        'test_installation.php' => __DIR__ . '/test_installation.php',
        'includes/auth_check.php' => __DIR__ . '/includes/auth_check.php',
        'config/database.php' => __DIR__ . '/config/database.php',
    ];
    
    echo '<ul class="file-list">';
    $all_found = true;
    foreach ($files_to_check as $name => $path) {
        $exists = file_exists($path);
        $class = $exists ? 'found' : 'missing';
        $icon = $exists ? '✅' : '❌';
        
        echo "<li class='$class'>$icon <b>$name</b>";
        if ($exists) {
            echo " - <small>OK</small>";
        } else {
            echo " - <small style='color: red;'>NÃO ENCONTRADO em: $path</small>";
            $all_found = false;
        }
        echo "</li>";
    }
    echo '</ul>';
    echo '</div>';
    
    // Instruções baseadas no resultado
    if (!$all_found) {
        echo '<div class="box error">';
        echo '<h2>⚠️ Arquivos Faltando!</h2>';
        echo '<p><b>Problema:</b> Alguns arquivos não estão no local correto.</p>';
        echo '<p><b>Solução:</b> Você precisa fazer upload dos arquivos para este diretório:</p>';
        echo '<pre>' . __DIR__ . '</pre>';
        echo '<p><b>Estrutura esperada:</b></p>';
        echo '<pre>';
        echo __DIR__ . '/
├── api_time_tracker_debug.php  ← Arquivo de debug
├── api_time_tracker.php        ← API principal
├── time-tracker.php            ← Interface
├── view_logs.php               ← Visualizador de logs
├── test_installation.php       ← Teste
├── includes/
│   └── auth_check.php
└── config/
    └── database.php
</pre>';
        echo '</div>';
    } else {
        echo '<div class="box success">';
        echo '<h2>✅ Todos os Arquivos Encontrados!</h2>';
        echo '<p>Os arquivos estão no local correto.</p>';
        echo '</div>';
    }
    
    // URLs de teste
    echo '<div class="box">';
    echo '<h2>🔗 URLs para Testar</h2>';
    
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $dir_parts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
    array_pop($dir_parts); // Remove o nome do arquivo atual
    $base_path = '/' . implode('/', $dir_parts);
    
    echo '<p><b>1. API Debug (deve retornar JSON):</b></p>';
    echo '<pre>' . $base_url . $base_path . '/api_time_tracker_debug.php?action=project_list</pre>';
    echo '<p><a href="' . $base_path . '/api_time_tracker_debug.php?action=project_list" target="_blank">➡️ Testar Agora</a></p>';
    
    echo '<p><b>2. Visualizador de Logs:</b></p>';
    echo '<pre>' . $base_url . $base_path . '/view_logs.php</pre>';
    echo '<p><a href="' . $base_path . '/view_logs.php" target="_blank">➡️ Abrir Logs</a></p>';
    
    echo '<p><b>3. Time Tracker:</b></p>';
    echo '<pre>' . $base_url . $base_path . '/time-tracker.php</pre>';
    echo '<p><a href="' . $base_path . '/time-tracker.php" target="_blank">➡️ Abrir Time Tracker</a></p>';
    
    echo '</div>';
    
    // Verificar permissões
    echo '<div class="box">';
    echo '<h2>🔐 Permissões</h2>';
    echo '<ul class="file-list">';
    foreach ($files_to_check as $name => $path) {
        if (file_exists($path)) {
            $perms = substr(sprintf('%o', fileperms($path)), -4);
            $readable = is_readable($path) ? '✅ Legível' : '❌ Não legível';
            echo "<li class='found'><b>$name</b>: $perms - $readable</li>";
        }
    }
    echo '</ul>';
    echo '</div>';
    
    // Informações do servidor
    echo '<div class="box">';
    echo '<h2>ℹ️ Informações do Servidor</h2>';
    echo '<p><b>PHP Version:</b> ' . phpversion() . '</p>';
    echo '<p><b>Server Software:</b> ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'Desconhecido') . '</p>';
    echo '<p><b>Document Root:</b> ' . ($_SERVER['DOCUMENT_ROOT'] ?? 'Desconhecido') . '</p>';
    echo '<p><b>Script Filename:</b> ' . ($_SERVER['SCRIPT_FILENAME'] ?? 'Desconhecido') . '</p>';
    echo '</div>';
    
    // Próximos passos
    if (!$all_found) {
        echo '<div class="box warning">';
        echo '<h2>📋 Próximos Passos</h2>';
        echo '<ol>';
        echo '<li>Faça upload dos arquivos faltando para: <code>' . __DIR__ . '</code></li>';
        echo '<li>Recarregue esta página para verificar novamente</li>';
        echo '<li>Quando todos os arquivos estiverem OK, teste as URLs acima</li>';
        echo '</ol>';
        echo '</div>';
    } else {
        echo '<div class="box success">';
        echo '<h2>🎉 Tudo Pronto!</h2>';
        echo '<p><b>Próximos passos:</b></p>';
        echo '<ol>';
        echo '<li>Abra o <a href="' . $base_path . '/view_logs.php">Visualizador de Logs</a></li>';
        echo '<li>Em outra aba, abra o <a href="' . $base_path . '/time-tracker.php">Time Tracker</a></li>';
        echo '<li>Use o Time Tracker normalmente</li>';
        echo '<li>Volte aos logs para ver o que está acontecendo</li>';
        echo '</ol>';
        echo '</div>';
    }
    ?>
    
</body>
</html>
