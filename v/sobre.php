<?php
session_start();
require_once __DIR__ . '/config/database.php';

$page_title = 'Sobre - Translators101';
$page_description = 'Conheça mais sobre a Translators101 e nossa missão na educação continuada para tradutores e intérpretes.';

include __DIR__ . '/vision/includes/head.php';
include __DIR__ . '/vision/includes/header.php';
include __DIR__ . '/vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-users"></i> Sobre a Translators101</h1>
            <p>Conheça nossa história e missão na educação continuada para profissionais da linguagem</p>
        </div>
    </div>

    <div class="video-card">
        <h2><i class="fas fa-rocket"></i> Nossa missão</h2>
        <p>A Translators101 é uma plataforma educacional dedicada ao desenvolvimento profissional de profissionais da tradução, interpretação e revisão. Nossa missão é oferecer conteúdo de alta qualidade que contribua para o crescimento e aperfeiçoamento desses profissionais essenciais.</p>
        
        <div class="stats-grid" style="margin-top: 30px;">
            <div class="video-card stats-card">
                <div class="stats-content">
                    <div class="stats-info">
                        <h3>Palestras disponíveis</h3>
                        <span class="stats-number">~400</span>
                    </div>
                    <div class="stats-icon stats-icon-purple">
                        <i class="fas fa-video"></i>
                    </div>
                </div>
            </div>
            
            <div class="video-card stats-card">
                <div class="stats-content">
                    <div class="stats-info">
                        <h3>Profissionais atendidos</h3>
                        <span class="stats-number">~1.500+</span>
                    </div>
                    <div class="stats-icon stats-icon-blue">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
            
            <div class="video-card stats-card">
                <div class="stats-content">
                    <div class="stats-info">
                        <h3>Horas de conteúdo</h3>
                        <span class="stats-number">+600</span>
                    </div>
                    <div class="stats-icon stats-icon-green">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="video-card">
        <h2><i class="fas fa-graduation-cap"></i> O que oferecemos</h2>
        
        <div class="quick-actions-grid">
            <div class="quick-action-card" style="cursor: default;">
                <div class="quick-action-icon quick-action-icon-purple">
                    <i class="fas fa-video"></i>
                </div>
                <h3>Palestras especializadas</h3>
                <p>Conteúdo exclusivo de especialistas renomados da área</p>
            </div>

            <div class="quick-action-card" style="cursor: default;">
                <div class="quick-action-icon quick-action-icon-blue">
                    <i class="fas fa-book"></i>
                </div>
                <h3>Glossários</h3>
                <p>Materiais de apoio e glossários especializados</p>
            </div>

            <div class="quick-action-card" style="cursor: default;">
                <div class="quick-action-icon quick-action-icon-green">
                    <i class="fas fa-certificate"></i>
                </div>
                <h3>Certificados</h3>
                <p>Certificados de participação para desenvolvimento profissional</p>
            </div>

            <div class="quick-action-card" style="cursor: default;">
                <div class="quick-action-icon quick-action-icon-red">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Comunidade</h3>
                <p>Conecte-se com outros profissionais da área</p>
            </div>
        </div>
    </div>

    <div class="video-card">
        <h2><i class="fas fa-heart"></i> Nossos valores</h2>
        
        <div class="dashboard-sections">
            <div>
                <h3><i class="fas fa-star"></i> <strong>Excelência</strong></h3>
                <p>Buscamos sempre oferecer conteúdo da mais alta qualidade, com palestrantes reconhecidos e temas relevantes para o mercado atual.</p>
                <p>…</p>
                <h3><i class="fas fa-lightbulb"></i> <strong>Inovação</strong></h3>
                <p>Utilizamos as mais modernas tecnologias para proporcionar uma experiência de aprendizado única e envolvente.</p>
            </div>
            
            <div>
                <h3><i class="fas fa-handshake"></i> <strong>Comunidade</strong></h3>
                <p>Acreditamos no poder da comunidade e no compartilhamento de conhecimento entre profissionais.</p>
                <p>…</p>
                <h3><i class="fas fa-chart-line"></i> <strong>Crescimento</strong></h3>
                <p>Nosso objetivo é contribuir para o desenvolvimento contínuo dos profissionais da linguagem.</p>
            </div>
        </div>
    </div>

    <div class="video-card">
        <h2><i class="fas fa-envelope"></i> Entre em contato</h2>
        <p>Tem alguma dúvida ou sugestão? Estamos sempre prontos para ouvir nossa comunidade.</p>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="contato.php" class="cta-btn">
                <i class="fas fa-paper-plane"></i> Fale conosco
            </a>
        </div>
    </div>
<style>
    .video-card {
      margin-bottom: 20px; /* ou o valor que preferir */
    }
</style>
</div>


<?php include __DIR__ . '/vision/includes/footer.php'; ?>