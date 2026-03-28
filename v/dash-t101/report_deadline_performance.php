<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';
require_once __DIR__ . '/../config/dash_functions.php';

// Verificar se o usuário está logado
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$page_title = "Relatório de Prazos";
$page_description = "Análise de desempenho de entrega de projetos concluídos.";

// --- Lógica de Filtro de Data (GET) ---
$default_end_date = date('Y-m-d');
$default_start_date = date('Y-m-d', strtotime('-30 days'));

$start_date = $_GET['start_date'] ?? $default_start_date;
$end_date = $_GET['end_date'] ?? $default_end_date;


// --- Consultas ao Banco de Dados ---
// [CORRIGIDO] Query agora usa o filtro de data
$stmt_projects = $pdo->prepare("
    SELECT 
        p.id, 
        p.title, 
        p.deadline, 
        p.completed_date,
        c.company AS client_name 
    FROM dash_projects p
    LEFT JOIN dash_clients c ON p.client_id = c.id
    WHERE p.user_id = ?
      AND p.status = 'completed'
      AND p.deadline IS NOT NULL
      AND p.completed_date IS NOT NULL
      AND p.completed_date BETWEEN ? AND ? -- Filtro de data aplicado
    ORDER BY p.completed_date DESC
");
$stmt_projects->execute([$user_id, $start_date, $end_date]);
$projects = $stmt_projects->fetchAll(PDO::FETCH_ASSOC);

$hasData = !empty($projects);

// --- Processamento dos KPIs ---
$total_completed = 0;
$total_late = 0;
$total_on_time = 0; // No prazo ou adiantado
$total_delay_days = 0;
$report_data = [];

if ($hasData) {
    $total_completed = count($projects);
    
    foreach ($projects as $project) {
        $deadline_ts = strtotime($project['deadline']);
        $completed_ts = strtotime($project['completed_date']);
        
        $delay_days = 0;
        $status = 'on_time'; // Default
        
        // Compara apenas a data (ignorando a hora)
        $deadline_date = date('Y-m-d', $deadline_ts);
        $completed_date = date('Y-m-d', $completed_ts);

        if ($completed_date > $deadline_date) {
            $total_late++;
            $status = 'late';
            
            // Calcula a diferença em dias
            $diff = $completed_ts - $deadline_ts;
            $delay_days = round($diff / (60 * 60 * 24));
            $total_delay_days += $delay_days;
        } else {
            $total_on_time++;
        }
        
        $report_data[] = [
            'project' => $project,
            'status' => $status,
            'delay_days' => $delay_days
        ];
    }
}

$on_time_percentage = ($total_completed > 0) ? ($total_on_time / $total_completed) * 100 : 0;
$average_delay = ($total_late > 0) ? ($total_delay_days / $total_late) : 0;


// --- Início do HTML ---
include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">

    <div class="video-card profile-header-card" style="background: linear-gradient(135deg, var(--brand-purple), #4a148c); border: none; margin-bottom: 25px;">
        <div class="header-icon-container">
            <i class="fas fa-calendar-check" style="font-size: 1.8rem; color: #fff;"></i>
        </div>
        <div class="header-text-container" style="margin-left: 20px;">
            <h2 style="margin: 0 0 5px 0; padding: 0; font-size: 1.5rem; color: #fff; font-weight: 600; border: none;"><?php echo $page_title; ?></h2>
            <p style="margin: 0; color: rgba(255, 255, 255, 0.8); font-size: 1rem;">Projetos concluídos entre <?php echo date('d/m/Y', strtotime($start_date)); ?> e <?php echo date('d/m/Y', strtotime($end_date)); ?></p>
        </div>
    </div>
    
    <div class="report-nav-buttons">
        <a href="reports.php" class="vision-btn vision-btn-secondary"><i class="fas fa-arrow-left"></i> Voltar aos Relatórios</a>
        <a href="index.php" class="vision-btn vision-btn-secondary"><i class="fas fa-home"></i> Voltar ao Dash-T101</a>
    </div>

    <div class="video-card">
        <h2><i class="fas fa-filter"></i> Filtrar Relatório</h2>
        <form method="GET" action="report_deadline_performance.php" class="vision-form">
            <div class="form-row-flex">
                <div class="form-group">
                    <label for="start_date">Data de Início (Conclusão)</label>
                    <input type="date" id="start_date" name="start_date" class="vision-input" value="<?php echo htmlspecialchars($start_date); ?>" required>
                </div>
                <div class="form-group">
                    <label for="end_date">Data de Fim (Conclusão)</label>
                    <input type="date" id="end_date" name="end_date" class="vision-input" value="<?php echo htmlspecialchars($end_date); ?>" required>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="vision-btn vision-btn-primary">
                    <i class="fas fa-filter"></i> Filtrar
                </button>
                <a href="report_deadline_performance.php" class="vision-btn vision-btn-secondary">Limpar (30 dias)</a>
            </div>
        </form>
    </div>

    <div class="video-card">
        <h2><i class="fas fa-chart-bar"></i> Resumo de Desempenho</h2>
        <div class="kpi-grid" style="padding: 20px 30px;">
            <div class="kpi-card-media kpi-profit">
                <h4><i class="fas fa-check-circle"></i> Taxa de Pontualidade</h4>
                <h3><?php echo number_format($on_time_percentage, 1, ',', '.'); ?>%</h3>
                <span class="kpi-subtext"><?php echo $total_on_time; ?> de <?php echo $total_completed; ?> projetos no prazo</span>
            </div>
            <div class="kpi-card-media <?php echo ($total_late > 0) ? 'kpi-cost' : 'kpi-revenue'; ?>">
                <h4><i class="fas fa-exclamation-triangle"></i> Projetos Atrasados</h4>
                <h3><?php echo $total_late; ?></h3>
                <span class="kpi-subtext">Total de projetos concluídos com atraso</span>
            </div>
            <div class="kpi-card-media kpi-margin">
                <h4><i class="fas fa-clock"></i> Média de Atraso</h4>
                <h3><?php echo number_format($average_delay, 1, ',', '.'); ?> dias</h3>
                <span class="kpi-subtext">Média de dias de atraso (apenas dos atrasados)</span>
            </div>
        </div>
    </div>

    <div class="video-card">
        <div class="card-header-refined">
            <h2><i class="fas fa-list-ul"></i> Detalhamento de Projetos Concluídos</h2>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                
                <a href="generate_deadline_pdf.php?start_date=<?php echo htmlspecialchars($start_date); ?>&end_date=<?php echo htmlspecialchars($end_date); ?>" 
                   id="btn_export_pdf_deadline" 
                   class="vision-btn vision-btn-secondary <?php echo $hasData ? '' : 'disabled-link'; ?>" 
                   style="background-color: #c0392b; border-color: #c0392b; color: #fff;"
                   target="_blank">
                    <i class="fas fa-file-pdf"></i> Exportar PDF
                </a>
                
                <button id="btn_export_csv_deadline" class="vision-btn vision-btn-secondary" <?php echo $hasData ? '' : 'disabled'; ?>>
                    <i class="fas fa-file-csv"></i> Exportar CSV
                </button>
            </div>
        </div>
        
        <div class="vision-table-container">
            <table class="vision-table" id="report_table">
                <thead>
                    <tr>
                        <th><i class="fas fa-project-diagram"></i> Projeto</th>
                        <th><i class="fas fa-user"></i> Cliente</th>
                        <th><i class="fas fa-calendar-alt"></i> Prazo (Deadline)</th>
                        <th><i class="fas fa-calendar-check"></i> Data Conclusão</th>
                        <th><i class="fas fa-flag"></i> Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($report_data)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">Nenhum projeto concluído com data de prazo encontrada.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($report_data as $data): ?>
                            <tr>
                                <td class="project-name-refined"><?php echo htmlspecialchars($data['project']['title']); ?></td>
                                <td><?php echo htmlspecialchars($data['project']['client_name']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($data['project']['deadline'])); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($data['project']['completed_date'])); ?></td>
                                <td>
                                    <?php if ($data['status'] === 'on_time'): ?>
                                        <span class="status-badge-refined status-completed">
                                            No Prazo
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge-refined status-cancelled">
                                            Atrasado (<?php echo $data['delay_days']; ?>d)
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div> <style>
/* Alertas */
.alert-success { opacity: 1; transition: opacity 1s ease-out; background: #22c55e; color: #fff; padding: 15px 30px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.alert-error { background: #ef4444; color: #fff; padding: 15px 30px; border-radius: 12px; margin-bottom: 20px; }
.alert-info { background: var(--accent-blue); color: #fff; padding: 15px 30px; border-radius: 12px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
.main-content .video-card { margin-bottom: 20px; }

/* Cards */
.video-card { background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.05)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; }
.video-card > h2 { margin: 0; padding: 25px 30px 20px; font-size: 1.3rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); }
.card-header-refined { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; padding: 25px 30px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); }
.card-header-refined h2 { margin: 0; font-size: 1.3rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 12px; }

/* Botões */
.report-nav-buttons { display: flex; gap: 15px; margin-bottom: 20px; }
.vision-btn { background: var(--brand-purple); color: white; border: 1px solid var(--brand-purple); border-radius: 20px; padding: 12px 24px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); }
.vision-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3); }
.vision-btn:disabled, .disabled-link { background: rgba(255, 255, 255, 0.1) !important; border-color: rgba(255, 255, 255, 0.1) !important; color: var(--text-muted) !important; cursor: not-allowed !important; }
.vision-btn-primary { background: var(--brand-purple); border-color: var(--brand-purple); }
.vision-btn-primary:hover { background: var(--brand-purple-dark); border-color: var(--brand-purple-dark); }
.vision-btn-secondary { background: rgba(255, 255, 255, 0.1); color: var(--text-primary); border-color: rgba(255, 255, 255, 0.2); }
.vision-btn-secondary:hover { background: rgba(255, 255, 255, 0.2) !important; border-color: rgba(255, 255, 255, 0.3) !important; color: #fff !important; }

/* Formulário */
.vision-form { padding: 20px 30px 30px; }
.form-row-flex { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px; }
.form-group { display: flex; flex-direction: column; gap: 8px; flex: 1 1 200px; }
.form-group label { font-weight: 600; color: var(--text-secondary); font-size: 0.9rem; }
.vision-input { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 12px 16px; color: var(--text-primary); font-size: 0.95rem; }
.form-actions { display: flex; gap: 12px; margin-top: 20px; }

/* Header */
.profile-header-card { display: flex; align-items: center; justify-content: center; }
.header-icon-container { background: rgba(255, 255, 255, 0.1); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.header-text-container { margin-left: 20px; }
.header-text-container h2 { margin: 0 0 5px 0; padding: 0; font-size: 1.5rem; color: #fff; font-weight: 600; border: none; }
.header-text-container p { margin: 0; color: rgba(255, 255, 255, 0.8); font-size: 1rem; }

/* Tabela */
.vision-table-container { margin: 0 30px 30px; overflow-x: auto; padding-bottom: 10px; }
.vision-table { width: 100%; border-collapse: collapse; }
.vision-table th { background: rgba(255, 255, 255, 0.05); border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding: 18px 20px; font-weight: 600; font-size: 0.9rem; color: var(--text-secondary); text-align: left; }
.vision-table th i { font-size: 0.8rem; margin-right: 6px; opacity: 0.7; }
.vision-table td { padding: 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.04); font-size: 0.95rem; vertical-align: middle; }
.vision-table tr:hover { background: rgba(255, 255, 255, 0.03); }
.project-name-refined { font-weight: 600; color: var(--text-primary); font-size: 1rem; }

/* KPI */
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; }
@media (min-width: 992px) { .kpi-grid { grid-template-columns: repeat(3, 1fr); } }
.kpi-card-media { background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.05)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; padding: 25px; text-align: center; }
.kpi-card-media h4 { margin: 0 0 10px 0; font-size: 1rem; font-weight: 600; color: var(--text-secondary); display: flex; align-items: center; gap: 8px; justify-content: center; }
.kpi-card-media h4 i { color: #FFD700; }
.kpi-card-media h3 { margin: 0 0 8px 0; font-size: 2rem; font-weight: 700; }
.kpi-card-media .kpi-subtext { font-size: 0.85rem; color: var(--text-muted); }
.kpi-revenue h3 { color: var(--accent-green) !important; }
.kpi-cost h3 { color: var(--accent-orange) !important; }
.kpi-profit h3 { color: var(--accent-blue); }
.kpi-margin h3 { color: var(--accent-cyan); }

/* Status Badges */
.status-badge-refined { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: capitalize; background: rgba(255, 255, 255, 0.1); color: var(--text-secondary); }
.status-badge-refined.status-completed { background: rgba(67, 160, 71, 0.1); color: #81c784; } /* Verde */
.status-badge-refined.status-cancelled { background: rgba(239, 83, 80, 0.1); color: #ef5350; } /* Vermelho */
</style>

<script>
    // --- Lógica de Exportação CSV ---
    function exportDeadlineTableToCSV(filename) {
        let csv = [];
        const rows = document.querySelectorAll("#report_table tr");
        
        // Cabeçalho
        let header = [];
        const ths = rows[0].querySelectorAll("th");
        ths.forEach(th => {
            let thText = th.innerText.trim();
            header.push('"' + thText.replace(/"/g, '""') + '"');
        });
        csv.push(header.join(','));
        
        // Body
        const bodyRows = document.querySelectorAll("#report_table tbody tr");
        bodyRows.forEach(row => {
            let rowData = [];
            row.querySelectorAll("td").forEach(td => {
                let data = td.innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/\s+/g, " ").trim();
                rowData.push('"' + data.replace(/"/g, '""') + '"');
            });
            csv.push(rowData.join(','));
        });

        // Download
        const csvFile = new Blob(["\uFEFF" + csv.join('\n')], { type: 'text/csv;charset=utf-8;' }); 
        
        if (window.navigator.msSaveOrOpenBlob) {
            window.navigator.msSaveOrOpenBlob(csvFile, filename);
        } else {
            const downloadLink = document.createElement("a");
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.download = filename;
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }
    }

    // Adiciona todos os listeners quando o DOM estiver pronto
    document.addEventListener('DOMContentLoaded', function() {
        
        // --- Anexar Listeners aos Botões de Exportação ---
        const btnCsv = document.getElementById('btn_export_csv_deadline');
        if (btnCsv) {
            btnCsv.addEventListener('click', function() {
                if (this.disabled) {
                    return;
                }
                exportDeadlineTableToCSV('desempenho_prazos.csv');
            });
        }

        const btnPdf = document.getElementById('btn_export_pdf_deadline');
        if (btnPdf) {
            btnPdf.addEventListener('click', function(e) {
                if (this.classList.contains('disabled-link')) {
                    e.preventDefault(); // Impede o link de ser seguido
                }
            });
        }
    });
</script>

<?php
include __DIR__ . '/../vision/includes/footer.php';
?>