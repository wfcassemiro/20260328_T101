<?php
/**
 * Integração entre certificados e watchlist
 * Este arquivo deve ser incluído nos pontos onde certificados são gerados
 * para automaticamente limpar a watchlist quando uma palestra é assistida
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/watchlist_cleanup.php';

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
?>