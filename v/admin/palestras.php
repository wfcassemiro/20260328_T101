<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Verificar se é admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: /login.php');
    exit;
}

$page_title = 'Gerenciar Palestras - Admin';
$message = '';
$error = '';

// Processar ações AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => ''];
    
    try {
    if ($action === 'toggle_featured') {
    $lecture_id = trim($_POST['lecture_id'] ?? '');
    if (!empty($lecture_id)) {
    $stmt = $pdo->prepare("UPDATE lectures SET is_featured = NOT is_featured, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$lecture_id]);
    
    // Buscar o novo status
    $stmt = $pdo->prepare("SELECT is_featured FROM lectures WHERE id = ?");
    $stmt->execute([$lecture_id]);
    $result = $stmt->fetch();
    
    $response['success'] = true;
    $response['is_featured'] = (bool)$result['is_featured'];
    $response['message'] = 'Status de destaque atualizado!';
    }
    }
    } catch (Exception $e) {
    $response['message'] = 'Erro: ' . $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// Processar ações normais
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) {
    $action = $_POST['action'] ?? '';
    
    try {
    switch ($action) {
    case 'add_lecture':
    $title = trim($_POST['title'] ?? '');
    $speaker = trim($_POST['speaker'] ?? '');
    $speaker_minibio = trim($_POST['speaker_minibio'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration_minutes = (int)($_POST['duration_minutes'] ?? 0);
    $embed_code = trim($_POST['embed_code'] ?? '');
    $thumbnail_url = trim($_POST['thumbnail_url'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $tags_input = trim($_POST['tags'] ?? '');
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_live = isset($_POST['is_live']) ? 1 : 0;
    $language = trim($_POST['language'] ?? '');
    $level = trim($_POST['level'] ?? '');
    
    // Processar tags para formato JSON válido
    $tags = '[""]'; // Default vazio
    if (!empty($tags_input)) {
    $tags_array = array_map('trim', explode(',', $tags_input));
    $tags_array = array_filter($tags_array); // Remove vazios
    $tags = json_encode($tags_array, JSON_UNESCAPED_UNICODE);
    }
    
    // Processar upload de imagem
    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/v/images/thumbnails/S08-HR/';
    if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
    }
    
    $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (in_array($file_extension, $allowed_extensions)) {
    $new_filename = uniqid('lecture_') . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;
    
    if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
    $image_path = '/v/images/thumbnails/S08-HR/' . $new_filename;
    }
    }
    }
    
    if (empty($title)) {
    throw new Exception('Título é obrigatório');
    }
    
    $stmt = $pdo->prepare("
    INSERT INTO lectures (
    id, title, speaker, speaker_minibio, description, duration_minutes, 
    embed_code, thumbnail_url, image, category, tags, is_featured, is_live, 
    language, level, created_at, updated_at
    ) VALUES (
    UUID(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
    )
    ");
    $stmt->execute([
    $title, $speaker, $speaker_minibio, $description, $duration_minutes,
    $embed_code, $thumbnail_url, $image_path, $category, $tags, $is_featured, $is_live,
    $language, $level
    ]);
    $message = 'Palestra adicionada com sucesso!';
    break;
    
    case 'bulk_upload':
    // Upload em massa via CSV
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    throw new Exception('Nenhum arquivo CSV foi enviado ou houve erro no upload.');
    }
    
    $csv_path = $_FILES['csv_file']['tmp_name'];
    $file_extension = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
    
    if ($file_extension !== 'csv') {
    throw new Exception('O arquivo deve ser um CSV válido.');
    }
    
    // Abrir CSV
    $handle = fopen($csv_path, 'r');
    if (!$handle) {
    throw new Exception('Não foi possível abrir o arquivo CSV.');
    }
    
    // Ler cabeçalho
    $header = fgetcsv($handle, 0, ',');
    if (!$header) {
    throw new Exception('Arquivo CSV vazio ou inválido.');
    }
    
    // Mapear colunas esperadas
    $expected_columns = [
    'title', 'speaker', 'speaker_minibio', 'description', 'duration_minutes',
    'embed_code', 'thumbnail_url', 'category', 'tags', 'is_featured', 
    'is_live', 'language', 'level', 'image_filename'
    ];
    
    $column_map = [];
    foreach ($header as $index => $col) {
    $column_map[trim($col)] = $index;
    }
    
    // Verificar se tem pelo menos a coluna 'title'
    if (!isset($column_map['title'])) {
    throw new Exception('O CSV deve conter pelo menos a coluna "title".');
    }
    
    $inserted_count = 0;
    $error_count = 0;
    $errors = [];
    
    // Processar linhas
    $line_number = 1;
    while (($row = fgetcsv($handle, 0, ',')) !== false) {
    $line_number++;
    
    try {
    // Extrair dados da linha
    $title = isset($column_map['title']) ? trim($row[$column_map['title']] ?? '') : '';
    $speaker = isset($column_map['speaker']) ? trim($row[$column_map['speaker']] ?? '') : '';
    $speaker_minibio = isset($column_map['speaker_minibio']) ? trim($row[$column_map['speaker_minibio']] ?? '') : '';
    $description = isset($column_map['description']) ? trim($row[$column_map['description']] ?? '') : '';
    $duration_minutes = isset($column_map['duration_minutes']) ? (int)($row[$column_map['duration_minutes']] ?? 0) : 0;
    $embed_code = isset($column_map['embed_code']) ? trim($row[$column_map['embed_code']] ?? '') : '';
    $thumbnail_url = isset($column_map['thumbnail_url']) ? trim($row[$column_map['thumbnail_url']] ?? '') : '';
    $category = isset($column_map['category']) ? trim($row[$column_map['category']] ?? '') : '';
    $tags_input = isset($column_map['tags']) ? trim($row[$column_map['tags']] ?? '') : '';
    $is_featured = isset($column_map['is_featured']) ? (int)(trim($row[$column_map['is_featured']] ?? '0')) : 0;
    $is_live = isset($column_map['is_live']) ? (int)(trim($row[$column_map['is_live']] ?? '0')) : 0;
    $language = isset($column_map['language']) ? trim($row[$column_map['language']] ?? '') : '';
    $level = isset($column_map['level']) ? trim($row[$column_map['level']] ?? '') : '';
    $image_filename = isset($column_map['image_filename']) ? trim($row[$column_map['image_filename']] ?? '') : '';
    
    if (empty($title)) {
    throw new Exception("Linha $line_number: Título é obrigatório.");
    }
    
    // Processar tags
    $tags = '[""]';
    if (!empty($tags_input)) {
    $tags_array = array_map('trim', explode(',', $tags_input));
    $tags_array = array_filter($tags_array);
    $tags = json_encode($tags_array, JSON_UNESCAPED_UNICODE);
    }
    
    // Processar imagem (se fornecida)
    $image_path = '';
    if (!empty($image_filename)) {
    $image_full_path = $_SERVER['DOCUMENT_ROOT'] . '/v/images/thumbnails/S08-HR/' . $image_filename;
    if (file_exists($image_full_path)) {
    $image_path = '/v/images/thumbnails/S08-HR/' . $image_filename;
    }
    }
    
    // Inserir no banco
    $stmt = $pdo->prepare("
    INSERT INTO lectures (
    id, title, speaker, speaker_minibio, description, duration_minutes, 
    embed_code, thumbnail_url, image, category, tags, is_featured, is_live, 
    language, level, created_at, updated_at
    ) VALUES (
    UUID(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
    )
    ");
    $stmt->execute([
    $title, $speaker, $speaker_minibio, $description, $duration_minutes,
    $embed_code, $thumbnail_url, $image_path, $category, $tags, $is_featured, $is_live,
    $language, $level
    ]);
    
    $inserted_count++;
    
    } catch (Exception $e) {
    $error_count++;
    $errors[] = $e->getMessage();
    }
    }
    
    fclose($handle);
    
    // Mensagem de resultado
    if ($inserted_count > 0) {
    $message = "✅ $inserted_count palestra(s) importada(s) com sucesso!";
    }
    if ($error_count > 0) {
    $error = "⚠️ $error_count erro(s) encontrado(s):<br>" . implode('<br>', array_slice($errors, 0, 10));
    if (count($errors) > 10) {
    $error .= '<br>... e mais ' . (count($errors) - 10) . ' erro(s).';
    }
    }
    
    break;
    
    case 'toggle_live':
    $lecture_id = trim($_POST['lecture_id'] ?? '');
    if (!empty($lecture_id)) {
    $stmt = $pdo->prepare("UPDATE lectures SET is_live = NOT is_live, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$lecture_id]);
    $message = 'Status ao vivo atualizado!';
    }
    break;
    
    case 'delete_lecture':
    $lecture_id = trim($_POST['lecture_id'] ?? '');
    if (!empty($lecture_id)) {
    $stmt = $pdo->prepare("DELETE FROM lectures WHERE id = ?");
    $stmt->execute([$lecture_id]);
    $message = 'Palestra removida com sucesso!';
    }
    break;
    }
    } catch (Exception $e) {
    $error = 'Erro ao processar ação: ' . $e->getMessage();
    }
}

// Buscar palestras
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$level_filter = $_GET['level'] ?? '';

try {
    $where_conditions = [];
    $params = [];
    
    if ($search) {
    $where_conditions[] = "(title LIKE ? OR speaker LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    }
    
    if ($category_filter) {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
    }
    
    if ($level_filter) {
    $where_conditions[] = "level = ?";
    $params[] = $level_filter;
    }
    
    $where_clause = '';
    if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    // Query com todos os campos da tabela
    $stmt = $pdo->prepare("
    SELECT 
    id, title, speaker, speaker_minibio, description, duration_minutes,
    embed_code, thumbnail_url, image, category, tags, is_featured, is_live,
    language, level, created_at, updated_at
    FROM lectures 
    $where_clause 
    ORDER BY created_at DESC
    ");
    $stmt->execute($params);
    $lectures = $stmt->fetchAll();
    
    // Buscar categorias para filtro
    $stmt = $pdo->query("SELECT DISTINCT category FROM lectures WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Buscar níveis para filtro
    $stmt = $pdo->query("SELECT DISTINCT level FROM lectures WHERE level IS NOT NULL AND level != '' ORDER BY level");
    $levels = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $lectures = [];
    $categories = [];
    $levels = [];
    $error = 'Erro ao carregar palestras: ' . $e->getMessage();
}

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
    <div class="hero-content">
    <h1><i class="fas fa-video"></i> Gerenciar Palestras</h1>
    <p>Administre o conteúdo educacional completo da plataforma</p>
    <a href="index.php" class="cta-btn">
    <i class="fas fa-arrow-left"></i> Voltar ao Admin
    </a>
    </div>
    </div>

    <?php if ($message): ?>
    <div class="alert-success">
    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert-error">
    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <!-- NOVO: Formulário de Upload em Massa -->
    <div class="vision-form">
    <div class="card-header">
    <h2><i class="fas fa-file-upload"></i> Importar Palestras em Massa (CSV)</h2>
    </div>
    
    <div style="padding: 20px; background: rgba(255,255,255,0.05); border-radius: 12px; margin-bottom: 20px;">
    <p style="color: #ccc; margin-bottom: 12px;">
    <i class="fas fa-info-circle"></i> 
    <strong>Como usar:</strong> Faça upload de um arquivo CSV com as colunas abaixo. 
    Apenas a coluna <code>title</code> é obrigatória.
    </p>
    <details style="color: #aaa; font-size: 0.9rem;">
    <summary style="cursor: pointer; margin-bottom: 8px;"><strong>📋 Colunas aceitas no CSV</strong></summary>
    <ul style="margin: 8px 0; padding-left: 20px;">
    <li><code>title</code> (obrigatório)</li>
    <li><code>speaker</code></li>
    <li><code>speaker_minibio</code></li>
    <li><code>description</code></li>
    <li><code>duration_minutes</code></li>
    <li><code>embed_code</code></li>
    <li><code>thumbnail_url</code></li>
    <li><code>category</code></li>
    <li><code>tags</code> (separadas por vírgula)</li>
    <li><code>is_featured</code> (0 ou 1)</li>
    <li><code>is_live</code> (0 ou 1)</li>
    <li><code>language</code> (ex: pt-BR, en, es)</li>
    <li><code>level</code> (ex: Iniciante, Intermediário, Avançado)</li>
    <li><code>image_filename</code> (nome do arquivo já na pasta <code>/v/images/thumbnails/S08-HR/</code>)</li>
    </ul>
    </details>
    
    <a href="#" onclick="downloadCSVTemplate(); return false;" class="page-btn" style="margin-top: 12px;">
    <i class="fas fa-download"></i> Baixar Modelo CSV
    </a>
    </div>
    
    <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="bulk_upload">
    
    <div class="form-group">
    <label for="csv_file"><i class="fas fa-file-csv"></i> Arquivo CSV</label>
    <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
    <small style="color: #ccc; display: block; margin-top: 4px;">
    Selecione um arquivo CSV com as palestras a serem importadas.
    </small>
    </div>
    
    <div class="form-actions">
    <button type="submit" class="cta-btn">
    <i class="fas fa-cloud-upload-alt"></i> Importar Palestras
    </button>
    </div>
    </form>
    </div>

    <!-- Formulário Individual -->
    <div class="vision-form">
    <div class="card-header">
    <h2><i class="fas fa-plus-circle"></i> Adicionar Nova Palestra (Individual)</h2>
    </div>
    
    <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="add_lecture">
    
    <div class="form-grid-improved">
    <div class="form-group">
    <label for="title"><i class="fas fa-heading"></i> Título *</label>
    <input type="text" id="title" name="title" required maxlength="500">
    </div>
    
    <div class="form-group">  
    <label for="speaker"><i class="fas fa-user"></i> Palestrante</label>
    <input type="text" id="speaker" name="speaker" maxlength="255">
    </div>
    
    <div class="form-group">
    <label for="category"><i class="fas fa-tag"></i> Categoria</label>
    <input type="text" id="category" name="category" maxlength="100" 
    placeholder="ex: Tradução, Interpretação, etc.">
    </div>
    
    <div class="form-group">
    <label for="duration_minutes"><i class="fas fa-clock"></i> Duração (min)</label>
    <input type="number" id="duration_minutes" name="duration_minutes" min="0" max="600" 
    placeholder="ex: 60">
    </div>
    
    <div class="form-group">
    <label for="level"><i class="fas fa-layer-group"></i> Nível</label>
    <select id="level" name="level">
    <option value="">Selecionar nível</option>
    <option value="Iniciante">Iniciante</option>
    <option value="Intermediário">Intermediário</option>
    <option value="Avançado">Avançado</option>
    <option value="Todos">Todos os níveis</option>
    </select>
    </div>
    
    <div class="form-group">
    <label for="language"><i class="fas fa-language"></i> Idioma</label>
    <select id="language" name="language">
    <option value="">Selecionar idioma</option>
    <option value="pt-BR">Português (BR)</option>
    <option value="en">Inglês</option>
    <option value="es">Espanhol</option>
    <option value="fr">Francês</option>
    </select>
    </div>
    
    <div class="form-group form-group-wide">
    <label for="speaker_minibio"><i class="fas fa-id-card"></i> Mini-bio do Palestrante</label>
    <textarea id="speaker_minibio" name="speaker_minibio" rows="2" maxlength="500"
    placeholder="Breve biografia do palestrante..."></textarea>
    </div>
    
    <div class="form-group form-group-wide">
    <label for="description"><i class="fas fa-align-left"></i> Descrição</label>
    <textarea id="description" name="description" rows="3" maxlength="2000"
    placeholder="Descreva o conteúdo da palestra..."></textarea>
    </div>
    
    <div class="form-group form-group-wide">
    <label for="embed_code"><i class="fas fa-code"></i> Código de Embed</label>
    <textarea id="embed_code" name="embed_code" rows="3" maxlength="2000"
    placeholder="Cole aqui o código de embed do vídeo..."></textarea>
    </div>
    
    <div class="form-group form-group-wide">
    <label for="thumbnail_url"><i class="fas fa-image"></i> URL da Thumbnail</label>
    <input type="url" id="thumbnail_url" name="thumbnail_url" maxlength="1000"
    placeholder="https://exemplo.com/thumbnail.jpg">
    </div>
    
    <div class="form-group form-group-wide">
    <label for="image"><i class="fas fa-upload"></i> Upload de Imagem da Palestra</label>
    <input type="file" id="image" name="image" accept="image/*"
    title="Formatos aceitos: JPG, PNG, GIF, WebP">
    <small style="color: #ccc; display: block; margin-top: 4px;">
    Opcional: Faça upload de uma imagem para a palestra (máx. 5MB). Será salva em <code>/v/images/thumbnails/S08-HR/</code>
    </small>
    </div>
    
    <div class="form-group form-group-wide">
    <label for="tags"><i class="fas fa-tags"></i> Tags (separadas por vírgula)</label>
    <input type="text" id="tags" name="tags" maxlength="500"
    placeholder="tradução, técnica, business, legal">
    </div>
    
    <div class="form-group checkbox-group">
    <label>
    <input type="checkbox" name="is_featured" value="1">
    <i class="fas fa-star"></i> Destacar palestra
    </label>
    </div>
    
    <div class="form-group checkbox-group">
    <label>
    <input type="checkbox" name="is_live" value="1">
    <i class="fas fa-broadcast-tower"></i> Ao vivo
    </label>
    </div>
    </div>
    
    <div class="form-actions">
    <button type="submit" class="cta-btn">
    <i class="fas fa-save"></i> Adicionar Palestra
    </button>
    </div>
    </form>
    </div>

    <!-- Filtros Avançados -->
    <div class="vision-form">
    <div class="card-header">
    <h2><i class="fas fa-filter"></i> Filtros Avançados</h2>
    </div>
    
    <form method="GET">
    <div class="row g-3">
    <div class="col-md-4">
    <input type="text" class="form-control" name="search" 
    placeholder="Buscar por título, palestrante..." 
    value="<?php echo htmlspecialchars($search); ?>">
    </div>
    
    <div class="col-md-4">
    <select name="category" class="form-control">
    <option value="">Todas as categorias</option>
    <?php foreach ($categories as $category): ?>
    <option value="<?php echo htmlspecialchars($category); ?>" 
    <?php echo $category_filter === $category ? 'selected' : ''; ?>>
    <?php echo htmlspecialchars($category); ?>
    </option>
    <?php endforeach; ?>
    </select>
    </div>
    
    <div class="col-md-4">
    <select name="level" class="form-control">
    <option value="">Todos os níveis</option>
    <?php foreach ($levels as $level): ?>
    <option value="<?php echo htmlspecialchars($level); ?>" 
    <?php echo $level_filter === $level ? 'selected' : ''; ?>>
    <?php echo htmlspecialchars($level); ?>
    </option>
    <?php endforeach; ?>
    </select>
    </div>
    </div>
    
    <div class="d-flex justify-content-end mt-3 gap-2">
    <button type="submit" class="cta-btn">
    <i class="fas fa-search"></i> Filtrar
    </button>
    <a href="palestras.php" class="page-btn">
    <i class="fas fa-times"></i> Limpar
    </a>
    </div>
    </form>
    </div>

    <!-- Lista Completa -->
    <div class="vision-table">
    <div class="card-header">
    <h2><i class="fas fa-list"></i> Palestras Cadastradas (<?php echo count($lectures); ?>)</h2>
    </div>
    
    <?php if (empty($lectures)): ?>
    <div class="empty-state">
    <i class="fas fa-video-slash"></i>
    <h3>Nenhuma palestra encontrada</h3>
    <p>Adicione a primeira palestra ou ajuste os filtros de busca.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
    <table class="data-table">
    <thead>
    <tr>
    <th><i class="fas fa-video"></i> Palestra</th>
    <th><i class="fas fa-user"></i> Palestrante</th>
    <th><i class="fas fa-tag"></i> Categoria</th>
    <th><i class="fas fa-clock"></i> Duração</th>
    <th><i class="fas fa-star"></i> Status</th>
    <th><i class="fas fa-cogs"></i> Ações</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($lectures as $lecture): ?>
    <tr>
    <td>
    <div class="project-info">
    <span class="text-primary" title="<?php echo htmlspecialchars($lecture['title']); ?>">
    <?php echo htmlspecialchars(substr($lecture['title'], 0, 60)); ?>
    <?php if (strlen($lecture['title']) > 60) echo '...'; ?>
    </span>
    <span class="project-client">
    <?php 
    $description = $lecture['description'] ?? '';
    echo htmlspecialchars(substr($description, 0, 80));
    if (strlen($description) > 80) echo '...';
    ?>
    </span>
    <?php if (!empty($lecture['level'])): ?>
    <span class="level-badge"><?php echo htmlspecialchars($lecture['level']); ?></span>
    <?php endif; ?>
    </div>
    </td>
    <td>
    <?php echo htmlspecialchars($lecture['speaker'] ?: '-'); ?>
    <?php if (!empty($lecture['language'])): ?>
    <br><small style="color: #888;"><?php echo htmlspecialchars($lecture['language']); ?></small>
    <?php endif; ?>
    </td>
    <td><?php echo htmlspecialchars($lecture['category'] ?: 'Geral'); ?></td>
    <td>
    <?php 
    $duration = (int)($lecture['duration_minutes'] ?? 0);
    echo $duration > 0 ? $duration . ' min' : '-'; 
    ?>
    </td>
    <td>
    <?php $isFeatured = (bool)($lecture['is_featured'] ?? false); ?>
    <?php $isLive = (bool)($lecture['is_live'] ?? false); ?>
    
    <?php if ($isLive): ?>
    <span class="status-badge status-live">AO VIVO</span>
    <?php elseif ($isFeatured): ?>
    <span class="status-badge status-completed">DESTAQUE</span>
    <?php else: ?>
    <span class="status-badge status-cancelled">PADRÃO</span>
    <?php endif; ?>
    </td>
    <td>
    <div class="action-buttons">
    <!-- Toggle Destaque com AJAX -->
    <button type="button" 
    class="page-btn featured-toggle-btn" 
    data-lecture-id="<?php echo htmlspecialchars($lecture['id']); ?>"
    data-featured="<?php echo $isFeatured ? '1' : '0'; ?>"
    title="Alternar Destaque">
    <i class="fas fa-star<?php echo $isFeatured ? '' : '-o'; ?>" 
    style="color: <?php echo $isFeatured ? '#f1c40f' : '#95a5a6'; ?>"></i>
    </button>
    
    <!-- Toggle Live -->
    <form method="POST" style="display: inline;">
    <input type="hidden" name="action" value="toggle_live">
    <input type="hidden" name="lecture_id" value="<?php echo htmlspecialchars($lecture['id']); ?>">
    <button type="submit" class="page-btn" title="Alternar Ao Vivo">
    <?php $isLive = (bool)($lecture['is_live'] ?? false); ?>
    <i class="fas fa-broadcast-tower" style="color: <?php echo $isLive ? '#e74c3c' : '#95a5a6'; ?>"></i>
    </button>
    </form>
    
    <!-- Editar -->
    <a href="editar_palestra.php?id=<?php echo htmlspecialchars($lecture['id']); ?>" 
    class="page-btn" title="Editar Palestra">
    <i class="fas fa-edit"></i>
    </a>
    
    <!-- Ver Embed -->
    <?php if (!empty(trim($lecture['embed_code'] ?? ''))): ?>
    <button class="page-btn" onclick="showEmbedModal('<?php echo htmlspecialchars($lecture['id']); ?>')" title="Ver Embed">
    <i class="fas fa-code"></i>
    </button>
    <?php endif; ?>
    
    <!-- Deletar -->
    <form method="POST" style="display: inline;" 
    onsubmit="return confirm('Tem certeza que deseja remover esta palestra?');">
    <input type="hidden" name="action" value="delete_lecture">
    <input type="hidden" name="lecture_id" value="<?php echo htmlspecialchars($lecture['id']); ?>">
    <button type="submit" class="page-btn" style="color: #e74c3c;" title="Remover Palestra">
    <i class="fas fa-trash"></i>
    </button>
    </form>
    </div>
    </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
    </div>
    <?php endif; ?>
    </div>

    <!-- Estatísticas Completas -->
    <div class="stats-grid" style="margin-top: 40px;">
    <div class="card stats-card">
    <div class="stats-content">
    <div class="stats-info">
    <h3>Total de Palestras</h3>
    <span class="stats-number"><?php echo count($lectures); ?></span>
    </div>
    <div class="stats-icon stats-icon-blue">
    <i class="fas fa-video"></i>
    </div>
    </div>
    </div>
    
    <div class="card stats-card">
    <div class="stats-content">
    <div class="stats-info">
    <h3>Em Destaque</h3>
    <?php $featuredLectures = array_filter($lectures, function($l) { return (bool)($l['is_featured'] ?? false); }); ?>
    <span class="stats-number"><?php echo count($featuredLectures); ?></span>
    </div>
    <div class="stats-icon stats-icon-green">
    <i class="fas fa-star"></i>
    </div>
    </div>
    </div>
    
    <div class="card stats-card">
    <div class="stats-content">
    <div class="stats-info">
    <h3>Ao Vivo</h3>
    <?php $liveLectures = array_filter($lectures, function($l) { return (bool)($l['is_live'] ?? false); }); ?>
    <span class="stats-number"><?php echo count($liveLectures); ?></span>
    </div>
    <div class="stats-icon stats-icon-red">
    <i class="fas fa-broadcast-tower"></i>
    </div>
    </div>
    </div>
    
    <div class="card stats-card">
    <div class="stats-content">
    <div class="stats-info">
    <h3>Duração Total</h3>
    <?php 
    $totalDuration = array_sum(array_map(function($l) { return (int)($l['duration_minutes'] ?? 0); }, $lectures));
    $hours = floor($totalDuration / 60);
    $minutes = $totalDuration % 60;
    ?>
    <span class="stats-number"><?php echo $hours; ?>h <?php echo $minutes; ?>m</span>
    </div>
    <div class="stats-icon stats-icon-purple">
    <i class="fas fa-clock"></i>
    </div>
    </div>
    </div>
    </div>
</div>

<style>
/* Layout melhorado para o formulário */
.form-grid-improved {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.form-group-wide {
    grid-column: 1 / -1;
}

.checkbox-group {
    display: flex;
    align-items: center;
    min-height: 60px;
}

.checkbox-group label {
    display: flex;
    align-items: center;
    cursor: pointer;
    font-size: 14px;
    color: #fff;
    margin: 0;
}

.checkbox-group input[type="checkbox"] {
    margin-right: 8px;
    transform: scale(1.2);
}

/* Responsividade melhorada */
@media (max-width: 768px) {
    .form-grid-improved {
    grid-template-columns: 1fr;
    gap: 15px;
    }
}

@media (min-width: 769px) and (max-width: 1024px) {
    .form-grid-improved {
    grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1025px) {
    .form-grid-improved {
    grid-template-columns: repeat(3, 1fr);
    }
}

/* Estilos específicos para a página de palestras */
.form-group textarea {
    resize: vertical;
    min-height: 60px;
}

.form-group input[type="file"] {
    width: 100%;
    padding: 8px 12px;
    border: 2px dashed rgba(255, 255, 255, 0.3);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.05);
    color: #fff;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.form-group input[type="file"]:hover {
    border-color: var(--brand-purple);
    background: rgba(142, 68, 173, 0.1);
}

.form-group input[type="file"]:focus {
    outline: none;
    border-color: var(--brand-purple);
    box-shadow: 0 0 0 2px rgba(142, 68, 173, 0.2);
}

.project-client {
    font-size: 0.85rem;
    color: #ccc !important;
    display: block;
    margin-top: 4px;
}

.level-badge {
    background: rgba(142, 68, 173, 0.2);
    color: var(--brand-purple);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    margin-top: 4px;
    display: inline-block;
}

.action-buttons {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
}

.action-buttons form {
    margin: 0;
}

.featured-toggle-btn {
    transition: all 0.3s ease;
}

.featured-toggle-btn:hover {
    transform: scale(1.1);
}

.search-group {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.row {
    display: flex;
    flex-wrap: wrap;
    margin: 0 -15px;
}

.col-md-4 {
    flex: 0 0 33.3333%;
    max-width: 33.3333%;
    padding: 0 15px;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: var(--brand-purple);
    box-shadow: 0 0 0 2px rgba(142, 68, 173, 0.2);
}

.form-control option {
    background: #2a2a2a;
    color: #fff;
}

.d-flex {
    display: flex;
}

.justify-content-end {
    justify-content: flex-end;
}

.mt-3 {
    margin-top: 1rem;
}

.gap-2 {
    gap: 0.5rem;
}

.g-3 > * {
    margin-bottom: 1rem;
}

.status-badge.status-live {
    background: rgba(231, 76, 60, 0.2);
    color: #e74c3c;
    animation: pulse 2s infinite;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    font-size: 4rem;
    color: #666;
    margin-bottom: 20px;
    display: block;
}

.empty-state h3 {
    color: #fff;
    margin-bottom: 12px;
    font-size: 1.4rem;
}

.empty-state p {
    color: #ccc;
    margin: 0;
}

.alert-success {
    background: rgba(46, 204, 113, 0.2);
    border: 1px solid rgba(46, 204, 113, 0.5);
    color: #2ecc71;
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-error {
    background: rgba(231, 76, 60, 0.2);
    border: 1px solid rgba(231, 76, 60, 0.5);
    color: #e74c3c;
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

@media (max-width: 768px) {
    .search-group {
    flex-direction: column;
    align-items: stretch;
    }
    
    .search-group input,
    .search-group select,
    .search-group button,
    .search-group a {
    width: 100% !important;
    margin-bottom: 8px;
    }
    
    .action-buttons {
    justify-content: center;
    }
    
    .col-md-4 {
    flex: 0 0 100%;
    max-width: 100%;
    }
}

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

.page-btn {
    display: inline-block;
    padding: 8px 16px;
    font-size: 0.9rem;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    text-decoration: none;
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.3s ease;
    cursor: pointer;
}

.page-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}
</style>

<script>
// Funcionalidade AJAX para toggle de destaque
document.addEventListener('DOMContentLoaded', function() {
    const featuredButtons = document.querySelectorAll('.featured-toggle-btn');
    
    featuredButtons.forEach(button => {
    button.addEventListener('click', function() {
    const lectureId = this.dataset.lectureId;
    const currentFeatured = this.dataset.featured === '1';
    
    // Desabilitar botão durante a requisição
    this.disabled = true;
    this.style.opacity = '0.6';
    
    // Fazer requisição AJAX
    fetch(window.location.href, {
    method: 'POST',
    headers: {
    'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `ajax=1&action=toggle_featured&lecture_id=${encodeURIComponent(lectureId)}`
    })
    .then(response => response.json())
    .then(data => {
    if (data.success) {
    // Atualizar ícone
    const icon = this.querySelector('i');
    if (data.is_featured) {
    icon.className = 'fas fa-star';
    icon.style.color = '#f1c40f';
    this.dataset.featured = '1';
    } else {
    icon.className = 'fas fa-star-o';
    icon.style.color = '#95a5a6';
    this.dataset.featured = '0';
    }
    
    // Atualizar status na tabela
    const row = this.closest('tr');
    const statusCell = row.querySelector('td:nth-child(5)');
    const statusBadge = statusCell.querySelector('.status-badge');
    
    if (data.is_featured) {
    statusBadge.className = 'status-badge status-completed';
    statusBadge.textContent = 'DESTAQUE';
    } else {
    // Verificar se é ao vivo
    const isLive = row.querySelector('[data-lecture-id]').closest('td').querySelector('[title="Alternar Ao Vivo"] i').style.color === 'rgb(231, 76, 60)';
    if (!isLive) {
    statusBadge.className = 'status-badge status-cancelled';
    statusBadge.textContent = 'PADRÃO';
    }
    }
    
    // Mostrar feedback visual
    this.style.transform = 'scale(1.2)';
    setTimeout(() => {
    this.style.transform = 'scale(1)';
    }, 200);
    
    } else {
    alert('Erro ao atualizar status: ' + data.message);
    }
    })
    .catch(error => {
    console.error('Erro:', error);
    alert('Erro ao processar solicitação');
    })
    .finally(() => {
    // Reabilitar botão
    this.disabled = false;
    this.style.opacity = '1';
    });
    });
    });
});

function showEmbedModal(lectureId) {
    // Funcionalidade para mostrar modal com código embed
    alert('Funcionalidade de visualização de embed em desenvolvimento');
}

// Função para baixar modelo CSV
function downloadCSVTemplate() {
    const csvContent = `title,speaker,speaker_minibio,description,duration_minutes,embed_code,thumbnail_url,category,tags,is_featured,is_live,language,level,image_filename
"Tradução Jurídica Avançada","Maria Silva","Tradutora juramentada com 15 anos de experiência","Palestra sobre técnicas avançadas de tradução jurídica e contratos internacionais",60,"<iframe src='https://youtube.com/embed/...'></iframe>","https://exemplo.com/thumb.jpg","Tradução","jurídico,contratos,internacional",1,0,"pt-BR","Avançado","palestra1.jpg"
"Interpretação Simultânea","João Souza","Intérprete de conferência certificado","Discussão sobre técnicas e desafios da interpretação simultânea",45,"","","Interpretação","simultânea,conferência,técnicas",0,1,"pt-BR","Intermediário","palestra2.png"
"Tradução Técnica para Iniciantes","Ana Costa","Tradutora técnica especializada","Introdução ao mundo da tradução técnica e suas particularidades",50,"","","Tradução","técnica,iniciante,básico",0,0,"pt-BR","Iniciante",""`;
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', 'modelo_palestras.csv');
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>