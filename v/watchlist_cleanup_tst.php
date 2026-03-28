<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Remove palestra da watchlist quando certificado é gerado
 * Deve ser chamado após a geração do certificado
 */
function removeWatchedFromList($user_id, $lecture_id) {
    global $pdo;
    
    try {
        // Remover da watchlist quando assistida
        $stmt = $pdo->prepare('DELETE FROM user_watchlist WHERE user_id = ? AND lecture_id = ?');
        $stmt->execute([$user_id, $lecture_id]);
        
        return ['success' => true, 'removed' => $stmt->rowCount() > 0];
        
    } catch (Exception $e) {
        error_log('Erro ao limpar watchlist: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Limpar watchlist para todas as palestras assistidas (certificados existentes)
 * Script de manutenção para limpar registros antigos
 */
function cleanupExistingWatchlist() {
    global $pdo;
    
    try {
        // Buscar todos os certificados existentes
        $stmt = $pdo->prepare("
            SELECT DISTINCT c.user_id, c.lecture_id 
            FROM certificates c 
            WHERE EXISTS (
                SELECT 1 FROM user_watchlist w 
                WHERE w.user_id = c.user_id AND w.lecture_id = c.lecture_id
            )
        ");
        $stmt->execute();
        $certificates = $stmt->fetchAll();
        
        $cleaned = 0;
        
        // Remover cada um da watchlist
        foreach ($certificates as $cert) {
            $stmt = $pdo->prepare('DELETE FROM user_watchlist WHERE user_id = ? AND lecture_id = ?');
            $stmt->execute([$cert['user_id'], $cert['lecture_id']]);
            $cleaned += $stmt->rowCount();
        }
        
        return [
            'success' => true, 
            'certificates_checked' => count($certificates),
            'items_cleaned' => $cleaned
        ];
        
    } catch (Exception $e) {
        error_log('Erro na limpeza da watchlist: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// API endpoint para limpeza manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'remove_watched':
            $user_id = $_POST['user_id'] ?? '';
            $lecture_id = $_POST['lecture_id'] ?? '';
            
            if (empty($user_id) || empty($lecture_id)) {
                echo json_encode(['success' => false, 'message' => 'Parametros obrigatorios']);
                exit;
            }
            
            $result = removeWatchedFromList($user_id, $lecture_id);
            echo json_encode($result);
            break;
            
        case 'cleanup_all':
            $result = cleanupExistingWatchlist();
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acao invalida']);
    }
    exit;
}

// Interface web simples para testes
if (!isset($_POST['action'])):
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpeza de Watchlist</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .result { margin-top: 20px; padding: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧹 Limpeza de Watchlist</h1>
        <p>Ferramentas para gerenciar a watchlist e remover palestras assistidas.</p>
        
        <div class="info">
            <strong>Nota:</strong> Quando um usuário assiste uma palestra e gera o certificado, 
            a palestra deve ser automaticamente removida da watchlist dele.
        </div>
        
        <h3>Remover Palestra Específica</h3>
        <form onsubmit="removeSpecific(event)">
            <div class="form-group">
                <label for="user_id">ID do Usuário:</label>
                <input type="text" id="user_id" name="user_id" required>
            </div>
            <div class="form-group">
                <label for="lecture_id">ID da Palestra:</label>
                <input type="text" id="lecture_id" name="lecture_id" required>
            </div>
            <button type="submit" class="btn">Remover da Watchlist</button>
        </form>
        
        <hr>
        
        <h3>Limpeza Geral</h3>
        <p>Remove da watchlist todas as palestras que já possuem certificados gerados:</p>
        <button onclick="cleanupAll()" class="btn">Executar Limpeza Geral</button>
        
        <div id="result"></div>
    </div>

    <script>
        function showResult(message, isSuccess = true) {
            const result = document.getElementById('result');
            result.innerHTML = message;
            result.className = `result ${isSuccess ? 'success' : 'error'}`;
        }
        
        function removeSpecific(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            formData.append('action', 'remove_watched');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showResult(`✅ Sucesso! ${data.removed ? 'Palestra removida da watchlist.' : 'Palestra não estava na watchlist.'}`);
                } else {
                    showResult(`❌ Erro: ${data.error || 'Erro desconhecido'}`, false);
                }
            })
            .catch(error => {
                showResult(`❌ Erro de rede: ${error.message}`, false);
            });
        }
        
        function cleanupAll() {
            showResult('🔄 Executando limpeza...', true);
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=cleanup_all'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showResult(`✅ Limpeza concluída!<br>
                        📋 Certificados verificados: ${data.certificates_checked}<br>
                        🗑️ Itens removidos da watchlist: ${data.items_cleaned}`);
                } else {
                    showResult(`❌ Erro na limpeza: ${data.error || 'Erro desconhecido'}`, false);
                }
            })
            .catch(error => {
                showResult(`❌ Erro de rede: ${error.message}`, false);
            });
        }
    </script>
</body>
</html>
<?php endif; ?>