<?php
/**
 * Teste FINAL - Buscar usuários por EMAIL dos assinantes ativos
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/hotmart.php';
require_once __DIR__ . '/../hotmart.php';

echo "=== Teste FINAL - Usuários Ativos com Progresso ===\n\n";

$api = new HotmartAPI();

// 1. Buscar Subscriptions e criar mapeamento
echo "1. Buscando assinaturas ativas...\n";
$subsResult = $api->getSubscriptions('ACTIVE');

$emailMap = [];
if ($subsResult['success'] && isset($subsResult['data']['items'])) {
    foreach ($subsResult['data']['items'] as $subscription) {
        $email = strtolower(trim($subscription['subscriber']['email'] ?? ''));
        $ucode = $subscription['subscriber']['ucode'] ?? null;
        
        if ($email && $ucode) {
            $emailMap[$email] = $ucode;
        }
    }
    echo "✓ Mapeados: " . count($emailMap) . " emails\n\n";
}

// 2. Buscar usuários do banco cujos EMAILS estão no mapeamento
echo "2. Buscando usuários do banco que SÃO assinantes ativos...\n\n";

$emailList = "'" . implode("','", array_keys($emailMap)) . "'";

$stmt = $pdo->query("
    SELECT id, email, name 
    FROM users 
    WHERE LOWER(email) IN ({$emailList})
    AND is_active = 1
    LIMIT 10
");

$matchedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Usuários encontrados no banco com assinatura ativa: " . count($matchedUsers) . "\n\n";

// 3. Para cada um, buscar progresso
$successCount = 0;
$progressCount = 0;

foreach ($matchedUsers as $user) {
    $email = strtolower(trim($user['email']));
    $ucode = $emailMap[$email];
    
    echo "Usuário: {$user['name']} ({$email})\n";
    echo "  ucode: {$ucode}\n";
    echo "  → Buscando progresso...\n";
    
    $progressResult = $api->getUserProgress($ucode);
    
    if ($progressResult['success']) {
        $successCount++;
        
        $data = $progressResult['data'] ?? [];
        if (is_array($data) && !empty($data)) {
            $progressCount++;
            echo "  ✓✓ PROGRESSO ENCONTRADO! " . count($data) . " itens\n";
            
            // Mostrar primeiro item
            if (isset($data[0])) {
                echo "  Primeiro item de progresso:\n";
                echo "  " . json_encode($data[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            }
        } else {
            echo "  ○ API respondeu mas sem dados de progresso\n";
        }
    } else {
        echo "  ✗ Erro: " . ($progressResult['message'] ?? 'Falha na API') . "\n";
    }
    echo "\n";
}

// 4. RESUMO FINAL
echo "\n=== RESUMO FINAL ===\n";
echo "Assinaturas ativas na Hotmart: " . count($emailMap) . "\n";
echo "Usuários no banco com assinatura ativa: " . count($matchedUsers) . "\n";
echo "APIs de progresso bem-sucedidas: {$successCount}\n";
echo "Usuários COM progresso disponível: {$progressCount}\n\n";

if ($progressCount > 0) {
    echo "🎉🎉🎉 SUCESSO TOTAL! 🎉🎉🎉\n\n";
    echo "Conseguimos:\n";
    echo "1. Mapear assinaturas ativas por email\n";
    echo "2. Encontrar usuários correspondentes no banco\n";
    echo "3. Obter progresso de aulas da API\n";
    echo "4. Identificar estrutura dos dados\n\n";
    echo "✅ PRONTO PARA IMPLEMENTAR A SINCRONIZAÇÃO COMPLETA!\n";
} elseif ($successCount > 0) {
    echo "⚠️ API funciona mas nenhum progresso disponível\n";
    echo "Possíveis causas:\n";
    echo "- Usuários não assistiram aulas ainda\n";
    echo "- Progresso não é registrado via API\n";
    echo "- Precisa configurar algo no Club Hotmart\n";
} else {
    echo "❌ Não conseguimos obter progresso\n";
    echo "Investigar:\n";
    echo "- Permissões da API\n";
    echo "- Configuração do produto\n";
    echo "- Logs da Hotmart\n";
}

echo "\n=== Fim do Teste ===\n";