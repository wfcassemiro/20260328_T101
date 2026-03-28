<header class="glass-header">
  <!-- Logo -->
  <div class="logo">
    <a href="index.php">
      <img src="images/logo-translators101.png" alt="Translators101" style="height: 40px;">
    </a>
  </div>

  <!-- Navegação Desktop -->
  <nav class="desktop-nav">
    <ul>
      <li><a href="index.php"><i class="fa-solid fa-house"></i> Início</a></li>
      <li><a href="projects.php"><i class="fa-solid fa-folder-open"></i> Projetos</a></li>
      <li><a href="glossarios.php"><i class="fa-solid fa-book"></i> Glossários</a></li>
      <li><a href="videoteca.php"><i class="fa-solid fa-film"></i> Videoteca</a></li>
      <li><a href="planos.php"><i class="fa-solid fa-briefcase"></i> Planos</a></li>
      <li><a href="contato.php"><i class="fa-solid fa-envelope"></i> Contato</a></li>
      <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
        <li><a href="dash-t101/"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
        <li><a href="logout.php"><i class="fa-solid fa-sign-out-alt"></i> Sair</a></li>
      <?php else: ?>
        <li><a href="login.php"><i class="fa-solid fa-key"></i> Login</a></li>
      <?php endif; ?>
    </ul>
  </nav>

  <!-- Botão Hamburguer (mobile) -->
  <div class="mobile-menu-toggle">
    <i class="fa-solid fa-bars"></i>
  </div>
</header>