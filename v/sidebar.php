<?php
// sidebar.php - Menu lateral para páginas de usuário comum (TRANSFORMADO PARA VISION UI)

session_start();
require_once __DIR__ . '/config/database.php';

$page_title = 'Menu de Navegação';
$page_description = 'Menu lateral com efeito glass Vision UI';

include __DIR__ . '/vision/includes/head.php';
include __DIR__ . '/vision/includes/header.php';
include __DIR__ . '/vision/includes/sidebar.php';

if (!isset($active_page)) {
    $active_page = '';
}

function isActivePage($page, $active_page) {
    return $page === $active_page ? 'active' : '';
}
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-bars"></i> Menu de Navegação</h1>
            <p>Acesso rápido às funcionalidades da plataforma</p>
        </div>
    </div>
    
    <div class="video-card">
        <h2><i class="fas fa-user-circle"></i> Área do Assinante</h2>
        <p class="text-light">Translators101 - Plataforma de Educação</p>
        
        <nav class="vision-nav">
            <!-- Dashboard -->
            <a href="dash-t101/" class="nav-item <?php echo isActivePage('dash-t101', $active_page); ?>">
                <i class="fas fa-home"></i>Dash-T101
            </a>
            
            <!-- Minhas palestras -->
            <a href="videoteca.php" class="nav-item <?php echo isActivePage('videoteca', $active_page); ?>">
                <i class="fas fa-play-circle"></i>Minhas palestras
            </a>
            
            <!-- Certificados -->
            <a href="certificados_final.php" class="nav-item <?php echo isActivePage('certificados_final', $active_page); ?>">
                <i class="fas fa-certificate"></i>Certificados
            </a>
            
            <!-- Glossários -->
            <a href="glossarios.php" class="nav-item <?php echo isActivePage('glossarios', $active_page); ?>">
                <i class="fas fa-book"></i>Glossários
            </a>

            <!-- Faturas -->
            <a href="invoices.php" class="nav-item <?php echo isActivePage('invoices', $active_page); ?>">
                <i class="fas fa-file-invoice-dollar"></i>Faturas
            </a>

            <!-- Projetos -->
            <a href="projects.php" class="nav-item <?php echo isActivePage('projects', $active_page); ?>">
                <i class="fas fa-project-diagram"></i>Projetos
            </a>

            <!-- Clientes -->
            <a href="clients.php" class="nav-item <?php echo isActivePage('clients', $active_page); ?>">
                <i class="fas fa-users"></i>Clientes
            </a>
        </nav>
        
        <div class="form-actions">
            <a href="logout.php" class="page-btn btn-danger">
                <i class="fas fa-sign-out-alt"></i> Sair
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>