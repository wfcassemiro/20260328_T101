<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
/**
 * Watchlist Cleanup Functions
 * Funções para limpeza automática da watchlist quando palestras são assistidas
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Remove uma palestra específica da watchlist de um usuário
 * 
 * @param string $user_id ID do usuário
 * @param string $lecture_id ID da palestra
 * @return array Resultado da operação
 */
function removeWatchedFromList($user_id, $lecture_id) {
    global $pdo;
    
    try {
    // Verificar se o item existe na watchlist
    $stmt = $pdo->prepare('SELECT id FROM user_watchlist WHERE user_id = ? AND lecture_id = ?');
    $stmt->execute([$user_id, $lecture_id]);
    $existing = $stmt->fetch();
    
    if (!$existing) {
    return [
    'success' => true,
    'removed' => false,
    'message' => 'Item não estava na watchlist'
    ];
    }
    
    // Remover da watchlist
    $stmt = $pdo->prepare('DELETE FROM user_watchlist WHERE user_id = ? AND lecture_id = ?');
    $stmt->execute([$user_id, $lecture_id]);
    
    $rows_affected = $stmt->rowCount();
    
    return [
    'success' => true,
    'removed' => $rows_affected > 0,
    'rows_affected' => $rows_affected,
    'message' => $rows_affected > 0 ? 'Palestra removida da watchlist' : 'Nenhuma linha afetada'
    ];
    
    } catch (Exception $e) {
    error_log('Erro em removeWatchedFromList: ' . $e->getMessage());
    return [
    'success' => false,
    'removed' => false,
    'error' => $e->getMessage()
    ];
    }
}

/**
 * Limpa todas as palestras da watchlist de um usuário que já possuem certificados
 * 
 * @param string $user_id ID do usuário (opcional, se vazio limpa para todos)
 * @return array Resultado da operação
 */
function cleanupExistingWatchlist($user_id = null) {
    global $pdo;
    
    try {
    // Query para buscar itens da watchlist que já possuem certificados
    if ($user_id) {
    $sql = "
    SELECT w.user_id, w.lecture_id, c.id as certificate_id
    FROM user_watchlist w
    INNER JOIN certificates c ON w.user_id = c.user_id AND w.lecture_id = c.lecture_id
    WHERE w.user_id = ?
    ";
    $params = [$user_id];
    } else {
    $sql = "
    SELECT w.user_id, w.lecture_id, c.id as certificate_id
    FROM user_watchlist w
    INNER JOIN certificates c ON w.user_id = c.user_id AND w.lecture_id = c.lecture_id
    ";
    $params = [];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items_to_clean = $stmt->fetchAll();
    
    $cleaned_count = 0;
    $errors = [];
    
    foreach ($items_to_clean as $item) {
    $result = removeWatchedFromList($item['user_id'], $item['lecture_id']);
    
    if ($result['success'] && $result['removed']) {
    $cleaned_count++;
    } elseif (!$result['success']) {
    $errors[] = "Erro ao remover {$item['lecture_id']} do usuário {$item['user_id']}: {$result['error']}";
    }
    }
    
    return [
    'success' => true,
    'total_found' => count($items_to_clean),
    'cleaned_count' => $cleaned_count,
    'errors' => $errors,
    'message' => "Limpeza concluída: {$cleaned_count} itens removidos de " . count($items_to_clean) . " encontrados"
    ];
    
    } catch (Exception $e) {
    error_log('Erro em cleanupExistingWatchlist: ' . $e->getMessage());
    return [
    'success' => false,
    'error' => $e->getMessage()
    ];
    }
}

/**
 * Verifica quantas palestras um usuário tem na watchlist
 * 
 * @param string $user_id ID do usuário
 * @return array Resultado com contagem
 */
function getWatchlistCount($user_id) {
    global $pdo;
    
    try {
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM user_watchlist WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    
    return [
    'success' => true,
    'count' => (int)$result['total']
    ];
    
    } catch (Exception $e) {
    return [
    'success' => false,
    'count' => 0,
    'error' => $e->getMessage()
    ];
    }
}

/**
 * Busca todas as palestras na watchlist de um usuário
 * 
 * @param string $user_id ID do usuário
 * @return array Lista de palestras
 */
function getUserWatchlist($user_id) {
    global $pdo;
    
    try {
    $stmt = $pdo->prepare("
    SELECT w.id as watchlist_id,
    w.added_at,
    l.id as lecture_id,
    l.title,
    l.speaker,
    l.category,
    l.duration_minutes
    FROM user_watchlist w
    JOIN lectures l ON w.lecture_id = l.id
    WHERE w.user_id = ?
    ORDER BY w.added_at DESC
    ");
    $stmt->execute([$user_id]);
    $watchlist = $stmt->fetchAll();
    
    return [
    'success' => true,
    'items' => $watchlist,
    'count' => count($watchlist)
    ];
    
    } catch (Exception $e) {
    return [
    'success' => false,
    'items' => [],
    'count' => 0,
    'error' => $e->getMessage()
    ];
    }
}

/**
 * Verifica se uma palestra específica está na watchlist do usuário
 * 
 * @param string $user_id ID do usuário
 * @param string $lecture_id ID da palestra
 * @return bool True se está na watchlist
 */
function isInWatchlist($user_id, $lecture_id) {
    global $pdo;
    
    try {
    $stmt = $pdo->prepare('SELECT id FROM user_watchlist WHERE user_id = ? AND lecture_id = ?');
    $stmt->execute([$user_id, $lecture_id]);
    return $stmt->fetch() ? true : false;
    
    } catch (Exception $e) {
    error_log('Erro em isInWatchlist: ' . $e->getMessage());
    return false;
    }
}

/**
 * Remove todas as palestras da watchlist de um usuário
 * Útil para reset completo
 * 
 * @param string $user_id ID do usuário
 * @return array Resultado da operação
 */
function clearUserWatchlist($user_id) {
    global $pdo;
    
    try {
    $stmt = $pdo->prepare('DELETE FROM user_watchlist WHERE user_id = ?');
    $stmt->execute([$user_id]);
    
    $rows_affected = $stmt->rowCount();
    
    return [
    'success' => true,
    'cleared_count' => $rows_affected,
    'message' => "Watchlist limpa: {$rows_affected} itens removidos"
    ];
    
    } catch (Exception $e) {
    error_log('Erro em clearUserWatchlist: ' . $e->getMessage());
    return [
    'success' => false,
    'error' => $e->getMessage()
    ];
    }
}

// API endpoints se chamado diretamente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && basename($_SERVER['SCRIPT_NAME']) === 'watchlist_cleanup.php') {
    header('Content-Type: application/json');
    
    // Verificar autenticação
    if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não logado']);
    exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $user_id = $_SESSION['user_id'];
    $lecture_id = $input['lecture_id'] ?? '';
    
    switch ($action) {
    case 'remove_watched':
    if (empty($lecture_id)) {
    echo json_encode(['success' => false, 'message' => 'ID da palestra obrigatório']);
    exit;
    }
    
    $result = removeWatchedFromList($user_id, $lecture_id);
    echo json_encode($result);
    break;
    
    case 'cleanup_existing':
    $result = cleanupExistingWatchlist($user_id);
    echo json_encode($result);
    break;
    
    case 'get_count':
    $result = getWatchlistCount($user_id);
    echo json_encode($result);
    break;
    
    case 'get_watchlist':
    $result = getUserWatchlist($user_id);
    echo json_encode($result);
    break;
    
    case 'clear_all':
    $result = clearUserWatchlist($user_id);
    echo json_encode($result);
    break;
    
    default:
    echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
    exit;
}
?>