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
$page_title = 'Custo por Serviço';
$page_description = "Relatório detalhado de custos por tipo de serviço.";

// --- Definição de Nomes Amigáveis ---
$service_name_map = [
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

// --- Lógica de Filtro de Data (GET) ---
$default_end_date = date('Y-m-d');
$default_start_date = date('Y-m-d', strtotime('-30 days'));

$start_date = $_GET['start_date'] ?? $default_start_date;
$end_date = $_GET['end_date'] ?? $default_end_date;

$report_data = [];
$totals_brl = 0;
$totals_by_currency = []; // Custo total por moeda
$base_currency = 'BRL'; // Vamos consolidar tudo em BRL
$rates_string = "1 BRL = 1.00 BRL";
$general_error = null;
$missing_rates_warning = null;
$hasData = false;

try {
    // =========================================================================
    // || ETAPA 1: Buscar Taxas de Câmbio Salvas                          ||
    // =========================================================================
    $rates = ['BRL' => 1.0]; // Moeda base
    $sql_rate_cases_cost_parts = [];  // Array para as partes
    $rates_string_parts = [];

    $stmt_rates = $pdo->prepare("SELECT setting_key, setting_value FROM dash_settings WHERE user_id = ? AND setting_key LIKE 'rate_%'");
    $stmt_rates->execute([$user_id]);
    $db_rates = $stmt_rates->fetchAll(PDO::FETCH_ASSOC);

    if (empty($db_rates) && !$stmt_rates) {
        throw new Exception("Falha ao buscar taxas de câmbio.");
    }

    foreach ($db_rates as $rate) {
        $currency_code = strtoupper(str_replace('rate_', '', $rate['setting_key']));
        $rate_value = (float)$rate['setting_value'];
        if ($rate_value > 0) {
            $rates[$currency_code] = $rate_value; // Armazena a taxa
            if ($currency_code !== 'BRL') { // BRL é o caso 'ELSE'
                // Construção manual segura da string SQL
                $sql_rate_cases_cost_parts[] = " WHEN " . $pdo->quote($currency_code) . " THEN j.total_cost * " . floatval($rate_value);
                $rates_string_parts[] = "1 $currency_code = $rate_value $base_currency";
            }
        }
    }

    // Montagem final da string SQL
    $sql_rate_cases_cost = "CASE j.currency " . implode(" ", $sql_rate_cases_cost_parts) . " WHEN 'BRL' THEN j.total_cost ELSE 0 END";

    if (!empty($rates_string_parts)) {
        $rates_string = implode(' / ', $rates_string_parts);
    }
    
    // =========================================================================
    // || ETAPA 2: Buscar Custo (Cost) por Serviço                        ||
    // =========================================================================
    $sql_cost = "
        SELECT 
            p.service_type, 
            j.currency,
            SUM(j.total_cost) AS total_cost_original,
            SUM($sql_rate_cases_cost) AS total_cost_brl,
            COUNT(j.id) as job_count
        FROM dash_jobs j
        JOIN dash_projects p ON j.project_id = p.id
        WHERE 
            p.user_id = ? AND 
            p.status = 'completed' AND
            p.completed_date BETWEEN ? AND ?
        GROUP BY p.service_type, j.currency
        ORDER BY p.service_type
    ";

    $stmt_cost = $pdo->prepare($sql_cost);
    $stmt_cost->execute([$user_id, $start_date, $end_date]);
    
    $raw_data = $stmt_cost->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($raw_data)) {
        $hasData = true;
    }

    // =========================================================================
    // || ETAPA 3: Processar e Agrupar Dados                            ||
    // =========================================================================
    foreach ($raw_data as $row) {
        $service_key = $row['service_type'] ?: 'other';
        $currency = $row['currency'];
        $cost_original = (float)$row['total_cost_original'];
        $cost_brl = (float)$row['total_cost_brl'];
        $job_count = (int)$row['job_count'];

        // Inicializa o array para o serviço
        if (!isset($report_data[$service_key])) {
            $report_data[$service_key] = [
                'cost_brl' => 0,
                'job_count' => 0
            ];
        }
        
        // Inicializa o array para a moeda
        if (!isset($totals_by_currency[$currency])) {
            $totals_by_currency[$currency] = ['cost' => 0, 'job_count' => 0];
        }

        // Soma os totais
        $report_data[$service_key]['cost_brl'] += $cost_brl;
        $report_data[$service_key]['job_count'] += $job_count;
        
        $totals_by_currency[$currency]['cost'] += $cost_original;
        $totals_by_currency[$currency]['job_count'] += $job_count;
        $totals_brl += $cost_brl;
    }
    
    // Ordenar dados por Custo (maior primeiro)
    uasort($report_data, function($a, $b) {
        return $b['cost_brl'] <=> $a['cost_brl'];
    });

    if (count($db_rates) === 0) {
        $missing_rates_warning = "Nenhuma taxa de câmbio encontrada. Cálculos podem estar incorretos. Por favor, configure as taxas em 'Moedas'.";
    }

} catch (Exception $e) {
    $general_error = "Erro ao gerar relatório: " . $e->getMessage();
} catch (PDOException $e) {
    $general_error = "Erro de banco de dados: " . $e->getMessage();
}

// --- Fim da Lógica PHP ---


// Incluir cabeçalhos
include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">

    <div class="video-card profile-header-card" style="background: linear-gradient(135deg, var(--brand-purple), #4a148c); border: none; margin-bottom: 25px;">
        <div class="header-icon-container">
            <i class="fas fa-wallet" style="font-size: 1.8rem; color: #fff;"></i>
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

    <?php if ($general_error): ?>
        <div class="alert-error"><i class="fas fa-exclamation-triangle"></i><?php echo htmlspecialchars($general_error); ?></div>
    <?php endif; ?>
    <?php if ($missing_rates_warning): ?>
        <div class="alert-info"><i class="fas fa-info-circle"></i><?php echo htmlspecialchars($missing_rates_warning); ?> <a href="settings.php" style="color: white; font-weight: 600;">Cadastrar Moedas</a>.</div>
    <?php endif; ?>

    <div class="video-card">
        <h2><i class="fas fa-filter"></i> Filtrar Relatório</h2>
        <form method="GET" action="report_service_cost.php" class="vision-form">
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
                <a href="report_service_cost.php" class="vision-btn vision-btn-secondary">Limpar (30 dias)</a>
            </div>
        </form>
    </div>

    <div class="video-card">
        <h2><i class="fas fa-coins"></i> Custo Total por Moeda (Valores Originais)</h2>
        <div class="kpi-grid" style="padding: 20px 30px;">
            <?php if (empty($totals_by_currency)): ?>
                <div class="kpi-card-media kpi-cost">
                    <h4><i class="fas fa-wallet"></i> Custo Total</h4>
                    <h3><?php echo formatCurrency(0, 'BRL'); ?></h3>
                </div>
            <?php else: ?>
                <?php ksort($totals_by_currency); ?>
                <?php foreach ($totals_by_currency as $currency => $data): ?>
                    <div class="kpi-card-media kpi-cost">
                        <h4><i class="fas fa-wallet"></i> Custo Total (<?php echo htmlspecialchars($currency); ?>)</h4>
                        <h3><?php echo formatCurrency($data['cost'], $currency); ?></h3>
                        <span class="kpi-subtext"><?php echo $data['job_count']; ?> jobs</span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="alert-info" style="margin: 0 30px 30px; border-radius: 12px;">
            <i class="fas fa-info-circle"></i> Taxas de câmbio usadas para consolidação: <?php echo htmlspecialchars($rates_string); ?>
        </div>
    </div>

    <div class="video-card">
        <div class="card-header-refined">
            <h2><i class="fas fa-list-ul"></i> Detalhamento de Custo (Consolidado em <?php echo $base_currency; ?>)</h2>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                
                <a href="generate_pdf_report_service_cost.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                   id="btn_export_pdf_service_cost" 
                   class="vision-btn vision-btn-secondary <?php echo $hasData ? '' : 'disabled-link'; ?>" 
                   style="background-color: #c0392b; border-color: #c0392b; color: #fff;"
                   target="_blank">
                    <i class="fas fa-file-pdf"></i> Exportar PDF
                </a>
                
                <button id="btn_export_csv_service_cost" class="vision-btn vision-btn-secondary" <?php echo $hasData ? '' : 'disabled'; ?>>
                    <i class="fas fa-file-csv"></i> Exportar CSV
                </button>
            </div>
        </div>
        
        <div class="vision-table-container">
            <table class="vision-table" id="report_table">
                <thead>
                    <tr>
                        <th><i class="fas fa-concierge-bell"></i> Serviço</th>
                        <th><i class="fas fa-hashtag"></i> Jobs</th>
                        <th><i class="fas fa-wallet"></i> Custo Total (<?php echo $base_currency; ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($report_data)): ?>
                        <tr>
                            <td colspan="3" style="text-align: center;">Nenhum dado encontrado para o período.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($report_data as $service_key => $data): ?>
                            <tr>
                                <td class="project-name-refined"><?php echo htmlspecialchars($service_name_map[$service_key] ?? ucfirst($service_key)); ?></td>
                                <td style="text-align: center;"><?php echo $data['job_count']; ?></td>
                                <td class="value-cell-refined" style="color: var(--accent-orange);"><?php echo formatCurrency($data['cost_brl'], $base_currency); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="table-totals">
                        <td colspan="2"><strong>Custo Total Consolidado</strong></td>
                        <td><strong><?php echo formatCurrency($totals_brl, $base_currency); ?></strong></td>
                    </tr>
                </tfoot>
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
.value-cell-refined { font-weight: 600; }
.vision-table tfoot tr.table-totals td { border-top: 2px solid var(--brand-purple); background: rgba(142, 68, 173, 0.1); font-size: 1.1rem; padding: 20px; }
.vision-table tfoot strong { color: var(--text-primary); }

/* KPI */
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; }
.kpi-card-media { background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.05)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; padding: 25px; text-align: center; }
.kpi-card-media h4 { margin: 0 0 10px 0; font-size: 1rem; font-weight: 600; color: var(--text-secondary); display: flex; align-items: center; gap: 8px; justify-content: center; }
.kpi-card-media h4 i { color: #FFD700; }
.kpi-card-media h3 { margin: 0 0 8px 0; font-size: 2rem; font-weight: 700; }
.kpi-card-media .kpi-subtext { font-size: 0.85rem; color: var(--text-muted); }
.kpi-cost h3 { color: var(--accent-orange) !important; }
</style>

<script>
    // --- Lógica do Alerta de Sucesso ---
    const successAlert = document.getElementById('success-alert');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.opacity = '0';
            setTimeout(() => { successAlert.remove(); }, 1000); 
        }, 5000);
    }

    // --- Lógica de Exportação CSV ---
    function exportServiceCostTableToCSV(filename) {
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

        // Footer (Totals)
        const tfootRows = document.querySelectorAll("#report_table tfoot tr");
        tfootRows.forEach(row => {
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

    document.addEventListener('DOMContentLoaded', function() {
        const btnCsv = document.getElementById('btn_export_csv_service_cost');
        if (btnCsv) {
            btnCsv.addEventListener('click', function() {
                if (this.disabled) return;
                exportServiceCostTableToCSV('custo_por_servico.csv');
            });
        }
        
        // Desabilitar link do PDF se o botão estiver desabilitado
        const btnPdf = document.getElementById('btn_export_pdf_service_cost');
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