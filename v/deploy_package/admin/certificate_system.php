<?php
session_start();
require_once '../config/database.php';

// Tentar incluir config.php, se não conseguir, definir função generateUUID() diretamente
if (file_exists('../config/config.php')) {
    require_once '../config/config.php';
} else {
    // Definir função generateUUID() se não existir
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

// Verificar se é admin
if (!isAdmin()) {
    header('Location: /login.php');
    exit;
}

// Defina a página ativa para o menu lateral
$active_page = 'certificate_system';

$page_title = 'Admin - Sistema de Certificados';
$success_message = '';
$error_message = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['regenerate_certificate'])) {
        $certificate_id = $_POST['certificate_id'];
        
        try {
            // Buscar dados do certificado
            $stmt = $pdo->prepare("SELECT * FROM certificates WHERE id = ?");
            $stmt->execute([$certificate_id]);
            $cert = $stmt->fetch();
            
            if ($cert) {
                // Atualizar data de emissão
                $stmt = $pdo->prepare("UPDATE certificates SET issued_at = NOW() WHERE id = ?");
                $stmt->execute([$certificate_id]);
                
                $success_message = "Certificado regenerado com nova data de emissão.";
            } else {
                $error_message = "Certificado não encontrado.";
            }
        } catch (Exception $e) {
            $error_message = "Erro ao regenerar certificado: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['delete_certificate'])) {
        $certificate_id = $_POST['certificate_id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM certificates WHERE id = ?");
            $stmt->execute([$certificate_id]);
            
            $success_message = "Certificado excluído com sucesso.";
        } catch (Exception $e) {
            $error_message = "Erro ao excluir certificado: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['bulk_generate'])) {
        $lecture_id = $_POST['lecture_id'];
        $user_ids = $_POST['user_ids'] ?? [];
        
        if (!empty($user_ids) && !empty($lecture_id)) {
            try {
                // Buscar dados da palestra
                $stmt = $pdo->prepare("SELECT * FROM lectures WHERE id = ?");
                $stmt->execute([$lecture_id]);
                $lecture = $stmt->fetch();
                
                if ($lecture) {
                    $count = 0;
                    foreach ($user_ids as $user_id) {
                        // Buscar dados do usuário
                        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $user = $stmt->fetch();
                        
                        if ($user) {
                            // Verificar se já existe certificado
                            $stmt = $pdo->prepare("SELECT id FROM certificates WHERE user_id = ? AND lecture_id = ?");
                            $stmt->execute([$user_id, $lecture_id]);
                            
                            if (!$stmt->fetch()) {
                                // Calcular duração em horas
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
                                
                                // Criar certificado
                                $stmt = $pdo->prepare("INSERT INTO certificates (id, user_id, lecture_id, user_name, lecture_title, speaker_name, duration_hours) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                $stmt->execute([
                                    generateUUID(),
                                    $user_id,
                                    $lecture_id,
                                    $user['name'],
                                    $lecture['title'],
                                    $lecture['speaker'],
                                    $duration_hours
                                ]);
                                
                                $count++;
                            }
                        }
                    }
                    
                    $success_message = "$count certificados gerados em lote.";
                } else {
                    $error_message = "Palestra não encontrada.";
                }
            } catch (Exception $e) {
                $error_message = "Erro na geração em lote: " . $e->getMessage();
            }
        } else {
            $error_message = "Selecione uma palestra e pelo menos um usuário.";
        }
    }
}

// Buscar dados para o sistema
try {
    // Estatísticas
    $stmt = $pdo->query("SELECT COUNT(*) FROM certificates");
    $total_certificates = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM certificates WHERE DATE(issued_at) = CURDATE()");
    $today_certificates = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT AVG(duration_hours) FROM certificates");
    $avg_duration = round($stmt->fetchColumn(), 1);
    
    // Palestras para geração em lote
    $stmt = $pdo->query("SELECT * FROM lectures ORDER BY title");
    $lectures = $stmt->fetchAll();
    
    // Usuários para geração em lote
    $stmt = $pdo->query("SELECT * FROM users WHERE role IN ('subscriber', 'admin') ORDER BY name");
    $users = $stmt->fetchAll();
    
    // Certificados recentes
    $stmt = $pdo->query("SELECT c.*, u.email 
        FROM certificates c 
        LEFT JOIN users u ON c.user_id = u.id 
        ORDER BY c.issued_at DESC 
        LIMIT 20");
    $recent_certificates = $stmt->fetchAll();
    
} catch (Exception $e) {
    $lectures = [];
    $users = [];
    $recent_certificates = [];
    $total_certificates = $today_certificates = 0;
    $avg_duration = 0;
}

include '../includes/header.php';
?>

<div class="flex min-h-screen bg-gray-100">
    <!-- Menu lateral -->
    <?php include 'admin_sidebar.php'; ?>

    <!-- Conteúdo principal -->
    <main class="flex-1 p-8">
        <div class="max-w-7xl mx-auto">
            <!-- Header -->
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Sistema de Certificados</h1>
                <a href="/admin/" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">
                    ← Voltar ao Dashboard
                </a>
            </div>
            
            <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Estatísticas -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg p-6 shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Total de Certificados</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $total_certificates; ?></p>
                        </div>
                        <i class="fas fa-certificate text-yellow-500 text-2xl"></i>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Emitidos Hoje</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $today_certificates; ?></p>
                        </div>
                        <i class="fas fa-calendar-day text-blue-500 text-2xl"></i>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm">Duração Média</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $avg_duration; ?>h</p>
                        </div>
                        <i class="fas fa-clock text-purple-500 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <!-- Geração em Lote -->
            <div class="bg-white rounded-lg p-6 mb-8 shadow">
                <h2 class="text-xl font-bold mb-6 text-gray-900">Geração de Certificados em Lote</h2>
                
                <form method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <label for="lecture_id" class="block text-sm font-medium text-gray-700 mb-2">Selecionar Palestra</label>
                            <select name="lecture_id" id="lecture_id" class="w-full p-3 border border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none" required>
                                <option value="">Escolha uma palestra...</option>
                                <?php foreach ($lectures as $lecture): ?>
                                <option value="<?php echo $lecture['id']; ?>">
                                    <?php echo htmlspecialchars($lecture['title'] . ' - ' . $lecture['speaker']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Selecionar Usuários</label>
                            <div class="border border-gray-300 rounded-lg p-3 max-h-48 overflow-y-auto bg-gray-50">
                                <div class="mb-3">
                                    <label class="flex items-center">
                                        <input type="checkbox" id="select-all" class="mr-2">
                                        <span class="text-sm font-medium text-gray-700">Selecionar Todos</span>
                                    </label>
                                </div>
                                <?php foreach ($users as $user): ?>
                                <div class="mb-2">
                                    <label class="flex items-center user-checkbox">
                                        <input type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>" class="mr-2">
                                        <span class="text-sm text-gray-700">
                                            <?php echo htmlspecialchars($user['name'] . ' (' . $user['email'] . ')'); ?>
                                            <span class="text-blue-600 text-xs">[<?php echo $user['role']; ?>]</span>
                                        </span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" name="bulk_generate" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold">
                            <i class="fas fa-magic mr-2"></i>Gerar Certificados em Lote
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Certificados Recentes -->
            <div class="bg-white rounded-lg overflow-hidden shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-900">Certificados Recentes</h2>
                </div>
                
                <?php if (!empty($recent_certificates)): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuário</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Palestra</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duração</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recent_certificates as $cert): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($cert['user_name']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($cert['email'] ?? 'Email não encontrado'); ?></div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($cert['lecture_title']); ?></div>
                                    <div class="text-sm text-blue-600"><?php echo htmlspecialchars($cert['speaker_name']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-sm font-semibold">
                                        <?php echo $cert['duration_hours']; ?>h
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('d/m/Y H:i', strtotime($cert['issued_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex space-x-2">
                                        <a href="/generate_certificate.php?id=<?php echo $cert['id']; ?>" 
                                           target="_blank"
                                           class="text-blue-600 hover:text-blue-900 text-sm">
                                            <i class="fas fa-eye mr-1"></i>Ver
                                        </a>
                                        
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="certificate_id" value="<?php echo $cert['id']; ?>">
                                            <button type="submit" name="regenerate_certificate" class="text-green-600 hover:text-green-900 text-sm">
                                                <i class="fas fa-redo mr-1"></i>Regenerar
                                            </button>
                                        </form>
                                        
                                        <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja excluir este certificado?')">
                                            <input type="hidden" name="certificate_id" value="<?php echo $cert['id']; ?>">
                                            <button type="submit" name="delete_certificate" class="text-red-600 hover:text-red-900 text-sm">
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
                <?php else: ?>
                <div class="text-center py-12">
                    <div class="text-4xl mb-4">📜</div>
                    <p class="text-gray-500">Nenhum certificado encontrado.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
// Controle do "Selecionar Todos"
document.getElementById('select-all').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.user-checkbox input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

// Se algum checkbox individual for desmarcado, desmarcar o "Selecionar Todos"
document.querySelectorAll('.user-checkbox input[type="checkbox"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        if (!this.checked) {
            document.getElementById('select-all').checked = false;
        } else {
            // Verificar se todos estão marcados para marcar o "Selecionar Todos"
            const allCheckboxes = document.querySelectorAll('.user-checkbox input[type="checkbox"]');
            const allChecked = Array.from(allCheckboxes).every(cb => cb.checked);
            document.getElementById('select-all').checked = allChecked;
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>