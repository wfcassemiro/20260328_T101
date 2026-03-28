// Confirma que o JS carregou
console.log("✅ Translators101 Vision UI ativo!");

// ===== Header Animado =====
window.addEventListener("scroll", () => {
  const header = document.querySelector(".glass-header");
  if (!header) return;
  if (window.scrollY > 50) {
    header.classList.add("scrolled");
  } else {
    header.classList.remove("scrolled");
  }
});

// ===== Lazy Fade-In com IntersectionObserver =====
const fadeItems = document.querySelectorAll(".fade-item");
const observer = new IntersectionObserver((entries, obs) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add("visible");
      obs.unobserve(entry.target);
    }
  });
}, { threshold: 0.2 });
fadeItems.forEach(item => observer.observe(item));

// ===== Sidebar Toggle para Mobile e Desktop =====
const sidebar = document.getElementById("sidebar");
const burger = document.querySelector(".mobile-menu-toggle");

if (burger && sidebar) {
  burger.addEventListener("click", () => {
    if (window.innerWidth <= 900) {
      // Mobile -> abre/fecha a sidebar
      sidebar.classList.toggle("active");
    } else {
      // Desktop -> colapsa/expande
      sidebar.classList.toggle("collapsed");
      document.body.classList.toggle("sidebar-collapsed");
    }
  });
}

// ===== Hover Expand na Sidebar (Desktop) =====
if (sidebar) {
  sidebar.addEventListener("mouseenter", () => {
    if (window.innerWidth > 900) {
      sidebar.classList.remove("collapsed");
      document.body.classList.remove("sidebar-collapsed");
    }
  });
  sidebar.addEventListener("mouseleave", () => {
    if (window.innerWidth > 900) {
      sidebar.classList.add("collapsed");
      document.body.classList.add("sidebar-collapsed");
    }
  });
}

// ===== Fechar sidebar mobile ao clicar fora =====
document.addEventListener("click", (e) => {
  if (window.innerWidth <= 900 && sidebar && sidebar.classList.contains("active")) {
    if (!sidebar.contains(e.target) && !burger.contains(e.target)) {
      sidebar.classList.remove("active");
    }
  }
});

// ===== Ajuste responsivo ao redimensionar =====
window.addEventListener("resize", () => {
  if (sidebar) {
    if (window.innerWidth > 900) {
      // Desktop → sidebar colapsada por padrão
      sidebar.classList.add("collapsed");
      document.body.classList.add("sidebar-collapsed");
      sidebar.classList.remove("active");
    } else {
      // Mobile → remove estado desktop
      sidebar.classList.remove("collapsed");
      document.body.classList.remove("sidebar-collapsed");
    }
  }
});

// ===== Inicialização =====
document.addEventListener("DOMContentLoaded", () => {
  if (sidebar && window.innerWidth > 900) {
    sidebar.classList.add("collapsed");
    document.body.classList.add("sidebar-collapsed");
  }
});