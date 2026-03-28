<?php
// admin_sidebar.php - Menu lateral padronizado para o painel administrativo (VISION UI)

// Definir a página ativa se não foi definida
if (!isset($active_page)) {
    $active_page = '';
}

// Função para verificar se a página está ativa
function isActivePage($page, $active_page) {
    return $page === $active_page ? 'active' : '';
}

// Função para verificar se um submenu deve estar aberto
function isSubmenuOpen($submenu_pages, $active_page) {
    return in_array($active_page, $submenu_pages) ? '' : 'closed';
}

// Função para o ícone do chevron
function getChevronClass($submenu_pages, $active_page) {
    return in_array($active_page, $submenu_pages) ? 'open' : '';
}
?>

<div class="glass-sidebar admin-sidebar">
    <div class="sidebar-header">
        <h2><i class="fas fa-crown"></i> Admin Panel</h2>
        <p class="text-light">Translators101</p>
    </div>
    
    <nav class="sidebar-menu">
        <a href="/admin/index.php" class="menu-item <?php echo isActivePage('dashboard', $active_page); ?>">
            <i class="fas fa-dashboard"></i> Dashboard
        </a>
        
        <div class="menu-section">
            <button type="button" onclick="toggleSubmenu('users')" class="menu-toggle <?php echo in_array($active_page, ['usuarios', 'importar_usuarios', 'gerenciar_senhas']) ? 'active' : ''; ?>">
                <span><i class="fas fa-users"></i> Usuários</span>
                <i class="fas fa-chevron-down chevron <?php echo getChevronClass(['usuarios', 'importar_usuarios', 'gerenciar_senhas'], $active_page); ?>" id="users-chevron"></i>
            </button>
            <div id="users-submenu" class="submenu <?php echo isSubmenuOpen(['usuarios', 'importar_usuarios', 'gerenciar_senhas'], $active_page); ?>">
                <a href="/admin/usuarios.php" class="submenu-item <?php echo isActivePage('usuarios', $active_page); ?>">
                    <i class="fas fa-list"></i> Listar Usuários
                </a>
                <a href="/admin/importar_usuarios.php" class="submenu-item <?php echo isActivePage('importar_usuarios', $active_page); ?>">
                    <i class="fas fa-upload"></i> Importar Usuários
                </a>
                <a href="/admin/gerenciar_senhas.php" class="submenu-item <?php echo isActivePage('gerenciar_senhas', $active_page); ?>">
                    <i class="fas fa-key"></i> Gerenciar Senhas
                </a>
            </div>
        </div>
        
        <div class="menu-section">
            <button type="button" onclick="toggleSubmenu('email')" class="menu-toggle <?php echo in_array($active_page, ['emails']) ? 'active' : ''; ?>">
                <span><i class="fas fa-envelope"></i> E-mail</span>
                <i class="fas fa-chevron-down chevron <?php echo getChevronClass(['emails'], $active_page); ?>" id="email-chevron"></i>
            </button>
            <div id="email-submenu" class="submenu <?php echo isSubmenuOpen(['emails'], $active_page); ?>">
                <a href="/admin/emails.php?tab=compose" class="submenu-item <?php echo isActivePage('emails', $active_page); ?>">
                    <i class="fas fa-edit"></i> Compor Email
                </a>
                <a href="/admin/emails.php?tab=test" class="submenu-item <?php echo isActivePage('emails', $active_page); ?>">
                    <i class="fas fa-vial"></i> Testar Sistema
                </a>
            </div>
        </div>
        
        <div class="menu-section">
            <button type="button" onclick="toggleSubmenu('content')" class="menu-toggle <?php echo in_array($active_page, ['palestras', 'glossarios', 'certificados']) ? 'active' : ''; ?>">
                <span><i class="fas fa-book"></i> Conteúdo</span>
                <i class="fas fa-chevron-down chevron <?php echo getChevronClass(['palestras', 'glossarios', 'certificados'], $active_page); ?>" id="content-chevron"></i>
            </button>
            <div id="content-submenu" class="submenu <?php echo isSubmenuOpen(['palestras', 'glossarios', 'certificados'], $active_page); ?>">
                <a href="/admin/palestras.php" class="submenu-item <?php echo isActivePage('palestras', $active_page); ?>">
                    <i class="fas fa-video"></i> Palestras
                </a>
                <a href="/admin/glossarios.php" class="submenu-item <?php echo isActivePage('glossarios', $active_page); ?>">
                    <i class="fas fa-book"></i> Glossários
                </a>
                <a href="/admin/certificados.php" class="submenu-item <?php echo isActivePage('certificados', $active_page); ?>">
                    <i class="fas fa-certificate"></i> Certificados
                </a>
            </div>
        </div>
        
        <a href="/admin/hotmart.php" class="menu-item <?php echo isActivePage('hotmart', $active_page); ?>">
            <i class="fas fa-shopping-cart"></i> Hotmart
        </a>
        
        <a href="/admin/logs.php" class="menu-item <?php echo isActivePage('logs', $active_page); ?>">
            <i class="fas fa-history"></i> Logs
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <a href="/" class="menu-item">
            <i class="fas fa-home"></i> Ver Site
        </a>
        <a href="/logout.php" class="menu-item">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>

<style>
.admin-sidebar {
    width: 280px;
    min-height: 100vh;
    padding: 2rem;
    display: flex;
    flex-direction: column;
}

.sidebar-header {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--glass-border);
}

.sidebar-header h2 {
    color: var(--brand-purple);
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.sidebar-menu {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.menu-item, .menu-toggle {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    color: var(--text-light);
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s ease;
    border: none;
    background: none;
    width: 100%;
    cursor: pointer;
    font-size: 0.95rem;
}

.menu-item:hover, .menu-toggle:hover {
    background: rgba(142, 68, 173, 0.2);
    color: white;
}

.menu-item.active, .menu-toggle.active {
    background: var(--brand-purple);
    color: white;
}

.menu-item i, .menu-toggle i {
    margin-right: 0.75rem;
    width: 1.25rem;
    text-align: center;
}

.menu-toggle {
    justify-content: space-between;
}

.chevron {
    transition: transform 0.3s ease;
    font-size: 0.75rem;
}

.chevron.open {
    transform: rotate(180deg);
}

.menu-section {
    margin-bottom: 0.5rem;
}

.submenu {
    margin-left: 1rem;
    margin-top: 0.5rem;
    padding-left: 1rem;
    border-left: 2px solid var(--glass-border);
    max-height: 200px;
    overflow: hidden;
    transition: max-height 0.3s ease;
}

.submenu.closed {
    max-height: 0;
    margin-top: 0;
}

.submenu-item {
    display: flex;
    align-items: center;
    padding: 0.5rem 0.75rem;
    color: var(--text-light);
    text-decoration: none;
    border-radius: 6px;
    transition: all 0.3s ease;
    font-size: 0.875rem;
    margin-bottom: 0.25rem;
}

.submenu-item:hover {
    background: rgba(142, 68, 173, 0.15);
    color: white;
}

.submenu-item.active {
    background: rgba(142, 68, 173, 0.3);
    color: white;
}

.submenu-item i {
    margin-right: 0.5rem;
    width: 1rem;
    text-align: center;
    font-size: 0.875rem;
}

.sidebar-footer {
    margin-top: 2rem;
    padding-top: 1rem;
    border-top: 1px solid var(--glass-border);
}
</style>

<script>
// Função para alternar submenus
function toggleSubmenu(menu) {
    const submenu = document.getElementById(menu + '-submenu');
    const chevron = document.getElementById(menu + '-chevron');
    
    if (submenu.classList.contains('closed')) {
        submenu.classList.remove('closed');
        chevron.classList.add('open');
    } else {
        submenu.classList.add('closed');
        chevron.classList.remove('open');
    }
}

// Inicializar submenus abertos baseado na página ativa
document.addEventListener('DOMContentLoaded', function() {
    const activeSubmenuUsers = ['usuarios', 'importar_usuarios', 'gerenciar_senhas'];
    const activeSubmenuEmail = ['emails'];
    const activeSubmenuContent = ['palestras', 'glossarios', 'certificados'];
    const activePage = '<?php echo $active_page; ?>';
    
    if (activeSubmenuUsers.includes(activePage)) {
        document.getElementById('users-submenu').classList.remove('closed');
        document.getElementById('users-chevron').classList.add('open');
    }
    if (activeSubmenuEmail.includes(activePage)) {
        document.getElementById('email-submenu').classList.remove('closed');
        document.getElementById('email-chevron').classList.add('open');
    }
    if (activeSubmenuContent.includes(activePage)) {
        document.getElementById('content-submenu').classList.remove('closed');
        document.getElementById('content-chevron').classList.add('open');
    }
});
</script>