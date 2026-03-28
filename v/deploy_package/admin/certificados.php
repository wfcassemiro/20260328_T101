<?php
session_start();
require_once '../config/database.php';

if (file_exists('../config/config.php')) {
    require_once '../config/config.php';
} else {
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
}

if (!isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_manual'])) {
    $user_id = $_POST['user_id'] ?? '';
    $lecture_id = $_POST['lecture_id'] ?? '';
    
    if (!empty($user_id) && !empty($lecture_id)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM certificates WHERE user_id = ? AND lecture_id = ?");
            $stmt->execute([$user_id, $lecture_id]);
            $existing = $stmt->fetch();
            
            if (!$existing) {
                $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                $stmt = $pdo->prepare("SELECT title, speaker, duration_minutes FROM lectures WHERE id = ?");
                $stmt->execute([$lecture_id]);
                $lecture = $stmt->fetch();
                
                if ($user && $lecture) {
                    $duration_hours = $lecture['duration_minutes'] / 60;
                    if ($duration_hours <= 0.5) {
                        $duration_hours = 0.5;
                    } elseif ($duration_hours <= 1.0) {
                        $duration_hours = 1.0;
                    } elseif ($duration_hours <= 1.5) {
                        $duration_hours = 1.5;
                    } else {
                        $duration_hours = ceil($duration_hours * 2) / 2;
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO certificates (id, user_id, lecture_id, user_name, lecture_title, speaker_name, duration_hours) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $certificate_id = generateUUID();
                    $stmt->execute([
                        $certificate_id,
                        $user_id,
                        $lecture_id,
                        $user['name'],
                        $lecture['title'],
                        $lecture['speaker'],
                        $duration_hours
                    ]);
                    
                    $message = "Certificado gerado com sucesso!";
                    $message_type = "success";
                } else {
                    $message = "Usuário ou palestra não encontrados.";
                    $message_type = "error";
                }
            } else {
                $message = "Certificado já existe para este usuário e palestra.";
                $message_type = "warning";
            }
        } catch(PDOException $e) {
            $message = "Erro ao gerar certificado: " . $e->getMessage();
            $message_type = "error";
        }
    } else {
        $message = "Por favor, selecione um usuário e uma palestra.";
        $message_type = "error";
    }
}

if (isset($_GET['delete'])) {
    $cert_id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM certificates WHERE id = ?");
        $stmt->execute([$cert_id]);
        $message = "Certificado excluído com sucesso!";
        $message_type = "success";
    } catch(PDOException $e) {
        $message = "Erro ao excluir certificado.";
        $message_type = "error";
    }
}

$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';
$where_clause = '';
$params = [];

if (!empty($search)) {
    $where_clause = " WHERE c.user_name LIKE ? OR c.lecture_title LIKE ? OR c.speaker_name LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM certificates c" . $where_clause);
    $count_stmt->execute($params);
    $total_certificates = $count_stmt->fetchColumn();
    $total_pages = ceil($total_certificates / $limit);
    
    $stmt = $pdo->prepare("
        SELECT c.*, u.email as user_email 
        FROM certificates c 
        LEFT JOIN users u ON c.user_id = u.id
        $where_clause
        ORDER BY c.issued_at DESC 
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $certificates = $stmt->fetchAll();
    
    $users_stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE role IN ('subscriber', 'admin') ORDER BY name");
    $users_stmt->execute();
    $users = $users_stmt->fetchAll();
    
    $lectures_stmt = $pdo->prepare("SELECT id, title, speaker FROM lectures ORDER BY title");
    $lectures_stmt->execute();
    $lectures = $lectures_stmt->fetchAll();
    
} catch(PDOException $e) {
    $message = "Erro ao carregar dados.";
    $message_type = "error";
    $certificates = [];
    $users = [];
    $lectures = [];
}

$page_title = "Gerenciar Certificados";
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Translators101 Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    purple: {
                        600: '#7c3aed',
                        700: '#6d28d9'
                    }
                }
            }
        }
    }
    </script>
    <style>
    body { background: #0f0f0f; color: #ffff; }
    </style>
</head>
<body class="bg-gray-900 text-white">
    <div class="flex min-h-screen">
        <?php include 'admin_sidebar.php'; ?>
        <main class="flex-1 p-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold mb-2"><?php echo $page_title; ?></h1>
                <p class="text-gray-400">Gerencie certificados emitidos e crie novos certificados</p>
            </div>
            
            <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-600 bg-opacity-20 border border-green-600 text-green-400' : ($message_type === 'warning' ? 'bg-yellow-600 bg-opacity-20 border border-yellow-600 text-yellow-400' : 'bg-red-600 bg-opacity-20 border border-red-600 text-red-400'); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <div class="bg-gray-800 rounded-lg p-6 mb-8">
                <h2 class="text-xl font-semibold mb-4">
                    <i class="fas fa-plus mr-2"></i>Gerar Certificado Manual
                </h2>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Usuário</label>
                        <select name="user_id" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white">
                            <option value="">Selecione um usuário</option>
                            <?php foreach($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['name'] . ' (' . $user['email'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Palestra</label>
                        <select name="lecture_id" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white">
                            <option value="">Selecione uma palestra</option>
                            <?php foreach($lectures as $lecture): ?>
                            <option value="<?php echo $lecture['id']; ?>">
                                <?php echo htmlspecialchars($lecture['title'] . ' - ' . $lecture['speaker']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" name="generate_manual" class="w-full bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg font-semibold transition-colors">
                            <i class="fas fa-certificate mr-2"></i>Gerar Certificado
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-4">Buscar Certificados</h3>
                    <form method="GET" class="flex gap-2">
                        <input 
                            type="text" 
                            name="search" 
                            value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Nome, palestra ou palestrante..."
                            class="flex-1 bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white"
                        >
                        <button type="submit" class="bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg transition-colors">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if ($search): ?>
                        <a href="certificados.php" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg transition-colors">
                            <i class="fas fa-times"></i>
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-4">Estatísticas</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-400"><?php echo $total_certificates; ?></div>
                            <div class="text-sm text-gray-400">Total de Certificados</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-400">
                                <?php 
                                try {
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM certificates WHERE DATE(issued_at) = CURDATE()");
                                    $stmt->execute();
                                    echo $stmt->fetchColumn();
                                } catch(PDOException $e) {
                                    echo "0";
                                }
                                ?>
                            </div>
                            <div class="text-sm text-gray-400">Hoje</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-800 rounded-lg p-6">
                <h2 class="text-xl font-semibold mb-4">
                    <i class="fas fa-list mr-2"></i>Certificados Emitidos
                    <?php if ($search): ?>
                    <span class="text-sm text-gray-400">- Resultados para "<?php echo htmlspecialchars($search); ?>"</span>
                    <?php endif; ?>
                </h2>
                <?php if (empty($certificates)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-certificate text-4xl text-gray-600 mb-4"></i>
                    <p class="text-gray-400">
                        <?php echo $search ? 'Nenhum certificado encontrado.' : 'Nenhum certificado emitido ainda.'; ?>
                    </p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-700">
                                <th class="text-left py-3 px-2">Usuário</th>
                                <th class="text-left py-3 px-2">Palestra</th>
                                <th class="text-left py-3 px-2">Palestrante</th>
                                <th class="text-left py-3 px-2">Duração</th>
                                <th class="text-left py-3 px-2">Emitido</th>
                                <th class="text-left py-3 px-2">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($certificates as $cert): ?>
                            <tr class="border-b border-gray-700 hover:bg-gray-700">
                                <td class="py-3 px-2">
                                    <div>
                                        <div class="font-medium"><?php echo htmlspecialchars($cert['user_name']); ?></div>
                                        <div class="text-xs text-gray-400"><?php echo htmlspecialchars($cert['user_email'] ?? ''); ?></div>
                                    </div>
                                </td>
                                <td class="py-3 px-2">
                                    <div class="max-w-xs truncate" title="<?php echo htmlspecialchars($cert['lecture_title']); ?>">
                                        <?php echo htmlspecialchars($cert['lecture_title']); ?>
                                    </div>
                                </td>
                                <td class="py-3 px-2"><?php echo htmlspecialchars($cert['speaker_name']); ?></td>
                                <td class="py-3 px-2"><?php echo $cert['duration_hours']; ?>h</td>
                                <td class="py-3 px-2">
                                    <div><?php echo date('d/m/Y', strtotime($cert['issued_at'])); ?></div>
                                    <div class="text-xs text-gray-400"><?php echo date('H:i', strtotime($cert['issued_at'])); ?></div>
                                </td>
                                <td class="py-3 px-2">
                                    <div class="flex gap-1">
                                        <a href="../view_certificate_files.php?id=<?php echo $cert['id']; ?>" 
                                            target="_blank"
                                            class="bg-blue-600 hover:bg-blue-700 px-2 py-1 rounded text-xs transition-colors"
                                            title="Visualizar Certificado">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="../download_certificate_files.php?id=<?php echo $cert['id']; ?>" 
                                            class="bg-green-600 hover:bg-green-700 px-2 py-1 rounded text-xs transition-colors"
                                            title="Baixar Certificado">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <a href="?delete=<?php echo $cert['id']; ?>" 
                                            onclick="return confirm('Tem certeza que deseja excluir este certificado?')"
                                            class="bg-red-600 hover:bg-red-700 px-2 py-1 rounded text-xs transition-colors"
                                            title="Excluir Certificado">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="mt-6 flex justify-center">
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                            class="bg-gray-700 hover:bg-gray-600 px-3 py-2 rounded text-sm transition-colors">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php endif; ?>
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                            class="px-3 py-2 rounded text-sm transition-colors <?php echo $i === (int)$page ? 'bg-purple-600 text-white' : 'bg-gray-700 hover:bg-gray-600'; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                            class="bg-gray-700 hover:bg-gray-600 px-3 py-2 rounded text-sm transition-colors">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>