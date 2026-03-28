<?php
session_start();
require_once __DIR__ . '/config/database.php';

$page_title = 'Glossários Especializados';
$page_description = 'Baixe gratuitamente nossos glossários especializados.';

// Redireciona se não estiver logado
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Parâmetros de busca e filtros
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$file_type = $_GET['file_type'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

$message = '';
$error = '';

// Construir query de busca
$where_conditions = ['is_active = 1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(title LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $where_conditions[] = "category = ?";
    $params[] = $category;
}

if (!empty($file_type)) {
    $where_conditions[] = "file_type = ?";
    $params[] = $file_type;
}

$where_sql = 'WHERE ' . implode(' AND ', $where_conditions);

try {
    // Contar total de glossários
    $count_sql = "SELECT COUNT(*) FROM glossary_files $where_sql";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_files = $stmt->fetchColumn();
    
    // Buscar glossários paginados
    $sql = "SELECT * FROM glossary_files $where_sql ORDER BY category, title ASC LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $files = $stmt->fetchAll();
    
    // Buscar categorias para filtro
    $categories_sql = "SELECT DISTINCT category FROM glossary_files WHERE is_active = 1 ORDER BY category";
    $categories = $pdo->query($categories_sql)->fetchAll(PDO::FETCH_COLUMN);
    
    // Buscar tipos de arquivo para filtro
    $file_types_sql = "SELECT DISTINCT file_type FROM glossary_files WHERE is_active = 1 ORDER BY file_type";
    $file_types_list = $pdo->query($file_types_sql)->fetchAll(PDO::FETCH_COLUMN);
    
    $total_pages = ceil($total_files / $per_page);
    
} catch (Exception $e) {
    $error = 'Erro ao carregar glossários: ' . $e->getMessage();
    $files = [];
    $categories = [];
    $file_types_list = [];
    $total_pages = 0;
}

include __DIR__ . '/vision/includes/head.php';
?>

<?php include __DIR__ . '/vision/includes/header.php'; ?>

<?php include __DIR__ . '/vision/includes/sidebar.php'; ?>

<main class="main-content">
    <!-- Hero Section -->
    <section class="glass-hero">
        <h1>📚 Glossários Especializados</h1>
        <p>Baixe gratuitamente nossos glossários especializados para tradutores e intérpretes profissionais.</p>
    </section>

    <!-- Mensagens -->
    <?php if ($message): ?>
        <div class="alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="videoteca-filtros">
        <form method="GET" class="filtros-grid">
            <div>
                <label for="search" style="display: block; margin-bottom: 8px; font-weight: 500;">Buscar</label>
                <input type="text" name="search" id="search" 
                       value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Digite sua busca...">
            </div>

            <div>
                <label for="category" style="display: block; margin-bottom: 8px; font-weight: 500;">Categoria</label>
                <select name="category" id="category">
                    <option value="">Todas as categorias</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" 
                                <?php echo $category === $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(ucfirst($cat)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="file_type" style="display: block; margin-bottom: 8px; font-weight: 500;">Tipo de Arquivo</label>
                <select name="file_type" id="file_type">
                    <option value="">Todos os tipos</option>
                    <?php foreach ($file_types_list as $type): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>" 
                                <?php echo $file_type === $type ? 'selected' : ''; ?>>
                            <?php echo strtoupper($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; align-items: end;">
                <button type="submit" class="cta-btn" style="width: 100%;">
                    <i class="fas fa-search"></i> Buscar
                </button>
            </div>
        </form>
    </div>

    <!-- Informações dos resultados -->
    <div style="background: var(--glass-bg); backdrop-filter: blur(20px); border: 1px solid var(--glass-border); border-radius: 12px; padding: 15px; margin-bottom: 30px;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <span style="color: #ddd;">
                Mostrando <?php echo count($files); ?> de <?php echo $total_files; ?> glossários
            </span>
            <?php if ($search || $category || $file_type): ?>
                <a href="glossarios.php" style="color: var(--brand-purple); text-decoration: none; font-size: 0.9rem;">
                    <i class="fas fa-times"></i> Limpar filtros
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($files): ?>
        <!-- Lista de Glossários -->
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <?php foreach ($files as $file): ?>
                <div class="video-card fade-item" style="flex-direction: row; overflow: visible;">
                    <div style="padding: 25px; flex: 1;">
                        <div style="display: flex; align-items: start; justify-content: space-between; margin-bottom: 15px;">
                            <div style="flex: 1;">
                                <h3 style="font-size: 1.3rem; margin-bottom: 10px;">
                                    <?php echo htmlspecialchars($file['title']); ?>
                                </h3>
                                <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                                    <span class="tag">
                                        <?php echo htmlspecialchars(ucfirst($file['category'])); ?>
                                    </span>
                                    <span class="tag" style="background: rgba(52, 152, 219, 0.25); color: #3498db;">
                                        <?php echo strtoupper($file['file_type']); ?>
                                    </span>
                                </div>
                            </div>
                            <div style="font-size: 0.85rem; color: #bbb;">
                                <?php echo date('d/m/Y', strtotime($file['created_at'])); ?>
                            </div>
                        </div>

                        <div style="margin-bottom: 20px;">
                            <p style="color: #ddd; line-height: 1.6;">
                                <?php echo nl2br(htmlspecialchars($file['description'])); ?>
                            </p>
                        </div>

                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div style="font-size: 0.9rem; color: #bbb;">
                                <i class="fas fa-file-alt"></i>
                                Tamanho: <?php echo htmlspecialchars($file['file_size']); ?>
                            </div>
                            <a href="download.php?id=<?php echo htmlspecialchars($file['id']); ?>"
                               class="cta-btn" style="font-size: 0.9rem; padding: 10px 20px;">
                                <i class="fas fa-download"></i> Baixar Arquivo
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <!-- Paginação -->
            <div class="videoteca-paginacao">
                <nav>
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                           class="page-btn">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    for ($i = $start; $i <= $end; $i++): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                           class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                           class="page-btn">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Estado vazio -->
        <div style="text-align: center; padding: 60px 20px; background: var(--glass-bg); backdrop-filter: blur(20px); border: 1px solid var(--glass-border); border-radius: 20px;">
            <i class="fas fa-book" style="font-size: 4rem; color: #666; margin-bottom: 20px;"></i>
            <h3 style="font-size: 1.5rem; margin-bottom: 15px;">Nenhum glossário encontrado</h3>
            <?php if ($search || $category || $file_type): ?>
                <p style="color: #ccc; margin-bottom: 30px;">Nenhum arquivo corresponde aos filtros aplicados.</p>
                <a href="glossarios.php" class="cta-btn">
                    <i class="fas fa-times"></i> Limpar filtros
                </a>
            <?php else: ?>
                <p style="color: #ccc;">Ainda não há glossários disponíveis.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Seção Informativa -->
    <section class="vision-form" style="margin-top: 60px;">
        <h3 style="font-size: 1.5rem; margin-bottom: 25px; text-align: center;">ℹ️ Sobre os Glossários</h3>
        <div class="video-grid">
            <div class="video-card fade-item">
                <div class="video-info">
                    <h4 style="color: var(--brand-purple); margin-bottom: 10px;">📋 O que são</h4>
                    <p style="font-size: 0.9rem; color: #ddd;">
                        Nossos glossários contêm termos especializados organizados por categoria, 
                        disponíveis para download para auxiliar tradutores em diferentes áreas.
                    </p>
                </div>
            </div>
            
            <div class="video-card fade-item">
                <div class="video-info">
                    <h4 style="color: var(--brand-purple); margin-bottom: 10px;">🎯 Como usar</h4>
                    <p style="font-size: 0.9rem; color: #ddd;">
                        Utilize os filtros para encontrar arquivos por categoria ou tipo de arquivo. 
                        Clique em "Baixar Arquivo" para salvar o glossário em seu computador.
                    </p>
                </div>
            </div>
            
            <div class="video-card fade-item">
                <div class="video-info">
                    <h4 style="color: var(--brand-purple); margin-bottom: 10px;">🔍 Busca inteligente</h4>
                    <p style="font-size: 0.9rem; color: #ddd;">
                        Use a busca para encontrar glossários específicos ou descrições que contenham 
                        palavras-chave relacionadas ao seu projeto de tradução.
                    </p>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>