<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Verificar acesso
if (!function_exists('hasVideotecaAccess') || !hasVideotecaAccess()) {
    header('Location: /index.php');
    exit;
}

// --- Funções de Acesso (Devem ser garantidas no ambiente de produção) ---
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
    }
}

$is_admin = isAdmin();

$page_title = 'Videoteca - Translators101';
$page_description = 'Aproveite nossas palestras especializadas em tradução e interpretação';

// Parâmetros de busca e filtros
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$sort_order = $_GET['sort'] ?? 'season_desc';

// --- BUSCAR PRÓXIMAS PALESTRAS (upcoming_announcements) ---
$upcomingLectures = [];
try {
    // Ordenação: data mais próxima para a mais distante
    $stmt = $pdo->query("
        SELECT id, title, speaker, description, image_path, announcement_date, lecture_time
        FROM upcoming_announcements
        WHERE is_active = 1
        AND announcement_date >= CURDATE()
        ORDER BY announcement_date ASC, display_order ASC
        LIMIT 3
    ");
    $upcomingLectures = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Em caso de erro, a lista fica vazia.
    $upcomingLectures = [];
}


// Query SQL principal
$sql = 'SELECT * FROM lectures WHERE 1=1';
$params = [];

if (!empty($search)) {
    $sql .= ' AND (title LIKE ? OR speaker LIKE ? OR description LIKE ?)';
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($category)) {
    $sql .= ' AND category = ?';
    $params[] = $category;
}

// Buscar palestras
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $lectures = $stmt->fetchAll();
} catch (Exception $e) {
    $lectures = [];
}

// Buscar watchlist do usuário logado
$user_watchlist = [];
$user_watched = [];
if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    
    // Palestras na watchlist
    try {
        $stmt = $pdo->prepare('SELECT lecture_id FROM user_watchlist WHERE user_id = ?');
        $stmt->execute([$user_id]);
        $watchlist_items = $stmt->fetchAll();
        foreach ($watchlist_items as $item) {
            $user_watchlist[] = $item['lecture_id'];
        }
    } catch (Exception $e) {
        // Ignorar erro
    }
    
    // Palestras já assistidas (com certificado)
    try {
        $stmt = $pdo->prepare('SELECT DISTINCT lecture_id FROM certificates WHERE user_id = ?');
        $stmt->execute([$user_id]);
        $watched_items = $stmt->fetchAll();
        foreach ($watched_items as $item) {
            $user_watched[] = $item['lecture_id'];
        }
    } catch (Exception $e) {
        // Ignorar erro
    }
}

// Buscar categorias
try {
    $stmt = $pdo->prepare('SELECT DISTINCT category FROM lectures WHERE category IS NOT NULL AND category != \'\' ORDER BY category');
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $categories = ['Traducao', 'Interpretacao', 'Linguas', 'Tecnologia', 'Negocios'];
}

// Funções de ordenação
function extractSeasonEpisode($title) {
    if (preg_match('/S(\d+)E(\d+)/i', $title, $matches)) {
        return [
            'season' => (int)$matches[1],
            'episode' => (int)$matches[2],
            'has_pattern' => true
        ];
    }
    return ['season' => 0, 'episode' => 0, 'has_pattern' => false];
}

function sortLectures($lectures, $sort_order) {
    $withPattern = [];
    $withoutPattern = [];
    
    foreach ($lectures as $lecture) {
        $seasonEpisode = extractSeasonEpisode($lecture['title']);
        if ($seasonEpisode['has_pattern']) {
            $lecture['season'] = $seasonEpisode['season'];
            $lecture['episode'] = $seasonEpisode['episode'];
            $withPattern[] = $lecture;
        } else {
            $withoutPattern[] = $lecture;
        }
    }
    
    switch ($sort_order) {
        case 'season_asc':
            usort($withPattern, function($a, $b) {
                if ($a['season'] != $b['season']) {
                    return $a['season'] - $b['season'];
                }
                return $a['episode'] - $b['episode'];
            });
            usort($withoutPattern, function($a, $b) {
                return strcmp($a['title'], $b['title']);
            });
            return array_merge($withPattern, $withoutPattern);
            
        case 'season_desc':
            usort($withPattern, function($a, $b) {
                if ($a['season'] != $b['season']) {
                    return $b['season'] - $a['season'];
                }
                return $b['episode'] - $a['episode'];
            });
            usort($withoutPattern, function($a, $b) {
                return strcmp($b['title'], $a['title']);
            });
            return array_merge($withPattern, $withoutPattern);
            
        case 'alpha_asc':
            $all = array_merge($withPattern, $withoutPattern);
            usort($all, function($a, $b) {
                return strcmp($a['title'], $b['title']);
            });
            return $all;
            
        case 'alpha_desc':
            $all = array_merge($withPattern, $withoutPattern);
            usort($all, function($a, $b) {
                return strcmp($b['title'], $a['title']);
            });
            return $all;
            
        case 'date_asc':
            $all = array_merge($withPattern, $withoutPattern);
            usort($all, function($a, $b) {
                return strtotime($a['created_at']) - strtotime($b['created_at']);
            });
            return $all;
            
        case 'date_desc':
            $all = array_merge($withPattern, $withoutPattern);
            usort($all, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
            return $all;
            
        default:
            return array_merge($withPattern, $withoutPattern);
    }
}

$lectures = sortLectures($lectures, $sort_order);

include __DIR__ . '/vision/includes/head.php';
?>

<style>
/* Estilos específicos para a videoteca */
.videoteca-title {
    display: flex;
    align-items: center;
    gap: 15px;
    justify-content: center;
}

.videoteca-filtros {
    margin-bottom: 30px;
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 24px;
}

.filtros-grid-improved {
    display: grid;
    grid-template-columns: 2fr 1.2fr 1.2fr auto;
    gap: 20px;
    align-items: end;
}

@media (max-width: 900px) {
    .filtros-grid-improved {
        grid-template-columns: 1fr;
    }
}

.search-field-expanded {
    display: flex;
    flex-direction: column;
}

.search-input-large,
.category-select,
.sort-select {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.18);
    color: #ffffff;
    font-size: 1rem;
    font-weight: 600;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.6);
}

.search-btn-container {
    display: flex;
    align-items: end;
}

.search-btn {
    white-space: nowrap;
    padding: 12px 20px;
    height: fit-content;
}

.video-grid-four {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 25px;
    margin-bottom: 40px;
    max-width: 100%;
}

.video-thumb-container {
    position: relative;
    width: 100%;
    padding-bottom: 56.25%;
    overflow: hidden;
    background: linear-gradient(135deg, var(--brand-purple), #5e3370);
}

.video-thumb {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, var(--brand-purple), #5e3370);
    display: flex;
    align-items: center;
    justify-content: center;
}

.video-image {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    display: block;
}

.video-card:hover {
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    border-color: var(--brand-purple);
}

.video-desc {
    font-size: 0.8rem;
    line-height: 1.4;
}

.video-meta {
    font-size: 0.8rem;
}

/* Estilo melhorado para o indicador "Destaque" */
.featured-tag {
    background: linear-gradient(135deg, #ff6b35, #f7931e) !important;
    color: #ffffff !important;
    border: 2px solid #ff4500 !important;
    font-weight: 700 !important;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.7) !important;
    box-shadow: 0 2px 8px rgba(255, 107, 53, 0.4) !important;
    text-transform: capitalize !important;
}

.featured-tag:hover {
    background: linear-gradient(135deg, #ff4500, #ff6b35) !important;
    box-shadow: 0 4px 12px rgba(255, 107, 53, 0.6) !important;
}

/* Estilo melhorado para categoria */
.video-category {
    background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;
    color: #ffffff !important;
    padding: 4px 12px !important;
    border-radius: 12px !important;
    border: 2px solid #1e40af !important;
    font-size: 0.75rem !important;
    font-weight: 700 !important;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.7) !important;
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.4) !important;
    display: inline-block !important;
    margin-bottom: 8px !important;
    text-transform: capitalize !important;
}

/* Estilos para watchlist/assistida */
.watchlist-section {
    margin-top: auto; /* A mágica acontece aqui! */
    padding-top: 10px; /* Adiciona um espaço acima */
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.watchlist-checkbox {
    display: flex;
    align-items: center;
    cursor: pointer;
    font-size: 0.85rem;
    color: #ffffff;
    user-select: none;
}

.watchlist-input {
    display: none;
}

.checkmark {
    height: 16px;
    width: 16px;
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.4);
    border-radius: 3px;
    margin-right: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    position: relative;
}

.checkmark:before {
    content: '';
    position: absolute;
    left: 3px;
    top: 0px;
    width: 4px;
    height: 8px;
    border: solid #ffffff;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.watchlist-input:checked + .checkmark {
    background: linear-gradient(135deg, #10b981, #059669);
    border-color: #059669;
}

.watchlist-input:checked + .checkmark:before {
    opacity: 1;
}

.watchlist-checkbox:hover .checkmark {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.6);
}

.watchlist-input:checked + .checkmark:hover {
    background: linear-gradient(135deg, #059669, #047857);
}

.watchlist-text {
    font-weight: 500;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.7);
    transition: color 0.3s ease;
}

.watchlist-checkbox:hover .watchlist-text {
    color: #f0f0f0;
}

/* Estilo para palestra assistida */
.watched-indicator {
    display: flex;
    align-items: center;
    font-size: 0.85rem;
    color: #10b981;
    font-weight: 600;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.7);
}

.watched-indicator i {
    margin-right: 6px;
}

@media (max-width: 1400px) {
    .video-grid-four {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 1200px) {
    .video-grid-four {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .video-grid-four {
        grid-template-columns: 1fr;
    }
}

.video-info {
    display: flex;
    flex-direction: column;
    flex-grow: 1; 
}

.watchlist-section {
    margin-top: auto; 
}

/* --- NOVAS REGRAS PARA ALINHAMENTO DOS CARDS DE VÍDEO --- */

/* 1. Garante que o TÍTULO do vídeo ocupe sempre 3 linhas */
.video-info h3 {
    font-size: 1rem;
    line-height: 1.3rem; /* Altura de linha fixa */
    display: -webkit-box;
    -webkit-line-clamp: 3; /* Limita a 3 linhas */
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    /* Força a altura para ser exatamente 3x a altura da linha */
    min-height: calc(1.3rem * 3); 
    max-height: calc(1.3rem * 3);
}

/* 2. Garante que o NOME DO PALESTRANTE ocupe sempre 2 linhas */
.video-speaker {
    font-size: 0.95rem;
    line-height: 1.2rem; /* Altura de linha fixa */
    display: -webkit-box;
    -webkit-line-clamp: 2; /* Limita a 2 linhas */
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    /* Força a altura para ser exatamente 2x a altura da linha */
    min-height: calc(1.2rem * 2);
    max-height: calc(1.2rem * 2);
    margin-bottom: 10px; /* Adiciona um espaço abaixo */
}

/* --- ESTILOS PARA SEÇÃO DE PRÓXIMAS PALESTRAS --- */
.upcoming-lectures {
    background: linear-gradient(135deg, rgba(142, 68, 173, 0.1), rgba(255, 255, 255, 0.05));
    margin: 40px 0;
}

.lectures-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin: 50px 0;
}

@media (max-width: 1024px) {
    .lectures-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .lectures-grid {
        grid-template-columns: 1fr;
    }
}

.lecture-card {
    position: relative;
    border: 2px solid transparent;
    border-radius: 20px;
    padding: 20px;
    background: rgba(255, 255, 255, 0.08);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.lecture-card:hover {
    border-color: var(--brand-purple);
    box-shadow: 0 20px 40px rgba(142, 68, 173, 0.6);
}

.lecture-image-container {
    position: relative;
    width: 100%;
    padding-bottom: 56.25%;
    overflow: hidden;
    border-radius: 12px;
}

.lecture-image {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.lecture-info {
    padding-top: 16px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}


.lecture-datetime {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.lecture-date {
    background: var(--brand-purple);
    color: white;
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 0.9rem;
    font-weight: 700;
    display: inline-block;
}

.lecture-time {
    background: var(--accent-gold);
    color: white;
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 0.9rem;
    font-weight: 700;
    display: inline-block;
}

.lecture-title {
    font-size: 1.2rem;
    color: var(--text-primary);
    margin: 8px 0 10px;
    font-weight: 700;
    line-height: 1.25rem;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    max-height: calc(1.25rem * 3);
    min-height: calc(1.25rem * 3);
}

.lecture-speaker {
    display: flex;
    align-items: flex-start; /* Alterado de 'center' para 'flex-start' para alinhar o ícone no topo */
    gap: 8px;
    font-size: 1.15rem;
    color: var(--brand-purple-dark);
    margin-bottom: 10px;
}

.lecture-speaker span {
    color: var(--accent-gold) !important;
    font-weight: 700;
    line-height: 1.3rem; /* 1. Definimos uma altura de linha fixa */
    display: -webkit-box;
    -webkit-line-clamp: 2; /* 2. Limitamos o texto a 2 linhas */
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    /* 3. Forçamos a altura para ser exatamente 2x a altura da linha */
    min-height: calc(1.3rem * 2); 
    max-height: calc(1.3rem * 2);
}

.lecture-speaker i {
    color: var(--accent-gold);
}

.lecture-summary {
    color: var(--text-secondary);
    font-size: 0.95rem;
    line-height: 1.4rem;
    display: -webkit-box;
    -webkit-line-clamp: 5;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    max-height: calc(1.4rem * 5);
    margin: 0;
    flex-grow: 1;
}

.schedule-actions-bottom {
    margin-top: 20px;
    text-align: center;
}

/* Novos estilos para o título e divisor da agenda */
.agenda-divider {
    border: none;
    height: 1px;
    background-color: rgba(255, 255, 255, 0.15);
    margin: 20px 0 15px;
}

.agenda-title {
    text-align: center;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin: 0 0 15px 0;
}

.agenda-buttons-container {
    display: flex;
    gap: 10px;
    justify-content: center;
}

.btn-agenda {
    flex: 1;
    padding: 10px 15px;
    font-size: 0.9rem;
    justify-content: center;
}

.btn-google-cal {
    background: linear-gradient(135deg, #4285F4, #357ae8);
    box-shadow: 0 4px 15px rgba(66, 133, 244, 0.4);
}

.btn-apple-cal {
    background: linear-gradient(135deg, #f0f0f0, #e0e0e0);
    color: #333;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.btn-google-cal:hover,
.btn-apple-cal:hover {
    background: linear-gradient(135deg, #2ecc71, #27ae60);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(39, 174, 96, 0.4);
}

.btn-agenda.clicked {
    background: linear-gradient(135deg, #27ae60, #229954);
    color: white;
    box-shadow: 0 4px 15px rgba(39, 174, 96, 0.5);
    cursor: default;
}

.main-content .video-card {
    margin-bottom: 20px;
}

/* Novo Estilo para Alinhar Título e Botões */
.card-header-with-action {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 25px 30px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.card-header-with-action h2 {
    margin: 0 !important;
}

.header-button-group {
    display: flex;
    gap: 10px;
}

.btn-small { 
    padding: 8px 16px; 
    font-size: 0.85rem; 
}

/* =================================================================== */
/* SOLUÇÃO DEFINITIVA DE ALINHAMENTO PARA OS CARDS DA VIDEOTECA      */
/* =================================================================== */

/* 1. Transforma o card inteiro em um container flexível vertical */
.video-card {
    display: flex !important;
    flex-direction: column !important;
}

/* 2. Garante que a área de informações ocupe todo o espaço vertical disponível */
.video-info {
    display: flex !important;
    flex-direction: column !important; /* Organiza os itens de texto em coluna */
    flex-grow: 1 !important;         /* ESSENCIAL: Faz esta área crescer para preencher o card */
    padding: 20px; /* Adiciona um padding padrão caso ele se perca */
}

/* 3. Mantém a altura fixa para TÍTULO e PALESTRANTE para consistência */
.video-info h3 {
    font-size: 1rem;
    line-height: 1.3rem;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    min-height: calc(1.3rem * 3);
    max-height: calc(1.3rem * 3);
}

.video-speaker {
    font-size: 0.95rem;
    line-height: 1.2rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    min-height: calc(1.2rem * 2);
    max-height: calc(1.2rem * 2);
    margin-bottom: 10px;
}

/* 4. Faz a DESCRIÇÃO ocupar o espaço que sobrar no meio */
.video-desc {
    flex-grow: 1; /* ESSENCIAL: Faz a descrição se expandir para preencher o espaço vazio */
    font-size: 0.8rem;
    line-height: 1.4rem;
    display: -webkit-box;
    -webkit-line-clamp: 4; /* Limita a descrição a 4 linhas no máximo */
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* 5. Empurra a seção "Colocar na minha lista" para o fundo do card */
.watchlist-section {
    margin-top: auto !important; /* A MÁGICA: Empurra este item para o final do container flexível */
    padding-top: 15px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

</style>

<?php
include __DIR__ . '/vision/includes/header.php';
include __DIR__ . '/vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content" style="text-align: center;">
            <h1 class="videoteca-title">
                <i class="fas fa-video"></i>
                Videoteca
            </h1>
            <p>Aproveite nossas palestras especializadas em tradução e interpretação</p>
        </div>
    </div>

    <?php if (!empty($upcomingLectures)): ?>
    <section class="upcoming-lectures fade-item">
        <div class="glass-section">
            <h2 class="section-title section-title-centered">Próximas palestras</h2>
            <p class="section-subtitle">Não perca os próximos eventos exclusivos da Translators101</p>

            <div class="lectures-grid" id="lecturesContainer">
                <?php
                date_default_timezone_set('America/Sao_Paulo');
                foreach ($upcomingLectures as $lecture):
                    $announcementDate = $lecture['announcement_date'] ?? '';
                    $lectureTime = $lecture['lecture_time'] ?? '19:00:00';
                    $defaultDuration = 90;

                    try {
                        $dateTimeStart = new DateTime($announcementDate . ' ' . $lectureTime);
                    } catch (Exception $e) {
                        $dateTimeStart = new DateTime($announcementDate . ' 19:00:00'); // Fallback
                    }
                    
                    $dateTimeEnd = clone $dateTimeStart;
                    $dateTimeEnd->modify("+{$defaultDuration} minutes");

                    $formattedDate = $dateTimeStart->format('d \d\e F, Y');
                    $formattedTime = $dateTimeStart->format('H:i');
                    $monthNames = [
                        'January' => 'Janeiro', 'February' => 'Fevereiro', 'March' => 'Março',
                        'April' => 'Abril', 'May' => 'Maio', 'June' => 'Junho',
                        'July' => 'Julho', 'August' => 'Agosto', 'September' => 'Setembro',
                        'October' => 'Outubro', 'November' => 'Novembro', 'December' => 'Dezembro'
                    ];
                    $formattedDate = str_replace(array_keys($monthNames), array_values($monthNames), $formattedDate);

                    // Gerar dados para o link
                    $eventData = [
                        'title' => $lecture['title'] ?? 'Palestra T101',
                        'description' => $lecture['description'] ?? 'Sem descrição.',
                        'speaker' => $lecture['speaker'] ?? 'Palestrante',
                        'start' => $dateTimeStart->format('Ymd\THis'), 
                        'end' => $dateTimeEnd->format('Ymd\THis'),
                        'start_utc' => $dateTimeStart->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z'),
                        'end_utc' => $dateTimeEnd->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z'),
                        'reminder' => 30,
                    ];
                ?>
                <div class="lecture-card fade-item" id="lecture-<?php echo htmlspecialchars($lecture['id']); ?>">
                    <div class="lecture-image-container">
                        <img src="<?php echo htmlspecialchars($lecture['image_path'] ?? '/images/palestra-placeholder.jpg'); ?>" alt="Palestra" class="lecture-image">
                    </div>
                    <div class="lecture-info">
                        <div class="lecture-datetime">
                            <div class="lecture-date"><?php echo htmlspecialchars($formattedDate); ?></div>
                            <div class="lecture-time"><?php echo htmlspecialchars($formattedTime); ?>h</div>
                        </div>
                        <h4 class="lecture-title"><?php echo htmlspecialchars($lecture['title']); ?></h4>
                        <div class="lecture-speaker">
                            <i class="fas fa-user"></i>
                            <span><?php echo htmlspecialchars($lecture['speaker']); ?></span>
                        </div>
                        <p class="lecture-summary">
                            <?php echo htmlspecialchars($lecture['description']); ?>
                        </p>
                    </div>
                    <div class="schedule-actions-bottom">
                        <hr class="agenda-divider">
                        <h4 class="agenda-title">Incluir na minha agenda</h4>
                        <div class="agenda-buttons-container">
                             <a href="#" 
                                class="cta-btn btn-agenda btn-google-cal" 
                                data-event='<?php echo htmlspecialchars(json_encode($eventData), ENT_QUOTES, 'UTF-8'); ?>'
                                onclick="generateGoogleCalendarLink(event)">
                                <i class="fab fa-google"></i> Google
                            </a>
                            <a href="#" 
                                class="cta-btn btn-agenda btn-apple-cal" 
                                data-event='<?php echo htmlspecialchars(json_encode($eventData), ENT_QUOTES, 'UTF-8'); ?>'
                                onclick="generateIcs(event)">
                                <i class="fab fa-apple"></i> Apple
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>


    <div class="videoteca-filtros">
        <form method="GET" action="" class="filtros-form">
            <div class="filtros-grid-improved">
                <div class="search-field-expanded">
                    <label class="form-label">Buscar por palavra-chave</label>
                    <input type="text" 
                           name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Digite o titulo, palestrante ou descricao..."
                           class="search-input-large">
                </div>
                
                <div class="category-field">
                    <label class="form-label">Categoria</label>
                    <select name="category" class="category-select">
                        <option value="">Todas as categorias</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" 
                                    <?php echo ($category === $cat) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="sort-field">
                    <label class="form-label">Ordenar por</label>
                    <select name="sort" class="sort-select">
                        <option value="season_desc" <?php echo ($sort_order === 'season_desc') ? 'selected' : ''; ?>>Temporada (decrescente)</option>
                        <option value="season_asc" <?php echo ($sort_order === 'season_asc') ? 'selected' : ''; ?>>Temporada (crescente)</option>
                        <option value="date_desc" <?php echo ($sort_order === 'date_desc') ? 'selected' : ''; ?>>Mais recentes</option>
                        <option value="date_asc" <?php echo ($sort_order === 'date_asc') ? 'selected' : ''; ?>>Mais antigas</option>
                        <option value="alpha_asc" <?php echo ($sort_order === 'alpha_asc') ? 'selected' : ''; ?>>Alfabetica (A-Z)</option>
                        <option value="alpha_desc" <?php echo ($sort_order === 'alpha_desc') ? 'selected' : ''; ?>>Alfabetica (Z-A)</option>
                    </select>
                </div>
                
                <div class="search-btn-container">
                    <button type="submit" class="cta-btn search-btn">
                        <i class="fas fa-search"></i>
                        Buscar
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php if (function_exists('isAdmin') && isAdmin()): ?>
    <div class="admin-section">
        <div class="video-card">
            <div class="card-header-with-action">
                <h3><i class="fas fa-cog"></i> Administração</h3>
                <div class="admin-actions">
                    <a href="/admin/palestras.php" class="cta-btn admin-btn">
                        <i class="fas fa-plus"></i> Gerenciar palestras
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($lectures)): ?>
        <div class="video-grid video-grid-four">
            <?php foreach ($lectures as $lecture): ?>
                <div class="video-card" onclick="location.href='/palestra.php?id=<?php echo $lecture['id']; ?>'">
                    <div class="video-thumb-container">
                        <div class="video-thumb">
                            <?php if (!empty($lecture['thumbnail_url'])): ?>
                                <img src="<?php echo htmlspecialchars($lecture['thumbnail_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($lecture['title']); ?>"
                                     class="video-image">
                            <?php else: ?>
                                <div class="video-placeholder">
                                    <i class="fas fa-video placeholder-icon"></i>
                                    <span class="placeholder-text">Palestra</span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="video-overlay">
                                <div class="play-button">
                                    <i class="fas fa-play"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="video-info">
                        <h3><?php echo htmlspecialchars($lecture['title']); ?></h3>
                        
                        <?php if (!empty($lecture['speaker'])): ?>
                            <div class="video-speaker"><?php echo htmlspecialchars($lecture['speaker']); ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($lecture['description'])): ?>
                            <p class="video-desc"><?php echo htmlspecialchars(substr($lecture['description'], 0, 100)); ?>...</p>
                        <?php endif; ?>
                        
                        <div class="video-meta">
                            <?php if (!empty($lecture['category'])): ?>
                                <div class="video-category">
                                    <?php echo htmlspecialchars($lecture['category']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($lecture['duration_minutes'])): ?>
                                <div class="video-duration">
                                    <i class="fas fa-clock"></i>
                                    <?php echo $lecture['duration_minutes']; ?>min
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($lecture['is_featured']) && $lecture['is_featured']): ?>
                            <div class="featured-section">
                                <span class="tag featured-tag">
                                    <i class="fas fa-star"></i> Destaque
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isLoggedIn()): ?>
                            <div class="watchlist-section">
                                <?php if (in_array($lecture['id'], $user_watched)): ?>
                                    <div class="watched-indicator">
                                        <i class="fas fa-check-circle"></i>
                                        <span>Assistida</span>
                                    </div>
                                <?php else: ?>
                                    <?php $isInWatchlist = in_array($lecture['id'], $user_watchlist); ?>
                                    <label class="watchlist-checkbox" onclick="event.stopPropagation();">
                                        <input type="checkbox" 
                                               class="watchlist-input" 
                                               data-lecture-id="<?php echo $lecture['id']; ?>"
                                               <?php echo $isInWatchlist ? 'checked' : ''; ?>
                                               onchange="toggleWatchlist(this, '<?php echo $lecture['id']; ?>')">
                                        <span class="checkmark"></span>
                                        <span class="watchlist-text">
                                            <?php echo $isInWatchlist ? 'Na minha lista' : 'Colocar na minha lista'; ?>
                                        </span>
                                    </label>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-search"></i>
            </div>
            <h2 class="empty-title">Nenhuma palestra encontrada</h2>
            <p class="empty-description">
                Tente ajustar os filtros de busca ou escolher uma categoria diferente.
            </p>
            <a href="?search=&category=&sort=season_desc" class="cta-btn">
                <i class="fas fa-refresh"></i>
                Limpar filtros
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleWatchlist(checkbox, lectureId) {
    const isChecked = checkbox.checked;
    const watchlistText = checkbox.parentElement.querySelector('.watchlist-text');
    
    watchlistText.textContent = isChecked ? 'Na minha lista' : 'Colocar na minha lista';
    
    fetch('/api_watchlist.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            lecture_id: lectureId,
            action: isChecked ? 'add' : 'remove'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            checkbox.checked = !isChecked;
            watchlistText.textContent = !isChecked ? 'Na minha lista' : 'Colocar na minha lista';
            alert('Erro: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        checkbox.checked = !isChecked;
        watchlistText.textContent = !isChecked ? 'Na minha lista' : 'Colocar na minha lista';
        alert('Erro de conexão: ' + error.message);
    });
}

function escapeIcs(value) {
    if (!value) return '';
    return value
        .replace(/\\/g, '\\\\')
        .replace(/,/g, '\\,')
        .replace(/;/g, '\\;')
        .replace(/\n/g, '\\n')
        .replace(/\r/g, ''); 
}

function showFeedback(btn, isGoogle) {
    btn.innerHTML = `<i class="fas fa-check"></i> ${isGoogle ? 'Aberto' : 'Baixado'}`;
    btn.classList.add('clicked');
    btn.onclick = function(event) { event.preventDefault(); };
    btn.style.cursor = 'default';
}

function generateGoogleCalendarLink(e) {
    e.preventDefault();
    const btn = e.currentTarget;
    const eventData = JSON.parse(btn.getAttribute('data-event'));

    const baseUrl = 'https://www.google.com/calendar/render?action=TEMPLATE';
    const params = new URLSearchParams({
        'text': `${eventData.title} com ${eventData.speaker}`,
        'dates': `${eventData.start_utc.replace(/\.000Z$/, 'Z')}/${eventData.end_utc.replace(/\.000Z$/, 'Z')}`,
        'details': `${eventData.description}\n\nPalestrante: ${eventData.speaker}\n\nAssista em: https://translators101.com/v/live-stream`,
        'location': 'Translators101 - Online'
    });
    
    window.open(baseUrl + '&' + params.toString(), '_blank');
    showFeedback(btn, true);
}

function generateIcs(e) {
    e.preventDefault();
    const btn = e.currentTarget;
    const eventData = JSON.parse(btn.getAttribute('data-event'));
    
    const title = eventData.title || "Evento Translators101";
    const description = eventData.description || "Palestra Exclusiva da Translators101";
    const speaker = eventData.speaker || "Palestrante";
    const start = eventData.start; 
    const end = eventData.end; 
    const reminder = eventData.reminder; 
    
    const escapedTitle = escapeIcs(title);
    const escapedDescription = escapeIcs(description);
    const escapedSpeaker = escapeIcs(speaker);

    const icsContent = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Translators101//Live Reminder//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        `DTSTART;TZID=America/Sao_Paulo:${start}`,
        `DTEND;TZID=America/Sao_Paulo:${end}`,
        `SUMMARY;CHARSET=UTF-8:${escapedTitle} com ${escapedSpeaker}`,
        `DESCRIPTION;CHARSET=UTF-8:${escapedDescription}\\n\\nPalestrante: ${escapedSpeaker}\\n\\nAssista em: https://translators101.com/v/live-stream`,
        `LOCATION;CHARSET=UTF-8:Translators101 - Online`,
        `UID:${Date.now()}-${Math.random().toString(36).substring(2, 9)}@translators101.com.br`,
        `DTSTAMP:${new Date().toISOString().replace(/[-:]|\.\d{3}/g, '')}Z`,
        'BEGIN:VALARM',
        'ACTION:DISPLAY',
        `DESCRIPTION;CHARSET=UTF-8:Lembrete: ${escapedTitle}`,
        `TRIGGER:-PT${reminder}M`,
        'END:VALARM',
        'END:VEVENT',
        'END:VCALENDAR'
    ].join('\r\n');
    
    const safeTitle = title.replace(/[\\/:\*?"<>|]/g, '_').substring(0, 40).trim();
    const filename = `Lembrete_${safeTitle}.ics`;
    
    const blob = new Blob([icsContent], { type: 'text/calendar;charset=utf-8' });

    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showFeedback(btn, false);
}
</script>

<style>
.cta-btn {
    display: inline-block;
    padding: 14px 28px;
    font-size: 1.1rem;
    font-weight: bold;
    border-radius: 30px;
    background: var(--brand-purple);
    color: #fff;
    text-decoration: none;
    box-shadow: 0 6px 18px rgba(142, 68, 173, 0.6);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: none;
    cursor: pointer;
}

.section-title-centered {
    text-align: center;
}

.section-subtitle {
    text-align: center;
    font-size: 1.2rem;
    color: #f0f0f0;
    margin-bottom: 40px;
    font-style: italic;
}

.glass-hero {
    padding: 2rem 1rem !important;
    margin-bottom: 20px !important;
}
</style>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>