<?php
$page_title = 'Teste CSS - Dashboard';
include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-test"></i> Teste de CSS Vision UI</h1>
            <p>Se você está vendo este texto com o fundo escuro e efeito glass, o CSS está funcionando!</p>
        </div>
    </div>

    <div class="video-card">
        <h2><i class="fas fa-check-circle"></i> CSS Carregado com Sucesso!</h2>
        <p>O arquivo CSS está sendo carregado corretamente.</p>
        <a href="index.php" class="cta-btn">
            <i class="fas fa-arrow-left"></i> Voltar ao Dashboard
        </a>
    </div>
</div>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>