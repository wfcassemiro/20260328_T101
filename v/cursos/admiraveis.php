<?php
session_start();
// --- CAMINHO CORRIGIDO ---
require_once __DIR__ . '/../config/database.php';

$page_title = 'Admiráveis Ferramentas Novas - Translators101';
$page_description = 'Aprenda a usar a inteligência artificial na tradução com ChatGPT, ModernMT e NotebookLM para aumentar sua produtividade.';

// --- CAMINHO CORRIGIDO ---
include __DIR__ . '/../vision/includes/head.php';

// Define o caminho base para as imagens
$image_path = '/images/cursos/admiraveis/';
?>

<style>

/* Efeito de transparência na imagem da seção de oferta */
.offer-section img {
    opacity: 0.8; /* Opacidade inicial (ajuste o valor como preferir) */
    transition: opacity 0.3s ease; /* Animação suave para a transição */
}

.offer-section img:hover {
    opacity: 1; /* Imagem fica 100% nítida ao passar o mouse */
}



/* Estilos específicos para a página, adaptados dos modelos */
.main-content {
    padding-top: 90px; /* Adicionado para não ficar embaixo do menu */
}

/* Estilo para o novo cabeçalho, do supercurso.php */
.course-hero-title {
    display: flex;
    align-items: center;
    gap: 15px;
    justify-content: center;
    font-size: 3rem;
    margin-bottom: 1rem;
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
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
}

/* --- Botão de CTA Principal --- */
.hero-cta-btn {
    font-size: 1.3rem;
    padding: 18px 36px;
    background: #fddb00; /* Cor amarela específica do curso */
    color: #000;
    box-shadow: 0 8px 25px rgba(253, 219, 0, 0.4);
    animation: pulse 2.5s infinite;
    border-radius: 30px;
    text-decoration: none;
    font-weight: bold;
    display: inline-block;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.hero-cta-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(253, 219, 0, 0.6);
}
.extra-cta-container {
    text-align: center;
    margin-top: 3rem;
}

/* --- Seção de Introdução --- */
.intro-grid {
    display: grid;
    grid-template-columns: 1fr 2fr;
    align-items: center;
    gap: 3rem;
    max-width: 1100px;
    margin: 0 auto;
}
.intro-image-container img {
    width: 100%;
    max-width: 320px;
    height: auto;
    border-radius: 20%;
    opacity: 0.9;
    border-width: 10px;
    box-shadow: 0 0 25px rgba(253, 219, 0, 0.5);
}
.intro-text-container h2 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
}
.intro-text-container p {
    font-size: 1.1rem;
    line-height: 1.7;
    margin-bottom: 1rem;
}

/* --- Grid de Módulos e Depoimentos --- */
.content-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
}
.module-card, .testimonial-card {
    background: var(--glass-bg);
    border-radius: 16px;
    border: 1px solid var(--glass-border);
    padding: 2rem;
    text-align: center;
    transition: all 0.3s ease;
    display: flex; 
    flex-direction: column; 
    justify-content: space-between; 
}
.module-card:hover, .testimonial-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.2);
    border-color: #fddb00;
}
.module-card img, .testimonial-card img {
    width: 100%;
    height: auto;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}
.module-card h3 {
    font-size: 1.5rem;
    margin-bottom: 1rem;
}

/* --- Seção do Autor --- */
.author-section {
    display: flex;
    align-items: center;
    gap: 2.5rem;
    max-width: 1000px;
    margin: 0 auto;
    background: var(--glass-bg);
    padding: 2.5rem;
    border-radius: 16px;
    border: 1px solid var(--glass-border);
}
.author-image img {
    width: 200px;
    height: 200px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #fddb00;
}
.author-info h3 {
    font-size: 2rem;
    margin-bottom: 1rem;
}
.author-info p {
    line-height: 1.6;
    margin-bottom: 1rem;
}

/* --- Seção de Oferta --- */
.offer-section img {
    max-width: 700px;
    width: 100%;
    height: auto;
    margin: 2rem auto;
    display: block;
}
.offer-section .warning-text {
    margin-top: 2rem;
    font-size: 1.5em;
    font-weight: 900;
    color: #ff0000;
    text-align: center;
}

/* --- Seção de Garantia --- */
.guarantee-box {
    border-top: 2px solid var(--text-color);
    border-bottom: 2px solid var(--text-color);
    padding: 1.5rem 0;
    margin: 0 auto 3rem auto;
    max-width: 700px;
    text-align: center;
}
.guarantee-box p {
    font-size: 1.2rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

/* --- Accordion (FAQ) --- */
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
.accordion-icon { transition: transform 0.3s ease; font-size: 1.2rem; color: #fddb00; }
.accordion-item.active .accordion-icon { transform: rotate(180deg); }
.accordion-content { max-height: 0; overflow: hidden; transition: max-height 0.4s ease-out, padding 0.4s ease-out; }
.accordion-content-inner { padding: 0 1.5rem 1.5rem; border-top: 1px solid var(--glass-border); }

/* Efeito de transparência interativo nos cards */
.module-card img, .testimonial-card img {
    opacity: 0.8; /* Opacidade inicial */
    transition: opacity 0.3s ease; /* Animação suave */
}

.module-card:hover img, .testimonial-card:hover img {
    opacity: 1; /* Imagem fica 100% nítida no hover do card */
}


/* --- Responsividade --- */
@media(max-width: 900px) {
    .course-hero-title { font-size: 2.5rem; }
    .intro-grid {
        grid-template-columns: 1fr;
        text-align: center;
    }
    .intro-image-container {
        margin: 0 auto;
    }
    .author-section {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<?php
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">

    <div class="glass-hero">
        <div class="hero-content" style="text-align: center;">
            <h1 class="course-hero-title"><i class="fas fa-robot"></i> Admiráveis Ferramentas Novas</h1>
            <p>Aprenda a usar ferramentas de IA em seu fluxo de trabalho para aprimorar suas traduções e produtividade.</p>
        </div>
    </div>
    
    <section class="course-section">
        <div class="glass-section">
            <div class="intro-grid">
                <div class="intro-image-container">
                    <img src="<?php echo $image_path; ?>redondo_cinza.png" alt="O Robozinho Voltou">
                </div>
                <div class="intro-text-container">
                    <h2>Aprenda a usar a inteligência artificial na tradução e aumente sua produtividade agora!</h2>
                    <p>Tenha acesso por seis meses para descobrir como integrar o <strong>ChatGPT</strong> e a <strong>ModernMT</strong> a seu fluxo de trabalho para aprimorar suas traduções e aproveitar as novas possibilidades que as ferramentas oferecem.</p>
                    <p>Crie bots para ajudar com tarefas na tradução.</p>
                    <p>Use o <strong>NotebookLM</strong> para criar glossários e estudar temas para suas traduções e interpretações.</p>
                    <div class="extra-cta-container" style="text-align: left; margin-top: 1.5rem;">
                         <a href="https://pay.hotmart.com/Q99784626X?off=djax7nxl&checkoutMode=10&hotfeature=51" class="hero-cta-btn">Quero aprender!</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="course-section">
        <div class="glass-section">
            <h2 class="section-title">Quem já assistiu ao workshop aprovou!</h2>
            <p class="section-subtitle">Veja o que disseram:</p>
            <div class="content-grid">
                <div class="testimonial-card">
                    <img src="<?php echo $image_path; ?>depoimento_1.png" alt="Depoimento 1: Julieta Boedo">
                    <h3>Julieta Boedo</h3>
                    <p>Tradutora e intérprete de espanhol</p>
                </div>
                <div class="testimonial-card">
                    <img src="<?php echo $image_path; ?>depoimento_2.png" alt="Depoimento 2: Denise Cipullo">
                    <h3>Denise Cipullo</h3>
                    <p>Intérprete e tradutora</p>
                </div>
                <div class="testimonial-card">
                    <img src="<?php echo $image_path; ?>depoimento_3.png" alt="Depoimento 3: Val Ivonica">
                    <h3>Val Ivonica</h3>
                    <p>Tradutora e Professora de Tradução</p>
                </div>
            </div>
        </div>
    </section>

    <section class="course-section">
        <div class="glass-section">
            <h2 class="section-title">Este workshop está dividido em três módulos.</h2>
            <div class="content-grid">
                <div class="module-card">
                    <div>
                        <img src="<?php echo $image_path; ?>vertical_m1.png" alt="Módulo I: Trados Studio + ChatGPT + ModernMT">
                        <h3>Módulo I</h3>
                        <p>O <strong>Módulo I</strong> apresenta o uso de Inteligência Artificial no Trados Studio 2022/2024, destacando a integração de dois recursos via plugins: <strong>ChatGPT</strong> e <strong>ModernMT</strong>. O ChatGPT pode ser utilizado como uma ferramenta de tradução automática, permitindo personalizar resultados por meio de prompts. Já a ModernMT é um sistema de tradução adaptativa que ajusta as traduções conforme as escolhas do usuário.</p>
                    </div>
                    <div class="extra-cta-container" style="margin-top: 2rem;">
                        <a href="https://pay.hotmart.com/U99747359J?checkoutMode=10&hotfeature=51" class="hero-cta-btn" style="font-size: 1rem; padding: 12px 24px;">Módulo I: R$ 150,00</a>
                    </div>
                </div>
                <div class="module-card">
                    <div>
                        <img src="<?php echo $image_path; ?>vertical_m2.png" alt="Módulo II: Dois robots (bots) para chamar de seus">
                        <h3>Módulo II</h3>
                        <p>No <strong>Módulo II</strong>, são apresentadas duas soluções: uma simples e eficiente, outra comercial, ambas aproveitando o poder dos Large Language Models para revolucionar seu trabalho, criando um assistente integrado a qualquer programa – seja navegador, PDF, CAT Tool, Word ou software de legendagem – acessível por um menu dropdown.</p>
                    </div>
                    <div class="extra-cta-container" style="margin-top: 2rem;">
                        <a href="https://pay.hotmart.com/C99755664J?off=jtrew6hw&checkoutMode=10&hotfeature=51" class="hero-cta-btn" style="font-size: 1rem; padding: 12px 24px;">Módulo II: R$ 150,00</a>
                    </div>
                </div>
                <div class="module-card">
                    <div>
                        <img src="<?php echo $image_path; ?>vertical_m3.png" alt="Módulo III: NotebookLM">
                        <h3>Módulo III</h3>
                        <p>No <strong>Módulo III</strong>, você conhecerá o <strong>NotebookLM</strong>, uma ferramenta de IA do Google, gratuita, que permite criar glossários a partir de PDFs, além de resumos de vídeos e websites, guias de estudo, listas de perguntas frequentes e gerar áudios similares a um podcast sobre o conteúdo.</p>
                    </div>
                    <div class="extra-cta-container" style="margin-top: 2rem;">
                         <a href="https://pay.hotmart.com/V99756612U?off=qqzbk206&checkoutMode=10&hotfeature=51" class="hero-cta-btn" style="font-size: 1rem; padding: 12px 24px;">Módulo III: R$ 150,00</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="course-section offer-section" id="oferta">
        <div class="glass-section">
            <h2 class="section-title">Compre individualmente ou economize com o combo!</h2>
            <p class="section-subtitle"><strong>Módulos Individuais:</strong> cada módulo custa <strong>R$150,00</strong>.<br><strong>Ou economize mais de 35% com o combo!</strong></p>
            <img src="<?php echo $image_path; ?>combo.png" alt="Oferta Combo: De R$450 por apenas R$310 em até 12 vezes!">
            <div class="extra-cta-container">
                 <a href="https://pay.hotmart.com/Q99784626X?off=djax7nxl&checkoutMode=10&hotfeature=51" class="hero-cta-btn">QUERO APROVEITAR O COMBO!</a>
            </div>
            <p class="section-subtitle" style="margin-top: 2rem; font-weight: 700; color: #fddb00;">Você terá acesso garantido por seis meses.</p>
            <p class="warning-text">ATENÇÃO: ESTA OFERTA PODE TERMINAR EM BREVE!</p>
        </div>
    </section>

    <section class="course-section">
        <div class="glass-section">
             <h2 class="section-title">Conheça quem criou o conteúdo</h2>
             <div class="author-section">
                 <div class="author-image">
                     <img src="<?php echo $image_path; ?>redondo_jorge.png" alt="Foto do Professor Jorge Davidson">
                 </div>
                 <div class="author-info">
                     <h3>Jorge Davidson</h3>
                     <p>Jorge Davidson é doutor em História Social (UFF) e mestre em Estudos da Linguagem (PUC-Rio). Tradutor freelance inglês/português/espanhol especializado em marketing de produtos tecnológicos, conteúdo técnico, TI, meio-ambiente e Ciências Sociais.</p>
                     <p>Membro da Abrates, capacitador e palestrante. Conta com mais de dez anos de experiência em tradução, gestão de projetos e localização. O Professor é conhecido por suas palestras sobre o impacto da IA e das tecnologias no trabalho do tradutor.</p>
                 </div>
             </div>
        </div>
    </section>

    <section class="course-section">
        <div class="glass-section">
            <h2 class="section-title">Garantia e Perguntas Frequentes</h2>
            <div class="guarantee-box">
                <p><strong>Garantia incondicional de sete dias</strong></p>
                <p><strong>Acesso garantido por seis meses</strong></p>
            </div>
            <div class="accordion-container" id="faq-accordion"></div>
        </div>
    </section>

</div> 

<script>
document.addEventListener('DOMContentLoaded', function() {

    const faqItems = [
        { question: "Para quem é esse produto?", answer: "O produto é destinado a tradutores, intérpretes e profissionais da linguagem que desejam integrar ferramentas de Inteligência Artificial (IA), como ChatGPT, ModernMT e NotebookLM, em seu fluxo de trabalho para aumentar a produtividade e aprimorar a qualidade de suas traduções." },
        { question: "Como funciona o 'Prazo de Garantia'?", answer: "Você tem uma garantia incondicional de 7 dias. Se por qualquer motivo você não estiver satisfeito com o conteúdo, pode solicitar o reembolso total dentro deste período." },
        { question: "O que é e como funciona o Certificado de Conclusão digital?", answer: "O certificado de conclusão é um documento digital que atesta a sua participação e conclusão do workshop, sendo emitido após a finalização de todos os módulos." },
        { question: "Como acessar o produto?", answer: "O acesso é feito pela plataforma Hotmart. Após a confirmação do pagamento, você receberá um e-mail com as instruções e o link para acessar a área de membros. O acesso é válido por seis meses." },
        { question: "Como faço para comprar?", answer: "Você pode comprar individualmente cada módulo ou aproveitar o desconto do combo. Clique no botão 'Quero aprender!' ou 'QUERO APROVEITAR O COMBO!' para ser redirecionado à página de pagamento seguro da Hotmart." }
    ];

    function createAccordion(containerId, items) {
        const container = document.getElementById(containerId);
        if (!container) return;

        items.forEach((item, index) => {
            const itemEl = document.createElement('div');
            itemEl.className = 'accordion-item';

            itemEl.innerHTML = `
                <div class="accordion-header">
                    <h3>${item.question}</h3>
                    <i class="fas fa-chevron-down accordion-icon"></i>
                </div>
                <div class="accordion-content">
                    <div class="accordion-content-inner">
                        <p>${item.answer}</p>
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

            // Fecha todos os outros itens
            container.querySelectorAll('.accordion-item').forEach(otherItem => {
                if (otherItem !== item) {
                    otherItem.classList.remove('active');
                    otherItem.querySelector('.accordion-content').style.maxHeight = null;
                }
            });
            
            // Abre ou fecha o item clicado
            if (!isActive) {
                item.classList.add('active');
                content.style.maxHeight = content.scrollHeight + "px";
            } else {
                item.classList.remove('active');
                content.style.maxHeight = null;
            }
        });
    }

    createAccordion('faq-accordion', faqItems);

    // Smooth scroll for anchor links
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