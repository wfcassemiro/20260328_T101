<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Verificar se é admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: /login.php');
    exit;
}

$page_title = 'Gerenciar Palestras - Admin';
$message = '';
$error = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_lecture':
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $speaker = trim($_POST['speaker'] ?? '');
                $category = trim($_POST['category'] ?? '');
                
                if (empty($title)) {
                    throw new Exception('Título é obrigatório');
                }
                
                // Inserção básica apenas com campos essenciais
                $stmt = $pdo->prepare("INSERT INTO lectures (id, title, description, speaker, category) VALUES (UUID(), ?, ?, ?, ?)");
                $stmt->execute([$title, $description, $speaker, $category]);
                $message = 'Palestra adicionada com sucesso!';
                break;
                
            case 'delete_lecture':
                $lecture_id = trim($_POST['lecture_id'] ?? '');
                if (!empty($lecture_id)) {
                    $stmt = $pdo->prepare("DELETE FROM lectures WHERE id = ?");
                    $stmt->execute([$lecture_id]);
                    $message = 'Palestra removida com sucesso!';
                }
                break;
        }
    } catch (Exception $e) {
        $error = 'Erro ao processar ação: ' . $e->getMessage();
    }
}

// Buscar palestras com SELECT básico
$search = $_GET['search'] ?? '';

try {
    $where_clause = '';
    $params = [];
    
    if ($search) {
        $where_clause = "WHERE (title LIKE ? OR speaker LIKE ?)";
        $params = ["%$search%", "%$search%"];
    }
    
    // Query básica apenas com campos essenciais
    $stmt = $pdo->prepare("SELECT id, title, description, speaker, category FROM lectures $where_clause ORDER BY id DESC");
    $stmt->execute($params);
    $lectures = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $lectures = [];
    $error = 'Erro ao carregar palestras: ' . $e->getMessage();
}

include __DIR__ . '/../../vision/includes/head.php';
include __DIR__ . '/../../vision/includes/header.php';
include __DIR__ . '/../../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-video"></i> Gerenciar Palestras</h1>
            <p>Administração do conteúdo educacional da plataforma</p>
            <a href="index.php" class="cta-btn">
                <i class="fas fa-arrow-left"></i> Voltar ao Admin
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
        
        <div style="background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 8px;">
            <h3>🔍 Para verificar a estrutura exata da tabela:</h3>
            <p>Acesse: <a href="verificar_estrutura_lectures.php" target="_blank" style="color: #007bff;">verificar_estrutura_lectures.php</a></p>
        </div>
    <?php endif; ?>

    <!-- Formulário Básico -->
    <div class="vision-form">
        <div class="card-header">
            <h2><i class="fas fa-plus-circle"></i> Adicionar Nova Palestra</h2>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="add_lecture">
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="title"><i class="fas fa-heading"></i> Título *</label>
                    <input type="text" id="title" name="title" required maxlength="255">
                </div>
                
                <div class="form-group">
                    <label for="speaker"><i class="fas fa-user"></i> Palestrante</label>
                    <input type="text" id="speaker" name="speaker" maxlength="100">
                </div>
                
                <div class="form-group form-group-wide">
                    <label for="category"><i class="fas fa-tag"></i> Categoria</label>
                    <input type="text" id="category" name="category" maxlength="50" 
                           placeholder="ex: Tradução, Interpretação, etc.">
                </div>
                
                <div class="form-group form-group-wide">
                    <label for="description"><i class="fas fa-align-left"></i> Descrição</label>
                    <textarea id="description" name="description" rows="3" maxlength="1000"
                              placeholder="Descreva o conteúdo da palestra..."></textarea>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="cta-btn">
                    <i class="fas fa-save"></i> Adicionar Palestra
                </button>
            </div>
        </form>
    </div>

    <!-- Busca Básica -->
    <div class="vision-form">
        <div class="card-header">
            <h2><i class="fas fa-search"></i> Buscar Palestras</h2>
        </div>
        
        <form method="GET">
            <div class="search-group">
                <input type="text" name="search" placeholder="Buscar por título ou palestrante..." 
                       value="<?php echo htmlspecialchars($search); ?>" style="width: 300px;">
                
                <button type="submit" class="cta-btn" style="padding: 10px 20px;">
                    <i class="fas fa-search"></i> Buscar
                </button>
                
                <a href="palestras.php" class="page-btn" style="padding: 10px 20px;">
                    <i class="fas fa-times"></i> Limpar
                </a>
            </div>
        </form>
    </div>

    <!-- Lista Básica -->
    <div class="vision-table">
        <div class="card-header">
            <h2><i class="fas fa-list"></i> Palestras Cadastradas (<?php echo count($lectures); ?>)</h2>
        </div>
        
        <?php if (empty($lectures)): ?>
            <div class="empty-state">
                <i class="fas fa-video-slash"></i>
                <h3>Nenhuma palestra encontrada</h3>
                <p>Adicione a primeira palestra ou verifique a estrutura da tabela.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-video"></i> Palestra</th>
                            <th><i class="fas fa-user"></i> Palestrante</th>
                            <th><i class="fas fa-tag"></i> Categoria</th>
                            <th><i class="fas fa-cogs"></i> Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lectures as $lecture): ?>
                            <tr>
                                <td>
                                    <div class="project-info">
                                        <span class="text-primary"><?php echo htmlspecialchars($lecture['title']); ?></span>
                                        <span class="project-client">
                                            <?php 
                                            $description = $lecture['description'] ?? '';
                                            echo htmlspecialchars(substr($description, 0, 100));
                                            if (strlen($description) > 100) echo '...';
                                            ?>
                                        </span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($lecture['speaker'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($lecture['category'] ?: 'Geral'); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- Deletar -->
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Tem certeza que deseja remover esta palestra?');">
                                            <input type="hidden" name="action" value="delete_lecture">
                                            <input type="hidden" name="lecture_id" value="<?php echo htmlspecialchars($lecture['id']); ?>">
                                            <button type="submit" class="page-btn" style="color: #e74c3c;" title="Remover Palestra">
                                                <i class="fas fa-trash"></i>
                                            </button>
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

    <!-- Estatísticas Básicas -->
    <div class="stats-grid" style="margin-top: 40px;">
        <div class="card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3>Total de Palestras</h3>
                    <span class="stats-number"><?php echo count($lectures); ?></span>
                </div>
                <div class="stats-icon stats-icon-blue">
                    <i class="fas fa-video"></i>
                </div>
            </div>
        </div>
        
        <div class="card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3>Com Palestrante</h3>
                    <?php $withSpeaker = array_filter($lectures, function($l) { return !empty($l['speaker']); }); ?>
                    <span class="stats-number"><?php echo count($withSpeaker); ?></span>
                </div>
                <div class="stats-icon stats-icon-green">
                    <i class="fas fa-user"></i>
                </div>
            </div>
        </div>
        
        <div class="card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3>Com Categoria</h3>
                    <?php $withCategory = array_filter($lectures, function($l) { return !empty($l['category']); }); ?>
                    <span class="stats-number"><?php echo count($withCategory); ?></span>
                </div>
                <div class="stats-icon stats-icon-purple">
                    <i class="fas fa-tags"></i>
                </div>
            </div>
        </div>
        
        <div class="card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3>Verificar Estrutura</h3>
                    <a href="verificar_estrutura_lectures.php" class="stats-number" style="color: #007bff; text-decoration: none;">🔍 Ver BD</a>
                </div>
                <div class="stats-icon stats-icon-red">
                    <i class="fas fa-database"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Estilos específicos para a página de palestras */
.form-group textarea {
    resize: vertical;
    min-height: 80px;
}

.project-client {
    font-size: 0.85rem;
    color: #ccc !important;
    display: block;
    margin-top: 4px;
}

.action-buttons {
    display: flex;
    gap: 8px;
    align-items: center;
}

.action-buttons form {
    margin: 0;
}

.search-group {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    font-size: 4rem;
    color: #666;
    margin-bottom: 20px;
    display: block;
}

.empty-state h3 {
    color: #fff;
    margin-bottom: 12px;
    font-size: 1.4rem;
}

.empty-state p {
    color: #ccc;
    margin: 0;
}

@media (max-width: 768px) {
    .search-group {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-group input,
    .search-group button,
    .search-group a {
        width: 100% !important;
        margin-bottom: 8px;
    }
    
    .action-buttons {
        flex-wrap: wrap;
        justify-content: center;
    }
}
</style>

<?php include __DIR__ . '/../../vision/includes/footer.php'; ?>