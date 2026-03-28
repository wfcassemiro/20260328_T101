<?php
date_default_timezone_set('America/Sao_Paulo');
// check-live-status.php
// Este arquivo serve apenas para retornar o status atual da live (JSON)

require_once __DIR__ . '/../../config/database.php'; // Ajuste o caminho se necessário

// Previne cache do navegador para garantir resposta fresca
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json');

$live_status = '0';

try {
    $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'live_status'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $live_status = $result['setting_value'];
    }
} catch (Exception $e) {
    // Em caso de erro, assume offline
    $live_status = '0';
}

// Retorna JSON: { "active": true } ou { "active": false }
echo json_encode(['status' => $live_status]);
exit;