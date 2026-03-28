<?php
session_start();

// Simular usuario logado para teste
$_SESSION['user_id'] = 'test-user-123';
$_SESSION['user_role'] = 'subscriber';

require_once __DIR__ . '/../config/database.php';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Teste Watchlist API</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; }
        button { padding: 10px; margin: 5px; cursor: pointer; }
        #result { background: #f5f5f5; padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Teste da API Watchlist</h1>
    
    <div class="test-section">
        <h3>Informações da Sessão:</h3>
        <p><strong>User ID:</strong> <?php echo $_SESSION['user_id'] ?? 'Não definido'; ?></p>
        <p><strong>Logado:</strong> <?php echo (function_exists('isLoggedIn') && isLoggedIn()) ? 'Sim' : 'Não'; ?></p>
    </div>
    
    <div class="test-section">
        <h3>Testes da API:</h3>
        <button onclick="testAddToWatchlist()">Adicionar à Lista (Teste)</button>
        <button onclick="testRemoveFromWatchlist()">Remover da Lista (Teste)</button>
        <button onclick="checkWatchlistStatus()">Verificar Status</button>
        
        <div id="result"></div>
    </div>

    <script>
        const TEST_LECTURE_ID = 'test-lecture-123';
        
        function updateResult(message, isError = false) {
            const result = document.getElementById('result');
            result.innerHTML = '<strong>' + (isError ? 'ERRO: ' : 'SUCESSO: ') + '</strong>' + message;
            result.style.backgroundColor = isError ? '#ffe6e6' : '#e6ffe6';
            result.style.color = isError ? '#cc0000' : '#006600';
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
                console.log('Status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Resposta:', data);
                if (data.success) {
                    updateResult('Palestra adicionada à lista com sucesso!');
                } else {
                    updateResult('Falha ao adicionar: ' + (data.message || 'Erro desconhecido'), true);
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
                console.log('Resposta:', data);
                if (data.success) {
                    updateResult('Palestra removida da lista com sucesso!');
                } else {
                    updateResult('Falha ao remover: ' + (data.message || 'Erro desconhecido'), true);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                updateResult('Erro de rede: ' + error.message, true);
            });
        }
        
        function checkWatchlistStatus() {
            // Teste simples para verificar se a conexão funciona
            fetch('api_watchlist.php', {
                method: 'GET'
            })
            .then(response => {
                console.log('Status da requisição GET:', response.status);
                updateResult('API respondeu com status: ' + response.status + ' (Esperado: 405 para GET)');
            })
            .catch(error => {
                updateResult('Erro ao conectar com a API: ' + error.message, true);
            });
        }
    </script>
</body>
</html>