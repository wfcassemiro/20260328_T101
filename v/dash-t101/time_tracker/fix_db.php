<?php
/**
 * Script de Correção DEFINITIVA de Banco de Dados
 * Resolve erro 150 (Foreign Key Incorrectly Formed)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Carregar configurações
$config_paths = [
    __DIR__ . '/config/database.php',
    $_SERVER['DOCUMENT_ROOT'] . '/dash-t101/config/database.php'
];

foreach ($config_paths as $path) {
    if (file_exists($path)) { require_once $path; break; }
}

echo "<style>body{font-family:sans-serif; padding:20px; line-height:1.6; background:#f4f4f4;} .box{background:#fff; padding:15px; margin-bottom:10px; border-radius:5px; border-left:5px solid #ccc;} .success{border-color:green;} .error{border-color:red;} .info{border-color:blue;}</style>";

echo "<h1>🛠️ Correção Avançada de Banco de Dados</h1>";

try {
    // 1. Descobrir o tipo exato do ID em dash_projects
    echo "<div class='box info'><h3>1. Analisando dash_projects...</h3>";
    $stmt = $pdo->query("DESCRIBE dash_projects id");
    $colData = $stmt->fetch(PDO::FETCH_ASSOC);
    $type = $colData['Type']; // Ex: int(11) ou bigint(20)
    echo "O tipo de ID da tabela dash_projects é: <b>$type</b></div>";

    // 2. Ajustar dash_projects (Client ID Nullable) - Passo anterior
    echo "<div class='box info'><h3>2. Ajustando dash_projects (Client ID)...</h3>";
    try {
        $pdo->exec("ALTER TABLE dash_projects MODIFY client_id INT(11) NULL");
        echo "✅ Coluna client_id ajustada para aceitar NULL.";
    } catch (Exception $e) {
        echo "ℹ️ " . $e->getMessage();
    }
    echo "</div>";

    // 3. Limpar Dados Órfãos (CRÍTICO PARA EVITAR ERRO 150)
    echo "<div class='box info'><h3>3. Limpando dados órfãos...</h3>";
    try {
        // Define como NULL qualquer project_id em time_entries que não exista em dash_projects
        $sql = "UPDATE time_entries SET project_id = NULL WHERE project_id IS NOT NULL AND project_id NOT IN (SELECT id FROM dash_projects)";
        $count = $pdo->exec($sql);
        echo "✅ <b>$count</b> registros órfãos corrigidos (definidos como NULL).";
    } catch (Exception $e) {
        echo "❌ Erro ao limpar dados: " . $e->getMessage();
    }
    echo "</div>";

    // 4. Alinhar tipos das colunas (CRÍTICO PARA EVITAR ERRO 150)
    echo "<div class='box info'><h3>4. Alinhando tipos de coluna...</h3>";
    try {
        // Modifica time_entries.project_id para ser EXATAMENTE igual ao tipo de dash_projects.id
        $sql = "ALTER TABLE time_entries MODIFY COLUMN project_id $type NULL";
        $pdo->exec($sql);
        echo "✅ Coluna time_entries.project_id modificada para <b>$type NULL</b> (Igual à tabela pai).";
    } catch (Exception $e) {
        echo "❌ Erro ao modificar coluna: " . $e->getMessage();
    }
    echo "</div>";

    // 5. Remover chaves antigas (Limpeza)
    echo "<div class='box info'><h3>5. Removendo constraints antigas...</h3>";
    $constraints = ['fk_time_entries_project', 'time_entries_ibfk_1'];
    foreach ($constraints as $fk) {
        try {
            $pdo->exec("ALTER TABLE time_entries DROP FOREIGN KEY $fk");
            echo "🗑️ Chave antiga $fk removida.<br>";
        } catch (Exception $e) {
            // Ignora se não existir
        }
    }
    echo "</div>";

    // 6. Criar a Foreign Key Correta
    echo "<div class='box info'><h3>6. Criando a Foreign Key...</h3>";
    try {
        $sql = "ALTER TABLE time_entries 
                ADD CONSTRAINT fk_time_entries_dash_projects 
                FOREIGN KEY (project_id) REFERENCES dash_projects(id) 
                ON DELETE SET NULL ON UPDATE CASCADE";
        $pdo->exec($sql);
        echo "<b style='color:green'>✅ SUCESSO! Foreign Key criada corretamente.</b>";
    } catch (Exception $e) {
        echo "<div class='error'>❌ FALHA FINAL: " . $e->getMessage() . "<br>";
        echo "<small>Dica: Se ainda der erro, verifique se dash_projects usa 'UNSIGNED' e time_entries não.</small></div>";
        
        // Tentativa de fallback (Forçar INT padrão nos dois)
        echo "<br><b>Tentando método de força bruta (Fallback)...</b><br>";
        try {
            // Força ambos para INT(11) padrão
            $pdo->exec("ALTER TABLE dash_projects MODIFY id INT(11) NOT NULL AUTO_INCREMENT");
            $pdo->exec("ALTER TABLE time_entries MODIFY project_id INT(11) NULL");
            $pdo->exec("ALTER TABLE time_entries ADD CONSTRAINT fk_time_entries_dash_projects_fb FOREIGN KEY (project_id) REFERENCES dash_projects(id) ON DELETE SET NULL");
            echo "<b style='color:green'>✅ SUCESSO NO FALLBACK! Tabelas padronizadas para INT(11).</b>";
        } catch (Exception $ex) {
            echo "❌ Fallback falhou: " . $ex->getMessage();
        }
    }
    echo "</div>";

    echo "<hr><a href='time-tracker.php' style='background:#007bff; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Voltar para o Time Tracker</a>";

} catch (PDOException $e) {
    die("<div class='box error'>Erro Fatal de Conexão: " . $e->getMessage() . "</div>");
}
?>