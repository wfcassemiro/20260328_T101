<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/dash_database.php';

// Page settings
$page_title = 'Projetos';
$page_description = 'Crie e gerencie seus projetos.';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_project':
                try {
                    $word_count = intval($_POST['word_count'] ?? 0);
                    $character_count = intval($_POST['character_count'] ?? 0);
                    $rate_per_word = floatval($_POST['rate_per_word'] ?? 0);
                    $rate_per_character = floatval($_POST['rate_per_character'] ?? 0);
                    $total_amount = calculateProjectTotal($word_count, $character_count, $rate_per_word, $rate_per_character);
                    
                    // Simplified insert without client dependency
                    $stmt = $pdo->prepare("INSERT INTO dash_projects (user_id, client_name, project_name, project_description, source_language, target_language, service_type, word_count, character_count, rate_per_word, rate_per_character, total_amount, currency, status, priority, start_date, deadline, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $result = $stmt->execute([
                        $user_id,
                        $_POST['client_name'] ?? '',
                        $_POST['project_name'],
                        $_POST['project_description'] ?? '',
                        $_POST['source_language'],
                        $_POST['target_language'],
                        $_POST['service_type'],
                        $word_count,
                        $character_count,
                        $rate_per_word,
                        $rate_per_character,
                        $total_amount,
                        $_POST['currency'] ?? 'BRL',
                        $_POST['status'],
                        $_POST['priority'],
                        $_POST['start_date'] ?: null,
                        $_POST['deadline'] ?: null,
                        $_POST['notes'] ?? ''
                    ]);
                    
                    if ($result) {
                        $message = 'Projeto adicionado com sucesso!';
                    } else {
                        $error = 'Erro ao adicionar projeto.';
                    }
                } catch (PDOException $e) {
                    // If table doesn't exist, show appropriate message
                    $error = 'Funcionalidade de projetos em desenvolvimento. Tabela não encontrada.';
                }
                break;
                
            case 'edit_project':
                try {
                    $word_count = intval($_POST['word_count'] ?? 0);
                    $character_count = intval($_POST['character_count'] ?? 0);
                    $rate_per_word = floatval($_POST['rate_per_word'] ?? 0);
                    $rate_per_character = floatval($_POST['rate_per_character'] ?? 0);
                    $total_amount = calculateProjectTotal($word_count, $character_count, $rate_per_word, $rate_per_character);
                    
                    $stmt = $pdo->prepare("UPDATE dash_projects SET client_name = ?, project_name = ?, project_description = ?, source_language = ?, target_language = ?, service_type = ?, word_count = ?, character_count = ?, rate_per_word = ?, rate_per_character = ?, total_amount = ?, currency = ?, status = ?, priority = ?, start_date = ?, deadline = ?, notes = ? WHERE id = ? AND user_id = ?");
                    $result = $stmt->execute([
                        $_POST['client_name'] ?? '',
                        $_POST['project_name'],
                        $_POST['project_description'] ?? '',
                        $_POST['source_language'],
                        $_POST['target_language'],
                        $_POST['service_type'],
                        $word_count,
                        $character_count,
                        $rate_per_word,
                        $rate_per_character,
                        $total_amount,
                        $_POST['currency'] ?? 'BRL',
                        $_POST['status'],
                        $_POST['priority'],
                        $_POST['start_date'] ?: null,
                        $_POST['deadline'] ?: null,
                        $_POST['notes'] ?? '',
                        $_POST['project_id'],
                        $user_id
                    ]);
                    
                    if ($result) {
                        $message = 'Projeto atualizado com sucesso!';
                    } else {
                        $error = 'Erro ao atualizar projeto.';
                    }
                } catch (PDOException $e) {
                    $error = 'Funcionalidade de projetos em desenvolvimento. Erro: ' . $e->getMessage();
                }
                break;
                
            case 'delete_project':
                try {
                    $stmt = $pdo->prepare("DELETE FROM dash_projects WHERE id = ? AND user_id = ?");
                    $result = $stmt->execute([$_POST['project_id'], $user_id]);
                    
                    if ($result) {
                        $message = 'Projeto excluído com sucesso!';
                    } else {
                        $error = 'Erro ao excluir projeto.';
                    }
                } catch (PDOException $e) {
                    $error = 'Funcionalidade de projetos em desenvolvimento. Erro: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Obter lista de projetos
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$where_clause = "WHERE user_id = ?";
$params = [$user_id];

if ($search) {
    $where_clause .= " AND project_name LIKE ?";
    $search_param = "%$search%";
    $params[] = $search_param;
}

if ($status_filter) {
    $where_clause .= " AND status = ?";
    $params[] = $status_filter;
}

// Simplified query without client table dependency
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dash_projects $where_clause");
    $stmt->execute($params);
    $total_projects = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT * FROM dash_projects $where_clause ORDER BY created_at DESC");
    $stmt->execute($params);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    // If dash_projects table doesn't exist, create empty results
    $projects = [];
    $total_projects = 0;
    $error = 'Tabela de projetos não encontrada. Funcionalidade em desenvolvimento.';
}

// Obter projeto para edição se solicitado
$edit_project = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM dash_projects WHERE id = ? AND user_id = ?");
        $stmt->execute([$_GET['edit'], $user_id]);
        $edit_project = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $edit_project = null;
    }
}

include __DIR__ . '/vision/includes/head.php';
?>

<?php include __DIR__ . '/vision/includes/header.php'; ?>

<?php include __DIR__ . '/vision/includes/sidebar.php'; ?>

<main class="main-content">
    <!-- Hero Section -->
    <section class="glass-hero">
        <h1><i class="fas fa-folder-open" style="margin-right: 10px;"></i>Gerenciar Projetos</h1>
        <p>Crie e acompanhe seus projetos de tradução de forma organizada e profissional.</p>
    </section>

    <!-- Mensagens -->
    <?php if ($message): ?>
        <div class="alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Formulário de Adicionar/Editar Projeto -->
    <div class="vision-form">
        <h2 style="font-size: 1.3rem; margin-bottom: 25px;">
            <i class="fas fa-<?php echo $edit_project ? 'edit' : 'plus'; ?>"></i>
            <?php echo $edit_project ? 'Editar Projeto' : 'Adicionar Novo Projeto'; ?>
        </h2>
        
        <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <input type="hidden" name="action" value="<?php echo $edit_project ? 'edit_project' : 'add_project'; ?>">
            <?php if ($edit_project): ?>
                <input type="hidden" name="project_id" value="<?php echo $edit_project['id']; ?>">
            <?php endif; ?>
            
            <div style="grid-column: span 2;">
                <label for="project_name">Nome do Projeto *</label>
                <input type="text" name="project_name" id="project_name" required
                       value="<?php echo htmlspecialchars($edit_project['project_name'] ?? ''); ?>">
            </div>
            
            <div>
                <label for="client_name">Nome do Cliente</label>
                <input type="text" name="client_name" id="client_name"
                       value="<?php echo htmlspecialchars($edit_project['client_name'] ?? ''); ?>"
                       placeholder="Ex: Empresa ABC, João Silva, etc.">
            </div>
            
            <div>
                <label for="source_language">Idioma de Origem *</label>
                <select name="source_language" id="source_language" required>
                    <option value="">Selecione</option>
                    <option value="pt-BR" <?php echo ($edit_project && $edit_project['source_language'] == 'pt-BR') ? 'selected' : ''; ?>>Português (Brasil)</option>
                    <option value="en-US" <?php echo ($edit_project && $edit_project['source_language'] == 'en-US') ? 'selected' : ''; ?>>Inglês (EUA)</option>
                    <option value="es-ES" <?php echo ($edit_project && $edit_project['source_language'] == 'es-ES') ? 'selected' : ''; ?>>Espanhol (Espanha)</option>
                    <option value="fr-FR" <?php echo ($edit_project && $edit_project['source_language'] == 'fr-FR') ? 'selected' : ''; ?>>Francês</option>
                    <option value="de-DE" <?php echo ($edit_project && $edit_project['source_language'] == 'de-DE') ? 'selected' : ''; ?>>Alemão</option>
                    <option value="it-IT" <?php echo ($edit_project && $edit_project['source_language'] == 'it-IT') ? 'selected' : ''; ?>>Italiano</option>
                </select>
            </div>
            
            <div>
                <label for="target_language">Idioma de Destino *</label>
                <select name="target_language" id="target_language" required>
                    <option value="">Selecione</option>
                    <option value="pt-BR" <?php echo ($edit_project && $edit_project['target_language'] == 'pt-BR') ? 'selected' : ''; ?>>Português (Brasil)</option>
                    <option value="en-US" <?php echo ($edit_project && $edit_project['target_language'] == 'en-US') ? 'selected' : ''; ?>>Inglês (EUA)</option>
                    <option value="es-ES" <?php echo ($edit_project && $edit_project['target_language'] == 'es-ES') ? 'selected' : ''; ?>>Espanhol (Espanha)</option>
                    <option value="fr-FR" <?php echo ($edit_project && $edit_project['target_language'] == 'fr-FR') ? 'selected' : ''; ?>>Francês</option>
                    <option value="de-DE" <?php echo ($edit_project && $edit_project['target_language'] == 'de-DE') ? 'selected' : ''; ?>>Alemão</option>
                    <option value="it-IT" <?php echo ($edit_project && $edit_project['target_language'] == 'it-IT') ? 'selected' : ''; ?>>Italiano</option>
                </select>
            </div>
            
            <div>
                <label for="service_type">Tipo de Serviço *</label>
                <select name="service_type" id="service_type" required>
                    <option value="translation" <?php echo ($edit_project && $edit_project['service_type'] == 'translation') ? 'selected' : ''; ?>>Tradução</option>
                    <option value="revision" <?php echo ($edit_project && $edit_project['service_type'] == 'revision') ? 'selected' : ''; ?>>Revisão</option>
                    <option value="proofreading" <?php echo ($edit_project && $edit_project['service_type'] == 'proofreading') ? 'selected' : ''; ?>>Revisão de Texto</option>
                    <option value="localization" <?php echo ($edit_project && $edit_project['service_type'] == 'localization') ? 'selected' : ''; ?>>Localização</option>
                    <option value="transcription" <?php echo ($edit_project && $edit_project['service_type'] == 'transcription') ? 'selected' : ''; ?>>Transcrição</option>
                    <option value="other" <?php echo ($edit_project && $edit_project['service_type'] == 'other') ? 'selected' : ''; ?>>Outro</option>
                </select>
            </div>
            
            <div>
                <label for="word_count">Contagem de Palavras</label>
                <input type="number" name="word_count" id="word_count" min="0"
                       value="<?php echo $edit_project['word_count'] ?? ''; ?>"
                       onchange="calculateTotal()">
            </div>
            
            <div>
                <label for="rate_per_word">Taxa por Palavra (R$)</label>
                <input type="number" name="rate_per_word" id="rate_per_word" min="0" step="0.01"
                       value="<?php echo $edit_project['rate_per_word'] ?? ''; ?>"
                       onchange="calculateTotal()">
            </div>
            
            <div>
                <label for="character_count">Contagem de Caracteres</label>
                <input type="number" name="character_count" id="character_count" min="0"
                       value="<?php echo $edit_project['character_count'] ?? ''; ?>"
                       onchange="calculateTotal()">
            </div>
            
            <div>
                <label for="rate_per_character">Taxa por Caractere (R$)</label>
                <input type="number" name="rate_per_character" id="rate_per_character" min="0" step="0.01"
                       value="<?php echo $edit_project['rate_per_character'] ?? ''; ?>"
                       onchange="calculateTotal()">
            </div>
            
            <div>
                <label for="total_amount">Valor Total (R$)</label>
                <input type="text" id="total_amount" readonly 
                       style="background: rgba(255,255,255,0.03); cursor: not-allowed;">
            </div>
            
            <div>
                <label for="status">Status</label>
                <select name="status" id="status">
                    <option value="pending" <?php echo ($edit_project && $edit_project['status'] == 'pending') ? 'selected' : ''; ?>>Pendente</option>
                    <option value="in_progress" <?php echo ($edit_project && $edit_project['status'] == 'in_progress') ? 'selected' : ''; ?>>Em Andamento</option>
                    <option value="completed" <?php echo ($edit_project && $edit_project['status'] == 'completed') ? 'selected' : ''; ?>>Concluído</option>
                    <option value="on_hold" <?php echo ($edit_project && $edit_project['status'] == 'on_hold') ? 'selected' : ''; ?>>Pausado</option>
                    <option value="cancelled" <?php echo ($edit_project && $edit_project['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelado</option>
                </select>
            </div>
            
            <div>
                <label for="priority">Prioridade</label>
                <select name="priority" id="priority">
                    <option value="low" <?php echo ($edit_project && $edit_project['priority'] == 'low') ? 'selected' : ''; ?>>Baixa</option>
                    <option value="medium" <?php echo ($edit_project && $edit_project['priority'] == 'medium') ? 'selected' : ''; ?>>Média</option>
                    <option value="high" <?php echo ($edit_project && $edit_project['priority'] == 'high') ? 'selected' : ''; ?>>Alta</option>
                    <option value="urgent" <?php echo ($edit_project && $edit_project['priority'] == 'urgent') ? 'selected' : ''; ?>>Urgente</option>
                </select>
            </div>
            
            <div>
                <label for="start_date">Data de Início</label>
                <input type="date" name="start_date" id="start_date"
                       value="<?php echo $edit_project['start_date'] ?? ''; ?>">
            </div>
            
            <div>
                <label for="deadline">Prazo de Entrega</label>
                <input type="date" name="deadline" id="deadline"
                       value="<?php echo $edit_project['deadline'] ?? ''; ?>">
            </div>
            
            <div style="grid-column: span 2;">
                <label for="project_description">Descrição do Projeto</label>
                <textarea name="project_description" id="project_description" rows="3"><?php echo htmlspecialchars($edit_project['project_description'] ?? ''); ?></textarea>
            </div>
            
            <div style="grid-column: span 2;">
                <label for="notes">Observações</label>
                <textarea name="notes" id="notes" rows="3"><?php echo htmlspecialchars($edit_project['notes'] ?? ''); ?></textarea>
            </div>
            
            <div style="grid-column: span 2; display: flex; gap: 15px;">
                <button type="submit" class="cta-btn">
                    <i class="fas fa-<?php echo $edit_project ? 'save' : 'plus'; ?>"></i>
                    <?php echo $edit_project ? 'Atualizar Projeto' : 'Adicionar Projeto'; ?>
                </button>
                <?php if ($edit_project): ?>
                    <a href="projects.php" class="cta-btn" style="background: rgba(255,255,255,0.1);">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Lista de Projetos -->
    <div class="vision-table">
        <div style="display: flex; align-items: center; justify-content: space-between; padding: 20px; border-bottom: 1px solid var(--glass-border);">
            <h2 style="font-size: 1.3rem; margin: 0;">
                <i class="fas fa-list"></i> Lista de Projetos
            </h2>
            
            <!-- Filtros -->
            <div style="display: flex; gap: 10px;">
                <form method="GET" style="display: flex; gap: 10px;">
                    <input type="text" name="search" placeholder="Buscar projetos..."
                           value="<?php echo htmlspecialchars($search); ?>"
                           style="padding: 8px 12px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); border-radius: 8px; color: white; font-size: 0.9rem;">
                    <select name="status" style="padding: 8px 12px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); border-radius: 8px; color: white; font-size: 0.9rem;">
                        <option value="">Todos os status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pendente</option>
                        <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>Em Andamento</option>
                        <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Concluído</option>
                        <option value="on_hold" <?php echo $status_filter == 'on_hold' ? 'selected' : ''; ?>>Pausado</option>
                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                    <button type="submit" class="cta-btn" style="font-size: 0.9rem; padding: 8px 15px;">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if ($search || $status_filter): ?>
                        <a href="projects.php" class="cta-btn" style="background: rgba(255,255,255,0.1); font-size: 0.9rem; padding: 8px 15px;">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if (empty($projects)): ?>
            <div style="text-align: center; padding: 60px 20px;">
                <i class="fas fa-folder-open" style="font-size: 4rem; color: #666; margin-bottom: 20px;"></i>
                <h3 style="font-size: 1.3rem; margin-bottom: 10px;">Nenhum projeto encontrado</h3>
                <p style="color: #ccc;">
                    <?php echo ($search || $status_filter) ? 'Nenhum projeto encontrado com os critérios de busca.' : 'Nenhum projeto cadastrado ainda.'; ?>
                </p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Projeto</th>
                        <th>Cliente</th>
                        <th>Idiomas</th>
                        <th>Status</th>
                        <th>Valor</th>
                        <th>Prazo</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                        <tr>
                            <td>
                                <div>
                                    <p style="font-weight: 600; margin-bottom: 5px;">
                                        <?php echo htmlspecialchars($project['project_name']); ?>
                                    </p>
                                    <p style="color: #ccc; font-size: 0.9rem;">
                                        <?php echo ucfirst($project['service_type']); ?>
                                    </p>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($project['client_name'] ?? 'Cliente não informado'); ?></td>
                            <td style="font-size: 0.9rem;">
                                <?php echo $project['source_language']; ?> → <?php echo $project['target_language']; ?>
                            </td>
                            <td>
                                <span class="tag <?php 
                                    switch($project['status']) {
                                        case 'completed': echo 'style="background: rgba(46, 204, 113, 0.25); color: #2ecc71;"'; break;
                                        case 'in_progress': echo 'style="background: rgba(52, 152, 219, 0.25); color: #3498db;"'; break;
                                        case 'pending': echo 'style="background: rgba(241, 196, 15, 0.25); color: #f1c40f;"'; break;
                                        case 'on_hold': echo 'style="background: rgba(230, 126, 34, 0.25); color: #e67e22;"'; break;
                                        case 'cancelled': echo 'style="background: rgba(231, 76, 60, 0.25); color: #e74c3c;"'; break;
                                        default: echo 'style="background: rgba(149, 165, 166, 0.25); color: #95a5a6;"';
                                    }
                                ?>">
                                    <?php 
                                    $status_labels = [
                                        'pending' => 'Pendente',
                                        'in_progress' => 'Em Andamento',
                                        'completed' => 'Concluído',
                                        'cancelled' => 'Cancelado',
                                        'on_hold' => 'Pausado'
                                    ];
                                    echo $status_labels[$project['status']] ?? $project['status'];
                                    ?>
                                </span>
                            </td>
                            <td>R$ <?php echo number_format($project['total_amount'], 2, ',', '.'); ?></td>
                            <td>
                                <?php if ($project['deadline']): ?>
                                    <?php echo date('d/m/Y', strtotime($project['deadline'])); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <a href="?edit=<?php echo $project['id']; ?>" 
                                       class="cta-btn" style="font-size: 0.8rem; padding: 6px 12px; background: var(--brand-purple);">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este projeto?')">
                                        <input type="hidden" name="action" value="delete_project">
                                        <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                        <button type="submit" class="cta-btn" style="font-size: 0.8rem; padding: 6px 12px; background: #e74c3c;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

<script>
function calculateTotal() {
    const wordCount = parseFloat(document.getElementById('word_count').value) || 0;
    const ratePerWord = parseFloat(document.getElementById('rate_per_word').value) || 0;
    const characterCount = parseFloat(document.getElementById('character_count').value) || 0;
    const ratePerCharacter = parseFloat(document.getElementById('rate_per_character').value) || 0;
    
    const wordTotal = wordCount * ratePerWord;
    const charTotal = characterCount * ratePerCharacter;
    const total = wordTotal + charTotal;
    
    document.getElementById('total_amount').value = 'R$ ' + total.toFixed(2).replace('.', ',');
}

// Calcular total ao carregar a página se estiver editando
document.addEventListener('DOMContentLoaded', function() {
    calculateTotal();
});
</script>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>
