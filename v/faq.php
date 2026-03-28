<?php
session_start();
require_once __DIR__ . '/config/database.php';

$page_title = 'FAQ - Perguntas Frequentes';
$page_description = 'Encontre respostas para as principais dúvidas sobre a plataforma Translators101.';

include __DIR__ . '/vision/includes/head.php';
include __DIR__ . '/vision/includes/header.php';
include __DIR__ . '/vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-question-circle"></i> Perguntas Frequentes</h1>
            <p>Encontre respostas rápidas para suas principais dúvidas</p>
        </div>
    </div>

    <div class="video-card">
        <h2><i class="fas fa-info-circle"></i> Dúvidas Gerais</h2>
        
        <div class="faq-section">
            <div class="faq-item">
                <h3><i class="fas fa-play"></i> Como acessar as palestras?</h3>
                <p>Após fazer login na plataforma, vá até a seção <strong>Videoteca</strong> onde você encontrará todas as palestras disponíveis. Use os filtros para encontrar conteúdo específico por categoria ou palestrante.</p>
            </div>

            <div class="faq-item">
                <h3><i class="fas fa-certificate"></i> Como obter certificados?</h3>
                <p>Os certificados são gerados automaticamente após assistir uma palestra completa. Você pode baixá-los diretamente da plataforma em formato PDF.</p>
            </div>

            <div class="faq-item">
                <h3><i class="fas fa-download"></i> Posso baixar os glossários?</h3>
                <p>Sim! Na seção <strong>Glossários</strong> você encontra materiais especializados para download gratuito. Todos os arquivos estão disponíveis em formato PDF.</p>
            </div>

            <div class="faq-item">
                <h3><i class="fas fa-mobile-alt"></i> A plataforma funciona no celular?</h3>
                <p>Sim, nossa plataforma é totalmente responsiva e funciona perfeitamente em dispositivos móveis, tablets e desktops.</p>
            </div>
        </div>
    </div>

    <div class="video-card">
        <h2><i class="fas fa-credit-card"></i> Planos e Pagamentos</h2>
        
        <div class="faq-section">
            <div class="faq-item">
                <h3><i class="fas fa-star"></i> Quais são os planos disponíveis?</h3>
                <p>Oferecemos diferentes planos de assinatura para atender suas necessidades. Visite nossa página de <a href="planos.php" style="color: var(--brand-purple);">Planos</a> para conhecer todas as opções.</p>
            </div>

            <div class="faq-item">
                <h3><i class="fas fa-sync"></i> Posso cancelar minha assinatura?</h3>
                <p>Você pode cancelar sua assinatura a qualquer momento através da sua área de usuário. O acesso permanece ativo até o final do período pago.</p>
            </div>

            <div class="faq-item">
                <h3><i class="fas fa-shield-alt"></i> Os pagamentos são seguros?</h3>
                <p>Sim, utilizamos sistemas de pagamento criptografados e seguros. Todos os dados são protegidos conforme as melhores práticas de segurança.</p>
            </div>
        </div>
    </div>

    <div class="video-card">
        <h2><i class="fas fa-tools"></i> Suporte Técnico</h2>
        
        <div class="faq-section">
            <div class="faq-item">
                <h3><i class="fas fa-exclamation-triangle"></i> Estou com problemas para assistir as palestras</h3>
                <p>Verifique sua conexão com a internet e tente atualizar a página. Se o problema persistir, entre em contato com nosso suporte através da página de <a href="contato.php" style="color: var(--brand-purple);">Contato</a>.</p>
            </div>

            <div class="faq-item">
                <h3><i class="fas fa-key"></i> Esqueci minha senha</h3>
                <p>Na página de login, clique em "Esqueci minha senha" e siga as instruções enviadas para seu email cadastrado.</p>
            </div>

            <div class="faq-item">
                <h3><i class="fas fa-user-edit"></i> Como atualizar meus dados?</h3>
                <p>Acesse sua área de usuário para atualizar informações pessoais, alterar senha e gerenciar suas preferências.</p>
            </div>
        </div>
    </div>

    <div class="video-card">
        <h2><i class="fas fa-comments"></i> Ainda tem dúvidas?</h2>
        <p>Se não encontrou a resposta que procurava, nossa equipe está pronta para ajudar!</p>
        
        <div class="quick-actions-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
            <a href="contato.php" class="quick-action-card">
                <div class="quick-action-icon quick-action-icon-blue">
                    <i class="fas fa-envelope"></i>
                </div>
                <h3>Enviar Mensagem</h3>
                <p>Entre em contato através do formulário</p>
            </a>

            <a href="mailto:suporte@translators101.com" class="quick-action-card">
                <div class="quick-action-icon quick-action-icon-green">
                    <i class="fas fa-at"></i>
                </div>
                <h3>Email Direto</h3>
                <p>suporte@translators101.com</p>
            </a>
        </div>
    </div>
</div>

<style>
.faq-section {
    margin-top: 20px;
}

.faq-item {
    padding: 20px 0;
    border-bottom: 1px solid var(--glass-border);
}

.faq-item:last-child {
    border-bottom: none;
}

.faq-item h3 {
    color: #fff;
    margin-bottom: 12px;
    font-size: 1.1rem;
}

.faq-item h3 i {
    margin-right: 10px;
    color: #daa520; /* var(--brand-purple)*/
}

.faq-item p {
    color: #ddd;
    line-height: 1.6;
}


/* ==== Ajustes de espaçamento e guias visuais no Perfil ==== */

/* Espaço interno extra nos cards */
.video-card {
  padding: 24px !important;       /* mais respiro interno */
  margin-bottom: 30px !important; /* garantir espaço entre seções */
}

/* Guia visual nos títulos */
.video-card h2,
.video-card h3 {
  border-left: 3px solid var(--brand-purple);
  padding-left: 10px;
  margin-bottom: 20px;
}

/* Inputs com mais respiro lateral */
.vision-form input,
.vision-form select,
.vision-form textarea,
.form-group input,
.form-group select,
.form-group textarea {
  padding: 12px 16px !important; /* texto afastado das bordas */
}

/* Labels com linha guia */
.vision-form label,
.form-group label {
  padding-left: 6px;
  border-left: 2px solid rgba(255,255,255,0.2);
}

/* Parágrafos de instrução também com guia */
.video-card p.text-light {
  padding-left: 8px;
  border-left: 2px solid rgba(255,255,255,0.2);
  margin-bottom: 16px;
}
</style>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>