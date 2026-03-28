<?php
/**
 * arquivo_radar.php
 * * Página pública do Radar T101 - Curadoria Semanal de Tradução e Localização.
 */

session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/config/database.php';

// ============================================
// PROCESSAMENTO AJAX - APROVAR SEM ENVIAR EMAIL
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Verifica se é admin
    if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        echo json_encode(['success' => false, 'error' => 'Acesso negado.']);
        exit;
    }
    
    $action = $_POST['action'];
    $newsletter_id = $_POST['newsletter_id'] ?? null;
    
    if (!$newsletter_id) {
        echo json_encode(['success' => false, 'error' => 'ID da newsletter não fornecido.']);
        exit;
    }
    
    try {
        if ($action === 'approve_only') {
            // Apenas aprova (publica na página) sem enviar emails
            // Busca o rascunho atual para mover para o compilado caso esteja vazio
            $stmtCheck = $pdo->prepare("SELECT compiled_newsletter, newsletter_content FROM weekly_newsletters WHERE id = ?");
            $stmtCheck->execute([$newsletter_id]);
            $current = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (empty($current['compiled_newsletter']) && !empty($current['newsletter_content'])) {
                // Alinhado ao ENUM do banco: 'posted' em vez de 'aprovado'
                $stmt = $pdo->prepare("UPDATE weekly_newsletters SET status = 'posted', compiled_newsletter = ? WHERE id = ?");
                $stmt->execute([$current['newsletter_content'], $newsletter_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE weekly_newsletters SET status = 'posted' WHERE id = ?");
                $stmt->execute([$newsletter_id]);
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Newsletter aprovada e publicada! (sem envio de e-mails)'
            ]);
            exit;
        }
        
        if ($action === 'approve_and_send') {
            // Aprova e envia emails - redireciona para aprovar_radar.php
            // Gera token de aprovação
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Cria tabela se não existir
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS radar_approval_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    newsletter_id INT NOT NULL,
                    token VARCHAR(64) NOT NULL UNIQUE,
                    action ENUM('approve', 'edit') NOT NULL,
                    used TINYINT(1) DEFAULT 0,
                    expires_at DATETIME NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_token (token),
                    INDEX idx_newsletter (newsletter_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            $stmtToken = $pdo->prepare("
                INSERT INTO radar_approval_tokens (newsletter_id, token, action, expires_at) 
                VALUES (?, ?, 'approve', ?)
            ");
            $stmtToken->execute([$newsletter_id, $token, $expiresAt]);
            
            echo json_encode([
                'success' => true, 
                'redirect' => '/aprovar_radar.php?token=' . $token
            ]);
            exit;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Funções de verificação de autenticação
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
}

function isSubscriber() {
    return isset($_SESSION['is_subscriber']) && $_SESSION['is_subscriber'];
}

$is_logged_in = isLoggedIn();
$is_admin = isAdmin();
$is_subscriber = isSubscriber();

// Função para renderizar formulário de newsletter
function renderNewsletterForm($posicao, $is_logged_in) {
    if ($is_logged_in) return '';

    $pos = htmlspecialchars($posicao);

    return '
    <section class="newsletter-section newsletter-' . $pos . '">
        <div class="newsletter-container">
            <h3><i class="fa-solid fa-paper-plane"></i> Receba o Radar T101 toda segunda</h3>
            <p>Inscreva-se para receber a curadoria semanal no seu e-mail.</p>

            <form class="newsletter-form" data-newsletter-form="1" id="form-newsletter-' . $pos . '">
                <input type="text" name="nome" placeholder="Seu nome" required>
                <input type="email" name="email" placeholder="Seu melhor e-mail" required>
                <button type="submit" class="btn-subscribe">Quero me inscrever</button>
            </form>

            <div class="form-message" aria-live="polite"></div>
        </div>
    </section>';
}

// ============================================
// BUSCA DA CURADORIA (COM FILTRO DE STATUS)
// ============================================

// Verifica se é admin para determinar os status permitidos ('posted' é o aprovado no banco)
$statusFilter = $is_admin ? "1=1" : "status = 'posted'";

// Busca a curadoria: se foi passado um ID específico ou a última aprovada
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM weekly_newsletters WHERE id = ? AND ({$statusFilter})");
    $stmt->execute([$_GET['id']]);
    $newsletter = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // Busca a newsletter mais recente com status aprovado (ou qualquer para admin)
    $stmt = $pdo->prepare("SELECT * FROM weekly_newsletters WHERE {$statusFilter} ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $newsletter = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Busca o histórico das últimas edições APROVADAS (ou todas para admin)
$historyStatusFilter = $is_admin ? "1=1" : "status = 'posted'";
$stmtHistory = $pdo->query("
    SELECT id, created_at, status
    FROM weekly_newsletters
    WHERE {$historyStatusFilter}
    ORDER BY created_at DESC, id DESC
    LIMIT 50
");
$rawHistory = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

// Remove repetição por data (mantém apenas a mais recente do dia)
$historyList = [];
$seenDates = [];

foreach ($rawHistory as $item) {
    $dateKey = date('Y-m-d', strtotime($item['created_at']));
    if (!isset($seenDates[$dateKey])) {
        $seenDates[$dateKey] = true;
        $historyList[] = $item;
    }
}

// Limita visualmente a 12 edições
$historyList = array_slice($historyList, 0, 12);

// ============================================
// BUSCA A PRÓXIMA PALESTRA E A MINIBIO
// ============================================
$sqlPalestra = "SELECT * FROM upcoming_announcements 
                WHERE STR_TO_DATE(CONCAT(announcement_date, ' ', lecture_time), '%Y-%m-%d %H:%i:%s') > NOW()
                ORDER BY announcement_date ASC, lecture_time ASC
                LIMIT 1";
$stmtPalestra = $pdo->query($sqlPalestra);
$proximaPalestra = $stmtPalestra->fetch(PDO::FETCH_ASSOC);

if ($proximaPalestra) {
    $minibio = '';
    
    // 1. Verifica se a minibio já veio nativamente na tabela da palestra
    if (!empty($proximaPalestra['speaker_minibio'])) {
        $minibio = $proximaPalestra['speaker_minibio'];
    } elseif (!empty($proximaPalestra['minibio'])) {
        $minibio = $proximaPalestra['minibio'];
    }
    
    // 2. Se não encontrou, faz uma busca resiliente pelo palestrante
    if (empty($minibio) && !empty($proximaPalestra['speaker'])) {
        $speakerName = $proximaPalestra['speaker'];
        
        // Tenta na tabela de palestras cadastradas (lectures)
        try {
            $stmtBio1 = $pdo->prepare("SELECT speaker_minibio FROM lectures WHERE speaker = ? AND speaker_minibio IS NOT NULL AND speaker_minibio != '' LIMIT 1");
            $stmtBio1->execute([$speakerName]);
            $row1 = $stmtBio1->fetch(PDO::FETCH_ASSOC);
            if ($row1 && !empty($row1['speaker_minibio'])) {
                $minibio = $row1['speaker_minibio'];
            }
        } catch (Exception $e) { /* Ignora caso a tabela/coluna não exista */ }
        
        // 3. Tenta na tabela de palestrantes (speakers)
        if (empty($minibio)) {
            try {
                $stmtBio2 = $pdo->prepare("SELECT minibio FROM speakers WHERE name = ? OR nome = ? LIMIT 1");
                $stmtBio2->execute([$speakerName, $speakerName]);
                $row2 = $stmtBio2->fetch(PDO::FETCH_ASSOC);
                if ($row2 && !empty($row2['minibio'])) {
                    $minibio = $row2['minibio'];
                }
            } catch (Exception $e) { /* Ignora caso a tabela/coluna não exista */ }
        }
    }
    
    // Atribui a minibio final encontrada
    $proximaPalestra['speaker_minibio'] = $minibio;
}

// Configurações da página para SEO
$page_title = "Radar T101 - Curadoria Semanal de Tradução e Localização";
$page_description = "Fique por dentro das principais notícias, tendências de IA e do mercado de tradução na curadoria semanal oficial da Translators101.";

include __DIR__ . '/vision/includes/head.php';
include __DIR__ . '/vision/includes/header.php';
include __DIR__ . '/vision/includes/sidebar.php';
?>

<style>
/* Estilos específicos do Radar T101 */
.radar-main-content {
    padding: 30px 20px;
    max-width: 100%;
}

.radar-container {
    max-width: 900px;
    margin: 0 auto;
}

.radar-hero {
    text-align: center;
    margin-bottom: 40px;
    padding: 40px 30px;
    background: linear-gradient(135deg, rgba(192, 132, 252, 0.1), rgba(139, 92, 246, 0.05));
    border-radius: 20px;
    border: 1px solid rgba(192, 132, 252, 0.2);
}

.radar-hero h1 {
    font-size: 2.5rem;
    color: #fff;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
}

.radar-hero h1 i {
    color: #c084fc;
}

.radar-hero p {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1.1rem;
    max-width: 600px;
    margin: 0 auto;
}

/* Status Badge para Admin */
.status-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    margin-left: 10px;
    text-transform: uppercase;
}

.status-badge.draft {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.4);
}

.status-badge.posted {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.4);
}

.status-badge.aguardando_aprovacao {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.4);
}

/* Seção de Curadoria */
.curadoria-section {
    background: rgba(30, 30, 50, 0.6);
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    padding: 30px;
    margin-bottom: 30px;
}

.curadoria-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    flex-wrap: wrap;
    gap: 15px;
}

.curadoria-header h2 {
    color: #c084fc;
    font-size: 1.3rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.curadoria-date {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.9rem;
}

.curadoria-content {
    color: rgba(255, 255, 255, 0.85);
    font-size: 1rem;
    line-height: 1.8;
}

.curadoria-content p {
    margin-bottom: 15px;
}

/* Separador de notícias */
.news-separator {
    border: none;
    border-top: 1px dashed rgba(192, 132, 252, 0.3);
    margin: 25px 0;
}

/* Botão de fonte */
.source-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(192, 132, 252, 0.15);
    color: #c084fc;
    padding: 8px 16px;
    border-radius: 20px;
    text-decoration: none;
    font-size: 0.85rem;
    margin-top: 10px;
    transition: all 0.3s;
    border: 1px solid rgba(192, 132, 252, 0.3);
}

.source-btn:hover {
    background: rgba(192, 132, 252, 0.25);
    transform: translateY(-2px);
}

.paywall-notice {
    color: rgba(245, 158, 11, 0.8);
    font-size: 0.75rem;
    margin-left: 10px;
}

/* Mensagem de vazio */
.empty-message {
    text-align: center;
    padding: 60px 30px;
    color: rgba(255, 255, 255, 0.5);
}

.empty-message i {
    font-size: 48px;
    color: rgba(192, 132, 252, 0.3);
    margin-bottom: 20px;
}

/* Histórico */
.history-section {
    background: rgba(30, 30, 50, 0.6);
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    padding: 25px;
    margin-bottom: 30px;
}

.history-section h3 {
    color: #c084fc;
    font-size: 1.1rem;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.history-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.history-item {
    background: rgba(0, 0, 0, 0.3);
    padding: 10px 18px;
    border-radius: 10px;
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    font-size: 0.9rem;
    transition: all 0.3s;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.history-item:hover {
    background: rgba(192, 132, 252, 0.15);
    color: #c084fc;
    border-color: rgba(192, 132, 252, 0.3);
}

.history-item.active {
    background: rgba(192, 132, 252, 0.2);
    color: #c084fc;
    border-color: #c084fc;
}

/* Próxima Palestra */
.lecture-section {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(251, 191, 36, 0.05));
    border-radius: 16px;
    border: 1px solid rgba(245, 158, 11, 0.3);
    padding: 30px;
    margin-bottom: 30px;
}

.lecture-section h3 {
    color: #f59e0b;
    font-size: 1.2rem;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.lecture-card {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.lecture-image-container {
    width: 100%;
    aspect-ratio: 16 / 9;
    overflow: hidden;
    border-radius: 12px;
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.lecture-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.lecture-info {
    flex: 1;
    min-width: 280px;
}

.lecture-title {
    font-size: 1.5rem;
    color: white;
    margin-bottom: 10px;
}

.lecture-speaker {
    color: #f59e0b;
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 15px;
}

.lecture-minibio {
    background: rgba(0, 0, 0, 0.2);
    padding: 15px 20px;
    border-radius: 12px;
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.9);
    margin-bottom: 20px;
    border-left: 3px solid #f59e0b;
    line-height: 1.5;
}

.lecture-description {
    color: rgba(255, 255, 255, 0.8);
    font-size: 1rem;
    line-height: 1.6;
    margin-bottom: 20px;
}

.lecture-datetime {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.95rem;
    margin-bottom: 20px;
}

.lecture-datetime i {
    color: #f59e0b;
    margin-right: 8px;
}

.btn-subscribe-lecture {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(90deg, #f59e0b, #d97706);
    color: white;
    padding: 12px 25px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-subscribe-lecture:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(245, 158, 11, 0.4);
}

/* Newsletter Form */
.newsletter-section {
    background: linear-gradient(135deg, rgba(192, 132, 252, 0.1), rgba(139, 92, 246, 0.05));
    border-radius: 16px;
    border: 1px solid rgba(192, 132, 252, 0.3);
    padding: 30px;
    margin-bottom: 30px;
}

.newsletter-container h3 {
    color: #c084fc;
    font-size: 1.2rem;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.newsletter-container p {
    color: rgba(255, 255, 255, 0.7);
    margin-bottom: 20px;
}

.newsletter-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.newsletter-form input {
    flex: 1;
    min-width: 200px;
    padding: 12px 18px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.3);
    color: white;
    font-size: 0.95rem;
}

.newsletter-form input:focus {
    outline: none;
    border-color: #c084fc;
}

.btn-subscribe {
    background: linear-gradient(90deg, #c084fc, #8b5cf6);
    color: white;
    padding: 12px 25px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-subscribe:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(192, 132, 252, 0.4);
}

.form-message {
    margin-top: 15px;
    padding: 12px;
    border-radius: 8px;
    display: none;
}

.form-message.success {
    display: block;
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.form-message.error {
    display: block;
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

/* Admin Actions */
.admin-actions {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}

.admin-actions h4 {
    color: #3b82f6;
    font-size: 1rem;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.admin-buttons-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.admin-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 12px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 600;
    transition: all 0.3s;
    cursor: pointer;
    border: none;
}

.admin-btn-approve-only {
    background: linear-gradient(90deg, #10b981, #059669);
    color: white;
}

.admin-btn-approve-send {
    background: linear-gradient(90deg, #8b5cf6, #7c3aed);
    color: white;
}

.admin-btn-edit {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.4);
}

.admin-btn-edit-direct {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
    border: 1px solid rgba(59, 130, 246, 0.4);
}

.admin-btn:hover {
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .radar-hero h1 {
        font-size: 1.8rem;
    }
    
    .curadoria-header {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<div class="main-content radar-main-content">
    <div class="radar-container">
        
        <div class="radar-hero">
            <h1><i class="fas fa-satellite-dish"></i> Radar T101</h1>
            <p>Sua curadoria semanal com as principais notícias, tendências de IA e insights do mercado de tradução e localização.</p>
        </div>

        <?php echo renderNewsletterForm('top', $is_logged_in); ?>

        <?php if ($is_admin && $newsletter && $newsletter['status'] !== 'posted'): ?>
        <div class="admin-actions">
            <h4><i class="fas fa-shield-alt"></i> Ações de Administrador</h4>
            <p style="color: rgba(255,255,255,0.6); margin-bottom: 15px; font-size: 0.9rem;">
                Esta newsletter ainda não foi aprovada. Apenas administradores podem visualizá-la.
            </p>
            <div class="admin-buttons-grid">
                <button type="button" class="admin-btn admin-btn-approve-only" onclick="approveOnly(<?php echo $newsletter['id']; ?>)">
                    <i class="fas fa-check"></i> Aprovar (sem enviar e-mail)
                </button>
                <button type="button" class="admin-btn admin-btn-approve-send" onclick="approveAndSend(<?php echo $newsletter['id']; ?>)">
                    <i class="fas fa-paper-plane"></i> Aprovar e Enviar E-mails
                </button>
                <a href="/admin/emails_b.php?editar_radar=<?php echo $newsletter['id']; ?>" class="admin-btn admin-btn-edit">
                    <i class="fas fa-edit"></i> Editar Conteúdo
                </a>
                <a href="/editar_radar.php?direct_edit=<?php echo $newsletter['id']; ?>" class="admin-btn admin-btn-edit-direct">
                    <i class="fas fa-pencil-alt"></i> Edição Rápida
                </a>
            </div>
            <div id="admin-message" style="margin-top: 15px; display: none;"></div>
        </div>
        <?php endif; ?>

        <?php if ($newsletter && (!empty($newsletter['compiled_newsletter']) || !empty($newsletter['newsletter_content']))): ?>
        <div class="curadoria-section">
            <div class="curadoria-header">
                <h2>
                    <i class="fas fa-newspaper"></i> Curadoria da Semana
                    <?php if ($is_admin): ?>
                        <span class="status-badge <?php echo $newsletter['status']; ?>">
                            <?php echo $newsletter['status'] === 'posted' ? 'Aprovado' : 'Rascunho'; ?>
                        </span>
                    <?php endif; ?>
                </h2>
                <span class="curadoria-date">
                    <i class="fas fa-calendar"></i>
                    <?php echo date('d/m/Y', strtotime($newsletter['created_at'])); ?>
                </span>
            </div>
            <div class="curadoria-content">
                <?php
                // Processa o conteúdo para adicionar links e separadores
                $content = !empty($newsletter['compiled_newsletter']) ? $newsletter['compiled_newsletter'] : $newsletter['newsletter_content'];
                
                // Adiciona botões de "Ver notícia fonte" para URLs
                $content = preg_replace_callback(
                    '/https?:\/\/[^\s\)\]]+/',
                    function($matches) {
                        $url = $matches[0];
                        $isPaywall = (stripos($url, 'slator.com') !== false) ? '<span class="paywall-notice">(paywall)</span>' : '';
                        return '<br><a href="' . htmlspecialchars($url) . '" target="_blank" class="source-btn"><i class="fas fa-external-link-alt"></i> Ver notícia - algumas fontes são exclusivas</a>' . $isPaywall . '<hr class="news-separator">';
                    },
                    $content
                );
                
                // Remove títulos específicos
                $content = preg_replace('/REFLEXÃO FINAL|PANORAMA DA SEMANA/i', '', $content);
                
                // Converte quebras de linha
                $content = nl2br(htmlspecialchars_decode($content));
                
                echo $content;
                ?>
            </div>
        </div>
        <?php else: ?>
        <div class="curadoria-section">
            <div class="empty-message">
                <i class="fas fa-satellite-dish"></i>
                <h3>Curadoria em andamento</h3>
                <p>Nossa equipe está preparando a curadoria desta semana. Volte em breve!</p>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($historyList)): ?>
        <div class="history-section">
            <h3><i class="fas fa-history"></i> Edições Anteriores</h3>
            <div class="history-grid">
                <?php foreach ($historyList as $item): 
                    $isActive = ($newsletter && $item['id'] == $newsletter['id']) ? 'active' : '';
                    $statusClass = ($is_admin && $item['status'] !== 'posted') ? ' style="opacity: 0.6;"' : '';
                ?>
                <a href="?id=<?php echo $item['id']; ?>" class="history-item <?php echo $isActive; ?>"<?php echo $statusClass; ?>>
                    <?php echo date('d/m/Y', strtotime($item['created_at'])); ?>
                    <?php if ($is_admin && $item['status'] !== 'posted'): ?>
                        <span class="status-badge <?php echo $item['status']; ?>" style="font-size: 10px; padding: 2px 6px; margin-left: 5px;">
                            <?php echo $item['status'] === 'draft' ? 'Rascunho' : ucfirst($item['status']); ?>
                        </span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($proximaPalestra): ?>
        <div class="lecture-section">
            <h3><i class="fas fa-video"></i> Próxima Palestra</h3>
            <div class="lecture-card">
                
                <?php if (!empty($proximaPalestra['image_path'])): ?>
                <div class="lecture-image-container">
                    <img src="<?php echo htmlspecialchars($proximaPalestra['image_path']); ?>" alt="<?php echo htmlspecialchars($proximaPalestra['title']); ?>" class="lecture-image">
                </div>
                <?php endif; ?>
                
                <div class="lecture-info">
                    <h4 class="lecture-title"><?php echo htmlspecialchars($proximaPalestra['title']); ?></h4>
                    <p class="lecture-speaker">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($proximaPalestra['speaker']); ?>
                    </p>
                    
                    <?php if (!empty($proximaPalestra['speaker_minibio'])): ?>
                    <div class="lecture-minibio">
                        <strong>Minibio:</strong><br>
                        <?php echo nl2br(htmlspecialchars($proximaPalestra['speaker_minibio'])); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($proximaPalestra['description'])): ?>
                    <div class="lecture-description">
                        <strong>Sobre a palestra:</strong><br>
                        <?php echo nl2br(htmlspecialchars($proximaPalestra['description'])); ?>
                    </div>
                    <?php endif; ?>
                    
                    <p class="lecture-datetime">
                        <i class="fas fa-calendar-alt"></i>
                        <?php echo date('d/m/Y', strtotime($proximaPalestra['announcement_date'])); ?> às 
                        <?php echo date('H:i', strtotime($proximaPalestra['lecture_time'])); ?>h
                    </p>
                    
                    <a href="/assinar" class="btn-subscribe-lecture">
                        <i class="fas fa-star"></i> Assinar para Participar
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php echo renderNewsletterForm('bottom', $is_logged_in); ?>

    </div>
</div>

<script>
(function() {
    const forms = document.querySelectorAll('form[data-newsletter-form="1"]');
    if (!forms.length) return;

    forms.forEach((form) => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const btn = form.querySelector('button[type="submit"]');
            const messageDiv = form.parentElement.querySelector('.form-message');
            const originalText = btn ? btn.textContent : '';

            if (messageDiv) {
                messageDiv.textContent = '';
                messageDiv.className = 'form-message';
            }

            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Enviando...';
            }

            try {
                const formData = new FormData(form);

                const resp = await fetch('inscrever_newsletter.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const data = await resp.json();

                if (messageDiv) {
                    messageDiv.textContent = (data && data.message) ? data.message : 'Resposta inesperada.';
                    messageDiv.className = 'form-message ' + ((data && data.success) ? 'success' : 'error');
                }

                if (data && data.success) form.reset();

            } catch (err) {
                if (messageDiv) {
                    messageDiv.textContent = 'Erro na conexão. Tente novamente.';
                    messageDiv.className = 'form-message error';
                }
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = originalText || 'Quero me inscrever';
                }
            }
        });
    });
})();

// Funções de aprovação do admin
function showAdminMessage(message, isError = false) {
    const msgDiv = document.getElementById('admin-message');
    if (msgDiv) {
        msgDiv.innerHTML = message;
        msgDiv.style.display = 'block';
        msgDiv.style.padding = '12px 16px';
        msgDiv.style.borderRadius = '8px';
        msgDiv.style.background = isError ? 'rgba(239, 68, 68, 0.15)' : 'rgba(16, 185, 129, 0.15)';
        msgDiv.style.color = isError ? '#ef4444' : '#10b981';
        msgDiv.style.border = isError ? '1px solid rgba(239, 68, 68, 0.3)' : '1px solid rgba(16, 185, 129, 0.3)';
    }
}

async function approveOnly(newsletterId) {
    if (!confirm('Aprovar e publicar esta newsletter SEM enviar e-mails para os assinantes?')) return;
    
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Aprovando...';
    
    try {
        const formData = new FormData();
        formData.append('action', 'approve_only');
        formData.append('newsletter_id', newsletterId);
        
        const resp = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const data = await resp.json();
        
        if (data.success) {
            showAdminMessage('<i class="fas fa-check-circle"></i> ' + data.message);
            setTimeout(() => location.reload(), 1500);
        } else {
            showAdminMessage('<i class="fas fa-exclamation-circle"></i> Erro: ' + data.error, true);
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (err) {
        showAdminMessage('<i class="fas fa-exclamation-circle"></i> Erro na conexão: ' + err.message, true);
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

async function approveAndSend(newsletterId) {
    if (!confirm('Aprovar esta newsletter E enviar e-mails para TODOS os assinantes e leads do Radar?')) return;
    
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
    
    try {
        const formData = new FormData();
        formData.append('action', 'approve_and_send');
        formData.append('newsletter_id', newsletterId);
        
        const resp = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const data = await resp.json();
        
        if (data.success && data.redirect) {
            showAdminMessage('<i class="fas fa-paper-plane"></i> Redirecionando para envio de e-mails...');
            window.location.href = data.redirect;
        } else if (data.error) {
            showAdminMessage('<i class="fas fa-exclamation-circle"></i> Erro: ' + data.error, true);
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (err) {
        showAdminMessage('<i class="fas fa-exclamation-circle"></i> Erro na conexão: ' + err.message, true);
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}
</script>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>