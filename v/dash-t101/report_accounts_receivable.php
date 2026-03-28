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

// --- Lógica de Ações (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'mark_as_paid') {
            $project_id = $_POST['project_id'];
            $payment_date = $_POST['payment_date_modal'] ?: date('Y-m-d');

            if (!empty($project_id)) {
                $stmt = $pdo->prepare("UPDATE dash_projects SET payment_date = ? WHERE id = ? AND user_id = ? AND status = 'completed'");
                $stmt->execute([$payment_date, $project_id, $user_id]);
                
                $_SESSION['temp_message'] = "Projeto marcado como pago!";
                header("Location: report_accounts_receivable.php");
                exit;
            }
        }
    } catch (PDOException $e) {
        $error = "Erro ao atualizar o projeto: " . $e->getMessage();
    }
}

if (isset($_SESSION['temp_message'])) {
    $message = $_SESSION['temp_message'];
    unset($_SESSION['temp_message']);
}
if (isset($_SESSION['temp_error'])) {
    $error = $_SESSION['temp_error'];
    unset($_SESSION['temp_error']);
}

// --- Consultas ao Banco de Dados ---
$kpi_totals = [];
$report_data = [];
$hasData = false;
$base_currency = 'BRL'; // Moeda base para KPIs

try {
    // [NOVO] Buscar taxas de câmbio para conversão
    $rates = ['BRL' => 1.0]; 
    $stmt_rates = $pdo->prepare("SELECT setting_key, setting_value FROM dash_settings WHERE user_id = ? AND setting_key LIKE 'rate_%'");
    $stmt_rates->execute([$user_id]);
    foreach ($stmt_rates->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $code = strtoupper(str_replace('rate_', '', $row['setting_key']));
        if((float)$row['setting_value'] > 0) {
            $rates[$code] = (float)$row['setting_value'];
        }
    }

    // Função helper para converter moedas para BRL
    $convert_to_brl = function($amount, $currency) use ($rates) {
        if ($currency === 'BRL') {
            return $amount;
        }
        if (!isset($rates[$currency]) || $rates[$currency] == 0) {
            return null; 
        }
        $rate = $rates[$currency];
        return $amount * $rate;
    };

    // --- Consulta principal ---
    $sql = "
        SELECT 
            p.id, 
            p.title, 
            p.total_amount, 
            p.currency, 
            p.deadline, 
            p.completed_date,
            c.company AS client_name
        FROM dash_projects p
        LEFT JOIN dash_clients c ON p.client_id = c.id
        WHERE p.user_id = ?
          AND p.status = 'completed'
          AND p.payment_date IS NULL
        ORDER BY p.deadline ASC, p.completed_date ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($report_data)) {
        $hasData = true;
    }

    // --- Processamento dos KPIs ---
    $total_overdue_brl = 0;
    $total_due_30_brl = 0;
    $total_due_later_brl = 0;
    $total_receivable_brl = 0;
    $today = new DateTime();
    $today_str = $today->format('Y-m-d');
    $missing_rates_warning = false;

    foreach ($report_data as $project) {
        $amount_brl = $convert_to_brl($project['total_amount'], $project['currency']);
        
        if ($amount_brl === null) {
            $missing_rates_warning = true;
            continue; // Pula este projeto se a taxa de câmbio estiver faltando
        }

        $total_receivable_brl += $amount_brl;
        $deadline_str = $project['deadline'];

        if ($deadline_str && $deadline_str < $today_str) {
            $total_overdue_brl += $amount_brl;
        } elseif ($deadline_str && $deadline_str <= $today->modify('+30 days')->format('Y-m-d')) {
            $total_due_30_brl += $amount_brl;
             $today->modify('-30 days'); // Reseta a data 'today'
        } else {
            $total_due_later_brl += $amount_brl;
        }
    }

} catch (PDOException $e) {
    $error = "Erro de banco de dados: " . $e->getMessage();
} catch (Exception $e) {
    $error = "Erro: " . $e->getMessage();
}


// --- Início do HTML ---
$page_title = "Contas a Receber";
$page_description = "Projetos concluídos e pendentes de pagamento.";

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">

    <div class="video-card profile-header-card" style="background: linear-gradient(135deg, var(--brand-purple), #4a148c); border: none; margin-bottom: 25px;">
        <div class="header-icon-container">
            <i class="fas fa-file-invoice-dollar" style="font-size: 1.8rem; color: #fff;"></i>
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

    <?php if ($message): ?><div id="success-alert" class="alert-success"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert-error"><i class="fas fa-exclamation-triangle"></i><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($missing_rates_warning): ?>
        <div class="alert-error"><i class="fas fa-exclamation-triangle"></i>
            Atenção: Um ou mais valores não puderam ser convertidos para <?php echo $base_currency; ?> por falta de taxa de câmbio. Os totais podem estar incorretos. <a href="settings.php" style="color: white; font-weight: 600;">Cadastrar Moedas</a>.
        </div>
    <?php endif; ?>

    <div class="video-card">
        <h2><i class="fas fa-coins"></i> Resumo (Consolidado em <?php echo $base_currency; ?>)</h2>
        <div class="kpi-grid" style="padding: 20px 30px;">
            <div class="kpi-card-media kpi-cost">
                <h4><i class="fas fa-exclamation-triangle"></i> Vencido</h4>
                <h3><?php echo formatCurrency($total_overdue_brl, $base_currency); ?></h3>
            </div>
            <div class="kpi-card-media kpi-margin">
                <h4><i class="fas fa-clock"></i> Vence em 30 dias</h4>
                <h3><?php echo formatCurrency($total_due_30_brl, $base_currency); ?></h3>
            </div>
            <div class="kpi-card-media kpi-profit">
                <h4><i class="fas fa-calendar-alt"></i> Vence a +30 dias</h4>
                <h3><?php echo formatCurrency($total_due_later_brl, $base_currency); ?></h3>
            </div>
            <div class="kpi-card-media kpi-revenue">
                <h4><i class="fas fa-dollar-sign"></i> Total a Receber</h4>
                <h3><?php echo formatCurrency($total_receivable_brl, $base_currency); ?></h3>
            </div>
        </div>
    </div>

    <div class="video-card">
        <div class="card-header-refined">
            <h2><i class="fas fa-list-ul"></i> Detalhamento de Projetos Pendentes</h2>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                
                <a href="generate_client_profit_pdf.php" 
                   id="btn_export_pdf_receivable" 
                   class="vision-btn vision-btn-secondary <?php echo $hasData ? '' : 'disabled-link'; ?>" 
                   style="background-color: #c0392b; border-color: #c0392b; color: #fff;"
                   target="_blank">
                    <i class="fas fa-file-pdf"></i> Exportar PDF
                </a>
                
                <button id="btn_export_csv_receivable" class="vision-btn vision-btn-secondary" <?php echo $hasData ? '' : 'disabled'; ?>>
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
                        <th><i class="fas fa-money-bill-wave"></i> Valor</th>
                        <th><i class="fas fa-calendar-check"></i> Conclusão</th>
                        <th><i class="fas fa-calendar-alt"></i> Vencimento</th>
                        <th><i class="fas fa-cogs"></i> Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($report_data)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">Nenhum projeto pendente de pagamento encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($report_data as $project): ?>
                            <?php
                                $deadline_status = 'future';
                                if ($project['deadline'] && $project['deadline'] < $today_str) {
                                    $deadline_status = 'overdue';
                                }
                            ?>
                            <tr>
                                <td class="project-name-refined"><?php echo htmlspecialchars($project['title']); ?></td>
                                <td><?php echo htmlspecialchars($project['client_name']); ?></td>
                                <td class="value-cell-refined" style="color: var(--accent-green);">
                                    <?php echo formatCurrency($project['total_amount'], $project['currency']); ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($project['completed_date'])); ?></td>
                                <td class="<?php echo $deadline_status === 'overdue' ? 'text-danger' : ''; ?>" style="font-weight: <?php echo $deadline_status === 'overdue' ? '600' : 'normal'; ?>;">
                                    <?php echo $project['deadline'] ? date('d/m/Y', strtotime($project['deadline'])) : 'N/A'; ?>
                                    <?php if ($deadline_status === 'overdue'): ?>
                                        <span class="status-badge-refined status-cancelled" style="font-size: 0.75rem; margin-left: 5px;">Vencido</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="vision-btn vision-btn-primary btn-mark-paid" 
                                            data-id="<?php echo $project['id']; ?>" 
                                            data-title="<?php echo htmlspecialchars($project['title']); ?>"
                                            style="padding: 8px 16px; border-radius: 10px; font-size: 0.85rem;">
                                        <i class="fas fa-check-circle"></i> Marcar como Pago
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div> <div id="markAsPaidModal" class="vision-modal">
    <div class="vision-modal-content" style="max-width: 500px;">
        <div class="vision-modal-header">
            <h3><i class="fas fa-check-circle"></i> Marcar como Pago</h3>
            <button type="button" class="vision-modal-close" onclick="hideModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="report_accounts_receivable.php" class="vision-form" style="padding: 20px 30px 30px;">
            <input type="hidden" name="action" value="mark_as_paid">
            <input type="hidden" name="project_id" id="modal_project_id">
            
            <p style="color: var(--text-secondary); margin-bottom: 20px;">Você confirma o pagamento do projeto <strong id="modal_project_title" style="color: var(--text-primary);"></strong>?</p>

            <div class="form-group">
                <label for="payment_date_modal">Data do Pagamento (opcional)</label>
                <input type="date" id="payment_date_modal" name="payment_date_modal" class="vision-input" value="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="vision-modal-actions" style="margin-top: 20px;">
                <button type="button" class="vision-btn vision-btn-secondary" onclick="hideModal()">Cancelar</button>
                <button type="submit" class="vision-btn vision-btn-primary">Confirmar Pagamento</button>
            </div>
        </form>
    </div>
</div>


<style>
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
.text-danger { color: var(--accent-orange) !important; }

/* KPI */
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; }
@media (min-width: 1200px) { .kpi-grid { grid-template-columns: repeat(4, 1fr); } }
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
.status-badge-refined.status-cancelled { background: rgba(239, 83, 80, 0.1); color: #ef5350; } /* Vermelho */

/* Modal */
.vision-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(10px); z-index: 1000; align-items: center; justify-content: center; }
.vision-modal.active { display: flex; }
.vision-modal-content { background: rgba(30, 30, 30, 0.95); backdrop-filter: blur(30px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 24px; width: 90%; max-width: 500px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5); }
.vision-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 25px 30px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
.vision-modal-header h3 { margin: 0; color: var(--text-primary); font-size: 1.2rem; display: flex; align-items: center; gap: 8px; }
.vision-modal-close { background: none; border: none; color: var(--text-secondary); font-size: 1.2rem; cursor: pointer; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 16px; transition: all 0.3s ease; }
.vision-modal-close:hover { background: rgba(255, 255, 255, 0.1); color: var(--text-primary); }
.vision-modal-form { padding: 30px; }
.vision-modal-actions { display: flex; gap: 12px; justify-content: flex-end; }
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
    function exportReceivableTableToCSV(filename) {
        let csv = [];
        const rows = document.querySelectorAll("#report_table tr");
        
        // Cabeçalho (ignora a última coluna "Ações")
        let header = [];
        const ths = rows[0].querySelectorAll("th");
        for (let i = 0; i < ths.length - 1; i++) { // Itera até "Ações"
            let thText = ths[i].innerText.trim();
            header.push('"' + thText.replace(/"/g, '""') + '"');
        }
        csv.push(header.join(','));
        
        // Body (ignora a última coluna "Ações")
        const bodyRows = document.querySelectorAll("#report_table tbody tr");
        bodyRows.forEach(row => {
            let rowData = [];
            const cols = row.querySelectorAll("td");
            for (let i = 0; i < cols.length - 1; i++) { // Itera até "Ações"
                let data = cols[i].innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/\s+/g, " ").trim();
                rowData.push('"' + data.replace(/"/g, '""') + '"');
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
    
    // --- Lógica do Modal ---
    const modal = document.getElementById('markAsPaidModal');

    function showModal(projectId, projectTitle) {
        document.getElementById('modal_project_id').value = projectId;
        document.getElementById('modal_project_title').innerText = projectTitle;
        modal.classList.add('active');
    }

    function hideModal() {
        modal.classList.remove('active');
    }

    document.addEventListener('DOMContentLoaded', function() {
        
        // Anexar listener aos botões "Marcar como Pago"
        document.querySelectorAll('.btn-mark-paid').forEach(button => {
            button.addEventListener('click', function() {
                const projectId = this.dataset.id;
                const projectTitle = this.dataset.title;
                showModal(projectId, projectTitle);
            });
        });

        // Fechar modal
        modal.addEventListener('click', function(e) {
            if (e.target === modal || e.target.closest('.vision-modal-close')) {
                hideModal();
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === "Escape" && modal.classList.contains('active')) {
                hideModal();
            }
        });
        
        
        // --- Anexar Listeners aos Botões de Exportação ---
        const btnCsv = document.getElementById('btn_export_csv_receivable');
        if (btnCsv) {
            btnCsv.addEventListener('click', function() {
                if (this.disabled) {
                    return;
                }
                exportReceivableTableToCSV('contas_a_receber.csv');
            });
        }

        const btnPdf = document.getElementById('btn_export_pdf_receivable');
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