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

// Parâmetros de busca e filtros (mantidos para filtragem, mas sem paginação)
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$file_type = $_GET['file_type'] ?? '';

// REMOVIDA A LÓGICA DE PAGINAÇÃO
// $page = max(1, intval($_GET['page'] ?? 1));
// $per_page = 12;
// $offset = ($page - 1) * $per_page;

$message = '';
$error = '';

/**
 * Função para formatar o tamanho de bytes para um formato legível (KB, MB, GB).
 * Assume que o valor de entrada ($bytes) está em bytes.
 * @param int $bytes O tamanho do arquivo em bytes.
 * @param int $precision O número de casas decimais para exibir.
 * @return string O tamanho formatado.
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');

    $bytes = max(0, intval($bytes));
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    // Usa round() para arredondar o valor
    $bytes /= (1 << (10 * $pow));

    // Formata o número com vírgula como separador decimal (Padrão PT-BR)
    $formatted_value = round($bytes, $precision);
    
    return str_replace('.', ',', $formatted_value) . ' ' . $units[$pow];
}

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
    // REINTRODUZIDA A CONTAGEM TOTAL PARA CORRIGIR O WARNING
    $count_sql = "SELECT COUNT(*) FROM glossary_files $where_sql";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_files = $stmt->fetchColumn(); // Variável $total_files agora está definida

    // Buscar TODOS os glossários que atendem aos filtros.
    // REMOVIDO: LIMIT $per_page OFFSET $offset
    // Garantimos que 'file_size' seja sempre selecionado
    $sql = "SELECT *, file_size FROM glossary_files $where_sql ORDER BY category, title ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $files = $stmt->fetchAll();

    // Se a coluna 'file_size' estiver em bytes, ela será formatada
    foreach ($files as &$file) {
        // Aplica a formatação se o campo existir e for numérico
        if (isset($file['file_size']) && is_numeric($file['file_size'])) {
            // A formatação acontece aqui: 1720122 -> "1.64 MB"
            $file['formatted_size'] = formatBytes($file['file_size']);
        } else {
            // Define um valor padrão se a informação de tamanho estiver faltando
            $file['formatted_size'] = 'N/D';
        }
    }
    unset($file); // Desfaz a referência para evitar modificações acidentais futuras

    // Buscar categorias para filtro
    $categories_sql = "SELECT DISTINCT category FROM glossary_files WHERE is_active = 1 ORDER BY category";
    $categories = $pdo->query($categories_sql)->fetchAll(PDO::FETCH_COLUMN);

    // Buscar tipos de arquivo para filtro
    $file_types_sql = "SELECT DISTINCT file_type FROM glossary_files WHERE is_active = 1 ORDER BY file_type";
    $file_types_list = $pdo->query($file_types_sql)->fetchAll(PDO::FETCH_COLUMN);

    // REMOVIDO: cálculo total de páginas
    // $total_pages = ceil($total_files / $per_page);

} catch (Exception $e) {
    $error = 'Erro ao carregar glossários: ' . $e->getMessage();
    $files = [];
    $categories = [];
    $file_types_list = [];
    $total_files = 0; // Definida em caso de erro também
    // $total_pages = 0; // Removido
}

include __DIR__ . '/vision/includes/head.php';
?>
<style>
/* ============================================
    ESTILOS APPLE VISION PARA FILTROS - INLINE
    ============================================ */

.videoteca-filtros {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 30px;
    padding: 25px;
    margin-bottom: 30px;
}

.filtros-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    align-items: end;
}

.filtros-grid label {
    font-weight: 600;
    font-size: 0.95rem;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filtros-grid input[type="text"],
.filtros-grid select {
    width: 100%;
    padding: 0.75rem 1rem;
    border-radius: 30px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.05);
    color: #fff;
    font-size: 0.95rem;
    font-family: 'Inter', sans-serif;
    transition: all 0.3s ease;
}

.filtros-grid input[type="text"]:focus,
.filtros-grid select:focus {
    outline: none;
    border-color: #7B61FF;
    background: rgba(123, 97, 255, 0.1);
    box-shadow: 0 0 0 3px rgba(123, 97, 255, 0.2);
}

.filtros-grid input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

.filtros-grid select {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23FFD700' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    padding-right: 2.5rem;
}

.filtros-grid select option {
    background: #1a1a1a;
    color: #fff;
    padding: 0.5rem;
}

.cta-btn {
    padding: 0.75rem 1.5rem;
    border-radius: 30px;
    border: none;
    background: linear-gradient(135deg, #7B61FF, #483D8B);
    color: #fff;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.cta-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(123, 97, 255, 0.4);
}

.cta-btn:active {
    transform: translateY(0);
}

.cta-btn i {
    font-size: 0.9rem;
}

/* Responsividade */
@media (max-width: 1200px) {
    .filtros-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .filtros-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include __DIR__ . '/vision/includes/header.php'; ?>

<?php include __DIR__ . '/vision/includes/sidebar.php'; ?>

<main class="main-content">
    <section class="glass-hero">
        <h1><i class="fas fa-book" style="margin-right: 10px;"></i>Glossários Especializados</h1>
        <p>Baixe gratuitamente nossos glossários especializados para tradutores e intérpretes profissionais.</p>
    </section>

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

    <div style="background: var(--glass-bg); backdrop-filter: blur(20px); border: 1px solid var(--glass-border); border-radius: 30px; padding: 15px; margin-bottom: 30px;">
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
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
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
                                <!-- CORREÇÃO APLICADA AQUI: AGORA USANDO 'formatted_size' SEM NEGIRTO -->
                                Tamanho: <?php echo htmlspecialchars($file['formatted_size']); ?>
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
    <?php else: ?>
        <div style="text-align: center; padding: 60px 20px; background: var(--glass-bg); backdrop-filter: blur(20px); border: 1px solid var(--glass-border); border-radius: 30px;">
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

    <section class="vision-form" style="margin-top: 60px;">
        <h3 style="font-size: 1.5rem; margin-bottom: 25px; text-align: center;">
            <i class="fas fa-info-circle" style="margin-right: 10px;"></i>Sobre os Glossários
        </h3>
        <div class="video-grid">
            <div class="video-card fade-item">
                <div class="video-info">
                    <h4 style="color: var(--brand-purple); margin-bottom: 10px;">
                        <i class="fas fa-clipboard-list" style="margin-right: 8px;"></i>O que são
                    </h4>
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
                    <h4 style="color: var(--brand-purple); margin-bottom: 10px;">
                        <i class="fas fa-search" style="margin-right: 8px;"></i>Busca inteligente
                    </h4>
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
