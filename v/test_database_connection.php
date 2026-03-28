<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

echo "<h2>Teste de Conexão - Base de Dados</h2>";

// Tentar diferentes caminhos para database.php
$possible_paths = [
    __DIR__ . '/config/database.php',
    __DIR__ . '/../config/database.php', 
    './config/database.php',
    '../config/database.php'
];

echo "<h3>1. Testando caminhos para database.php:</h3>";
$database_included = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        echo "✅ ENCONTRADO: " . $path . "<br>";
        require_once $path;
        $database_included = true;
        break;
    } else {
        echo "❌ NÃO ENCONTRADO: " . $path . "<br>";
    }
}

if (!$database_included) {
    die("<br>❌ <strong>ERRO CRÍTICO:</strong> database.php não encontrado em nenhum caminho!");
}

echo "<h3>2. Testando conexão PDO:</h3>";
if (isset($pdo)) {
    echo "✅ Variável \$pdo está definida<br>";
    try {
        $stmt = $pdo->query("SELECT 1");
        echo "✅ Conexão com banco funcionando<br>";
    } catch (Exception $e) {
        echo "❌ Erro na conexão: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Variável \$pdo NÃO está definida<br>";
}

echo "<h3>3. Testando tabela upcoming_announcements:</h3>";
try {
    $stmt = $pdo->query("DESCRIBE upcoming_announcements");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Tabela upcoming_announcements encontrada<br>";
    echo "<strong>Colunas:</strong><br>";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro com tabela upcoming_announcements: " . $e->getMessage() . "<br>";
}

echo "<h3>4. Testando busca por ID específico:</h3>";
$test_id = '115faa0d55024b9b9670b82c4c7f9ad4';
try {
    $stmt = $pdo->prepare("SELECT * FROM upcoming_announcements WHERE id = ?");
    $stmt->execute([$test_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "✅ Registro encontrado para ID: $test_id<br>";
        echo "<strong>Dados:</strong><br>";
        foreach ($result as $key => $value) {
            echo "- $key: $value<br>";
        }
    } else {
        echo "⚠️ Nenhum registro encontrado para ID: $test_id<br>";
    }
} catch (Exception $e) {
    echo "❌ Erro na busca: " . $e->getMessage() . "<br>";
}

echo "<h3>5. Testando sessão:</h3>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";
echo "Is Admin: " . ($_SESSION['is_admin'] ?? 'NOT SET') . "<br>";
echo "User Role: " . ($_SESSION['user_role'] ?? 'NOT SET') . "<br>";

?>