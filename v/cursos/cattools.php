<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$page_title = 'Curso de CAT Tools e Tecnologias da Tradução - Translators101';
$page_description = 'Domine as principais ferramentas de tradução assistida por computador e tecnologias essenciais para tradutores profissionais.';

include __DIR__ . '/../vision/includes/head.php';
?>

<style>
/* Estilos específicos para a página do Curso de CAT Tools */
.main-content {
    padding-top: 0;
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

/* --- Seção de Benefícios e Detalhes do Curso --- */
.benefits-grid, .course-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    max-width: 1100px;
    margin: 0 auto;
}
.benefit-card, .detail-card {
    background: var(--glass-bg);
    padding: 2rem;
    border-radius: 16px;
    border: 1px solid var(--glass-border);
    text-align: center;
    transition: all 0.3s ease;
}
.benefit-card:hover, .detail-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.2);
    border-color: var(--brand-purple);
}
.benefit-card .icon, .detail-card .icon {
    font-size: 2.5rem;
    color: var(--accent-gold);
    margin-bottom: 1rem;
}
.benefit-card h3, .detail-card h3 {
    font-size: 1.4rem;
    margin-bottom: 0.8rem;
}
.detail-card p {
    font-size: 1.1rem;
    line-height: 1.6;
}

/* --- Seção de Instrutores --- */
.instructors-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
}
.instructor-card {
    background: var(--glass-bg);
    border-radius: 16px;
    border: 1px solid var(--glass-border);
    padding: 2rem;
    text-align: center;
    transition: all 0.3s ease;
}
.instructor-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0 25px rgba(168, 85, 247, 0.7);
    border-color: var(--brand-purple);
}
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
    margin-bottom: 1rem;
    color: white;
}
.instructor-card p {
    font-size: 1rem;
    line-height: 1.6;
    color: var(--text-muted);
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
.target-audience-list {
    list-style: none;
    padding: 0;
    max-width: 700px;
    margin: 0 auto;
    font-size: 1.1rem;
}
.target-audience-list li {
    background: var(--glass-bg);
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 15px;
}
.target-audience-list i {
    color: var(--accent-green);
    font-size: 1.4rem;
}

/* --- Seção de Depoimentos --- */
.testimonials-grid {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2rem;
}
.testimonials-grid img {
    max-width: 900px;
    width: 100%;
    height: auto;
    border-radius: 15px;
}

/* --- Nota adicional --- */
.course-note {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 1.5rem;
    margin-top: 2rem;
    margin-left: auto;
    margin-right: auto;
    max-width: 1100px;
    text-align: center;
    font-size: 1.3rem;
    font-weight: 600;
    color: white;
    transition: all 0.3s ease;
}
.course-note:hover {
    border-color: var(--brand-purple);
    box-shadow: 0 0 25px rgba(168, 85, 247, 0.7);
    transform: translateY(-3px);
}

/* --- CTA extra --- */
.extra-cta-container { text-align: center; margin-top: 3rem; }

@media(max-width: 900px) {
    .hero-content h1 { font-size: 2.5rem; }
}
</style>

<?php
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    
    <div class="glass-hero">
    <div class="hero-content" style="text-align: center;">
    <h1 class="course-hero-title"><i class="fas fa-laptop-code"></i> Curso de CAT Tools e Tecnologias da Tradução</h1>
    <p>Domine as principais ferramentas de tradução assistida por computador e tecnologias essenciais para tradutores profissionais.</p>
    </div>
    </div>
    <div class="hero-cta-container">
    <a href="https://pay.hotmart.com/S101952150O" class="hero-cta-btn">Quero me inscrever agora!</a>
    </div>

    <section class="course-section">
    <div class="glass-section">
    <h2 class="section-title">Por que fazer este curso?</h2>
    <div class="benefits-grid">
    <div class="benefit-card">
    <div class="icon"><i class="fas fa-briefcase"></i></div>
    <h3>Para ter mais chances de contratação, é obrigatório saber usar CAT Tools</h3>
    </div>
    <div class="benefit-card">
    <div class="icon"><i class="fas fa-user-tie"></i></div>
    <h3>Aprenda com especialistas atuantes no mercado</h3>
    </div>
    <div class="benefit-card">
    <div class="icon"><i class="fas fa-tasks"></i></div>
    <h3>Pratique com exercícios reais e receba feedback especializado</h3>
    </div>
    <div class="benefit-card">
    <div class="icon"><i class="fas fa-chart-line"></i></div>
    <h3>Descubra novas oportunidades de aprimorar sua produtividade</h3>
    </div>
    <div class="benefit-card">
    <div class="icon"><i class="fas fa-robot"></i></div>
    <h3>Atualize-se com as melhores ferramentas, incluindo Inteligência Artificial</h3>
    </div>
    <div class="benefit-card">
    <div class="icon"><i class="fas fa-users"></i></div>
    <h3>Desenvolva o networking com profissionais do setor</h3>
    </div>
    </div>
    </div>
    </section>

    <section class="course-section">
    <div class="glass-section">
    <h2 class="section-title">Conheça seus professores</h2>
    <p class="section-subtitle">Profissionais experientes que vão guiar você nesta jornada.</p>
    <div class="instructors-grid">
    <div class="instructor-card">
    <img src="/images/cursos/cattools/Luciana.png" alt="Foto de Luciana Boldorini">
    <h3>Luciana Boldorini</h3>
    <p>Tradutora, especialista em localização de jogos, formada em revisão pela UNESP, formada em Publicidade e Letras, pós-graduada em Linguística, Mestra pela USP, mais de 20 anos de mercado.</p>
    </div>
    <div class="instructor-card">
    <img src="/images/cursos/cattools/Ivar.png" alt="Foto de Ivar Panazzolo Jr">
    <h3>Ivar Panazzolo Jr</h3>
    <p>Graduado em Marketing com especialização em design gráfico. Tradutor desde 2008 nas áreas de saúde, localização de jogos e tradução literária. Professor em cursos de tradução e membro da diretoria da Abrates.</p>
    </div>
    </div>
    <div class="extra-cta-container">
    <a href="https://pay.hotmart.com/S101952150O" class="hero-cta-btn">Garantir minha vaga agora!</a>
    </div>
    </div>
    </section>

    <section class="course-section">
    <div class="glass-section">
    <h2 class="section-title">O que você vai aprender</h2>
    <p class="section-subtitle">Um curso completo sobre as principais ferramentas e tecnologias da tradução.</p>
    <div class="accordion-container" id="modules-accordion"></div>
    <div class="extra-cta-container">
    <a href="https://pay.hotmart.com/S101952150O" class="hero-cta-btn">Quero ter acesso a tudo isso!</a>
    </div>
    </div>
    </section>
    
    <section class="course-section">
    <div class="glass-section">
    <h2 class="section-title">Para quem é este curso?</h2>
    <ul class="target-audience-list">
    <li><i class="fas fa-check-circle"></i> Tradutores iniciantes que querem profissionalizar seu trabalho.</li>
    <li><i class="fas fa-check-circle"></i> Profissionais que desejam aumentar sua produtividade.</li>
    <li><i class="fas fa-check-circle"></i> Estudantes de Letras e Tradução que buscam conhecimento prático.</li>
    <li><i class="fas fa-check-circle"></i> Qualquer pessoa interessada em tecnologias de tradução.</li>
    </ul>
    </div>
    </section>

    <section class="course-section">
    <div class="glass-section">
    <h2 class="section-title">Como funciona o curso?</h2>
    <div class="course-details-grid">
    <div class="detail-card">
    <div class="icon"><i class="fas fa-video"></i></div>
    <h3>Formato</h3>
    <p>Curso online com aulas ao vivo gravadas e material de apoio.</p>
    </div>
    <div class="detail-card">
    <div class="icon"><i class="fas fa-clock"></i></div>
    <h3>Carga horária</h3>
    <p>15 horas de conteúdo prático e teórico.</p>
    </div>
    <div class="detail-card">
    <div class="icon"><i class="fas fa-calendar-alt"></i></div>
    <h3>Datas das aulas ao vivo</h3>
    <p>20, 22, 24, 27 e 29 de outubro.</p>
    </div>
    <div class="detail-card">
    <div class="icon"><i class="fas fa-hourglass-half"></i></div>
    <h3>Horário</h3>
    <p>19h às 22h (horário de Brasília).</p>
    </div>
    <div class="detail-card">
    <div class="icon"><i class="fas fa-history"></i></div>
    <h3>Acesso às gravações</h3>
    <p>Por 6 meses após o término do curso.</p>
    </div>
    <div class="detail-card">
    <div class="icon"><i class="fas fa-certificate"></i></div>
    <h3>Certificado</h3>
    <p>Certificado de conclusão para comprovar sua especialização.</p>
    </div>
    </div>
    <div class="course-note">
    <p>As ferramentas usadas ao longo do curso são gratuitas para que você consiga</p><p>colocar a mão na massa imediatamente, sem despesas adicionais.</p>
    </div>
    </div>
    </section>

    <section class="course-section">
    <div class="glass-section">
    <h2 class="section-title">O que diz quem já fez o curso?</h2>
    <div class="testimonials-grid">
    <img src="/images/cursos/cattools/depoimentos.png" alt="Depoimentos de alunos do curso de CAT Tools">
    </div>
    </div>
    </section>
    
    <section class="course-section">
    <div class="glass-section">
    <h2 class="section-title">Perguntas Frequentes</h2>
    <div class="accordion-container" id="faq-accordion"></div>
    </div>
    </section>
    
    <section class="course-section final-cta-section" id="comprar">
    <div class="hero-cta-container">
    <a href="https://pay.hotmart.com/S101952150O" class="hero-cta-btn">Garantir minha vaga agora!</a>
    </div>
    </section>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    const courseModules = [
    { title: "Introdução", lessons: ["Conceitos básicos", "Memória de tradução", "Primeiro projeto"] },
    { title: "Tags, glossários e correspondências", lessons: ["Uso de tags", "Como montar e importar glossários", "Correspondências"] },
    { title: "Tradução", lessons: ["Tradução de planilhas", "Alinhamento", "Pré-tradução"] },
    { title: "Funções", lessons: ["Funções avançadas", "Mercado de trabalho", "Práticas de pagamento"] },
    { title: "IA e tecnologias", lessons: ["Tradução de máquina", "Linguística computacional", "IA aplicada à tradução"] }
    ];

    const faqItems = [
    { question: "O curso é para iniciantes?", answer: "Sim! O curso foi desenvolvido para atender desde iniciantes até profissionais que desejam aprimorar suas habilidades." },
    { question: "Preciso comprar as ferramentas?", answer: "Não! As ferramentas usadas ao longo do curso são gratuitas para que você consiga colocar a mão na massa imediatamente, sem despesas adicionais." },
    { question: "As aulas são ao vivo ou gravadas?", answer: "As aulas são ao vivo e ficam gravadas para você assistir quando e onde quiser, no seu próprio ritmo." },
    { question: "Terei certificado de conclusão?", answer: "Sim! Ao concluir o curso, você receberá um certificado digital de conclusão." },
    { question: "Por quanto tempo terei acesso?", answer: "Você terá acesso às gravações por 6 meses após o término do curso." }
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