<?php
/**
 * Funções Auxiliares para o Dashboard
 * Usado por projects.php e outros arquivos do dashboard
 * VERSÃO: Original + Correção de Faturas (UniqID)
 */

// Incluir database se ainda não foi incluído
if (!isset($pdo)) {
    if (file_exists(__DIR__ . '/database.php')) {
        require_once __DIR__ . '/database.php';
    } elseif (file_exists(__DIR__ . '/../config/database.php')) {
        require_once __DIR__ . '/../config/database.php';
    }
}

// Configurações de sessão
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_domain', '.translators101.com');
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

/**
 * Sanitizar entrada de dados
 */
if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Formatar moeda
 */
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount, $currency = 'BRL') {
        $symbols = [
            'BRL' => 'R$',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£'
        ];
        
        $symbol = $symbols[$currency] ?? $currency;
        return $symbol . ' ' . number_format((float)$amount, 2, ',', '.');
    }
}

/**
 * Formatar data brasileira
 */
if (!function_exists('formatDateBR')) {
    function formatDateBR($date) {
        if (empty($date) || $date === '0000-00-00') {
            return '-';
        }
        
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj) {
            return '-';
        }
        
        return $dateObj->format('d/m/Y');
    }
}

/**
 * Formatar data e hora brasileira
 */
if (!function_exists('formatDateTimeBR')) {
    function formatDateTimeBR($datetime) {
        if (empty($datetime)) {
            return '-';
        }
        
        $dateObj = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
        if (!$dateObj) {
            return '-';
        }
        
        return $dateObj->format('d/m/Y H:i');
    }
}

/**
 * Obter status em português
 */
if (!function_exists('getStatusLabel')) {
    function getStatusLabel($status) {
        $labels = [
            'pending' => 'Pendente',
            'in_progress' => 'Em Andamento',
            'completed' => 'Concluído',
            'cancelled' => 'Cancelado',
            'active' => 'Ativo',
            'inactive' => 'Inativo',
            'draft' => 'Rascunho',
            'sent' => 'Enviado',
            'paid' => 'Pago',
            'overdue' => 'Vencido'
        ];
        
        return $labels[$status] ?? ucfirst($status);
    }
}

/**
 * Obter cor do status
 */
if (!function_exists('getStatusColor')) {
    function getStatusColor($status) {
        $colors = [
            'pending' => '#FFA500',
            'in_progress' => '#4169E1',
            'completed' => '#32CD32',
            'cancelled' => '#DC143C',
            'active' => '#32CD32',
            'inactive' => '#808080',
            'draft' => '#808080',
            'paid' => '#32CD32',
            'overdue' => '#DC143C'
        ];
        
        return $colors[$status] ?? '#808080';
    }
}

/**
 * Obter prioridade em português
 */
if (!function_exists('getPriorityLabel')) {
    function getPriorityLabel($priority) {
        $labels = [
            'low' => 'Baixa',
            'medium' => 'Média',
            'high' => 'Alta',
            'urgent' => 'Urgente'
        ];
        
        return $labels[$priority] ?? ucfirst($priority);
    }
}

/**
 * Calcular dias restantes até o deadline
 */
if (!function_exists('getDaysUntilDeadline')) {
    function getDaysUntilDeadline($deadline) {
        if (empty($deadline) || $deadline === '0000-00-00') {
            return null;
        }
        
        $now = new DateTime();
        $deadlineDate = new DateTime($deadline);
        $interval = $now->diff($deadlineDate);
        
        $days = $interval->days;
        if ($interval->invert) {
            $days = -$days;
        }
        
        return $days;
    }
}

/**
 * Debug - var_dump formatado
 */
if (!function_exists('dd')) {
    function dd(...$vars) {
        echo '<pre style="background:#000;color:#0f0;padding:20px;font-family:monospace;">';
        foreach ($vars as $var) {
            var_dump($var);
        }
        echo '</pre>';
        die();
    }
}

// Funções de Auth (Garantia)
if (!function_exists('isLoggedIn')) { function isLoggedIn() { return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']); } }
if (!function_exists('isAdmin')) { function isAdmin() { return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'; } }
if (!function_exists('isSubscriber')) { function isSubscriber() { if (isAdmin()) return true; return isLoggedIn() && isset($_SESSION['is_subscriber']) && $_SESSION['is_subscriber'] == 1; } }
if (!function_exists('hasVideotecaAccess')) { function hasVideotecaAccess() { return isAdmin() || isSubscriber(); } }
if (!function_exists('isSuperAdmin')) { function isSuperAdmin() { return isLoggedIn() && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true; } }
if (!function_exists('requireAuth')) {
    function requireAuth() {
        if (!isLoggedIn()) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Não autenticado']);
                exit;
            } else {
                header('Location: /login.php');
                exit;
            }
        }
    }
}
if (!function_exists('getCurrentUserId')) { function getCurrentUserId() { return $_SESSION['user_id'] ?? null; } }


// ==============================================================================
// NOVA FUNÇÃO DE FATURA (CORRIGIDA PARA EVITAR DUPLICIDADE)
// ==============================================================================

if (!function_exists('generateInvoiceFromProject')) {
    function generateInvoiceFromProject($pdo, $project_id, $user_id) {
        // 1. Buscar Projeto
        $stmt = $pdo->prepare("SELECT * FROM dash_projects WHERE id = ? AND user_id = ?");
        $stmt->execute([$project_id, $user_id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$project) return false;

        // 2. Criar Fatura (Header)
        // GERAÇÃO DE NÚMERO ÚNICO: INV + DATA + ID + CÓDIGO ALEATÓRIO
        // Isso resolve o erro "Duplicate entry" definitivamente
        $number = 'INV-' . date('Ymd') . '-' . $project['id'] . '-' . strtoupper(substr(uniqid(), -4));
        
        $date = date('Y-m-d');
        $due_date = date('Y-m-d', strtotime('+30 days'));

        $stmtInv = $pdo->prepare("
            INSERT INTO dash_invoices (user_id, project_id, client_id, number, date, due_date, status, currency, total) 
            VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, ?)
        ");
        
        $stmtInv->execute([
            $user_id,
            $project['id'],
            $project['client_id'],
            $number,
            $date,
            $due_date,
            $project['currency'],
            $project['total_amount']
        ]);
        
        $invoice_id = $pdo->lastInsertId();

        // 3. Buscar Tarefas e Criar Itens
        $stmtJobs = $pdo->prepare("SELECT * FROM dash_jobs WHERE project_id = ?");
        $stmtJobs->execute([$project_id]);
        $jobs = $stmtJobs->fetchAll(PDO::FETCH_ASSOC);

        $stmtItem = $pdo->prepare("INSERT INTO dash_invoice_items (invoice_id, description, quantity, unit_price, total) VALUES (?, ?, ?, ?, ?)");

        if (count($jobs) > 0) {
            foreach ($jobs as $job) {
                $desc = $job['service'];
                if ($job['lang_from'] && $job['lang_to']) {
                    $desc .= " (" . $job['lang_from'] . " > " . $job['lang_to'] . ")";
                } elseif ($job['lang_to']) {
                    $desc .= " (" . $job['lang_to'] . ")";
                }

                $stmtItem->execute([
                    $invoice_id,
                    $desc,
                    $job['quantity'],
                    $job['price_per_unit'],
                    $job['total_cost']
                ]);
            }
        } else {
            // Item genérico caso não haja tarefas
            $stmtItem->execute([
                $invoice_id,
                "Serviços do Projeto: " . $project['title'],
                1,
                $project['total_amount'],
                $project['total_amount']
            ]);
        }

        return $invoice_id;
    }
}
?>