<?php
require_once __DIR__ . '/../config/database.php';

echo "<h2>Verificação e Setup do Banco de Dados</h2>";

try {
    // Verificar se a tabela user_watchlist existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_watchlist'");
    $table_exists = $stmt->fetch();
    
    if ($table_exists) {
        echo "<p>✅ Tabela 'user_watchlist' já existe.</p>";
        
        // Verificar estrutura da tabela
        $stmt = $pdo->query("DESCRIBE user_watchlist");
        $columns = $stmt->fetchAll();
        
        echo "<h3>Estrutura da tabela:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Verificar dados existentes
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM user_watchlist");
        $count = $stmt->fetch();
        echo "<p>📊 Total de itens na watchlist: " . $count['total'] . "</p>";
        
    } else {
        echo "<p>❌ Tabela 'user_watchlist' não existe. Criando...</p>";
        
        // Criar a tabela
        $sql = "CREATE TABLE `user_watchlist` (
            `id` varchar(36) NOT NULL,
            `user_id` varchar(36) NOT NULL,
            `lecture_id` varchar(36) NOT NULL,
            `added_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_user_lecture` (`user_id`, `lecture_id`),
            KEY `idx_user_id` (`user_id`),
            KEY `idx_lecture_id` (`lecture_id`),
            KEY `idx_added_at` (`added_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        $pdo->exec($sql);
        echo "<p>✅ Tabela 'user_watchlist' criada com sucesso!</p>";
    }
    
    // Verificar se a tabela lectures existe (para testes)
    $stmt = $pdo->query("SHOW TABLES LIKE 'lectures'");
    $lectures_exists = $stmt->fetch();
    
    if ($lectures_exists) {
        echo "<p>✅ Tabela 'lectures' existe.</p>";
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM lectures");
        $count = $stmt->fetch();
        echo "<p>📚 Total de palestras: " . $count['total'] . "</p>";
        
        // Mostrar algumas palestras para teste
        $stmt = $pdo->query("SELECT id, title FROM lectures LIMIT 3");
        $sample_lectures = $stmt->fetchAll();
        
        if ($sample_lectures) {
            echo "<h3>Palestras disponíveis (amostra):</h3>";
            echo "<ul>";
            foreach ($sample_lectures as $lecture) {
                echo "<li><strong>" . htmlspecialchars($lecture['id']) . "</strong>: " . htmlspecialchars($lecture['title']) . "</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p>❌ Tabela 'lectures' não existe. Isso pode causar problemas!</p>";
        
        // Criar uma palestra de teste se não existir
        echo "<p>Criando palestra de teste...</p>";
        
        $test_sql = "CREATE TABLE IF NOT EXISTS `lectures` (
            `id` varchar(36) NOT NULL,
            `title` varchar(255) NOT NULL,
            `speaker` varchar(255) DEFAULT NULL,
            `description` text,
            `category` varchar(100) DEFAULT NULL,
            `duration_minutes` int DEFAULT NULL,
            `thumbnail_url` varchar(500) DEFAULT NULL,
            `is_featured` tinyint(1) DEFAULT '0',
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        $pdo->exec($test_sql);
        
        // Inserir palestra de teste
        $stmt = $pdo->prepare("INSERT INTO lectures (id, title, speaker, description, category, duration_minutes) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'test-lecture-123',
            'Palestra de Teste - Tradução Jurídica',
            'Dr. João Silva',
            'Esta é uma palestra de teste para verificar o funcionamento da watchlist.',
            'Traducao',
            60
        ]);
        
        echo "<p>✅ Palestra de teste criada!</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<br><a href='test_watchlist.php'>Testar API Watchlist</a>";
echo "<br><a href='test_session.php'>Configurar Sessão de Teste</a>";
echo "<br><a href='videoteca.php'>Ir para Videoteca</a>";
?>