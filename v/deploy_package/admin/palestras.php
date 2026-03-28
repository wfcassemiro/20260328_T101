<?php
session_start();
require_once '../config/database.php';

// Verificar se é admin
if (!isAdmin()) {
    header('Location: /login.php');
    exit;
}

// Função para gerar UUID se não existir
if (!function_exists('generateUUID')) {
    function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

// Defina a página ativa para o menu lateral
$active_page = 'palestras';

$page_title = 'Admin - Gerenciar Palestras';
$success_message = '';
$error_message = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_lecture'])) {
        // Adicionar nova palestra
        $title = trim($_POST['title']);
        $speaker = trim($_POST['speaker']);
        $description = trim($_POST['description']);
        $duration_minutes = intval($_POST['duration_minutes']);
        $embed_code = trim($_POST['embed_code']);
        $thumbnail_url = trim($_POST['thumbnail_url']);
        $category = trim($_POST['category']);
        $tags = array_map('trim', explode(',', $_POST['tags']));
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $is_live = isset($_POST['is_live']) ? 1 : 0;

        if (!empty($title) && !empty($speaker) && !empty($description) && !empty($embed_code)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO lectures (id, title, speaker, description, duration_minutes, embed_code, thumbnail_url, category, tags, is_featured, is_live) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    generateUUID(),
                    $title,
                    $speaker,
                    $description,
                    $duration_minutes,
                    $embed_code,
                    $thumbnail_url,
                    $category,
                    json_encode($tags),
                    $is_featured,
                    $is_live
                ]);
                $success_message = "Palestra adicionada com sucesso!";
            } catch (Exception $e) {
                $error_message = "Erro ao adicionar palestra: " . $e->getMessage();
            }
        } else {
            $error_message = "Todos os campos obrigatórios devem ser preenchidos.";
        }
    }

    if (isset($_POST['edit_lecture'])) {
        // Editar palestra existente
        $id = $_POST['lecture_id'];
        $title = trim($_POST['title']);
        $speaker = trim($_POST['speaker']);
        $description = trim($_POST['description']);
        $duration_minutes = intval($_POST['duration_minutes']);
        $embed_code = trim($_POST['embed_code']);
        $thumbnail_url = trim($_POST['thumbnail_url']);
        $category = trim($_POST['category']);
        $tags = array_map('trim', explode(',', $_POST['tags']));
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $is_live = isset($_POST['is_live']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("UPDATE lectures SET title=?, speaker=?, description=?, duration_minutes=?, embed_code=?, thumbnail_url=?, category=?, tags=?, is_featured=?, is_live=? WHERE id=?");
            $stmt->execute([
                $title,
                $speaker,
                $description,
                $duration_minutes,
                $embed_code,
                $thumbnail_url,
                $category,
                json_encode($tags),
                $is_featured,
                $is_live,
                $id
            ]);
            $success_message = "Palestra atualizada com sucesso!";
        } catch (Exception $e) {
            $error_message = "Erro ao atualizar palestra: " . $e->getMessage();
        }
    }

    if (isset($_POST['delete_lecture'])) {
        // Excluir palestra
        $id = $_POST['lecture_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM lectures WHERE id = ?");
            $stmt->execute([$id]);
            $success_message = "Palestra excluída com sucesso!";
        } catch (Exception $e) {
            $error_message = "Erro ao excluir palestra: " . $e->getMessage();
        }
    }

    if (isset($_POST['upload_lectures']) && isset($_FILES['csv_file'])) {
        // Upload em lote via CSV
        if ($_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $file_path = $_FILES['csv_file']['tmp_name'];
            $file_extension = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));

            if ($file_extension === 'csv') {
                try {
                    $handle = fopen($file_path, 'r');
                    $header = fgetcsv($handle, 0, ';'); // Pular cabeçalho, delimitador ;
                    $count_inserted = 0;
                    $count_updated = 0;

                    while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
                        // Mapeamento: 
                        // 0: videoExternalId (usar como ID se não vazio, senão gerar UUID)
                        // 1: title
                        // 2: speaker
                        // 3: speaker_minibio (ignorar)
                        // 4: description
                        // 5: duration (em segundos)
                        // 6: embed
                        // 7: thumbnail_url
                        // 8: category
                        // 9: tags_csv
                        // 10: is_featured
                        // 11: is_live

                        if (count($data) >= 12) {
                            $duration_minutes = intval(round(floatval($data[5]) / 60)); // converte segundos para minutos

                            // Usar videoExternalId se disponível, senão gerar UUID
                            $lecture_id = !empty($data[0]) ? $data[0] : generateUUID();

                            // Verificar se já existe uma palestra com este ID
                            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM lectures WHERE id = ?");
                            $check_stmt->execute([$lecture_id]);

                            if ($check_stmt->fetchColumn() == 0) {
                                // Inserir nova palestra
                                $stmt = $pdo->prepare("INSERT INTO lectures (id, title, speaker, description, duration_minutes, embed_code, thumbnail_url, category, tags, is_featured, is_live) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmt->execute([
                                    $lecture_id,
                                    $data[1], // title
                                    $data[2], // speaker
                                    $data[4], // description
                                    $duration_minutes, // duration_minutes (convertido)
                                    $data[6], // embed_code
                                    $data[7], // thumbnail_url
                                    $data[8], // category
                                    json_encode(array_map('trim', explode(',', $data[9]))), // tags
                                    intval($data[10]), // is_featured
                                    intval($data[11])  // is_live
                                ]);
                                $count_inserted++;
                            } else {
                                // Atualizar palestra existente
                                $stmt = $pdo->prepare("UPDATE lectures SET title=?, speaker=?, description=?, duration_minutes=?, embed_code=?, thumbnail_url=?, category=?, tags=?, is_featured=?, is_live=? WHERE id=?");
                                $stmt->execute([
                                    $data[1], // title
                                    $data[2], // speaker
                                    $data[4], // description
                                    $duration_minutes, // duration_minutes (convertido)
                                    $data[6], // embed_code
                                    $data[7], // thumbnail_url
                                    $data[8], // category
                                    json_encode(array_map('trim', explode(',', $data[9]))), // tags
                                    intval($data[10]), // is_featured
                                    intval($data[11]), // is_live
                                    $lecture_id
                                ]);
                                $count_updated++;
                            }
                        }
                    }
                    fclose($handle);
                    $success_message = "Upload realizado com sucesso! $count_inserted palestras adicionadas e $count_updated palestras atualizadas.";
                } catch (Exception $e) {
                    $error_message = "Erro ao processar arquivo: " . $e->getMessage();
                }
            } else {
                $error_message = "Apenas arquivos CSV são aceitos.";
            }
        } else {
            $error_message = "Erro no upload do arquivo.";
        }
    }
}

// Buscar palestras
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(title LIKE ? OR speaker LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $where_conditions[] = "category = ?";
    $params[] = $category;
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Contar total
    $count_sql = "SELECT COUNT(*) FROM lectures $where_sql";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_lectures = $stmt->fetchColumn();

    // Buscar palestras
    $sql = "SELECT * FROM lectures $where_sql ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $lectures = $stmt->fetchAll();

    // Buscar categorias
    $stmt = $pdo->prepare("SELECT DISTINCT category FROM lectures ORDER BY category");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $total_pages = ceil($total_lectures / $per_page);

} catch (Exception $e) {
    $lectures = [];
    $categories = [];
    $total_lectures = 0;
    $total_pages = 0;
}

// Se está editando, buscar dados da palestra
$editing_lecture = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM lectures WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $editing_lecture = $stmt->fetch();
    } catch (Exception $e) {
        $error_message = "Palestra não encontrada.";
    }
}

include '../includes/header.php';
?>

<div class="flex min-h-screen bg-gray-900">
    <!-- Menu lateral -->
    <?php include 'admin_sidebar.php'; ?>

    <!-- Conteúdo principal -->
    <main class="flex-1 p-8 bg-gray-900">
        <div class="min-h-screen px-4 py-8">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-8">
                    <h1 class="text-3xl font-bold">Gerenciar Palestras</h1>
                    <div class="space-x-4">
                        <a href="/admin/" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg">
                            ← Voltar ao Dashboard
                        </a>
                        <button onclick="toggleAddForm()" id="addBtn" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded-lg">
                            <i class="fas fa-plus mr-2"></i>Adicionar Palestra
                        </button>
                    </div>
                </div>

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

                <!-- Formulário Adicionar/Editar -->
                <div id="lectureForm" class="<?php echo $editing_lecture ? 'block' : 'hidden'; ?> bg-gray-900 rounded-lg p-6 mb-8">
                    <h2 class="text-xl font-bold mb-6">
                        <?php echo $editing_lecture ? 'Editar Palestra' : 'Adicionar Nova Palestra'; ?>
                    </h2>

                    <form method="POST" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <?php if ($editing_lecture): ?>
                        <input type="hidden" name="lecture_id" value="<?php echo $editing_lecture['id']; ?>">
                        <?php endif; ?>

                        <div>
                            <label for="title" class="block text-sm font-medium mb-2">Título *</label>
                            <input
                                type="text"
                                name="title"
                                id="title"
                                value="<?php echo $editing_lecture ? htmlspecialchars($editing_lecture['title']) : ''; ?>"
                                class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                                required
                            />
                        </div>

                        <div>
                            <label for="speaker" class="block text-sm font-medium mb-2">Palestrante *</label>
                            <input
                                type="text"
                                name="speaker"
                                id="speaker"
                                value="<?php echo $editing_lecture ? htmlspecialchars($editing_lecture['speaker']) : ''; ?>"
                                class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                                required
                            />
                        </div>

                        <div class="lg:col-span-2">
                            <label for="description" class="block text-sm font-medium mb-2">Descrição *</label>
                            <textarea
                                name="description"
                                id="description"
                                rows="4"
                                class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                                required
                            ><?php echo $editing_lecture ? htmlspecialchars($editing_lecture['description']) : ''; ?></textarea>
                        </div>

                        <div>
                            <label for="duration_minutes" class="block text-sm font-medium mb-2">Duração (minutos) *</label>
                            <input
                                type="number"
                                name="duration_minutes"
                                id="duration_minutes"
                                min="1"
                                value="<?php echo $editing_lecture ? $editing_lecture['duration_minutes'] : ''; ?>"
                                class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                                required
                            />
                        </div>

                        <div>
                            <label for="category" class="block text-sm font-medium mb-2">Categoria</label>
                            <input
                                type="text"
                                name="category"
                                id="category"
                                value="<?php echo $editing_lecture ? htmlspecialchars($editing_lecture['category']) : ''; ?>"
                                placeholder="ex: Tradução, Interpretação, Tecnologia"
                                class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                            />
                        </div>

                        <div class="lg:col-span-2">
                            <label for="embed_code" class="block text-sm font-medium mb-2">Código Embed *</label>
                            <textarea
                                name="embed_code"
                                id="embed_code"
                                rows="3"
                                placeholder="<iframe src='...'></iframe> ou <div style='...'></div>"
                                class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                                required
                            ><?php echo $editing_lecture ? htmlspecialchars($editing_lecture['embed_code']) : ''; ?></textarea>
                        </div>

                        <div>
                            <label for="thumbnail_url" class="block text-sm font-medium mb-2">URL da Thumbnail</label>
                            <input
                                type="url"
                                name="thumbnail_url"
                                id="thumbnail_url"
                                value="<?php echo $editing_lecture ? htmlspecialchars($editing_lecture['thumbnail_url']) : ''; ?>"
                                placeholder="https://images.unsplash.com/..."
                                class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label for="tags" class="block text-sm font-medium mb-2">Tags (separadas por vírgula)</label>
                            <input
                                type="text"
                                name="tags"
                                id="tags"
                                value="<?php echo $editing_lecture ? implode(', ', json_decode($editing_lecture['tags'] ?? '[]', true)) : ''; ?>"
                                placeholder="tradução, técnica, especializada"
                                class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                            />
                        </div>

                        <div class="lg:col-span-2">
                            <div class="flex space-x-6">
                                <label class="flex items-center">
                                    <input
                                        type="checkbox"
                                        name="is_featured"
                                        <?php echo ($editing_lecture && $editing_lecture['is_featured']) ? 'checked' : ''; ?>
                                        class="mr-2"
                                    />
                                    <span>Palestra em Destaque</span>
                                </label>

                                <label class="flex items-center">
                                    <input
                                        type="checkbox"
                                        name="is_live"
                                        <?php echo ($editing_lecture && $editing_lecture['is_live']) ? 'checked' : ''; ?>
                                        class="mr-2"
                                    />
                                    <span>Transmissão ao Vivo</span>
                                </label>
                            </div>
                        </div>

                        <div class="lg:col-span-2 flex justify-end space-x-4">
                            <button type="button" onclick="hideForm()" class="bg-gray-600 hover:bg-gray-700 px-6 py-3 rounded-lg">
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                name="<?php echo $editing_lecture ? 'edit_lecture' : 'add_lecture'; ?>"
                                class="bg-purple-600 hover:bg-purple-700 px-6 py-3 rounded-lg font-semibold"
                            >
                                <?php echo $editing_lecture ? 'Atualizar Palestra' : 'Adicionar Palestra'; ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Upload em Lote -->
                <div class="bg-gray-900 rounded-lg p-6 mb-8">
                    <h2 class="text-xl font-bold mb-4">Upload em Lote (CSV) - Atualiza Existentes</h2>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <div class="bg-gray-800 rounded-lg p-4 mb-4">
                                <h4 class="font-semibold mb-2">Formato do CSV:</h4>
                                <div class="text-sm text-gray-300 space-y-1">
                                    <p><strong>Delimitador:</strong> ponto e vírgula (;)</p>
                                    <p><strong>Colunas esperadas:</strong></p>
                                    <p>0. videoExternalId, 1. title, 2. speaker</p>
                                    <p>3. speaker_minibio, 4. description, 5. duration</p>
                                    <p>6. embed, 7. thumbnail_url, 8. category</p>
                                    <p>9. tags_csv, 10. is_featured, 11. is_live</p>
                                    <div class="mt-2 p-2 bg-blue-900 rounded">
                                        <p class="text-blue-300 text-xs"><strong>Novo:</strong> Agora atualiza palestras existentes automaticamente!</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                                <div>
                                    <label for="csv_file" class="block text-sm font-medium mb-2">Arquivo CSV</label>
                                    <input
                                        type="file"
                                        name="csv_file"
                                        id="csv_file"
                                        accept=".csv"
                                        class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                                        required
                                    />
                                </div>

                                <button
                                    type="submit"
                                    name="upload_lectures"
                                    class="w-full bg-blue-600 hover:bg-blue-700 py-3 rounded-lg font-semibold"
                                >
                                    <i class="fas fa-upload mr-2"></i>Upload em Lote (Inserir/Atualizar)
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Filtros e Busca -->
                <div class="bg-gray-900 rounded-lg p-6 mb-8">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="search" class="block text-sm font-medium mb-2">Buscar</label>
                            <input
                                type="text"
                                name="search"
                                id="search"
                                value="<?php echo htmlspecialchars($search); ?>"
                                placeholder="Título ou palestrante..."
                                class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label for="category" class="block text-sm font-medium mb-2">Categoria</label>
                            <select
                                name="category"
                                id="category"
                                class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                            >
                                <option value="">Todas as categorias</option>
                                <?php foreach($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="flex items-end">
                            <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 px-6 py-3 rounded-lg font-semibold">
                                <i class="fas fa-search mr-2"></i>Buscar
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Lista de Palestras -->
                <div class="bg-gray-900 rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-700">
                        <h2 class="text-xl font-bold">Palestras Cadastradas (<?php echo $total_lectures; ?>)</h2>
                    </div>

                    <?php if (!empty($lectures)): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-800">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">Palestra</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">Categoria</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">Duração</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-purple-400 uppercase">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <?php foreach ($lectures as $lecture): ?>
                                <tr class="hover:bg-gray-800">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="w-16 h-12 bg-gradient-to-br from-purple-600 to-purple-800 rounded flex-shrink-0 mr-4">
                                                <?php if ($lecture['thumbnail_url']): ?>
                                                <img 
                                                    src="<?php echo htmlspecialchars($lecture['thumbnail_url']); ?>" 
                                                    alt="<?php echo htmlspecialchars($lecture['title']); ?>"
                                                    class="w-full h-full object-cover rounded"
                                                />
                                                <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center">
                                                    <i class="fas fa-video text-white"></i>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="font-medium"><?php echo htmlspecialchars($lecture['title']); ?></div>
                                                <div class="text-sm text-purple-400"><?php echo htmlspecialchars($lecture['speaker']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="bg-purple-600 text-white px-2 py-1 rounded text-sm">
                                            <?php echo htmlspecialchars($lecture['category']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <?php echo $lecture['duration_minutes']; ?> min
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex space-x-2">
                                            <?php if ($lecture['is_featured']): ?>
                                            <span class="bg-yellow-600 text-yellow-100 px-2 py-1 rounded text-xs">Destaque</span>
                                            <?php endif; ?>
                                            <?php if ($lecture['is_live']): ?>
                                            <span class="bg-red-600 text-red-100 px-2 py-1 rounded text-xs">Ao Vivo</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex space-x-2">
                                            <a href="?edit=<?php echo $lecture['id']; ?>" 
                                               class="text-blue-400 hover:text-blue-300 text-sm">
                                                <i class="fas fa-edit mr-1"></i>Editar
                                            </a>
                                            <a href="/palestra.php?id=<?php echo $lecture['id']; ?>" 
                                               target="_blank"
                                               class="text-green-400 hover:text-green-300 text-sm">
                                                <i class="fas fa-external-link-alt mr-1"></i>Ver
                                            </a>
                                            <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja excluir esta palestra?')">
                                                <input type="hidden" name="lecture_id" value="<?php echo $lecture['id']; ?>">
                                                <button type="submit" name="delete_lecture" class="text-red-400 hover:text-red-300 text-sm">
                                                    <i class="fas fa-trash mr-1"></i>Excluir
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginação -->
                    <?php if ($total_pages > 1): ?>
                    <div class="px-6 py-4 border-t border-gray-700">
                        <div class="flex justify-center">
                            <nav class="flex space-x-2">
                                <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                   class="bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded-lg">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="<?php echo $i === $page ? 'bg-purple-600' : 'bg-gray-700 hover:bg-gray-600'; ?> px-4 py-2 rounded-lg">
                                    <?php echo $i; ?>
                                </a>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                   class="bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded-lg">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="text-center py-12">
                        <div class="text-4xl mb-4">🎥</div>
                        <p class="text-gray-400">Nenhuma palestra encontrada.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function toggleAddForm() {
    const form = document.getElementById('lectureForm');
    const btn = document.getElementById('addBtn');

    if (form.classList.contains('hidden')) {
        form.classList.remove('hidden');
        btn.innerHTML = '<i class="fas fa-times mr-2"></i>Cancelar';
    } else {
        hideForm();
    }
}

function hideForm() {
    const form = document.getElementById('lectureForm');
    const btn = document.getElementById('addBtn');

    form.classList.add('hidden');
    btn.innerHTML = '<i class="fas fa-plus mr-2"></i>Adicionar Palestra';

    // Limpar URL se estava editando
    if (window.location.search.includes('edit=')) {
        window.location.href = window.location.pathname;
    }
}
</script>

<?php include '../includes/footer.php'; ?>