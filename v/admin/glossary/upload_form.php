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
$prefilledTitle = '';
$prefilledCategory = 'Geral';
$prefilledFileType = '';

if ($isExistingFile) {
    $prefilledTitle = pathinfo($existingFile, PATHINFO_FILENAME);
    $ext = strtolower(pathinfo($existingFile, PATHINFO_EXTENSION));
    $prefilledFileType = strtoupper($ext);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title        = trim($_POST['title'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $category     = trim($_POST['category'] ?? '');
        $source_lang  = trim($_POST['source_lang'] ?? '');
        $target_lang  = trim($_POST['target_lang'] ?? '');

        // Validações simples
        if ($title === '' || $category === '') {
            throw new RuntimeException('Título e Categoria são obrigatórios.');
        }

        $uploadDir = __DIR__ . '/../../uploads/glossarios/';
        
        // Verificar se é arquivo existente ou novo upload
        if (!empty($_POST['file_existing'])) {
            // Arquivo já existe no servidor (vindo da varredura)
            $existingFileName = basename($_POST['file_existing']);
            $targetPath = $uploadDir . $existingFileName;
            
            if (!file_exists($targetPath)) {
                throw new RuntimeException('Arquivo não encontrado no servidor: ' . $existingFileName);
            }
            
            $uniqueName = $existingFileName;
            $ext = strtolower(pathinfo($existingFileName, PATHINFO_EXTENSION));
            
        } else {
            // Upload novo
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

            // Garante diretório
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    throw new RuntimeException('Não foi possível criar o diretório de upload.');
                }
            }

            // Gera nome único preservando um pouco do nome original limpo
            $safeBase = preg_replace('/[^a-zA-Z0-9-_]+/', '_', pathinfo($originalName, PATHINFO_FILENAME));
            $uniqueName = $safeBase . '__' . substr(sha1(uniqid('', true)), 0, 10) . '.' . $ext;
            $targetPath = $uploadDir . $uniqueName;

            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new RuntimeException('Falha ao mover o arquivo para o destino.');
            }
        }

        // Monta dados para INSERT
        $id = uuid_v4();
        $file_type = strtoupper($ext); // PDF, CSV, XLSX
        $file_size = (string) filesize($targetPath);
        $download_url = '/uploads/glossarios/' . $uniqueName;

        // Verifica colunas opcionais para metadados de idioma
        $hasSource = hasColumn($pdo, 'glossary_files', 'source_lang');
        $hasTarget = hasColumn($pdo, 'glossary_files', 'target_lang');

        // Monta statement dinamicamente conforme colunas existentes
        $columns = ['id','title','description','category','file_type','download_url','file_size','is_active','created_at'];
        $placeholders = ['?','?','?','?','?','?','?','1','NOW()'];
        $values = [$id, $title, $description, $category, $file_type, $download_url, $file_size];

        if ($hasSource) {
            array_splice($columns, 4, 0, 'source_lang'); // antes de file_type
            array_splice($placeholders, 4, 0, '?');
            array_splice($values, 4, 0, $source_lang);
        }
        if ($hasTarget) {
            $idx = array_search('file_type', $columns, true);
            array_splice($columns, $idx, 0, 'target_lang'); // antes de file_type
            array_splice($placeholders, $idx, 0, '?');
            array_splice($values, $idx, 0, $target_lang);
        }

        $sql = 'INSERT INTO glossary_files (' . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        $message = $isExistingFile ? 
            'Arquivo cadastrado com sucesso! Metadados registrados no sistema.' : 
            'Upload concluído e glossário registrado com sucesso!';
            
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
      <h1><i class="fas fa-upload"></i> <?php echo $isExistingFile ? 'Cadastrar Arquivo Existente' : 'Upload de Glossários'; ?></h1>
      <p><?php echo $isExistingFile ? 'Defina os metadados para o arquivo detectado no servidor' : 'Envie seus arquivos (PDF, CSV, XLSX) e defina os metadados para exibição'; ?></p>
      <div class="flex gap-2">
        <a href="../glossarios.php" class="cta-btn"><i class="fas fa-arrow-left"></i> Voltar a Glossários</a>
      </div>
    </div>
  </div>

  <?php if (!empty($message)): ?>
    <div class="alert-success">
      <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div class="alert-error">
      <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
  <?php endif; ?>

  <?php if ($isExistingFile): ?>
  <div class="video-card">
    <h2><i class="fas fa-info-circle"></i> Arquivo Detectado</h2>
    <div class="alert-info">
      <i class="fas fa-file"></i>
      <strong>Arquivo:</strong> <?php echo htmlspecialchars($existingFile); ?><br>
      <strong>Localização:</strong> /uploads/glossarios/<br>
      <strong>Status:</strong> Arquivo já existe no servidor, apenas os metadados serão cadastrados.
    </div>
  </div>
  <?php endif; ?>

  <div class="video-card">
    <h2><i class="fas fa-<?php echo $isExistingFile ? 'edit' : 'cloud-upload-alt'; ?>"></i> <?php echo $isExistingFile ? 'Cadastrar Metadados' : 'Novo Upload'; ?></h2>

    <form method="POST" enctype="multipart/form-data" class="vision-form">
      <?php if ($isExistingFile): ?>
        <input type="hidden" name="file_existing" value="<?php echo htmlspecialchars($existingFile); ?>">
      <?php endif; ?>
      
      <div class="form-grid">
        <div class="form-group form-group-wide">
          <label for="title"><i class="fas fa-heading"></i> Título *</label>
          <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($prefilledTitle); ?>" required />
        </div>

        <div class="form-group">
          <label for="category"><i class="fas fa-tags"></i> Área/Categoria *</label>
          <select id="category" name="category" required>
            <option value="">Selecione uma categoria</option>
            <option value="Jurídico" <?php echo $prefilledCategory === 'Jurídico' ? 'selected' : ''; ?>>Jurídico</option>
            <option value="Médico" <?php echo $prefilledCategory === 'Médico' ? 'selected' : ''; ?>>Médico</option>
            <option value="Técnico" <?php echo $prefilledCategory === 'Técnico' ? 'selected' : ''; ?>>Técnico</option>
            <option value="Financeiro" <?php echo $prefilledCategory === 'Financeiro' ? 'selected' : ''; ?>>Financeiro</option>
            <option value="Marketing" <?php echo $prefilledCategory === 'Marketing' ? 'selected' : ''; ?>>Marketing</option>
            <option value="Acadêmico" <?php echo $prefilledCategory === 'Acadêmico' ? 'selected' : ''; ?>>Acadêmico</option>
            <option value="Geral" <?php echo $prefilledCategory === 'Geral' ? 'selected' : ''; ?>>Geral</option>
          </select>
        </div>

        <div class="form-group">
          <label for="source_lang"><i class="fas fa-language"></i> Idioma Fonte</label>
          <input type="text" id="source_lang" name="source_lang" placeholder="ex: pt-BR, en-US" />
        </div>

        <div class="form-group">
          <label for="target_lang"><i class="fas fa-language"></i> Idioma Alvo</label>
          <input type="text" id="target_lang" name="target_lang" placeholder="ex: en-US, pt-BR" />
        </div>

        <div class="form-group form-group-wide">
          <label for="description"><i class="fas fa-file-alt"></i> Descrição</label>
          <textarea id="description" name="description" rows="4" placeholder="Descreva o conteúdo do glossário, público, formato, etc."></textarea>
        </div>

        <?php if (!$isExistingFile): ?>
        <div class="form-group form-group-wide">
          <label for="glossary_file"><i class="fas fa-file-arrow-up"></i> Arquivo (PDF, CSV, XLSX) *</label>
          <input type="file" id="glossary_file" name="glossary_file" accept=".pdf,.csv,.xlsx,application/pdf,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required />
          <small>Máx. recomendado: 10MB. Formatos aceitos: PDF, CSV, XLSX</small>
        </div>
        <?php endif; ?>
      </div>

      <div class="form-actions">
        <button type="submit" class="cta-btn">
          <i class="fas fa-<?php echo $isExistingFile ? 'save' : 'upload'; ?>"></i> 
          <?php echo $isExistingFile ? 'Cadastrar Metadados' : 'Enviar e Registrar'; ?>
        </button>
        <a href="../glossarios.php" class="page-btn"><i class="fas fa-list"></i> Ver Lista</a>
      </div>
    </form>
  </div>

  <div class="video-card">
    <h2><i class="fas fa-folder-open"></i> <?php echo $isExistingFile ? 'Sobre Arquivos Existentes' : 'Dicas e Status'; ?></h2>
    <div class="dashboard-sections">
      <?php if ($isExistingFile): ?>
      <div>
        <h3><i class="fas fa-info-circle"></i> Arquivo Detectado</h3>
        <p>• Este arquivo foi encontrado automaticamente na pasta de uploads.</p>
        <p>• Não é necessário fazer upload novamente, apenas definir os metadados.</p>
        <p>• Após o cadastro, o arquivo ficará disponível para download pelos usuários.</p>
      </div>
      <div>
        <h3><i class="fas fa-cogs"></i> Próximos Passos</h3>
        <p>• Preencha o título e categoria (obrigatórios).</p>
        <p>• Defina idiomas se aplicável.</p>
        <p>• Adicione uma descrição detalhada.</p>
        <p>• Clique em "Cadastrar Metadados" para finalizar.</p>
      </div>
      <?php else: ?>
      <div>
        <h3><i class="fas fa-info-circle"></i> Recomendações</h3>
        <p>• Garanta que a pasta <code>/uploads/glossarios/</code> exista e tenha permissão de escrita.</p>
        <p>• Utilize nomes de arquivo claros. O sistema preserva parte do nome e adiciona um identificador único.</p>
        <p>• Para grandes volumes, você pode enviar arquivos por FTP e cadastrá-los via varredura automática.</p>
      </div>
      <div>
        <h3><i class="fas fa-database"></i> Colunas de Idioma</h3>
        <p>Este formulário usa campos de idioma se as colunas existirem na tabela <code>glossary_files</code>:</p>
        <ul class="list-disc ml-6">
          <li><code>source_lang</code></li>
          <li><code>target_lang</code></li>
        </ul>
        <p>Se ainda não existirem, você pode adicioná-las com:</p>
        <pre><code>ALTER TABLE glossary_files
  ADD COLUMN source_lang VARCHAR(10) DEFAULT NULL AFTER category,
  ADD COLUMN target_lang VARCHAR(10) DEFAULT NULL AFTER source_lang;</code></pre>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../vision/includes/footer.php'; ?>