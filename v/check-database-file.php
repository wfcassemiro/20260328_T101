<?php
echo '<h1>🔍 Verificando arquivo database.php</h1>';

$file = 'config/database.php';

if (file_exists($file)) {
    echo '<h2>✅ Arquivo existe</h2>';
    
    // Ler conteúdo
    $content = file_get_contents($file);
    
    // Mostrar tamanho
    echo '<p>Tamanho: ' . strlen($content) . ' bytes</p>';
    
    // Mostrar primeiras linhas
    $lines = explode("\n", $content);
    echo '<h3>📄 Primeiras 10 linhas:</h3>';
    echo '<pre style="background: #f0f0f0; padding: 10px; overflow-x: auto;">';
    for ($i = 0; $i < min(10, count($lines)); $i++) {
        echo ($i+1) . ': ' . htmlspecialchars($lines[$i]) . "\n";
    }
    echo '</pre>';
    
    // Verificar problemas comuns
    echo '<h3>🔍 Verificações:</h3>';
    
    if (substr($content, 0, 5) !== '<?php') {
        echo '<p>❌ PROBLEMA: Arquivo não começa com &lt;?php</p>';
        echo '<p>Primeiros 20 caracteres: ' . htmlspecialchars(substr($content, 0, 20)) . '</p>';
    } else {
        echo '<p>✅ Arquivo começa com &lt;?php</p>';
    }
    
    if (strpos($content, '$pdo') !== false) {
        echo '<p>✅ Contém variável $pdo</p>';
    } else {
        echo '<p>❌ NÃO contém variável $pdo</p>';
    }
    
    if (strpos($content, 'PDO') !== false) {
        echo '<p>✅ Contém classe PDO</p>';
    } else {
        echo '<p>❌ NÃO contém classe PDO</p>';
    }
    
} else {
    echo '<h2>❌ Arquivo NÃO existe!</h2>';
    
    // Listar arquivos na pasta config
    echo '<h3>📁 Arquivos na pasta config:</h3>';
    if (is_dir('config')) {
        $files = scandir('config');
        foreach ($files as $f) {
            if ($f !== '.' && $f !== '..') {
                echo '<p>- ' . $f . '</p>';
            }
        }
    } else {
        echo '<p>❌ Pasta config não existe!</p>';
    }
}
?>