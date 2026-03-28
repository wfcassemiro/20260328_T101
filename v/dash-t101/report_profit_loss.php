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
$message = '';
$error = '';

// --- Filtros de Data ---\
// Define o período padrão (últimos 30 dias)
$default_end_date = date('Y-m-d');
$default_start_date = date('Y-m-d', strtotime('-30 days'));

$start_date = $_GET['start_date'] ?? $default_start_date;
$end_date = $_GET['end_date'] ?? $default_end_date;

// --- Consultas ao Banco de Dados ---\

$summary = [
    'BRL' => ['revenue' => 0, 'cost' => 0, 'profit' => 0],
    'USD' => ['revenue' => 0, 'cost' => 0, 'profit' => 0],
    'EUR' => ['revenue' => 0, 'cost' => 0, 'profit' => 0],
    // Outras moedas serão adicionadas se encontradas
];
$all_currencies = ['BRL', 'USD', 'EUR']; // Base

// [NOVO] Buscar taxas de câmbio para conversão
$rates = ['BRL' => 1.0]; // Moeda base
try {
    $stmt_rates = $pdo->prepare("SELECT setting_key, setting_value FROM dash_settings WHERE user_id = ? AND setting_key LIKE 'rate_%'");
    $stmt_rates->execute([$user_id]);
    foreach ($stmt_rates->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $code = strtoupper(str_replace('rate_', '', $row['setting_key']));
        if((float)$row['setting_value'] > 0) {
            // Armazena a taxa de conversão PARA BRL (Ex: 1 USD = 5.2 BRL)
            $rates[$code] = (float)$row['setting_value'];
        }
    }
} catch (Exception $e) {
    // Falha ao buscar taxas, continuar apenas com BRL
    $error = "Aviso: Falha ao buscar taxas de câmbio. Os cálculos podem estar incompletos.";
}

// [NOVO] Função helper para converter moedas para BRL
$convert_to_brl = function($amount, $currency) use ($rates) {
    if ($currency === 'BRL') {
        return $amount;
    }
    if (!isset($rates[$currency]) || $rates[$currency] == 0) {
        // Se a taxa não existe ou é zero, não podemos converter. Retorna null.
        return null; 
    }
    $rate = $rates[$currency];
    return $amount * $rate;
};


// 1. Tabela - Projetos Concluídos no Período
// [ALTERADO] Adicionado p.tax_percentage
$stmt_projects = $pdo->prepare("
    SELECT 
        p.id, p.title, p.status, p.total_amount, p.currency, p.created_at, p.completed_date,
        p.tax_percentage,
        c.company as company_name
    FROM dash_projects p
    LEFT JOIN dash_clients c ON p.client_id = c.id
    WHERE
        p.user_id = ? AND
        p.completed_date BETWEEN ? AND ?
    ORDER BY p.completed_date DESC
");
$stmt_projects->execute([$user_id, $start_date, $end_date]);
$projects = $stmt_projects->fetchAll(PDO::FETCH_ASSOC);


// 2. Resumo de Receita (Query Original mantida, lógica de SOMA alterada)
$stmt_revenue_summary = $pdo->prepare("
    SELECT currency, SUM(total_amount) as total_revenue
    FROM dash_projects
    WHERE user_id = ? AND status = 'completed' AND completed_date BETWEEN ? AND ?
    GROUP BY currency
");
$stmt_revenue_summary->execute([$user_id, $start_date, $end_date]);
$all_revenues = $stmt_revenue_summary->fetchAll(PDO::FETCH_ASSOC);

foreach ($all_revenues as $revenue) {
    $currency = $revenue['currency'];
    if (!isset($summary[$currency])) {
        $summary[$currency] = ['revenue' => 0, 'cost' => 0, 'profit' => 0];
        if (!in_array($currency, $all_currencies)) $all_currencies[] = $currency;
    }
    $summary[$currency]['revenue'] += $revenue['total_revenue'];
}


// 3. Resumo de Custo (Query CORRIGIDA)
$stmt_cost_summary = $pdo->prepare("
    SELECT j.currency, SUM(j.total_cost) as total_cost
    FROM dash_jobs j
    JOIN dash_projects p ON j.project_id = p.id
    WHERE p.user_id = ? AND p.status = 'completed' AND p.completed_date BETWEEN ? AND ?
    GROUP BY j.currency
");
$stmt_cost_summary->execute([$user_id, $start_date, $end_date]);
$all_costs = $stmt_cost_summary->fetchAll(PDO::FETCH_ASSOC);

foreach ($all_costs as $cost) {
    $currency = $cost['currency'];
    if (!isset($summary[$currency])) {
        $summary[$currency] = ['revenue' => 0, 'cost' => 0, 'profit' => 0];
        if (!in_array($currency, $all_currencies)) $all_currencies[] = $currency;
    }
    $summary[$currency]['cost'] += $cost['total_cost'];
}


// 4. Cálculo de Lucro do Resumo (Lógica Original mantida)
$total_revenue_brl = 0;
$total_cost_brl = 0;
$total_profit_brl = 0;
$total_tax_brl = 0; // [NOVO] Total de imposto

foreach ($all_currencies as $currency) {
    if (isset($summary[$currency])) {
        // Lucro bruto (antes do imposto) por moeda
        $summary[$currency]['profit'] = $summary[$currency]['revenue'] - $summary[$currency]['cost'];
        
        // Consolida o total em BRL usando a conversão
        $revenue_in_brl = $convert_to_brl($summary[$currency]['revenue'], $currency);
        $cost_in_brl = $convert_to_brl($summary[$currency]['cost'], $currency);

        if($revenue_in_brl !== null) {
            $total_revenue_brl += $revenue_in_brl;
        }
        if($cost_in_brl !== null) {
            $total_cost_brl += $cost_in_brl;
        }
    }
}
// O $total_profit_brl e $margin_percent serão calculados após o loop de projetos (item 5)


// 5. [CORRIGIDO] Preparar dados da Tabela (Buscando custos, impostos e convertendo para BRL)
$projects_data = [];
$missing_rates_warning = false;

foreach ($projects as $project) {
    
    // Buscar custos (jobs) para este projeto
    $stmt_jobs = $pdo->prepare("SELECT total_cost, currency FROM dash_jobs WHERE project_id = ? AND user_id = ?");
    $stmt_jobs->execute([$project['id'], $user_id]);
    $jobs = $stmt_jobs->fetchAll(PDO::FETCH_ASSOC);

    $cost_brl = 0;
    $cost_conversion_failed = false;
    
    foreach ($jobs as $job) {
        $converted_cost = $convert_to_brl($job['total_cost'], $job['currency']);
        if ($converted_cost === null) {
            $cost_conversion_failed = true; // Marca se uma taxa faltar
            $missing_rates_warning = true;
        }
        $cost_brl += $converted_cost ?? 0;
    }

    // Converter receita para BRL
    $revenue_brl = $convert_to_brl($project['total_amount'], $project['currency']);
    if ($revenue_brl === null) {
        $revenue_brl = 0; // Se a taxa da receita faltar, zera
        $cost_conversion_failed = true; // Marca para aviso
        $missing_rates_warning = true;
    }

    // [NOVO] Calcular Imposto (sempre sobre a receita em BRL)
    $tax_percentage = (float)($project['tax_percentage'] ?? 0);
    $tax_brl = $revenue_brl * ($tax_percentage / 100);
    $total_tax_brl += $tax_brl; // Acumula o imposto total

    // [ALTERADO] Calcular Lucro Líquido
    $profit_brl = $revenue_brl - $cost_brl - $tax_brl;
    
    // [ATUALIZADO] Adiciona ao array de dados da tabela
    $projects_data[] = [
        'project' => $project,
        'revenue_brl' => $revenue_brl,
        'cost_brl' => $cost_brl,
        'tax_brl' => $tax_brl, // [NOVO]
        'profit_brl' => $profit_brl, // [ALTERADO]
        'conversion_failed' => $cost_conversion_failed
    ];
}

// [ALTERADO] Cálculo final do Lucro Líquido e Margem Líquida
$total_profit_brl = $total_revenue_brl - $total_cost_brl - $total_tax_brl;
$margin_percent = ($total_revenue_brl > 0) ? ($total_profit_brl / $total_revenue_brl) * 100 : 0;


// --- Início do HTML ---
$page_title = 'Relatório de Lucratividade';
$page_description = "Relatório de Receita, Custo e Lucro por projeto.";

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="video-card profile-header-card" style="background: linear-gradient(135deg, var(--brand-purple), #4a148c); border: none; margin-bottom: 25px;">
        <div class="header-icon-container">
            <i class="fas fa-chart-line" style="font-size: 1.8rem; color: #fff;"></i>
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

    <?php if ($message): ?><div id="success-alert" class="alert-success"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert-error"><i class="fas fa-exclamation-triangle"></i><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($missing_rates_warning): ?>
        <div class="alert-error"><i class="fas fa-exclamation-triangle"></i>
            Atenção: Um ou mais valores não puderam ser convertidos para BRL por falta de taxa de câmbio cadastrada. Os totais consolidados podem estar incorretos. <a href="settings.php" style="color: white; font-weight: 600;">Cadastrar Moedas</a>.
        </div>
    <?php endif; ?>


    <div class="video-card">
        <h2><i class="fas fa-filter"></i> Filtrar Relatório</h2>
        <form method="GET" action="report_profit_loss.php" class="vision-form">
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
                <a href="report_profit_loss.php" class="vision-btn vision-btn-secondary">Limpar (30 dias)</a>
            </div>
        </form>
    </div>

    <div class="video-card">
        <h2><i class="fas fa-coins"></i> Resumo Consolidado (Convertido para BRL)</h2>
        <div class="kpi-grid" style="padding: 20px 30px;">
            <div class="kpi-card-media kpi-revenue">
                <h4><i class="fas fa-dollar-sign"></i> Receita Total (BRL)</h4>
                <h3><?php echo formatCurrency($total_revenue_brl, 'BRL'); ?></h3>
            </div>
            <div class="kpi-card-media kpi-cost">
                <h4><i class="fas fa-wallet"></i> Custo Total (BRL)</h4>
                <h3><?php echo formatCurrency($total_cost_brl, 'BRL'); ?></h3>
            </div>
            <div class="kpi-card-media kpi-tax">
                <h4><i class="fas fa-file-invoice-dollar"></i> Imposto Total (BRL)</h4>
                <h3><?php echo formatCurrency($total_tax_brl, 'BRL'); ?></h3>
            </div>
            <div class="kpi-card-media kpi-profit">
                <h4><i class="fas fa-chart-line"></i> Lucro Líquido (BRL)</h4>
                <h3><?php echo formatCurrency($total_profit_brl, 'BRL'); ?></h3>
                <p style="margin: 5px 0 0 0; color: var(--text-muted); font-size: 0.9rem;">
                    Margem: <?php echo number_format($margin_percent, 2, ',', '.'); ?>%
                </p>
            </div>
        </div>
    </div>
    
    <div class="video-card">
        <h2><i class="fas fa-calculator"></i> Resumo por Moeda (Valores Brutos)</h2>
        <div class="vision-table-container" style="margin: 0 30px 20px;">
            <table class="vision-table">
                <thead>
                    <tr>
                        <th>Moeda</th>
                        <th>Receita</th>
                        <th>Custo</th>
                        <th>Lucro (Bruto)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    ksort($summary); // Ordena pela chave (moeda)
                    foreach ($all_currencies as $currency): 
                        if (empty($summary[$currency]['revenue']) && empty($summary[$currency]['cost'])) continue;
                    ?>
                        <tr>
                            <td class="client-name-refined"><?php echo htmlspecialchars($currency); ?></td>
                            <td><?php echo formatCurrency($summary[$currency]['revenue'], $currency); ?></td>
                            <td><?php echo formatCurrency($summary[$currency]['cost'], $currency); ?></td>
                            <td class="client-name-refined"><?php echo formatCurrency($summary[$currency]['profit'], $currency); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="video-card">
        <div class="card-header-refined">
            <h2><i class="fas fa-list-ul"></i> Detalhamento de Projetos (Consolidado em BRL)</h2>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="generate_pnl_pdf.php?start_date=<?php echo htmlspecialchars($start_date); ?>&end_date=<?php echo htmlspecialchars($end_date); ?>" target="_blank" class="vision-btn vision-btn-secondary" style="background-color: #c0392b; border-color: #c0392b; color: #fff;">
                    <i class="fas fa-file-pdf"></i> Exportar PDF
                </a>
                <button class="vision-btn vision-btn-secondary" onclick="exportTableToCSV('lucratividade_projetos.csv')">
                    <i class="fas fa-file-csv"></i> Exportar CSV
                </button>
            </div>
        </div>
        
        <div class="vision-table-container">
            <?php if (empty($projects_data)): ?>
                <div class="alert-info" style="margin: 20px 30px;"><i class="fas fa-info-circle"></i> Nenhum projeto concluído encontrado neste período.</div>
            <?php else: ?>
                <table class="vision-table" id="report_table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-project-diagram"></i> Projeto</th>
                            <th><i class="fas fa-user"></i> Cliente</th>
                            <th><i class="fas fa-calendar-check"></i> Data Conclusão</th>
                            <th><i class="fas fa-dollar-sign"></i> Receita (BRL)</th>
                            <th><i class="fas fa-wallet"></i> Custo (BRL)</th>
                            <th><i class="fas fa-file-invoice-dollar"></i> Imposto (BRL)</th> <th><i class="fas fa-chart-line"></i> Lucro Líquido (BRL)</th> </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($projects_data as $data): 
                            $project = $data['project'];
                        ?>
                            <tr>
                                <td class="client-name-refined">
                                    <?php echo htmlspecialchars($project['title']); ?>
                                    <?php if ($data['conversion_failed']): ?>
                                        <br><span class_not_used="badge badge-warning" style="color: var(--accent-orange); font-size: 0.8rem;"><i class="fas fa-exclamation-triangle"></i> Taxa não encontrada</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($project['company_name']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($project['completed_date'])); ?></td>
                                
                                <td><?php echo formatCurrency($data['revenue_brl'], 'BRL'); ?> <small style="color: var(--text-muted);">(<?php echo formatCurrency($project['total_amount'], $project['currency']); ?>)</small></td>
                                <td><?php echo formatCurrency($data['cost_brl'], 'BRL'); ?></td>
                                <td><?php echo formatCurrency($data['tax_brl'], 'BRL'); ?></td> <td class="client-name-refined <?php echo ($data['profit_brl'] < 0) ? 'text-danger' : ''; ?>" style="<?php echo ($data['profit_brl'] < 0) ? 'color: var(--accent-orange) !important;' : ''; ?>">
                                    <?php echo formatCurrency($data['profit_brl'], 'BRL'); ?> </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* (Estilos existentes do seu tema) */
.alert-success { opacity: 1; transition: opacity 1s ease-out; background: #22c55e; color: #fff; padding: 15px 30px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.alert-error { background: #ef4444; color: #fff; padding: 15px 30px; border-radius: 12px; margin-bottom: 20px; }
.alert-info { background: var(--accent-blue); color: #fff; padding: 15px 30px; border-radius: 12px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
.main-content .video-card { margin-bottom: 20px; }
.video-card { background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.05)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; }
.video-card > h2 { margin: 0; padding: 25px 30px 20px; font-size: 1.3rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); }
.card-header-refined { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; padding: 25px 30px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); }
.card-header-refined h2 { margin: 0; font-size: 1.3rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 12px; }

/* Botões */
.report-nav-buttons { display: flex; gap: 15px; margin-bottom: 20px; }
.vision-btn { background: var(--brand-purple); color: white; border: 1px solid var(--brand-purple); border-radius: 20px; padding: 12px 24px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); }
.vision-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3); }
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
.vision-table-container { margin: 20px 30px 30px; overflow-x: auto; padding-bottom: 10px; }
.vision-table { width: 100%; border-collapse: collapse; }
.vision-table th { background: rgba(255, 255, 255, 0.05); border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding: 18px 20px; font-weight: 600; font-size: 0.9rem; color: var(--text-secondary); text-align: left; }
.vision-table td { padding: 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.04); font-size: 0.95rem; }
.vision-table tr:hover { background: rgba(255, 255, 255, 0.03); }
.client-name-refined { color: var(--text-primary); font-weight: 600; }
.text-danger { color: var(--accent-orange); }

/* KPI */
.kpi-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); /* Responsivo por padrão */
    gap: 20px; 
    margin-bottom: 20px; 
}
@media (min-width: 992px) and (max-width: 1199px) {
    .kpi-grid {
        grid-template-columns: repeat(2, 1fr); /* 2 colunas em tablets grandes */
    }
}
@media (min-width: 1200px) {
    .kpi-grid {
        grid-template-columns: repeat(4, 1fr); /* 4 colunas em telas grandes */
    }
}
.kpi-card-media { background: linear-gradient(145deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.05)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; padding: 25px; text-align: center; }
.kpi-card-media h4 { margin: 0 0 10px 0; font-size: 1rem; font-weight: 600; color: var(--text-secondary); display: flex; align-items: center; gap: 8px; justify-content: center; }
.kpi-card-media h4 i { color: #FFD700; }
.kpi-card-media h3 { margin: 0 0 8px 0; font-size: 2rem; font-weight: 700; }
.kpi-revenue h3 { color: var(--accent-green) !important; }
.kpi-cost h3 { color: var(--accent-orange) !important; }
.kpi-tax h3 { color: var(--accent-orange) !important; } /* [NOVO] Estilo para imposto (cor de custo) */
.kpi-profit h3 { color: var(--accent-blue); }
.kpi-margin h3 { color: var(--accent-cyan); }
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
    function exportTableToCSV(filename) {
        let csv = [];
        const rows = document.querySelectorAll("#report_table tr");
        
        // Cabeçalho
        let header = [];
        const ths = rows[0].querySelectorAll("th");
        ths.forEach(th => {
            // Remove o ícone se houver
            let thText = th.innerText.trim();
            header.push('"' + thText.replace(/"/g, '""') + '"');
        });
        csv.push(header.join(','));
        
        // Linhas
        for (let i = 1; i < rows.length; i++) {
            let row = [], cols = rows[i].querySelectorAll("td");
            
            cols.forEach(td => {
                // Limpa o texto (remove newlines, excesso de espaço) e coloca entre aspas
                let data = td.innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/\s+/g, " ").trim();
                data = '"' + data.replace(/"/g, '""') + '"';
                row.push(data);
            });
            
            csv.push(row.join(','));
        }

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
</script>

<?php
include __DIR__ . '/../vision/includes/footer.php';
?>