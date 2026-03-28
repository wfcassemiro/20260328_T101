<?php
/**
 * Verificação de Autenticação - Time Tracker
 * Inclui database.php e verifica se o usuário está autenticado
 */

// Iniciar sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Carregar database.php se ainda não foi carregado
if (!isset($pdo) || !function_exists('isLoggedIn')) {
    require_once __DIR__ . '/../config/database.php';
}

// Carregar config.php se existir (arquivo opcional de configurações adicionais)
$config_path = __DIR__ . '/../config/config.php';
if (file_exists($config_path)) {
    require_once $config_path;
}

// Verificar se usuário está autenticado
// Nota: Esta verificação é OPCIONAL para páginas que não requerem login obrigatório
// Para páginas que requerem login, chame requireAuth() ou requireSubscriber()