<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Verificação de autenticação
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

$page_title = 'Mapeamento de Palestras Hotmart';

// Carregar dados
$hotmart_lectures = require __DIR__ . '/data_hotmart.php';
$system_lectures_ids = require __DIR__ . '/data_lectures.php';

// Buscar detalhes completos das palestras do sistema (TODOS os dados disponíveis)
$system_lectures = [];
try {
    $ids = array_column($system_lectures_ids, 'id');
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT id, title, speaker, duration_minutes, created_at, category, tags, level, 
               description, is_featured, language, hotmart_page_id, hotmart_lesson_id, 
               hotmart_module_id
        FROM lectures 
        WHERE id IN ($placeholders)
    ");
    $stmt->execute($ids);
    $system_lectures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback para dados básicos se houver erro
    $system_lectures = $system_lectures_ids;
}

// Ordenar alfabeticamente
sort($hotmart_lectures);
usort($system_lectures, function($a, $b) {
    return strcasecmp($a['title'], $b['title']);
});

// Buscar mapeamentos existentes
$existing_mappings = [];
$mapped_hotmart_titles = [];
$mapped_lecture_ids = [];
$hotmart_with_data = []; // Array para armazenar dados extras das palestras Hotmart
try {
    $stmt = $pdo->query("SELECT id, hotmart_title, lecture_id, lecture_title, hotmart_page_id FROM hotmart_lecture_mapping ORDER BY hotmart_title");
    $existing_mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Criar arrays de títulos/IDs já mapeados para fácil verificação
    foreach ($existing_mappings as $mapping) {
        $mapped_hotmart_titles[] = $mapping['hotmart_title'];
        $mapped_lecture_ids[] = $mapping['lecture_id'];
    }
} catch (PDOException $e) {
    // Silent fail - não há mapeamentos ainda
}

// Contar itens disponíveis
$available_hotmart = count($hotmart_lectures) - count($mapped_hotmart_titles);
$available_lectures = count($system_lectures) - count($mapped_lecture_ids);
$total_mappings = count($existing_mappings);

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<style>
    /* Estilos específicos para a página de mapeamento */
    .mapping-page-container {
        padding: 2rem;
        max-width: 100%;
    }

    .mapping-hero {
        background: linear-gradient(135deg, rgba(123, 97, 255, 0.1), rgba(72, 61, 139, 0.1));
        padding: 2rem;
        border-radius: 16px;
        margin-bottom: 2rem;
        border: 1px solid rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
    }

    .mapping-hero h1 {
        font-size: 2rem;
        font-weight: 700;
        margin: 0 0 0.5rem;
        background: linear-gradient(135deg, #FFD700, #FFA500);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .mapping-hero p {
        margin: 0;
        opacity: 0.8;
        font-size: 1.1rem;
    }

    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: rgba(255, 255, 255, 0.05);
        padding: 1.5rem;
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        text-align: center;
        backdrop-filter: blur(10px);
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        background: linear-gradient(135deg, #FFD700, #FFA500);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin: 0.5rem 0;
    }

    .stat-label {
        font-size: 0.9rem;
        opacity: 0.7;
        margin: 0;
    }

    .mapping-container {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    @media (max-width: 1400px) {
        .mapping-container {
            grid-template-columns: 1fr;
        }
    }

    .lecture-column {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        max-height: 80vh;
        backdrop-filter: blur(10px);
    }

    .column-title {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .column-title i {
        color: #FFD700;
    }

    .filter-buttons {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .btn-filter {
        flex: 1;
        padding: 0.5rem;
        font-size: 0.85rem;
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        background: rgba(255, 255, 255, 0.05);
        color: #fff;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-filter:hover {
        background: rgba(255, 255, 255, 0.1);
        transform: translateY(-2px);
    }

    .search-box {
        width: 100%;
        padding: 0.75rem;
        margin-bottom: 1rem;
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        background: rgba(255, 255, 255, 0.05);
        color: #fff;
        font-size: 0.95rem;
    }

    .search-box::placeholder {
        color: rgba(255, 255, 255, 0.5);
    }

    .manual-add-section {
        margin: 1rem 0;
        padding: 1rem;
        background: rgba(33, 150, 243, 0.1);
        border-radius: 8px;
        border: 2px dashed rgba(33, 150, 243, 0.5);
    }

    .manual-add-section > div:first-child {
        font-size: 0.9rem;
        font-weight: 600;
        color: #64B5F6;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .input-group {
        display: flex;
        gap: 0.5rem;
    }

    .input-group input {
        flex: 1;
        padding: 0.5rem;
        border-radius: 6px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        background: rgba(255, 255, 255, 0.05);
        color: #fff;
        font-size: 0.9rem;
    }

    .input-group button {
        padding: 0.5rem 1rem;
        border-radius: 6px;
        border: none;
        background: linear-gradient(135deg, #2196F3, #1976D2);
        color: #fff;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .input-group button:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(33, 150, 243, 0.4);
    }

    #hotmart-list, #system-list, #mappings-list {
        flex: 1;
        overflow-y: auto;
        padding-right: 0.5rem;
    }

    .lecture-item, .mapping-item {
        padding: 1rem;
        margin-bottom: 0.75rem;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .lecture-item:hover {
        background: rgba(255, 255, 255, 0.1);
        transform: translateX(5px);
    }

    .lecture-item.selected {
        background: linear-gradient(135deg, rgba(123, 97, 255, 0.3), rgba(72, 61, 139, 0.3));
        border-color: #7B61FF;
        transform: translateX(5px);
    }

    .lecture-item.mapped {
        background: rgba(76, 175, 80, 0.2);
        border-color: rgba(76, 175, 80, 0.5);
        opacity: 0.6;
        cursor: not-allowed;
    }

    .lecture-item.mapped::after {
        content: ' ✓';
        color: #4CAF50;
        font-weight: 700;
        font-size: 1.2rem;
    }

    .lecture-metadata {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.5rem;
        font-size: 0.8rem;
    }

    .meta-item {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.5rem;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 4px;
        font-size: 0.75rem;
    }

    .meta-item i {
        font-size: 0.7rem;
        opacity: 0.8;
    }

    .btn-map {
        padding: 0.75rem;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .btn-success {
        background: linear-gradient(135deg, #4CAF50, #388E3C);
    }

    .btn-warning {
        background: linear-gradient(135deg, #FFC107, #F57C00);
        color: #000;
    }

    .btn-info {
        background: linear-gradient(135deg, #2196F3, #1976D2);
    }

    .btn-map:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(255, 255, 255, 0.2);
    }

    .btn-map:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }

    .mapping-content {
        margin-bottom: 0.75rem;
    }

    .mapping-title {
        font-weight: 600;
        margin-bottom: 0.25rem;
    }

    .mapping-subtitle {
        font-size: 0.85rem;
        opacity: 0.7;
    }

    .btn-delete {
        padding: 0.5rem 1rem;
        background: linear-gradient(135deg, #f44336, #d32f2f);
        border: none;
        border-radius: 6px;
        color: #fff;
        cursor: pointer;
        font-size: 0.85rem;
        transition: all 0.3s ease;
    }

    .btn-delete:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(244, 67, 54, 0.4);
    }

    .alert {
        padding: 1rem 1.5rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .alert-success {
        background: rgba(76, 175, 80, 0.2);
        border: 1px solid rgba(76, 175, 80, 0.5);
        color: #81C784;
    }

    .alert-danger {
        background: rgba(244, 67, 54, 0.2);
        border: 1px solid rgba(244, 67, 54, 0.5);
        color: #E57373;
    }

    /* Scrollbar personalizada */
    #hotmart-list::-webkit-scrollbar,
    #system-list::-webkit-scrollbar,
    #mappings-list::-webkit-scrollbar {
        width: 6px;
    }

    #hotmart-list::-webkit-scrollbar-track,
    #system-list::-webkit-scrollbar-track,
    #mappings-list::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 3px;
    }

    #hotmart-list::-webkit-scrollbar-thumb,
    #system-list::-webkit-scrollbar-thumb,
    #mappings-list::-webkit-scrollbar-thumb {
        background: rgba(255, 215, 0, 0.5);
        border-radius: 3px;
    }

    #hotmart-list::-webkit-scrollbar-thumb:hover,
    #system-list::-webkit-scrollbar-thumb:hover,
    #mappings-list::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 215, 0, 0.7);
    }
</style>

<div class="main-content">
    <div class="mapping-page-container">
        <!-- Hero Section -->
        <div class="mapping-hero">
            <h1><i class="fas fa-link"></i> Mapeamento Manual de Palestras</h1>
            <p>Associe palestras da Hotmart com palestras do sistema interno para geração automática de certificados</p>
        </div>

        <!-- Alert Container -->
        <div id="alert-container"></div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <i class="fas fa-store" style="font-size: 2rem; color: #E91E63;"></i>
                <div class="stat-number" id="hotmartCount"><?php echo $available_hotmart; ?> / <?php echo count($hotmart_lectures); ?></div>
                <p class="stat-label">Hotmart Disponíveis</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-database" style="font-size: 2rem; color: #2196F3;"></i>
                <div class="stat-number" id="systemCount"><?php echo $available_lectures; ?> / <?php echo count($system_lectures); ?></div>
                <p class="stat-label">Sistema Disponíveis</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-check-circle" style="font-size: 2rem; color: #4CAF50;"></i>
                <div class="stat-number" id="mappingsCount"><?php echo $total_mappings; ?></div>
                <p class="stat-label">Associações Criadas</p>
            </div>
        </div>

        <!-- Mapping Interface -->
        <div class="mapping-container">
            <!-- Palestras Hotmart -->
            <div class="lecture-column">
                <div class="column-title">
                    <i class="fas fa-store"></i> Palestras Hotmart
                </div>
                <div class="filter-buttons">
                    <button class="btn-filter" id="filterHotmart">
                        <i class="fas fa-filter"></i> Apenas disponíveis
                    </button>
                    <button class="btn-filter" id="showAllHotmart" style="display:none;">
                        <i class="fas fa-list"></i> Mostrar todas
                    </button>
                </div>
                <input type="text" class="search-box" id="searchHotmart" placeholder="🔍 Buscar palestra Hotmart...">
                
                <!-- Campo para adicionar palestra manualmente -->
                <div class="manual-add-section">
                    <div>
                        <i class="fas fa-plus-circle"></i> Adicionar Palestra Manualmente
                    </div>
                    <div class="input-group">
                        <input type="text" id="manualHotmartTitle" placeholder="Digite o título da palestra Hotmart...">
                        <button type="button" id="btnAddManualHotmart">
                            <i class="fas fa-plus"></i> Adicionar
                        </button>
                    </div>
                </div>
                
                <div id="hotmart-list">
                    <?php foreach ($hotmart_lectures as $index => $lecture): 
                        $is_mapped = in_array($lecture, $mapped_hotmart_titles);
                    ?>
                        <div class="lecture-item <?php echo $is_mapped ? 'mapped' : ''; ?>" 
                             data-title="<?php echo htmlspecialchars($lecture); ?>" 
                             data-index="<?php echo $index; ?>"
                             data-mapped="<?php echo $is_mapped ? '1' : '0'; ?>">
                            <div><?php echo htmlspecialchars($lecture); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Palestras do Sistema -->
            <div class="lecture-column">
                <div class="column-title">
                    <i class="fas fa-database"></i> Palestras do Sistema
                </div>
                <div class="filter-buttons">
                    <button class="btn-filter" id="filterSystem">
                        <i class="fas fa-filter"></i> Apenas disponíveis
                    </button>
                    <button class="btn-filter" id="showAllSystem" style="display:none;">
                        <i class="fas fa-list"></i> Mostrar todas
                    </button>
                </div>
                <input type="text" class="search-box" id="searchSystem" placeholder="🔍 Buscar palestra do sistema...">
                <div id="system-list">
                    <?php foreach ($system_lectures as $lecture): 
                        $is_mapped = in_array($lecture['id'], $mapped_lecture_ids);
                    ?>
                        <div class="lecture-item <?php echo $is_mapped ? 'mapped' : ''; ?>" 
                             data-id="<?php echo htmlspecialchars($lecture['id']); ?>" 
                             data-title="<?php echo htmlspecialchars($lecture['title']); ?>"
                             data-mapped="<?php echo $is_mapped ? '1' : '0'; ?>">
                            <div><?php echo htmlspecialchars($lecture['title']); ?></div>
                            
                            <?php if (!empty($lecture['duration_minutes']) || !empty($lecture['speaker'])): ?>
                                <div class="lecture-metadata">
                                    <?php if (!empty($lecture['duration_minutes'])): ?>
                                        <span class="meta-item">
                                            <i class="fas fa-clock"></i> <?php echo $lecture['duration_minutes']; ?> min
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($lecture['speaker'])): ?>
                                        <span class="meta-item">
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($lecture['speaker']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Associações Criadas -->
            <div class="lecture-column">
                <div class="column-title">
                    <i class="fas fa-check-circle"></i> Associações Criadas
                </div>
                <button class="btn-map btn-success w-100 mb-2" id="btnAssociate" disabled>
                    <i class="fas fa-link"></i> Associar Selecionadas
                </button>
                <button class="btn-map btn-warning w-100 mb-2" id="btnQuickAssociate">
                    <i class="fas fa-bolt"></i> Associação Rápida
                </button>
                <a href="verificar_certificados_mapeados.php" class="btn-map btn-info w-100 mb-2" style="text-decoration: none;">
                    <i class="fas fa-certificate"></i> Verificar Certificados
                </a>
                <div id="mappings-list">
                    <?php if (empty($existing_mappings)): ?>
                        <p style="text-align: center; opacity: 0.5; margin-top: 2rem;">Nenhuma associação criada ainda.</p>
                    <?php else: ?>
                        <?php foreach ($existing_mappings as $mapping): ?>
                            <div class="mapping-item" data-mapping-id="<?php echo $mapping['id']; ?>">
                                <div class="mapping-content">
                                    <div class="mapping-title">
                                        <i class="fas fa-store" style="color: #E91E63;"></i> <?php echo htmlspecialchars($mapping['hotmart_title']); ?>
                                    </div>
                                    <div class="mapping-subtitle">
                                        <i class="fas fa-arrow-down" style="color: #FFD700;"></i>
                                    </div>
                                    <div class="mapping-subtitle">
                                        <i class="fas fa-database" style="color: #2196F3;"></i> <?php echo htmlspecialchars($mapping['lecture_title']); ?>
                                    </div>
                                </div>
                                <button class="btn-delete" onclick="deleteMapping('<?php echo $mapping['id']; ?>', '<?php echo htmlspecialchars($mapping['hotmart_title']); ?>', '<?php echo htmlspecialchars($mapping['lecture_id']); ?>')">
                                    <i class="fas fa-trash"></i> Deletar
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Associação Rápida -->
<div id="quickAssociateModal" style="display:none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.8); backdrop-filter: blur(5px);">
    <div style="background: linear-gradient(135deg, rgba(30, 30, 30, 0.95), rgba(20, 20, 20, 0.95)); margin: 5% auto; padding: 0; border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 16px; width: 90%; max-width: 600px; box-shadow: 0 8px 32px rgba(0,0,0,0.5);">
        <!-- Header do Modal -->
        <div style="background: linear-gradient(135deg, #7B61FF, #483D8B); color: white; padding: 2rem; border-radius: 16px 16px 0 0; position: relative;">
            <h3 style="margin: 0; font-size: 1.5em;">
                <i class="fas fa-bolt"></i> Associação Rápida
            </h3>
            <p style="margin: 0.5rem 0 0 0; font-size: 0.9em; opacity: 0.9;">
                Crie uma associação completa em uma única etapa
            </p>
            <button id="closeQuickModal" style="position: absolute; top: 20px; right: 20px; background: transparent; border: none; color: white; font-size: 1.5em; cursor: pointer; opacity: 0.8; transition: opacity 0.3s;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Corpo do Modal -->
        <div style="padding: 2rem;">
            <form id="quickAssociateForm">
                <!-- Campo Hotmart -->
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #fff;">
                        <i class="fas fa-store" style="color: #E91E63;"></i> Palestra Hotmart
                    </label>
                    <input type="text" 
                           id="quickHotmartTitle" 
                           placeholder="Digite o título completo da palestra Hotmart..."
                           required
                           style="width: 100%; padding: 0.75rem; font-size: 1em; border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 8px; background: rgba(255, 255, 255, 0.05); color: #fff;">
                    <small style="color: rgba(255, 255, 255, 0.6); font-size: 0.85em; display: block; margin-top: 0.5rem;">
                        <i class="fas fa-info-circle"></i> Digite exatamente como aparece na Hotmart
                    </small>
                </div>
                
                <!-- Select de Palestra do Sistema -->
                <div style="margin-bottom: 2rem;">
                    <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #fff;">
                        <i class="fas fa-database" style="color: #2196F3;"></i> Palestra do Sistema
                    </label>
                    <select id="quickLectureSelect" 
                            required
                            style="width: 100%; padding: 0.75rem; font-size: 1em; border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 8px; background: rgba(255, 255, 255, 0.05); color: #fff;">
                        <option value="">-- Selecione uma palestra --</option>
                        <?php foreach ($system_lectures as $lecture): 
                            $is_mapped = in_array($lecture['id'], $mapped_lecture_ids);
                            if ($is_mapped) continue;
                        ?>
                            <option value="<?php echo htmlspecialchars($lecture['id']); ?>">
                                <?php echo htmlspecialchars($lecture['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: rgba(255, 255, 255, 0.6); font-size: 0.85em; display: block; margin-top: 0.5rem;">
                        <i class="fas fa-filter"></i> Apenas palestras disponíveis (não mapeadas)
                    </small>
                </div>
                
                <!-- Botões -->
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" 
                            id="cancelQuickAssociate" 
                            style="padding: 0.75rem 1.5rem; font-weight: 600; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.2); background: rgba(255, 255, 255, 0.05); color: #fff; cursor: pointer;">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" 
                            style="padding: 0.75rem 1.5rem; font-weight: 600; border-radius: 8px; border: none; background: linear-gradient(135deg, #7B61FF, #483D8B); color: #fff; cursor: pointer;">
                        <i class="fas fa-check"></i> Criar Associação
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let selectedHotmart = null;
    let selectedSystem = null;

    // Seleção de palestras Hotmart
    document.querySelectorAll('#hotmart-list .lecture-item').forEach(item => {
        item.addEventListener('click', function() {
            if (this.dataset.mapped === '1') {
                showAlert('danger', 'Esta palestra Hotmart já foi associada!');
                return;
            }
            
            document.querySelectorAll('#hotmart-list .lecture-item').forEach(i => i.classList.remove('selected'));
            this.classList.add('selected');
            selectedHotmart = {
                title: this.dataset.title,
                index: this.dataset.index
            };
            updateAssociateButton();
        });
    });

    // Seleção de palestras do Sistema
    document.querySelectorAll('#system-list .lecture-item').forEach(item => {
        item.addEventListener('click', function() {
            if (this.dataset.mapped === '1') {
                showAlert('danger', 'Esta palestra do sistema já foi associada!');
                return;
            }
            
            document.querySelectorAll('#system-list .lecture-item').forEach(i => i.classList.remove('selected'));
            this.classList.add('selected');
            selectedSystem = {
                id: this.dataset.id,
                title: this.dataset.title
            };
            updateAssociateButton();
        });
    });

    // Atualizar botão de associar
    function updateAssociateButton() {
        const btn = document.getElementById('btnAssociate');
        if (selectedHotmart && selectedSystem) {
            btn.disabled = false;
        } else {
            btn.disabled = true;
        }
    }

    // Busca Hotmart
    document.getElementById('searchHotmart').addEventListener('input', function() {
        const search = this.value.toLowerCase();
        document.querySelectorAll('#hotmart-list .lecture-item').forEach(item => {
            const title = item.dataset.title.toLowerCase();
            item.style.display = title.includes(search) ? 'block' : 'none';
        });
    });

    // Busca Sistema
    document.getElementById('searchSystem').addEventListener('input', function() {
        const search = this.value.toLowerCase();
        document.querySelectorAll('#system-list .lecture-item').forEach(item => {
            const title = item.dataset.title.toLowerCase();
            item.style.display = title.includes(search) ? 'block' : 'none';
        });
    });

    // Filtros
    document.getElementById('filterHotmart').addEventListener('click', function() {
        document.querySelectorAll('#hotmart-list .lecture-item').forEach(item => {
            item.style.display = item.dataset.mapped === '0' ? 'block' : 'none';
        });
        this.style.display = 'none';
        document.getElementById('showAllHotmart').style.display = 'block';
    });

    document.getElementById('showAllHotmart').addEventListener('click', function() {
        document.querySelectorAll('#hotmart-list .lecture-item').forEach(item => {
            item.style.display = 'block';
        });
        this.style.display = 'none';
        document.getElementById('filterHotmart').style.display = 'block';
    });

    document.getElementById('filterSystem').addEventListener('click', function() {
        document.querySelectorAll('#system-list .lecture-item').forEach(item => {
            item.style.display = item.dataset.mapped === '0' ? 'block' : 'none';
        });
        this.style.display = 'none';
        document.getElementById('showAllSystem').style.display = 'block';
    });

    document.getElementById('showAllSystem').addEventListener('click', function() {
        document.querySelectorAll('#system-list .lecture-item').forEach(item => {
            item.style.display = 'block';
        });
        this.style.display = 'none';
        document.getElementById('filterSystem').style.display = 'block';
    });

    // Associar
    document.getElementById('btnAssociate').addEventListener('click', function() {
        if (!selectedHotmart || !selectedSystem) return;

        fetch('save_mapping_ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `hotmart_title=${encodeURIComponent(selectedHotmart.title)}&lecture_id=${encodeURIComponent(selectedSystem.id)}&lecture_title=${encodeURIComponent(selectedSystem.title)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', 'Associação criada com sucesso!');
                addMappingToList(data.mapping_id, selectedHotmart.title, selectedSystem.id, selectedSystem.title);
                markAsAssociated(selectedHotmart.title, selectedSystem.id);
                selectedHotmart = null;
                selectedSystem = null;
                document.querySelectorAll('.lecture-item.selected').forEach(i => i.classList.remove('selected'));
                updateAssociateButton();
                updateAvailableCounts();
            } else {
                showAlert('danger', data.message || 'Erro ao criar associação');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'Erro ao processar requisição');
        });
    });

    // Deletar associação
    function deleteMapping(id, hotmartTitle, lectureId) {
        if (!confirm('Deseja realmente deletar esta associação?')) return;

        fetch('delete_mapping_ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `mapping_id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', 'Associação deletada com sucesso!');
                document.querySelector(`[data-mapping-id="${id}"]`).remove();
                unmarkAsAssociated(hotmartTitle, lectureId);
                updateAvailableCounts();
            } else {
                showAlert('danger', data.message || 'Erro ao deletar associação');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'Erro ao processar requisição');
        });
    }

    // Adicionar à lista
    function addMappingToList(id, hotmartTitle, lectureId, lectureTitle) {
        const list = document.getElementById('mappings-list');
        const item = document.createElement('div');
        item.className = 'mapping-item';
        item.dataset.mappingId = id;
        item.innerHTML = `
            <div class="mapping-content">
                <div class="mapping-title">
                    <i class="fas fa-store" style="color: #E91E63;"></i> ${escapeHtml(hotmartTitle)}
                </div>
                <div class="mapping-subtitle">
                    <i class="fas fa-arrow-down" style="color: #FFD700;"></i>
                </div>
                <div class="mapping-subtitle">
                    <i class="fas fa-database" style="color: #2196F3;"></i> ${escapeHtml(lectureTitle)}
                </div>
            </div>
            <button class="btn-delete" onclick="deleteMapping('${id}', '${escapeHtml(hotmartTitle)}', '${lectureId}')">
                <i class="fas fa-trash"></i> Deletar
            </button>
        `;
        list.appendChild(item);
    }

    // Marcar como associado
    function markAsAssociated(hotmartTitle, lectureId) {
        document.querySelectorAll('#hotmart-list .lecture-item').forEach(item => {
            if (item.dataset.title === hotmartTitle) {
                item.classList.add('mapped');
                item.dataset.mapped = '1';
            }
        });
        document.querySelectorAll('#system-list .lecture-item').forEach(item => {
            if (item.dataset.id === lectureId) {
                item.classList.add('mapped');
                item.dataset.mapped = '1';
            }
        });
    }

    // Desmarcar como associado
    function unmarkAsAssociated(hotmartTitle, lectureId) {
        document.querySelectorAll('#hotmart-list .lecture-item').forEach(item => {
            if (item.dataset.title === hotmartTitle) {
                item.classList.remove('mapped');
                item.dataset.mapped = '0';
            }
        });
        document.querySelectorAll('#system-list .lecture-item').forEach(item => {
            if (item.dataset.id === lectureId) {
                item.classList.remove('mapped');
                item.dataset.mapped = '0';
            }
        });
    }

    // Atualizar contadores
    function updateAvailableCounts() {
        const hotmartAvailable = document.querySelectorAll('#hotmart-list .lecture-item[data-mapped="0"]').length;
        const systemAvailable = document.querySelectorAll('#system-list .lecture-item[data-mapped="0"]').length;
        const totalMappings = document.querySelectorAll('#mappings-list .mapping-item').length;
        
        document.getElementById('hotmartCount').textContent = `${hotmartAvailable} / <?php echo count($hotmart_lectures); ?>`;
        document.getElementById('systemCount').textContent = `${systemAvailable} / <?php echo count($system_lectures); ?>`;
        document.getElementById('mappingsCount').textContent = totalMappings;
    }

    // Mostrar alerta
    function showAlert(type, message) {
        const container = document.getElementById('alert-container');
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
            ${message}
        `;
        container.appendChild(alert);
        setTimeout(() => alert.remove(), 5000);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ============================================
    // ADICIONAR PALESTRA MANUALMENTE
    // ============================================
    document.getElementById('btnAddManualHotmart').addEventListener('click', function() {
        const titleInput = document.getElementById('manualHotmartTitle');
        const title = titleInput.value.trim();
        
        if (!title) {
            showAlert('danger', 'Por favor, digite o título da palestra Hotmart.');
            return;
        }
        
        const existingItems = document.querySelectorAll('#hotmart-list .lecture-item');
        for (let item of existingItems) {
            if (item.dataset.title.toLowerCase() === title.toLowerCase()) {
                showAlert('danger', 'Esta palestra já existe na lista!');
                return;
            }
        }
        
        const newItem = document.createElement('div');
        newItem.className = 'lecture-item';
        newItem.dataset.title = title;
        newItem.dataset.index = 'manual-' + Date.now();
        newItem.dataset.mapped = '0';
        newItem.dataset.manual = 'true';
        newItem.innerHTML = `
            <div>${escapeHtml(title)}</div>
            <div class="lecture-metadata">
                <span class="meta-item" style="background: #FF9800; color: white;">
                    <i class="fas fa-hand-pointer"></i> MANUAL
                </span>
            </div>
        `;
        
        const hotmartList = document.getElementById('hotmart-list');
        hotmartList.insertBefore(newItem, hotmartList.firstChild);
        
        titleInput.value = '';
        
        newItem.addEventListener('click', function() {
            if (this.dataset.mapped === '1') {
                showAlert('danger', 'Esta palestra já foi associada!');
                return;
            }
            
            document.querySelectorAll('#hotmart-list .lecture-item').forEach(item => {
                item.classList.remove('selected');
            });
            this.classList.add('selected');
            selectedHotmart = {
                title: this.dataset.title,
                index: this.dataset.index
            };
            updateAssociateButton();
        });
        
        newItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        newItem.style.backgroundColor = 'rgba(255, 235, 59, 0.2)';
        setTimeout(() => {
            newItem.style.backgroundColor = '';
        }, 2000);
        
        showAlert('success', `Palestra "${title}" adicionada! Agora você pode selecioná-la e associar.`);
        updateAvailableCounts();
    });
    
    // ============================================
    // ASSOCIAÇÃO RÁPIDA (MODAL)
    // ============================================
    document.getElementById('btnQuickAssociate').addEventListener('click', function() {
        document.getElementById('quickAssociateModal').style.display = 'block';
    });
    
    document.getElementById('closeQuickModal').addEventListener('click', function() {
        document.getElementById('quickAssociateModal').style.display = 'none';
    });
    
    document.getElementById('cancelQuickAssociate').addEventListener('click', function() {
        document.getElementById('quickAssociateModal').style.display = 'none';
    });
    
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('quickAssociateModal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
    
    document.getElementById('quickAssociateForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const hotmartTitle = document.getElementById('quickHotmartTitle').value.trim();
        const lectureId = document.getElementById('quickLectureSelect').value;
        
        if (!hotmartTitle) {
            showAlert('danger', 'Por favor, digite o título da palestra Hotmart.');
            return;
        }
        
        if (!lectureId) {
            showAlert('danger', 'Por favor, selecione uma palestra do sistema.');
            return;
        }
        
        const lectureSelect = document.getElementById('quickLectureSelect');
        const lectureTitle = lectureSelect.options[lectureSelect.selectedIndex].text;
        
        fetch('save_mapping_ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `hotmart_title=${encodeURIComponent(hotmartTitle)}&lecture_id=${encodeURIComponent(lectureId)}&lecture_title=${encodeURIComponent(lectureTitle)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', 'Associação criada com sucesso via modo rápido!');
                addMappingToList(data.mapping_id, hotmartTitle, lectureId, lectureTitle);
                markAsAssociated(hotmartTitle, lectureId);
                document.getElementById('quickHotmartTitle').value = '';
                document.getElementById('quickLectureSelect').value = '';
                document.getElementById('quickAssociateModal').style.display = 'none';
                updateAvailableCounts();
            } else {
                showAlert('danger', data.message || 'Erro ao criar associação');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'Erro ao processar requisição');
        });
    });
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>