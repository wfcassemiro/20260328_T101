<?php
// Configurações da página
$page_title = 'Translators101 - Educação Continuada para Tradutores';
$page_description = 'Plataforma de streaming educacional para profissionais de tradução, interpretação e revisão. Quase 400 palestras especializadas.';

// Inclui o arquivo de funções de autenticação e conexão com o banco de dados
require_once __DIR__ . '/config/database.php';

// Inclui o cabeçalho Vision
include __DIR__ . '/vision/includes/head.php';
?>

<?php include __DIR__ . '/vision/includes/header.php'; ?>

<?php include __DIR__ . '/vision/includes/sidebar.php'; ?>

<main class="main-content">
    <!-- Hero Section -->
    <section class="glass-hero fade-item">
        <h1>Transforme sua Carreira na Tradução</h1>
        <p>Acesse nossa plataforma com quase 400 palestras especializadas em tradução, interpretação e revisão. Conteúdo de alta qualidade com certificados automáticos.</p>
        <a href="planos.php" class="cta-btn">
            <i class="fa-solid fa-rocket"></i> Comece Agora
        </a>
    </section>

    <!-- Seção de Recursos -->
    <section class="fade-item">
        <h2 style="text-align: center; margin-bottom: 40px; font-size: 2.5rem;">Por que escolher a Translators101?</h2>
        
        <div class="video-grid">
            <div class="video-card fade-item">
                <div class="video-info">
                    <div style="text-align: center; font-size: 3rem; margin-bottom: 15px;">🎥</div>
                    <h3>Quase 400 Palestras</h3>
                    <p class="video-desc">Acesso completo ao nosso extenso catálogo de palestras especializadas em tradução, interpretação e revisão.</p>
                </div>
            </div>

            <div class="video-card fade-item">
                <div class="video-info">
                    <div style="text-align: center; font-size: 3rem; margin-bottom: 15px;">📜</div>
                    <h3>Certificados Automáticos</h3>
                    <p class="video-desc">Receba certificados em PDF automaticamente após assistir às palestras completas.</p>
                </div>
            </div>

            <div class="video-card fade-item">
                <div class="video-info">
                    <div style="text-align: center; font-size: 3rem; margin-bottom: 15px;">🔄</div>
                    <h3>Conteúdo Atualizado</h3>
                    <p class="video-desc">Novas palestras adicionadas toda semana para manter você sempre atualizado com as tendências do mercado.</p>
                </div>
            </div>

            <div class="video-card fade-item">
                <div class="video-info">
                    <div style="text-align: center; font-size: 3rem; margin-bottom: 15px;">📱</div>
                    <h3>Acesso Multiplataforma</h3>
                    <p class="video-desc">Assista em qualquer dispositivo, a qualquer hora e lugar. Desktop, tablet ou smartphone.</p>
                </div>
            </div>

            <div class="video-card fade-item">
                <div class="video-info">
                    <div style="text-align: center; font-size: 3rem; margin-bottom: 15px;">🎯</div>
                    <h3>Especializado</h3>
                    <p class="video-desc">Conteúdo focado exclusivamente em tradução, interpretação e revisão por profissionais renomados.</p>
                </div>
            </div>

            <div class="video-card fade-item">
                <div class="video-info">
                    <div style="text-align: center; font-size: 3rem; margin-bottom: 15px;">💰</div>
                    <h3>Preço Acessível</h3>
                    <p class="video-desc">A partir de R$ 53/mês. O melhor custo-benefício do mercado para educação continuada.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Final -->
    <section class="glass-hero fade-item" style="margin-top: 60px;">
        <h2 style="font-size: 2.5rem;">Pronto para acelerar sua carreira?</h2>
        <p>Junte-se a mais de 1.500 profissionais que já transformaram suas carreiras com a Translators101.</p>
        <div style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
            <a href="planos.php" class="cta-btn">
                <i class="fa-solid fa-star"></i> Ver Planos
            </a>
            <a href="videoteca.php" class="cta-btn" style="background: rgba(255,255,255,0.1);">
                <i class="fa-solid fa-play"></i> Explorar Palestras
            </a>
        </div>
    </section>
</main>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>

<style>
/* Ajustes específicos para a home */
.video-grid {
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-bottom: 40px;
}

@media (max-width: 768px) {
    .video-grid {
        grid-template-columns: 1fr;
    }
    
    .glass-hero h1 {
        font-size: 2.2rem;
    }
    
    .glass-hero h2 {
        font-size: 2rem;
    }
}
</style>

<?php
// URL da Landing Page da Hotmart como fallback
$hotmart_lp_url = 'https://hotm.art/t101';
?>
