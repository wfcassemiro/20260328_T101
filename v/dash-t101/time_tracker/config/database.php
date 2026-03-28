<?php
/**
 * Configuração de Banco de Dados - Time Tracker
 * Integrado com o sistema principal do site
 */

// Configurações de sessão (apenas se não tiver começado ainda)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_domain', '.translators101.com');
    ini_set('session.cookie_secure', 1);   // garante que só trafegue em HTTPS
    ini_set('session.cookie_httponly', 1); // protege contra JavaScript
    ini_set('session.cookie_samesite', 'Lax'); // proteção CSRF
    session_start();
}

// Conexão com o banco de dados
$host = 'localhost';
$db   = 'u335416710_t101_db';
$user = 'u335416710_t101';
$pass = 'Pa392ap!';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Mensagem de erro aprimorada para depuração
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        die("Erro de Conexão com o Banco de Dados: Acesso negado. Verifique o usuário e senha no config/database.php. Detalhes: " . $e->getMessage());
    } else {
        die("Erro de Conexão com o Banco de Dados: " . $e->getMessage());
    }
}

// Funções auxiliares de autenticação (garantir que estão definidas globalmente)
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
}

if (!function_exists('isSubscriber')) {
    function isSubscriber() {
        // Admin tem acesso completo, incluindo videoteca
        if (isAdmin()) {
            return true;
        }
        return isLoggedIn() && isset($_SESSION['is_subscriber']) && $_SESSION['is_subscriber'] == 1;
    }
}

// Função adicional para verificar acesso à videoteca especificamente
if (!function_exists('hasVideotecaAccess')) {
    function hasVideotecaAccess() {
        // Admin ou assinante têm acesso à videoteca
        return isAdmin() || isSubscriber();
    }
}

// Função para verificar se usuário é administrador principal
if (!function_exists('isSuperAdmin')) {
    function isSuperAdmin() {
        return isLoggedIn() && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    }
}

// ===== FUNÇÕES ESPECÍFICAS DO TIME TRACKER =====

/**
 * Verificar autenticação para páginas protegidas
 */
if (!function_exists('requireAuth')) {
    function requireAuth() {
        if (!isLoggedIn()) {
            // Verificar se é requisição AJAX
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Não autenticado', 'redirect' => '/login.php']);
                exit;
            } else {
                header('Location: /login.php');
                exit;
            }
        }
    }
}

/**
 * Verificar se é subscriber (para Time Tracker)
 */
if (!function_exists('requireSubscriber')) {
    function requireSubscriber() {
        requireAuth();
        
        if (!isSubscriber()) {
            // Verificar se é requisição AJAX
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
    }
}

/**
 * Obter ID do usuário logado
 */
if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
}

/**
 * Obter dados do usuário logado
 */
if (!function_exists('getCurrentUser')) {
    function getCurrentUser() {
        if (!isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'name' => $_SESSION['name'] ?? 'Usuário',
            'email' => $_SESSION['email'] ?? '',
            'role' => $_SESSION['user_role'] ?? 'free',
            'is_subscriber' => $_SESSION['is_subscriber'] ?? false,
            'is_admin' => $_SESSION['is_admin'] ?? false
        ];
    }
}
