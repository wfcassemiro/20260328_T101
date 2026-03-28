<?php
// admin_sidebar.php - Menu lateral padronizado para o painel administrativo

// Definir a página ativa se não foi definida
if (!isset($active_page)) {
    $active_page = '';
}

// Função para verificar se a página está ativa
function isActivePage($page, $active_page) {
    return $page === $active_page ? 'bg-purple-600 text-white' : 'hover:bg-gray-700 transition-colors';
}

// Função para verificar se um submenu deve estar aberto
function isSubmenuOpen($submenu_pages, $active_page) {
    return in_array($active_page, $submenu_pages) ? '' : 'hidden';
}

// Função para o ícone do chevron
function getChevronClass($submenu_pages, $active_page) {
    return in_array($active_page, $submenu_pages) ? 'rotate-180' : '';
}
?>

<div class="w-64 bg-gray-800 p-6 flex-shrink-0 min-h-screen overflow-y-auto">
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-purple-400">Admin Panel</h2>
        <p class="text-gray-400">Translators101</p>
    </div>
    
    <nav class="space-y-2">
        <a href="index.php" class="block p-3 rounded <?php echo isActivePage('dashboard', $active_page); ?>">
            <i class="fas fa-dashboard mr-2"></i>Dashboard
        </a>
        
        <div class="space-y-1">
            <button type="button" onclick="toggleSubmenu('users')" class="w-full flex items-center justify-between p-3 rounded <?php echo in_array($active_page, ['usuarios', 'importar_usuarios', 'gerenciar_senhas']) ? 'bg-gray-700' : 'hover:bg-gray-700'; ?> transition-colors">
                <span><i class="fas fa-users mr-2"></i>Usuários</span>
                <i class="fas fa-chevron-down text-xs <?php echo getChevronClass(['usuarios', 'importar_usuarios', 'gerenciar_senhas'], $active_page); ?>" id="users-chevron"></i>
            </button>
            <div id="users-submenu" class="ml-4 space-y-1 <?php echo isSubmenuOpen(['usuarios', 'importar_usuarios', 'gerenciar_senhas'], $active_page); ?>">
                <a href="usuarios.php" class="block p-2 rounded <?php echo isActivePage('usuarios', $active_page); ?> text-sm">
                    <i class="fas fa-list mr-2"></i>Listar Usuários
                </a>
                <a href="importar_usuarios.php" class="block p-2 rounded <?php echo isActivePage('importar_usuarios', $active_page); ?> text-sm">
                    <i class="fas fa-upload mr-2"></i>Importar Usuários
                </a>
                <a href="gerenciar_senhas.php" class="block p-2 rounded <?php echo isActivePage('gerenciar_senhas', $active_page); ?> text-sm">
                    <i class="fas fa-key mr-2"></i>Gerenciar Senhas
                </a>
            </div>
        </div>
        
        <div class="space-y-1">
            <button type="button" onclick="toggleSubmenu('email')" class="w-full flex items-center justify-between p-3 rounded <?php echo in_array($active_page, ['emails']) ? 'bg-gray-700' : 'hover:bg-gray-700'; ?> transition-colors">
                <span><i class="fas fa-envelope mr-2"></i>E-mail</span>
                <i class="fas fa-chevron-down text-xs <?php echo getChevronClass(['emails'], $active_page); ?>" id="email-chevron"></i>
            </button>
            <div id="email-submenu" class="ml-4 space-y-1 <?php echo isSubmenuOpen(['emails'], $active_page); ?>">
                <a href="emails.php?tab=compose" class="block p-2 rounded <?php echo isActivePage('emails', $active_page); ?> text-sm">
                    <i class="fas fa-edit mr-2"></i>Compor Email
                </a>
                <a href="emails.php?tab=test" class="block p-2 rounded <?php echo isActivePage('emails', $active_page); ?> text-sm">
                    <i class="fas fa-vial mr-2"></i>Testar Sistema
                </a>
            </div>
        </div>
        
        <div class="space-y-1">
            <button type="button" onclick="toggleSubmenu('content')" class="w-full flex items-center justify-between p-3 rounded <?php echo in_array($active_page, ['palestras', 'glossarios', 'certificados']) ? 'bg-gray-700' : 'hover:bg-gray-700'; ?> transition-colors">
                <span><i class="fas fa-book mr-2"></i>Conteúdo</span>
                <i class="fas fa-chevron-down text-xs <?php echo getChevronClass(['palestras', 'glossarios', 'certificados'], $active_page); ?>" id="content-chevron"></i>
            </button>
            <div id="content-submenu" class="ml-4 space-y-1 <?php echo isSubmenuOpen(['palestras', 'glossarios', 'certificados'], $active_page); ?>">
                <a href="palestras.php" class="block p-2 rounded <?php echo isActivePage('palestras', $active_page); ?> text-sm">
                    <i class="fas fa-video mr-2"></i>Palestras
                </a>
                <a href="glossarios.php" class="block p-2 rounded <?php echo isActivePage('glossarios', $active_page); ?> text-sm">
                    <i class="fas fa-book mr-2"></i>Glossários
                </a>
                <a href="certificados.php" class="block p-2 rounded <?php echo isActivePage('certificados', $active_page); ?> text-sm">
                    <i class="fas fa-certificate mr-2"></i>Certificados
                </a>
            </div>
        </div>
        
        <a href="hotmart.php" class="block p-3 rounded <?php echo isActivePage('hotmart', $active_page); ?>">
            <i class="fas fa-shopping-cart mr-2"></i>Hotmart
        </a>
        
        <a href="logs.php" class="block p-3 rounded <?php echo isActivePage('logs', $active_page); ?>">
            <i class="fas fa-history mr-2"></i>Logs
        </a>
    </nav>
    
    <div class="mt-8 pt-8 border-t border-gray-700">
        <a href="../" class="block p-3 rounded hover:bg-gray-700 transition-colors">
            <i class="fas fa-home mr-2"></i>Ver Site
        </a>
        <a href="../login.php?logout=1" class="block p-3 rounded hover:bg-gray-700 transition-colors">
            <i class="fas fa-sign-out-alt mr-2"></i>Logout
        </a>
    </div>
</div>

<script>
// Função para alternar submenus
function toggleSubmenu(menu) {
    const submenu = document.getElementById(menu + '-submenu');
    const chevron = document.getElementById(menu + '-chevron');
    
    if (submenu.classList.contains('hidden')) {
        submenu.classList.remove('hidden');
        chevron.classList.add('rotate-180');
    } else {
        submenu.classList.add('hidden');
        chevron.classList.remove('rotate-180');
    }
}

// Inicializar submenus abertos baseado na página ativa
document.addEventListener('DOMContentLoaded', function() {
    const activeSubmenuUsers = ['usuarios', 'importar_usuarios', 'gerenciar_senhas'];
    const activeSubmenuEmail = ['emails'];
    const activeSubmenuContent = ['palestras', 'glossarios', 'certificados'];
    const activePage = '<?php echo $active_page; ?>';
    
    if (activeSubmenuUsers.includes(activePage)) {
        document.getElementById('users-submenu').classList.remove('hidden');
        document.getElementById('users-chevron').classList.add('rotate-180');
    }
    if (activeSubmenuEmail.includes(activePage)) {
        document.getElementById('email-submenu').classList.remove('hidden');
        document.getElementById('email-chevron').classList.add('rotate-180');
    }
    if (activeSubmenuContent.includes(activePage)) {
        document.getElementById('content-submenu').classList.remove('hidden');
        document.getElementById('content-chevron').classList.add('rotate-180');
    }
});
</script>