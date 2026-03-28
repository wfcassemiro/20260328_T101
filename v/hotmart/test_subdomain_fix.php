<?php
/**
 * Teste Rápido - Verificar correção do subdomain
 */

// Carregar configurações
require_once __DIR__ . '/../config/hotmart.php';
require_once __DIR__ . '/../hotmart.php';

echo "=== TESTE DE CORREÇÃO DO SUBDOMAIN ===\n\n";

// Verificar constante
echo "1. HOTMART_SUBDOMAIN configurado: " . HOTMART_SUBDOMAIN . "\n";
echo "   Esperado: assinaturapremiumplustranslato\n";
echo "   Status: " . (HOTMART_SUBDOMAIN === 'assinaturapremiumplustranslato' ? '✅ CORRETO' : '❌ INCORRETO') . "\n\n";

// Testar API
echo "2. Testando chamada à API com subdomain correto...\n";
$api = new HotmartAPI();

// Teste 1: getClubUsers
echo "\n   a) Testando getClubUsers()...\n";
$result = $api->getClubUsers(HOTMART_SUBDOMAIN);
echo "      - Success: " . ($result['success'] ? 'true' : 'false') . "\n";
echo "      - HTTP Code: " . ($result['http_code'] ?? 'N/A') . "\n";
if (!$result['success']) {
    echo "      - Message: " . ($result['message'] ?? 'N/A') . "\n";
}

// Teste 2: getSubscriptions
echo "\n   b) Testando getSubscriptions()...\n";
$subResult = $api->getSubscriptions('ACTIVE');
echo "      - Success: " . ($subResult['success'] ? 'true' : 'false') . "\n";
echo "      - HTTP Code: " . ($subResult['http_code'] ?? 'N/A') . "\n";

if ($subResult['success'] && isset($subResult['data']['items'])) {
    $items = $subResult['data']['items'];
    echo "      - Total de assinaturas: " . count($items) . "\n";
    
    if (count($items) > 0) {
        $firstSub = $items[0];
        echo "      - Primeira assinatura:\n";
        echo "        * Email: " . ($firstSub['subscriber']['email'] ?? 'N/A') . "\n";
        echo "        * Ucode: " . ($firstSub['subscriber']['ucode'] ?? 'N/A') . "\n";
        
        // Teste 3: getUserProgress com o ucode
        if (isset($firstSub['subscriber']['ucode'])) {
            $ucode = $firstSub['subscriber']['ucode'];
            echo "\n   c) Testando getUserProgress({$ucode}) com subdomain...\n";
            $progressResult = $api->getUserProgress($ucode);
            echo "      - Success: " . ($progressResult['success'] ? 'true' : 'false') . "\n";
            echo "      - Message: " . ($progressResult['message'] ?? 'Sem mensagem') . "\n";
            
            if ($progressResult['success'] && isset($progressResult['data'])) {
                $data = $progressResult['data'];
                if (isset($data['items'])) {
                    echo "      - Progresso encontrado: " . count($data['items']) . " itens\n";
                } elseif (isset($data['pages'])) {
                    echo "      - Páginas encontradas: " . count($data['pages']) . " páginas\n";
                } else {
                    echo "      - Dados: " . json_encode($data) . "\n";
                }
            }
        }
    }
}

echo "\n\n=== VERIFICAR LOGS ===\n";
echo "Para detalhes completos, verifique:\n";
echo "- tail -f /var/log/php_errors.log | grep HOTMART\n";
echo "- cat /app/temp_repo/public_html/v/logs/hotmart_progress_sync_local.log\n";

echo "\n✅ Teste concluído!\n";
?>