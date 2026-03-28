<?php
session_start();
require_once __DIR__ . '/config/database.php';

$page_title = 'Planos e Preços';
$page_description = 'Escolha o plano ideal para sua carreira. Acesso a mais de 350 palestras especializadas e com direito a certificados.';

$redirect = $_GET['redirect'] ?? '';

include __DIR__ . '/vision/includes/head.php';
?>

<?php include __DIR__ . '/vision/includes/header.php'; ?>

<?php include __DIR__ . '/vision/includes/sidebar.php'; ?>

<main class="main-content">
    <!-- Hero Section -->
    <section class="glass-hero">
        <h1>💼 Planos e Preços</h1>
        <p>Escolha o plano que melhor se adapta a suas necessidades e acelere sua carreira como profissional de tradução, interpretação ou revisão.</p>
        
        <?php if ($redirect): ?>
            <div class="alert-warning" style="margin-top: 25px; max-width: 500px; margin-left: auto; margin-right: auto;">
                <i class="fas fa-lock"></i>
                É necessário ser assinante para acessar este conteúdo.
            </div>
        <?php endif; ?>
    </section>
    
    <!-- Planos -->
    <section>
        <div class="video-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px;">
            <?php
            $plans = [
                [
                    'name' => 'Mensal',
                    'price' => 'R$ 53',
                    'period' => '/mês',
                    'savings' => 'Muito barato',
                    'description' => 'Ideal para começar',
                    'hotmart_link' => 'https://pay.hotmart.com/V94273047M?off=i1hvrpr2&checkoutMode=10',
                    'popular' => false
                ],
                [
                    'name' => 'Trimestral',
                    'price' => 'R$ 150',
                    'period' => '/3 meses',
                    'savings' => 'Economize R$ 9',
                    'description' => 'Melhor custo-benefício',
                    'hotmart_link' => 'https://pay.hotmart.com/V94273047M?off=whfa869v&checkoutMode=10',
                    'popular' => true
                ],
                [
                    'name' => 'Semestral',
                    'price' => 'R$ 285',
                    'period' => '/6 meses',
                    'savings' => 'Economize R$ 33',
                    'description' => 'Para estudos contínuos',
                    'hotmart_link' => 'https://pay.hotmart.com/V94273047M?off=qh0m3cuy&checkoutMode=10',
                    'popular' => false
                ],
                [
                    'name' => 'Anual',
                    'price' => 'R$ 550',
                    'period' => '/ano',
                    'savings' => 'Economize R$ 86',
                    'description' => 'Máxima economia',
                    'hotmart_link' => 'https://pay.hotmart.com/V94273047M?off=cor1dwtx&checkoutMode=10',
                    'popular' => false
                ]
            ];
            
            foreach($plans as $plan):
            ?>
                <div class="video-card fade-item" style="position: relative; <?php echo $plan['popular'] ? 'border: 2px solid var(--brand-purple);' : ''; ?>">
                    <?php if ($plan['popular']): ?>
                        <div class="badge-new" style="top: -10px; left: 50%; transform: translateX(-50%); background: var(--brand-purple);">
                            <i class="fas fa-star"></i> Mais Popular
                        </div>
                    <?php endif; ?>
                    
                    <div class="video-info" style="text-align: center;">
                        <h3 style="font-size: 1.3rem; margin-bottom: 10px;"><?php echo $plan['name']; ?></h3>
                        <p style="color: #ccc; margin-bottom: 20px;"><?php echo $plan['description']; ?></p>
                        
                        <div style="margin-bottom: 25px;">
                            <div style="font-size: 2.2rem; font-weight: bold; color: var(--brand-purple); margin-bottom: 5px;">
                                <?php echo $plan['price']; ?>
                            </div>
                            <div style="color: #ccc; margin-bottom: 10px;"><?php echo $plan['period']; ?></div>
                            <?php if ($plan['savings']): ?>
                                <div class="tag" style="background: rgba(46, 204, 113, 0.25); color: #2ecc71; font-weight: 600;">
                                    <?php echo $plan['savings']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <a href="<?php echo $plan['hotmart_link']; ?>" 
                           class="cta-btn" 
                           style="width: 100%; margin-bottom: 20px;"
                           target="_blank">
                            <i class="fas fa-credit-card"></i> Assinar
                        </a>
                        
                        <!-- Benefícios inclusos -->
                        <div style="text-align: left; color: #ddd; font-size: 0.9rem;">
                            <p style="font-weight: 600; margin-bottom: 10px; text-align: center;">Incluso no plano:</p>
                            <ul style="list-style: none; padding: 0; space-y: 5px;">
                                <li><i class="fas fa-check" style="color: #2ecc71; margin-right: 8px;"></i>Acesso a todas as palestras</li>
                                <li><i class="fas fa-check" style="color: #2ecc71; margin-right: 8px;"></i>Certificados automáticos</li>
                                <li><i class="fas fa-check" style="color: #2ecc71; margin-right: 8px;"></i>Suporte especializado</li>
                                <li><i class="fas fa-check" style="color: #2ecc71; margin-right: 8px;"></i>Cancelamento fácil</li>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    
    <!-- Benefícios Gerais -->
    <section class="vision-form" style="margin-top: 60px;">
        <h2 style="font-size: 2rem; text-align: center; margin-bottom: 40px;">O que você ganha com a assinatura</h2>
        
        <div class="video-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
            <div class="video-card fade-item">
                <div class="video-info" style="text-align: center;">
                    <div style="font-size: 3rem; margin-bottom: 15px;">🎥</div>
                    <h3 style="margin-bottom: 15px;">Quase 400 Palestras</h3>
                    <p class="video-desc">
                        Acesso completo ao nosso extenso catálogo de palestras especializadas.
                    </p>
                </div>
            </div>
            
            <div class="video-card fade-item">
                <div class="video-info" style="text-align: center;">
                    <div style="font-size: 3rem; margin-bottom: 15px;">📜</div>
                    <h3 style="margin-bottom: 15px;">Certificados Automáticos</h3>
                    <p class="video-desc">
                        Receba certificados em PDF automaticamente após assistir às palestras.
                    </p>
                </div>
            </div>
            
            <div class="video-card fade-item">
                <div class="video-info" style="text-align: center;">
                    <div style="font-size: 3rem; margin-bottom: 15px;">🔄</div>
                    <h3 style="margin-bottom: 15px;">Novas palestras toda semana</h3>
                    <p class="video-desc">
                        Conteúdo novo toda semana para manter você sempre atualizado.
                    </p>
                </div>
            </div>
            
            <div class="video-card fade-item">
                <div class="video-info" style="text-align: center;">
                    <div style="font-size: 3rem; margin-bottom: 15px;">📱</div>
                    <h3 style="margin-bottom: 15px;">Acesso Multiplataforma</h3>
                    <p class="video-desc">
                        Assista em qualquer dispositivo, a qualquer hora e lugar.
                    </p>
                </div>
            </div>
            
            <div class="video-card fade-item">
                <div class="video-info" style="text-align: center;">
                    <div style="font-size: 3rem; margin-bottom: 15px;">🎯</div>
                    <h3 style="margin-bottom: 15px;">Conteúdo Especializado</h3>
                    <p class="video-desc">
                        Focado exclusivamente em tradução, interpretação e revisão.
                    </p>
                </div>
            </div>
            
            <div class="video-card fade-item">
                <div class="video-info" style="text-align: center;">
                    <div style="font-size: 3rem; margin-bottom: 15px;">❌</div>
                    <h3 style="margin-bottom: 15px;">Cancelamento Fácil</h3>
                    <p class="video-desc">
                        Cancele sua assinatura a qualquer momento, sem complicações.
                    </p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Depoimentos -->
    <section style="margin-top: 60px;">
        <h2 style="font-size: 2rem; text-align: center; margin-bottom: 40px;">O que os assinantes dizem</h2>
        
        <div class="video-grid" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));">
            <div class="video-card fade-item">
                <div class="video-info">
                    <div style="display: flex; align-items: center; margin-bottom: 15px;">
                        <div style="width: 50px; height: 50px; background: var(--brand-purple); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                            <span style="color: white; font-weight: bold;">MC</span>
                        </div>
                        <div>
                            <h4 style="font-weight: 600; margin-bottom: 5px;">Maria Clara</h4>
                            <p style="color: #ccc; font-size: 0.9rem;">Tradutora Juramentada</p>
                        </div>
                    </div>
                    <p class="video-desc">
                        "As palestras são excelentes e me ajudaram muito a aprimorar minhas técnicas 
                        de tradução. O certificado automático é um diferencial."
                    </p>
                    <div style="display: flex; color: #f1c40f; margin-top: 15px;">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                </div>
            </div>
            
            <div class="video-card fade-item">
                <div class="video-info">
                    <div style="display: flex; align-items: center; margin-bottom: 15px;">
                        <div style="width: 50px; height: 50px; background: var(--brand-purple); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                            <span style="color: white; font-weight: bold;">JS</span>
                        </div>
                        <div>
                            <h4 style="font-weight: 600; margin-bottom: 5px;">João Silva</h4>
                            <p style="color: #ccc; font-size: 0.9rem;">Intérprete Simultâneo</p>
                        </div>
                    </div>
                    <p class="video-desc">
                        "Conteúdo de altíssima qualidade com palestrantes renomados. 
                        Indispensável para quem quer se manter atualizado no mercado."
                    </p>
                    <div style="display: flex; color: #f1c40f; margin-top: 15px;">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                </div>
            </div>
            
            <div class="video-card fade-item">
                <div class="video-info">
                    <div style="display: flex; align-items: center; margin-bottom: 15px;">
                        <div style="width: 50px; height: 50px; background: var(--brand-purple); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                            <span style="color: white; font-weight: bold;">AF</span>
                        </div>
                        <div>
                            <h4 style="font-weight: 600; margin-bottom: 5px;">Ana Fernandes</h4>
                            <p style="color: #ccc; font-size: 0.9rem;">Revisora Técnica</p>
                        </div>
                    </div>
                    <p class="video-desc">
                        "A plataforma é muito fácil de usar e o preço é baixíssimo. 
                        Já assisti dezenas de palestras e todas agregaram muito conhecimento."
                    </p>
                    <div style="display: flex; color: #f1c40f; margin-top: 15px;">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Call to Action Final -->
    <section class="glass-hero" style="margin-top: 60px;">
        <h2 style="font-size: 2rem;">Vamos acelerar sua carreira?</h2>
        <p>Junte-se a mais de 1.500 profissionais que já transformaram suas carreiras com a Translators101.</p>
        <div style="margin-top: 30px;">
            <a href="#planos" onclick="document.querySelector('.video-grid').scrollIntoView({behavior: 'smooth'})" 
               class="cta-btn" style="background: white; color: var(--brand-purple);">
                <i class="fas fa-rocket"></i> Escolher Plano
            </a>
        </div>
    </section>
</main>

<style>
/* Ajustes específicos para planos */
.video-card ul li {
    margin-bottom: 8px;
}

.badge-new {
    font-size: 0.8rem;
    padding: 6px 12px;
}

@media (max-width: 768px) {
    .video-grid {
        grid-template-columns: 1fr !important;
    }
    
    .glass-hero h1, .glass-hero h2 {
        font-size: 1.8rem;
    }
}
</style>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>
