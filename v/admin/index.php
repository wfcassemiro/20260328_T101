<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

$page_title = 'Painel administrativo - Translators101';
$page_description = 'Administração da plataforma Translators101';

$message = ''; $message_type = '';
if (isset($_SESSION['admin_message'])) { $message = $_SESSION['admin_message']; $message_type = 'success'; unset($_SESSION['admin_message']); }
if (isset($_SESSION['admin_error'])) { $message = $_SESSION['admin_error']; $message_type = 'error'; unset($_SESSION['admin_error']); }

try {
    $total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
    $total_lectures = $pdo->query("SELECT COUNT(*) FROM lectures")->fetchColumn();
    $total_certificates = $pdo->query("SELECT COUNT(*) FROM certificates")->fetchColumn();
    $total_glossaries = $pdo->query("SELECT COUNT(*) FROM glossary_files")->fetchColumn();
    $total_signups = $pdo->query("SELECT COUNT(*) FROM course_signups")->fetchColumn();
    $total_courses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    $total_leads = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
    
    try {
        $total_leads_fundo = $pdo->query("SELECT COUNT(*) FROM leads_fundo")->fetchColumn();
    } catch (PDOException $e) {
        $total_leads_fundo = 0;
    }
} catch (PDOException $e) {
    $total_users = $total_lectures = $total_certificates = $total_glossaries = $total_signups = $total_courses = $total_leads = $total_leads_fundo = 0;
    error_log("Erro ao buscar estatísticas: " . $e->getMessage());
}
$total_users_formatted = number_format($total_users);
$total_lectures_formatted = number_format($total_lectures);
$total_certificates_formatted = number_format($total_certificates);
$total_glossaries_formatted = number_format($total_glossaries);
$total_signups_formatted = number_format($total_signups);
$total_courses_formatted = number_format($total_courses);
$total_leads_formatted = number_format($total_leads);
$total_leads_fundo_formatted = number_format($total_leads_fundo);

include __DIR__ . '/../vision/includes/head.php';
?>
<style>
    .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(280px, 1fr));
        gap: 20px;
        margin-top: 20px;
        margin-bottom: 30px;
        padding: 0 20px;
    }
    @media (max-width: 1200px) { .quick-actions-grid { grid-template-columns: repeat(2, minmax(280px, 1fr)); } }
    @media (max-width: 700px) { .quick-actions-grid { grid-template-columns: 1fr; } }

    .quick-action-card {
        text-decoration: none;
        color: inherit;
        padding: 20px 25px;
        background: rgba(255, 255, 255, .05);
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, .1);
        transition: transform .3s ease, background .3s ease, box-shadow .3s ease;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .quick-action-card:hover {
        transform: translateY(-5px);
        background: rgba(255, 255, 255, .1);
        box-shadow: 0 8px 25px rgba(0, 0, 0, .3);
    }
    .card-info { display: flex; align-items: center; gap: 18px; width: 100%; }
    .card-content { flex-grow: 1; }
    
    .quick-action-icon {
        font-size: 28px;
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        color: #fff;
        flex-shrink: 0;
    }
    
    .quick-action-card h3 { margin: 0 0 5px; font-size: 1.15em; font-weight: 700; color: #fff; }
    .quick-action-card p { margin: 0; font-size: .9em; color: rgba(255, 255, 255, .7); line-height: 1.4; }
    .card-stat { font-size: 2.2em; font-weight: 700; color: #fff; opacity: .9; margin-left: auto; padding-left: 15px; }

    .quick-action-icon-blue { background: linear-gradient(135deg, #007AFF, #0056CC); }
    .quick-action-icon-purple { background: linear-gradient(135deg, #AF52DE, #8A2BE2); }
    .quick-action-icon-green { background: linear-gradient(135deg, #34C759, #28A745); }
    .quick-action-icon-red { background: linear-gradient(135deg, #FF3B30, #DC3545); }
    .quick-action-icon-gold { background: linear-gradient(135deg, #f39c12, #e67e22); }
    .quick-action-icon-orange { background: linear-gradient(135deg, #e67e22, #d35400); }
    .quick-action-icon-teal { background: linear-gradient(135deg, #17a2b8, #138496); }
    .quick-action-icon-pink { background: linear-gradient(135deg, #ec4899, #db2777); }
    .quick-action-icon-ai { background: linear-gradient(135deg, #6366f1, #4f46e5); }
    /* Estilo para o Agente 3 */
    .quick-action-icon-radar { background: linear-gradient(135deg, #ff7e5f, #feb47b); }

    .cta-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 24px;
        font-size: 1rem;
        font-weight: bold;
        border-radius: 8px;
        background: #8e44ad;
        color: #fff;
        text-decoration: none;
        border: none;
        cursor: pointer;
        width: 100%;
        margin-top: 15px;
        transition: background 0.3s, transform 0.2s;
    }
    .cta-btn:hover { background: #5e3370; transform: translateY(-2px); }
    .cta-btn:disabled { background: #555; cursor: not-allowed; transform: none; }

    .message-card { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: none; }
    .success-message { background: linear-gradient(135deg, rgba(52, 199, 89, .2), rgba(52, 199, 89, .1)); border: 1px solid rgba(52, 199, 89, .3); color: #34C759; }
    .error-message { background: linear-gradient(135deg, rgba(255, 59, 48, .2), rgba(255, 59, 48, .1)); border: 1px solid rgba(255, 59, 48, .3); color: #FF3B30; }
    .message-card p { margin: 0; display: flex; align-items: center; gap: 10px; }
    
    hr { border: 0; height: 1px; background-color: rgba(255, 255, 255, .1); margin: 30px 20px; }
    .video-card h2 { padding-left: 20px; margin-top: 30px; }
    
    .progress-bar-container { width: 100%; background-color: #333; border-radius: 5px; overflow: hidden; height: 10px; margin-top: 10px; display: none; }
    .progress-bar { width: 0%; height: 100%; background-color: #28a745; transition: width .5s ease-in-out; }
    .sync-status { font-size: 0.85em; color: rgba(255, 255, 255, 0.7); margin-top: 5px; height: 20px; text-align: center; }
</style>

<?php
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content"><h1><i class="fas fa-cogs"></i> Painel administrativo</h1><p>Gerencie todos os aspectos da plataforma Translators101</p></div>
    </div>
    
    <div id="notification-area" class="message-card" style="margin: 20px;"></div>

    <div class="video-card">
        <h2><i class="fas fa-tools"></i> Gerenciamento geral</h2>
        <div class="quick-actions-grid">
            <a href="processar_palestra.php" class="quick-action-card" style="border: 1px solid rgba(99, 102, 241, 0.5);">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-ai"><i class="fas fa-robot"></i></div>
                    <div class="card-content"><h3>Gerador de Conteúdo</h3><p>IA: Insights de Palestras</p></div>
                </div>
            </a>
            
            <a href="agente_leads.php" class="quick-action-card" style="border: 1px solid rgba(175, 82, 222, 0.5);">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-ai"><i class="fas fa-users-viewfinder"></i></div>
                    <div class="card-content"><h3>Prospector de Leads</h3><p>IA: Abordagens B2C</p></div>
                </div>
            </a>

            <a href="radar_mercado.php" class="quick-action-card" style="border: 1px solid rgba(255, 126, 95, 0.5);">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-radar"><i class="fas fa-satellite-dish"></i></div>
                    <div class="card-content"><h3>Radar T101</h3><p>IA: Curadoria de Mercado</p></div>
                </div>
            </a>

            <a href="usuarios.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-blue"><i class="fas fa-users-cog"></i></div>
                    <div class="card-content"><h3>Usuários</h3><p>Gerenciar assinantes</p></div>
                </div>
                <span class="card-stat"><?php echo $total_users_formatted; ?></span>
            </a>
            <a href="palestras.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-purple"><i class="fas fa-video"></i></div>
                    <div class="card-content"><h3>Palestras</h3><p>Adicionar e editar</p></div>
                </div>
                <span class="card-stat"><?php echo $total_lectures_formatted; ?></span>
            </a>
            <a href="certificados.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-green"><i class="fas fa-certificate"></i></div>
                    <div class="card-content"><h3>Certificados</h3><p>Gerar e validar</p></div>
                </div>
                <span class="card-stat"><?php echo $total_certificates_formatted; ?></span>
            </a>
            <a href="certificados_palestrantes.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-teal"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="card-content"><h3>Palestrantes</h3><p>Emitir certificados</p></div>
                </div>
            </a>
            <a href="interesse_cursos.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-gold"><i class="fas fa-user-graduate"></i></div>
                    <div class="card-content"><h3>Interessados</h3><p>Ver lista de leads</p></div>
                </div>
                <span class="card-stat"><?php echo $total_signups_formatted; ?></span>
            </a>
            <a href="gerenciar_cursos.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-orange"><i class="fas fa-graduation-cap"></i></div>
                    <div class="card-content"><h3>Cursos</h3><p>Adicionar e editar</p></div>
                </div>
                <span class="card-stat"><?php echo $total_courses_formatted; ?></span>
            </a>
            <a href="emails.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-blue"><i class="fas fa-envelope"></i></div>
                    <div class="card-content"><h3>E-mails</h3><p>Comunicação interna</p></div>
                </div>
            </a>
            <a href="leads.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-pink"><i class="fas fa-magnet"></i></div>
                    <div class="card-content"><h3>Leads</h3><p>Captura da agenda</p></div>
                </div>
                <span class="card-stat"><?php echo $total_leads_formatted; ?></span>
            </a>
            <a href="leads_fundo.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-orange"><i class="fas fa-funnel-dollar"></i></div>
                    <div class="card-content"><h3>Leads Fundo</h3><p>Trials expirados</p></div>
                </div>
                <span class="card-stat"><?php echo $total_leads_fundo_formatted; ?></span>
            </a>
            <a href="logs.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-red"><i class="fas fa-list-alt"></i></div>
                    <div class="card-content"><h3>Logs do sistema</h3><p>Auditoria de atividades</p></div>
                </div>
            </a>
            <a href="gerenciar_senhas.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-purple"><i class="fas fa-key"></i></div>
                    <div class="card-content"><h3>Gerenciar senhas</h3><p>Enviar links de definição</p></div>
                </div>
            </a>
        </div>
        
        <hr>
        <h2><i class="fas fa-sync-alt"></i> Sincronização Hotmart</h2>
        <div class="quick-actions-grid">
            <div id="sync-card" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-green"><i id="sync-icon" class="fas fa-sync"></i></div>
                    <div class="card-content">
                        <h3 id="sync-title">Sincronizar progresso</h3>
                        <p id="sync-desc">Certificados da Hotmart.</p>
                        <div class="progress-bar-container" id="progress-container">
                            <div class="progress-bar" id="progress-bar"></div>
                        </div>
                        <div class="sync-status" id="sync-status"></div>
                    </div>
                </div>
                <button id="sync-button" class="cta-btn" onclick="startSync()">Iniciar sincronização</button>
            </div>
            <a href="map_lectures_interface.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-orange"><i class="fas fa-link"></i></div>
                    <div class="card-content"><h3>Mapeamento Hotmart</h3><p>Associar palestras</p></div>
                </div>
            </a>
            <a href="verificar_certificados_mapeados.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-purple"><i class="fas fa-certificate"></i></div>
                    <div class="card-content"><h3>Verificar certificados</h3><p>Monitorar certificados</p></div>
                </div>
            </a>
        </div>
        
        <hr>
        <h2><i class="fas fa-cog"></i> Configurações</h2>
        <div class="quick-actions-grid">
            <a href="palestras_agendadas.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-blue"><i class="fas fa-calendar-alt"></i></div>
                    <div class="card-content"><h3>Palestras agendadas</h3><p>Gerenciar agenda</p></div>
                </div>
            </a>
            <a href="glossarios.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-teal"><i class="fas fa-book"></i></div>
                    <div class="card-content"><h3>Glossários</h3><p>Gerenciar glossários</p></div>
                </div>
            </a>
            <a href="admin-live-settings.php" class="quick-action-card">
                <div class="card-info">
                    <div class="quick-action-icon quick-action-icon-red"><i class="fas fa-broadcast-tower"></i></div>
                    <div class="card-content"><h3>Configurar live</h3><p>Gerenciar transmissão</p></div>
                </div>
            </a>
        </div>
    </div>
</div>

<script>
function startSync() {
    const button = document.getElementById('sync-button');
    const icon = document.getElementById('sync-icon');
    const title = document.getElementById('sync-title');
    const desc = document.getElementById('sync-desc');
    const status = document.getElementById('sync-status');
    const progressContainer = document.getElementById('progress-container');
    const progressBar = document.getElementById('progress-bar');
    const notificationArea = document.getElementById('notification-area');

    button.disabled = true;
    button.innerText = "Sincronizando...";
    icon.className = 'fas fa-spinner fa-spin';
    title.innerText = "Em andamento...";
    desc.innerText = "O processo pode levar vários minutos.";
    notificationArea.style.display = 'none';
    progressContainer.style.display = 'block';
    progressBar.style.width = '0%';
    status.innerText = 'Iniciando...';

    const eventSource = new EventSource('sync_hotmart_progress.php');

    eventSource.onmessage = function(event) {
        const data = JSON.parse(event.data);

        if (data.type === 'progress') {
            progressBar.style.width = data.progress + '%';
            status.innerText = data.message;
        } else if (data.type === 'info') {
            status.innerText = data.message;
        } else if (data.type === 'done' || data.type === 'error') {
            notificationArea.innerHTML = `<p><i class="fas fa-${data.type === 'done' ? 'check-circle' : 'exclamation-triangle'}"></i> ${data.message}</p>`;
            notificationArea.className = `message-card ${data.type === 'done' ? 'success-message' : 'error-message'}`;
            notificationArea.style.display = 'block';
            
            button.disabled = false;
            button.innerText = "Iniciar sincronização";
            icon.className = 'fas fa-sync';
            title.innerText = "Sincronizar progresso";
            desc.innerText = "Sincronizar certificados da Hotmart.";
            status.innerText = data.type === 'done' ? "Concluído!" : "Falhou.";
            progressBar.style.width = data.type === 'done' ? '100%' : progressBar.style.width;

            if(data.type === 'done') setTimeout(() => { window.location.reload(); }, 4000);
            
            eventSource.close();
        }
    };

    eventSource.onerror = function() {
        notificationArea.innerHTML = '<p><i class="fas fa-exclamation-triangle"></i> Erro de conexão. A sincronização parou.</p>';
        notificationArea.className = 'message-card error-message';
        notificationArea.style.display = 'block';
        eventSource.close();
        
        button.disabled = false;
        button.innerText = "Tentar novamente";
        icon.className = 'fas fa-sync';
        title.innerText = "Sincronização falhou";
        status.innerText = "A conexão foi perdida.";
    };
}
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>