<?php
session_start();
require_once '../config/database.php'; 
require_once '../hotmart.php'; 
require_once '../includes/hotmart_sync.php'; 

if (!isAdmin()) {
    header('Location: ../login.php'); 
    exit;
}

$active_page = 'hotmart'; // Para destacar o menu lateral
$page_title = 'Admin - Integração Hotmart';
$sync_message = '';
$sync_message_type = '';
$error_message = ''; // Para erros gerais da página
$token_message = '';
$token_message_type = '';

$hotmart = new Hotmart(); 
$tokenResult = $hotmart->getAccessToken();

if ($tokenResult['success']) {
    $token_message = "Conexão com API Hotmart bem-sucedida! Access Token obtido.";
    $token_message_type = 'success';
    $hotmart->setAccessToken($tokenResult['access_token']);
} else {
    $token_message = "Falha na Conexão com Hotmart API: " . ($tokenResult['message'] ?? 'Erro desconhecido.');
    $token_message_type = 'error';
    // Não prosseguir com sincronização ou outras chamadas API se o token falhar
}

// Processar ação de sincronização se o token foi obtido
if ($tokenResult['success'] && isset($_POST['sync_hotmart'])) {
    writeToHotmartApiLog("Admin Hotmart: Sincronização manual disparada.", "HOTMART_ADMIN_ACTION");
    $hotmartSync = new HotmartSync($pdo, $hotmart); 
    $syncResult = $hotmartSync->syncSubscriptions(); 
    
    if ($syncResult['success']) {
        $sync_message = "Sincronização de assinaturas concluída. Total processado na API: " . ($syncResult['total_synced'] ?? 'N/A') . ".";
        $sync_message_type = 'success';
    } else {
        $sync_message = "Erro na sincronização de assinaturas: " . ($syncResult['message'] ?? 'Erro desconhecido.');
        $sync_message_type = 'error';
    }
}

// Buscar assinaturas do banco de dados local para exibição
$subscriptionsFromDb = [];
$product_id_filter = 4304019; // Seu product_id específico
try {
    $stmt_local_subs = $pdo->prepare("SELECT * FROM hotmart_subscriptions WHERE product_id = ? ORDER BY created_at DESC LIMIT 100");
    $stmt_local_subs->execute([$product_id_filter]);
    $subscriptionsFromDb = $stmt_local_subs->fetchAll();
} catch (PDOException $e) {
    $error_message = "Erro ao carregar assinaturas do banco de dados local: " . $e->getMessage();
}

include '../includes/header.php';
?>

<div class="flex min-h-screen bg-gray-900">
    <!-- Menu lateral -->
    <?php include 'admin_sidebar.php'; ?>

    <!-- Conteúdo principal -->
    <main class="flex-1 p-8 bg-gray-900">
        <div class="min-h-screen px-4 py-8">
            <div class="max-w-7xl mx-auto">
                <!-- Header da Página -->
                <div class="flex justify-between items-center mb-8">
                    <h1 class="text-3xl font-bold"><?php echo $page_title; ?></h1>
                    <a href="/admin/" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors">
                        ← Voltar ao Dashboard
                    </a>
                </div>

                <?php if ($token_message): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $token_message_type === 'success' ? 'bg-green-600 bg-opacity-20 border border-green-600 text-green-300' : 'bg-red-600 bg-opacity-20 border border-red-600 text-red-300'; ?>">
                    <p><?php echo htmlspecialchars($token_message); ?></p>
                    <?php if ($token_message_type === 'error' && isset($tokenResult['response'])): ?>
                    <pre class="mt-2 text-xs"><?php print_r($tokenResult['response']); ?></pre>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($sync_message): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $sync_message_type === 'success' ? 'bg-green-600 bg-opacity-20 border border-green-600 text-green-300' : 'bg-red-600 bg-opacity-20 border border-red-600 text-red-300'; ?>">
                    <p><?php echo htmlspecialchars($sync_message); ?></p>
                    <?php if ($sync_message_type === 'error' && isset($syncResult) && isset($syncResult['response'])): ?>
                    <pre class="mt-2 text-xs"><?php print_r($syncResult['response']); ?></pre>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                <div class="bg-red-600 bg-opacity-20 border border-red-600 text-red-300 rounded-lg p-4 mb-6">
                    <p><?php echo htmlspecialchars($error_message); ?></p>
                </div>
                <?php endif; ?>

                <!-- Seção de Sincronização Manual -->
                <div class="bg-gray-900 rounded-lg p-6 mb-8">
                    <h2 class="text-xl font-bold mb-4">Sincronização Manual</h2>
                    <p class="text-gray-400 mb-4">
                        Clique no botão abaixo para buscar as últimas atualizações de assinaturas da Hotmart para o produto ID <?php echo $product_id_filter; ?> e atualizar o banco de dados local.
                    </p>
                    <form method="POST">
                        <button type="submit" name="sync_hotmart" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors <?php echo !$tokenResult['success'] ? 'opacity-50 cursor-not-allowed' : ''; ?>" <?php echo !$tokenResult['success'] ? 'disabled' : ''; ?>>
                            <i class="fas fa-sync-alt mr-2"></i>Sincronizar Assinaturas da Hotmart
                        </button>
                    </form>
                    <?php if (!$tokenResult['success']): ?>
                    <p class="text-sm text-yellow-400 mt-2">A sincronização está desabilitada devido à falha na obtenção do Access Token da Hotmart.</p>
                    <?php endif; ?>
                </div>

                <!-- Assinaturas Sincronizadas do DB Local -->
                <div class="bg-gray-900 rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-700">
                        <h2 class="text-xl font-bold">Assinaturas Sincronizadas no DB (Produto: <?php echo $product_id_filter; ?>)</h2>
                    </div>
                    
                    <?php if (!empty($subscriptionsFromDb)): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-800">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">ID Assinatura Hotmart</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">Comprador</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">Tipo de Assinatura (Plano)</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">Status Hotmart</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">Data Início</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">Data Fim/Expira</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <?php foreach ($subscriptionsFromDb as $sub): ?>
                                <tr class="hover:bg-gray-800">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300"><?php echo htmlspecialchars($sub['subscription_id']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300"><?php echo htmlspecialchars($sub['buyer_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300"><?php echo htmlspecialchars($sub['buyer_email']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300"><?php echo htmlspecialchars($sub['product_name']); // Este campo agora contém o nome do plano ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo (strtoupper($sub['status']) === 'ACTIVE' || strtoupper($sub['status']) === 'GRACE_PERIOD') ? 'bg-green-700 text-green-100' : 'bg-red-700 text-red-100'; ?>">
                                            <?php echo htmlspecialchars(strtoupper($sub['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400"><?php echo $sub['start_date'] ? date('d/m/Y H:i', strtotime($sub['start_date'])) : '-'; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400"><?php echo $sub['end_date'] ? date('d/m/Y H:i', strtotime($sub['end_date'])) : '-'; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <?php
                                        // Verificar se o usuário existe na tabela 'users' para habilitar o botão de reset de senha
                                        $stmt_check_user = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                                        $stmt_check_user->execute([$sub['buyer_email']]);
                                        $user_exists = $stmt_check_user->fetch();
                                        ?>
                                        <?php if ($user_exists): ?>
                                        <form action="/admin/gerenciar_senhas.php" method="POST" class="inline">
                                            <input type="hidden" name="user_email_for_reset" value="<?php echo htmlspecialchars($sub['buyer_email']); ?>">
                                            <button type="submit" name="send_reset_link" class="text-blue-400 hover:text-blue-300" title="Enviar link para redefinição de senha">
                                                <i class="fas fa-key"></i> Redefinir senha
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <span class="text-xs text-gray-500 italic" title="Usuário não encontrado no sistema local para permitir reset de senha.">Sem Ação</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-12">
                        <div class="text-4xl mb-4">💳</div>
                        <p class="text-gray-400">Nenhuma assinatura encontrada para o produto <?php echo $product_id_filter; ?> no banco de dados local.</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Seção de Teste de Endpoints (Opcional, pode ser expandida) -->
                <div class="bg-gray-900 rounded-lg p-6 mt-8">
                    <h2 class="text-xl font-bold mb-4">Teste de Outros Endpoints (Debug)</h2>
                    <?php if ($tokenResult['success']): ?>
                    <p class="text-gray-400 mb-2">Resultados de chamadas de API para depuração (limitado a poucos itens):</p>
                    <div class="text-xs bg-gray-800 p-4 rounded overflow-x-auto">
                        <p class="text-yellow-400">Endpoints de Vendas, Produtos, etc., ainda precisam ter suas URLs/caminhos verificados na documentação da Hotmart, pois atualmente retornam HTML.</p>
                    </div>
                    <?php else: ?>
                    <p class="text-yellow-400">Outros testes de endpoint desabilitados devido à falha na obtenção do Access Token.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>