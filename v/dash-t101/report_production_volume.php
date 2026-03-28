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
$page_title = 'Volume de Produção';
$page_description = "Relatório de volume de produção por serviço e unidade.";

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

$unit_name_map = [
    'palavra' => 'Palavras',
    'hora' => 'Horas',
    'minuto' => 'Minutos',
    'lauda' => 'Laudas',
    'diaria' => 'Diárias',
    'caracteres' => 'Caracteres',
    'projeto' => 'Projetos'
];

// --- Lógica de Filtro de Data (GET) ---
$default_end_date = date('Y-m-d');
$default_start_date = date('Y-m-d', strtotime('-30 days'));

$start_date = $_GET['start_date'] ?? $default_start_date;
$end_date = $_GET['end_date'] ?? $default_end_date;

$report_data = []; // Agrupado por serviço
$kpi_totals = [];  // Agrupado por unidade
$general_error = null;
$hasData = false;

try {
    // =========================================================================
    // || ETAPA 1: Buscar Dados de Produção (de dash_projects)            ||
    // =========================================================================
    
    // [CORRIGIDO] Filtra por completed_date para consistência
    $sql = "
        SELECT 
            service_type, 
            unit_type,
            SUM(word_count) as total_volume, -- 'word_count' é usado para todas as quantidades
            COUNT(id) as project_count
        FROM dash_projects
        WHERE 
            user_id = ? AND 
            status = 'completed' AND
            completed_date BETWEEN ? AND ?
        GROUP BY service_type, unit_type
        ORDER BY service_type, unit_type
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $start_date, $end_date]);
    
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($raw_data)) {
        $hasData = true;
    }

    // =========================================================================
    // || ETAPA 2: Processar e Agrupar Dados                            ||
    // =========================================================================
    
    foreach ($raw_data as $row) {
        $service_key = $row['service_type'] ?: 'other';
        $unit_key = $row['unit_type'] ?: 'palavra'; // Assume 'palavra' se nulo
        $volume = (float)$row['total_volume'];
        $count = (int)$row['project_count'];

        // Inicializa o array para o serviço
        if (!isset($report_data[$service_key])) {
            $report_data[$service_key] = [
                'total_projects' => 0,
                'volumes' => [] // Volumes por unidade
            ];
        }
        
        // Inicializa o array para o KPI de unidade
        if (!isset($kpi_totals[$unit_key])) {
            $kpi_totals[$unit_key] = 0;
        }

        // Soma os totais
        $report_data[$service_key]['volumes'][$unit_key] = $volume;
        $report_data[$service_key]['total_projects'] += $count;
        $kpi_totals[$unit_key] += $volume;
    }
    
    // Ordenar KPIs por chave (nome da unidade)
    ksort($kpi_totals);

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
            <i class="fas fa-tasks" style="font-size: 1.8rem; color: #fff;"></i>
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

    <div class="video-card">
        <h2><i class="fas fa-filter"></i> Filtrar Relatório</h2>
        <form method="GET" action="report_production_volume.php" class="vision-form">
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
                <a href="report_production_volume.php" class="vision-btn vision-btn-secondary">Limpar (30 dias)</a>
            </div>
        </form>
    </div>

    <div class="video-card">
        <h2><i class="fas fa-ruler-combined"></i> Volume Total por Unidade</h2>
        <div class="kpi-grid" style="padding: 20px 30px;">
            <?php if (empty($kpi_totals)): ?>
                <div class="kpi-card-media kpi-cost">
                    <h4><i class="fas fa-box-open"></i> Volume Total</h4>
                    <h3>0</h3>
                </div>
            <?php else: ?>
                <?php foreach ($kpi_totals as $unit_key => $total_volume): ?>
                    <div class="kpi-card-media kpi-profit"> <h4><i class="fas fa-ruler"></i> Total (<?php echo htmlspecialchars($unit_name_map[$unit_key] ?? $unit_key); ?>)</h4>
                        <h3><?php echo number_format($total_volume, 0, ',', '.'); ?></h3>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="video-card">
        <div class="card-header-refined">
            <h2><i class="fas fa-list-ul"></i> Detalhamento por Serviço e Unidade</h2>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                
                <button id="btn_export_csv_production" class="vision-btn vision-btn-secondary" <?php echo $hasData ? '' : 'disabled'; ?>>
                    <i class="fas fa-file-csv"></i> Exportar CSV
                </button>
            </div>
        </div>
        
        <div class="vision-table-container">
            <table class="vision-table" id="report_table">
                <thead>
                    <tr>
                        <th><i class="fas fa-concierge-bell"></i> Serviço</th>
                        <th><i class="fas fa-ruler-combined"></i> Unidade</th>
                        <th><i class="fas fa-tasks"></i> Volume</th>
                        <th><i class="fas fa-hashtag"></i> Projetos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($report_data)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center;">Nenhum dado encontrado para o período.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($report_data as $service_key => $data): ?>
                            <?php 
                            // Mescla as linhas se um serviço tiver múltiplas unidades
                            $rowspan = count($data['volumes']); 
                            $first_unit = true;
                            ?>
                            <?php foreach ($data['volumes'] as $unit_key => $volume): ?>
                                <tr>
                                    <?php if ($first_unit): ?>
                                        <td class="project-name-refined" rowspan="<?php echo $rowspan; ?>">
                                            <?php echo htmlspecialchars($service_name_map[$service_key] ?? ucfirst($service_key)); ?>
                                        </td>
                                    <?php endif; ?>
                                    
                                    <td><?php echo htmlspecialchars($unit_name_map[$unit_key] ?? $unit_key); ?></td>
                                    <td class="value-cell-refined" style="color: var(--accent-cyan);"><?php echo number_format($volume, 0, ',', '.'); ?></td>

                                    <?php if ($first_unit): ?>
                                        <td style="text-align: center;" rowspan="<?php echo $rowspan; ?>">
                                            <?php echo $data['total_projects']; ?>
                                        </td>
                                    <?php 
                                        $first_unit = false;
                                    endif; 
                                    ?>
                                </tr>
                            <?php endforeach; ?>
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
.value-cell-refined { font-weight: 600; }

/* KPI */
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; }
.kpi-card-media { background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.05)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; padding: 25px; text-align: center; }
.kpi-card-media h4 { margin: 0 0 10px 0; font-size: 1rem; font-weight: 600; color: var(--text-secondary); display: flex; align-items: center; gap: 8px; justify-content: center; }
.kpi-card-media h4 i { color: #FFD700; }
.kpi-card-media h3 { margin: 0 0 8px 0; font-size: 2rem; font-weight: 700; }
.kpi-card-media .kpi-subtext { font-size: 0.85rem; color: var(--text-muted); }
.kpi-profit h3 { color: var(--accent-blue); } /* Azul para volume */
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
    function exportProductionTableToCSV(filename) {
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
                // Lida com rowspan para a primeira coluna
                if (td.hasAttribute('rowspan')) {
                    // Adiciona o valor da célula com rowspan
                    rowData.push('"' + data.replace(/"/g, '""') + '"');
                } else if (row.cells.length < ths.length && rowData.length === 0) {
                    // Esta é uma linha que "continua" um rowspan anterior,
                    // então adicionamos uma célula vazia para a primeira coluna
                    rowData.push('""'); 
                    rowData.push('"' + data.replace(/"/g, '""') + '"');
                } else {
                     rowData.push('"' + data.replace(/"/g, '""') + '"');
                }
            });
            // Corrige linhas que tiveram rowspan
            while (rowData.length > 0 && rowData.length < ths.length) {
                rowData.push('""');
            }
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
        const btnCsv = document.getElementById('btn_export_csv_production');
        if (btnCsv) {
            btnCsv.addEventListener('click', function() {
                if (this.disabled) return;
                exportProductionTableToCSV('volume_producao.csv');
            });
        }
        
        // Desabilitar link do PDF se o botão estiver desabilitado
        const btnPdf = document.getElementById('btn_export_pdf_production');
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