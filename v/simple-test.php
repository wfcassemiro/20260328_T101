<?php
// Teste básico 1
echo '<h1>Teste 1: PHP Funcionando</h1>';
echo '<p>Se você vê isso, PHP está executando!</p>';

// Teste básico 2 - Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo '<h2>Teste 2: Error reporting ativado</h2>';

// Teste básico 3 - Sessão
session_start();
echo '<h2>Teste 3: Sessão iniciada</h2>';

// Teste básico 4 - Incluir database
echo '<h2>Teste 4: Testando database.php</h2>';
try {
    if (file_exists('config/database.php')) {
        echo '<p>✅ Arquivo config/database.php existe</p>';
        
        // Ler as primeiras linhas para ver se tem problema
        $content = file_get_contents('config/database.php');
        $lines = explode("\n", $content);
        echo '<p>Primeira linha: ' . htmlspecialchars($lines[0]) . '</p>';
        echo '<p>Segunda linha: ' . htmlspecialchars($lines[1]) . '</p>';
        
        // Tentar incluir
        include_once 'config/database.php';
        echo '<p>✅ Database incluído sem erro</p>';
        
        if (isset($pdo)) {
            echo '<p>✅ Variável $pdo existe</p>';
        } else {
            echo '<p>❌ Variável $pdo NÃO existe</p>';
        }
        
    } else {
        echo '<p>❌ Arquivo config/database.php NÃO existe</p>';
    }
} catch (Exception $e) {
    echo '<p>❌ ERRO: ' . $e->getMessage() . '</p>';
}

echo '<h2>✅ Teste básico completo!</h2>';
?>