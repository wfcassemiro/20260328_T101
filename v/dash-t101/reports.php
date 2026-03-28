<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';
require_once __DIR__ . '/../config/dash_functions.php';

// Verificar se o usuário está logado
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$page_title = 'Relatórios - Dash-T101';
$page_description = "Central de relatórios financeiros e operacionais.";

// Incluir cabeçalhos
include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">

    <div class="video-card profile-header-card" style="background: linear-gradient(135deg, var(--brand-purple), #4a148c); border: none; margin-bottom: 25px;">
        <div class="header-icon-container" style="background: rgba(255, 255, 255, 0.1); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <i class="fas fa-chart-line" style="font-size: 1.8rem; color: #fff;"></i>
        </div>
        <div class="header-text-container" style="margin-left: 20px;">
            <h2 style="margin: 0 0 5px 0; padding: 0; font-size: 1.5rem; color: #fff; font-weight: 600; border: none;">Central de Relatórios</h2>
            <p style="margin: 0; color: rgba(255, 255, 255, 0.8); font-size: 1rem;">Analise a saúde de sua empresa.</p>
        </div>
    </div>

    <div class="report-nav-buttons">
        <a href="index.php" class="vision-btn vision-btn-secondary"><i class="fas fa-home"></i> Voltar ao Dash-T101</a>
    </div>


    <div class="video-card">
        <h2><i class="fas fa-dollar-sign"></i> Relatórios Financeiros</h2>
        <div class="quick-links-grid">
            <a href="report_profit_loss.php" class="quick-link-card" title="Gerar relatório">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Demonstrativo de resultados</span>
            </a>
            
            <a href="report_client_profitability.php" class="quick-link-card" title="Gerar relatório">
                <i class="fas fa-users"></i>
                <span>Lucratividade por cliente</span>
            </a>
            
            <a href="report_service_profitability.php" class="quick-link-card" title="Gerar relatório">
                <i class="fas fa-cogs"></i>
                <span>Lucratividade por serviço</span>
            </a>
            
            <a href="report_accounts_receivable.php" class="quick-link-card" title="Gerar relatório">
                <i class="fas fa-calendar-check"></i>
                <span>Contas a receber</span>
            </a>
            <a href="report_accounts_payable.php" class="quick-link-card" title="Gerar relatório">
                <i class="fas fa-calendar-times"></i>
                <span>Contas a pagar</span>
            </a>
        </div>
    </div>

    <div class="video-card">
        <h2><i class="fas fa-tasks"></i> Relatórios Operacionais</h2>
        <div class="quick-links-grid">
            <a href="report_deadline_performance.php" class="quick-link-card" title="Gerar relatório">
                <i class="fas fa-shipping-fast"></i>
                <span>Desempenho de prazos</span>
            </a>
            <a href="report_production_volume.php" class="quick-link-card" title="Gerar relatório">
                <i class="fas fa-file-word"></i>
                <span>Volume de produção</span>
            </a>
        </div>
    </div>
    
    <div class="video-card">
        <h2><i class="fas fa-user-tag"></i> Relatórios de Fornecedores</h2>
        <div class="quick-links-grid">
            <a href="report_vendor_statement.php" class="quick-link-card" title="Gerar relatório">
                <i class="fas fa-user-clock"></i>
                <span>Extrato do fornecedor</span>
            </a>
            <a href="report_service_cost.php" class="quick-link-card" title="Gerar relatório">
                <i class="fas fa-search-dollar"></i>
                <span>Custo por serviço</span>
            </a>
        </div>
    </div>

</div>

<style>
/* Cabeçalho Roxo */
.profile-header-card {
    display: flex;
    align-items: center;
    justify-content: center; /* Centralizado */
}
.header-icon-container {
    background: rgba(255, 255, 255, 0.1); 
    border-radius: 50%; 
    width: 60px; 
    height: 60px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    flex-shrink: 0;
}
.header-icon-container i {
    font-size: 1.8rem; 
    color: #fff;
}
.header-text-container {
    margin-left: 20px;
}
.header-text-container h2 {
    margin: 0 0 5px 0; 
    padding: 0; 
    font-size: 1.5rem; 
    color: #fff; 
    font-weight: 600; 
    border: none;
}
.header-text-container p {
    margin: 0; 
    color: rgba(255, 255, 255, 0.8); 
    font-size: 1rem;
}
.main-content .video-card { margin-bottom: 20px; }
.video-card { background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.05)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; }
.video-card > h2 { margin: 0; padding: 25px 30px 20px; font-size: 1.3rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); }
.quick-links-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; padding: 20px 25px; }
.quick-link-card { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; background: rgba(255, 255, 255, 0.05); border-radius: 16px; padding: 25px; text-decoration: none; color: var(--text-secondary); transition: all 0.3s ease; border: 1px solid rgba(255, 255, 255, 0.1); }
.quick-link-card i { font-size: 1.8rem; color: #f9b42d; }
.quick-link-card span { font-size: 0.95rem; font-weight: 600; text-align: center; }
.quick-link-card:hover { background: rgba(255, 255, 255, 0.1); color: var(--text-primary); transform: translateY(-3px); box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2); }
.disabled-link {
    opacity: 0.5;
    cursor: not-allowed;
    background: rgba(0, 0, 0, 0.1);
}
.disabled-link:hover {
    transform: none;
    box-shadow: none;
    background: rgba(0, 0, 0, 0.1);
    color: var(--text-secondary);
}
body { cursor: default !important; }
a, button, label, select,
input[type="submit"], input[type="button"], input[type="reset"],
input[type="checkbox"], input[type="radio"],
[role="button"], [onclick] {
    cursor: pointer !important;
}
.vision-btn, .btn-primary, .btn-secondary,
.action-btn-refined, .add-service-btn,
.modal-close, .vision-modal-close,
.page-btn, .report-btn-highlight,
.selection-tag, .rate-item-remove,
.vision-modal-backdrop, [data-close-modal],
.quick-link-card, .filter-btn {
    cursor: pointer !important;
}

/* Botões */
.report-nav-buttons { display: flex; gap: 15px; margin-bottom: 20px; }
.vision-btn { background: var(--brand-purple); color: white; border: 1px solid var(--brand-purple); border-radius: 20px; padding: 12px 24px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); }
.vision-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3); }
.vision-btn-primary { background: var(--brand-purple); border-color: var(--brand-purple); }
.vision-btn-primary:hover { background: var(--brand-purple-dark); border-color: var(--brand-purple-dark); }
.vision-btn-secondary { background: rgba(255, 255, 255, 0.1); color: var(--text-primary); border-color: rgba(255, 255, 255, 0.2); }
.vision-btn-secondary:hover { background: rgba(255, 255, 255, 0.2) !important; border-color: rgba(255, 255, 255, 0.3) !important; color: #fff !important; }


</style>

<?php
include __DIR__ . '/../vision/includes/footer.php';
?>