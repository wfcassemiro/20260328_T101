<?php
require_once __DIR__ . '/../config/database.php';

echo "<h1>🔧 Teste de Setup da Watchlist</h1>";

try {
    // 1. Verificar se a tabela user_watchlist existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_watchlist'");
    $table_exists = $stmt->fetch();
    
    if ($table_exists) {
        echo "<p>✅ Tabela 'user_watchlist' existe</p>";
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
    
    // 2. Verificar se há palestras na tabela lectures
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM lectures");
    $lectures_count = $stmt->fetch();
    
    echo "<p>📚 Total de palestras disponíveis: {$lectures_count['total']}</p>";
    
    if ($lectures_count['total'] == 0) {
        echo "<p>⚠️ Nenhuma palestra encontrada. Isso pode causar problemas nos testes.</p>";
    }
    
    // 3. Mostrar algumas palestras de exemplo
    $stmt = $pdo->query("SELECT id, title, speaker FROM lectures LIMIT 5");
    $sample_lectures = $stmt->fetchAll();
    
    if ($sample_lectures) {
        echo "<h3>📋 Palestras disponíveis (amostra):</h3>";
        echo "<ul>";
        foreach ($sample_lectures as $lecture) {
            echo "<li><strong>{$lecture['id']}</strong>: {$lecture['title']}";
            if ($lecture['speaker']) {
                echo " - {$lecture['speaker']}";
            }
            echo "</li>";
        }
        echo "</ul>";
    }
    
    // 4. Testar função de autenticação
    echo "<h3>🔐 Teste de Autenticação:</h3>";
    if (isLoggedIn()) {
        echo "<p>✅ Usuário está logado: " . ($_SESSION['user_id'] ?? 'ID não disponível') . "</p>";
        echo "<p>Nome: " . ($_SESSION['user_name'] ?? 'Nome não disponível') . "</p>";
        echo "<p>Email: " . ($_SESSION['user_email'] ?? 'Email não disponível') . "</p>";
        
        if (hasVideotecaAccess()) {
            echo "<p>✅ Usuário tem acesso à videoteca</p>";
        } else {
            echo "<p>❌ Usuário NÃO tem acesso à videoteca</p>";
        }
    } else {
        echo "<p>❌ Usuário não está logado</p>";
    }
    
    // 5. Verificar registros existentes na watchlist
    if (isLoggedIn()) {
        $user_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM user_watchlist WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $watchlist_count = $stmt->fetch();
        
        echo "<p>📝 Itens na sua watchlist: {$watchlist_count['total']}</p>";
        
        if ($watchlist_count['total'] > 0) {
            $stmt = $pdo->prepare("
                SELECT w.added_at, l.title 
                FROM user_watchlist w
                JOIN lectures l ON w.lecture_id = l.id 
                WHERE w.user_id = ? 
                ORDER BY w.added_at DESC 
                LIMIT 3
            ");
            $stmt->execute([$user_id]);
            $watchlist_items = $stmt->fetchAll();
            
            echo "<h4>🔖 Últimos itens na sua lista:</h4>";
            echo "<ul>";
            foreach ($watchlist_items as $item) {
                echo "<li>{$item['title']} - Adicionado em " . date('d/m/Y H:i', strtotime($item['added_at'])) . "</li>";
            }
            echo "</ul>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<hr>

<h3>🧪 Teste Rápido da API</h3>

<?php if (isLoggedIn() && !empty($sample_lectures)): ?>
    <div id="api-test-section">
        <p>Testando com a primeira palestra: <strong><?php echo htmlspecialchars($sample_lectures[0]['title']); ?></strong></p>
        <p>ID: <code><?php echo htmlspecialchars($sample_lectures[0]['id']); ?></code></p>
        
        <button onclick="testAddToWatchlist()" id="addBtn">➕ Adicionar à Lista</button>
        <button onclick="testRemoveFromWatchlist()" id="removeBtn">➖ Remover da Lista</button>
        <button onclick="checkStatus()" id="statusBtn">📋 Verificar Status</button>
        
        <div id="result" style="margin-top: 15px; padding: 10px; background: #f0f0f0; border-radius: 5px; display: none;"></div>
    </div>

    <script>
        const TEST_LECTURE_ID = '<?php echo htmlspecialchars($sample_lectures[0]['id']); ?>';
        
        function updateResult(message, isError = false) {
            const result = document.getElementById('result');
            result.innerHTML = '<strong>' + (isError ? '❌ ' : '✅ ') + '</strong>' + message;
            result.style.backgroundColor = isError ? '#ffe6e6' : '#e6ffe6';
            result.style.color = isError ? '#cc0000' : '#006600';
            result.style.display = 'block';
        }
        
        function testAddToWatchlist() {
            console.log('Testando adição à watchlist...');
            
            fetch('api_watchlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    lecture_id: TEST_LECTURE_ID,
                    action: 'add'
                })
            })
            .then(response => {
                console.log('Status da resposta:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Dados retornados:', data);
                if (data.success) {
                    updateResult('Palestra adicionada à watchlist com sucesso!');
                } else {
                    updateResult('Erro ao adicionar: ' + (data.message || 'Erro desconhecido'), true);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                updateResult('Erro de rede: ' + error.message, true);
            });
        }
        
        function testRemoveFromWatchlist() {
            console.log('Testando remoção da watchlist...');
            
            fetch('api_watchlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    lecture_id: TEST_LECTURE_ID,
                    action: 'remove'
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Dados retornados:', data);
                if (data.success) {
                    updateResult('Palestra removida da watchlist com sucesso!');
                } else {
                    updateResult('Erro ao remover: ' + (data.message || 'Erro desconhecido'), true);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                updateResult('Erro de rede: ' + error.message, true);
            });
        }
        
        function checkStatus() {
            location.reload();
        }
    </script>
<?php else: ?>
    <p>❌ Usuário não logado ou nenhuma palestra disponível para teste</p>
<?php endif; ?>

<hr>

<div style="margin-top: 20px;">
    <h3>🔗 Links Úteis:</h3>
    <ul>
        <li><a href="videoteca.php">📚 Ir para Videoteca</a></li>
        <li><a href="perfil_updated.php">👤 Ir para Perfil Atualizado</a></li>
        <li><a href="api_watchlist.php" target="_blank">🔧 Testar API diretamente</a> (deve dar erro 405 - normal)</li>
    </ul>
</div>