<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Verificar se é admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: /login.php');
    exit;
}

// Utils
function uuid_v4() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function hasColumn(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

$page_title = 'Upload de Glossário - Admin';
$message = '';
$error = '';

// Verificar se é um arquivo existente (vindo da varredura)
$existingFile = $_GET['file_existing'] ?? '';
$isExistingFile = !empty($existingFile);

// Pré-preencher dados se for arquivo existente
$prefilledTitle = $isExistingFile ? pathinfo($existingFile, PATHINFO_FILENAME) : '';
$prefilledCategory = 'Geral';

// Buscar categorias existentes no banco
$existingCategories = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT category FROM glossary_files WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $existingCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $existingCategories = [];
}

// Categorias base
$defaultCategories = ['Jurídico', 'Médico', 'Técnico', 'Financeiro', 'Marketing', 'Acadêmico', 'Geral'];
$allCategories = array_unique(array_merge($defaultCategories, $existingCategories));
sort($allCategories);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title        = trim($_POST['title'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $category     = trim($_POST['category'] ?? '');
        $new_category = trim($_POST['new_category'] ?? '');
        $source_lang  = trim($_POST['source_lang'] ?? '');
        $target_lang  = trim($_POST['target_lang'] ?? '');

        // Se foi escolhida "nova categoria"
        if ($category === '__new__' && $new_category !== '') {
            $category = ucfirst($new_category);
        } elseif ($category === '__new__' && $new_category === '') {
            throw new RuntimeException('Digite um nome para a nova categoria.');
        }

        if ($title === '' || $category === '') {
            throw new RuntimeException('Título e Categoria são obrigatórios.');
        }

        $uploadDir = __DIR__ . '/../../uploads/glossarios/';
        
        // Determinar arquivo
        if (!empty($_POST['file_existing'])) {
            $existingFileName = basename($_POST['file_existing']);
            $targetPath = $uploadDir . $existingFileName;
            if (!file_exists($targetPath)) {
                throw new RuntimeException('Arquivo não encontrado no servidor: ' . $existingFileName);
            }
            $uniqueName = $existingFileName;
            $ext = strtolower(pathinfo($existingFileName, PATHINFO_EXTENSION));
        } else {
            if (!isset($_FILES['glossary_file']) || $_FILES['glossary_file']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Selecione um arquivo válido.');
            }

            $file = $_FILES['glossary_file'];
            $originalName = $file['name'];
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $allowed = ['pdf','csv','xlsx'];
            
            if (!in_array($ext, $allowed, true)) {
                throw new RuntimeException('Formato não permitido. Utilize PDF, CSV ou XLSX.');
            }

            if ($file['size'] > 10 * 1024 * 1024) {
                throw new RuntimeException('Arquivo muito grande. Máximo: 10MB.');
            }

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $safeBase = preg_replace('/[^a-zA-Z0-9-_]+/', '_', pathinfo($originalName, PATHINFO_FILENAME));
            $uniqueName = $safeBase . '__' . substr(sha1(uniqid('', true)), 0, 10) . '.' . $ext;
            $targetPath = $uploadDir . $uniqueName;

            move_uploaded_file($file['tmp_name'], $targetPath);
        }

        // Monta dados banco
        $id = uuid_v4();
        $file_type = strtoupper($ext);
        $file_size = filesize($targetPath);
        $download_url = '/uploads/glossarios/' . $uniqueName;

        $hasSource = hasColumn($pdo, 'glossary_files', 'source_lang');
        $hasTarget = hasColumn($pdo, 'glossary_files', 'target_lang');

        $columns = ['id','title','description','category','file_type','download_url','file_size','is_active','created_at'];
        $placeholders = ['?','?','?','?','?','?','?','1','NOW()'];
        $values = [$id, $title, $description, $category, $file_type, $download_url, $file_size];

        if ($hasSource) {
            array_splice($columns, 4, 0, 'source_lang');
            array_splice($placeholders, 4, 0, '?');
            array_splice($values, 4, 0, $source_lang);
        }
        if ($hasTarget) {
            $idx = array_search('file_type', $columns);
            array_splice($columns, $idx, 0, 'target_lang');
            array_splice($placeholders, $idx, 0, '?');
            array_splice($values, $idx, 0, $target_lang);
        }

        $sql = 'INSERT INTO glossary_files (' . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        $message = 'Glossário cadastrado com sucesso!';
        if ($category === $new_category && $new_category !== '') {
            $message .= ' Nova categoria "' . htmlspecialchars($category) . '" criada.';
        }

    } catch (Throwable $e) {
        $error = 'Erro: ' . $e->getMessage();
    }
}

include __DIR__ . '/../../vision/includes/head.php';
include __DIR__ . '/../../vision/includes/header.php';
include __DIR__ . '/../../vision/includes/sidebar.php';
?>

<div class="main-content">
  <div class="glass-hero">
    <div class="hero-content">
      <h1><i class="fas fa-upload"></i> Upload de Glossários</h1>
      <p>Envie seu arquivo ou cadastre um existente e defina os metadados.</p>
      <div class="flex gap-2">
        <a href="../glossarios.php" class="cta-btn"><i class="fas fa-arrow-left"></i> Voltar</a>
      </div>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
  <?php endif; ?>

  <div class="video-card">
    <h2><i class="fas fa-plus-circle"></i> Formulário</h2>
    <form method="POST" enctype="multipart/form-data" class="vision-form">
      <?php if ($isExistingFile): ?>
        <input type="hidden" name="file_existing" value="<?php echo htmlspecialchars($existingFile); ?>">
      <?php endif; ?>

      <div class="form-grid">
        <div class="form-group form-group-wide">
          <label for="title"><i class="fas fa-heading"></i> Título *</label>
          <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($prefilledTitle); ?>" required>
        </div>

        <div class="form-group">
          <label for="category"><i class="fas fa-tags"></i> Categoria *</label>
          <select id="category" name="category" required onchange="toggleNewCategory(this.value)">
            <option value="">Selecione uma categoria</option>
            <?php foreach ($allCategories as $cat): ?>
              <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
            <?php endforeach; ?>
            <option value="__new__">+ Adicionar Nova Categoria</option>
          </select>
        </div>

        <div class="form-group" id="newCategoryGroup" style="display:none;">
          <label for="new_category"><i class="fas fa-plus-circle"></i> Nova Categoria *</label>
          <input type="text" id="new_category" name="new_category" placeholder="Digite o nome da nova categoria" maxlength="50">
        </div>

        <div class="form-group">
          <label for="source_lang"><i class="fas fa-language"></i> Idioma Fonte</label>
          <input type="text" id="source_lang" name="source_lang" placeholder="ex: pt-BR, en-US">
        </div>

        <div class="form-group">
          <label for="target_lang"><i class="fas fa-language"></i> Idioma Alvo</label>
          <input type="text" id="target_lang" name="target_lang" placeholder="ex: en-US, pt-BR">
        </div>

        <div class="form-group form-group-wide">
          <label for="description"><i class="fas fa-file-alt"></i> Descrição</label>
          <textarea id="description" name="description" rows="4"></textarea>
        </div>

        <?php if (!$isExistingFile): ?>
        <div class="form-group form-group-wide">
          <label for="glossary_file"><i class="fas fa-file-upload"></i> Arquivo *</label>
          <input type="file" id="glossary_file" name="glossary_file" accept=".pdf,.csv,.xlsx" required>
        </div>
        <?php endif; ?>
      </div>

      <div class="form-actions">
        <button type="submit" class="cta-btn"><i class="fas fa-save"></i> Salvar</button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleNewCategory(value) {
    const group = document.getElementById('newCategoryGroup');
    const input = document.getElementById('new_category');
    if (value === '__new__') {
        group.style.display = 'block';
        input.required = true;
    } else {
        group.style.display = 'none';
        input.required = false;
        input.value = '';
    }
}
</script>

<?php include __DIR__ . '/../../vision/includes/footer.php'; ?>