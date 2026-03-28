<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';
require_once __DIR__ . '/../config/dash_functions.php';

if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = null;

// --- Definição de Nomes Amigáveis ---
$service_types_list = [
    'translation' => 'Tradução', 
    'revision' => 'Revisão', 
    'proofreading' => 'Revisão (Proofreading)',
    'localization' => 'Localização', 
    'interpretacao' => 'Interpretação', 
    'transcription' => 'Transcrição', 
    'mtpe' => 'Pós-edição (MTPE)',
    'copywriting' => 'Copywriting',
    'other' => 'Outros'
];
$job_status_list = [
    'completed' => 'Pendente de Pagamento',
    'paid' => 'Pago'
];

// --- Busca de Fornecedores para o Filtro ---
$stmt_freelancers = $pdo->prepare("SELECT id, name FROM dash_freelancers WHERE user_id = ? ORDER BY name ASC");
$stmt_freelancers->execute([$user_id]);
$freelancers = $stmt_freelancers->fetchAll(PDO::FETCH_ASSOC);

// --- Lógica de Filtro (GET) ---
$selected_freelancer_id = isset($_GET['freelancer_id']) ? (int)$_GET['freelancer_id'] : null;
$selected_freelancer_name = 'N/A'; 
$startDate = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); 

$report_data = [];
$kpi_summary = [];
$hasData = false;
$filter_applied = ($selected_freelancer_id !== null);

// --- Processamento dos Dados se o Filtro for Aplicado ---
if ($filter_applied) {
    try {
        // 1. Obter o nome do fornecedor selecionado
        $stmt_name = $pdo->prepare("SELECT name FROM dash_freelancers WHERE id = ? AND user_id = ?");
        $stmt_name->execute([$selected_freelancer_id, $user_id]);
        $selected_freelancer_name = $stmt_name->fetchColumn();

        // 2. Buscar trabalhos (Jobs) do fornecedor no período (CORRIGIDO)
        $stmt_jobs = $pdo->prepare("
            SELECT 
                j.id,
                j.service_type,
                j.total_cost,
                j.currency,
                j.status,
                j.updated_at, /* Usado para data e ordenação */
                p.title AS project_title,
                p.id AS project_id_num
            FROM dash_jobs j
            LEFT JOIN dash_projects p ON j.project_id = p.id
            WHERE j.user_id = ?
              AND j.freelancer_id = ?
              AND j.status IN ('completed', 'paid') 
              AND DATE(j.updated_at) BETWEEN ? AND ?
            ORDER BY j.updated_at DESC, j.id DESC
        ");
        $stmt_jobs->execute([$user_id, $selected_freelancer_id, $startDate, $endDate]);
        $report_data = $stmt_jobs->fetchAll(PDO::FETCH_ASSOC);
        
        $hasData = !empty($report_data);

        // 3. Calcular KPIs (Totais por moeda)
        $kpi_summary = ['total_payable' => [], 'total_paid' => [], 'total_geral' => []];
        foreach ($report_data as $job) {
            $currency = $job['currency'];
            $cost = (float)$job['total_cost'];
            if (!isset($kpi_summary['total_payable'][$currency])) $kpi_summary['total_payable'][$currency] = 0;
            if (!isset($kpi_summary['total_paid'][$currency])) $kpi_summary['total_paid'][$currency] = 0;
            if (!isset($kpi_summary['total_geral'][$currency])) $kpi_summary['total_geral'][$currency] = 0;
            if ($job['status'] == 'completed') {
                $kpi_summary['total_payable'][$currency] += $cost;
            } else if ($job['status'] == 'paid') {
                $kpi_summary['total_paid'][$currency] += $cost;
            }
            $kpi_summary['total_geral'][$currency] += $cost;
        }

    } catch (PDOException $e) {
        $error = "Erro ao buscar dados: " . $e->getMessage();
    }
}

// --- Metadata da Página ---
$page_title = 'Extrato do Fornecedor';
$page_description = "Analise o histórico de pagamentos e trabalhos de um fornecedor.";
// --- Fim Metadata ---

// Incluir cabeçalhos
include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">

    <div class="video-card profile-header-card" style="background: linear-gradient(135deg, var(--brand-purple), #4a148c); border: none; margin-bottom: 25px;">
        <div class="header-icon-container" style="background: rgba(255, 255, 255, 0.1); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <i class="fas fa-user-clock" style="font-size: 1.8rem; color: #fff;"></i>
        </div>
        <div class="header-text-container" style="margin-left: 20px;">
            <h2 style="margin: 0 0 5px 0; padding: 0; font-size: 1.5rem; color: #fff; font-weight: 600; border: none;"><?php echo $page_title; ?></h2>
            <p style="margin: 0; color: rgba(255, 255, 255, 0.8); font-size: 1rem;"><?php echo htmlspecialchars($page_description); ?></p>
        </div>
    </div>
    
    <div class="report-nav-buttons">
        <a href="reports.php" class="vision-btn vision-btn-secondary"><i class="fas fa-arrow-left"></i> Voltar aos Relatórios</a>
        <a href="index.php" class="vision-btn vision-btn-secondary"><i class="fas fa-home"></i> Voltar ao Dash-T101</a>
    </div>

    <?php if ($error): ?>
        <div class="alert-error" style="margin-bottom: 20px;"><i class="fas fa-times-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="video-card">
        <h2><i class="fas fa-filter"></i> Filtrar extrato</h2>
        <form method="GET" action="report_vendor_statement.php" class="vision-form">
            <div class="form-row-flex">
                <div class="form-group" style="flex: 2 1 300px;">
                    <label for="freelancer_id"><i class="fas fa-user-tag"></i> Selecionar Fornecedor</label>
                    <select id="freelancer_id" name="freelancer_id" class="vision-input" required>
                        <option value="" disabled <?php echo !$selected_freelancer_id ? 'selected' : ''; ?>>-- Escolha um fornecedor --</option>
                        <?php foreach ($freelancers as $freelancer): ?>
                            <option value="<?php echo $freelancer['id']; ?>" <?php echo ($selected_freelancer_id == $freelancer['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($freelancer['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 1 1 200px;">
                    <label for="start_date"><i class="fas fa-calendar-alt"></i> Data inicial (de pagamento/conclusão)</label>
                    <input type="date" id="start_date" name="start_date" class="vision-input" value="<?php echo htmlspecialchars($startDate); ?>" required>
                </div>
                <div class="form-group" style="flex: 1 1 200px;">
                    <label for="end_date"><i class="fas fa-calendar-alt"></i> Data final (de pagamento/conclusão)</label>
                    <input type="date" id="end_date" name="end_date" class="vision-input" value="<?php echo htmlspecialchars($endDate); ?>" required>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="vision-btn vision-btn-primary"><i class="fas fa-search"></i> Gerar extrato</button>
            </div>
        </form>
    </div>

    <?php if ($filter_applied && empty($error)): ?>
        
        <div class="video-card">
            <h2><i class="fas fa-coins"></i> Resumo para <?php echo htmlspecialchars($selected_freelancer_name); ?> (Valores Originais)</h2>
            <div class="kpi-grid" style="padding: 20px 30px;">
                <div class="kpi-card-media kpi-cost">
                    <h4><i class="fas fa-exclamation-triangle"></i> Total Pendente (a pagar)</h4>
                    <?php if (empty($kpi_summary['total_payable'])): ?>
                        <h3>-</h3>
                    <?php else: ?>
                        <?php foreach ($kpi_summary['total_payable'] as $currency => $total): ?>
                            <h3 style="font-size: 1.5rem;"><?php echo formatCurrency($total, $currency); ?></h3>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="kpi-card-media kpi-revenue">
                    <h4><i class="fas fa-check-circle"></i> Total Pago (no período)</h4>
                     <?php if (empty($kpi_summary['total_paid'])): ?>
                        <h3>-</h3>
                    <?php else: ?>
                        <?php foreach ($kpi_summary['total_paid'] as $currency => $total): ?>
                            <h3 style="font-size: 1.5rem;"><?php echo formatCurrency($total, $currency); ?></h3>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="kpi-card-media kpi-profit"> 
                    <h4><i class="fas fa-file-invoice-dollar"></i> Total Geral (no período)</h4>
                    <?php if (empty($kpi_summary['total_geral'])): ?>
                        <h3>-</h3>
                    <?php else: ?>
                        <?php foreach ($kpi_summary['total_geral'] as $currency => $total): ?>
                            <h3 style="font-size: 1.5rem;"><?php echo formatCurrency($total, $currency); ?></h3>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>


        <div class="video-card">
            <div class="card-header-refined">
                <h2><i class="fas fa-list-ul"></i> Detalhamento para <?php echo htmlspecialchars($selected_freelancer_name); ?></h2>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="<?php echo $hasData ? 'generate_vendor_statement_pdf.php?freelancer_id=' . $selected_freelancer_id . '&start_date=' . $startDate . '&end_date=' . $endDate : '#'; ?>" 
                       target="_blank" id="btn_export_pdf_vendor" 
                       class="vision-btn vision-btn-secondary <?php echo !$hasData ? 'disabled-link' : ''; ?>"
                       style="background-color: #c0392b; border-color: #c0392b; color: #fff;">
                        <i class="fas fa-file-pdf"></i> Exportar PDF
                    </a>
                    <button type="button" id="btn_export_csv_vendor" class="vision-btn vision-btn-secondary" <?php echo !$hasData ? 'disabled' : ''; ?>>
                        <i class="fas fa-file-csv"></i> Exportar CSV
                    </button>
                </div>
            </div>
            
            <div class="vision-table-container">
                <?php if (!$hasData): ?>
                    <div class="alert-info" style="margin: 20px 30px;"><i class="fas fa-info-circle"></i> Nenhum trabalho (concluído ou pago) encontrado para este fornecedor no período selecionado.</div>
                <?php else: ?>
                    <table class="vision-table" id="vendor_statement_table">
                        <thead>
                            <tr>
                                <th>Data (Pagto/Concl.)</th>
                                <th>Projeto</th>
                                <th>Serviço</th>
                                <th>Valor</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $job): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($job['updated_at'])); ?></td>
                                    <td>
                                        <div class="project-info-refined">
                                            <a href="projects.php?edit=<?php echo $job['project_id_num']; ?>" class="project-name-refined" style="text-decoration: none; font-size: 0.95rem;">
                                                <?php echo htmlspecialchars($job['project_title'] ?? 'Projeto N/A'); ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($service_types_list[$job['service_type']] ?? $job['service_type']); ?></td>
                                    <td class="value-cell-refined <?php echo $job['status'] == 'completed' ? 'cost' : 'revenue'; ?>">
                                        <?php echo formatCurrency($job['total_cost'], $job['currency']); ?>
                                    </td>
                                    <td>
                                        <?php if ($job['status'] == 'paid'): ?>
                                            <span class="status-badge-refined status-completed" style="font-size: 0.8rem;">
                                                <i class="fas fa-check-circle"></i> <?php echo $job_status_list['paid']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge-refined status-pending" style="font-size: 0.8rem;">
                                                <i class="fas fa-exclamation-circle"></i> <?php echo $job_status_list['completed']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    
    <?php elseif (!$filter_applied): ?>
        <div class="alert-info" style="margin: 20px 0px;"><i class="fas fa-info-circle"></i> Selecione um fornecedor e um período para gerar o extrato.</div>
    <?php endif; ?>

</div>

<style>
/* Alertas */
.alert-error { background: #ef4444; color: #fff; padding: 15px 30px; border-radius: 12px; margin-bottom: 20px; }
.alert-info { background: var(--accent-blue); color: #fff; padding: 15px 30px; border-radius: 12px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
.main-content .video-card { margin-bottom: 20px; }

/* Cards */
.video-card { background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.05)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; }
.video-card > h2 { margin: 0; padding: 25px 30px 20px; font-size: 1.3rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); }
.card-header-refined { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; padding: 25px 30px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); }
.card-header-refined h2 { margin: 0; padding: 0; font-size: 1.3rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 12px; border-bottom: none; }

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
.value-cell-refined { font-weight: 600; }
.value-cell-refined.revenue { color: var(--accent-green); }
.value-cell-refined.cost { color: var(--accent-orange); }

/* KPI (Padrão "Vision") */
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; }
@media (min-width: 992px) { .kpi-grid { grid-template-columns: repeat(3, 1fr); } } /* 3 colunas para este relatório */
.kpi-card-media { background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.05)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; padding: 25px; text-align: center; }
.kpi-card-media h4 { margin: 0 0 10px 0; font-size: 1rem; font-weight: 600; color: var(--text-secondary); display: flex; align-items: center; gap: 8px; justify-content: center; }
.kpi-card-media h4 i { color: #FFD700; }
.kpi-card-media h3 { margin: 0 0 8px 0; font-size: 2rem; font-weight: 700; }
.kpi-revenue h3 { color: var(--accent-green) !important; }
.kpi-cost h3 { color: var(--accent-orange) !important; }
.kpi-profit h3 { color: var(--accent-blue); }

/* Status Badges */
.status-badge-refined { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: capitalize; display: inline-flex; align-items: center; gap: 6px; }
.status-badge-refined.status-completed { background: rgba(67, 160, 71, 0.1); color: #81c784; } /* Verde */
.status-badge-refined.status-pending { background: rgba(255, 193, 7, 0.1); color: #ffca28; } /* Amarelo */
</style>

<script>
    // --- Lógica de Exportação CSV ---
    function exportVendorStatementToCSV(filename) {
        const table = document.getElementById("vendor_statement_table");
        if (!table) { alert("Erro: Tabela não encontrada."); return; }
        let csv = [];
        const headerRow = table.querySelector("thead tr");
        let header = [];
        headerRow.querySelectorAll("th").forEach(th => {
            header.push('"' + th.innerText.trim().replace(/"/g, '""') + '"');
        });
        csv.push(header.join(','));
        const bodyRows = table.querySelectorAll("tbody tr");
        bodyRows.forEach(row => {
            let rowData = [];
            const cols = row.querySelectorAll("td");
            rowData.push('"' + cols[0].innerText.trim().replace(/"/g, '""') + '"');
            rowData.push('"' + cols[1].querySelector('.project-name-refined').innerText.trim().replace(/"/g, '""') + '"');
            rowData.push('"' + cols[2].innerText.trim().replace(/"/g, '""') + '"'); 
            rowData.push('"' + cols[3].innerText.trim().replace(/"/g, '""') + '"'); 
            rowData.push('"' + cols[4].querySelector('.status-badge-refined').innerText.trim().replace(/"/g, '""') + '"'); 
            csv.push(rowData.join(','));
        });
        const csvFile = new Blob(["\uFEFF" + csv.join('\n')], { type: 'text/csv;charset=utf-8;' }); 
        const downloadLink = document.createElement("a");
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.download = filename;
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const btnCsv = document.getElementById('btn_export_csv_vendor');
        if (btnCsv) {
            btnCsv.addEventListener('click', function() {
                if (this.disabled) return;
                const select = document.getElementById('freelancer_id');
                const freelancerName = select.options[select.selectedIndex].text.trim().replace(/\s+/g, '_');
                const filename = `extrato_${freelancerName}.csv`;
                exportVendorStatementToCSV(filename);
            });
        }
        const btnPdf = document.getElementById('btn_export_pdf_vendor');
        if (btnPdf) {
            btnPdf.addEventListener('click', function(e) {
                if (this.classList.contains('disabled-link')) {
                    e.preventDefault(); 
                }
            });
        }
    });
</script>

<?php
include __DIR__ . '/../vision/includes/footer.php';
?>