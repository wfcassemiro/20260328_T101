<?php
/**
 * Integração entre certificados e watchlist
 * Este arquivo deve ser incluído nos pontos onde certificados são gerados
 * para automaticamente limpar a watchlist quando uma palestra é assistida
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/watchlist_cleanup_tst.php';

/**
 * Função para ser chamada ANTES da geração de um certificado
 * Remove automaticamente a palestra da watchlist quando o usuário se torna elegível
 * Esta função deve ser chamada após todas as validações de segurança passarem
 * 
 * @param string $user_id ID do usuário
 * @param string $lecture_id ID da palestra
 * @return array Resultado da operação
 */
function handleCertificateEligible($user_id, $lecture_id) {
    try {
        // Remover da watchlist usando a função do cleanup
        $result = removeWatchedFromList($user_id, $lecture_id);
        
        // Log da ação para auditoria
        if ($result['success'] && $result['removed']) {
            error_log("Watchlist cleanup: Palestra {$lecture_id} removida da lista do usuário {$user_id} - elegível para certificado");
        }
        
        return [
            'success' => true,
            'watchlist_cleaned' => $result['success'] && $result['removed'],
            'message' => 'Usuário elegível para certificado, watchlist atualizada'
        ];
        
    } catch (Exception $e) {
        error_log("Erro na integração watchlist (elegibilidade): " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Hook para ser chamado quando uma palestra é marcada como "assistida"
 * mesmo que não gere certificado imediatamente
 * 
 * @param string $user_id ID do usuário
 * @param string $lecture_id ID da palestra
 * @return array Resultado da operação
 */
function handleLectureWatched($user_id, $lecture_id) {
    global $pdo;
    
    try {
        // Verificar se já existe certificado para esta palestra
        $stmt = $pdo->prepare('SELECT id FROM certificates WHERE user_id = ? AND lecture_id = ?');
        $stmt->execute([$user_id, $lecture_id]);
        $existing_cert = $stmt->fetch();
        
        if ($existing_cert) {
            // Se já tem certificado, remove da watchlist
            $result = removeWatchedFromList($user_id, $lecture_id);
            
            return [
                'success' => true,
                'watchlist_cleaned' => $result['success'] && $result['removed'],
                'had_certificate' => true,
                'message' => 'Palestra já certificada, watchlist atualizada'
            ];
        } else {
            // Se não tem certificado ainda, apenas marca como "em progresso"
            // A watchlist só será limpa quando o certificado for gerado
            return [
                'success' => true,
                'watchlist_cleaned' => false,
                'had_certificate' => false,
                'message' => 'Palestra marcada como assistida, aguardando certificado'
            ];
        }
        
    } catch (Exception $e) {
        error_log("Erro ao processar palestra assistida: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Função para verificar e limpar watchlist baseado em certificados existentes
 * Útil para executar em batch ou correção de dados
 * 
 * @param string $user_id ID do usuário (opcional, se não fornecido processa todos)
 * @return array Resultado da operação
 */
function syncWatchlistWithCertificates($user_id = null) {
    global $pdo;
    
    try {
        $sql = "
            SELECT DISTINCT c.user_id, c.lecture_id, c.id as certificate_id
            FROM certificates c 
            WHERE EXISTS (
                SELECT 1 FROM user_watchlist w 
                WHERE w.user_id = c.user_id AND w.lecture_id = c.lecture_id
            )
        ";
        $params = [];
        
        if ($user_id) {
            $sql .= " AND c.user_id = ?";
            $params[] = $user_id;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $certificates = $stmt->fetchAll();
        
        $cleaned = 0;
        $processed = 0;
        
        foreach ($certificates as $cert) {
            $result = removeWatchedFromList($cert['user_id'], $cert['lecture_id']);
            $processed++;
            
            if ($result['success'] && $result['removed']) {
                $cleaned++;
                error_log("Sync: Removida palestra {$cert['lecture_id']} da watchlist do usuário {$cert['user_id']}");
            }
        }
        
        return [
            'success' => true,
            'certificates_processed' => $processed,
            'watchlist_items_cleaned' => $cleaned,
            'message' => "Sincronização concluída: {$cleaned} itens removidos de {$processed} processados"
        ];
        
    } catch (Exception $e) {
        error_log("Erro na sincronização watchlist/certificados: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// API endpoints para integração
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Verificar autenticação
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    $user_id = $_SESSION['user_id'];
    $lecture_id = $_POST['lecture_id'] ?? '';
    
    switch ($action) {
        case 'certificate_eligible':
            if (empty($lecture_id)) {
                echo json_encode(['success' => false, 'message' => 'ID da palestra obrigatório']);
                exit;
            }
            
            $result = handleCertificateEligible($user_id, $lecture_id);
            echo json_encode($result);
            break;
            
        case 'lecture_watched':
            if (empty($lecture_id)) {
                echo json_encode(['success' => false, 'message' => 'ID da palestra obrigatório']);
                exit;
            }
            
            $result = handleLectureWatched($user_id, $lecture_id);
            echo json_encode($result);
            break;
            
        case 'sync_watchlist':
            $result = syncWatchlistWithCertificates($user_id);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
    exit;
}

// Interface web para testes de integração
if (!isset($_POST['action'])):
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integração Certificados & Watchlist</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #212529; }
        .result { margin-top: 20px; padding: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .code-example { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 4px; padding: 15px; margin: 10px 0; font-family: monospace; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔗 Integração Certificados & Watchlist</h1>
        <p>Interface para testar a integração entre geração de certificados e limpeza automática da watchlist.</p>
        
        <div class="info">
            <strong>💡 Como usar:</strong><br>
            1. Quando um certificado for gerado, chame <code>handleCertificateGenerated()</code><br>
            2. Para marcar palestra como assistida, use <code>handleLectureWatched()</code><br>
            3. Para sincronizar dados existentes, execute <code>syncWatchlistWithCertificates()</code>
        </div>
        
        <h3>🎯 Simular Elegibilidade para Certificado</h3>
        <form onsubmit="simulateEligible(event)">
            <div class="form-group">
                <label for="cert_lecture_id">ID da Palestra:</label>
                <input type="text" id="cert_lecture_id" name="lecture_id" required 
                       placeholder="Ex: 123e4567-e89b-12d3-a456-426614174000">
            </div>
            <button type="submit" class="btn btn-success">✅ Simular Elegibilidade</button>
        </form>
        
        <hr>
        
        <h3>👁️ Marcar Palestra como Assistida</h3>
        <form onsubmit="markWatched(event)">
            <div class="form-group">
                <label for="watched_lecture_id">ID da Palestra:</label>
                <input type="text" id="watched_lecture_id" name="lecture_id" required 
                       placeholder="Ex: 123e4567-e89b-12d3-a456-426614174000">
            </div>
            <button type="submit" class="btn btn-warning">👀 Marcar como Assistida</button>
        </form>
        
        <hr>
        
        <h3>🔄 Sincronização Geral</h3>
        <p>Remove da watchlist todas as palestras que já possuem certificados:</p>
        <button onclick="syncWatchlist()" class="btn">🔄 Sincronizar Watchlist</button>
        
        <hr>
        
        <h3>📋 Exemplo de Integração</h3>
        <p>Para integrar no seu código de geração de certificados, adicione:</p>
        <div class="code-example">
// No arquivo generate_certificate.php:<br>
require_once 'certificate_integration_tst.php';<br><br>

// ANTES de inserir o certificado no banco (após validações passarem):<br>
$result = handleCertificateEligible($user_id, $lecture_id);<br><br>

// A palestra é automaticamente marcada como assistida<br>
// quando o usuário se torna elegível para o certificado
        </div>
        
        <div id="result"></div>
    </div>

    <script>
        function showResult(message, isSuccess = true) {
            const result = document.getElementById('result');
            result.innerHTML = message;
            result.className = `result ${isSuccess ? 'success' : 'error'}`;
        }
        
        function simulateEligible(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            formData.append('action', 'certificate_eligible');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showResult(`✅ Elegibilidade processada!<br>
                        🗑️ Watchlist limpa: ${data.watchlist_cleaned ? 'Sim' : 'Não'}<br>
                        📝 ${data.message}`);
                } else {
                    showResult(`❌ Erro: ${data.error || data.message}`, false);
                }
            })
            .catch(error => {
                showResult(`❌ Erro de rede: ${error.message}`, false);
            });
        }
        
        function markWatched(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            formData.append('action', 'lecture_watched');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showResult(`✅ Palestra processada!<br>
                        📜 Já tinha certificado: ${data.had_certificate ? 'Sim' : 'Não'}<br>
                        🗑️ Watchlist limpa: ${data.watchlist_cleaned ? 'Sim' : 'Não'}<br>
                        📝 ${data.message}`);
                } else {
                    showResult(`❌ Erro: ${data.error || data.message}`, false);
                }
            })
            .catch(error => {
                showResult(`❌ Erro de rede: ${error.message}`, false);
            });
        }
        
        function syncWatchlist() {
            showResult('🔄 Sincronizando...', true);
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=sync_watchlist'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showResult(`✅ Sincronização concluída!<br>
                        📋 Certificados processados: ${data.certificates_processed}<br>
                        🗑️ Itens removidos: ${data.watchlist_items_cleaned}<br>
                        📝 ${data.message}`);
                } else {
                    showResult(`❌ Erro: ${data.error || data.message}`, false);
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