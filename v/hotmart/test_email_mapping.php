<?php
/**
 * Script de teste rápido - Verificar mapeamento por email
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/hotmart.php';
require_once __DIR__ . '/../hotmart.php';

echo "=== Teste de Mapeamento por Email ===\n\n";

$api = new HotmartAPI();

// 1. Buscar Club Users
echo "1. Buscando Club Users...\n";
$subdomain = defined('HOTMART_SUBDOMAIN') ? HOTMART_SUBDOMAIN : 'assinaturapremiumplustranslato';
$clubResult = $api->getClubUsers($subdomain);
echo "Success: " . ($clubResult['success'] ? 'true' : 'false') . "\n";

if ($clubResult['success'] && !empty($clubResult['data'])) {
    $users = $clubResult['data'];
    echo "Usuários no Club: " . count($users) . "\n\n";
    
    // Criar mapeamento email -> ucode
    $emailMap = [];
    foreach ($users as $user) {
        $email = strtolower(trim($user['email'] ?? ''));
        $ucode = $user['ucode'] ?? $user['subscriber_code'] ?? null;
        
        if ($email && $ucode) {
            $emailMap[$email] = $ucode;
            echo "✓ {$email} → {$ucode}\n";
        }
    }
    
    echo "\nTotal mapeado: " . count($emailMap) . " emails\n\n";
    
    // 2. Testar com alguns usuários do banco
    echo "2. Testando com usuários do banco...\n\n";
    $stmt = $pdo->query("
        SELECT email, name, hotmart_subscription_id
        FROM users 
        WHERE is_active = 1 
        AND hotmart_subscription_id IS NOT NULL
        LIMIT 5
    ");
    
    $localUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($localUsers as $localUser) {
        $email = strtolower(trim($localUser['email']));
        echo "Usuário: {$localUser['name']} ({$email})\n";
        echo "  hotmart_subscription_id: {$localUser['hotmart_subscription_id']}\n";
        
        if (isset($emailMap[$email])) {
            $ucode = $emailMap[$email];
            echo "  ✓ Encontrado no mapeamento! ucode: {$ucode}\n";
            
            // Testar getUserProgress
            echo "  → Buscando progresso...\n";
            $progressResult = $api->getUserProgress($ucode);
            echo "  ← Success: " . ($progressResult['success'] ? 'true' : 'false') . "\n";
            
            if ($progressResult['success'] && isset($progressResult['data'])) {
                $dataCount = is_array($progressResult['data']) ? count($progressResult['data']) : 0;
                echo "  ✓ Progresso encontrado: {$dataCount} itens\n";
            } else {
                echo "  ✗ Sem progresso\n";
            }
        } else {
            echo "  ✗ NÃO encontrado no mapeamento\n";
            echo "  (Este email não está no Club)\n";
        }
        echo "\n";
    }
    
} else {
    echo "✗ Club Users vazio ou falhou\n";
    echo "Resposta: " . json_encode($clubResult, JSON_PRETTY_PRINT) . "\n";
}

echo "\n=== Fim do Teste ===\n";