<?php
// sidebar.php - Menu lateral para páginas de usuário comum

if (!isset($active_page)) {
    $active_page = '';
}

function isActivePage($page, $active_page) {
    return $page === $active_page ? 'bg-purple-600 text-white' : 'hover:bg-gray-700 transition-colors';
}
?>

<!-- Sidebar -->
<div class="w-64 bg-gray-800 p-6 flex-shrink-0 min-h-screen overflow-y-auto">
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-purple-400">Área do assinante</h2>
        <p class="text-gray-400">Translators101</p>
    </div>
    
    <nav class="space-y-2">
        <!-- Dashboard -->
        <a href="dash-t101/" class="block p-3 rounded <?php echo isActivePage('dash-t101', $active_page); ?>">
            <i class="fas fa-home mr-2"></i>Dash-T101
        </a>
        
        <!-- Minhas palestras -->
        <a href="videoteca.php" class="block p-3 rounded <?php echo isActivePage('videoteca', $active_page); ?>">
            <i class="fas fa-play-circle mr-2"></i>Minhas palestras
        </a>
        
        <!-- Certificados -->
        <a href="certificados_final.php" class="block p-3 rounded <?php echo isActivePage('certificados_final', $active_page); ?>">
            <i class="fas fa-certificate mr-2"></i>Certificados
        </a>
        
        <!-- Glossários -->
        <a href="glossarios.php" class="block p-3 rounded <?php echo isActivePage('glossarios', $active_page); ?>">
            <i class="fas fa-book mr-2"></i>Glossários
        </a>

        <!-- Faturas -->
        <a href="invoices.php" class="block p-3 rounded <?php echo isActivePage('invoices', $active_page); ?>">
            <i class="fas fa-file-invoice-dollar mr-2"></i>Faturas
        </a>

        <!-- Projetos -->
        <a href="projects.php" class="block p-3 rounded <?php echo isActivePage('projects', $active_page); ?>">
            <i class="fas fa-project-diagram mr-2"></i>Projetos
        </a>

        <!-- Clientes -->
        <a href="clients.php" class="block p-3 rounded <?php echo isActivePage('clients', $active_page); ?>">
            <i class="fas fa-users mr-2"></i>Clientes
        </a>
    </nav>
    
    <div class="mt-8 pt-8 border-t border-gray-700">
        <a href="logout.php" class="block p-3 rounded hover:bg-gray-700 transition-colors">
            <i class="fas fa-sign-out-alt mr-2"></i>Sair
        </a>
    </div>
</div>