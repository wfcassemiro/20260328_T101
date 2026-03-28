<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');

// ==========================================
// 1. CONFIGURAÇÕES GERAIS
// ==========================================

// Caminhos
$path_includes = __DIR__ . '/vision/includes/';
$db_file = __DIR__ . '/../config/database.php';

// Conexão DB
if (file_exists($db_file)) {
    require_once $db_file;
} else {
    // Fallback
    $host = 'localhost'; $db = 'u335416710_t101_db'; $user = 'u335416710_t101'; $pass = 'Pa392ap!';
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    } catch (PDOException $e) { die("Erro DB: " . $e->getMessage()); }
}

// ==========================================
// 2. CONTROLE DE ACESSO E SESSÃO
// ==========================================

if (!function_exists('isLoggedIn')) { function isLoggedIn() { return isset($_SESSION['user_id']); } }
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
    }
}
if (!function_exists('isSubscriber')) {
    function isSubscriber() {
        return isset($_SESSION['is_subscriber']) && $_SESSION['is_subscriber'];
    }
}

$is_logged_in = isLoggedIn();
$user_id = $_SESSION['user_id'] ?? null;
$is_subscriber = false;
$is_admin_user = isAdmin();

if ($is_logged_in) {
    if (function_exists('hasVideotecaAccess')) {
        $is_subscriber = hasVideotecaAccess();
    } else {
        $role = $_SESSION['role'] ?? 'free';
        $is_subscriber = ($role === 'subscriber' || $role === 'admin');
    }
}

// ==========================================
// PROCESSAMENTO DO FORMULÁRIO DE LEADS
// ==========================================
$lead_success_message = '';
$lead_error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cadastrar_lead') {
    if ($is_subscriber || $is_admin_user) {
        $lead_error_message = "Você já é assinante e tem acesso a todo o conteúdo!";
    } else {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $whatsapp = trim($_POST['whatsapp'] ?? '');
        
        if (empty($nome) || empty($email) || empty($whatsapp)) {
            $lead_error_message = "Por favor, preencha todos os campos.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $lead_error_message = "Por favor, insira um e-mail válido.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id FROM leads WHERE email = ?");
                $stmt->execute([$email]);
                
                if ($stmt->fetch()) {
                    $lead_error_message = "Este e-mail já está cadastrado!";
                } else {
                    $id = uniqid('lead_', true);
                    $stmt = $pdo->prepare("INSERT INTO leads (id, nome, email, whatsapp, fonte, created_at) VALUES (?, ?, ?, ?, 'vetor_t101', NOW())");
                    $stmt->execute([$id, $nome, $email, $whatsapp]);
                    
                    $lead_success_message = "Cadastro realizado com sucesso! Você receberá as notificações sobre as próximas palestras.";
                }
            } catch (PDOException $e) {
                error_log("Erro ao cadastrar lead: " . $e->getMessage());
                $lead_error_message = "Ocorreu um erro ao processar seu cadastro. Tente novamente.";
            }
        }
    }
}

// Carrega Minha Lista (Watchlist)
$user_watchlist = [];
$user_watched = [];
if ($is_logged_in && isset($pdo)) {
    try {
        $stmt = $pdo->prepare('SELECT lecture_id FROM user_watchlist WHERE user_id = ?');
        $stmt->execute([$user_id]);
        $watchlist_items = $stmt->fetchAll();
        foreach ($watchlist_items as $item) $user_watchlist[] = $item['lecture_id'];
    } catch (Exception $e) {}
    
    // Palestras já assistidas (com certificado)
    try {
        $stmt = $pdo->prepare('SELECT DISTINCT lecture_id FROM certificates WHERE user_id = ?');
        $stmt->execute([$user_id]);
        $watched_items = $stmt->fetchAll();
        foreach ($watched_items as $item) {
            $user_watched[] = $item['lecture_id'];
        }
    } catch (Exception $e) {}
}

// ==========================================
// 3. MOTOR DE RECOMENDAÇÃO MELHORADO
// ==========================================

// Inputs
$roles = $_POST['roles'] ?? [];       
$specs = $_POST['specs'] ?? [];       
$themes = $_POST['themes'] ?? [];
$interest = $_POST['interest'] ?? ''; 
$trilha = $_GET['trilha'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 18;

$results = [];
$searched = false;
$total_results = 0;
$total_pages = 0;
$paged_results = [];
$error_msg = '';
$validation_error = '';

// ==========================================
// TRILHAS ESPECIAIS
// ==========================================
if (!empty($trilha)) {
    $searched = true;
    
    // Mapeamento de trilhas para campos booleanos
    $trilha_mapping = [
        'traducao' => 'is_translation = 1',
        'interpretacao' => 'is_interpretation = 1',
        'iniciante' => 'is_beginner = 1',
        'ferramentas' => 'is_tools = 1',
        'literaria' => 'is_literary = 1',
        'bemestar' => "(is_wellness = 1 OR LOWER(title) LIKE '%bem-estar%' OR LOWER(title) LIKE '%wellness%' OR LOWER(description) LIKE '%bem-estar%' OR LOWER(description) LIKE '%wellness%' OR LOWER(title) LIKE '%burnout%' OR LOWER(description) LIKE '%burnout%' OR LOWER(title) LIKE '%autocuidado%' OR LOWER(description) LIKE '%autocuidado%' OR LOWER(title) LIKE '%saúde mental%' OR LOWER(description) LIKE '%saúde mental%' OR LOWER(title) LIKE '%mindfulness%' OR LOWER(title) LIKE '%meditação%')",
        'legendagem' => 'is_subtitling = 1',
        'games' => 'is_gaming = 1',
        'dublagem' => 'is_dubbing = 1',
        'tecnica' => 'is_technical = 1',
        'medica' => 'is_medical = 1',
        'revisao' => 'is_revision = 1',
        'juridica' => 'is_legal = 1',
    ];
    
    if (isset($trilha_mapping[$trilha])) {
        try {
            $sql = "SELECT * FROM lectures WHERE " . $trilha_mapping[$trilha] . " ORDER BY created_at DESC";
            $stmt = $pdo->query($sql);
            $results = $stmt->fetchAll();
            
            // Adicionar relevance para manter compatibilidade
            foreach ($results as &$result) {
                $result['relevance'] = 50; // Relevância alta para trilhas
            }
            
            // Paginação
            $total_results = count($results);
            $total_pages = ceil($total_results / $limit);
            $offset = ($page - 1) * $limit;
            $paged_results = array_slice($results, $offset, $limit);
            
        } catch (Exception $e) {
            $error_msg = "Erro ao buscar palestras da trilha.";
        }
    }
}

// Mantém filtros na sessão para paginação
if ($_SERVER['REQUEST_METHOD'] === 'POST' || (isset($_GET['page']) && empty($trilha))) {
    $searched = true;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION['vetor_filters'] = [
            'roles' => $roles,
            'specs' => $specs,
            'themes' => $themes,
            'interest' => $interest
        ];
    } elseif (isset($_SESSION['vetor_filters'])) {
        $roles = $_SESSION['vetor_filters']['roles'];
        $specs = $_SESSION['vetor_filters']['specs'];
        $themes = $_SESSION['vetor_filters']['themes'] ?? [];
        $interest = $_SESSION['vetor_filters']['interest'];
    }

    // ==========================================
    // VALIDAÇÃO: MÍNIMO DE 3 CAMPOS
    // ==========================================
    $total_selections = count($roles) + count($specs) + count($themes);
    if (!empty($interest)) $total_selections++;

    if ($total_selections < 3) {
        $validation_error = "Por favor, selecione pelo menos três campos para gerar recomendações mais precisas, ou selecione uma de nossas coleções acima.";
    } else {
        $sql = "SELECT *, 0 as relevance FROM lectures WHERE 1=1";
        $params = [];

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $candidates = $stmt->fetchAll();

            // ==========================================
            // ALGORITMO DE PONTUAÇÃO APRIMORADO
            // ==========================================
            foreach ($candidates as $row) {
                $score = 0;
                
                // Corpus com peso na descrição
                $title = mb_strtolower($row['title']);
                $description = mb_strtolower($row['description']);
                $tags = mb_strtolower($row['tags']);
                $category = mb_strtolower($row['category']);

                // ⚠️ PALAVRA-CHAVE (Obrigatória se preenchida)
                if (!empty($interest)) {
                    $interest_lower = mb_strtolower(trim($interest));
                    $found_interest = false;
                    
                    // Busca no título (peso maior)
                    if (strpos($title, $interest_lower) !== false) {
                        $score += 15;
                        $found_interest = true;
                    }
                    // Busca na descrição (peso médio)
                    elseif (strpos($description, $interest_lower) !== false) {
                        $score += 10;
                        $found_interest = true;
                    }
                    // Busca nas tags/categoria (peso menor)
                    elseif (strpos($tags . ' ' . $category, $interest_lower) !== false) {
                        $score += 5;
                        $found_interest = true;
                    }
                    
                    // Se não encontrou a palavra-chave, descarta
                    if (!$found_interest) {
                        continue;
                    }
                }

                // Pontuação baseada em Roles (Área de Atuação)
                foreach ($roles as $role) {
                    $role_lower = mb_strtolower($role);
                    if (strpos($title, $role_lower) !== false) {
                        $score += 12; // Título
                    } elseif (strpos($description, $role_lower) !== false) {
                        $score += 8; // Descrição
                    } elseif (strpos($tags . ' ' . $category, $role_lower) !== false) {
                        $score += 5; // Tags/Categoria
                    }
                }
                
                // Pontuação baseada em Specs (Especialidade)
                foreach ($specs as $spec) {
                    $spec_lower = mb_strtolower($spec);
                    if (strpos($title, $spec_lower) !== false) {
                        $score += 20; // Título (peso alto)
                    } elseif (strpos($description, $spec_lower) !== false) {
                        $score += 12; // Descrição
                    } elseif (strpos($tags . ' ' . $category, $spec_lower) !== false) {
                        $score += 8; // Tags/Categoria
                    }
                }
                
                // Pontuação baseada em Themes (Temas/Tecnologias)
                foreach ($themes as $theme) {
                    $theme_lower = mb_strtolower($theme);
                    if (strpos($title, $theme_lower) !== false) {
                        $score += 10; // Título
                    } elseif (strpos($description, $theme_lower) !== false) {
                        $score += 6; // Descrição
                    } elseif (strpos($tags . ' ' . $category, $theme_lower) !== false) {
                        $score += 4; // Tags/Categoria
                    }
                }

                // Threshold mínimo de 15 pontos
                if ($score >= 15) {
                    $row['relevance'] = $score;
                    $results[] = $row;
                }
            }

            // Ordenar por relevância
            usort($results, function($a, $b) { return $b['relevance'] <=> $a['relevance']; });

            // Paginação
            $total_results = count($results);
            $total_pages = ceil($total_results / $limit);
            $offset = ($page - 1) * $limit;
            $paged_results = array_slice($results, $offset, $limit);

        } catch (Exception $e) {
            $error_msg = "Erro ao buscar palestras.";
        }
    }
}

$page_title = 'Vetor-T101 - Translators101';
$page_description = 'Encontre o conteúdo ideal para seu momento';

include __DIR__ . '/vision/includes/head.php';
?>

<style>
/* Scroll suave */
html {
    scroll-behavior: smooth;
}

#resultados {
    scroll-margin-top: 20px;
}

/* Identidade visual do videoteca.php mantida */
.vetor-hero {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 40px 30px;
    margin-bottom: 30px;
    text-align: center;
}

.vetor-hero h1 {
    display: flex;
    align-items: center;
    gap: 15px;
    justify-content: center;
    font-size: 2.5rem;
    color: #ffffff;
    margin-bottom: 15px;
}

.vetor-hero p {
    color: var(--text-secondary);
    font-size: 1.15rem;
    margin: 0;
}

/* Trilhas Especiais */
.trilhas-container {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 30px;
}

.trilhas-title {
    color: #ffffff;
    font-size: 1.8rem;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.trilhas-title i {
    color: var(--accent-gold);
}

.trilhas-subtitle {
    color: var(--text-secondary);
    font-size: 1rem;
    margin-bottom: 25px;
}

.trilhas-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
}

@media (max-width: 1200px) {
    .trilhas-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .trilhas-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .trilhas-grid {
        grid-template-columns: 1fr;
    }
}

.trilha-btn {
    background: linear-gradient(135deg, rgba(142, 68, 173, 0.3), rgba(94, 51, 112, 0.3));
    border: 2px solid var(--brand-purple);
    border-radius: 12px;
    padding: 18px 15px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.trilha-btn:before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    transition: left 0.5s ease;
}

.trilha-btn:hover:before {
    left: 100%;
}

.trilha-btn:hover {
    background: linear-gradient(135deg, rgba(142, 68, 173, 0.5), rgba(94, 51, 112, 0.5));
    border-color: var(--accent-gold);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(142, 68, 173, 0.6);
}

.trilha-btn i {
    font-size: 2rem;
    color: var(--accent-gold);
    transition: transform 0.3s ease;
}

.trilha-btn:hover i {
    transform: scale(1.15);
}

.trilha-name {
    color: #ffffff;
    font-size: 0.95rem;
    font-weight: 700;
    text-align: center;
}

.trilha-count {
    background: rgba(247, 147, 30, 0.2);
    color: var(--accent-gold);
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 700;
    border: 1px solid var(--accent-gold);
}

/* Cores únicas para cada trilha */

/* 1. Tradução - Azul Royal */
.trilha-traducao {
    background: linear-gradient(135deg, rgba(41, 128, 185, 0.3), rgba(52, 152, 219, 0.3));
    border-color: #3498db;
}
.trilha-traducao:hover {
    background: linear-gradient(135deg, rgba(41, 128, 185, 0.5), rgba(52, 152, 219, 0.5));
    border-color: #2980b9;
}
.trilha-traducao i {
    color: #3498db;
}

/* 2. Iniciante - Verde */
.trilha-destaque {
    background: linear-gradient(135deg, rgba(46, 204, 113, 0.3), rgba(39, 174, 96, 0.3));
    border-color: #2ecc71;
}
.trilha-destaque:hover {
    background: linear-gradient(135deg, rgba(46, 204, 113, 0.5), rgba(39, 174, 96, 0.5));
    border-color: #27ae60;
}
.trilha-destaque i {
    color: #2ecc71;
}

/* 3. Interpretação - Roxo */
.trilha-interpretacao {
    background: linear-gradient(135deg, rgba(142, 68, 173, 0.3), rgba(155, 89, 182, 0.3));
    border-color: #9b59b6;
}
.trilha-interpretacao:hover {
    background: linear-gradient(135deg, rgba(142, 68, 173, 0.5), rgba(155, 89, 182, 0.5));
    border-color: #8e44ad;
}
.trilha-interpretacao i {
    color: #9b59b6;
}

/* 4. Ferramentas - Cinza/Prata */
.trilha-ferramentas {
    background: linear-gradient(135deg, rgba(127, 140, 141, 0.3), rgba(149, 165, 166, 0.3));
    border-color: #95a5a6;
}
.trilha-ferramentas:hover {
    background: linear-gradient(135deg, rgba(127, 140, 141, 0.5), rgba(149, 165, 166, 0.5));
    border-color: #7f8c8d;
}
.trilha-ferramentas i {
    color: #bdc3c7;
}

/* 5. Literária - Rosa/Magenta */
.trilha-literaria {
    background: linear-gradient(135deg, rgba(236, 112, 99, 0.3), rgba(231, 76, 60, 0.3));
    border-color: #e74c3c;
}
.trilha-literaria:hover {
    background: linear-gradient(135deg, rgba(236, 112, 99, 0.5), rgba(231, 76, 60, 0.5));
    border-color: #c0392b;
}
.trilha-literaria i {
    color: #ec7063;
}

/* 6. Bem-estar - Turquesa/Ciano */
.trilha-wellness {
    background: linear-gradient(135deg, rgba(26, 188, 156, 0.3), rgba(22, 160, 133, 0.3));
    border-color: #1abc9c;
}
.trilha-wellness:hover {
    background: linear-gradient(135deg, rgba(26, 188, 156, 0.5), rgba(22, 160, 133, 0.5));
    border-color: #16a085;
}
.trilha-wellness i {
    color: #1abc9c;
}

/* 7. Legendagem - Azul Escuro */
.trilha-legendagem {
    background: linear-gradient(135deg, rgba(52, 73, 94, 0.3), rgba(44, 62, 80, 0.3));
    border-color: #2980b9;
}
.trilha-legendagem:hover {
    background: linear-gradient(135deg, rgba(52, 73, 94, 0.5), rgba(44, 62, 80, 0.5));
    border-color: #2c3e50;
}
.trilha-legendagem i {
    color: #7f8c8d;
}

/* 8. Games - Verde Limão */
.trilha-gaming {
    background: linear-gradient(135deg, rgba(154, 205, 50, 0.3), rgba(124, 179, 66, 0.3));
    border-color: #9acd32;
}
.trilha-gaming:hover {
    background: linear-gradient(135deg, rgba(154, 205, 50, 0.5), rgba(124, 179, 66, 0.5));
    border-color: #7cb342;
}
.trilha-gaming i {
    color: #9acd32;
}

/* 9. Dublagem - Laranja */
.trilha-dublagem {
    background: linear-gradient(135deg, rgba(230, 126, 34, 0.3), rgba(211, 84, 0, 0.3));
    border-color: #e67e22;
}
.trilha-dublagem:hover {
    background: linear-gradient(135deg, rgba(230, 126, 34, 0.5), rgba(211, 84, 0, 0.5));
    border-color: #d35400;
}
.trilha-dublagem i {
    color: #e67e22;
}

/* 10. Técnica - Azul Petróleo */
.trilha-tecnica {
    background: linear-gradient(135deg, rgba(23, 165, 137, 0.3), rgba(17, 122, 101, 0.3));
    border-color: #17a589;
}
.trilha-tecnica:hover {
    background: linear-gradient(135deg, rgba(23, 165, 137, 0.5), rgba(17, 122, 101, 0.5));
    border-color: #117a65;
}
.trilha-tecnica i {
    color: #17a589;
}

/* 11. Médica/Saúde - Vermelho */
.trilha-medical {
    background: linear-gradient(135deg, rgba(192, 57, 43, 0.3), rgba(231, 76, 60, 0.3));
    border-color: #c0392b;
}
.trilha-medical:hover {
    background: linear-gradient(135deg, rgba(192, 57, 43, 0.5), rgba(231, 76, 60, 0.5));
    border-color: #e74c3c;
}
.trilha-medical i {
    color: #e74c3c;
}

/* 12. Jurídica - Dourado/Amarelo */
.trilha-legal {
    background: linear-gradient(135deg, rgba(243, 156, 18, 0.3), rgba(241, 196, 15, 0.3));
    border-color: #f39c12;
}
.trilha-legal:hover {
    background: linear-gradient(135deg, rgba(243, 156, 18, 0.5), rgba(241, 196, 15, 0.5));
    border-color: #f1c40f;
}
.trilha-legal i {
    color: #f39c12;
}

/* Estado ativo da trilha */
.trilha-active {
    background: linear-gradient(135deg, var(--accent-gold), #e68a00) !important;
    border-color: #ffffff !important;
    box-shadow: 0 8px 20px rgba(247, 147, 30, 0.8) !important;
    transform: scale(1.05);
}

.trilha-active i {
    color: #2c3e50 !important;
    animation: pulse 2s infinite;
}

.trilha-active .trilha-name {
    color: #2c3e50 !important;
}

.trilha-active .trilha-count {
    background: rgba(44, 62, 80, 0.3);
    color: #2c3e50 !important;
    border-color: #2c3e50 !important;
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
}

/* Perguntas guiadas */
.guided-questions {
    background: linear-gradient(135deg, rgba(142, 68, 173, 0.2), rgba(94, 51, 112, 0.2));
    border: 2px solid var(--brand-purple);
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 30px;
}

.guided-questions h3 {
    color: var(--accent-gold);
    font-size: 1.3rem;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.question-item {
    background: rgba(255, 255, 255, 0.05);
    border-left: 4px solid var(--brand-purple);
    padding: 15px 20px;
    margin-bottom: 15px;
    border-radius: 8px;
    color: #ffffff;
    font-size: 1rem;
    line-height: 1.6;
}

.question-item strong {
    color: var(--accent-gold);
}

/* Alerta de validação */
.validation-alert {
    background: linear-gradient(135deg, rgba(231, 76, 60, 0.2), rgba(192, 57, 43, 0.2));
    border: 2px solid #e74c3c;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    color: #ffffff;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 15px;
}

.validation-alert i {
    font-size: 2rem;
    color: #e74c3c;
}

/* Seção de Filtros */
.videoteca-filtros {
    margin-bottom: 30px;
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 30px;
}

.filter-section-title {
    color: var(--brand-purple-light);
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.9rem;
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 2px solid var(--brand-purple);
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 25px;
    margin-bottom: 25px;
}

@media (max-width: 1200px) {
    .filter-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
}

.filter-column {
    display: flex;
    flex-direction: column;
}

.custom-checkbox-wrapper {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.custom-checkbox-wrapper.two-columns {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px 15px;
}

@media (max-width: 768px) {
    .custom-checkbox-wrapper.two-columns {
        grid-template-columns: 1fr;
    }
}

.custom-checkbox {
    display: flex;
    align-items: center;
    cursor: pointer;
    font-size: 0.95rem;
    color: #ffffff;
    user-select: none;
    padding: 8px;
    border-radius: 8px;
    transition: background 0.3s ease;
}

.custom-checkbox:hover {
    background: rgba(255, 255, 255, 0.05);
}

.custom-checkbox input[type="checkbox"],
.custom-checkbox input[type="radio"] {
    display: none;
}

.checkbox-mark {
    height: 20px;
    width: 20px;
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.4);
    border-radius: 4px;
    margin-right: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    position: relative;
    flex-shrink: 0;
}

.checkbox-mark:before {
    content: '';
    position: absolute;
    left: 4px;
    top: 1px;
    width: 5px;
    height: 10px;
    border: solid #ffffff;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.custom-checkbox input:checked + .checkbox-mark {
    background: linear-gradient(135deg, var(--brand-purple), #5e3370);
    border-color: var(--brand-purple);
}

.custom-checkbox input:checked + .checkbox-mark:before {
    opacity: 1;
}

.search-input-large {
    width: 100%;
    padding: 12px 16px;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.18);
    color: #ffffff;
    font-size: 1rem;
    font-weight: 600;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.6);
    transition: all 0.3s ease;
}

.search-input-large::placeholder {
    color: rgba(255, 255, 255, 0.6);
}

.search-input-large:focus {
    outline: none;
    border-color: var(--brand-purple);
    background: rgba(255, 255, 255, 0.25);
    box-shadow: 0 0 0 3px rgba(142, 68, 173, 0.3);
}

/* Botões de ação */
.action-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.search-btn-full {
    width: 100%;
    padding: 14px;
    font-size: 1.1rem;
    font-weight: bold;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--brand-purple), #5e3370);
    color: #fff;
    border: none;
    cursor: pointer;
    box-shadow: 0 6px 18px rgba(142, 68, 173, 0.6);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.search-btn-full:hover {
    background: linear-gradient(135deg, #5e3370, var(--brand-purple));
    box-shadow: 0 8px 22px rgba(142, 68, 173, 0.8);
    transform: translateY(-2px);
}

.clear-btn {
    width: 100%;
    padding: 14px;
    font-size: 1.1rem;
    font-weight: bold;
    border-radius: 12px;
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: #fff;
    border: none;
    cursor: pointer;
    box-shadow: 0 6px 18px rgba(231, 76, 60, 0.6);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.clear-btn:hover {
    background: linear-gradient(135deg, #c0392b, #e74c3c);
    box-shadow: 0 8px 22px rgba(231, 76, 60, 0.8);
    transform: translateY(-2px);
}

.results-header {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 20px 30px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.results-count {
    font-size: 1.5rem;
    font-weight: 700;
    color: #ffffff;
}

.results-count span {
    color: var(--accent-gold);
}

.clear-trilha-btn {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: #ffffff;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.clear-trilha-btn:hover {
    background: linear-gradient(135deg, #c0392b, #e74c3c);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(231, 76, 60, 0.6);
}

.video-grid-four {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 25px;
    margin-bottom: 40px;
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

.video-card {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    height: 100%;
    position: relative;
}

.video-card:hover {
    border-color: var(--brand-purple);
    box-shadow: 0 20px 40px rgba(142, 68, 173, 0.6);
    transform: translateY(-5px);
}

/* BOLINHA DE RELEVÂNCIA */
.relevance-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    z-index: 20;
    cursor: help;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.relevance-badge[data-level="very-low"] {
    background: #3498db;
}

.relevance-badge[data-level="low"] {
    background: #1abc9c;
}

.relevance-badge[data-level="medium"] {
    background: #2ecc71;
}

.relevance-badge[data-level="high"] {
    background: #f39c12;
}

.relevance-badge[data-level="very-high"] {
    background: #e74c3c;
}

.relevance-tooltip {
    position: absolute;
    top: 12px;
    right: 38px;
    background: rgba(0, 0, 0, 0.9);
    color: white;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 700;
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
    z-index: 25;
    white-space: nowrap;
}

.relevance-badge:hover + .relevance-tooltip {
    opacity: 1;
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
}

.video-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    color: rgba(255, 255, 255, 0.7);
    font-size: 1.5rem;
    text-align: center;
}

.placeholder-icon {
    font-size: 3rem;
    margin-bottom: 10px;
}

.video-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.video-card:hover .video-overlay {
    opacity: 1;
}

.play-button {
    background: rgba(255, 255, 255, 0.9);
    color: var(--brand-purple);
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.4);
    transition: transform 0.3s ease;
}

.play-button:hover {
    transform: scale(1.1);
}

.video-info {
    display: flex;
    flex-direction: column;
    flex-grow: 1;
    padding: 20px;
}

.video-info h3 {
    font-size: 1.05rem;
    line-height: 1.35rem;
    color: #ffffff;
    margin-bottom: 10px;
    font-weight: 700;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    min-height: calc(1.35rem * 3);
    max-height: calc(1.35rem * 3);
}

.video-speaker {
    font-size: 0.95rem;
    line-height: 1.2rem;
    color: var(--accent-gold);
    font-weight: 600;
    margin-bottom: 10px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    min-height: calc(1.2rem * 2);
    max-height: calc(1.2rem * 2);
}

.video-desc {
    font-size: 0.85rem;
    line-height: 1.4rem;
    color: var(--text-secondary);
    flex-grow: 1;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.video-category {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #ffffff;
    padding: 5px 12px;
    border-radius: 12px;
    border: 2px solid #1e40af;
    font-size: 0.75rem;
    font-weight: 700;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.7);
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.4);
    display: inline-block;
    margin-bottom: 8px;
    text-transform: capitalize;
}

.watchlist-section {
    margin-top: auto;
    padding-top: 15px;
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

.pagination-wrapper {
    display: flex;
    justify-content: center;
    margin-top: 40px;
    margin-bottom: 40px;
}

.pagination {
    display: flex;
    gap: 8px;
    align-items: center;
}

.page-link {
    padding: 10px 16px;
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    color: #ffffff;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
}

.page-link:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: var(--brand-purple);
    transform: translateY(-2px);
}

.page-link.active {
    background: linear-gradient(135deg, var(--brand-purple), #5e3370);
    border-color: var(--brand-purple);
    box-shadow: 0 4px 12px rgba(142, 68, 173, 0.6);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    border-radius: 16px;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    backdrop-filter: blur(20px);
    margin-top: 40px;
}

.empty-icon {
    font-size: 5rem;
    color: var(--brand-purple-light);
    margin-bottom: 20px;
    opacity: 0.6;
}

.empty-title {
    font-size: 2rem;
    color: #ffffff;
    margin-bottom: 15px;
}

.empty-description {
    font-size: 1.1rem;
    color: var(--text-secondary);
    max-width: 500px;
    margin: 0 auto 30px;
    line-height: 1.6;
}

.premium-cta {
    background: linear-gradient(135deg, rgba(142, 68, 173, 0.9), rgba(94, 51, 112, 0.9));
    backdrop-filter: blur(20px);
    border: 2px solid var(--brand-purple);
    border-radius: 20px;
    padding: 40px;
    text-align: center;
    margin-top: 50px;
    box-shadow: 0 10px 30px rgba(142, 68, 173, 0.4);
}

.premium-cta h3 {
    font-size: 2rem;
    color: #ffffff;
    margin-bottom: 15px;
    font-weight: 700;
}

.premium-cta p {
    font-size: 1.2rem;
    color: var(--text-secondary);
    margin-bottom: 25px;
}

.cta-btn {
    display: inline-block;
    padding: 16px 40px;
    font-size: 1.2rem;
    font-weight: bold;
    border-radius: 30px;
    background: linear-gradient(135deg, var(--accent-gold), #e68a00);
    color: #2c3e50;
    text-decoration: none;
    box-shadow: 0 6px 18px rgba(247, 147, 30, 0.6);
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.cta-btn:hover {
    background: linear-gradient(135deg, #e68a00, var(--accent-gold));
    box-shadow: 0 8px 22px rgba(247, 147, 30, 0.8);
    transform: translateY(-3px);
}

.initial-message {
    text-align: center;
    padding: 80px 20px;
    color: #ffffff;
}

.initial-message i {
    font-size: 6rem;
    color: var(--brand-purple-light);
    margin-bottom: 30px;
    opacity: 0.8;
}

.initial-message h2 {
    font-size: 2.5rem;
    margin-bottom: 20px;
    font-weight: 700;
}

.initial-message p {
    font-size: 1.3rem;
    color: var(--text-secondary);
    max-width: 600px;
    margin: 0 auto 30px;
    line-height: 1.6;
}

/* Lead Section */
.lead-section {
    background: linear-gradient(135deg, rgba(236, 72, 153, 0.15), rgba(139, 92, 246, 0.1));
    border: 1px solid rgba(236, 72, 153, 0.3);
    border-radius: 20px;
    padding: 40px 30px;
    margin-bottom: 40px;
    text-align: center;
}

.lead-section h2 {
    color: #ec4899;
    margin-bottom: 15px;
    font-size: 1.8rem;
}

.lead-section p {
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 20px;
    font-size: 1.05rem;
}

.prize-text {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(234, 179, 8, 0.1));
    border: 1px solid rgba(245, 158, 11, 0.4);
    padding: 12px 25px;
    border-radius: 30px;
    margin-bottom: 25px;
    font-weight: 600;
    color: #fbbf24;
}

.prize-text i {
    animation: prize-pulse 2s infinite;
}

@keyframes prize-pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.2); }
}

/* Lead Form */
.lead-form {
    max-width: 500px;
    margin: 0 auto;
    text-align: left;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: white;
    font-weight: 600;
    font-size: 0.95rem;
}

.form-group label i {
    margin-right: 8px;
    color: #c084fc;
}

.form-group input {
    width: 100%;
    padding: 14px 16px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.3);
    color: white;
    font-size: 1rem;
    transition: all 0.3s;
    box-sizing: border-box;
}

.form-group input:focus {
    outline: none;
    border-color: #c084fc;
    box-shadow: 0 0 0 3px rgba(192, 132, 252, 0.15);
}

.form-group input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

/* Phone Input Container */
.phone-input-container {
    display: flex;
    gap: 10px;
}

.country-input-wrapper {
    display: flex;
    flex-direction: column;
    gap: 5px;
    width: 160px;
    flex-shrink: 0;
}

.country-code-input {
    width: 100% !important;
    padding: 14px 10px !important;
    text-align: center;
    font-weight: 600;
    font-size: 1.1rem !important;
}

.country-select {
    width: 100%;
    padding: 8px 5px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(0, 0, 0, 0.3);
    color: white;
    font-size: 0.8rem;
    cursor: pointer;
}

.country-select:focus {
    outline: none;
    border-color: #c084fc;
}

.country-select optgroup {
    background: #1a1a1a;
    color: #c084fc;
    font-weight: 600;
}

.country-select option {
    background: #2a2a2a;
    color: white;
    padding: 5px;
}

.phone-input-container input[type="tel"] {
    flex: 1;
}

.phone-hint {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.5);
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.phone-hint i {
    color: #10b981;
}

.submit-btn {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, #ec4899, #8b5cf6);
    border: none;
    border-radius: 10px;
    color: white;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(236, 72, 153, 0.3);
}

/* Alerts */
.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-success {
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #10b981;
}

.alert-error {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

/* Responsive para formulário */
@media (max-width: 768px) {
    .lead-section {
        padding: 30px 20px;
    }
    
    .phone-input-container {
        flex-direction: column;
    }
    
    .country-input-wrapper {
        width: 100%;
    }
}
</style>

<?php
include __DIR__ . '/vision/includes/header.php';
include __DIR__ . '/vision/includes/sidebar.php';
?>

<div class="main-content">
    <!-- Hero Section -->
    
    <div class="glass-hero">
        <div class="hero-content"><h1><i class="fas fa-brain"></i> Vetor-T101</h1><p>Encontre o conteúdo ideal para seu momento</p></div>
    </div>
    
    <!-- Mensagens de alerta do formulário -->
    <?php if ($lead_success_message): ?>
    <div class="alert alert-success" id="successAlert">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($lead_success_message); ?>
    </div>
    <?php endif; ?>

    <?php if ($lead_error_message): ?>
    <div class="alert alert-error" id="errorAlert">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($lead_error_message); ?>
    </div>
    <?php endif; ?>

    <!-- Seção de captura de leads (apenas para não-assinantes) - TOPO -->
    <?php if (!$is_subscriber && !$is_admin_user): ?>
    <div class="lead-section" id="cadastro">
        <h2><i class="fas fa-bell"></i> Não perca nenhuma palestra!</h2>
        <p>Cadastre-se para receber notificações sobre as próximas palestras gratuitas.</p>
        
        <div class="prize-text">
            <i class="fas fa-trophy"></i>
            Concorra a 7 dias de acesso grátis no sorteio mensal!
        </div>

        <form method="POST" action="#cadastro" class="lead-form">
            <input type="hidden" name="action" value="cadastrar_lead">
            
            <div class="form-group">
                <label for="nome"><i class="fas fa-user"></i> Seu nome</label>
                <input type="text" id="nome" name="nome" placeholder="Digite seu nome completo" required>
            </div>

            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Seu e-mail</label>
                <input type="email" id="email" name="email" placeholder="seuemail@exemplo.com" required>
            </div>

            <div class="form-group">
                <label for="whatsapp"><i class="fab fa-whatsapp"></i> Seu WhatsApp (com código do país)</label>
                <div class="phone-input-container">
                    <div class="country-input-wrapper">
                        <input type="text" id="country_code" class="country-code-input" value="+55" maxlength="5" placeholder="+55" required>
                        <select id="country_select" class="country-select" onchange="selectCountry(this)">
                            <option value="">Selecione...</option>
                            <optgroup label="América do Sul">
                                <option value="+55">🇧🇷 Brasil (+55)</option>
                                <option value="+54">🇦🇷 Argentina (+54)</option>
                                <option value="+56">🇨🇱 Chile (+56)</option>
                                <option value="+57">🇨🇴 Colômbia (+57)</option>
                                <option value="+51">🇵🇪 Peru (+51)</option>
                                <option value="+598">🇺🇾 Uruguai (+598)</option>
                            </optgroup>
                            <optgroup label="América do Norte e Central">
                                <option value="+1">🇺🇸 EUA / 🇨🇦 Canadá (+1)</option>
                                <option value="+52">🇲🇽 México (+52)</option>
                            </optgroup>
                            <optgroup label="Europa">
                                <option value="+351">🇵🇹 Portugal (+351)</option>
                                <option value="+34">🇪🇸 Espanha (+34)</option>
                                <option value="+33">🇫🇷 França (+33)</option>
                                <option value="+49">🇩🇪 Alemanha (+49)</option>
                                <option value="+44">🇬🇧 Reino Unido (+44)</option>
                                <option value="+39">🇮🇹 Itália (+39)</option>
                                <option value="+41">🇨🇭 Suíça (+41)</option>
                            </optgroup>
                            <optgroup label="África">
                                <option value="+244">🇦🇴 Angola (+244)</option>
                                <option value="+258">🇲🇿 Moçambique (+258)</option>
                                <option value="+238">🇨🇻 Cabo Verde (+238)</option>
                            </optgroup>
                            <optgroup label="Outros">
                                <option value="+81">🇯🇵 Japão (+81)</option>
                                <option value="+61">🇦🇺 Austrália (+61)</option>
                            </optgroup>
                            <option value="outro">✏️ Outro (digitar código)</option>
                        </select>
                    </div>
                    <input type="tel" id="whatsapp_number" placeholder="99999-9999" required>
                </div>
                <input type="hidden" id="whatsapp" name="whatsapp">
                <p class="phone-hint">
                    <i class="fas fa-info-circle"></i>
                    Selecione seu país ou digite o código manualmente. Ex: +55 para Brasil
                </p>
                <p style="font-size: 0.75rem; color: rgba(255, 255, 255, 0.5); margin-top: 8px;">
                    Não se preocupe: os dados serão usados somente para informações sobre as palestras e do resultado do sorteio.
                </p>
            </div>

            <p style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.6); text-align: center; margin: 15px 0;">
                <i class="fas fa-shield-alt" style="color: #10b981; margin-right: 5px;"></i>
                Seus dados estão seguros conosco e não serão compartilhados com terceiros.
            </p>

            <button type="submit" class="submit-btn">
                <i class="fas fa-paper-plane"></i> Quero participar!
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Trilhas Especiais -->
    <?php
    // Buscar contagem de palestras por trilha
    $trilhas = [];
    try {
        $stmt = $pdo->query("SELECT 
            SUM(is_translation) as trilha_traducao,
            SUM(is_interpretation) as trilha_interpretacao,
            SUM(is_beginner) as trilha_iniciante,
            SUM(is_tools) as trilha_ferramentas,
            SUM(is_literary) as trilha_literaria,
            SUM(is_wellness) as trilha_bemestar,
            SUM(is_subtitling) as trilha_legendagem,
            SUM(is_gaming) as trilha_games,
            SUM(is_dubbing) as trilha_dublagem,
            SUM(is_technical) as trilha_tecnica,
            SUM(is_medical) as trilha_medica,
            SUM(is_revision) as trilha_revisao,
            SUM(is_legal) as trilha_juridica
        FROM lectures");
        $trilhas = $stmt->fetch();
    } catch (Exception $e) {
        // Fallback se der erro
        $trilhas = [
            'trilha_traducao' => 277,
            'trilha_interpretacao' => 129,
            'trilha_iniciante' => 250,
            'trilha_ferramentas' => 86,
            'trilha_literaria' => 52,
            'trilha_bemestar' => 48,
            'trilha_legendagem' => 36,
            'trilha_games' => 28,
            'trilha_dublagem' => 21,
            'trilha_tecnica' => 16,
            'trilha_medica' => 14,
            'trilha_revisao' => 14,
            'trilha_juridica' => 10,
        ];
    }
    ?>
    
    <div class="trilhas-container fade-item">
        <h3 class="trilhas-title">
            <i class="fas fa-route"></i>
            Trilhas Especiais
        </h3>
        <p class="trilhas-subtitle">Explore nossas coleções de palestras por tema</p>
        
        <div class="trilhas-grid">
            <a href="vetor.php?trilha=traducao#resultados" class="trilha-btn trilha-traducao <?= $trilha === 'traducao' ? 'trilha-active' : '' ?>" data-trilha="traducao">
                <i class="fas fa-language"></i>
                <span class="trilha-name">Tradução</span>
                <span class="trilha-count"><?= $trilhas['trilha_traducao'] ?></span>
            </a>
            
            <a href="vetor.php?trilha=iniciante#resultados" class="trilha-btn trilha-destaque <?= $trilha === 'iniciante' ? 'trilha-active' : '' ?>" data-trilha="iniciante">
                <i class="fas fa-seedling"></i>
                <span class="trilha-name">Iniciante</span>
                <span class="trilha-count"><?= $trilhas['trilha_iniciante'] ?></span>
            </a>
            
            <a href="vetor.php?trilha=interpretacao#resultados" class="trilha-btn trilha-interpretacao <?= $trilha === 'interpretacao' ? 'trilha-active' : '' ?>" data-trilha="interpretacao">
                <i class="fas fa-microphone"></i>
                <span class="trilha-name">Interpretação</span>
                <span class="trilha-count"><?= $trilhas['trilha_interpretacao'] ?></span>
            </a>
            
            <a href="vetor.php?trilha=ferramentas#resultados" class="trilha-btn trilha-ferramentas <?= $trilha === 'ferramentas' ? 'trilha-active' : '' ?>" data-trilha="ferramentas">
                <i class="fas fa-tools"></i>
                <span class="trilha-name">Ferramentas</span>
                <span class="trilha-count"><?= $trilhas['trilha_ferramentas'] ?></span>
            </a>
            
            <a href="vetor.php?trilha=literaria#resultados" class="trilha-btn trilha-literaria <?= $trilha === 'literaria' ? 'trilha-active' : '' ?>" data-trilha="literaria">
                <i class="fas fa-book"></i>
                <span class="trilha-name">Literária</span>
                <span class="trilha-count"><?= $trilhas['trilha_literaria'] ?></span>
            </a>
            
            <a href="vetor.php?trilha=bemestar#resultados" class="trilha-btn trilha-wellness <?= $trilha === 'bemestar' ? 'trilha-active' : '' ?>" data-trilha="bemestar">
                <i class="fas fa-spa"></i>
                <span class="trilha-name">Bem-estar</span>
                <span class="trilha-count"><?= $trilhas['trilha_bemestar'] ?></span>
            </a>
            
            <a href="vetor.php?trilha=legendagem#resultados" class="trilha-btn trilha-legendagem <?= $trilha === 'legendagem' ? 'trilha-active' : '' ?>" data-trilha="legendagem">
                <i class="fas fa-closed-captioning"></i>
                <span class="trilha-name">Legendagem</span>
                <span class="trilha-count"><?= $trilhas['trilha_legendagem'] ?></span>
            </a>
            
            <a href="vetor.php?trilha=games#resultados" class="trilha-btn trilha-gaming <?= $trilha === 'games' ? 'trilha-active' : '' ?>" data-trilha="games">
                <i class="fas fa-gamepad"></i>
                <span class="trilha-name">Games</span>
                <span class="trilha-count"><?= $trilhas['trilha_games'] ?></span>
            </a>
            
            <a href="vetor.php?trilha=dublagem#resultados" class="trilha-btn trilha-dublagem <?= $trilha === 'dublagem' ? 'trilha-active' : '' ?>" data-trilha="dublagem">
                <i class="fas fa-film"></i>
                <span class="trilha-name">Dublagem</span>
                <span class="trilha-count"><?= $trilhas['trilha_dublagem'] ?></span>
            </a>
            
            <a href="vetor.php?trilha=tecnica#resultados" class="trilha-btn trilha-tecnica <?= $trilha === 'tecnica' ? 'trilha-active' : '' ?>" data-trilha="tecnica">
                <i class="fas fa-cogs"></i>
                <span class="trilha-name">Técnica</span>
                <span class="trilha-count"><?= $trilhas['trilha_tecnica'] ?></span>
            </a>
            
            <a href="vetor.php?trilha=medica#resultados" class="trilha-btn trilha-medical <?= $trilha === 'medica' ? 'trilha-active' : '' ?>" data-trilha="medica">
                <i class="fas fa-heartbeat"></i>
                <span class="trilha-name">Saúde</span>
                <span class="trilha-count"><?= $trilhas['trilha_medica'] ?></span>
            </a>
            
            <a href="vetor.php?trilha=juridica#resultados" class="trilha-btn trilha-legal <?= $trilha === 'juridica' ? 'trilha-active' : '' ?>" data-trilha="juridica">
                <i class="fas fa-gavel"></i>
                <span class="trilha-name">Jurídica</span>
                <span class="trilha-count"><?= $trilhas['trilha_juridica'] ?></span>
            </a>
        </div>
    </div>

    <!-- Alerta de Validação -->
    <?php if (!empty($validation_error)): ?>
    <div class="validation-alert fade-item">
        <i class="fas fa-exclamation-triangle"></i>
        <div><?= htmlspecialchars($validation_error) ?></div>
    </div>
    <?php endif; ?>

    <!-- Seção de Filtros -->
    <div class="videoteca-filtros fade-item">
        <div class="guided-questions fade-item">
            <h3><i class="fas fa-lightbulb"></i> Selecione pelo menos três itens abaixo, em qualquer campo, para receber recomendações de palestras!</h3>
        </div>        
        <form method="POST" action="vetor.php" id="filterForm">
            <div class="filter-grid">
                <!-- Coluna 1: Área de Atuação -->
                <div class="filter-column">
                    <span class="filter-section-title">
                        <i class="fas fa-briefcase"></i>
                        Área de Atuação
                    </span>
                    <div class="custom-checkbox-wrapper">
                        <label class="custom-checkbox">
                            <input type="checkbox" name="roles[]" value="Tradução" <?= in_array('Tradução', $roles) ? 'checked' : '' ?>>
                            <span class="checkbox-mark"></span>
                            Tradução
                        </label>
                        <label class="custom-checkbox">
                            <input type="checkbox" name="roles[]" value="Interpretação" <?= in_array('Interpretação', $roles) ? 'checked' : '' ?>>
                            <span class="checkbox-mark"></span>
                            Interpretação
                        </label>
                        <label class="custom-checkbox">
                            <input type="checkbox" name="roles[]" value="Legendagem" <?= in_array('Legendagem', $roles) ? 'checked' : '' ?>>
                            <span class="checkbox-mark"></span>
                            Legendagem
                        </label>
                        <label class="custom-checkbox">
                            <input type="checkbox" name="roles[]" value="Localização" <?= in_array('Localização', $roles) ? 'checked' : '' ?>>
                            <span class="checkbox-mark"></span>
                            Localização
                        </label>
                        <label class="custom-checkbox">
                            <input type="checkbox" name="roles[]" value="Revisão" <?= in_array('Revisão', $roles) ? 'checked' : '' ?>>
                            <span class="checkbox-mark"></span>
                            Revisão
                        </label>
                    </div>
                </div>

                <!-- Coluna 2: Especialidade -->
                <div class="filter-column">
                    <span class="filter-section-title">
                        <i class="fas fa-star"></i>
                        Especialidade
                    </span>
                    <div class="custom-checkbox-wrapper two-columns">
                        <label class="custom-checkbox">
                            <input type="checkbox" name="specs[]" value="Jurídica" <?= in_array('Jurídica', $specs) ? 'checked' : '' ?>>
                            <span class="checkbox-mark"></span>
                            Jurídica
                        </label>
                        <label class="custom-checkbox">
                            <input type="checkbox" name="specs[]" value="Médica" <?= in_array('Médica', $specs) ? 'checked' : '' ?>>
                            <span class="checkbox-mark"></span>
                            Saúde
                        </label>
                        <label class="custom-checkbox">
                            <input type="checkbox" name="specs[]" value="Literária" <?= in_array('Literária', $specs) ? 'checked' : '' ?>>
                            <span class="checkbox-mark"></span>
                            Literária
                        </label>
                        <label class="custom-checkbox">
                            <input type="checkbox" name="specs[]" value="Games" <?= in_array('Games', $specs) ? 'checked' : '' ?>>
                            <span class="checkbox-mark"></span>
                            Games
                        </label>
                        <label class="custom-checkbox">
                            <input type="checkbox" name="specs[]" value="Audiovisual" <?= in_array('Audiovisual', $specs) ? 'checked' : '' ?>>
                            <span class="checkbox-mark"></span>
                            Audiovisual
                        </label>
                        <label class="custom-checkbox">
                            <input type="checkbox" name="specs[]" value="Marketing" <?= in_array('Marketing', $specs) ? 'checked' : '' ?>>
                            <span class="checkbox-mark"></span>
                            Marketing
                        </label>
                        <label class="custom-checkbox">
                            <input type="checkbox" name="specs[]" value="Técnica" <?= in_array('Técnica', $specs) ? 'checked' : '' ?>>
                            <span class="checkbox-mark"></span>
                            Técnica
                        </label>
                        <label class="custom-checkbox">
                            <input type="checkbox" name="specs[]" value="Científica" <?= in_array('Científica', $specs) ? 'checked' : '' ?>>
                            <span class="checkbox-mark"></span>
                            Científica
                        </label>
                        <label class="custom-checkbox">
                            <input type="checkbox" name="specs[]" value="Turismo" <?= in_array('Turismo', $specs) ? 'checked' : '' ?>>
                            <span class="checkbox-mark"></span>
                            Turismo
                        </label>
                        <label class="custom-checkbox">
                            <input type="checkbox" name="specs[]" value="Financeira" <?= in_array('Financeira', $specs) ? 'checked' : '' ?>>
                            <span class="checkbox-mark"></span>
                            Financeira
                        </label>
                    </div>
                </div>

                <!-- Coluna 3: Temas/Tecnologias e Palavra-Chave -->
                <div class="filter-column">
                    <span class="filter-section-title">
                        <i class="fas fa-microchip"></i>
                        Temas / Tecnologias
                    </span>
                    <div class="custom-checkbox-wrapper">
                        <label class="custom-checkbox">
                            <input type="checkbox" name="themes[]" value="IA" <?= in_array('IA', $themes) ? 'checked' : '' ?>>
                            <span class="checkbox-mark"></span>
                            Inteligência Artificial
                        </label>
                        <label class="custom-checkbox">
                            <input type="checkbox" name="themes[]" value="Ferramentas" <?= in_array('Ferramentas', $themes) || in_array('CAT', $themes) ? 'checked' : '' ?>>
                            <span class="checkbox-mark"></span>
                            Ferramentas
                        </label>
                        <label class="custom-checkbox">
                            <input type="checkbox" name="themes[]" value="Carreira" <?= in_array('Carreira', $themes) ? 'checked' : '' ?>>
                            <span class="checkbox-mark"></span>
                            Carreira
                        </label>
                        <label class="custom-checkbox">
                            <input type="checkbox" name="themes[]" value="Negócios" <?= in_array('Negócios', $themes) ? 'checked' : '' ?>>
                            <span class="checkbox-mark"></span>
                            Negócios
                        </label>
                        <label class="custom-checkbox">
                            <input type="checkbox" name="themes[]" value="Gestão" <?= in_array('Gestão', $themes) ? 'checked' : '' ?>>
                            <span class="checkbox-mark"></span>
                            Gestão de projetos
                        </label>
                    </div>
                    
                    <span class="filter-section-title" style="margin-top: 20px;">
                        <i class="fas fa-key"></i>
                        Palavra-Chave (Opcional)
                    </span>
                    <input type="text" 
                           name="interest" 
                           class="search-input-large" 
                           placeholder="Ex: Marketing, Tecnologia..." 
                           value="<?= htmlspecialchars($interest) ?>">
                </div>

            </div>

            <!-- Botões de Ação -->
            <div class="action-buttons">
                <button type="submit" class="search-btn-full">
                    <i class="fas fa-search"></i>
                    Buscar recomendações
                </button>
                <button type="button" class="clear-btn" onclick="clearFilters()">
                    <i class="fas fa-times-circle"></i>
                    Limpar tudo
                </button>
            </div>
        </form>
    </div>

    <!-- Seção de Resultados -->
    <?php if ($searched && (empty($validation_error) || !empty($trilha))): ?>
        
        <?php if (count($paged_results) > 0): ?>
            
            <!-- Âncora para scroll -->
            <div id="resultados"></div>
            
            <!-- Header de Resultados -->
            <div class="results-header fade-item">
                <div class="results-count">
                    <?php if (!empty($trilha)): ?>
                        <i class="fas fa-route" style="color: var(--accent-gold); margin-right: 10px;"></i>
                        Trilha: <strong style="color: var(--accent-gold);"><?php 
                            $trilha_names = [
                                'traducao' => 'Tradução',
                                'interpretacao' => 'Interpretação',
                                'iniciante' => 'Iniciante',
                                'ferramentas' => 'Ferramentas',
                                'literaria' => 'Literária',
                                'bemestar' => 'Bem-estar',
                                'legendagem' => 'Legendagem',
                                'games' => 'Games',
                                'dublagem' => 'Dublagem',
                                'tecnica' => 'Técnica',
                                'medica' => 'Médica/Saúde',
                                'revisao' => 'Revisão',
                                'juridica' => 'Jurídica',
                            ];
                            echo $trilha_names[$trilha] ?? ucfirst($trilha);
                        ?></strong> - 
                    <?php endif; ?>
                    <span><?php echo $total_results; ?></span> <?php echo $total_results == 1 ? 'palestra' : 'palestras'; ?>
                </div>
                <?php if (!empty($trilha)): ?>
                    <a href="vetor.php" class="clear-trilha-btn">
                        <i class="fas fa-times"></i> Voltar
                    </a>
                <?php endif; ?>
            </div>

            <!-- Grid de Vídeos -->
            <div class="video-grid video-grid-four">
                <?php foreach ($paged_results as $lecture): 
                    $max_score = 60;
                    $percentage = min(100, ($lecture['relevance'] / $max_score) * 100);
                    
                    if ($lecture['relevance'] < 15) {
                        $color_level = 'very-low';
                    } elseif ($lecture['relevance'] < 25) {
                        $color_level = 'low';
                    } elseif ($lecture['relevance'] < 35) {
                        $color_level = 'medium';
                    } elseif ($lecture['relevance'] < 45) {
                        $color_level = 'high';
                    } else {
                        $color_level = 'very-high';
                    }
                ?>
                    <div class="video-card" onclick="location.href='/palestra.php?id=<?php echo $lecture['id']; ?>'">
                        
                        <div class="video-thumb-container">
                            <!-- Bolinha de Relevância -->
                            <div class="relevance-badge" data-level="<?= $color_level ?>"></div>
                            <div class="relevance-tooltip">
                                <?= $lecture['relevance'] ?> pontos
                            </div>
                            
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
                                <div class="video-speaker">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($lecture['speaker']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($lecture['description'])): ?>
                                <p class="video-desc"><?php echo htmlspecialchars(substr($lecture['description'], 0, 120)); ?>...</p>
                            <?php endif; ?>
                            
                            <?php if (!empty($lecture['category'])): ?>
                                <div class="video-category">
                                    <?php echo htmlspecialchars($lecture['category']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($is_logged_in): ?>
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

            <!-- Paginação -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-wrapper">
                <div class="pagination">
                    <?php for($i=1; $i <= $total_pages; $i++): 
                        // Manter trilha na URL se existir
                        $page_url = !empty($trilha) ? "?trilha=$trilha&page=$i" : "?page=$i";
                    ?>
                        <a href="<?= $page_url ?>#resultados" class="page-link <?= ($i == $page) ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>


        <?php else: ?>
            <div class="empty-state fade-item">
                <div class="empty-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h2 class="empty-title">Nenhum resultado encontrado</h2>
                <p class="empty-description">
                    Não encontramos palestras que correspondam a seus critérios. Tente ajustar os filtros.
                </p>
            </div>
        <?php endif; ?>
        
    <?php elseif (!$searched): ?>
        <div class="initial-message fade-item">
            <h2>Descubra conteúdos incríveis!</h2>
            <p>Cuide de sua estratégia de educação continuada com a Translators101!</p>
        </div>
    <?php endif; ?>


            <!-- CTA Premium -->
        <?php if (!$is_subscriber): ?>
            <div class="premium-cta fade-item">
                <h3><i class="fas fa-crown"></i> Gostou das sugestões?</h3>
                <p>Assine o Premium e tenha acesso imediato a todas as nossas palestras. E com direito a certificado!</p>
                <a href="/#:~:text=Oferta%20especial-,%3A,-Acesso%20completo%20por" class="cta-btn">
                    <i class="fas fa-star"></i> Assinar agora
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

function clearFilters() {
    // Desmarca todos os checkboxes
    document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
    
    // Limpa o campo de texto
    document.querySelector('input[name="interest"]').value = '';
    
    // Recarrega a página para estado inicial
    window.location.href = 'vetor.php';
}

// Smooth scroll para navegadores antigos
document.addEventListener('DOMContentLoaded', function() {
    // Se a URL tem #resultados, rola suavemente após carregamento
    if (window.location.hash === '#resultados') {
        setTimeout(function() {
            const element = document.getElementById('resultados');
            if (element) {
                element.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }, 100);
    }
});

// ========================================
// FUNÇÕES DO FORMULÁRIO DE LEADS
// ========================================

// SELETOR DE PAÍS E CÓDIGO - FORMULÁRIO 1
function selectCountry(select) {
    const value = select.value;
    const codeInput = document.getElementById('country_code');
    
    if (value === 'outro') {
        codeInput.value = '+';
        codeInput.focus();
        codeInput.select();
    } else if (value) {
        codeInput.value = value;
    }
    
    updatePhonePlaceholder(value || codeInput.value, 'whatsapp_number');
    document.getElementById('whatsapp_number').value = '';
}

// SELETOR DE PAÍS E CÓDIGO - FORMULÁRIO 2
function selectCountry2(select) {
    const value = select.value;
    const codeInput = document.getElementById('country_code2');
    
    if (value === 'outro') {
        codeInput.value = '+';
        codeInput.focus();
        codeInput.select();
    } else if (value) {
        codeInput.value = value;
    }
    
    updatePhonePlaceholder(value || codeInput.value, 'whatsapp_number2');
    document.getElementById('whatsapp_number2').value = '';
}

// Permitir digitação manual do código - Formulário 1
document.getElementById('country_code')?.addEventListener('input', function(e) {
    let value = e.target.value;
    if (!value.startsWith('+')) {
        value = '+' + value.replace(/[^0-9]/g, '');
    } else {
        value = '+' + value.substring(1).replace(/[^0-9]/g, '');
    }
    e.target.value = value;
    updatePhonePlaceholder(value, 'whatsapp_number');
});

// Permitir digitação manual do código - Formulário 2
document.getElementById('country_code2')?.addEventListener('input', function(e) {
    let value = e.target.value;
    if (!value.startsWith('+')) {
        value = '+' + value.replace(/[^0-9]/g, '');
    } else {
        value = '+' + value.substring(1).replace(/[^0-9]/g, '');
    }
    e.target.value = value;
    updatePhonePlaceholder(value, 'whatsapp_number2');
});

function updatePhonePlaceholder(code, inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    const placeholders = {
        '+55': '(11) 99999-9999',
        '+1': '(555) 123-4567',
        '+351': '912 345 678',
        '+34': '612 345 678',
        '+33': '6 12 34 56 78',
        '+49': '151 1234 5678',
        '+44': '7911 123456',
        '+39': '333 123 4567',
        '+54': '11 1234-5678',
        '+56': '9 1234 5678',
        '+52': '55 1234 5678'
    };
    
    input.placeholder = placeholders[code] || '1234 5678 9012';
}

// Processar envio do formulário
document.querySelectorAll('.lead-form').forEach(function(form, index) {
    form.addEventListener('submit', function(e) {
        const suffix = index === 0 ? '' : '2';
        const countryCode = document.getElementById('country_code' + suffix).value;
        const phoneNumber = document.getElementById('whatsapp_number' + suffix).value.replace(/\D/g, '');
        const fullNumber = countryCode + phoneNumber;
        document.getElementById('whatsapp' + suffix).value = fullNumber;
        
        if (phoneNumber.length < 7) {
            e.preventDefault();
            alert('Por favor, insira um número de telefone válido (mínimo 7 dígitos).');
            return false;
        }
        
        if (!countryCode.match(/^\+\d{1,4}$/)) {
            e.preventDefault();
            alert('Por favor, insira um código de país válido (ex: +55).');
            return false;
        }
    });
});

// Máscara de telefone - Formulário 1
document.getElementById('whatsapp_number')?.addEventListener('input', function(e) {
    applyPhoneMask(e, 'country_code');
});

// Máscara de telefone - Formulário 2
document.getElementById('whatsapp_number2')?.addEventListener('input', function(e) {
    applyPhoneMask(e, 'country_code2');
});

function applyPhoneMask(e, countryCodeId) {
    let value = e.target.value.replace(/\D/g, '');
    const countryCode = document.getElementById(countryCodeId)?.value || '+55';
    
    let maxLength = 15;
    
    if (countryCode === '+55') {
        maxLength = 11;
        if (value.length > maxLength) value = value.substring(0, maxLength);
        
        if (value.length > 6) {
            if (value.length === 11) {
                value = '(' + value.substring(0, 2) + ') ' + value.substring(2, 7) + '-' + value.substring(7);
            } else if (value.length === 10) {
                value = '(' + value.substring(0, 2) + ') ' + value.substring(2, 6) + '-' + value.substring(6);
            } else {
                value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
            }
        } else if (value.length > 2) {
            value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
        } else if (value.length > 0) {
            value = '(' + value;
        }
    } else if (countryCode === '+1') {
        maxLength = 10;
        if (value.length > maxLength) value = value.substring(0, maxLength);
        
        if (value.length > 6) {
            value = '(' + value.substring(0, 3) + ') ' + value.substring(3, 6) + '-' + value.substring(6);
        } else if (value.length > 3) {
            value = '(' + value.substring(0, 3) + ') ' + value.substring(3);
        } else if (value.length > 0) {
            value = '(' + value;
        }
    } else if (countryCode === '+351') {
        maxLength = 9;
        if (value.length > maxLength) value = value.substring(0, maxLength);
        
        if (value.length > 6) {
            value = value.substring(0, 3) + ' ' + value.substring(3, 6) + ' ' + value.substring(6);
        } else if (value.length > 3) {
            value = value.substring(0, 3) + ' ' + value.substring(3);
        }
    } else {
        if (value.length > maxLength) value = value.substring(0, maxLength);
        
        if (value.length > 8) {
            value = value.substring(0, 4) + ' ' + value.substring(4, 8) + ' ' + value.substring(8);
        } else if (value.length > 4) {
            value = value.substring(0, 4) + ' ' + value.substring(4);
        }
    }
    
    e.target.value = value;
}

// Fade out das mensagens de alerta
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(function(alert) {
        alert.style.transition = 'opacity 1s';
        alert.style.opacity = '0';
        setTimeout(function() {
            alert.remove();
        }, 1000);
    });
}, 10000);
</script>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>