<?php
session_start();
require_once 'config/database.php';

$page_title = 'Teste Menu Limpo';

include __DIR__ . '/vision/includes/head.php';
include __DIR__ . '/vision/includes/header.php';
include __DIR__ . '/vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1>🧹 Teste Menu Limpo</h1>
            <p>Testando com arquivos completamente novos</p>
        </div>
    </div>
    
    <div class="video-card">
        <h2>Estado do Login</h2>
        <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
            <p>✅ <strong>Usuário LOGADO</strong></p>
            <p>Nome: <?php echo $_SESSION['user_name'] ?? 'N/A'; ?></p>
            <p>Admin: <?php echo (function_exists('isAdmin') && isAdmin()) ? 'SIM' : 'NÃO'; ?></p>
        <?php else: ?>
            <p>❌ <strong>Usuário NÃO LOGADO</strong></p>
        <?php endif; ?>
    </div>
    
    <div class="video-card">
        <h2>✅ Teste com Arquivos Novos</h2>
        <p>Se este menu estiver funcionando, vamos substituir os arquivos originais.</p>
    </div>
</div>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>