<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Verificar se é admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: /login.php');
    exit;
}

// Criar tabela se não existir
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS course_signups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        course_interest VARCHAR(255),
        other_course TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Tabela já existe
}

// Processar exportação CSV via GET
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    try {
        $stmt = $pdo->query("SELECT * FROM course_signups ORDER BY created_at DESC");
        $signups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Gerar CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="interessados_cursos_' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Cabeçalho
        fputcsv($output, ['ID', 'Nome', 'E-mail', 'Curso de Interesse', 'Outro Curso', 'Data de Cadastro'], ';');
        
        // Dados
        foreach ($signups as $signup) {
            fputcsv($output, [
                $signup['id'],
                $signup['name'],
                $signup['email'],
                $signup['course_interest'],
                $signup['other_course'] ?: '-',
                date('d/m/Y H:i', strtotime($signup['created_at']))
            ], ';');
        }
        
        fclose($output);
        exit;
    } catch (PDOException $e) {
        die('Erro ao exportar dados: ' . $e->getMessage());
    }
}

// Processar ações AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    if ($action === 'delete_signup') {
        $id = intval($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM course_signups WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Registro excluído com sucesso']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao excluir registro']);
        }
        exit;
    }
}

// Buscar interessados
try {
    $signups = $pdo->query("SELECT * FROM course_signups ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $signups = [];
}

$page_title = 'Interessados em Cursos - Admin';
$page_description = 'Gerenciar interessados em cursos futuros';

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-user-graduate"></i> Interessados em Cursos</h1>
            <p>Gerencie as pessoas interessadas em futuros cursos</p>
        </div>
    </div>

    <div class="video-card">
        <div class="card-header">
            <h2><i class="fas fa-list"></i> Lista de Interessados (<?php echo count($signups); ?>)</h2>
            <div class="header-actions">
                <a href="?action=export_csv" class="btn btn-success">
                    <i class="fas fa-file-excel"></i> Exportar CSV
                </a>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
        </div>

        <div id="messageContainer"></div>

        <?php if (empty($signups)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>Nenhum interessado ainda</h3>
            <p>Quando alguém se inscrever no formulário da página de cursos, aparecerá aqui.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Curso de Interesse</th>
                        <th>Outro Curso</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($signups as $signup): ?>
                    <tr id="row-<?php echo $signup['id']; ?>">
                        <td><?php echo $signup['id']; ?></td>
                        <td><?php echo htmlspecialchars($signup['name']); ?></td>
                        <td>
                            <a href="mailto:<?php echo htmlspecialchars($signup['email']); ?>" class="email-link">
                                <?php echo htmlspecialchars($signup['email']); ?>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($signup['course_interest'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($signup['other_course'] ?: '-'); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($signup['created_at'])); ?></td>
                        <td>
                            <button onclick="deleteSignup(<?php echo $signup['id']; ?>)" class="btn-icon btn-danger" title="Excluir">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.card-header h2 {
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-success {
    background: linear-gradient(135deg, #27ae60, #229954);
    color: white;
}

.btn-success:hover {
    background: linear-gradient(135deg, #229954, #1e8449);
    transform: translateY(-2px);
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

.table-responsive {
    overflow-x: auto;
    margin-top: 20px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    overflow: hidden;
}

.data-table thead {
    background: rgba(255, 255, 255, 0.1);
}

.data-table th,
.data-table td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.data-table th {
    font-weight: 600;
    color: #fff;
}

.data-table tbody tr:hover {
    background: rgba(255, 255, 255, 0.05);
}

.email-link {
    color: #3498db;
    text-decoration: none;
}

.email-link:hover {
    text-decoration: underline;
}

.btn-icon {
    padding: 8px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.1);
    color: white;
}

.btn-danger {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
}

.btn-danger:hover {
    background: linear-gradient(135deg, #c0392b, #a93226);
    transform: scale(1.1);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: rgba(255, 255, 255, 0.7);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-state h3 {
    margin: 0 0 10px 0;
    color: #fff;
}

.message {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.message.success {
    background: rgba(39, 174, 96, 0.2);
    border: 1px solid #27ae60;
    color: #27ae60;
}

.message.error {
    background: rgba(231, 76, 60, 0.2);
    border: 1px solid #e74c3c;
    color: #e74c3c;
}

@media (max-width: 768px) {
    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-actions {
        width: 100%;
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
function showMessage(message, type) {
    const container = document.getElementById('messageContainer');
    const messageDiv = document.createElement('div');
    messageDiv.className = 'message ' + type;
    messageDiv.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + message;
    
    container.innerHTML = '';
    container.appendChild(messageDiv);
    
    setTimeout(() => {
        messageDiv.style.opacity = '0';
        messageDiv.style.transform = 'translateY(-10px)';
        messageDiv.style.transition = 'all 0.3s ease';
        setTimeout(() => messageDiv.remove(), 300);
    }, 5000);
}

function deleteSignup(id) {
    if (!confirm('Tem certeza que deseja excluir este registro?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_signup');
    formData.append('id', id);
    
    fetch(window.location.pathname, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('row-' + id).remove();
            showMessage(data.message, 'success');
            
            // Atualizar contador
            const table = document.querySelector('.data-table tbody');
            if (table && table.children.length === 0) {
                location.reload();
            }
        } else {
            showMessage(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showMessage('Erro ao excluir registro', 'error');
    });
}
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>