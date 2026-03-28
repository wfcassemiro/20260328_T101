<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Verificar se é admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: /login.php');
    exit;
}

// --- LÓGICA DE DOWNLOADS (Deve vir antes de qualquer HTML) ---
$request_action = $_GET['action'] ?? '';

// 1. Download do Relatório de Arquivos Faltantes (TXT)
if ($request_action === 'download_missing_report' && isset($_SESSION['missing_files_list'])) {
    $filename = "relatorio_arquivos_faltantes_" . date('Y-m-d_H-i') . ".txt";
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo "RELATÓRIO DE ARQUIVOS NÃO ENCONTRADOS - " . date('d/m/Y H:i') . "\n";
    echo "Estes arquivos constavam no CSV importado, mas não foram achados na pasta de uploads.\n";
    echo str_repeat("-", 80) . "\n\n";
    
    foreach ($_SESSION['missing_files_list'] as $file) {
        echo "- " . $file . "\n";
    }
    exit;
}

// 2. Exportar Glossários Cadastrados (CSV)
if ($request_action === 'export_glossaries') {
    $filename = "glossarios_cadastrados_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    // BOM para Excel reconhecer UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Cabeçalho
    fputcsv($output, ['ID', 'Título', 'Categoria', 'Tipo', 'Downloads', 'Data Criação', 'URL'], ';');
    
    try {
        $stmt = $pdo->query("SELECT * FROM glossary_files ORDER BY title ASC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['id'],
                $row['title'],
                $row['category'],
                $row['file_type'],
                $row['download_count'],
                $row['created_at'],
                $row['download_url']
            ], ';');
        }
    } catch (Exception $e) {
        // Silencioso em exportação
    }
    
    fclose($output);
    exit;
}

$page_title = 'Gerenciar Glossários - Admin';
$message = '';
$error = '';
$notFoundList = []; 

// Definição do diretório de upload para uso global
$uploadDir = __DIR__ . '/../uploads/glossarios/';

// --- PROCESSAMENTO DE POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'import_csv':
                if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
                    $tmpName = $_FILES['csv_file']['tmp_name'];
                    
                    // Limpa sessão anterior de erros
                    unset($_SESSION['missing_files_list']);

                    $fileMap = [];
                    if (is_dir($uploadDir)) {
                        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadDir));
                        foreach ($iterator as $file) {
                            if ($file->isFile()) {
                                $relativePath = str_replace(realpath($uploadDir) . DIRECTORY_SEPARATOR, '', $file->getRealPath());
                                $relativePath = str_replace('\\', '/', $relativePath);
                                $lowerFilename = strtolower($file->getFilename());
                                $fileMap[$lowerFilename] = $relativePath;
                            }
                        }
                    }

                    $handle = fopen($tmpName, "r");
                    $importedCount = 0;
                    $skippedCount = 0;
                    $notFoundCount = 0;

                    if ($handle !== FALSE) {
                        fgetcsv($handle, 0, ";"); // Pular cabeçalho

                        while (($data = fgetcsv($handle, 0, ";")) !== FALSE) {
                            $nameNoExt = trim($data[0] ?? '');
                            $category = trim($data[1] ?? 'Geral');
                            $langSource = trim($data[2] ?? '');
                            $langTarget = trim($data[3] ?? '');
                            $descText = trim($data[4] ?? '');
                            $fileType = strtoupper(trim($data[6] ?? 'PDF'));

                            if (empty($nameNoExt)) continue;

                            $searchFilename = strtolower($nameNoExt . '.' . $fileType);
                            
                            if (isset($fileMap[$searchFilename])) {
                                $foundPath = $fileMap[$searchFilename];
                                $download_url = '/uploads/glossarios/' . $foundPath;

                                $fullDescription = $descText;
                                if ($langSource && $langTarget) {
                                    $fullDescription .= " (Fonte: $langSource | Alvo: $langTarget)";
                                }

                                $id = bin2hex(random_bytes(16));
                                $formatted_id = sprintf('%s-%s-%s-%s-%s',
                                    substr($id, 0, 8), substr($id, 8, 4), substr($id, 12, 4), 
                                    substr($id, 16, 4), substr($id, 20, 12)
                                );

                                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM glossary_files WHERE title = ? OR download_url = ?");
                                $checkStmt->execute([$nameNoExt, $download_url]);
                                
                                if ($checkStmt->fetchColumn() == 0) {
                                    $stmt = $pdo->prepare("INSERT INTO glossary_files (id, title, description, category, file_type, download_url, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
                                    $stmt->execute([$formatted_id, $nameNoExt, $fullDescription, $category, $fileType, $download_url]);
                                    $importedCount++;
                                } else {
                                    $skippedCount++;
                                }
                            } else {
                                $notFoundList[] = $nameNoExt . '.' . $fileType;
                                $notFoundCount++;
                            }
                        }
                        fclose($handle);
                        
                        // Salva lista de erros na sessão para download
                        if (!empty($notFoundList)) {
                            $_SESSION['missing_files_list'] = $notFoundList;
                        }

                        $message = "Processo finalizado: $importedCount importados, $skippedCount duplicados ignorados.";
                        
                        if ($notFoundCount > 0) {
                            $error = "Atenção: $notFoundCount arquivos listados no CSV não foram encontrados na pasta de uploads.";
                        }
                    } else {
                        $error = "Não foi possível abrir o arquivo CSV.";
                    }
                } else {
                    $error = "Erro no upload do arquivo.";
                }
                break;

            case 'add_glossary':
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $category = trim($_POST['category']);
                $download_url = trim($_POST['download_url']);
                $file_type = trim($_POST['file_type']);

                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM glossary_files WHERE title = ? OR download_url = ?");
                $checkStmt->execute([$title, $download_url]);

                if ($checkStmt->fetchColumn() > 0) {
                    $error = 'Já existe um glossário com este título ou URL.';
                } else {
                    $id = bin2hex(random_bytes(16));
                    $formatted_id = sprintf('%s-%s-%s-%s-%s',
                        substr($id, 0, 8), substr($id, 8, 4), substr($id, 12, 4), 
                        substr($id, 16, 4), substr($id, 20, 12)
                    );

                    $stmt = $pdo->prepare("INSERT INTO glossary_files (id, title, description, category, file_type, download_url, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
                    $stmt->execute([$formatted_id, $title, $description, $category, $file_type, $download_url]);
                    $message = 'Glossário adicionado com sucesso!';
                }
                break;

            case 'toggle_glossary':
                $glossary_id = trim($_POST['glossary_id']);
                $stmt = $pdo->prepare("UPDATE glossary_files SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$glossary_id]);
                $message = 'Status atualizado!';
                break;

            case 'delete_glossary':
                $glossary_id = trim($_POST['glossary_id']);
                $stmt = $pdo->prepare("DELETE FROM glossary_files WHERE id = ?");
                $stmt->execute([$glossary_id]);
                $message = 'Glossário removido!';
                break;
        }
    } catch (PDOException $e) {
        $error = 'Erro ao processar: ' . $e->getMessage();
    } catch (Exception $e) {
        $error = 'Erro inesperado: ' . $e->getMessage();
    }
}

// --- BUSCAS DE DADOS ---
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';

try {
    $where_conditions = [];
    $params = [];

    if ($search) {
        $where_conditions[] = "(title LIKE ? OR description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($category_filter) {
        $where_conditions[] = "category = ?";
        $params[] = $category_filter;
    }

    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }

    $stmt = $pdo->prepare("SELECT * FROM glossary_files $where_clause ORDER BY created_at DESC");
    $stmt->execute($params);
    $glossaries = $stmt->fetchAll();

    $stmt = $pdo->query("SELECT DISTINCT category FROM glossary_files WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->query("SELECT COUNT(*) FROM glossary_files WHERE is_active = 1");
    $active_glossaries = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT SUM(download_count) FROM glossary_files");
    $total_downloads = (int) $stmt->fetchColumn();

} catch (PDOException $e) {
    $glossaries = [];
    $categories = [];
    $active_glossaries = 0;
    $total_downloads = 0;
    $error = 'Erro ao carregar dados.';
}

// Varredura de arquivos não cadastrados
$unregisteredFiles = [];
try {
    $stmt = $pdo->query("SELECT download_url FROM glossary_files");
    $existingPaths = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (is_dir($uploadDir)) {
        $files = scandir($uploadDir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || is_dir($uploadDir . $file)) continue;

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf', 'csv', 'xlsx'])) {
                $registered = false;
                foreach($existingPaths as $path) {
                    if (strpos($path, $file) !== false) {
                        $registered = true;
                        break;
                    }
                }
                if (!$registered) {
                    $filePath = $uploadDir . $file;
                    $unregisteredFiles[] = [
                        'name' => $file,
                        'size' => file_exists($filePath) ? filesize($filePath) : 0,
                        'type' => strtoupper($ext),
                        'modified' => file_exists($filePath) ? filemtime($filePath) : 0
                    ];
                }
            }
        }
    }
} catch (Exception $e) { }

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<style>
    /* Container Principal */
    .main-content {
        padding: 40px 60px; /* Aumentado para dar respiro nas laterais */
        max-width: 1600px;
        margin: 0 auto;
        box-sizing: border-box;
    }

    /* Grid de Estatísticas (Stats Cards) - Correção total de alinhamento */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); /* Responsivo real */
        gap: 24px; /* Espaço real entre os cards */
        margin-bottom: 40px;
        width: 100%;
    }

    .stats-card {
        margin: 0 !important; /* Remove margens que quebravam o grid */
        height: 100%;
        min-height: 140px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    /* Cards Gerais (Vídeo Card) */
    .video-card {
        margin-bottom: 40px !important;
        padding: 30px !important;
        width: 100%;
        box-sizing: border-box;
    }

    /* Botões */
    .btn-action-group {
        display: flex;
        gap: 15px;
        margin-top: 15px;
        flex-wrap: wrap;
    }
    
    .btn-secondary-action {
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #fff;
        padding: 8px 16px;
        border-radius: 6px;
        text-decoration: none;
        font-size: 0.9rem;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-secondary-action:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .btn-back {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 1.2rem;
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.3);
        background: rgba(255, 255, 255, 0.05);
        color: #fff;
        font-weight: 500;
        text-decoration: none;
        margin-top: 20px;
        margin-bottom: 20px;
    }

    /* Tabela de Glossários */
    .glossary-title {
        display: block;
        font-weight: 700;
        font-size: 1rem;
        color: #fff;
        margin-bottom: 4px;
        text-transform: uppercase; /* Força maiúscula se desejar, ou remova */
    }
    
    .glossary-desc {
        display: block;
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.5);
        line-height: 1.4;
        max-width: 500px;
    }

    /* Lista de Erros */
    .error-box {
        background: rgba(255, 71, 87, 0.1);
        border: 1px solid rgba(255, 71, 87, 0.3);
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 30px;
    }
    
    .error-list {
        max-height: 200px;
        overflow-y: auto;
        background: rgba(0,0,0,0.2);
        padding: 10px;
        border-radius: 6px;
        margin-top: 10px;
    }
    
    .error-item {
        color: #ff6b81;
        font-family: monospace;
        font-size: 0.85rem;
        padding: 4px 0;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }

    /* Responsividade */
    @media (max-width: 768px) {
        .main-content { padding: 20px; }
        .stats-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="main-content">
    
    <div class="glass-hero" style="margin-bottom: 0;">
        <div class="hero-content">
            <h1><i class="fas fa-book"></i> Gerenciar glossários</h1>
            <p>Administração dos glossários especializados da plataforma</p>
        </div>
    </div>

    <div style="width: 100%;">
        <a href="index.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Voltar ao admin
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert-success" style="margin-bottom: 2rem;">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert-error" style="margin-bottom: 2rem;">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($notFoundList) || isset($_SESSION['missing_files_list'])): ?>
        <?php 
            $displayList = !empty($notFoundList) ? $notFoundList : $_SESSION['missing_files_list']; 
            $countMissing = count($displayList);
        ?>
        <div class="error-box">
            <h3 style="color: #ff4757; margin-top:0;">
                <i class="fas fa-exclamation-triangle"></i> Arquivos não encontrados (<?php echo $countMissing; ?>)
            </h3>
            <p style="color: #ddd; font-size: 0.9rem;">
                Os arquivos abaixo constam no CSV mas não estão na pasta de uploads.
            </p>
            
            <div class="btn-action-group">
                <a href="glossarios.php?action=download_missing_report" class="btn-secondary-action" style="border-color: #ff4757; color: #ff6b81;">
                    <i class="fas fa-file-download"></i> Baixar relatório (.txt)
                </a>
            </div>

            <div class="error-list">
                <?php foreach($displayList as $missingFile): ?>
                    <div class="error-item"><i class="fas fa-times"></i> <?php echo htmlspecialchars($missingFile); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="video-card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3 style="text-transform: none; margin-bottom: 5px;">Glossários ativos</h3>
                    <span class="stats-number" style="font-size: 2rem;"><?php echo number_format($active_glossaries); ?></span>
                </div>
                <div class="stats-icon stats-icon-green">
                    <i class="fas fa-book-open"></i>
                </div>
            </div>
        </div>

        <div class="video-card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3 style="text-transform: none; margin-bottom: 5px;">Total glossários</h3>
                    <span class="stats-number" style="font-size: 2rem;"><?php echo count($glossaries); ?></span>
                </div>
                <div class="stats-icon stats-icon-blue">
                    <i class="fas fa-book"></i>
                </div>
            </div>
        </div>

        <div class="video-card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3 style="text-transform: none; margin-bottom: 5px;">Downloads totais</h3>
                    <span class="stats-number" style="font-size: 2rem;"><?php echo number_format($total_downloads); ?></span>
                </div>
                <div class="stats-icon stats-icon-purple">
                    <i class="fas fa-download"></i>
                </div>
            </div>
        </div>

        <div class="video-card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3 style="text-transform: none; margin-bottom: 5px;">Não cadastrados (raiz)</h3>
                    <span class="stats-number" style="font-size: 2rem;"><?php echo count($unregisteredFiles); ?></span>
                </div>
                <div class="stats-icon stats-icon-orange">
                    <i class="fas fa-folder-open"></i>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($unregisteredFiles)): ?>
        <div class="video-card">
            <h2 style="text-transform: none; margin-bottom: 1.5rem;"><i class="fas fa-folder-open"></i> Arquivos detectados na raiz (Não cadastrados)</h2>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Arquivo</th>
                        <th>Tamanho</th>
                        <th>Tipo</th>
                        <th>Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($unregisteredFiles as $file): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($file['name']); ?></td>
                            <td><?php echo $file['size'] > 0 ? number_format($file['size'] / 1024, 1) . ' KB' : '-'; ?></td>
                            <td><?php echo htmlspecialchars($file['type']); ?></td>
                            <td>
                                <a href="glossary/upload_form.php?file_existing=<?php echo urlencode($file['name']); ?>" class="cta-btn">Cadastrar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="video-card" style="border-left: 4px solid #7B61FF;">
        <h2 style="text-transform: none;"><i class="fas fa-file-csv"></i> Importação em massa (CSV)</h2>
        <p style="margin-bottom: 1.5rem; color: rgba(255,255,255,0.7); font-size: 0.95rem;">
            Faça upload do CSV (separado por ponto e vírgula). O sistema ignorará diferenças de maiúsculas/minúsculas no nome dos arquivos e pulará duplicatas.
        </p>
        
        <form method="POST" enctype="multipart/form-data" style="width: 100%; display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="action" value="import_csv">
            <div style="flex-grow: 1; min-width: 300px;">
                <label for="csv_file" style="color: #fff; display: block; margin-bottom: 8px;">Selecionar arquivo CSV</label>
                <input type="file" id="csv_file" name="csv_file" accept=".csv" required
                       style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.2); background: rgba(255, 255, 255, 0.05); color: #fff;">
            </div>
            <button type="submit" class="cta-btn" style="background: linear-gradient(135deg, #2ecc71, #27ae60); height: 48px;">
                <i class="fas fa-upload"></i> Processar CSV
            </button>
        </form>
    </div>

    <div class="video-card">
        <h2 style="text-transform: none; margin-bottom: 1.5rem;"><i class="fas fa-plus-circle"></i> Adicionar novo glossário manualmente</h2>
        <form method="POST" style="width: 100%;">
            <input type="hidden" name="action" value="add_glossary">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                <div style="display: flex; flex-direction: column; gap: 0.5rem; grid-column: span 2;">
                    <label for="title" style="color: #fff;">Título do glossário *</label>
                    <input type="text" id="title" name="title" required
                           style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.2); background: rgba(255, 255, 255, 0.05); color: #fff;">
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <label for="category" style="color: #fff;">Categoria *</label>
                    <select id="category" name="category" required
                            style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.2); background: rgba(255, 255, 255, 0.05); color: #fff;">
                        <option value="">Selecione...</option>
                        <?php foreach($categories as $cat) echo "<option value='$cat'>$cat</option>"; ?>
                        <option value="Jurídico">Jurídico</option>
                        <option value="Médico">Médico</option>
                        <option value="Técnico">Técnico</option>
                        <option value="Financeiro">Financeiro</option>
                        <option value="Marketing">Marketing</option>
                        <option value="Acadêmico">Acadêmico</option>
                        <option value="Geral">Geral</option>
                    </select>
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <label for="file_type" style="color: #fff;">Tipo *</label>
                    <select id="file_type" name="file_type" required
                            style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.2); background: rgba(255, 255, 255, 0.05); color: #fff;">
                        <option value="PDF">PDF</option>
                        <option value="XLSX">Excel (XLSX)</option>
                        <option value="CSV">CSV</option>
                        <option value="TXT">Texto (TXT)</option>
                    </select>
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <label for="download_url" style="color: #fff;">URL de download</label>
                    <input type="url" id="download_url" name="download_url" placeholder="https://..."
                           style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.2); background: rgba(255, 255, 255, 0.05); color: #fff;">
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.5rem; grid-column: span 2;">
                    <label for="description" style="color: #fff;">Descrição</label>
                    <textarea id="description" name="description" rows="3"
                              style="width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.2); background: rgba(255, 255, 255, 0.05); color: #fff;"></textarea>
                </div>
            </div>
            <button type="submit" class="cta-btn"><i class="fas fa-plus"></i> Adicionar glossário</button>
        </form>
    </div>

    <div class="video-card">
        <div class="card-header">
            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; flex-wrap: wrap; gap: 15px;">
                <h2 style="text-transform: none; margin: 0;"><i class="fas fa-list"></i> Lista de glossários</h2>
                
                <a href="glossarios.php?action=export_glossaries" class="btn-secondary-action">
                    <i class="fas fa-file-csv"></i> Baixar lista completa (.csv)
                </a>
            </div>

            <div class="search-filters" style="margin-top: 20px;">
                <form method="GET" class="search-form">
                    <input type="text" name="search" placeholder="Buscar..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="page-btn"><i class="fas fa-search"></i></button>
                    <?php if ($search): ?><a href="glossarios.php" class="page-btn"><i class="fas fa-times"></i></a><?php endif; ?>
                </form>
            </div>
        </div>

        <?php if (empty($glossaries)): ?>
            <div class="alert-warning"><i class="fas fa-info-circle"></i> Nenhum glossário encontrado.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 45%;">Glossário</th>
                            <th>Categoria</th>
                            <th>Downloads</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($glossaries as $glossary): ?>
                        <tr>
                            <td>
                                <div class="project-info">
                                    <span class="glossary-title"><?php echo htmlspecialchars($glossary['title']); ?></span>
                                    
                                    <?php if (!empty($glossary['description'])): ?>
                                        <span class="glossary-desc">
                                            <?php echo htmlspecialchars(substr($glossary['description'], 0, 120)) . (strlen($glossary['description']) > 120 ? '...' : ''); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($glossary['category'] ?? '-'); ?></td>
                            <td><?php echo number_format($glossary['download_count'] ?? 0); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $glossary['is_active'] ? 'completed' : 'cancelled'; ?>">
                                    <?php echo $glossary['is_active'] ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_glossary">
                                        <input type="hidden" name="glossary_id" value="<?php echo htmlspecialchars($glossary['id']); ?>">
                                        <button type="submit" class="page-btn"><i class="fas fa-toggle-<?php echo $glossary['is_active'] ? 'on' : 'off'; ?>"></i></button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Excluir?')">
                                        <input type="hidden" name="action" value="delete_glossary">
                                        <input type="hidden" name="glossary_id" value="<?php echo htmlspecialchars($glossary['id']); ?>">
                                        <button type="submit" class="page-btn btn-danger"><i class="fas fa-trash"></i></button>
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

    <div style="width: 100%; margin-top: 1rem;">
        <a href="index.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Voltar ao admin
        </a>
    </div>

</div>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>