<?php
session_start();

// Define o fuso horário para o de São Paulo (GMT -3)
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/trial_functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Permitir acesso a trials também
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['subscriber', 'admin', 'trial_7d', 'trial_24h'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// --- LÓGICA PARA ATUALIZAR O PERFIL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_personal_info'])) {
        try {
            $name = $_POST['name'] ?? '';
            $email = $_POST['email'] ?? '';
            $native_language = $_POST['native_language'] ?? null;
            $working_languages = $_POST['working_languages'] ?? null;
            $does_version = isset($_POST['does_version']) ? 1 : 0;

            $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, native_language = ?, working_languages = ?, does_version = ? WHERE id = ?');
            $stmt->execute([$name, $email, $native_language, $working_languages, $does_version, $user_id]);
            $message = 'Informações atualizadas!';
        } catch (Exception $e) {
            $error = 'Erro ao atualizar as informações.';
        }
    }
}

try {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) {
        header('Location: logout.php');
        exit;
    }
    
    // Verificar status do trial se aplicável
    $trialInfo = null;
    if (in_array($user['role'], ['trial_7d', 'trial_24h'])) {
        $user = checkAndUpdateTrialStatus($pdo, $user);
        $trialInfo = getTrialStatusInfo($pdo, $user);
    }
    
} catch (Exception $e) {
    $error = 'Erro ao carregar dados do perfil.';
}

$certificates_stats = [];
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_certificates, SUM(duration_hours) as total_hours FROM certificates WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $certificates_stats = $stmt->fetch();
} catch (Exception $e) {
    $certificates_stats = ['total_certificates' => 0, 'total_hours' => 0];
}

$user_watchlist = [];
try {
    $query = "SELECT w.id as watchlist_id, w.added_at, l.id as lecture_id, l.title, l.speaker, l.duration_minutes
    FROM user_watchlist w
    JOIN lectures l ON w.lecture_id = l.id
    WHERE w.user_id = ?
    ORDER BY w.added_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $user_watchlist = $stmt->fetchAll();
} catch (Exception $e) {
    $user_watchlist = [];
}

$user_certificates = [];
try {
    $stmt = $pdo->prepare("SELECT c.*, l.title as lecture_title, l.speaker as speaker_name, l.duration_minutes
    FROM certificates c
    LEFT JOIN lectures l ON c.lecture_id = l.id
    WHERE c.user_id = ?
    ORDER BY c.issued_at DESC");
    $stmt->execute([$user_id]);
    $user_certificates = $stmt->fetchAll();
} catch (Exception $e) {}

$user_reports = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM dash_reports WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $user_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $user_reports = [];
}

function cleanTitle($title) {
    $parts = explode('—', $title);
    if (count($parts) > 2) {
        array_pop($parts);
        return trim(implode('—', $parts));
    }
    return trim($title);
}

$page_title = 'Meu Perfil - Translators101';
$page_description = 'Gerencie suas informações pessoais e configurações de conta';

include __DIR__ . '/vision/includes/head.php';
include __DIR__ . '/vision/includes/header.php';
include __DIR__ . '/vision/includes/sidebar.php';
?>

<link rel="stylesheet" href="/vision/css/forms.css">

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-user-circle"></i> Meu perfil</h1>
            <p>Gerencie suas informações pessoais e configurações de conta</p>
        </div>
    </div>

    <?php if ($message): ?><div class="alert-success"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert-error"><i class="fas fa-exclamation-triangle"></i><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <?php if ($trialInfo): ?>
    <div class="video-card trial-status-card <?php echo $trialInfo['type']; ?>">
        <div class="card-header">
            <h2>
                <?php if ($trialInfo['type'] === 'trial_7d'): ?>
                    <i class="fas fa-calendar-week"></i> Seu acesso de 7 dias
                <?php else: ?>
                    <i class="fas fa-clock"></i> Sua degustação
                <?php endif; ?>
            </h2>
        </div>
        
        <div class="trial-info-grid">
            <div class="trial-stat">
                <div class="trial-stat-icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="trial-stat-content">
                    <span class="trial-stat-label">Tempo restante</span>
                    <span class="trial-stat-value <?php echo isset($trialInfo['expired']) && $trialInfo['expired'] ? 'expired' : ''; ?>">
                        <?php echo htmlspecialchars($trialInfo['time_remaining']); ?>
                    </span>
                </div>
            </div>
            
            <?php if ($trialInfo['type'] === 'trial_24h'): ?>
            <div class="trial-stat">
                <div class="trial-stat-icon">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="trial-stat-content">
                    <span class="trial-stat-label">Palestras restantes</span>
                    <span class="trial-stat-value"><?php echo $trialInfo['lectures_remaining']; ?> de 3</span>
                </div>
            </div>
            
            <div class="trial-stat">
                <div class="trial-stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="trial-stat-content">
                    <span class="trial-stat-label">Palestras assistidas</span>
                    <span class="trial-stat-value"><?php echo $trialInfo['lectures_watched']; ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($trialInfo['started'] && isset($trialInfo['expires_at'])): ?>
            <div class="trial-stat">
                <div class="trial-stat-icon">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div class="trial-stat-content">
                    <span class="trial-stat-label">Expira em</span>
                    <span class="trial-stat-value"><?php echo htmlspecialchars($trialInfo['expires_at']); ?></span>
                </div>
            </div>
            <?php elseif (!$trialInfo['started']): ?>
            <div class="trial-stat full-width">
                <div class="trial-stat-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="trial-stat-content">
                    <span class="trial-stat-label">Informação</span>
                    <span class="trial-stat-value info"><?php echo htmlspecialchars($trialInfo['message'] ?? 'O contador começará quando você acessar o primeiro conteúdo.'); ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (isset($trialInfo['expired']) && $trialInfo['expired']): ?>
        <div class="trial-expired-message">
            <i class="fas fa-exclamation-triangle"></i>
            <p>Seu período de acesso expirou. Para continuar assistindo às palestras, assine o plano completo.</p>
            <a href="/planos.php" class="cta-btn">
                <i class="fas fa-star"></i> Ver planos de assinatura
            </a>
        </div>
        <?php else: ?>
        <div class="trial-cta">
            <p>Gostando do conteúdo? Assine para ter acesso ilimitado!</p>
            <a href="/planos.php" class="cta-btn">
                <i class="fas fa-star"></i> Assinar agora
            </a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="video-card profile-card">
        <div class="card-header"><h2><i class="fas fa-list-ul"></i> Minha lista</h2></div>
        <?php if (!empty($user_watchlist)): ?>
            <div class="list-header"><span>Palestra</span><span>Palestrante</span><span>Duração</span><span>Ações</span></div>
            <?php foreach ($user_watchlist as $item): ?>
                <div class="list-row" data-lecture-id="<?php echo htmlspecialchars($item['lecture_id']); ?>">
                    <span><?php echo htmlspecialchars(cleanTitle($item['title'])); ?></span>
                    <span><?php echo htmlspecialchars($item['speaker']); ?></span>
                    <span><?php echo htmlspecialchars($item['duration_minutes']); ?> min</span>
                    <span class="col-actions">
                        <a href="/palestra.php?id=<?php echo htmlspecialchars($item['lecture_id']); ?>" class="cta-btn btn-small">Assistir</a>
                        <button class="cta-btn btn-small btn-remove" onclick="removeFromWatchlist('<?php echo htmlspecialchars($item['lecture_id']); ?>', this)">Remover</button>
                    </span>
                </div>
            <?php endforeach; ?>
        <?php else: ?><p class="text-light" style="padding: 15px;">Sua lista está vazia.</p><?php endif; ?>
    </div>

    <div class="video-card profile-card">
        <div class="card-header"><h2><i class="fas fa-list-alt"></i> Palestras assistidas</h2></div>
        <div class="stats-report-summary">
            <span>Certificados: <?php echo $certificates_stats['total_certificates'] ?? 0; ?></span>
            <span>Total de horas: <?php echo number_format($certificates_stats['total_hours'] ?? 0, 1); ?></span>
            <div class="button-wrapper-inline">
                <button onclick="generateReport()" class="cta-btn btn-small btn-green-report" id="btnGenerateReport">Baixar relatório</button>
            </div>
        </div>
        <?php if (!empty($user_certificates)): ?>
            <div class="list-header certificates-header"><span>Palestra</span><span>Concluída em</span><span>Duração</span><span>Ações</span></div>
            <?php foreach ($user_certificates as $cert): ?>
                <div class="list-row">
                    <span><?php echo htmlspecialchars(cleanTitle($cert['lecture_title'])); ?></span>
                    <span><?php echo htmlspecialchars(date('d/m/Y', strtotime($cert['issued_at']))); ?></span>
                    <span><?php echo number_format($cert['duration_hours'] ?? 0, 1); ?> h</span>
                    <span class="col-actions">
                        <a href="view_certificate_files.php?id=<?php echo htmlspecialchars($cert['id']); ?>" target="_blank" class="cta-btn btn-small">Certificado</a>
                        <a href="/palestra.php?id=<?php echo htmlspecialchars($cert['lecture_id']); ?>" class="cta-btn btn-small">Assistir</a>
                    </span>
                </div>
            <?php endforeach; ?>
        <?php else: ?><p class="text-light" style="padding: 15px;">Nenhuma palestra concluída.</p><?php endif; ?>
    </div>

    <div class="video-card profile-card">
        <div class="card-header"><h2><i class="fas fa-file-alt"></i> Meus relatórios de projetos</h2></div>
        <?php if (!empty($user_reports)): ?>
            <div class="list-header report-header">
                <span>Nome do arquivo</span>
                <span>Tipo</span>
                <span>Data</span>
                <span>Ações</span>
            </div>
            <?php foreach ($user_reports as $report): ?>
                <div class="list-row report-row" data-report-id="<?php echo htmlspecialchars($report['id']); ?>">
                    <span class="report-name">
                        <i class="fas fa-file-<?php echo htmlspecialchars(strtolower($report['report_type'])); ?>"></i>
                        <?php echo htmlspecialchars($report['report_name']); ?>
                    </span>
                    <span class="report-type">
                        <span class="type-badge type-<?php echo htmlspecialchars(strtolower($report['report_type'])); ?>">
                            <?php echo strtoupper(htmlspecialchars($report['report_type'])); ?>
                        </span>
                    </span>
                    <span class="report-date">
                        <?php
                        $report_date = new DateTime($report['created_at'], new DateTimeZone('UTC'));
                        $report_date->setTimezone(new DateTimeZone('America/Sao_Paulo'));
                        echo $report_date->format('d/m/Y H:i');
                        ?>
                    </span>
                    <span class="col-actions">
                        <a href="dash-t101/<?php echo htmlspecialchars($report['file_path']); ?>" 
                           class="cta-btn btn-small btn-download-report" 
                           download title="Download">
                            <i class="fas fa-download"></i> Baixar
                        </a>
                        <button class="cta-btn btn-small btn-delete-report" 
                                onclick="deleteReport(<?php echo htmlspecialchars($report['id']); ?>, this)"
                                title="Excluir">
                            <i class="fas fa-trash"></i> Remover
                        </button>
                    </span>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-light" style="padding: 15px;">Você ainda não gerou nenhum relatório.</p>
        <?php endif; ?>
    </div>
    
    <div class="profile-row">
        <div class="video-card profile-card flex-1">
            <div class="card-header"><h2><i class="fas fa-id-card"></i> Informações pessoais</h2></div>
            <form method="POST" class="vision-form">
                <input type="hidden" name="update_personal_info" value="1">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name"><i class="fas fa-user"></i> Nome completo</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> E-mail</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="native_language_input"><i class="fas fa-globe"></i> Idioma nativo</label>
                        <div id="native-tag-container" class="tag-container">
                            <input type="text" id="native_language_input" placeholder="Digite os idiomas separados por vírgula.">
                        </div>
                        <input type="hidden" name="native_language" id="native_language_hidden" value="<?php echo htmlspecialchars($user['native_language'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="working_languages_input"><i class="fas fa-language"></i> Idiomas de trabalho</label>
                        <div id="tag-container" class="tag-container">
                            <input type="text" id="working_languages_input" placeholder="Digite os idiomas separados por vírgula.">
                        </div>
                        <input type="hidden" name="working_languages" id="working_languages_hidden" value="<?php echo htmlspecialchars($user['working_languages'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group form-group-wide">
                        <label class="checkbox-label">
                            <input type="checkbox" id="does_version" name="does_version" <?php echo ($user['does_version'] ?? 0) ? 'checked' : ''; ?>>
                            <span>Também faço versão</span>
                        </label>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="cta-btn"><i class="fas fa-save"></i> Salvar</button>
                </div>
            </form>
        </div>
        <div class="video-card profile-card flex-1">
            <div class="card-header"><h2><i class="fas fa-lock"></i> Alterar senha</h2></div>
            <form method="POST" class="vision-form">
                <div class="form-grid">
                    <div class="form-group form-group-wide">
                        <label for="current_password"><i class="fas fa-key"></i> Senha atual</label>
                        <input type="password" id="current_password" name="current_password" placeholder="Digite sua senha atual">
                    </div>
                    <div class="form-group">
                        <label for="new_password"><i class="fas fa-lock"></i> Nova senha</label>
                        <input type="password" id="new_password" name="new_password" placeholder="Digite a nova senha">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password"><i class="fas fa-lock"></i> Confirmar senha</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirme a nova senha">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="cta-btn"><i class="fas fa-key"></i> Alterar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="profile-nav-section">
        <div class="quick-actions-grid">
            <a href="videoteca.php" class="quick-action-card"><i class="fas fa-play-circle"></i><h3>Videoteca</h3><p>Acesse suas aulas</p></a>
            <a href="glossarios.php" class="quick-action-card"><i class="fas fa-book"></i><h3>Glossários</h3><p>Consulte termos</p></a>
            <a href="dash-t101/index.php" class="quick-action-card"><i class="fas fa-chart-line"></i><h3>Dashboard</h3><p>Seu progresso</p></a>
        </div>
    </div>
</div>

<div id="downloadNotification" class="download-notification">
    <i class="fas fa-check-circle"></i>
    <span>Relatório baixado! Verifique sua pasta de Downloads.</span>
</div>

<style>
/* ============================================
   TRIAL STATUS CARD STYLES
   ============================================ */
.trial-status-card {
    margin-bottom: 25px;
    border-radius: 16px;
    overflow: hidden;
}

.trial-status-card .card-header {
    padding: 20px 25px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.trial-status-card .card-header h2 {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 12px;
}

.trial-status-card.trial_7d {
    background: rgba(30, 30, 30, 0.8);
    border: 1px solid rgba(16, 185, 129, 0.4);
}

.trial-status-card.trial_7d .card-header {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(6, 182, 212, 0.1));
}

.trial-status-card.trial_7d .card-header h2 {
    color: #10b981;
}

.trial-status-card.trial_24h {
    background: rgba(30, 30, 30, 0.8);
    border: 1px solid rgba(236, 72, 153, 0.4);
}

.trial-status-card.trial_24h .card-header {
    background: linear-gradient(135deg, rgba(236, 72, 153, 0.2), rgba(139, 92, 246, 0.1));
}

.trial-status-card.trial_24h .card-header h2 {
    color: #ec4899;
}

.trial-info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    padding: 25px;
}

.trial-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 12px;
    padding: 20px 15px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    transition: all 0.3s ease;
}

.trial-stat:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-2px);
}

.trial-stat.full-width {
    grid-column: 1 / -1;
    flex-direction: row;
    text-align: left;
    align-items: center;
    justify-content: flex-start;
    gap: 15px;
}

.trial-stat-icon {
    width: 48px;
    height: 48px;
    min-width: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    font-size: 1.2rem;
}

.trial_7d .trial-stat-icon {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.trial_24h .trial-stat-icon {
    background: rgba(236, 72, 153, 0.15);
    color: #ec4899;
}

.trial-stat-content {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.trial-stat-label {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.trial-stat-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: white;
}

.trial-stat-value.expired {
    color: #ef4444;
}

.trial-stat-value.info {
    font-size: 0.95rem;
    font-weight: 400;
    color: rgba(255, 255, 255, 0.8);
    line-height: 1.4;
}

.trial-cta {
    padding: 20px 25px;
    text-align: center;
    background: rgba(0, 0, 0, 0.2);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.trial-cta p {
    margin-bottom: 15px;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.95rem;
}

.trial-expired-message {
    padding: 25px;
    text-align: center;
    background: rgba(239, 68, 68, 0.1);
    border-top: 1px solid rgba(239, 68, 68, 0.2);
}

.trial-expired-message i {
    font-size: 2.5rem;
    color: #ef4444;
    margin-bottom: 15px;
    display: block;
}

.trial-expired-message p {
    margin-bottom: 20px;
    color: rgba(255, 255, 255, 0.8);
}

/* ============================================
   PROFILE PAGE STYLES
   ============================================ */
.certificates-header { grid-template-columns: 3fr 1.5fr 1fr 1.5fr; }
.profile-card, .quick-action-card { margin-bottom: 25px; }
.card-header h2, .card-header h3 { padding: 15px 0 15px 18px; }
.profile-card:hover, .quick-action-card:hover {
    transform: none !important;
    border-color: #a855f7 !important;
    box-shadow: 0 0 15px rgba(168,85,247,0.6) !important;
}
.list-header, .list-row {
    display: grid;
    grid-template-columns: 3fr 2fr 1fr 1fr;
    gap:10px; padding:8px 12px; align-items:center;
}
.list-header { font-weight:600; border-bottom:2px solid rgba(255,255,255,0.2); }
.list-row { border-bottom:1px solid rgba(255,255,255,0.1); transition: opacity 0.3s ease; }
.col-actions { display:flex; gap:6px; }
.profile-row { display:flex; gap:20px; margin-bottom:25px; }
.flex-1 { flex:1; }
.cta-btn {
    border: none; background: var(--brand-purple); border-radius: 25px;
    color: #fff !important; cursor: pointer; padding: 8px 16px; font-weight: 600;
    transition: all 0.3s ease; display:inline-flex; align-items:center; justify-content:center; gap: 8px;
    text-decoration: none;
}
.cta-btn:hover { box-shadow: 0 0 8px rgba(139,92,246,0.6); }
.btn-small { font-size:0.8rem; padding:5px 12px; }
.btn-remove { background-color: #ff0000 !important; border: 1px solid #ff0000; color: white !important; }
.btn-remove:hover { background-color: #cc0000 !important; border-color: #cc0000; }
.btn-green-report {
    background-color: #28a745 !important; border: 1px solid #28a745 !important;
    color: white !important; padding: 10px 20px; font-size: 1rem !important;
}
.btn-green-report:hover { background-color: #218838 !important; border-color: #218838 !important; }
.btn-green-report:disabled {
    background-color: #6c757d !important; border-color: #6c757d !important;
    cursor: not-allowed; opacity: 0.6;
}
.stats-report-summary {
    display: flex; gap: 50px !important; justify-content: center !important; align-items: center !important;
    padding: 15px 30px; border-bottom: 1px solid rgba(255,255,255,0.1);
    font-weight: 600; font-size: 1.25rem !important; flex-wrap: wrap;
}
.stats-report-summary span { font-size: 1.75rem !important; color: #ffff !important; }
.button-wrapper-inline { flex-shrink: 0; }
.download-notification {
    position: fixed; top: 20px; right: 20px;
    background: linear-gradient(135deg, rgba(40, 167, 69, 0.95), rgba(34, 139, 58, 0.95));
    backdrop-filter: blur(10px); border: 1px solid rgba(40, 167, 69, 0.5); border-radius: 12px;
    padding: 18px 24px; display: flex; align-items: center; gap: 12px; color: white;
    font-weight: 600; font-size: 0.95rem; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3), 0 0 20px rgba(40, 167, 69, 0.4);
    z-index: 10000; opacity: 0; transform: translateX(400px);
    transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55); pointer-events: none;
}
.download-notification.show { opacity: 1; transform: translateX(0); pointer-events: auto; }
.download-notification i { font-size: 1.5rem; color: #fff; }
.security-info { display:flex; gap:15px; margin-top:10px; font-size:0.85rem; }
.report-header, .report-row { 
    grid-template-columns: 3fr 1fr 1.5fr 1.5fr; 
}
.report-name { display: flex; align-items: center; gap: 8px; font-weight: 600; }
.report-name i { color: #a855f7; font-size: 1.1rem; }
.type-badge {
    display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem;
    font-weight: 700; text-transform: uppercase;
}
.type-pdf { 
    background: rgba(220, 53, 69, 0.4);
    color: #f8d7da;
    border: 1px solid rgba(220, 53, 69, 0.6); 
}
.type-csv { background: rgba(102, 187, 106, 0.2); color: #66bb6a; border: 1px solid rgba(102, 187, 106, 0.4); }
.report-date { color: #ddd; font-size: 0.9rem; }
.btn-download-report { 
    background-color: var(--brand-purple) !important; 
    border-color: var(--brand-purple) !important;
}
.btn-download-report:hover { 
    background-color: #8b40d3 !important;
    border-color: #8b40d3 !important; 
}
.btn-delete-report { background-color: #dc3545 !important; border: 1px solid #dc3545 !important; }
.btn-delete-report:hover { background-color: #c82333 !important; border-color: #c82333 !important; }

/* Form adjustments */
.vision-form {
    padding: 20px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    color: var(--text-light, rgba(255,255,255,0.8));
}

.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #7B61FF;
}

.tag-container {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    padding: 8px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 5px;
    cursor: text;
    min-height: 44px;
}

.tag-container:focus-within {
    border-color: #7B61FF;
    box-shadow: 0 0 0 3px rgba(123, 97, 255, 0.2);
}

.tag {
    background-color: var(--brand-purple, #7B61FF);
    color: white;
    padding: 5px 10px;
    border-radius: 30px;
    display: flex;
    align-items: center;
    font-size: 0.9em;
}

.tag-close {
    margin-left: 8px;
    cursor: pointer;
    font-weight: bold;
}

.tag-container input {
    border: none;
    outline: none;
    background: transparent;
    color: white;
    flex-grow: 1;
    padding: 5px;
    min-width: 150px;
}

.tag-container input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

/* Quick actions grid */
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.quick-action-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 30px 20px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    text-decoration: none;
    color: white;
    transition: all 0.3s ease;
}

.quick-action-card i {
    font-size: 2.5rem;
    color: #a855f7;
    margin-bottom: 15px;
}

.quick-action-card h3 {
    margin: 0 0 8px 0;
    font-size: 1.2rem;
}

.quick-action-card p {
    margin: 0;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
}

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 768px) {
    .trial-info-grid {
        grid-template-columns: 1fr;
        padding: 15px;
    }
    
    .trial-stat {
        flex-direction: row;
        text-align: left;
        padding: 15px;
    }
    
    .trial-stat-value {
        font-size: 1.1rem;
    }
    
    .profile-row { flex-direction: column; }
    .list-header { display:none; }
    .list-row { grid-template-columns: 1fr; padding:12px; }
    .list-row span { display:block; margin-bottom:6px; }
    .list-row span:nth-child(1):before { content: "Palestra: "; font-weight:600; color:#a855f7; }
    .list-row span:nth-child(2):before { content: "Palestrante: "; font-weight:600; color:#a855f7; }
    .list-row span:nth-child(3):before { content: "Duração: "; font-weight:600; color:#a855f7; }
    .list-row span:nth-child(4):before { content: "Ações: "; font-weight:600; color:#a855f7; }
    .col-actions { margin-top:5px; }
    .stats-report-summary { flex-direction: column; align-items: flex-start; }
    .stats-report-summary span { margin-bottom: 5px; }
    .button-wrapper-inline { margin-top: 10px; }
    .download-notification { top: 10px; right: 10px; left: 10px; font-size: 0.85rem; padding: 14px 18px; }
    .report-row { grid-template-columns: 1fr; }
    .report-row span:nth-child(1):before { content: "Arquivo: "; font-weight:600; color:#a855f7; }
    .report-row span:nth-child(2):before { content: "Tipo: "; font-weight:600; color:#a855f7; }
    .report-row span:nth-child(3):before { content: "Data: "; font-weight:600; color:#a855f7; }
    .report-row span:nth-child(4):before { content: "Ações: "; font-weight:600; color:#a855f7; }
    
    .quick-actions-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function showDownloadNotification(message = 'Relatório baixado! Verifique sua pasta de Downloads.') {
    const notification = document.getElementById('downloadNotification');
    notification.querySelector('span').textContent = message;
    notification.classList.add('show');
    setTimeout(() => {
        notification.classList.remove('show');
    }, 5000);
}

function generateReport() {
    const btn = document.getElementById('btnGenerateReport');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando...';

    fetch('PDF Generator_report.php?save_only=true', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'generate_report' })
    }).then(response => response.json()).then(data => {
        if(data.success){
            console.log('Relatório salvo no servidor.');
            setTimeout(() => location.reload(), 1500); 
        }
    });

    fetch('generate_report.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'generate_report' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = data.download_url;
            showDownloadNotification();
        } else {
            alert('Erro ao gerar relatório: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao gerar relatório. Tente novamente.');
    })
    .finally(() => {
        setTimeout(() => { 
            btn.disabled = false; 
            btn.innerHTML = originalText;
        }, 2000);
    });
}

function generateCertificate(lectureId, userId) {
    const securityData = {
        fraud_detected: false,
        sequential_time: 600,
        segments_completed: 3,
        suspicious_activity: 0
    };

    fetch('generate_certificate.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            lecture_id: lectureId,
            user_id: userId,
            security_data: securityData
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const certificateUrl = 'https://v.translators101.com/download_certificate_files.php?id=' + encodeURIComponent(data.certificate_id);
            window.open(certificateUrl, '_blank');
        } else {
            alert('Erro ao gerar certificado: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao gerar certificado. Tente novamente.');
    });
}

function removeFromWatchlist(lectureId, button) {
    if (!confirm('Remover esta palestra da sua lista?')) return;
    
    const row = button.closest('.list-row');
    row.style.opacity = '0.5';
    
    fetch('api/watchlist.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'remove', lecture_id: lectureId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            row.style.transition = 'opacity 0.3s ease';
            row.style.opacity = '0';
            setTimeout(() => row.remove(), 300);
        } else {
            row.style.opacity = '1';
            alert('Erro ao remover: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        row.style.opacity = '1';
        console.error('Erro:', error);
    });
}

function deleteReport(reportId, button) {
    if (!confirm('Tem certeza que deseja excluir este relatório?')) return;
    
    const row = button.closest('.report-row');
    row.style.opacity = '0.5';
    
    fetch('dash-t101/delete_report.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ report_id: reportId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            row.style.transition = 'opacity 0.3s ease';
            row.style.opacity = '0';
            setTimeout(() => row.remove(), 300);
        } else {
            row.style.opacity = '1';
            alert('Erro ao excluir: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        row.style.opacity = '1';
        console.error('Erro:', error);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    function setupTagInput(containerId, inputId, hiddenId) {
        const tagContainer = document.getElementById(containerId);
        const input = document.getElementById(inputId);
        const hiddenInput = document.getElementById(hiddenId);
        let tags = [];

        function createTag(label) {
            const div = document.createElement('div');
            div.setAttribute('class', 'tag');
            const span = document.createElement('span');
            span.innerHTML = label;
            const closeBtn = document.createElement('span');
            closeBtn.setAttribute('class', 'tag-close');
            closeBtn.innerHTML = '×';
            closeBtn.addEventListener('click', function() {
                const tagLabel = this.previousElementSibling.innerHTML;
                const index = tags.indexOf(tagLabel);
                tags.splice(index, 1);
                updateTags();
                updateHiddenInput();
            });
            div.appendChild(span);
            div.appendChild(closeBtn);
            return div;
        }

        function updateTags() {
            const currentTags = tagContainer.querySelectorAll('.tag');
            currentTags.forEach(tag => tag.remove());
            tags.slice().reverse().forEach(function(tagLabel) {
                const tagElement = createTag(tagLabel);
                tagContainer.prepend(tagElement);
            });
        }

        function updateHiddenInput() {
            hiddenInput.value = tags.join(',');
        }

        input.addEventListener('keyup', function(e) {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                const value = input.value.trim().replace(/,/g, '');
                if (value !== '' && !tags.includes(value)) {
                    tags.push(value);
                    updateTags();
                    updateHiddenInput();
                }
                input.value = '';
            }
        });

        if (hiddenInput.value) {
            tags = hiddenInput.value.split(',').map(tag => tag.trim()).filter(tag => tag);
            updateTags();
        }

        tagContainer.addEventListener('click', () => {
            input.focus();
        });
    }

    setupTagInput('native-tag-container', 'native_language_input', 'native_language_hidden');
    setupTagInput('tag-container', 'working_languages_input', 'working_languages_hidden');
});
</script>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>