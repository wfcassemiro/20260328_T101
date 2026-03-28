<?php
/**
 * Verificação de Autenticação
 */

// Usar o database.php principal do site se existir
if (file_exists(__DIR__ . '/../../dash-t101/config/database.php')) {
    require_once __DIR__ . '/../../dash-t101/config/database.php';
} else {
    require_once __DIR__ . '/../config/database.php';
}

// Verificar se usuário está logado
if (!isLoggedIn()) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        // Requisição AJAX
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Não autenticado', 'redirect' => '/login.php']);
        exit;
    } else {
        // Requisição normal
        header('Location: /login.php');
        exit;
    }
}

// Verificar se é subscriber
if (!isSubscriber()) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Acesso negado. Assinatura requerida.']);
        exit;
    } else {
        header('Location: /dash-t101/index.php?error=subscription_required');
        exit;
    }
}

// Obter dados do usuário
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Usuário';
$user_email = $_SESSION['email'] ?? '';
