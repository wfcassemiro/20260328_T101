<?php
session_start();
// Incluir configuração do banco de dados
require_once __DIR__ . '/../config/database.php';

// Definir título e descrição da página (será preenchido pela IA)
$page_title = '[TÍTULO DO CURSO] - Translators101';
$page_description = '[DESCRIÇÃO DO CURSO]';

// Incluir cabeçalho e estilos
include __DIR__ . '/../vision/includes/head.php';
?>

<style>
/* Estilos específicos para a página do curso */
.main-content {
    padding-top: 90px;
}

/* --- Hero Section --- */
.course-hero-title {
    display: flex;
    align-items: center;
    gap: 15px;
    justify-content: center;
    font-size: 3rem;
    margin-bottom: 1rem;
}
.hero-cta-container {
    padding: 2rem 0;
    text-align: center;
}
.hero-cta-btn {
    font-size: 1.3rem;
    padding: 18px 36px;
    background: linear-gradient(135deg, var(--accent-gold), #e67e22);
    box-shadow: 0 8px 25px rgba(243, 156, 18, 0.4);
    animation: pulse 2.5s infinite;
    border-radius: 30px;
    color: white;
    text-decoration: none;
    font-weight: bold;
    display: inline-block;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.hero-cta-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(243, 156, 18, 0.6);
}

/* --- Seções Gerais --- */
.course-section {
    padding: 4rem 1rem;
}
.section-title {
    text-align: center;
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
}
.section-subtitle {
    text-align: center;
    font-size: 1.2rem;
    color: var(--text-muted);
    margin-bottom: 3.5rem;
    max-width: 700px;
    margin-left: auto;
    margin-right: auto;
}

/* --- Grids e Cards --- */
.content-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    max-width: 1100px;
    margin: 0 auto;
}
.benefit-card, .detail-card, .instructor-card, .module-card {
    background: var(--glass-bg);
    padding: 2rem;
    border-radius: 16px;
    border: 1px solid var(--glass-border);
    text-align: center;
    transition: all 0.3s ease;
}
.benefit-card:hover, .detail-card:hover, .instructor-card:hover, .module-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.2);
    border-color: var(--brand-purple);
}
.benefit-card .icon, .detail-card .icon, .module-card .icon {
    font-size: 2.5rem;
    color: var(--accent-gold);
    margin-bottom: 1rem;
}
.benefit-card h3, .detail-card h3, .module-card h3 {
    font-size: 1.4rem;
    margin-bottom: 0.8rem;
}
.detail-card p, .module-card p {
    font-size: 1.1rem;
    line-height: 1.6;
}

/* --- Seção de Instrutores --- */
.instructor-card img {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid var(--brand-purple);
    margin-bottom: 1.5rem;
}
.instructor-card h3 {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}
.instructor-card .title {
    color: var(--accent-gold);
    font-weight: 600;
    margin-bottom: 0.1rem;
}

/* --- Seção de Módulos e FAQ (Accordion) --- */
.accordion-container {
    max-width: 850px;
    margin: 0 auto;
}
.accordion-item {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    margin-bottom: 15px;
    overflow: hidden;
}
.accordion-header {
    padding: 1.2rem 1.5rem;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: background 0.3s ease;
}
.accordion-header:hover { background: rgba(255,255,255,0.05); }
.accordion-header h3 { margin: 0; font-size: 1.2rem; }
.accordion-icon { transition: transform 0.3s ease; font-size: 1.2rem; color: var(--accent-gold); }
.accordion-item.active .accordion-icon { transform: rotate(180deg); }
.accordion-content { max-height: 0; overflow: hidden; transition: max-height 0.4s ease-out, padding 0.4s ease-out; }
.accordion-content-inner { padding: 0 1.5rem 1.5rem; border-top: 1px solid var(--glass-border); }
.accordion-content-inner ul { list-style: none; padding: 0; margin: 0; }
.accordion-content-inner li { padding: 0.8rem 0; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; gap: 10px; }
.accordion-content-inner li:last-child { border-bottom: none; }
.accordion-content-inner li i { color: var(--accent-green); }

/* --- Seção Para Quem é o Curso --- */
.target-audience-list { list-style: none; padding: 0; max-width: 700px; margin: 0 auto; font-size: 1.1rem; }
.target-audience-list li { background: var(--glass-bg); padding: 1rem 1.5rem; border-radius: 12px; margin-bottom: 10px; display: flex; align-items: center; gap: 15px; }
.target-audience-list i { color: var(--accent-green); font-size: 1.4rem; }

/* --- Seção de Depoimentos --- */
.testimonials-grid { display: flex; flex-direction: column; align-items: center; gap: 2rem; }
.testimonials-grid img { max-width: 900px; width: 100%; height: auto; border-radius: 15px; }

/* --- CTA extra --- */
.extra-cta-container { text-align: center; margin-top: 3rem; }

/* --- Responsividade --- */
@media(max-width: 900px) {
    .hero-content h1 { font-size: 2.5rem; }
    .content-grid { grid-template-columns: 1fr; }
}
</style>

<?php
// Incluir cabeçalho e sidebar
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    
    <div class="glass-hero">
        <div class="hero-content" style="text-align: center;">
            <h1 class="course-hero-title"><i class="[ÍCONE DO CURSO]"></i> [TÍTULO DO CURSO]</h1>
            <p>[SUBTÍTULO/DESCRIÇÃO CURTA DO CURSO]</p>
        </div>
    </div>
    
    <div class="hero-cta-container">
        <a href="[LINK PARA COMPRA]" class="hero-cta-btn">Quero me inscrever agora!</a>
    </div>

    <!-- Seção de Benefícios -->
    <section class="course-section">
        <div class="glass-section">
            <h2 class="section-title">Por que fazer este curso?</h2>
            <div class="content-grid">
                <!-- Os benefícios serão adicionados aqui pela IA -->
                [BLOCOS DE BENEFÍCIOS]
            </div>
        </div>
    </section>

    <!-- Seção de Instrutores -->
    <section class="course-section">
        <div class="glass-section">
            <h2 class="section-title">Conheça seus instrutores</h2>
            <p class="section-subtitle">Profissionais experientes que vão guiar você nesta jornada.</p>
            <div class="content-grid">
                <!-- Os instrutores serão adicionados aqui pela IA -->
                [BLOCOS DE INSTRUTORES]
            </div>
            <div class="extra-cta-container">
                <a href="[LINK PARA COMPRA]" class="hero-cta-btn">Garantir minha vaga agora!</a>
            </div>
        </div>
    </section>

    <!-- Seção de Conteúdo do Curso -->
    <section class="course-section">
        <div class="glass-section">
            <h2 class="section-title">O que você vai aprender</h2>
            <p class="section-subtitle">[DESCRIÇÃO DO CONTEÚDO]</p>
            <div class="accordion-container" id="modules-accordion"></div>
            <div class="extra-cta-container">
                <a href="[LINK PARA COMPRA]" class="hero-cta-btn">Quero ter acesso a tudo isso!</a>
            </div>
        </div>
    </section>
    
    <!-- Seção de Público-Alvo -->
    <section class="course-section">
        <div class="glass-section">
            <h2 class="section-title">Para quem é este curso?</h2>
            <ul class="target-audience-list">
                <!-- O público-alvo será adicionado aqui pela IA -->
                [ITENS DO PÚBLICO-ALVO]
            </ul>
        </div>
    </section>

    <!-- Seção de Detalhes do Curso -->
    <section class="course-section">
        <div class="glass-section">
            <h2 class="section-title">Como funciona o curso?</h2>
            <div class="content-grid">
                <!-- Os detalhes serão adicionados aqui pela IA -->
                [BLOCOS DE DETALHES]
            </div>
        </div>
    </section>

    <!-- Seção de Depoimentos -->
    <section class="course-section">
        <div class="glass-section">
            <h2 class="section-title">O que diz quem já fez o curso?</h2>
            <div class="testimonials-grid">
                <!-- Os depoimentos serão adicionados aqui pela IA -->
                [IMAGENS DE DEPOIMENTOS]
            </div>
        </div>
    </section>
    
    <!-- Seção de FAQ -->
    <section class="course-section">
        <div class="glass-section">
            <h2 class="section-title">Perguntas Frequentes</h2>
            <div class="accordion-container" id="faq-accordion"></div>
        </div>
    </section>
    
    <!-- Seção de CTA Final -->
    <section class="course-section" id="comprar">
        <div class="hero-cta-container">
            <a href="[LINK PARA COMPRA]" class="hero-cta-btn">Garantir minha vaga agora!</a>
        </div>
    </section>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Módulos do curso (será preenchido pela IA)
    const courseModules = [
        [MÓDULOS DO CURSO]
    ];

    // Perguntas frequentes (será preenchido pela IA)
    const faqItems = [
        [PERGUNTAS E RESPOSTAS FREQUENTES]
    ];

    function createAccordion(containerId, items) {
        const container = document.getElementById(containerId);
        if (!container) return;

        items.forEach((item, index) => {
            const itemEl = document.createElement('div');
            itemEl.className = 'accordion-item';

            let contentHtml = '';
            if (item.lessons) {
                contentHtml = '<ul>' + item.lessons.map(lesson => `<li><i class="fas fa-check-circle"></i>${lesson}</li>`).join('') + '</ul>';
            } else {
                contentHtml = `<p>${item.answer}</p>`;
            }
            
            itemEl.innerHTML = `
                <div class="accordion-header">
                    <h3>${item.title || item.question}</h3>
                    <i class="fas fa-chevron-down accordion-icon"></i>
                </div>
                <div class="accordion-content">
                    <div class="accordion-content-inner">
                        ${contentHtml}
                    </div>
                </div>
            `;
            container.appendChild(itemEl);
        });

        container.addEventListener('click', function(e) {
            const header = e.target.closest('.accordion-header');
            if (!header) return;

            const item = header.parentElement;
            const content = header.nextElementSibling;
            
            const isActive = item.classList.contains('active');

            container.querySelectorAll('.accordion-item').forEach(otherItem => {
                otherItem.classList.remove('active');
                otherItem.querySelector('.accordion-content').style.maxHeight = null;
            });

            if (!isActive) {
                item.classList.add('active');
                content.style.maxHeight = content.scrollHeight + "px";
            }
        });
    }

    createAccordion('modules-accordion', courseModules);
    createAccordion('faq-accordion', faqItems);

    // Smooth scroll para links de âncora
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>