<?php
session_start();

// CORRIGIR: Carregar database.php ANTES de auth_check.php  
require_once '../config/database.php';
require_once 'auth_check.php';

// Verifica se é admin
if (!isAdmin()) {
    header('Location: /login.php');
    exit;
}

$page_title = 'Admin - Gerenciar Glossários';
$active_page = 'glossarios';

$success_message = isset($_SESSION["message"]) && isset($_SESSION["success"]) ? $_SESSION["message"] : '';
$error_message = isset($_SESSION["message"]) && isset($_SESSION["error"]) ? $_SESSION["message"] : '';
unset($_SESSION["message"]);
unset($_SESSION["success"]);
unset($_SESSION["error"]);

// Define o diretório de glossários no servidor
$uploadDir = __DIR__ . '/../../uploads/glossarios/';

// Cria a pasta se ela não existir
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Funções de formatação
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Processar upload se houver
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['glossary_file'])) {
    $file = $_FILES['glossary_file'];
    
    // Validações
    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['pdf', 'docx', 'xlsx', 'csv', 'doc', 'xls', 'txt'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($fileExtension, $allowedTypes)) {
            if ($file['size'] <= 100 * 1024 * 1024) { // 100MB max
                // Gerar nome único para o arquivo
                $uniqueFileName = time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $file['name']);
                $uploadPath = $uploadDir . $uniqueFileName;
                
                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    try {
                        // Inserir no banco de dados
                        $stmt = $pdo->prepare("INSERT INTO glossary_files (id, title, description, category, file_type, download_url, file_size, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                        $stmt->execute([
                            generateUUID(),
                            $_POST['title'] ?: pathinfo($file['name'], PATHINFO_FILENAME),
                            $_POST['description'] ?: '',
                            $_POST['category'] ?: 'Geral',
                            strtoupper($fileExtension),
                            '/uploads/glossarios/' . $uniqueFileName,
                            formatFileSize($file['size'])
                        ]);
                        
                        $_SESSION["message"] = "Glossário enviado com sucesso!";
                        $_SESSION["success"] = true;
                    } catch (Exception $e) {
                        unlink($uploadPath); // Remove arquivo se erro no BD
                        $_SESSION["message"] = "Erro ao salvar no banco: " . $e->getMessage();
                        $_SESSION["error"] = true;
                    }
                } else {
                    $_SESSION["message"] = "Erro ao mover arquivo para pasta de uploads.";
                    $_SESSION["error"] = true;
                }
            } else {
                $_SESSION["message"] = "Arquivo muito grande. Máximo permitido: 100MB.";
                $_SESSION["error"] = true;
            }
        } else {
            $_SESSION["message"] = "Tipo de arquivo não permitido. Use: PDF, DOCX, XLSX, CSV, DOC, XLS, TXT.";
            $_SESSION["error"] = true;
        }
    } else {
        $_SESSION["message"] = "Erro no upload. Código: " . $file['error'];
        $_SESSION["error"] = true;
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$files_on_server = [];
if (is_dir($uploadDir)) {
    $scan = scandir($uploadDir);
    $files_on_server = array_diff($scan, ['.', '..', '.htaccess']);
}

// Buscar glossários existentes no banco de dados
try {
    $stmt = $pdo->prepare("SELECT * FROM glossary_files WHERE is_active = 1 ORDER BY created_at DESC");
    $stmt->execute();
    $db_files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $db_filenames = array_column($db_files, 'download_url');
} catch (Exception $e) {
    $error_message = 'Erro ao carregar glossários do banco de dados: ' . $e->getMessage();
    $db_files = [];
    $db_filenames = [];
}

// Mapeia o caminho relativo para a URL completa
$db_filenames_mapped = array_map(function($url) {
    return basename($url);
}, $db_filenames);

// Encontra arquivos na pasta que não estão no banco de dados
$unregistered_files = array_diff($files_on_server, $db_filenames_mapped);

include '../includes/header.php';
?>

<div class="flex min-h-screen bg-gray-900">
    <?php include 'admin_sidebar.php'; ?>

    <main class="flex-1 p-8 bg-gray-900">
        <div class="min-h-screen px-4 py-8">
            <div class="max-w-6xl mx-auto">
                <h1 class="text-3xl font-bold mb-8 text-white">🗂️ Gerenciar Glossários</h1>
                
                <?php if ($success_message): ?>
                <div class="bg-green-600 bg-opacity-20 border border-green-600 border-opacity-30 rounded-lg p-4 mb-6">
                    <p class="text-green-300"><?php echo htmlspecialchars($success_message); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                <div class="bg-red-600 bg-opacity-20 border border-red-600 border-opacity-30 rounded-lg p-4 mb-6">
                    <p class="text-red-300"><?php echo htmlspecialchars($error_message); ?></p>
                </div>
                <?php endif; ?>
                
                <!-- FORMULÁRIO DE UPLOAD -->
                <div class="bg-gray-800 rounded-lg p-6 mb-8">
                    <h2 class="text-xl font-bold mb-6 text-purple-400">📤 Upload de Novo Glossário</h2>
                    
                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="title" class="block text-sm font-medium mb-2 text-gray-300">Título *</label>
                                <input type="text" id="title" name="title" required
                                    class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white"
                                    placeholder="Ex: Glossário de Medicina">
                            </div>
                            
                            <div>
                                <label for="category" class="block text-sm font-medium mb-2 text-gray-300">Categoria *</label>
                                <input type="text" id="category" name="category" required
                                    class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white"
                                    placeholder="Ex: Medicina, Tecnologia, Direito">
                            </div>
                        </div>
                        
                        <div>
                            <label for="description" class="block text-sm font-medium mb-2 text-gray-300">Descrição</label>
                            <textarea id="description" name="description" rows="3"
                                class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white"
                                placeholder="Descreva o conteúdo do glossário..."></textarea>
                        </div>
                        
                        <div>
                            <label for="glossary_file" class="block text-sm font-medium mb-2 text-gray-300">Arquivo do Glossário *</label>
                            <input type="file" id="glossary_file" name="glossary_file" required
                                accept=".pdf,.docx,.xlsx,.csv,.doc,.xls,.txt"
                                class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
                            <p class="text-sm text-gray-400 mt-2">
                                📄 Formatos aceitos: PDF, DOCX, XLSX, CSV, DOC, XLS, TXT (Máx: 100MB)
                            </p>
                        </div>
                        
                        <button type="submit" 
                            class="w-full bg-purple-600 hover:bg-purple-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
                            <i class="fas fa-upload mr-2"></i>Enviar Glossário
                        </button>
                    </form>
                </div>

                <!-- ARQUIVOS PARA REGISTRAR -->
                <?php if (!empty($unregistered_files)): ?>
                <div class="bg-gray-800 rounded-lg p-6 mb-8">
                    <h2 class="text-xl font-bold mb-6 text-yellow-400">⚠️ Arquivos Não Registrados</h2>
                    <p class="text-gray-300 mb-4">Os seguintes arquivos estão na pasta mas não no banco de dados:</p>
                    
                    <form method="POST" action="glossary/process_metadata.php" class="space-y-4">
                        <?php foreach ($unregistered_files as $filename): ?>
                            <?php
                            $file_path = $uploadDir . $filename;
                            $file_size = formatFileSize(filesize($file_path));
                            $file_type = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
                            $suggested_title = pathinfo($filename, PATHINFO_FILENAME);
                            ?>
                            <div class="bg-gray-700 rounded-lg p-4 space-y-4">
                                <h4 class="font-semibold text-white">📄 <?php echo htmlspecialchars($filename); ?></h4>
                                
                                <input type="hidden" name="files[]" value="<?php echo htmlspecialchars($filename); ?>">
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium mb-2 text-gray-300">Título *</label>
                                        <input type="text" name="title[]" value="<?php echo htmlspecialchars($suggested_title); ?>" required
                                            class="w-full p-3 bg-gray-600 border border-gray-500 rounded-lg focus:border-purple-500 focus:outline-none text-white">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-2 text-gray-300">Categoria *</label>
                                        <input type="text" name="category[]" placeholder="Ex: Tecnologia, Medicina" required
                                            class="w-full p-3 bg-gray-600 border border-gray-500 rounded-lg focus:border-purple-500 focus:outline-none text-white">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-2 text-gray-300">Tipo/Tamanho</label>
                                        <input type="text" value="<?php echo $file_type . ' - ' . $file_size; ?>" readonly
                                            class="w-full p-3 bg-gray-500 border border-gray-400 rounded-lg text-gray-300">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-2 text-gray-300">Descrição</label>
                                    <textarea name="description[]" rows="2" placeholder="Descrição do glossário..."
                                        class="w-full p-3 bg-gray-600 border border-gray-500 rounded-lg focus:border-purple-500 focus:outline-none text-white"></textarea>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <button type="submit" class="w-full bg-yellow-600 hover:bg-yellow-700 py-3 rounded-lg font-semibold transition-colors text-white">
                            <i class="fas fa-plus mr-2"></i>Registrar Todos os Arquivos (<?php echo count($unregistered_files); ?>)
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- GLOSSÁRIOS REGISTRADOS -->
                <div class="bg-gray-800 rounded-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-green-400">✅ Glossários Cadastrados (<?php echo count($db_files); ?>)</h2>
                    </div>
                    
                    <?php if (!empty($db_files)): ?>
                    <div class="overflow-hidden rounded-lg border border-gray-700">
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-900">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-purple-400 uppercase tracking-wider">Glossário</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-purple-400 uppercase tracking-wider">Categoria</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-purple-400 uppercase tracking-wider">Arquivo</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-purple-400 uppercase tracking-wider">Data</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-purple-400 uppercase tracking-wider">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-700 bg-gray-800">
                                    <?php foreach ($db_files as $file): ?>
                                    <tr class="hover:bg-gray-750 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="font-medium text-white"><?php echo htmlspecialchars($file['title']); ?></div>
                                            <?php if ($file['description']): ?>
                                            <div class="text-sm text-gray-400 truncate max-w-xs">
                                                <?php echo htmlspecialchars($file['description']); ?>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="bg-purple-600 text-white px-3 py-1 rounded-full text-sm">
                                                <?php echo htmlspecialchars($file['category']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm">
                                                <span class="font-mono text-gray-300"><?php echo $file['file_type']; ?></span>
                                                <div class="text-gray-500"><?php echo htmlspecialchars($file['file_size'] ?? 'N/A'); ?></div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-400">
                                            <?php echo date('d/m/Y', strtotime($file['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex space-x-2">
                                                <a href="../download.php?id=<?php echo htmlspecialchars($file['id']); ?>" 
                                                   class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm transition-colors">
                                                    <i class="fas fa-download mr-1"></i>Baixar
                                                </a>
                                                <button onclick="deleteGlossary('<?php echo $file['id']; ?>', '<?php echo htmlspecialchars($file['title']); ?>')"
                                                        class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm transition-colors">
                                                    <i class="fas fa-trash mr-1"></i>Excluir
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-12 bg-gray-700 rounded-lg">
                        <div class="text-6xl mb-4">📁</div>
                        <h3 class="text-xl font-medium text-white mb-2">Nenhum glossário cadastrado</h3>
                        <p class="text-gray-400">Use o formulário acima para enviar seu primeiro glossário.</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- INFORMAÇÕES ADICIONAIS -->
                <div class="bg-blue-900 bg-opacity-30 border border-blue-600 border-opacity-30 rounded-lg p-6 mt-8">
                    <h3 class="text-lg font-semibold text-blue-300 mb-4">ℹ️ Informações Importantes</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-blue-200">
                        <div>
                            <h4 class="font-medium mb-2">📂 Localização dos Arquivos</h4>
                            <p>Os arquivos são armazenados em: <code>/uploads/glossarios/</code></p>
                        </div>
                        <div>
                            <h4 class="font-medium mb-2">📏 Limites de Upload</h4>
                            <p>Tamanho máximo: 100MB por arquivo</p>
                        </div>
                        <div>
                            <h4 class="font-medium mb-2">📄 Formatos Aceitos</h4>
                            <p>PDF, DOCX, XLSX, CSV, DOC, XLS, TXT</p>
                        </div>
                        <div>
                            <h4 class="font-medium mb-2">🔄 Backup</h4>
                            <p>Faça backup regular da pasta uploads/glossarios/</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal de Confirmação para Exclusão -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-gray-800 rounded-lg p-6 max-w-md mx-4">
        <h3 class="text-lg font-semibold text-white mb-4">Confirmar Exclusão</h3>
        <p class="text-gray-300 mb-6">Tem certeza de que deseja excluir o glossário "<span id="deleteTitle"></span>"?</p>
        <div class="flex space-x-4">
            <button onclick="confirmDelete()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded">
                Excluir
            </button>
            <button onclick="closeDeleteModal()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                Cancelar
            </button>
        </div>
    </div>
</div>

<script>
let deleteId = null;

function deleteGlossary(id, title) {
    deleteId = id;
    document.getElementById('deleteTitle').textContent = title;
    document.getElementById('deleteModal').classList.remove('hidden');
    document.getElementById('deleteModal').classList.add('flex');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    document.getElementById('deleteModal').classList.remove('flex');
    deleteId = null;
}

function confirmDelete() {
    if (deleteId) {
        // Criar formulário para enviar DELETE
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'glossary/delete_glossary.php';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'glossary_id';
        input.value = deleteId;
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}

// Validação do formulário de upload
document.querySelector('form[enctype="multipart/form-data"]').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('glossary_file');
    const file = fileInput.files[0];
    
    if (file && file.size > 100 * 1024 * 1024) {
        e.preventDefault();
        alert('Arquivo muito grande! O tamanho máximo é 100MB.');
        return false;
    }
});
</script>

<?php include '../includes/footer.php'; ?>