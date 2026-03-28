<?php
/**
 * Relatório Time Tracker - Versão Final V3.1 (Correção de Sessão/Login)
 */

// --- 1. Configuração e Sessão (Simplificado e Robusto) ---
session_start(); // Inicia a sessão imediatamente

date_default_timezone_set('America/Sao_Paulo'); // Garante o fuso horário correto
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar se o usuário está logado DIRETAMENTE pela sessão
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    // Se não houver ID na sessão, redireciona para o login
    header('Location: /login.php');
    exit;
}

// --- 2. Inclusão de Dependências (Caminhos Corrigidos) ---
// Ajustando para subir 2 níveis (de /time_tracker/ para a raiz)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

// Tenta incluir as funções do dashboard se existirem (para evitar erros no header)
if (file_exists(__DIR__ . '/../../config/dash_functions.php')) {
    require_once __DIR__ . '/../../config/dash_functions.php';
}

// --- 3. Dados do Usuário e Preparação do Arquivo ---
try {
    $stmt_user = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $user_name = $stmt_user->fetchColumn() ?: 'Usuario';
} catch (PDOException $e) {
    $user_name = 'Usuario';
}

$user_name_safe = preg_replace('/[^a-zA-Z0-9à-úÀ-Ú ]/', '', $user_name); 
$date_str = date('d-m-Y');
$filename_export = "Relatório de horas - $user_name_safe - $date_str";

// --- 4. Filtros ---
$start_date = $_GET['start_date'] ?? date('Y-m-01'); 
$end_date   = $_GET['end_date'] ?? date('Y-m-d');    
$project_id = $_GET['project_id'] ?? '';             

// --- 5. Exportação CSV ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename_export . '.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para Excel
    
    fputcsv($output, ['Data', 'Hora Inicio', 'Projeto', 'Cliente', 'Tarefa', 'Descricao', 'Duracao (Seg)', 'Duracao Formatada']);
    
    $sql = "SELECT e.*, p.title as project_name, p.client_id, t.name as task_name FROM time_entries e LEFT JOIN dash_projects p ON e.project_id = p.id LEFT JOIN time_tasks t ON e.task_id = t.id WHERE e.user_id = ? AND e.is_running = 0 AND DATE(e.start_time) BETWEEN ? AND ?";
    $params = [$user_id, $start_date, $end_date];
    if ($project_id) { $sql .= " AND e.project_id = ?"; $params[] = $project_id; }
    $sql .= " ORDER BY e.start_time DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [date('d/m/Y', strtotime($row['start_time'])), date('H:i', strtotime($row['start_time'])), $row['project_name']?:'Sem Projeto', $row['client_id']?:'-', $row['task_name']?:'-', $row['description'], $row['duration'], gmdate('H:i:s', $row['duration'])]);
    }
    fclose($output); exit;
}

// --- 6. Helpers & Dados para a View ---
function formatSecs($s) { $h=floor($s/3600); $m=floor(($s%3600)/60); return sprintf("%02dh %02dm", $h, $m); }

$where = "WHERE e.user_id = ? AND e.is_running = 0 AND DATE(e.start_time) BETWEEN ? AND ?";
$params = [$user_id, $start_date, $end_date];
if ($project_id) { $where .= " AND e.project_id = ?"; $params[] = $project_id; }

// KPIs
$stmt = $pdo->prepare("SELECT SUM(duration) FROM time_entries e $where"); $stmt->execute($params); $totalPeriod = $stmt->fetchColumn()?:0;
$stmt = $pdo->prepare("SELECT COUNT(id) FROM time_entries e $where"); $stmt->execute($params); $totalEntries = $stmt->fetchColumn()?:0;
$stmt = $pdo->prepare("SELECT p.title, SUM(e.duration) as total FROM time_entries e JOIN dash_projects p ON e.project_id = p.id $where GROUP BY p.id ORDER BY total DESC LIMIT 1"); $stmt->execute($params); $topProject = $stmt->fetch();

// Lista
$stmt = $pdo->prepare("SELECT e.*, p.title as project_name, t.name as task_name FROM time_entries e LEFT JOIN dash_projects p ON e.project_id = p.id LEFT JOIN time_tasks t ON e.task_id = t.id $where ORDER BY e.start_time DESC LIMIT 200"); $stmt->execute($params); $entries = $stmt->fetchAll();

// Projetos (Para o Filtro)
$projects_list_stmt = $pdo->prepare("SELECT id, title FROM dash_projects WHERE user_id = ? ORDER BY title ASC"); 
$projects_list_stmt->execute([$user_id]); 
$projects_list = $projects_list_stmt->fetchAll();

$page_title = "Relatório de horas";

// Includes Visuais (Caminhos Corrigidos)
@include __DIR__ . '/../../vision/includes/head.php';
@include __DIR__ . '/../../vision/includes/header.php';
@include __DIR__ . '/../../vision/includes/sidebar.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<style>
    .main-content { padding-bottom: 50px; }

    /* Header Roxo */
    .profile-header-card {
        display: flex; align-items: center; justify-content: center; text-align: left;
        padding: 20px; gap: 20px; margin-bottom: 20px;
        background: linear-gradient(135deg, var(--brand-purple), #4a148c);
        border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    .header-icon-container { background: rgba(255,255,255,0.1); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

    /* Navegação (Botões Voltar) */
    .nav-buttons-container { margin-bottom: 25px; display: flex; gap: 15px; }
    .btn-nav-link {
        padding: 10px 20px; background: rgba(255,255,255,0.1); color: #fff; border-radius: 20px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; border: 1px solid rgba(255,255,255,0.1); transition: 0.2s; font-size: 0.9rem;
    }
    .btn-nav-link:hover { background: rgba(255,255,255,0.2); transform: translateY(-2px); }

    /* Filtros */
    .filter-card {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 20px;
        margin-bottom: 30px;
        backdrop-filter: blur(10px);
    }
    .filter-container { display: flex; align-items: flex-end; gap: 15px; flex-wrap: wrap; }
    .form-group { display: flex; flex-direction: column; gap: 5px; }
    .form-group label { color: #aaa; font-size: 0.85rem; font-weight: 500; margin-left: 2px; }
    
    .vision-input {
        height: 42px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 0 15px;
        color: #fff;
        outline: none;
        font-size: 0.9rem;
        box-sizing: border-box;
    }
    .vision-input:focus { border-color: #7c4dff; background: rgba(255,255,255,0.1); }
    option { background: #1a1a2e; color: #fff; }

    /* Botões de Ação */
    .action-group { display: flex; gap: 10px; margin-left: auto; align-items: flex-end; }
    
    .btn-fixed {
        height: 42px;
        padding: 0 20px;
        border-radius: 12px;
        border: 0;
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        text-decoration: none; white-space: nowrap; transition: 0.2s;
    }
    .btn-filter { background: linear-gradient(135deg, #7c4dff, #b388ff); color: #fff; }
    .btn-filter:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(124, 77, 255, 0.4); }
    
    .btn-secondary { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.1); color: #fff; }
    .btn-secondary:hover { background: rgba(255,255,255,0.15); }

    /* KPIs */
    .report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .stat-card { background: rgba(255, 255, 255, 0.05); padding: 25px; border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center; }
    .stat-label { font-size: 0.9rem; color: #aaa; margin-bottom: 10px; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .stat-label i { color: #FFD700; }
    .stat-value { font-size: 1.8rem; font-weight: 700; color: #fff; }

    /* Tabela */
    .report-table-container { background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px; padding: 25px; }
    .report-table { width: 100%; border-collapse: collapse; color: #fff; }
    .report-table th { text-align: left; padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.15); color: #fff; font-weight: 700; text-transform: uppercase; font-size: 0.85rem; opacity: 0.8; }
    .report-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.08); color: #ffffff; font-size: 0.95rem; }
    .report-table small { color: #dddddd !important; }
    .tag-project { background: rgba(124, 77, 255, 0.25); color: #d1c4e9; padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; display: inline-block; }

    /* PDF */
    #pdf-content-area { display: none; background: #fff; color: #333; font-family: 'Helvetica', sans-serif; padding: 40px; flex-direction: column; min-height: 260mm; }
    .pdf-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 15px; }
    .pdf-header h2 { margin: 0 0 5px 0; color: #555; font-size: 24px; }
    .pdf-header p { color: #777; margin: 0; font-size: 14px; }
    
    .pdf-kpis { display: flex; gap: 20px; margin-bottom: 40px; justify-content: space-between; }
    .pdf-card { border: 1px solid #ddd; border-radius: 10px; padding: 20px; flex: 1; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
    .pdf-card-label { color: #888; font-size: 12px; margin-bottom: 5px; text-transform: uppercase; font-weight: bold; }
    .pdf-card-value { color: #000; font-size: 24px; font-weight: bold; }
    
    .pdf-section-title { font-size: 18px; color: #555; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; font-weight: bold; }
    .pdf-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .pdf-table th { text-align: left; padding: 10px; border-bottom: 2px solid #333; color: #333; text-transform: uppercase; font-weight: bold; }
    .pdf-table td { padding: 10px; border-bottom: 1px solid #eee; color: #333; }
    .pdf-footer { margin-top: auto; padding-top: 15px; border-top: 1px solid #eee; text-align: center; font-size: 10px; color: #999; width: 100%; }
    
    /* Alinhamento Perfeito da Barra de Filtros */
    .filter-container { display: flex; align-items: flex-end !important; gap: 15px; flex-wrap: wrap; }
    .vision-input, .vision-select, .btn-fixed { height: 42px !important; min-height: 42px; line-height: 1; box-sizing: border-box; margin: 0; }
    .action-group { display: flex; flex-direction: row; gap: 10px; margin-left: auto; align-items: center; padding-bottom: 0; margin-bottom: 0; }
    .action-group label { display: none !important; }
    .filter-container .form-group { margin-bottom: 0; }
</style>

<div class="main-content">

    <div class="profile-header-card">
        <div class="header-icon-container"><i class="fas fa-chart-pie" style="font-size: 1.8rem; color: #fff;"></i></div>
        <div>
            <h2 style="margin: 0; font-size: 1.5rem; color: #fff; font-weight: 600;">Relatórios de tempo</h2>
            <p style="margin: 0; color: rgba(255, 255, 255, 0.8); font-size: 1rem;">Análise detalhada da sua produtividade</p>
        </div>
    </div>

    <div class="nav-buttons-container">
        <a href="../index.php" class="btn-nav-link"><i class="fas fa-arrow-left"></i> Voltar ao Dash-T101</a>
        <a href="time-tracker.php" class="btn-nav-link"><i class="fas fa-clock"></i> Voltar ao Time Tracker</a>
    </div>

    <div class="filter-card">
        <form method="GET" class="filter-container">
            <div class="form-group">
                <label>Início</label>
                <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="vision-input">
            </div>
            <div class="form-group">
                <label>Fim</label>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="vision-input">
            </div>
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label>Projeto</label>
                <select name="project_id" class="vision-input">
                    <option value="">Todos os projetos</option>
                    <?php foreach ($projects_list as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo ($project_id == $p['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="flex:0;">
                <label>&nbsp;</label>
                <button type="submit" class="btn-fixed btn-filter"><i class="fas fa-filter"></i> Filtrar</button>
            </div>

            <div class="action-group">
                <label>&nbsp;</label>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn-fixed btn-secondary" title="Baixar CSV">
                    <i class="fas fa-file-csv" style="color: #28a745;"></i> CSV
                </a>
                <button type="button" onclick="generatePDF()" class="btn-fixed btn-secondary" title="Download PDF">
                    <i class="fas fa-file-pdf" style="color: #dc3545;"></i> PDF
                </button>
            </div>
        </form>
    </div>

    <div class="report-grid">
        <div class="stat-card">
            <div class="stat-label"><i class="fas fa-clock"></i> Total no período</div>
            <div class="stat-value"><?php echo formatSecs($totalPeriod); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label"><i class="fas fa-list-check"></i> Entradas</div>
            <div class="stat-value"><?php echo $totalEntries; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label"><i class="fas fa-trophy"></i> Projeto principal</div>
            <div class="stat-value" style="font-size: 1.2rem;"><?php echo $topProject ? htmlspecialchars($topProject['title']) : '-'; ?></div>
        </div>
    </div>

    <div class="report-table-container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0; color:#fff;"><i class="fas fa-history"></i> Detalhamento</h3>
            <small style="color:#ccc;">Exibindo até 200 registros recentes</small>
        </div>
        <div style="overflow-x: auto;">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Projeto</th>
                        <th>Descrição</th>
                        <th>Duração</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($entries) > 0): ?>
                        <?php foreach ($entries as $row): ?>
                            <tr>
                                <td>
                                    <strong><?php echo date('d/m/Y', strtotime($row['start_time'])); ?></strong>
                                    <br><small><?php echo date('H:i', strtotime($row['start_time'])); ?></small>
                                </td>
                                <td>
                                    <?php if ($row['project_name']): ?>
                                        <span class="tag-project"><?php echo htmlspecialchars($row['project_name']); ?></span>
                                    <?php else: ?> <span style="color:#aaa">-</span> <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($row['description'] ?: 'Sem descrição'); ?>
                                    <?php if ($row['task_name']): ?> <br><small style="color:#b388ff;"><i class="fas fa-check"></i> <?php echo htmlspecialchars($row['task_name']); ?></small> <?php endif; ?>
                                </td>
                                <td style="font-family: monospace; font-size: 1.1rem;">
                                    <?php echo formatSecs($row['duration']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align: center; padding: 40px; color: #aaa;">Nenhum registro encontrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="nav-buttons-container">
        <a href="../index.php" class="btn-nav-link"><i class="fas fa-arrow-left"></i> Voltar ao Dash-T101</a>
        <a href="time-tracker.php" class="btn-nav-link"><i class="fas fa-clock"></i> Voltar ao Time Tracker</a>
    </div>

</div>

<div id="pdf-content-area">
    <div class="pdf-header">
        <h2>Relatório de Horas - Dash-T101</h2>
        <p>Período: <strong><?php echo date('d/m/Y', strtotime($start_date)); ?></strong> até <strong><?php echo date('d/m/Y', strtotime($end_date)); ?></strong></p>
        <p>Gerado para: <strong><?php echo htmlspecialchars($user_name); ?></strong></p>
    </div>

    <div class="pdf-kpis">
        <div class="pdf-card">
            <div class="pdf-card-label">● Total no período</div>
            <div class="pdf-card-value"><?php echo formatSecs($totalPeriod); ?></div>
        </div>
        <div class="pdf-card">
            <div class="pdf-card-label">≡ Entradas</div>
            <div class="pdf-card-value"><?php echo $totalEntries; ?></div>
        </div>
        <div class="pdf-card">
            <div class="pdf-card-label">🏆 Projeto principal</div>
            <div class="pdf-card-value" style="font-size:18px;"><?php echo $topProject ? htmlspecialchars($topProject['title']) : '-'; ?></div>
        </div>
    </div>

    <div class="pdf-section-title">↺ Detalhamento</div>

    <table class="pdf-table">
        <thead>
            <tr>
                <th>Data</th>
                <th>Projeto</th>
                <th>Descrição</th>
                <th style="text-align:right;">Duração</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($entries as $row): ?>
            <tr>
                <td class="pdf-row-highlight">
                    <?php echo date('d/m/Y', strtotime($row['start_time'])); ?>
                    <span class="pdf-small"><?php echo date('H:i', strtotime($row['start_time'])); ?></span>
                </td>
                <td class="pdf-row-highlight"><?php echo htmlspecialchars($row['project_name'] ?: '-'); ?></td>
                <td>
                    <?php echo htmlspecialchars($row['description'] ?: '-'); ?>
                    <?php if ($row['task_name']): ?> <br><span class="pdf-small">Tarefa: <?php echo htmlspecialchars($row['task_name']); ?></span> <?php endif; ?>
                </td>
                <td style="text-align:right; font-family:monospace; font-weight:bold;">
                    <?php echo formatSecs($row['duration']); ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="pdf-footer">
        Relatório gerado pelo Dash-T101, da Translators101
    </div>
</div>

<script>
function generatePDF() {
    const element = document.getElementById('pdf-content-area');
    const filename = '<?php echo $filename_export; ?>.pdf';
    const opt = {
        margin:       10,
        filename:     filename,
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2 },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    element.style.display = 'flex';
    html2pdf().set(opt).from(element).save().then(function() {
        element.style.display = 'none';
    });
}
</script>

<?php @include __DIR__ . '/../../vision/includes/footer.php'; ?>