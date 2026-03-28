<?php
// Funções auxiliares para o Dash-T101

/**
 * Calcular o total de um projeto
 */
function calculateProjectTotal($word_count, $character_count, $rate_per_word, $rate_per_character) {
    $word_total = $word_count * $rate_per_word;
    $char_total = $character_count * $rate_per_character;
    return $word_total + $char_total;
}

/**
 * Gerar número de fatura único
 */
function generateInvoiceNumber($user_id) {
    global $pdo;
    
    $year = date('Y');
    $month = date('m');
    
    // Buscar o último número da fatura do usuário no mês atual
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dash_invoices WHERE user_id = ? AND YEAR(created_at) = ? AND MONTH(created_at) = ?");
    $stmt->execute([$user_id, $year, $month]);
    $count = $stmt->fetchColumn();
    
    $sequence = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    
    return "INV-{$year}{$month}-{$sequence}";
}

/**
 * Obter configurações do usuário
 */
function getUserSettings($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM dash_user_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings) {
        // Criar configurações padrão se não existirem
        $default_settings = [
            'user_id' => $user_id,
            'company_name' => '',
            'company_address' => '',
            'company_phone' => '',
            'company_email' => '',
            'default_currency' => 'BRL',
            'default_tax_rate' => 0.00,
            'invoice_terms' => 'Pagamento em 30 dias.',
            'invoice_footer' => 'Obrigado pelo seu negócio!'
        ];
        
        $stmt = $pdo->prepare("INSERT INTO dash_user_settings (user_id, company_name, company_address, company_phone, company_email, default_currency, default_tax_rate, invoice_terms, invoice_footer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $default_settings['company_name'],
            $default_settings['company_address'],
            $default_settings['company_phone'],
            $default_settings['company_email'],
            $default_settings['default_currency'],
            $default_settings['default_tax_rate'],
            $default_settings['invoice_terms'],
            $default_settings['invoice_footer']
        ]);
        
        return $default_settings;
    }
    
    return $settings;
}

/**
 * Obter estatísticas do dashboard
 */
function getDashboardStats($user_id) {
    global $pdo;
    
    $stats = [];
    
    // Total de clientes
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dash_clients WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats['total_clients'] = $stmt->fetchColumn();
    
    // Total de projetos
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dash_projects WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats['total_projects'] = $stmt->fetchColumn();
    
    // Projetos em andamento
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dash_projects WHERE user_id = ? AND status = 'in_progress'");
    $stmt->execute([$user_id]);
    $stats['active_projects'] = $stmt->fetchColumn();
    
    // Projetos concluídos
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dash_projects WHERE user_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $stats['completed_projects'] = $stmt->fetchColumn();
    
    // Total de faturas
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dash_invoices WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats['total_invoices'] = $stmt->fetchColumn();
    
    // Faturas pagas
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dash_invoices WHERE user_id = ? AND status = 'paid'");
    $stmt->execute([$user_id]);
    $stats['paid_invoices'] = $stmt->fetchColumn();
    
    // Faturas pendentes
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dash_invoices WHERE user_id = ? AND status = 'sent'");
    $stmt->execute([$user_id]);
    $stats['pending_invoices'] = $stmt->fetchColumn();
    
    // Receita total (faturas pagas)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM dash_invoices WHERE user_id = ? AND status = 'paid'");
    $stmt->execute([$user_id]);
    $stats['total_revenue'] = $stmt->fetchColumn();
    
    // Receita pendente (faturas enviadas)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM dash_invoices WHERE user_id = ? AND status = 'sent'");
    $stmt->execute([$user_id]);
    $stats['pending_revenue'] = $stmt->fetchColumn();
    
    // Faturas vencidas
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dash_invoices WHERE user_id = ? AND status = 'sent' AND due_date < CURDATE()");
    $stmt->execute([$user_id]);
    $stats['overdue_invoices'] = $stmt->fetchColumn();
    
    // Projetos com prazo próximo (próximos 7 dias)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dash_projects WHERE user_id = ? AND status IN ('pending', 'in_progress') AND deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
    $stmt->execute([$user_id]);
    $stats['upcoming_deadlines'] = $stmt->fetchColumn();
    
    return $stats;
}

/**
 * Obter atividades recentes
 */
function getRecentActivities($user_id, $limit = 10) {
    global $pdo;
    
    $activities = [];
    
    // Projetos recentes
    $stmt = $pdo->prepare("SELECT p.project_name, c.company_name, p.created_at, 'project' as type FROM dash_projects p LEFT JOIN dash_clients c ON p.client_id = c.id WHERE p.user_id = ? ORDER BY p.created_at DESC LIMIT ?");
    $stmt->execute([$user_id, $limit]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($projects as $project) {
        $activities[] = [
            'type' => 'project',
            'title' => 'Novo projeto criado',
            'description' => $project['project_name'] . ' - ' . $project['company_name'],
            'date' => $project['created_at']
        ];
    }
    
    // Faturas recentes
    $stmt = $pdo->prepare("SELECT i.invoice_number, c.company_name, i.created_at, 'invoice' as type FROM dash_invoices i LEFT JOIN dash_clients c ON i.client_id = c.id WHERE i.user_id = ? ORDER BY i.created_at DESC LIMIT ?");
    $stmt->execute([$user_id, $limit]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($invoices as $invoice) {
        $activities[] = [
            'type' => 'invoice',
            'title' => 'Nova fatura criada',
            'description' => $invoice['invoice_number'] . ' - ' . $invoice['company_name'],
            'date' => $invoice['created_at']
        ];
    }
    
    // Ordenar por data
    usort($activities, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    return array_slice($activities, 0, $limit);
}

/**
 * Formatar moeda
 */
function formatCurrency($amount, $currency = 'BRL') {
    switch ($currency) {
        case 'BRL':
            return 'R$ ' . number_format($amount, 2, ',', '.');
        case 'USD':
            return '$' . number_format($amount, 2, '.', ',');
        case 'EUR':
            return '€' . number_format($amount, 2, ',', '.');
        default:
            return number_format($amount, 2, '.', ',');
    }
}

/**
 * Obter cor do status
 */
function getStatusColor($status, $type = 'project') {
    $colors = [
        'project' => [
            'pending' => 'bg-yellow-600',
            'in_progress' => 'bg-blue-600',
            'completed' => 'bg-green-600',
            'on_hold' => 'bg-orange-600',
            'cancelled' => 'bg-red-600'
        ],
        'invoice' => [
            'draft' => 'bg-gray-600',
            'sent' => 'bg-blue-600',
            'paid' => 'bg-green-600',
            'overdue' => 'bg-red-600',
            'cancelled' => 'bg-red-600'
        ]
    ];
    
    return $colors[$type][$status] ?? 'bg-gray-600';
}

/**
 * Obter label do status
 */
function getStatusLabel($status, $type = 'project') {
    $labels = [
        'project' => [
            'pending' => 'Pendente',
            'in_progress' => 'Em Andamento',
            'completed' => 'Concluído',
            'on_hold' => 'Pausado',
            'cancelled' => 'Cancelado'
        ],
        'invoice' => [
            'draft' => 'Rascunho',
            'sent' => 'Enviada',
            'paid' => 'Paga',
            'overdue' => 'Vencida',
            'cancelled' => 'Cancelada'
        ]
    ];
    
    return $labels[$type][$status] ?? $status;
}

/**
 * Calcular progresso do projeto
 */
function calculateProjectProgress($project) {
    // Lógica simples baseada no status
    switch ($project['status']) {
        case 'pending':
            return 0;
        case 'in_progress':
            return 50;
        case 'completed':
            return 100;
        case 'cancelled':
        case 'on_hold':
            return 25;
        default:
            return 0;
    }
}
?>

