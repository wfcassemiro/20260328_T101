<?php
session_start();
require_once __DIR__ . '/config/database.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$page_title = 'Minhas Faturas - Translators101';
$page_description = 'Visualize e gerencie suas faturas e pagamentos';

$user_id = $_SESSION['user_id'];

// Buscar faturas do usuário
try {
    $stmt = $pdo->prepare("
        SELECT i.*, c.company_name 
        FROM invoices i 
        LEFT JOIN clients c ON i.client_id = c.id 
        WHERE i.user_id = ? 
        ORDER BY i.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $invoices = $stmt->fetchAll();
} catch (PDOException $e) {
    $invoices = [];
}

// Calcular estatísticas
$total_invoices = count($invoices);
$total_amount = 0;
$paid_amount = 0;
$pending_amount = 0;

foreach ($invoices as $invoice) {
    $total_amount += $invoice['total_amount'];
    if ($invoice['status'] === 'paid') {
        $paid_amount += $invoice['total_amount'];
    } else {
        $pending_amount += $invoice['total_amount'];
    }
}

function formatCurrency($amount, $currency = 'BRL') {
    return 'R$ ' . number_format($amount, 2, ',', '.');
}

include __DIR__ . '/vision/includes/head.php';
include __DIR__ . '/vision/includes/header.php';
include __DIR__ . '/vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-file-invoice-dollar"></i> Minhas Faturas</h1>
            <p>Visualize e acompanhe suas faturas e pagamentos</p>
        </div>
    </div>

    <!-- Estatísticas -->
    <div class="stats-grid">
        <div class="video-card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3>Total de Faturas</h3>
                    <span class="stats-number"><?php echo $total_invoices; ?></span>
                </div>
                <div class="stats-icon stats-icon-blue">
                    <i class="fas fa-file-invoice"></i>
                </div>
            </div>
        </div>

        <div class="video-card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3>Valor Total</h3>
                    <span class="stats-number"><?php echo formatCurrency($total_amount); ?></span>
                </div>
                <div class="stats-icon stats-icon-purple">
                    <i class="fas fa-calculator"></i>
                </div>
            </div>
        </div>

        <div class="video-card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3>Valor Pago</h3>
                    <span class="stats-number"><?php echo formatCurrency($paid_amount); ?></span>
                </div>
                <div class="stats-icon stats-icon-green">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>

        <div class="video-card stats-card">
            <div class="stats-content">
                <div class="stats-info">
                    <h3>Valor Pendente</h3>
                    <span class="stats-number"><?php echo formatCurrency($pending_amount); ?></span>
                </div>
                <div class="stats-icon stats-icon-red">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="video-card">
        <div class="card-header">
            <h2><i class="fas fa-list"></i> Lista de Faturas</h2>
            
            <?php if (!empty($invoices)): ?>
                <div class="search-filters">
                    <a href="dash-t101/invoices.php" class="cta-btn">
                        <i class="fas fa-plus"></i> Nova Fatura
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($invoices)): ?>
            <div class="empty-state">
                <i class="fas fa-file-invoice"></i>
                <h3>Nenhuma fatura ainda</h3>
                <p>Você ainda não possui faturas cadastradas</p>
                <a href="dash-t101/invoices.php" class="cta-btn">
                    <i class="fas fa-plus"></i> Criar Primeira Fatura
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i> Número</th>
                            <th><i class="fas fa-user"></i> Cliente</th>
                            <th><i class="fas fa-calendar"></i> Data</th>
                            <th><i class="fas fa-calendar-check"></i> Vencimento</th>
                            <th><i class="fas fa-money-bill-wave"></i> Valor</th>
                            <th><i class="fas fa-flag"></i> Status</th>
                            <th><i class="fas fa-cogs"></i> Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                            <?php
                            $is_overdue = ($invoice['status'] == 'sent' && strtotime($invoice['due_date']) < time());
                            ?>
                            <tr>
                                <td>
                                    <span class="text-primary"><?php echo htmlspecialchars($invoice['invoice_number']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($invoice['company_name'] ?? 'Cliente não informado'); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($invoice['invoice_date'])); ?></td>
                                <td>
                                    <span class="<?php echo $is_overdue ? 'text-danger' : ''; ?>">
                                        <?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?>
                                    </span>
                                </td>
                                <td><?php echo formatCurrency($invoice['total_amount'], $invoice['currency'] ?? 'BRL'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php 
                                        if ($is_overdue) {
                                            echo 'overdue';
                                        } else {
                                            echo $invoice['status'];
                                        }
                                        ?>">
                                        <?php 
                                        if ($is_overdue) {
                                            echo 'Vencida';
                                        } else {
                                            $status_labels = [
                                                'draft' => 'Rascunho',
                                                'sent' => 'Enviada',
                                                'paid' => 'Paga',
                                                'cancelled' => 'Cancelada'
                                            ];
                                            echo $status_labels[$invoice['status']] ?? $invoice['status'];
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="dash-t101/view_invoice.php?id=<?php echo $invoice['id']; ?>" 
                                           class="page-btn" title="Visualizar">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($invoice['status'] !== 'paid'): ?>
                                            <a href="dash-t101/invoices.php?edit=<?php echo $invoice['id']; ?>" 
                                               class="page-btn" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Dicas -->
    <div class="video-card">
        <h2><i class="fas fa-lightbulb"></i> Dicas de Faturamento</h2>
        
        <div class="dashboard-sections">
            <div>
                <h3><i class="fas fa-calendar-alt"></i> <strong>Organize seus Prazos</strong></h3>
                <p>Mantenha sempre o controle dos prazos de vencimento das suas faturas para evitar atrasos nos pagamentos.</p>
                
                <h3><i class="fas fa-chart-line"></i> <strong>Acompanhe o Fluxo</strong></h3>
                <p>Use as estatísticas para monitorar sua receita mensal e identificar tendências no seu negócio.</p>
            </div>
            
            <div>
                <h3><i class="fas fa-file-contract"></i> <strong>Detalhamento</strong></h3>
                <p>Mantenha suas faturas sempre bem detalhadas com descrições claras dos serviços prestados.</p>
                
                <h3><i class="fas fa-envelope"></i> <strong>Comunicação</strong></h3>
                <p>Envie lembretes educados aos clientes sobre faturas próximas do vencimento.</p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>