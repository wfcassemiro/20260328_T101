<header class="glass-header">
  <div class="logo">
    <a href="/">
    <img src="/images/Logo-t101.webp" alt="Translators101" width="75" height="40">
    </a>
  </div>

  <nav class="desktop-nav">
    <ul>
    <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
    <li><a href="/perfil.php"><i class="fa-solid fa-user-circle"></i> Perfil</a></li>
    <li><a href="/agenda.php"><i class="fa-solid fa-calendar-alt"></i> Agenda</a></li>
    <li><a href="/arquivo_radar.php"><i class="fa-solid fa-satellite-dish"></i> Radar T101</a></li>
    <li><a href="/cursos/"><i class="fa-solid fa-graduation-cap"></i><span>Cursos</span></a></li>
    <li><a href="/contato.php"><i class="fa-solid fa-envelope"></i> Contato</a></li>
    <li><a href="/logout_confirm.php"><i class="fa-solid fa-sign-out-alt"></i><span>Sair</span></a></li>
    <?php else: ?>
    <li><a href="/agenda.php"><i class="fa-solid fa-calendar-alt"></i> Agenda</a></li>
    <li><a href="/arquivo_radar.php"><i class="fa-solid fa-satellite-dish"></i> Radar T101</a></li>
    <li><a href="/#:~:text=Escolha%20seu%20plano%20e%20comece%20hoje%20mesmo"><i class="fa-solid fa-briefcase"></i><span>Planos</span></a></li>
    <li><a href="/cursos/"><i class="fa-solid fa-graduation-cap"></i><span>Cursos</span></a></li>
    <li><a href="/faq.php"><i class="fa-solid fa-circle-question"></i> FAQ</a></li>
    <li><a href="/contato.php"><i class="fa-solid fa-envelope"></i> Contato</a></li>
    <li><a href="/login.php"><i class="fa-solid fa-key"></i> Login</a></li>
    <li><a href="/agenda.php#:~:text=N%C3%A3o%20se%20preocupe-,%3A,-s%C3%B3%20usaremos%20para"><i class="fa-solid fa-user-plus"></i> Cadastro</a></li>
    <?php endif; ?>
    </ul>
  </nav>

  <div class="mobile-menu-toggle">
    <i class="fa-solid fa-bars"></i>
  </div>

    <style>
        .desktop-nav ul {
            display: flex;
            align-items: center;
            gap: 2rem;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .desktop-nav li {
            display: flex;
            align-items: center;
        }

        .desktop-nav a {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
        }

        .desktop-nav i {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .desktop-nav span {
            font-size: 1rem;
        }

        /* Garante que o header fique por cima da sidebar (z-index: 1000) */
        .glass-header {
            position: relative;
            z-index: 1001;
        }
    </style>
</header>