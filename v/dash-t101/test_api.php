<?php
/**
 * Teste simples da API Time Tracker
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Teste da API Time Tracker</h1>";

echo "<h2>1. Verificando Arquivos</h2>";
$files = [
    'auth_check' => __DIR__ . '/includes/auth_check.php',
    'functions' => __DIR__ . '/includes/functions.php',
    'database' => __DIR__ . '/config/database.php',
    'api' => __DIR__ . '/api_time_tracker.php'
];

foreach ($files as $name => $path) {
    $exists = file_exists($path);
    $color = $exists ? 'green' : 'red';
    echo "<p style='color: $color;'>$name: " . ($exists ? 'EXISTE' : 'NÃO EXISTE') . " ($path)</p>";
}

echo "<h2>2. Testando Includes</h2>";
try {
    require_once __DIR__ . '/includes/auth_check.php';
    echo "<p style='color: green;'>✓ auth_check.php carregado</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro ao carregar auth_check.php: " . $e->getMessage() . "</p>";
}

try {
    require_once __DIR__ . '/includes/functions.php';
    echo "<p style='color: green;'>✓ functions.php carregado</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro ao carregar functions.php: " . $e->getMessage() . "</p>";
}

try {
    require_once __DIR__ . '/config/database.php';
    echo "<p style='color: green;'>✓ database.php carregado</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro ao carregar database.php: " . $e->getMessage() . "</p>";
}

echo "<h2>3. Verificando Funções</h2>";
$functions = ['generateUUID', 'formatDuration', 'sanitizeInput', 'jsonResponse', 'jsonError', 'jsonSuccess', 'calculateDuration'];

foreach ($functions as $func) {
    $exists = function_exists($func);
    $color = $exists ? 'green' : 'red';
    echo "<p style='color: $color;'>$func: " . ($exists ? 'EXISTE' : 'NÃO EXISTE') . "</p>";
}

echo "<h2>4. Verificando Sessão</h2>";
echo "<p>Session status: " . session_status() . "</p>";
echo "<p>User ID: " . ($_SESSION['user_id'] ?? 'NÃO DEFINIDO') . "</p>";
echo "<p>User name: " . ($_SESSION['name'] ?? 'NÃO DEFINIDO') . "</p>";
echo "<p>Is logged in: " . (function_exists('isLoggedIn') && isLoggedIn() ? 'SIM' : 'NÃO') . "</p>";

echo "<h2>5. Verificando Banco de Dados</h2>";
try {
    if (isset($pdo)) {
        echo "<p style='color: green;'>✓ Conexão PDO existe</p>";
        
        // Testar query simples
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM dash_projects");
        $result = $stmt->fetch();
        echo "<p style='color: green;'>✓ Total de projetos no banco: " . $result['count'] . "</p>";
        
        // Se usuário estiver logado, contar seus projetos
        if (isset($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM dash_projects WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $result = $stmt->fetch();
            echo "<p style='color: green;'>✓ Projetos do usuário logado: " . $result['count'] . "</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ Conexão PDO NÃO existe</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Erro ao testar banco: " . $e->getMessage() . "</p>";
}

echo "<h2>6. Testar API Diretamente</h2>";
if (function_exists('isLoggedIn') && isLoggedIn()) {
    echo "<p><a href='/dash-t101/api_time_tracker.php?action=project_list' target='_blank'>Testar: /dash-t101/api_time_tracker.php?action=project_list</a></p>";
} else {
    echo "<p style='color: red;'>Você precisa estar logado para testar a API</p>";
}

echo "<h2>7. Logs de Erro PHP</h2>";
echo "<p>Verifique o log de erros do PHP para mais detalhes</p>";
echo "<p>Geralmente em: /var/log/apache2/error.log ou similar</p>";
