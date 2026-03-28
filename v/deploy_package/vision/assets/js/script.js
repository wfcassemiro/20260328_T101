// Debug para saber se carregou
console.log("✅ Script carregado corretamente!");

// Evento de scroll para header animado
window.addEventListener("scroll", function () {
  const header = document.querySelector(".glass-header");

  if (!header) return;

  // Efeito no header
  if (window.scrollY > 50) {
    header.classList.add("scrolled");
  } else {
    header.classList.remove("scrolled");
  }
});

// Lazy Fade-In usando IntersectionObserver
const fadeItems = document.querySelectorAll('.fade-item');

const observer = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('visible');
      // Parar de observar depois da primeira animação
      observer.unobserve(entry.target);
    }
  });
}, {
  threshold: 0.2 // ativa quando 20% do elemento aparece
});

fadeItems.forEach(item => {
  observer.observe(item);
});