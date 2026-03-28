<?php
session_start();
require_once __DIR__ . '/config/database.php';

$page_title = 'Videoteca de Palestras';
$page_description = 'Acesse nossa videoteca com quase 400 palestras especializadas em tradução, interpretação e revisão.';

// Nova função de verificação de acesso
function hasVideotecaAccess() {
    return isset($_SESSION['user_role']) && ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'subscriber');
}

// Verificar acesso (admin tem acesso completo, assinantes também)
if (!hasVideotecaAccess()) {
    header('Location: /planos.php?redirect=videoteca');
    exit;
}

// Parâmetros de busca e filtros
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$sort_order = $_GET['sort'] ?? 'recent'; // 'recent' ou 'oldest'
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 15; // 15 palestras por página (3 por linha)
$offset = ($page - 1) * $per_page;

// Construir query de busca
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(title LIKE ? OR speaker LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $where_conditions[] = "category = ?";
    $params[] = $category;
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Ordenação simplificada e corrigida
if ($sort_order === 'recent') {
    $order_sql = "ORDER BY 
    CASE 
    WHEN title LIKE 'S08%' THEN 0 
    ELSE 1 
    END,
    CASE 
    WHEN title LIKE 'S08%' THEN title 
    ELSE '' 
    END DESC,
    created_at DESC";
} else {
    $order_sql = "ORDER BY created_at ASC";
}

try {
    $newest_stmt = $pdo->prepare("SELECT id FROM lectures ORDER BY created_at DESC LIMIT 1");
    $newest_stmt->execute();
    $newest_lecture_id = $newest_stmt->fetchColumn();

    $count_sql = "SELECT COUNT(*) FROM lectures $where_sql";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_lectures = $stmt->fetchColumn();

    $sql = "SELECT * FROM lectures $where_sql $order_sql LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $lectures = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT DISTINCT category FROM lectures ORDER BY category");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $total_pages = ceil($total_lectures / $per_page);

} catch(PDOException $e) {
    $lectures = [];
    $categories = [];
    $total_lectures = 0;
    $total_pages = 0;
    $newest_lecture_id = null;
}

include __DIR__ . '/vision/includes/head.php';
?>

<?php include __DIR__ . '/vision/includes/header.php'; ?>

<?php include __DIR__ . '/vision/includes/sidebar.php'; ?>

<main class="main-content">
    <!-- Hero da Videoteca -->
    <section class="glass-hero">
        <h1>📚 Videoteca de Palestras</h1>
        <p>Acesse nosso catálogo completo com quase 400 palestras especializadas em tradução, interpretação e revisão.</p>
        
        <?php if (function_exists('isAdmin') && isAdmin()): ?>
            <div style="margin-top: 20px;">
                <span class="badge-new">
                    <i class="fas fa-crown"></i> Acesso Admin
                </span>
                <div style="margin-top: 10px;">
                    <a href="/admin/palestras.php" class="cta-btn" style="font-size: 0.9rem; padding: 8px 16px;">
                        <i class="fas fa-cog"></i> Gerenciar Palestras
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <!-- Filtros -->
    <div class="videoteca-filtros">
        <form method="GET" class="filtros-grid">
            <div>
                <label for="search" style="display: block; margin-bottom: 8px; font-weight: 500;">Buscar</label>
                <input type="text" name="search" id="search" 
                       value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Título, palestrante ou descrição...">
            </div>

            <div>
                <label for="category" style="display: block; margin-bottom: 8px; font-weight: 500;">Categoria</label>
                <select name="category" id="category">
                    <option value="">Todas as categorias</option>
                    <?php foreach($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="sort" style="display: block; margin-bottom: 8px; font-weight: 500;">Ordenação</label>
                <select name="sort" id="sort">
                    <option value="recent" <?php echo $sort_order === 'recent' ? 'selected' : ''; ?>>
                        Mais Recentes (S08 primeiro)
                    </option>
                    <option value="oldest" <?php echo $sort_order === 'oldest' ? 'selected' : ''; ?>>
                        Mais Antigas
                    </option>
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
        <p style="color: #ddd;">
        <?php if ($total_lectures > 0): ?>
            Mostrando <?php echo count($lectures); ?> de <?php echo $total_lectures; ?> palestras
            <?php if (!empty($search) || !empty($category)): ?>
                <?php if (!empty($search)): ?>
                    para "<strong><?php echo htmlspecialchars($search); ?></strong>"
                <?php endif; ?>
                <?php if (!empty($category)): ?>
                    na categoria "<strong><?php echo htmlspecialchars($category); ?></strong>"
                <?php endif; ?>
            <?php endif; ?>
            - <strong>15 por página</strong> - Ordenação: <strong><?php echo $sort_order === 'recent' ? 'Mais Recentes (S08 primeiro)' : 'Mais Antigas'; ?></strong>
        <?php else: ?>
            Nenhuma palestra encontrada
        <?php endif; ?>
        </p>
    </div>

    <?php if (!empty($lectures)): ?>
    <!-- Grid de vídeos -->
    <div class="video-grid">
        <?php foreach($lectures as $lecture): ?>
        <div class="video-card fade-item" onclick="window.location.href='/palestra.php?id=<?php echo $lecture['id']; ?>'">
            <div class="video-thumb" style="position: relative; background: linear-gradient(135deg, var(--brand-purple), #5e3370); display: flex; align-items: center; justify-content: center;">
                <?php if ($lecture['thumbnail_url']): ?>
                <img src="<?php echo htmlspecialchars($lecture['thumbnail_url']); ?>" 
                     alt="<?php echo htmlspecialchars($lecture['title']); ?>"
                     style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                <div style="text-align: center; color: white;">
                    <div style="font-size: 3rem; margin-bottom: 10px;">🎥</div>
                    <div style="font-size: 0.9rem; opacity: 0.8;">Palestra</div>
                </div>
                <?php endif; ?>

                <?php if ($lecture['id'] == $newest_lecture_id): ?>
                <div class="badge-new">
                    <i class="fas fa-star"></i>NOVA
                </div>
                <?php endif; ?>

                <?php if (strpos($lecture['title'], 'S08') === 0 && $lecture['id'] != $newest_lecture_id): ?>
                <div class="badge-new" style="background: #3498db;">
                    <i class="fas fa-fire"></i>S08
                </div>
                <?php endif; ?>

                <div style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s; background: rgba(0,0,0,0.5);" 
                     onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0'">
                    <div style="background: var(--brand-purple); border-radius: 50%; padding: 15px;">
                        <i class="fas fa-play" style="font-size: 1.5rem; margin-left: 3px;"></i>
                    </div>
                </div>
            </div>

            <div class="video-info">
                <h3><?php echo htmlspecialchars($lecture['title']); ?></h3>
                <p class="video-speaker"><?php echo htmlspecialchars($lecture['speaker']); ?></p>
                <p class="video-desc"><?php echo htmlspecialchars(substr($lecture['description'], 0, 120)) . '...'; ?></p>

                <div class="video-meta" style="display: flex; justify-content: space-between; align-items: center;">
                    <span class="tag"><?php echo htmlspecialchars($lecture['category']); ?></span>
                    <span>
                        <i class="fas fa-clock"></i><?php echo $lecture['duration_minutes']; ?> min
                    </span>
                </div>

                <?php if ($lecture['is_featured']): ?>
                <div style="margin-top: 10px;">
                    <span class="tag" style="background: rgba(241, 196, 15, 0.25); color: #f1c40f;">
                        <i class="fas fa-star"></i>Destaque
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <!-- Paginação -->
    <div class="videoteca-paginacao">
        <nav>
            <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-btn">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>

            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);

            for ($i = $start_page; $i <= $end_page; $i++):
            ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-btn">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </nav>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- Estado vazio -->
    <div style="text-align: center; padding: 60px 20px; background: var(--glass-bg); backdrop-filter: blur(20px); border: 1px solid var(--glass-border); border-radius: 20px;">
        <div style="font-size: 4rem; margin-bottom: 20px;">🔍</div>
        <h3 style="font-size: 1.5rem; margin-bottom: 15px;">Nenhuma palestra encontrada</h3>
        <p style="color: #ccc; margin-bottom: 30px;">
            Tente ajustar os filtros ou termos de busca.
        </p>
        <a href="/videoteca.php" class="cta-btn">
            Ver Todas as Palestras
        </a>
    </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>