<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Proteção da página: Apenas admins podem acessar
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}


// Processar ações do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = $_POST['id'] ?? 0;
    
    // Lógica de Upload de Imagem
    $image_path = $_POST['current_image_path'] ?? ''; // Mantém a imagem atual por padrão
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../images/cursos/posters/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $file_name = uniqid('course_') . '_' . basename($_FILES['image']['name']);
        $target_file = $upload_dir . $file_name;
        
        // Valida o tipo de arquivo
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($imageFileType, $allowed_types)) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image_path = '/images/cursos/posters/' . $file_name;
            }
        }
    }

    switch ($_POST['action']) {
        case 'toggle_enrollment':
            $stmt = $pdo->prepare("UPDATE courses SET enrollment_open = NOT enrollment_open, manual_close = 1 WHERE id = ?");
            $stmt->execute([$id]);
            break;
            
        case 'delete':
            // Opcional: deletar a imagem do servidor antes de apagar do DB
            $stmt = $pdo->prepare("SELECT image_path FROM courses WHERE id = ?");
            $stmt->execute([$id]);
            $course = $stmt->fetch();
            if ($course && !empty($course['image_path']) && file_exists(__DIR__ . '/..' . $course['image_path'])) {
                unlink(__DIR__ . '/..' . $course['image_path']);
            }

            $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
            $stmt->execute([$id]);
            break;
            
        case 'add':
            $stmt = $pdo->prepare("INSERT INTO courses (title, slug, short_description, features, image_path, page_url, badge_text, course_type, start_date, end_date, enrollment_open) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            // Criando um slug simples a partir do título
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['title'])));
            $stmt->execute([
                $_POST['title'], $slug, $_POST['short_description'], $_POST['features'], $image_path, 
                $_POST['page_url'], $_POST['badge_text'], $_POST['course_type'], $_POST['start_date'], $_POST['end_date']
            ]);
            break;
            
        case 'edit':
            $stmt = $pdo->prepare("UPDATE courses SET title = ?, short_description = ?, features = ?, image_path = ?, page_url = ?, badge_text = ?, course_type = ?, start_date = ?, end_date = ? WHERE id = ?");
            $stmt->execute([
                $_POST['title'], $_POST['short_description'], $_POST['features'], $image_path, 
                $_POST['page_url'], $_POST['badge_text'], $_POST['course_type'], $_POST['start_date'], $_POST['end_date'], $id
            ]);
            break;
    }
    header('Location: gerenciar_cursos.php');
    exit;
}

// Buscar cursos
$stmt = $pdo->query("SELECT * FROM courses ORDER BY start_date DESC");
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Gerenciar Cursos - Admin';
include __DIR__ . '/../vision/includes/head.php';
?>

<style>
/* Estilos específicos da página de gerenciamento */
.page-header { margin-bottom: 40px; }
.page-header h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 15px; }
.page-header p { color: var(--text-muted); font-size: 1.1rem; }
.actions-bar { display: flex; gap: 15px; margin-bottom: 30px; flex-wrap: wrap; }
.btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: var(--glass-bg); backdrop-filter: blur(10px); border: 1px solid var(--glass-border); border-radius: 12px; color: var(--text-primary); text-decoration: none; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: all 0.3s ease; }
.btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(168, 85, 247, 0.3); border-color: var(--brand-purple); }
.btn-primary { background: linear-gradient(135deg, var(--brand-purple), #6a1b9a); border-color: var(--brand-purple); color: white; }
.btn-primary:hover { box-shadow: 0 8px 20px rgba(168, 85, 247, 0.5); }
.btn-success { background: linear-gradient(135deg, var(--accent-green), #1e8449); border-color: var(--accent-green); color: white; }
.btn-success:hover { box-shadow: 0 8px 20px rgba(39, 174, 96, 0.5); }
.btn-warning { background: linear-gradient(135deg, var(--accent-gold), #e67e22); border-color: var(--accent-gold); color: white;}
.btn-warning:hover { box-shadow: 0 8px 20px rgba(243, 156, 18, 0.5); }
.btn-danger { background: linear-gradient(135deg, #e74c3c, #c0392b); border-color: #e74c3c; color: white; }
.btn-danger:hover { box-shadow: 0 8px 20px rgba(231, 76, 60, 0.5); }
.btn-small { padding: 8px 16px; font-size: 0.85rem; }
.glass-card { background: var(--glass-bg); backdrop-filter: blur(20px); border: 1px solid var(--glass-border); border-radius: 20px; padding: 30px; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); }
.table-container { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 16px; text-align: left; border-bottom: 1px solid var(--glass-border); }
th { font-weight: 600; color: var(--text-primary); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
td { color: var(--text-muted); vertical-align: middle; }
.course-info { display: flex; align-items: center; gap: 15px; }
.course-info img { width: 120px; height: 67px; object-fit: cover; border-radius: 8px; }
.course-title { color: var(--text-primary); font-weight: 600; font-size: 1.05rem; }
.status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
.status-open { background: rgba(39, 174, 96, 0.2); color: var(--accent-green); border: 1px solid var(--accent-green); }
.status-closed { background: rgba(231, 76, 60, 0.2); color: #e74c3c; border: 1px solid #e74c3c; }
.actions-cell { display: flex; gap: 8px; flex-wrap: wrap; }
.actions-cell form { display: inline; }
.modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(10px); z-index: 1000; align-items: center; justify-content: center; }
.modal.active { display: flex; }
.modal-content { background: var(--glass-bg-darker); backdrop-filter: blur(20px); max-width: 600px; width: 90%; padding: 40px; border-radius: 24px; border: 1px solid var(--glass-border); box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5); position: relative; animation: modalSlideIn 0.3s ease; max-height: 90vh; overflow-y: auto; }
@keyframes modalSlideIn { from { opacity: 0; transform: translateY(-30px); } to { opacity: 1; transform: translateY(0); } }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
.modal-header h2 { font-size: 1.8rem; font-weight: 700; }
.close { font-size: 28px; cursor: pointer; color: var(--text-muted); transition: color 0.3s ease; background: none; border: none; padding: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; }
.close:hover { color: var(--text-primary); }
.form-group { margin-bottom: 24px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary); font-size: 0.95rem; }
.form-group input, .form-group textarea, .form-group select { width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid var(--glass-border); background: rgba(0,0,0,0.2); color: var(--text-primary); font-size: 1rem; transition: all 0.3s ease; box-sizing: border-box; }
.form-group textarea { resize: vertical; min-height: 100px; }
.form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: var(--brand-purple); box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.2); }
.empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
.empty-state i { font-size: 4rem; margin-bottom: 20px; opacity: 0.3; }
.empty-state h3 { font-size: 1.5rem; margin-bottom: 10px; color: var(--text-primary); }
</style>

<?php
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-graduation-cap"></i> Gerenciar Cursos</h1>
        <p>Adicione, edite e controle o status dos cursos para a página principal.</p>
    </div>
    
    <div class="actions-bar">
        <button onclick="openAddModal()" class="btn btn-success">
            <i class="fas fa-plus"></i> Adicionar Curso
        </button>
    </div>

    <div class="glass-card">
        <?php if (empty($courses)): ?>
            <div class="empty-state">
                <i class="fas fa-graduation-cap"></i>
                <h3>Nenhum curso cadastrado</h3>
                <p>Clique em "Adicionar Curso" para começar.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Curso</th>
                            <th>Datas</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $course): ?>
                        <tr>
                            <td>
                                <div class="course-info">
                                    <img src="<?= htmlspecialchars($course['image_path'] ?? '/images/placeholder.png') ?>" alt="Capa do curso">
                                    <span class="course-title"><?= htmlspecialchars($course['title']) ?></span>
                                </div>
                            </td>
                            <td>
                                Início: <?= date('d/m/Y', strtotime($course['start_date'])) ?><br>
                                Fim: <?= $course['end_date'] ? date('d/m/Y', strtotime($course['end_date'])) : '-' ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $course['enrollment_open'] ? 'status-open' : 'status-closed' ?>">
                                    <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                    <?= $course['enrollment_open'] ? 'Abertas' : 'Encerradas' ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <form method="POST"><input type="hidden" name="id" value="<?= $course['id'] ?>"><input type="hidden" name="action" value="toggle_enrollment"><button type="submit" class="btn btn-warning btn-small"><i class="fas fa-toggle-<?= $course['enrollment_open'] ? 'on' : 'off' ?>"></i> <?= $course['enrollment_open'] ? 'Fechar' : 'Abrir' ?></button></form>
                                    <button onclick='openEditModal(<?= json_encode($course) ?>)' class="btn btn-primary btn-small"><i class="fas fa-edit"></i> Editar</button>
                                    <form method="POST" onsubmit="return confirm('Tem certeza que deseja excluir este curso?')"><input type="hidden" name="id" value="<?= $course['id'] ?>"><input type="hidden" name="action" value="delete"><button type="submit" class="btn btn-danger btn-small"><i class="fas fa-trash"></i> Excluir</button></form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="courseModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Adicionar Novo Curso</h2>
            <button class="close" onclick="closeModal('courseModal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="edit_id">
            <input type="hidden" name="current_image_path" id="current_image_path">
            
            <div class="form-group">
                <label>Título do Curso</label>
                <input type="text" name="title" id="edit_title" required>
            </div>
            <div class="form-group">
                <label>Descrição Curta (para o card)</label>
                <textarea name="short_description" id="edit_short_description" required></textarea>
            </div>
            <div class="form-group">
                <label>Características (uma por linha)</label>
                <textarea name="features" id="edit_features" placeholder="Ex: 20 horas de conteúdo&#10;Acesso por 6 meses"></textarea>
            </div>
             <div class="form-group">
                <label>Tipo de Curso</label>
                <select name="course_type" id="edit_course_type">
                    <option value="Ao vivo">Ao vivo / Próxima turma</option>
                    <option value="Gravado">Gravado</option>
                </select>
            </div>
            <div class="form-group">
                <label>Texto do Badge (Ex: 🎮 Curso completo)</label>
                <input type="text" name="badge_text" id="edit_badge_text">
            </div>
            <div class="form-group">
                <label>URL da Página do Curso (Ex: /cursos/supercurso8.php)</label>
                <input type="text" name="page_url" id="edit_page_url" required>
            </div>
            <div class="form-group">
                <label>Imagem do Card (proporção 16:9)</label>
                <input type="file" name="image" accept="image/png, image/jpeg, image/webp, image/gif">
            </div>
            <div class="form-group">
                <label>Data de Início</label>
                <input type="date" name="start_date" id="edit_start_date" required>
            </div>
            <div class="form-group">
                <label>Data de Término das Inscrições</label>
                <input type="date" name="end_date" id="edit_end_date">
            </div>
            <button type="submit" class="btn btn-success" style="width: 100%;"><i class="fas fa-save"></i> Salvar</button>
        </form>
    </div>
</div>

<script>
const modal = document.getElementById('courseModal');
const modalTitle = document.getElementById('modalTitle');
const formAction = document.getElementById('formAction');
const form = modal.querySelector('form');

function openAddModal() {
    form.reset();
    modalTitle.innerText = 'Adicionar Novo Curso';
    formAction.value = 'add';
    document.getElementById('edit_id').value = '';
    document.getElementById('current_image_path').value = '';
    modal.classList.add('active');
}

function openEditModal(course) {
    form.reset();
    modalTitle.innerText = 'Editar Curso';
    formAction.value = 'edit';
    
    document.getElementById('edit_id').value = course.id;
    document.getElementById('edit_title').value = course.title;
    document.getElementById('edit_short_description').value = course.short_description || '';
    document.getElementById('edit_features').value = course.features || '';
    document.getElementById('current_image_path').value = course.image_path || '';
    document.getElementById('edit_page_url').value = course.page_url || '';
    document.getElementById('edit_badge_text').value = course.badge_text || '';
    document.getElementById('edit_course_type').value = course.course_type || 'Ao vivo';
    document.getElementById('edit_start_date').value = course.start_date;
    document.getElementById('edit_end_date').value = course.end_date || '';
    
    modal.classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(m => m.classList.remove('active'));
    }
});
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>