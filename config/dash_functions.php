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
    global $pdo, $dash_config; // Acessa $dash_config para obter as moedas

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

    // Receita total por moeda
    foreach ($dash_config['currencies'] as $currency_code) {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM dash_invoices WHERE user_id = ? AND status = 'paid' AND currency = ?");
        $stmt->execute([$user_id, $currency_code]);
        $stats['total_revenue_' . $currency_code] = $stmt->fetchColumn();
    }

    // Receita pendente por moeda
    foreach ($dash_config['currencies'] as $currency_code) {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM dash_invoices WHERE user_id = ? AND status = 'sent' AND currency = ?");
        $stmt->execute([$user_id, $currency_code]);
        $stats['pending_revenue_' . $currency_code] = $stmt->fetchColumn();
    }
    
    // Para manter compatibilidade com o HTML antigo que somava tudo:
    $stats['total_revenue'] = array_sum(array_filter($stats, function($key) { return str_starts_with($key, 'total_revenue_'); }, ARRAY_FILTER_USE_KEY));
    $stats['pending_revenue'] = array_sum(array_filter($stats, function($key) { return str_starts_with($key, 'pending_revenue_'); }, ARRAY_FILTER_USE_KEY));


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
 * Obter projetos recentes
 */
function getRecentProjects($user_id, $limit = 5) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT p.id, p.title AS project_name, c.company AS company_name, c.name AS contact_name, p.created_at, p.status, p.source_language, p.target_language, p.deadline, p.currency FROM dash_projects p LEFT JOIN dash_clients c ON p.client_id = c.id WHERE p.user_id = ? ORDER BY p.created_at DESC LIMIT ?");
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obter faturas recentes
 */
function getRecentInvoices($user_id, $limit = 5) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT i.invoice_number, c.company AS company_name, c.name AS contact_name, i.created_at, i.issue_date AS invoice_date, i.total_amount, i.status, i.due_date, i.currency FROM dash_invoices i LEFT JOIN dash_clients c ON i.client_id = c.id WHERE i.user_id = ? ORDER BY i.created_at DESC LIMIT ?");
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Gerar fatura a partir de um projeto
 */
function generateInvoiceFromProject($user_id, $project_id) {
    global $pdo;

    $dash_error_log = __DIR__ . '/../logs/dash_errors.log';
    if (!is_dir(dirname($dash_error_log))) {
        mkdir(dirname($dash_error_log), 0755, true);
    }

    try {
        $pdo->beginTransaction();

        // 1. Obter detalhes do projeto
        $stmt = $pdo->prepare("SELECT p.id, p.client_id, p.title, p.total_amount, p.currency, p.description, p.source_language, p.target_language, p.service_type, p.po_number, p.word_count, p.rate_per_word, p.character_count, p.rate_per_character FROM dash_projects p WHERE p.id = ? AND p.user_id = ?");
        $stmt->execute([$project_id, $user_id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$project) {
            $pdo->rollBack();
            file_put_contents($dash_error_log, "[" . date('Y-m-d H:i:s') . "] ERROR: generateInvoiceFromProject - Projeto ID {$project_id} para o usuário {$user_id} não encontrado ou não pertence a ele.\n", FILE_APPEND);
            return false;
        }

        // 2. Verificar se já existe uma fatura para este projeto (opcional, mas boa prática)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dash_invoice_items WHERE project_id = ?");
        $stmt->execute([$project_id]);
        if ($stmt->fetchColumn() > 0) {
            $pdo->rollBack();
            file_put_contents($dash_error_log, "[" . date('Y-m-d H:i:s') . "] ERROR: generateInvoiceFromProject - Fatura já existe para o Projeto ID {$project_id}.\n", FILE_APPEND);
            return false;
        }

        // 3. Obter configurações do usuário para impostos padrão
        $user_settings = getUserSettings($user_id);
        $tax_rate = $user_settings['default_tax_rate'] ?? 0.00;

        // 4. Calcular valores da fatura
        $subtotal = $project['total_amount'];
        $tax_amount = $subtotal * ($tax_rate / 100);
        $total_amount = $subtotal + $tax_amount;
        $currency = $project['currency']; // Moeda do projeto

        // 5. Gerar número da fatura
        $invoice_number = generateInvoiceNumber($user_id);
        $invoice_date = date('Y-m-d');
        $due_date = date('Y-m-d', strtotime('+30 days')); // Exemplo: Vencimento em 30 dias

        // 6. Inserir a fatura principal
        $stmt = $pdo->prepare("INSERT INTO dash_invoices (user_id, client_id, invoice_number, issue_date, due_date, subtotal, tax_rate, tax_amount, total_amount, currency, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([
            $user_id,
            $project['client_id'],
            $invoice_number,
            $invoice_date,
            $due_date,
            $subtotal,
            $tax_rate,
            $tax_amount,
            $total_amount,
            $currency,
            'sent', // Status inicial da fatura
            "Fatura gerada automaticamente do projeto: " . $project['title']
        ]);

        if (!$result) {
            $pdo->rollBack();
            file_put_contents($dash_error_log, "[" . date('Y-m-d H:i:s') . "] ERROR: generateInvoiceFromProject - Falha ao inserir fatura principal para o Projeto ID {$project_id}.\n", FILE_APPEND);
            return false;
        }

        $invoice_id = $pdo->lastInsertId();

        // 7. Inserir item da fatura
        $item_description = "Projeto: " . $project['title'] . " (" . $project['source_language'] . " -> " . $project['target_language'] . ", " . $project['service_type'] . ")";
        if (!empty($project['po_number'])) {
            $item_description = "PO: " . $project['po_number'] . " - " . $item_description;
        }
        if ($project['word_count'] > 0 && $project['rate_per_word'] > 0) {
            $item_description .= " | Palavras: " . $project['word_count'] . " | Taxa/Palavra: " . formatCurrency($project['rate_per_word'], $project['currency']);
        } elseif ($project['character_count'] > 0 && $project['rate_per_character'] > 0) {
            $item_description .= " | Caracteres: " . $project['character_count'] . " | Taxa/Caractere: " . formatCurrency($project['rate_per_character'], $project['currency']);
        }

        $stmt = $pdo->prepare("INSERT INTO dash_invoice_items (invoice_id, project_id, description, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([
            $invoice_id,
            $project['id'],
            $item_description,
            1.00, // Quantidade 1 para o projeto inteiro
            $project['total_amount'],
            $project['total_amount']
        ]);

        if (!$result) {
            $pdo->rollBack();
            file_put_contents($dash_error_log, "[" . date('Y-m-d H:i:s') . "] ERROR: generateInvoiceFromProject - Falha ao inserir item da fatura para o Projeto ID {$project_id}.\n", FILE_APPEND);
            return false;
        }

        $pdo->commit();
        return $invoice_number; // Retorna o número da fatura gerada
    } catch (PDOException $e) {
        $pdo->rollBack();
        file_put_contents($dash_error_log, "[" . date('Y-m-d H:i:s') . "] PDOException in generateInvoiceFromProject: " . $e->getMessage() . " (SQLSTATE: " . $e->getCode() . ")\n", FILE_APPEND);
        return false;
    } catch (Exception $e) {
        file_put_contents($dash_error_log, "[" . date('Y-m-d H:i:s') . "] Exception in generateInvoiceFromProject: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

/**
 * Obter atividades recentes (função original para lista combinada)
 * Esta função não será usada diretamente pelo index.php após as novas funções acima,
 * mas é mantida aqui caso seja usada em outro local ou para futuras adaptações.
 */
function getRecentActivities($user_id, $limit = 10) {
    global $pdo;

    $activities = [];

    // Projetos recentes para atividades
    $stmt = $pdo->prepare("SELECT p.title AS project_name, c.company AS company_name, p.created_at, 'project' as type FROM dash_projects p LEFT JOIN dash_clients c ON p.client_id = c.id WHERE p.user_id = ? ORDER BY p.created_at DESC LIMIT ?");
    $stmt->execute([$user_id, $limit]);
    $projects_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($projects_activities as $project) {
        $activities[] = [
            'type' => 'project',
            'title' => 'Novo projeto criado',
            'description' => $project['project_name'] . ' - ' . $project['company_name'],
            'date' => $project['created_at']
        ];
    }

    // Faturas recentes para atividades
    $stmt = $pdo->prepare("SELECT i.invoice_number, c.company AS company_name, i.created_at, 'invoice' as type FROM dash_invoices i LEFT JOIN dash_clients c ON i.client_id = c.id WHERE i.user_id = ? ORDER BY i.created_at DESC LIMIT ?");
    $stmt->execute([$user_id, $limit]);
    $invoices_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($invoices_activities as $invoice) {
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
    if (is_null($amount)) {
        $amount = 0;
    }
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