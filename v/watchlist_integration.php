<?php
session_start();
require_once __DIR__ . '/../config/database.php';

/**
 * Funcao para ser chamada quando uma palestra for marcada como assistida
 * Remove automaticamente da watchlist do usuario
 * 
 * @param string $user_id ID do usuario
 * @param string $lecture_id ID da palestra
 * @return bool True se removida com sucesso ou se nao estava na lista
 */
function removeFromWatchlistOnView($user_id, $lecture_id) {
    global $pdo;
    
    try {
        // Verificar se a palestra esta na watchlist do usuario
        $stmt = $pdo->prepare('SELECT id FROM user_watchlist WHERE user_id = ? AND lecture_id = ?');
        $stmt->execute([$user_id, $lecture_id]);
        $watchlist_item = $stmt->fetch();
        
        if ($watchlist_item) {
            // Remover da watchlist
            $stmt = $pdo->prepare('DELETE FROM user_watchlist WHERE user_id = ? AND lecture_id = ?');
            $stmt->execute([$user_id, $lecture_id]);
            
            return true;
        }
        
        return true; // Nao estava na lista, entao e considerado sucesso
        
    } catch (Exception $e) {
        // Log do erro para debugging
        error_log('Erro ao remover da watchlist apos visualizacao: ' . $e->getMessage());
        return false;
    }
}

/**
 * Funcao para verificar se uma palestra esta na watchlist do usuario
 * 
 * @param string $user_id ID do usuario
 * @param string $lecture_id ID da palestra
 * @return bool True se estiver na watchlist
 */
function isInUserWatchlist($user_id, $lecture_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare('SELECT id FROM user_watchlist WHERE user_id = ? AND lecture_id = ?');
        $stmt->execute([$user_id, $lecture_id]);
        return $stmt->fetch() !== false;
        
    } catch (Exception $e) {
        error_log('Erro ao verificar watchlist: ' . $e->getMessage());
        return false;
    }
}

/**
 * Funcao para obter estatisticas da watchlist do usuario
 * 
 * @param string $user_id ID do usuario
 * @return array Array com estatisticas
 */
function getUserWatchlistStats($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as total_items,
                   AVG(l.duration_minutes) as avg_duration,
                   SUM(l.duration_minutes) as total_duration
            FROM user_watchlist w
            JOIN lectures l ON w.lecture_id = l.id
            WHERE w.user_id = ?
        ');
        $stmt->execute([$user_id]);
        $stats = $stmt->fetch();
        
        return [
            'total_items' => (int)($stats['total_items'] ?? 0),
            'avg_duration' => round($stats['avg_duration'] ?? 0, 1),
            'total_duration' => (int)($stats['total_duration'] ?? 0)
        ];
        
    } catch (Exception $e) {
        error_log('Erro ao obter estatisticas da watchlist: ' . $e->getMessage());
        return ['total_items' => 0, 'avg_duration' => 0, 'total_duration' => 0];
    }
}

// Se este arquivo for chamado diretamente via AJAX para remocao apos visualizacao
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_watched'])) {
    header('Content-Type: application/json');
    
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Usuario nao logado']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $lecture_id = $_POST['lecture_id'] ?? '';
    
    if (empty($lecture_id)) {
        echo json_encode(['success' => false, 'message' => 'ID da palestra nao fornecido']);
        exit;
    }
    
    $success = removeFromWatchlistOnView($user_id, $lecture_id);
    
    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Palestra removida da lista apos visualizacao' : 'Erro ao remover da lista'
    ]);
    exit;
}
?>