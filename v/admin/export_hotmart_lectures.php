<?php
set_time_limit(600);
session_start();
require_once __DIR__ . '/../config/database.php';

// --- Verificação de segurança ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die('Acesso negado.');
}

// --- Funções de Autenticação ---
define('HOTMART_BASIC_AUTH', 'Basic N2UzZDM0MmQtYWY0Zi00MTkwLTk1OWMtNmE5NzU0NmYxNDM3OjZmNjI1NzZmLTQzMzUtNDBkMC04N2FhLThhNThmMDlkZjdmZA==');
define('HOTMART_ACCESS_TOKEN_URL', 'https://api-sec-vlc.hotmart.com/security/oauth/token');
define('HOTMART_API_BASE_URL', 'https://developers.hotmart.com');

function get_hotmart_access_token() {
    $ch = curl_init(HOTMART_ACCESS_TOKEN_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['grant_type' => 'client_credentials']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: ' . HOTMART_BASIC_AUTH, 'Content-Type: application/x-www-form-urlencoded']);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http_code !== 200) return null;
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

try {
    $access_token = get_hotmart_access_token();
    if (!$access_token) throw new Exception("Falha na autenticação com a Hotmart.");

    $headers = ['Authorization: Bearer ' . $access_token, 'Content-Type: application/json'];
    $hotmart_club_subdomain = 'assinaturapremiumplustranslato';
    $hotmart_modules = [];
    
    // Buscar todos os módulos da Hotmart
    $modules_url = HOTMART_API_BASE_URL . '/club/api/v1/modules?' . http_build_query(['subdomain' => $hotmart_club_subdomain, 'max_results' => 500]);
    
    $ch = curl_init($modules_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) throw new Exception("Falha ao buscar módulos da Hotmart. Código: {$http_code}");
    
    $hotmart_data = json_decode($response, true);
    if (empty($hotmart_data)) throw new Exception('Nenhum módulo foi retornado pela API da Hotmart.');

    // Preparar para gerar o CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="hotmart_modulos_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // BOM para UTF-8 funcionar no Excel

    // Cabeçalho do CSV
    fputcsv($output, ['ID do Módulo (Hotmart)', 'ID da Primeira Lição (Hotmart)', 'Título na Hotmart']);

    // Preencher o CSV com os dados
    foreach ($hotmart_data as $module) {
        fputcsv($output, [
            $module['module_id'] ?? 'N/A',
            $module['classes'][0] ?? 'N/A', // ID da primeira lição
            $module['name'] ?? 'N/A'
        ]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    // Se der erro, exibe uma mensagem de erro simples
    die('Erro ao gerar o arquivo CSV: ' . $e->getMessage());
}