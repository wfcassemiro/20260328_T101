<?php
echo "<h3>Procurando auth-check.php</h3>";

// Testa mais locais possíveis
$paths_to_test = [
    // Raiz do site
    dirname($_SERVER['DOCUMENT_ROOT']) . '/auth-check.php',
    
    // Dentro de pastas comuns
    dirname($_SERVER['DOCUMENT_ROOT']) . '/includes/auth-check.php',
    dirname($_SERVER['DOCUMENT_ROOT']) . '/functions/auth-check.php',
    dirname($_SERVER['DOCUMENT_ROOT']) . '/system/auth-check.php',
    
    // Dentro de /v/
    $_SERVER['DOCUMENT_ROOT'] . '/includes/auth-check.php',
    $_SERVER['DOCUMENT_ROOT'] . '/functions/auth-check.php',
    $_SERVER['DOCUMENT_ROOT'] . '/system/auth-check.php',
    
    // Outros locais
    __DIR__ . '/../includes/auth-check.php',
    __DIR__ . '/../../includes/auth-check.php',
];

foreach ($paths_to_test as $path) {
    echo "<p><strong>$path:</strong> " . (file_exists($path) ? "✅ EXISTE" : "❌ NÃO EXISTE") . "</p>";
}

// Vamos também listar o que tem na pasta /v/
echo "<h3>Conteúdo da pasta /v/:</h3>";
$files = scandir($_SERVER['DOCUMENT_ROOT']);
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        echo "<p>📁 $file</p>";
    }
}
?>