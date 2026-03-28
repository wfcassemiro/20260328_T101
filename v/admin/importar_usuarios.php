<?php
session_start();
require_once '../config/database.php';
require_once '../config/hotmart.php';

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
$import_log = [];

// Processar adição individual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_individual'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $status = $_POST['status'] ?? 'ACTIVE';
    $role = $_POST['role'] ?? 'subscriber';
    $send_email = isset($_POST['send_email']);

    if (!empty($name) && !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $existingUser = $stmt->fetch();

            if (!$existingUser) {
                $userId = generateUUID();
                $reset_token = bin2hex(random_bytes(32));
                
                $stmt = $pdo->prepare("
                    INSERT INTO users (id, name, email, password_hash, role, hotmart_status, hotmart_synced_at, 
                                     password_reset_token, password_reset_expires, first_login, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, DATE_ADD(NOW(), INTERVAL 7 DAY), TRUE, NOW())
                ");
                $stmt->execute([
                    $userId,
                    $name,
                    $email,
                    password_hash('temp_' . $reset_token, PASSWORD_DEFAULT),
                    $role,
                    $status,
                    $reset_token
                ]);
                
                // Enviar email se solicitado
                if ($send_email) {
                    require_once '../config/email.php';
                    $email_sent = sendPasswordSetupEmail($email, $name, $reset_token);
                    $email_status = $email_sent ? " Email enviado!" : " Falha no email.";
                } else {
                    $email_status = "";
                }
                
                $message = "Usuário '{$name}' adicionado com sucesso!{$email_status}";
                $message_type = 'success';
            } else {
                $message = "Usuário com email '{$email}' já existe.";
                $message_type = 'warning';
            }
        } catch (PDOException $e) {
            $message = "Erro ao adicionar usuário: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = "Nome e email válido são obrigatórios.";
        $message_type = 'error';
    }
}

// Processar upload de arquivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file']) && isset($_FILES['csv_file'])) {
    $send_emails = isset($_POST['send_emails']);
    $update_existing = isset($_POST['update_existing']);
    
    try {
        $file = $_FILES['csv_file'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($extension === 'csv') {
                $uploaded_file = 'uploads/' . uniqid() . '.' . $extension;
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0755, true);
                }
                if (move_uploaded_file($file['tmp_name'], $uploaded_file)) {
                    $result = processImportFile($uploaded_file, $pdo, $import_log, $send_emails, $update_existing);
                    $message = "Importação concluída! Usuários adicionados: {$result['success']}, Já existiam: {$result['existing']}, Atualizados: {$result['updated']}, Erros: {$result['errors']}";
                    $message_type = $result['errors'] > 0 ? 'warning' : 'success';
                    unlink($uploaded_file);
                } else {
                    $message = "Erro no upload do arquivo.";
                    $message_type = 'error';
                }
            } else {
                $message = "Apenas arquivos CSV são permitidos.";
                $message_type = 'error';
            }
        } else {
            $message = "Erro no upload: " . $file['error'];
            $message_type = 'error';
        }
    } catch (Exception $e) {
        $message = "Erro na importação: " . $e->getMessage();
        $message_type = 'error';
    }
}

function processImportFile($file, $pdo, &$import_log, $send_emails = false, $update_existing = false) {
    return processHotmartCSV($file, $pdo, $import_log, $send_emails, $update_existing);
}

function processHotmartCSV($file, $pdo, &$import_log, $send_emails = false, $update_existing = false) {
    $success = 0;
    $errors = 0;
    $existing = 0;
    $updated = 0;

    if (($handle = fopen($file, "r")) !== FALSE) {
        $header = fgetcsv($handle, 1000, ";");
        $i = 0;
        $columnMap = [
            'nome' => 0,
            'email' => 1,
            'papel' => 2,
            'acesso' => 3,
            'turma' => 4,
            'categoria' => 5,
            'primeiro_acesso' => 6,
            'ultimo_acesso' => 7,
            'progresso' => 8,
            'engajamento' => 9,
            'data_compra' => 10,
            'num_acessos' => 11
        ];
        
        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
            // Conversão para UTF-8 (linha a linha)
            foreach ($data as $k => $v) {
                $data[$k] = mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1,UTF-8');
            }
            
            $linha = $i + 2;
            $name = trim($data[$columnMap['nome']] ?? '');
            $email = trim($data[$columnMap['email']] ?? '');
            $acesso = trim($data[$columnMap['acesso']] ?? 'Ativo');
            $categoria = trim($data[$columnMap['categoria']] ?? 'Comprador');
            $data_compra = trim($data[$columnMap['data_compra']] ?? '');

            $status = 'ACTIVE';
            $role = 'subscriber';
            if (strtolower($acesso) === 'bloqueado') {
                $status = 'CANCELED';
                $role = 'free';
            }

            if (!empty($name) && !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $result = addHotmartUser($name, $email, $status, $role, $data_compra, $pdo, $motivo_erro, $send_emails, $update_existing);
                if ($result === 'success') {
                    $success++;
                    $import_log[] = [$linha, $name, $email, 'Adicionado', ''];
                } elseif ($result === 'updated') {
                    $updated++;
                    $import_log[] = [$linha, $name, $email, 'Atualizado', ''];
                } elseif ($result === 'existing') {
                    $existing++;
                    $import_log[] = [$linha, $name, $email, 'Já existia', ''];
                } else {
                    $errors++;
                    $import_log[] = [$linha, $name, $email, 'Erro', $motivo_erro ?: 'Erro ao inserir no banco'];
                }
            } else {
                $errors++;
                $import_log[] = [$linha, $name, $email, 'Erro', 'Nome ou e-mail inválido'];
            }
            $i++;
        }
        fclose($handle);
    }
    return ['success' => $success, 'errors' => $errors, 'existing' => $existing, 'updated' => $updated];
}

function addHotmartUser($name, $email, $status, $role, $data_compra, $pdo, &$motivo_erro = null, $send_email = false, $update_existing = false) {
    if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $motivo_erro = 'Nome ou e-mail inválido';
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, hotmart_synced_at FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existingUser = $stmt->fetch();
        
        if ($existingUser) {
            if ($update_existing) {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET name = ?, role = ?, hotmart_status = ?, hotmart_synced_at = NOW(), updated_at = NOW()
                    WHERE email = ?
                ");
                $stmt->execute([$name, $role, $status, $email]);
                return 'updated';
            } else {
                return 'existing';
            }
        }
        
        $purchase_date = null;
        if (!empty($data_compra)) {
            $purchase_date = date('Y-m-d H:i:s', strtotime($data_compra));
        }
        
        $reset_token = bin2hex(random_bytes(32));
        $userId = generateUUID();
        
        $stmt = $pdo->prepare("
            INSERT INTO users (
                id, name, email, password_hash, role, hotmart_status, 
                hotmart_synced_at, password_reset_token, password_reset_expires, 
                first_login, created_at, updated_at
            ) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, DATE_ADD(NOW(), INTERVAL 7 DAY), TRUE, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $userId,
            $name,
            $email,
            password_hash('temp_' . $reset_token, PASSWORD_DEFAULT),
            $role,
            $status,
            $reset_token,
            $purchase_date ?: date('Y-m-d H:i:s')
        ]);
        
        // Enviar email se solicitado
        if ($result && $send_email) {
            require_once '../config/email.php';
            sendPasswordSetupEmail($email, $name, $reset_token);
        }
        
        if (!$result) {
            $motivo_erro = 'Falha ao inserir no banco';
        }
        
        return $result ? 'success' : false;
        
    } catch (PDOException $e) {
        $motivo_erro = $e->getMessage();
        error_log("Erro ao adicionar usuário {$email}: " . $e->getMessage());
        return false;
    }
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN hotmart_synced_at IS NOT NULL THEN 1 ELSE 0 END) as from_hotmart,
            SUM(CASE WHEN role = 'subscriber' THEN 1 ELSE 0 END) as subscribers
        FROM users
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
} catch (Exception $e) {
    $stats = ['total' => 0, 'from_hotmart' => 0, 'subscribers' => 0];
}

$page_title = "Importar Usuários";
$active_page = 'importar_usuarios';

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-upload"></i> <?php echo $page_title; ?></h1>
            <p>Importe usuários do Hotmart via CSV ou adicione individualmente</p>
            <a href="index.php" class="cta-btn">
                <i class="fas fa-arrow-left"></i> Voltar ao Admin
            </a>
        </div>
    </div>
            
    <?php if ($message): ?>
        <div class="alert-<?php echo $message_type === 'success' ? 'success' : ($message_type === 'warning' ? 'warning' : 'error'); ?>">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'times-circle'); ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

            <!-- Log detalhado da importação -->
            <?php if (!empty($import_log)): ?>
                <div class="bg-gray-800 rounded-lg p-6 mb-8 overflow-x-auto">
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-list mr-2"></i>Log detalhado da importação
                    </h3>
                    <table class="min-w-full text-sm text-left">
                        <thead>
                            <tr class="bg-gray-700">
                                <th class="px-3 py-2">Linha</th>
                                <th class="px-3 py-2">Nome</th>
                                <th class="px-3 py-2">E-mail</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($import_log as $log): ?>
                                <tr class="border-b border-gray-700 <?php echo $log[3]==='Erro'?'text-red-400':($log[3]==='Já existia'?'text-yellow-400':($log[3]==='Atualizado'?'text-blue-400':'text-green-400')); ?>">
                                    <td class="px-3 py-1"><?php echo $log[0]; ?></td>
                                    <td class="px-3 py-1"><?php echo htmlspecialchars($log[1]); ?></td>
                                    <td class="px-3 py-1"><?php echo htmlspecialchars($log[2]); ?></td>
                                    <td class="px-3 py-1"><?php echo $log[3]; ?></td>
                                    <td class="px-3 py-1"><?php echo htmlspecialchars($log[4]); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

    <!-- Estatísticas -->
    <div class="stats-grid">
        <div class="video-card stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total de Usuários</div>
            </div>
        </div>
        
        <div class="video-card stat-card">
            <div class="stat-icon">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['from_hotmart']; ?></div>
                <div class="stat-label">Via Hotmart</div>
            </div>
        </div>
        
        <div class="video-card stat-card">
            <div class="stat-icon">
                <i class="fas fa-star"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['subscribers']; ?></div>
                <div class="stat-label">Assinantes</div>
            </div>
        </div>
    </div>
            
            <!-- Importação CSV do Hotmart -->
            <div class="bg-gray-800 rounded-lg p-6 mb-8">
                <h2 class="text-xl font-semibold mb-4">
                    <i class="fas fa-upload mr-2"></i>Importação CSV do Hotmart
                </h2>
                
                <div class="mb-4">
                    <h3 class="text-lg font-medium mb-2">📋 Formato esperado (CSV do Hotmart):</h3>
                    <div class="bg-gray-700 p-4 rounded-lg text-sm">
                        <strong>Colunas esperadas (separadas por ponto e vírgula):</strong><br>
                        Nome;E-mail;Papel;Acesso;Turma;Categoria;Primeiro acesso;Último acesso;Progresso;Engajamento;Data da compra;Nº de acessos<br><br>
                        <strong>✅ O sistema irá:</strong>
                        <ul class="list-disc list-inside text-gray-400 mt-2">
                            <li>Processar automaticamente o formato do Hotmart</li>
                            <li>Mapear "Acesso: Ativo" → role: subscriber</li>
                            <li>Mapear "Acesso: Bloqueado" → role: free</li>
                            <li>Criar tokens para definição de senha</li>
                            <li>Evitar duplicatas por email (opcional: atualizar existentes)</li>
                        </ul>
                    </div>
                </div>
                
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Arquivo CSV do Hotmart</label>
                        <input type="file" name="csv_file" accept=".csv" required 
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white">
                    </div>
                    
                    <!-- Opções de importação -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex items-center">
                            <input type="checkbox" name="send_emails" id="send_emails" class="mr-2">
                            <label for="send_emails" class="text-sm">Enviar emails de definição de senha automaticamente</label>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" name="update_existing" id="update_existing" class="mr-2">
                            <label for="update_existing" class="text-sm">Atualizar usuários existentes</label>
                        </div>
                    </div>
                    
                    <button type="submit" name="upload_file" class="bg-purple-600 hover:bg-purple-700 px-6 py-3 rounded-lg font-semibold transition-colors">
                        <i class="fas fa-upload mr-2"></i>Importar CSV do Hotmart
                    </button>
                </form>
            </div>
            
            <!-- Adicionar Usuário Individual -->
            <div class="bg-gray-800 rounded-lg p-6 mb-8">
                <h2 class="text-xl font-semibold mb-4">
                    <i class="fas fa-user-plus mr-2"></i>Adicionar Usuário Individual
                </h2>
                
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Nome Completo</label>
                        <input type="text" name="name" required 
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white"
                               placeholder="Ex: João Silva">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Email</label>
                        <input type="email" name="email" required 
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white"
                               placeholder="Ex: joao@email.com">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Status Hotmart</label>
                        <select name="status" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white">
                            <option value="ACTIVE">ACTIVE (Ativo)</option>
                            <option value="APPROVED">APPROVED (Aprovado)</option>
                            <option value="COMPLETE">COMPLETE (Completo)</option>
                            <option value="CANCELED">CANCELED (Cancelado)</option>
                            <option value="EXPIRED">EXPIRED (Expirado)</option>
                            <option value="REFUNDED">REFUNDED (Reembolsado)</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Role no Sistema</label>
                        <select name="role" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-white">
                            <option value="subscriber">Subscriber (Assinante)</option>
                            <option value="free">Free (Gratuito)</option>
                            <option value="admin">Admin (Administrador)</option>
                        </select>
                    </div>
                    
                    <div class="md:col-span-2">
                        <div class="flex items-center mb-4">
                            <input type="checkbox" name="send_email" id="send_email_individual" class="mr-2">
                            <label for="send_email_individual" class="text-sm">Enviar email de definição de senha</label>
                        </div>
                        
                        <button type="submit" name="add_individual" class="bg-green-600 hover:bg-green-700 px-6 py-3 rounded-lg font-semibold transition-colors">
                            <i class="fas fa-user-plus mr-2"></i>Adicionar Usuário
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Instruções de Uso -->
            <div class="bg-gray-800 rounded-lg p-6">
                <h2 class="text-xl font-semibold mb-4">
                    <i class="fas fa-info-circle mr-2"></i>Instruções de Uso
                </h2>
                
                <div class="text-gray-400 space-y-2">
                    <p><strong>1.</strong> Exporte os usuários do Hotmart em formato CSV</p>
                    <p><strong>2.</strong> Faça upload do arquivo usando o formulário acima</p>
                    <p><strong>3.</strong> O sistema processará automaticamente o formato do Hotmart</p>
                    <p><strong>4.</strong> Usuários receberão email para definir senha (se habilitado)</p>
                    <p><strong>5.</strong> Usuários existentes podem ser atualizados (se habilitado)</p>
                    <p><strong>6.</strong> Use a adição individual para casos específicos</p>
                </div>
                
                <div class="mt-4 p-3 bg-blue-600 bg-opacity-20 border border-blue-600 border-opacity-30 rounded">
                    <p class="text-blue-300 text-sm">
                        <i class="fas fa-lightbulb mr-2"></i>
                        <strong>Dica:</strong> Para grandes volumes, desabilite o envio automático de emails e use o sistema de emails em massa posteriormente.
                    </p>
                </div>
            </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    width: 3rem;
    height: 3rem;
    background: var(--brand-purple);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: white;
}

.stat-label {
    color: var(--text-light);
    font-size: 0.875rem;
}
</style>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>