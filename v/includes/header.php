<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - Translators101' : 'Translators101 - Educação Continuada para Tradutores'; ?></title>
    <meta name="description" content="<?php echo isset($page_description) ? $page_description : 'Plataforma de streaming educacional para profissionais de tradução, interpretação e revisão. Quase 400 palestras especializadas.'; ?>">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Icons -->
<?php if (!isset($hide_top_menu) || !$hide_top_menu): ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Favicon -->
    <link rel="icon" href="/images/favicon.ico">
</head>
<body class="bg-black text-white font-inter">
    <!-- Navigation -->
    <nav class="bg-black bg-opacity-90 fixed w-full z-50 top-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <a href="/" class="flex items-center">
                        <div class="text-2xl font-bold bg-gradient-to-r from-purple-600 to-purple-400 bg-clip-text text-transparent">
                            Translators101
                        </div>
                    </a>
                </div>
                
                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-8">
                        <?php if (!function_exists('isLoggedIn' ) || !isLoggedIn()): ?>
                            <a href="/" class="hover:text-purple-400 transition-colors <?php echo basename($_SERVER["PHP_SELF"]) == "index.php" ? "text-purple-400" : ""; ?>">Home</a>
                        <?php endif; ?>
                        <a href="/videoteca.php" class="hover:text-purple-400 transition-colors <?php echo basename($_SERVER["PHP_SELF"]) == "videoteca.php" ? "text-purple-400" : ""; ?>">Videoteca</a>
                        <a href="/glossarios.php" class="hover:text-purple-400 transition-colors <?php echo basename($_SERVER["PHP_SELF"]) == "glossarios.php" ? "text-purple-400" : ""; ?>">Glossários</a>
                        <?php if (function_exists('isLoggedIn') && isLoggedIn() && function_exists('isAdmin') && isAdmin()): ?>
                            <a href="/admin/glossary/upload_form.php" class="hover:text-green-400 transition-colors <?php echo basename($_SERVER["PHP_SELF"]) == "upload_form.php" ? "text-green-400" : ""; ?>">
                                <i class="fa fa-upload"></i> Gerenciar Glossários
                            </a>
                        <?php endif; ?>
                        <a href="/sobre.php" class="hover:text-purple-400 transition-colors <?php echo basename($_SERVER["PHP_SELF"]) == "sobre.php" ? "text-purple-400" : ""; ?>">Sobre</a>
                        <a href="/planos.php" class="hover:text-purple-400 transition-colors <?php echo basename($_SERVER["PHP_SELF"]) == "planos.php" ? "text-purple-400" : ""; ?>">Planos</a>
                        <a href="/contato.php" class="hover:text-purple-400 transition-colors <?php echo basename($_SERVER["PHP_SELF"]) == "contato.php" ? "text-purple-400" : ""; ?>">Contato</a>
                    </div>
                </div>

                <div class="hidden md:block">
                    <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                        <div class="flex items-center space-x-4">
                            <span class="text-purple-400">Olá, <?php echo htmlspecialchars($_SESSION["user_name"]); ?></span>
                            <?php if (function_exists('isAdmin') && isAdmin()): ?>
                                <a href="/admin/" class="text-yellow-400 hover:text-yellow-300">Admin</a>
                            <?php endif; ?>
                            <a href="/dash-t101/" class="bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg transition-colors">
                                Dash-T101
                            </a>
                            <a href="/logout.php" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg transition-colors">
                                Sair
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="space-x-4">
                            <a href="/login.php" class="bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg transition-colors">
                                Entrar
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button id="mobile-menu-button" class="text-white">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile menu -->
        <div id="mobile-menu" class="hidden md:hidden bg-black bg-opacity-95">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
                <?php if (!function_exists('isLoggedIn') || !isLoggedIn()): ?>
                    <a href="/" class="block px-3 py-2 hover:text-purple-400">Home</a>
                <?php endif; ?>
                <a href="/videoteca.php" class="block px-3 py-2 hover:text-purple-400">Videoteca</a>
                <a href="/glossarios.php" class="block px-3 py-2 hover:text-purple-400">Glossários</a>
                <?php if (function_exists('isLoggedIn') && isLoggedIn() && function_exists('isAdmin') && isAdmin()): ?>
                    <a href="/admin/glossary/upload_form.php" class="block px-3 py-2 text-green-400">
                        <i class="fa fa-upload"></i> Gerenciar Glossários
                    </a>
                <?php endif; ?>
                <a href="/sobre.php" class="block px-3 py-2 hover:text-purple-400">Sobre</a>
                <a href="/planos.php" class="block px-3 py-2 hover:text-purple-400">Planos</a>
                <a href="/contato.php" class="block px-3 py-2 hover:text-purple-400">Contato</a>
                <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                    <a href="/dash-t101/" class="block px-3 py-2 hover:text-purple-400">
                        Dash-T101
                    </a>
                    <?php if (function_exists('isAdmin') && isAdmin()): ?>
                        <a href="/admin/" class="block px-3 py-2 text-yellow-400">Admin</a>
                    <?php endif; ?>
<?php endif; ?>
                    <a href="/logout.php" class="block px-3 py-2 hover:text-purple-400">
                        Sair (<?php echo htmlspecialchars($_SESSION["user_name"]); ?>)
                    </a>
                <?php else: ?>
                    <a href="/login.php" class="block px-3 py-2 hover:text-purple-400">Entrar</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="pt-16">

    <script>
    document.addEventListener('DOMContentLoaded', function () {
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');

    mobileMenuButton.addEventListener('click', function () {
    mobileMenu.classList.toggle('hidden');
    });
    });
    </script>
