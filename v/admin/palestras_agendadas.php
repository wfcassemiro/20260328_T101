<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
// Ajuste o caminho do database conforme sua estrutura
require_once __DIR__ . '/../config/database.php';

// Verificar se é admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: /login.php');
    exit;
}

$page_title = 'Palestras agendadas - Admin';
$message = '';
$error = '';

// --- LÓGICA DE LIMPEZA AUTOMÁTICA ---
// Mantemos isso: Atualiza para inativo (is_active = 0) palestras passadas.
// Isso garante que o SITE PÚBLICO não mostre palestras velhas, mesmo que elas apareçam aqui na lista admin.
try {
    $pdo->query("
        UPDATE upcoming_announcements 
        SET is_active = 0 
        WHERE CONCAT(announcement_date, ' ', lecture_time) < NOW() 
        AND is_active = 1
    ");
} catch (Exception $e) {
    error_log("Erro ao atualizar status das palestras: " . $e->getMessage());
}

// --- PROCESSAMENTO DE FORMULÁRIOS (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. ADICIONAR
    if ($action === 'add') {
        $title = $_POST['title'];
        $speaker = $_POST['speaker'];
        $date = $_POST['announcement_date'];
        $time = $_POST['lecture_time'];
        $desc = $_POST['description'];
        $embed = $_POST['video_embed'] ?? '';

        $image_path = '/images/palestra-placeholder.jpg';
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $upload_dir = __DIR__ . '/../images/announcements/';
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
            
            if (!is_writable($upload_dir)) {
                $error = 'Erro: Diretório de upload não tem permissão de escrita';
            } else {
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $filename = 'announcement_' . uniqid() . '_' . date('Y-m-d') . '.' . $ext;
                $full_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $full_path)) {
                    $image_path = '/images/announcements/' . $filename;
                } else {
                    $error = 'Erro ao fazer upload da imagem.';
                }
            }
        } elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $error = "Erro no upload da imagem: código " . $_FILES['image']['error'];
        }

        if (empty($error)) {
            try {
                // CORREÇÃO AQUI: Gerar um ID único
                $newId = uniqid(); 
                
                // Incluído 'id' na query
                $stmt = $pdo->prepare("INSERT INTO upcoming_announcements (id, title, speaker, announcement_date, lecture_time, description, video_embed, image_path, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$newId, $title, $speaker, $date, $time, $desc, $embed, $image_path]);
                $message = 'Palestra agendada!';
            } catch (PDOException $e) {
                $error = 'Erro ao agendar: ' . $e->getMessage();
            }
        }
    }

    // 2. EDITAR
    elseif ($action === 'edit') {
        $id = $_POST['id'];
        $title = $_POST['title'];
        $speaker = $_POST['speaker'];
        $date = $_POST['announcement_date'];
        $time = $_POST['lecture_time'];
        $desc = $_POST['description'];
        $embed = $_POST['video_embed'] ?? '';

        try {
            if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                $upload_dir = __DIR__ . '/../images/announcements/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
                
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $filename = 'announcement_' . uniqid() . '_' . date('Y-m-d') . '.' . $ext;

                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                    $image_path = '/images/announcements/' . $filename;
                    $stmt = $pdo->prepare("UPDATE upcoming_announcements SET title=?, speaker=?, announcement_date=?, lecture_time=?, description=?, video_embed=?, image_path=? WHERE id=?");
                    $stmt->execute([$title, $speaker, $date, $time, $desc, $embed, $image_path, $id]);
                }
            } else {
                $stmt = $pdo->prepare("UPDATE upcoming_announcements SET title=?, speaker=?, announcement_date=?, lecture_time=?, description=?, video_embed=? WHERE id=?");
                $stmt->execute([$title, $speaker, $date, $time, $desc, $embed, $id]);
            }
            $message = 'Palestra atualizada!';
        } catch (PDOException $e) {
            $error = 'Erro ao atualizar: ' . $e->getMessage();
        }
    }

    // 3. EXCLUIR
    elseif ($action === 'delete') {
        $id = $_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM upcoming_announcements WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Palestra excluída.';
        } catch (PDOException $e) {
            $error = 'Erro ao excluir: ' . $e->getMessage();
        }
    }

    // 4. ENVIAR PARA O AR (LIVE)
    elseif ($action === 'send_to_live') {
        $id = $_POST['id'];
        try {
            // Pega dados da palestra
            $stmt = $pdo->prepare("SELECT * FROM upcoming_announcements WHERE id = ?");
            $stmt->execute([$id]);
            $lecture = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($lecture) {
                // Atualiza tabela de settings da live
                // (Ajuste os campos conforme sua tabela live_stream_settings)
                $stmtUp = $pdo->prepare("UPDATE live_stream_settings SET stream_title = ?, is_active = 1 WHERE id = 1"); 
                // Assumindo que id=1 é a configuração principal. Se não tiver ID fixo, ajuste o WHERE.
                // Se precisar atualizar o embed do OBS com o video_embed da palestra, adicione aqui.
                $stmtUp->execute([$lecture['title']]);
                
                echo json_encode(['success' => true]);
                exit;
            } else {
                echo json_encode(['success' => false, 'message' => 'Palestra não encontrada']);
                exit;
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

// --- LISTAGEM ---
$all_announcements = [];
try {
    // Ordenar por data (mais recente primeiro) ou data futura
    $stmt = $pdo->query("SELECT * FROM upcoming_announcements ORDER BY announcement_date DESC, lecture_time DESC");
    $all_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Erro ao buscar dados.';
    $all_announcements = [];
}

// --- HTML (estrutura básica mantida, adapte ao seu layout) ---
// Se usar include de header/sidebar, mantenha-os.
include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-calendar-alt"></i> Gerenciar palestras</h1>
            <p>Agende e gerencie as próximas transmissões</p>
        </div>
    </div>

    <div class="container-fluid" style="padding: 20px;">
        
        <?php if ($message): ?>
            <div class="alert success" id="successAlert">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div style="display:flex; justify-content: space-between; align-items:center; margin-bottom: 20px;">
            <button onclick="openModal('addModal')" class="cta-button">
                <i class="fas fa-plus"></i> Nova Palestra
            </button>
        </div>

        <div class="glass-panel">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Palestra</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($all_announcements) > 0): foreach ($all_announcements as $lecture): 
                            $is_live_now = false; // Lógica se estiver no ar (opcional)
                            $rowClass = $is_live_now ? 'row-active' : '';
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td>
                                <div><?php echo date('d/m/Y', strtotime($lecture['announcement_date'])); ?></div>
                                <small style="color:#aaa;"><?php echo date('H:i', strtotime($lecture['lecture_time'])); ?></small>
                            </td>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <img src="<?php echo htmlspecialchars($lecture['image_path']); ?>" style="width:50px; height:50px; object-fit:cover; border-radius:5px;">
                                    <div>
                                        <div style="font-weight:bold;"><?php echo htmlspecialchars($lecture['title']); ?></div>
                                        <small><?php echo htmlspecialchars($lecture['speaker']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php 
                                    if ($lecture['is_active']) echo '<span class="badge success">Ativo</span>';
                                    else echo '<span class="badge secondary">Passado/Inativo</span>';
                                ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn edit" onclick='openEditModal(<?php echo json_encode($lecture); ?>)' title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn live" onclick="sendToLive(<?php echo $lecture['id']; ?>)" title="<?php echo $is_live_now ? 'Já está no ar' : 'Transmitir agora'; ?>">
                                        <i class="fas fa-broadcast-tower"></i>
                                    </button>
                                    <button class="action-btn delete" onclick="confirmDelete('<?php echo $lecture['id']; ?>')" title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="4" style="text-align:center; padding:20px;">Nenhuma palestra encontrada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="addModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('addModal')">&times;</span>
        <h2><i class="fas fa-calendar-plus"></i> Agendar palestra</h2>
        <form method="post" enctype="multipart/form-data" id="addForm">
            <input type="hidden" name="action" value="add">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Título</label>
                    <input type="text" name="title" required>
                </div>
                <div class="form-group">
                    <label>Palestrante</label>
                    <input type="text" name="speaker" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Data</label>
                    <input type="date" name="announcement_date" required>
                </div>
                <div class="form-group">
                    <label>Horário</label>
                    <input type="time" name="lecture_time" required>
                </div>
            </div>

            <div class="form-group">
                <label>Descrição</label>
                <textarea name="description" rows="3"></textarea>
            </div>

            <div class="form-group">
                <label>Link/Embed (YouTube/Zoom)</label>
                <input type="text" name="video_embed" placeholder="<iframe>...</iframe> ou Link">
            </div>

            <div class="form-group">
                <label>Imagem de capa</label>
                <input type="file" name="image" accept="image/*">
            </div>

            <div style="text-align:right; margin-top:20px;">
                <button type="button" class="btn-cancel" onclick="closeModal('addModal')">Cancelar</button>
                <button type="submit" class="cta-button">Agendar</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('editModal')">&times;</span>
        <h2><i class="fas fa-edit"></i> Editar palestra</h2>
        <form method="post" enctype="multipart/form-data" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Título</label>
                    <input type="text" name="title" id="edit_title" required>
                </div>
                <div class="form-group">
                    <label>Palestrante</label>
                    <input type="text" name="speaker" id="edit_speaker" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Data</label>
                    <input type="date" name="announcement_date" id="edit_date" required>
                </div>
                <div class="form-group">
                    <label>Horário</label>
                    <input type="time" name="lecture_time" id="edit_time" required>
                </div>
            </div>

            <div class="form-group">
                <label>Descrição</label>
                <textarea name="description" id="edit_desc" rows="3"></textarea>
            </div>

            <div class="form-group">
                <label>Link/Embed</label>
                <input type="text" name="video_embed" id="edit_embed">
            </div>

            <div class="form-group">
                <label>Alterar imagem</label>
                <input type="file" name="image" accept="image/*">
                <small id="current_image_text" style="color:#aaa;"></small>
            </div>

            <div style="text-align:right; margin-top:20px;">
                <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Cancelar</button>
                <button type="submit" class="cta-button">Salvar alterações</button>
            </div>
        </form>
    </div>
</div>

<form method="post" id="deleteForm" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<style>
/* Estilos básicos para o layout funcionar caso não tenha o CSS global */
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); backdrop-filter: blur(5px); }
.modal-content { background: #1a1a1a; margin: 5% auto; padding: 25px; border: 1px solid #333; width: 90%; max-width: 600px; border-radius: 12px; color: #fff; box-shadow: 0 0 20px rgba(0,0,0,0.5); }
.close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
.close:hover { color: #fff; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; color: #ccc; }
.form-group input, .form-group textarea { width: 100%; padding: 10px; background: #2a2a2a; border: 1px solid #444; color: #fff; border-radius: 5px; }
.cta-button { background: #e74c3c; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
.btn-cancel { background: transparent; color: #ccc; border: 1px solid #555; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-right: 10px; }
.glass-hero { background: linear-gradient(135deg, rgba(255,255,255,0.05), rgba(255,255,255,0.01)); padding: 40px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.1); margin-bottom: 30px; }
.glass-panel { background: rgba(30, 30, 30, 0.6); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; color: #fff; }
.data-table th, .data-table td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); }
.data-table th { background: rgba(0,0,0,0.2); color: #ccc; font-weight: 600; }
.data-table tr:hover { background: rgba(255,255,255,0.05); }
/* Destaque para linha no ar */
.row-active { background: rgba(231, 76, 60, 0.1) !important; border-left: 3px solid #e74c3c; }
/* Botões */
.action-buttons { display: flex; gap: 10px; }
.action-btn { background: transparent; border: none; cursor: pointer; font-size: 1.1rem; color: #ccc; transition: 0.3s; }
.action-btn:hover { color: #fff; transform: scale(1.1); }
.action-btn.delete:hover { color: #e74c3c; }
.alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
.alert.success { background: rgba(46, 204, 113, 0.2); border: 1px solid #2ecc71; color: #2ecc71; }
.alert.error { background: rgba(231, 76, 60, 0.2); border: 1px solid #e74c3c; color: #e74c3c; }
.badge { padding: 5px 10px; border-radius: 15px; font-size: 0.8rem; }
.badge.success { background: rgba(46, 204, 113, 0.2); color: #2ecc71; }
.badge.secondary { background: rgba(128, 128, 128, 0.2); color: #ccc; }
</style>

<script>
// JS para Modais
function openModal(id) { document.getElementById(id).style.display = 'block'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

function openEditModal(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_title').value = data.title;
    document.getElementById('edit_speaker').value = data.speaker;
    document.getElementById('edit_date').value = data.announcement_date;
    document.getElementById('edit_time').value = data.lecture_time;
    document.getElementById('edit_desc').value = data.description;
    document.getElementById('edit_embed').value = data.video_embed || '';
    document.getElementById('current_image_text').innerText = 'Imagem atual: ' + data.image_path.split('/').pop();
    openModal('editModal');
}

function confirmDelete(id) {
    if(confirm('Excluir esta palestra?')) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}

function sendToLive(id) {
    if (!confirm('ATENÇÃO: Substituir a transmissão atual por esta palestra?')) return;

    const formData = new FormData();
    formData.append('action', 'send_to_live');
    formData.append('id', id);

    fetch('palestras_agendadas.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Palestra está no ar!');
            window.location.reload(); // Recarrega para ver o ícone vermelho
        } else {
            alert('❌ Erro: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro de conexão.');
    });
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// Fade out alerts
const alert = document.getElementById('successAlert');
if (alert) {
    setTimeout(function() {
        alert.style.opacity = '0';
        setTimeout(function() { alert.style.display = 'none'; }, 500); // Aguarda fade out
    }, 5000);
}
</script>