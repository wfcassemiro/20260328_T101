<?php
/**
 * Configuração de Integração com Zoom - VERSÃO CORRIGIDA
 * Credenciais Server-to-Server OAuth
 */

// Credenciais do Zoom
define('ZOOM_ACCOUNT_ID', 'KiJeWwARQbGPJ1uhAWf-dw');
define('ZOOM_CLIENT_ID', 'F0STeVn6RCqnh90twpeWQQ');
define('ZOOM_CLIENT_SECRET', '6J0ihEGf6JjjyixZ4pkV5u4DmVR9nuJ5');
define('ZOOM_SECRET_TOKEN', '_bavMn5PQeKbw0ZMnfnoMw');

// URLs da API do Zoom
define('ZOOM_API_BASE_URL', 'https://api.zoom.us/v2');
define('ZOOM_OAUTH_TOKEN_URL', 'https://zoom.us/oauth/token');

// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'u335416710_t101_db');
define('DB_USER', 'u335416710_t101');
define('DB_PASS', 'Pa392ap!');

// Timezone
date_default_timezone_set('America/Sao_Paulo');

/**
 * Conexão com o banco de dados
 */
function getDbConnection() {
    global $pdo;
    
    if (isset($pdo) && $pdo instanceof PDO) {
        return $pdo;
    }
    
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Erro de conexão com BD: " . $e->getMessage());
        throw new Exception("Erro ao conectar com o banco de dados");
    }
}


?>