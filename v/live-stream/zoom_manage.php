<?php
/**
 * Painel de Gerenciamento de Reuniões do Zoom - VERSÃO INTEGRADA AO LAYOUT
 */

session_start();

// Dependências do site e do Zoom
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/zoom_functions.php'; // ATENÇÃO: Verifique se o nome do arquivo está correto

date_default_timezone_set('America/Sao_Paulo');

/**
 * Funções de verificação de usuário (devem ser consistentes com o site)
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    if (!isLoggedIn()) return false;
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        return $user && $user['role'] === 'admin';
    } catch (PDOException $e) {
        error_log("Erro ao verificar role do usuário: " . $e->getMessage());
        return false;
    }
}

// Verificar acesso de administrador
if (!isAdmin()) {
    header('Location: /auth/login.php'); // Redireciona se não for admin
    exit;
}

// Garante que a tabela do Zoom exista
createZoomMeetingsTable();

$message = '';
$messageType = '';

// Processar ações do formulário (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'test_auth':
            $result = testZoomAuth();
            if ($result['success']) {
                $message = '✓ Autenticação funcionando! Usuário: ' . ($result['user']['email'] ?? 'N/A');
                $messageType = 'success';
            } else {
                $message = '✗ Erro na autenticação: ' . $result['message'];
                $messageType = 'error';
            }
            break;
            
        case 'create':
            $result = createZoomMeeting($_POST['topic'], $_POST['start_time'], $_POST['duration'], $_POST['agenda'] ?? '');
            if ($result['success']) {
                $message = '✓ Reunião criada com sucesso!';
                $messageType = 'success';
            } else {
                $message = '✗ Erro ao criar reunião: ' . $result['error'];
                $messageType = 'error';
            }
            break;
            
        case 'add_existing':
            $result = addExistingMeeting($_POST['meeting_id_or_url']);
            if ($result['success']) {
                $message = '✓ Reunião adicionada com sucesso!';
                $messageType = 'success';
            } else {
                $message = '✗ Erro ao adicionar reunião: ' . $result['error'];
                $messageType = 'error';
            }
            break;
            
        case 'delete':
            $result = deleteZoomMeeting($_POST['meeting_id']);
            if ($result['success']) {
                $message = '✓ Reunião deletada com sucesso!';
                $messageType = 'success';
            } else {
                $message = '✗ Erro ao deletar reunião: ' . $result['error'];
                $messageType = 'error';
            }
            break;
            
        case 'sync':
            $result = syncZoomMeetings();
            if ($result['success']) {
                $message = "✓ {$result['synced']} reuniões sincronizadas!";
                $messageType = 'success';
            } else {
                $message = '✗ Erro ao sincronizar: ' . $result['error'];
                $messageType = 'error';
            }
            break;
            
        case 'toggle_live':
            $meetingId = $_POST['meeting_id'];
            $showLive = $_POST['show_live'] == '1' ? 1 : 0;
            if (toggleMeetingLiveDisplay($meetingId, $showLive)) {
                $message = $showLive ? '✓ Reunião marcada para exibição!' : '✓ Exibição da reunião desmarcada.';
                $messageType = 'success';
            } else {
                $message = '✗ Erro ao atualizar reunião';
                $messageType = 'error';
            }
            break;
    }
}

// Buscar reuniões do banco de dados para exibir na página
$meetings = getActiveMeetingsFromDatabase(50);

// Configurações da Página
$page_title = 'Gerenciar Reuniões Zoom';
$page_description = 'Crie e gerencie reuniões do Zoom para o live stream.';

// Inclui o cabeçalho do site
include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';

?>
<style>
    .admin-card {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 25px;
        backdrop-filter: blur(10px);
    }
    .admin-card h2 {
        color: var(--text-primary);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: var(--text-secondary);
        font-weight: 600;
    }
    .form-group input, .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid var(--glass-border);
        border-radius: 8px;
        background-color: rgba(255, 255, 255, 0.08);
        color: var(--text-primary);
        font-size: 14px;
    }
    .form-group input:focus, .form-group textarea:focus {
        outline: none;
        border-color: var(--brand-purple);
    }
    .btn-admin {
        background: var(--brand-purple);
        color: white;
        border: none;
        padding: 12px 25px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-admin:hover {
        background: var(--brand-purple-dark);
        transform: translateY(-2px);
    }
    .btn-admin-secondary { background: #555; }
    .btn-admin-danger { background: var(--accent-red); }
    .btn-admin-success { background: var(--accent-green); }
    .btn-admin-small { padding: 8px 16px; font-size: 12px; }
    
    .message-feedback {
        padding: 15px; border-radius: 8px; margin-bottom: 20px;
    }
    .message-feedback.success { background-color: rgba(39, 174, 96, 0.3); color: #fff; }
    .message-feedback.error { background-color: rgba(192, 57, 43, 0.4); color: #fff; }

    .meetings-grid-admin {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
    }
    .meeting-card-admin {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
        border: 1px solid var(--glass-border);
        color: white; padding: 25px; border-radius: 12px; position: relative;
    }
    .meeting-card-admin.live {
        border-color: var(--accent-green);
        box-shadow: 0 0 15px rgba(72, 187, 120, 0.5);
    }
</style>

<div class="main-content">
    
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-video"></i> Gerenciar Reuniões Zoom</h1>
            <p>Crie e gerencie reuniões para o live stream</p>
        </div>
    </div>
    
    <?php if ($message): ?>
    <div class="message-feedback <?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <div class="admin-card">
        <h2><i class="fas fa-plug"></i> Testar Conexão com Zoom</h2>
        <form method="POST" style="display: inline-block; margin-right: 10px;">
            <input type="hidden" name="action" value="test_auth">
            <button type="submit" class="btn-admin"><i class="fas fa-bolt"></i> Testar Autenticação</button>
        </form>
        <a href="zoom_debug_log.txt" target="_blank" class="btn-admin btn-admin-secondary"><i class="fas fa-file-alt"></i> Ver Log</a>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; align-items: flex-start;">
        <div class="admin-card">
            <h2><i class="fas fa-plus-circle"></i> Criar Nova Reunião</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="form-group"><label for="topic">Título *</label><input type="text" id="topic" name="topic" required></div>
                <div class="form-group"><label for="start_time">Data e Hora *</label><input type="datetime-local" id="start_time" name="start_time" required></div>
                <div class="form-group"><label for="duration">Duração (minutos) *</label><input type="number" id="duration" name="duration" value="60" required></div>
                <div class="form-group"><label for="agenda">Descrição/Agenda</label><textarea id="agenda" name="agenda"></textarea></div>
                <button type="submit" class="btn-admin"><i class="fas fa-calendar-plus"></i> Criar Reunião</button>
            </form>
        </div>
        
        <div class="admin-card">
            <h2><i class="fas fa-link"></i> Adicionar/Sincronizar</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_existing">
                <div class="form-group">
                    <label for="meeting_id_or_url">Adicionar por ID ou URL *</label>
                    <input type="text" id="meeting_id_or_url" name="meeting_id_or_url" placeholder="Ex: 1234567890" required>
                </div>
                <button type="submit" class="btn-admin"><i class="fas fa-plus"></i> Adicionar</button>
            </form>
            <hr style="margin: 30px 0; border: none; border-top: 1px solid var(--glass-border);">
            <form method="POST">
                <input type="hidden" name="action" value="sync">
                <button type="submit" class="btn-admin btn-admin-secondary"><i class="fas fa-sync-alt"></i> Sincronizar Todas</button>
            </form>
        </div>
    </div>

    <div class="admin-card">
        <h2><i class="fas fa-list"></i> Reuniões Agendadas (<?php echo count($meetings); ?>)</h2>
        <?php if (empty($meetings)): ?>
            <p>Nenhuma reunião encontrada.</p>
        <?php else: ?>
            <div class="meetings-grid-admin">
                <?php foreach ($meetings as $meeting): ?>
                    <div class="meeting-card-admin <?php echo ($meeting['show_live'] ?? 0) ? 'live' : ''; ?>">
                        <h4 style="margin-bottom: 15px;"><?php echo htmlspecialchars($meeting['topic']); ?></h4>
                        <p><i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($meeting['start_time'])); ?></p>
                        <p><i class="fas fa-hashtag"></i> ID: <?php echo $meeting['meeting_id']; ?></p>
                        <div style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                            <a href="<?php echo htmlspecialchars($meeting['join_url']); ?>" target="_blank" class="btn-admin btn-admin-success btn-admin-small"><i class="fas fa-video"></i> Entrar</a>
                            <form method="POST" style="display: inline;"><input type="hidden" name="action" value="toggle_live"><input type="hidden" name="meeting_id" value="<?php echo $meeting['meeting_id']; ?>"><input type="hidden" name="show_live" value="<?php echo ($meeting['show_live'] ?? 0) ? '0' : '1'; ?>"><button type="submit" class="btn-admin btn-admin-secondary btn-admin-small"><i class="fas fa-<?php echo ($meeting['show_live'] ?? 0) ? 'eye-slash' : 'eye'; ?>"></i> <?php echo ($meeting['show_live'] ?? 0) ? 'Ocultar' : 'Exibir'; ?></button></form>
                            <form method="POST" onsubmit="return confirm('Tem certeza?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="meeting_id" value="<?php echo $meeting['meeting_id']; ?>"><button type="submit" class="btn-admin btn-admin-danger btn-admin-small"><i class="fas fa-trash"></i></button></form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Inclui o rodapé do site
include __DIR__ . '/../vision/includes/footer.php';
?>