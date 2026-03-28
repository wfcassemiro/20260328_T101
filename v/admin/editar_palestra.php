<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Verificar se é admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: /login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: palestras.php");
    exit;
}

$id = trim($_GET['id']);
$message = '';
$error = '';

try {
    $stmt = $pdo->prepare("SELECT * FROM lectures WHERE id = ?");
    $stmt->execute([$id]);
    $palestra = $stmt->fetch();

    if (!$palestra) {
        $error = "Palestra não encontrada.";
    }
} catch (PDOException $e) {
    $error = 'Erro ao carregar palestra: ' . $e->getMessage();
}

// Converter tags de JSON para string amigável
$tags = $palestra['tags'] ?? '';
if ($tags && $tags[0] === '[') {
    $decoded = json_decode($tags, true);
    if (is_array($decoded)) $tags = implode(", ", $decoded);
}

$page_title = 'Editar Palestra - Admin';
include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-edit"></i> Editar Palestra</h1>
            <p>Modifique as informações da palestra selecionada</p>
            <a href="palestras.php" class="cta-btn">
                <i class="fas fa-arrow-left"></i> Voltar às Palestras
            </a>
        </div>
    </div>

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

    <?php if ($palestra): ?>
    <!-- Formulário de Edição -->
    <div class="vision-form">
        <div class="card-header">
            <h2><i class="fas fa-edit"></i> Editar: <?php echo htmlspecialchars(substr($palestra['title'], 0, 50)); ?>...</h2>
        </div>
        
        <form method="POST" action="salvar_edicao.php" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($palestra['id']); ?>">
            
            <div class="form-grid">
                <div class="form-group form-group-wide">
                    <label for="title"><i class="fas fa-heading"></i> Título *</label>
                    <input type="text" id="title" name="title" required maxlength="500" 
                           value="<?php echo htmlspecialchars($palestra['title']); ?>">
                </div>
                
                <div class="form-group">  
                    <label for="speaker"><i class="fas fa-user"></i> Palestrante</label>
                    <input type="text" id="speaker" name="speaker" maxlength="255"
                           value="<?php echo htmlspecialchars($palestra['speaker'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="category"><i class="fas fa-tag"></i> Categoria</label>
                    <input type="text" id="category" name="category" maxlength="100" 
                           value="<?php echo htmlspecialchars($palestra['category'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="duration_minutes"><i class="fas fa-clock"></i> Duração (min)</label>
                    <input type="number" id="duration_minutes" name="duration_minutes" min="0" max="600" 
                           value="<?php echo (int)($palestra['duration_minutes'] ?? 0); ?>">
                </div>
                
                <div class="form-group">
                    <label for="level"><i class="fas fa-layer-group"></i> Nível</label>
                    <select id="level" name="level">
                        <option value="">Selecionar nível</option>
                        <option value="Iniciante" <?php echo ($palestra['level'] ?? '') === 'Iniciante' ? 'selected' : ''; ?>>Iniciante</option>
                        <option value="Intermediário" <?php echo ($palestra['level'] ?? '') === 'Intermediário' ? 'selected' : ''; ?>>Intermediário</option>
                        <option value="Avançado" <?php echo ($palestra['level'] ?? '') === 'Avançado' ? 'selected' : ''; ?>>Avançado</option>
                        <option value="Todos" <?php echo ($palestra['level'] ?? '') === 'Todos' ? 'selected' : ''; ?>>Todos os níveis</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="language"><i class="fas fa-language"></i> Idioma</label>
                    <select id="language" name="language">
                        <option value="">Selecionar idioma</option>
                        <option value="pt-BR" <?php echo ($palestra['language'] ?? '') === 'pt-BR' ? 'selected' : ''; ?>>Português (BR)</option>
                        <option value="en" <?php echo ($palestra['language'] ?? '') === 'en' ? 'selected' : ''; ?>>Inglês</option>
                        <option value="es" <?php echo ($palestra['language'] ?? '') === 'es' ? 'selected' : ''; ?>>Espanhol</option>
                        <option value="fr" <?php echo ($palestra['language'] ?? '') === 'fr' ? 'selected' : ''; ?>>Francês</option>
                    </select>
                </div>
                
                <div class="form-group form-group-wide">
                    <label for="speaker_minibio"><i class="fas fa-id-card"></i> Mini-bio do Palestrante</label>
                    <textarea id="speaker_minibio" name="speaker_minibio" rows="2" maxlength="500"><?php echo htmlspecialchars($palestra['speaker_minibio'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group form-group-wide">
                    <label for="description"><i class="fas fa-align-left"></i> Descrição</label>
                    <textarea id="description" name="description" rows="3" maxlength="2000"><?php echo htmlspecialchars($palestra['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group form-group-wide">
                    <label for="embed_code"><i class="fas fa-code"></i> Código de Embed</label>
                    <textarea id="embed_code" name="embed_code" rows="3"><?php echo htmlspecialchars($palestra['embed_code'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group form-group-wide">
                    <label for="thumbnail"><i class="fas fa-image"></i> Thumbnail</label>
                    <?php if (!empty($palestra['thumbnail_url'])): ?>
                    <div class="mt-2 mb-2">
                        <img src="<?php echo htmlspecialchars($palestra['thumbnail_url']); ?>" 
                             alt="Thumbnail atual" style="max-width: 200px; border-radius: 8px;">
                    </div>
                    <?php endif; ?>
                    
                    <input type="url" id="thumbnail_url" name="thumbnail_url" maxlength="1000"
                           placeholder="Ou cole a URL da imagem"
                           value="<?php echo htmlspecialchars($palestra['thumbnail_url'] ?? ''); ?>">
                    <input type="file" id="thumbnail_file" name="thumbnail_file" accept=".jpg,.jpeg,.png,.webp" class="form-control mt-2">
                </div>
                
                <div class="form-group form-group-wide">
                    <label for="tags"><i class="fas fa-tags"></i> Tags</label>
                    <input type="text" id="tags" name="tags" maxlength="500"
                           value="<?php echo htmlspecialchars($tags); ?>">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_featured" value="1" 
                               <?php echo (bool)($palestra['is_featured'] ?? false) ? 'checked' : ''; ?>>
                        <i class="fas fa-star"></i> Destacar palestra
                    </label>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_live" value="1"
                               <?php echo (bool)($palestra['is_live'] ?? false) ? 'checked' : ''; ?>>
                        <i class="fas fa-broadcast-tower"></i> Ao vivo
                    </label>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="cta-btn">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
                <a href="palestras.php" class="page-btn cancel-btn">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<style>
.cancel-btn {
    background: rgba(255,255,255,0.1);
    color: #ccc !important;
    padding: 12px 24px;
    border-radius: 8px;
    transition: all 0.3s ease;
    text-decoration: none !important;
}
.cancel-btn:hover {
    background: rgba(255,255,255,0.2);
    color: #fff !important;
}
</style>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>