<?php
session_start();

// Armazenar informações para mensagem
$was_logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? '';

// Destruir sessão
session_destroy();

$page_title = 'Logout - Translators101';
$page_description = 'Você foi desconectado da plataforma';

include __DIR__ . '/vision/includes/head.php';
include __DIR__ . '/vision/includes/header.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-sign-out-alt"></i> Logout Realizado</h1>
            <p><?php echo $was_logged_in ? 'Você foi desconectado com sucesso' : 'Sessão encerrada'; ?></p>
        </div>
    </div>

    <div class="video-card" style="max-width: 500px; margin: 0 auto; text-align: center;">
        <?php if ($was_logged_in): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle"></i>
                <?php if ($user_name): ?>
                    Até logo, <?php echo htmlspecialchars($user_name); ?>!
                <?php else: ?>
                    Logout realizado com sucesso!
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <h2><i class="fas fa-wave-square"></i> Obrigado pela visita!</h2>
        <p style="color: #ccc; margin-bottom: 30px;">
            Esperamos vê-lo novamente em breve na plataforma Translators101.
        </p>

        <div class="quick-actions-grid" style="grid-template-columns: 1fr 1fr; max-width: 400px; margin: 0 auto;">
            <a href="login.php" class="quick-action-card">
                <div class="quick-action-icon quick-action-icon-purple">
                    <i class="fas fa-sign-in-alt"></i>
                </div>
                <h3>Fazer Login</h3>
                <p>Entrar novamente</p>
            </a>

            <a href="index.php" class="quick-action-card">
                <div class="quick-action-icon quick-action-icon-blue">
                    <i class="fas fa-home"></i>
                </div>
                <h3>Página Inicial</h3>
                <p>Voltar ao início</p>
            </a>
        </div>

        <div style="margin-top: 30px; padding-top: 30px; border-top: 1px solid var(--glass-border);">
            <p style="color: #999; font-size: 0.9rem;">
                <i class="fas fa-shield-alt"></i> 
                Sua sessão foi encerrada com segurança
            </p>
        </div>
    </div>

    <!-- Informações úteis -->
    <div class="video-card">
        <h2><i class="fas fa-info-circle"></i> Informações Úteis</h2>
        
        <div class="dashboard-sections">
            <div>
                <h3><i class="fas fa-star"></i> <strong>Não perca!</strong></h3>
                <p>Acesse regularmente nossa plataforma para acompanhar novas palestras e materiais educacionais.</p>
                
                <h3><i class="fas fa-mobile-alt"></i> <strong>Acesso Mobile</strong></h3>
                <p>Nossa plataforma funciona perfeitamente em dispositivos móveis. Estude onde estiver!</p>
            </div>
            
            <div>
                <h3><i class="fas fa-certificate"></i> <strong>Certificados</strong></h3>
                <p>Não esqueça de baixar seus certificados após assistir às palestras completas.</p>
                
                <h3><i class="fas fa-envelope"></i> <strong>Contato</strong></h3>
                <p>Dúvidas? Entre em contato conosco através da página de contato.</p>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="contato.php" class="page-btn">
                <i class="fas fa-paper-plane"></i> Fale Conosco
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>