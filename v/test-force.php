<?php
session_start();
require_once 'config/database.php';

$page_title = 'Teste Forçado';
include __DIR__ . '/vision/includes/head-force.php';
include __DIR__ . '/vision/includes/header-new.php';
include __DIR__ . '/vision/includes/sidebar-new.php';
?>

<div class=\"main-content\">
    <div class=\"glass-hero\">
        <div class=\"hero-content\">
            <h1>⚡ Teste Forçado - CSS v16</h1>
            <p>Arquivo head completamente novo com cache-busting v=16</p>
        </div>
    </div>
    
    <div class=\"video-card\">
        <h2>✅ Se você vê efeito glass, o CSS carregou!</h2>
        <p>Verifique o código fonte desta página.</p>
    </div>
</div>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>