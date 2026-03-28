<?php
session_start();
// --- CAMINHO CORRIGIDO ---
require_once __DIR__ . '/../config/database.php';

$page_title = 'Supercurso de Tradução de Jogos - Translators101';
$page_description = 'Torne-se um especialista na localização de jogos com o curso completo, ministrado por Ivar Jr, Luciana Boldorini e Horacio Corral.';

// --- CAMINHO CORRIGIDO ---
include __DIR__ . '/../vision/includes/head.php';
?>

<style>
/* Estilos específicos para a página do Supercurso */
.main-content {
    padding-top: 40;
}

/* --- Hero Section (Adaptado ao estilo da Videoteca) --- */
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

/* --- Seção de Bônus --- */
.bonus-card { background: linear-gradient(135deg, rgba(39, 174, 96, 0.1), rgba(39, 174, 96, 0.05)); border: 1px solid var(--accent-green); padding: 2rem; }
.bonus-card .icon { color: var(--accent-green); }

/* --- Seção de Depoimentos --- */
.testimonials-grid { display: flex; flex-direction: column; align-items: center; gap: 2rem; }
.testimonials-grid img { max-width: 900px; width: 100%; height: auto; border-radius: 15px; }

/* --- Seção de Preço (Final CTA) --- */
.final-cta-section { background: none; padding-bottom: 2rem; }
.final-cta-card { max-width: 800px; margin: 0 auto; background: transparent; padding: 0; text-align: center; }
.final-cta-card a img { max-width: 100%; height: auto; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); transition: transform 0.3s ease, box-shadow 0.3s ease; }
.final-cta-card a:hover img { transform: scale(1.02); box-shadow: 0 15px 40px rgba(142, 68, 173, 0.5); }

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
            <h1 class="course-hero-title"><i class="fas fa-gamepad"></i> Supercurso de Tradução de Jogos</h1>
            <p>Aprenda com especialistas da área e domine as técnicas, ferramentas e segredos da localização de jogos.</p>
        </div>
    </div>
    <div class="hero-cta-container">
        <a href="https://pay.hotmart.com/W101846156U?checkoutMode=6&off=jgybfc1g&offDiscount=SC8-LISTA" class="hero-cta-btn">Quero me inscrever agora!</a>
    </div>

    <section class="course-section">
        <div class="glass-section">
            <div class="benefits-grid">
                <div class="benefit-card">
                    <div class="icon"><i class="fas fa-graduation-cap"></i></div>
                    <h3>Aprenda com especialistas</h3>
                    <p>Aulas com Ivar Jr, Luciana Boldorini e Horacio Corral, profissionais com vasta experiência em grandes títulos do mercado.</p>
                </div>
                <div class="benefit-card">
                    <div class="icon"><i class="fas fa-tools"></i></div>
                    <h3>Conteúdo prático e direto</h3>
                    <p>Foco em habilidades aplicáveis, desde o primeiro contato com o cliente até a entrega final e o uso de ferramentas.</p>
                </div>
                <div class="benefit-card">
                    <div class="icon"><i class="fas fa-history"></i></div>
                    <h3>Acesso por 6 meses</h3>
                    <p>Assista às aulas no seu ritmo, com acesso por 6 meses após a conclusão das aulas ao vivo, incluindo futuras atualizações.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="course-section">
        <div class="glass-section">
            <h2 class="section-title">Conheça seus instrutores</h2>
            <p class="section-subtitle">Uma equipe de peso para guiar você nesta jornada.</p>
            <div class="instructors-grid">
                <div class="instructor-card">
                    <img src="/images/cursos/supercurso8/ivar.png" alt="Foto de Ivar Jr">
                    <h3>Ivar Jr</h3>
                    <p class="title">Especialista em</p><p class="title">Tradução Criativa</p>
                    <p>Mestre em adaptar nomes, piadas e referências culturais, garantindo que a alma do jogo não se perca na tradução.</p>
                </div>
                <div class="instructor-card">
                    <img src="/images/cursos/supercurso8/luciana.png" alt="Foto de Luciana Boldorini">
                    <h3>Luciana Boldorini</h3>
                    <p class="title">Especialista em</p><p class="title">Jogos de Tabuleiro</p>
                    <p>Com vasta experiência na localização de board games, Luciana traz uma perspectiva única sobre a tradução de regras e narrativas interativas.</p>
                </div>
                <div class="instructor-card">
                    <img src="/images/cursos/supercurso8/horacio.png" alt="Foto de Horacio Corral">
                    <h3>Horacio Corral</h3>
                    <p class="title">Especialista em</p><p class="title">Localização de Games</p>
                    <p>Atuou em grandes projetos para consoles e PC, dominando as ferramentas e os processos que as grandes empresas exigem.</p>
                </div>
            </div>
            <div class="extra-cta-container">
                <a href="https://pay.hotmart.com/W101846156U?checkoutMode=6&off=jgybfc1g&offDiscount=SC8-LISTA" class="hero-cta-btn">Garantir minha vaga agora!</a>
            </div>
        </div>
    </section>

    <section class="course-section">
        <div class="glass-section">
            <h2 class="section-title">O que você vai aprender</h2>
            <p class="section-subtitle">Uma jornada completa, do básico ao avançado, em 8 módulos detalhados.</p>
            <div class="accordion-container" id="modules-accordion"></div>
            <div class="extra-cta-container">
                <a href="https://pay.hotmart.com/W101846156U?checkoutMode=6&off=jgybfc1g&offDiscount=SC8-LISTA" class="hero-cta-btn">Quero ter acesso a tudo isso!</a>
            </div>
        </div>
    </section>
    
    <section class="course-section">
        <div class="glass-section">
            <h2 class="section-title">E ainda tem mais! Bônus exclusivos</h2>
            <p class="section-subtitle">Ao se inscrever, você também garante acesso a estes materiais incríveis.</p>
            <div class="benefits-grid">
                <div class="benefit-card bonus-card">
                    <div class="icon"><i class="fas fa-file-alt"></i></div>
                    <h3>Modelos de Orçamento e Contrato</h3>
                    <p>Comece a prospectar clientes com segurança usando nossos modelos prontos e editáveis.</p>
                </div>
                <div class="benefit-card bonus-card">
                    <div class="icon"><i class="fas fa-comments"></i></div>
                    <h3>Comunidade VIP de Alunos</h3>
                    <p>Acesso a um grupo exclusivo no Discord para networking, tirar dúvidas e trocar experiências.</p>
                </div>
                <div class="benefit-card bonus-card">
                    <div class="icon"><i class="fas fa-briefcase"></i></div>
                    <h3>Lista de Agências de Localização</h3>
                    <p>Uma lista selecionada com mais de 50 empresas que contratam tradutores de jogos.</p>
                </div>
            </div>
            <div class="extra-cta-container">
                <a href="https://pay.hotmart.com/W101846156U?checkoutMode=6&off=jgybfc1g&offDiscount=SC8-LISTA" class="hero-cta-btn">Quero os bônus!</a>
            </div>
        </div>
    </section>
    
    <section class="course-section">
        <div class="glass-section">
            <h2 class="section-title">Para quem é este curso?</h2>
            <ul class="target-audience-list">
                <li><i class="fas fa-check-circle"></i> Tradutores iniciantes que querem entrar em um nicho lucrativo.</li>
                <li><i class="fas fa-check-circle"></i> Profissionais experientes de outras áreas que buscam se especializar em games.</li>
                <li><i class="fas fa-check-circle"></i> Gamers apaixonados por idiomas que sonham em trabalhar na indústria.</li>
                <li><i class="fas fa-check-circle"></i> Estudantes de Letras e Tradução que desejam um diferencial no currículo.</li>
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
                    <p>Curso online e ao vivo, com todas as aulas gravadas e disponíveis para consulta.</p>
                </div>
                <div class="detail-card">
                    <div class="icon"><i class="fas fa-hourglass-half"></i></div>
                    <h3>Carga horária</h3>
                    <p>10 encontros de 2 horas cada, totalizando 20 horas de conteúdo aprofundado.</p>
                </div>
                 <div class="detail-card">
                    <div class="icon"><i class="fas fa-clock"></i></div>
                    <h3>Horário das aulas</h3>
                    <p>Aulas teóricas e práticas, sempre das 19h às 21h (horário de Brasília).</p>
                </div>
                <div class="detail-card">
                    <div class="icon"><i class="fas fa-calendar-alt"></i></div>
                    <h3>Datas dos encontros</h3>
                    <p>3, 5, 10, 13, 17, 19, 24 e 26 de novembro e 1 e 3 de dezembro.</p>
                </div>
                 <div class="detail-card">
                    <div class="icon"><i class="fas fa-certificate"></i></div>
                    <h3>Certificado</h3>
                    <p>Certificado de conclusão reconhecido para comprovar sua especialização.</p>
                </div>
                <div class="detail-card">
                    <div class="icon"><i class="fas fa-desktop"></i></div>
                    <h3>Plataforma Zoom</h3>
                    <p>As aulas são ministradas no Zoom, uma plataforma de alta confiabilidade.</p>
                </div>
            </div>
        </div>
    </section>
    <section class="course-section testimonials-enhanced fade-item">
        <div class="glass-section">
            <h2 class="section-title section-title-centered">Veja o que os alunos estão dizendo</h2>
            <p class="section-subtitle">(Depoimentos reais de profissionais que fizeram o curso)</p>
            <div class="testimonials-grid">
                <img src="/images/cursos/supercurso8/sc_comentarios_1.png" alt="Depoimentos de alunos do curso de tradução de jogos">
                <img src="/images/cursos/supercurso8/sc_comentarios_2.png" alt="Mais depoimentos de alunos do curso">
            </div>
        </div>
    </section>
    
    <section class="course-section">
        <div class="glass-section">
            <h2 class="section-title">Perguntas Frequentes</h2>
            <div class="accordion-container" id="faq-accordion"></div>
        </div>
    </section>
    
    

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    const courseModules = [
        { title: "Módulo 1: Introdução à Localização de Jogos", lessons: ["O que é localização?", "Diferenças entre tradução e localização", "O mercado de games no Brasil e no mundo", "Tipos de jogos e suas particularidades"] },
        { title: "Módulo 2: Ferramentas do Tradutor de Jogos", lessons: ["CAT Tools essenciais para games", "Uso de Inteligência Artificial na tradução", "Plataformas de localização (MemoQ, SmartCat, etc.)", "Criando e gerenciando glossários e TMs"] },
        { title: "Módulo 3: O Processo de Localização na Prática", lessons: ["Recebendo o primeiro projeto", "Análise de arquivos e restrições (character limit)", "Variáveis, tags e placeholders: como lidar?", "O processo de LQA (Localization Quality Assurance)"] },
        { title: "Módulo 4: Desafios Criativos da Tradução", lessons: ["Tradução de nomes de personagens e lugares", "Adaptação de piadas e referências culturais", "Tradução de UI/UX (Interface de Usuário)", "Narrativa e consistência de tom"] },
        { title: "Módulo 5: Tradução de Jogos de Tabuleiro", lessons: ["Diferenças entre jogos digitais e analógicos", "Traduzindo manuais e regras complexas", "Consistência terminológica em expansões", "Estudo de caso: A tradução de um board game famoso"] },
        { title: "Módulo 6: O Mercado de Trabalho", lessons: ["Como montar seu portfólio", "Onde encontrar clientes (agências vs. diretos)", "Como precificar seu trabalho (palavra, hora, projeto)", "Testes de tradução: como se preparar e se destacar"] },
        { title: "Módulo 7: Aspectos Legais e de Carreira", lessons: ["Contratos e NDAs (Acordos de Confidencialidade)", "Direitos autorais e créditos na tradução", "Networking e presença online para tradutores de jogos"] },
        { title: "Módulo 8: Mão na Massa - Projeto Final", lessons: ["Traduzindo um mini-jogo (simulado)", "Aplicando LQA no seu próprio trabalho", "Recebendo feedback e aprimorando a entrega"] }
    ];

    const faqItems = [
        { question: "O curso é para iniciantes?", answer: "Sim! O curso foi desenhado para levar você do zero absoluto até um nível profissional, mesmo que nunca tenha traduzido um jogo antes." },
        { question: "Preciso de algum software específico?", answer: "Vamos indicar diversas ferramentas, incluindo opções gratuitas e pagas. Você não precisa comprar nada antes de começar o curso; vamos guiar você em cada etapa." },
        { question: "As aulas são ao vivo ou gravadas?", answer: "Todas as aulas são gravadas para você assistir quando e onde quiser. O acesso é garantido por 6 meses após a conclusão das aulas ao vivo." },
        { question: "Terei certificado de conclusão?", answer: "Sim! Ao concluir 100% das aulas, você poderá emitir seu certificado diretamente na plataforma, pronto para adicionar ao seu currículo e LinkedIn." },
        { question: "Como funciona o suporte para tirar dúvidas?", answer: "Você terá acesso à nossa comunidade exclusiva de alunos no Discord, onde poderá interagir com os instrutores e outros colegas para tirar dúvidas e trocar experiências." }
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