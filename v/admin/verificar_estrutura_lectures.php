<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

try {
    // Verificar estrutura da tabela lectures
    $stmt = $pdo->query("DESCRIBE lectures");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Estrutura da tabela 'lectures':</h2>";
    echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th style='padding: 8px;'>Campo</th>";
    echo "<th style='padding: 8px;'>Tipo</th>";
    echo "<th style='padding: 8px;'>Null</th>";
    echo "<th style='padding: 8px;'>Chave</th>";
    echo "<th style='padding: 8px;'>Padrão</th>";
    echo "<th style='padding: 8px;'>Extra</th>";
    echo "</tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td style='padding: 8px; font-weight: bold;'>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($column['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Listar todos os campos encontrados
    $fields = array_column($columns, 'Field');
    echo "<h3>Campos disponíveis:</h3>";
    echo "<pre>" . implode("\
", $fields) . "</pre>";
    
    // Verificar se há dados na tabela
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM lectures");
    $count = $stmt->fetch();
    echo "<h3>Total de registros: " . $count['total'] . "</h3>";
    
    // Mostrar uma amostra dos dados se existir
    if ($count['total'] > 0) {
        $stmt = $pdo->query("SELECT * FROM lectures LIMIT 1");
        $sample = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<h3>Amostra de dados:</h3>";
        echo "<pre>";
        foreach ($sample as $key => $value) {
            echo "$key: " . ($value !== null ? htmlspecialchars($value) : 'NULL') . "\
";
        }
        echo "</pre>";
    }
    
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>Erro ao acessar a tabela lectures:</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    
    // Tentar listar todas as tabelas para ver o que existe
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<h3>Tabelas disponíveis no banco:</h3>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>" . htmlspecialchars($table) . "</li>";
        }
        echo "</ul>";
    } catch (Exception $e2) {
        echo "<p>Erro ao listar tabelas: " . htmlspecialchars($e2->getMessage()) . "</p>";
    }
}
?>