<?php
// config/dash_functions.php

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('formatCurrency')) {
    function formatCurrency($amount, $currency = 'BRL') {
        // Fallback for environments without Intl extension
        if (!class_exists('NumberFormatter')) {
            return $currency . ' ' . number_format($amount, 2, ',', '.');
        }
        $formatter = new NumberFormatter('pt_BR', NumberFormatter::CURRENCY);
        return $formatter->formatCurrency($amount, $currency);
    }
}

if (!function_exists('getDashboardStats')) {
    function getDashboardStats($user_id) {
        global $pdo;
        $stats = [];
        
        $queries = [
            'total_clients' => "SELECT COUNT(*) FROM dash_clients WHERE user_id = :user_id",
            'total_projects' => "SELECT COUNT(*) FROM dash_projects WHERE user_id = :user_id",
            'active_projects' => "SELECT COUNT(*) FROM dash_projects WHERE user_id = :user_id AND status = 'in_progress'",
            'total_revenue' => "SELECT SUM(total_amount) FROM dash_projects WHERE user_id = :user_id AND status = 'completed'",
            'pending_invoices' => "SELECT COUNT(*) FROM dash_invoices WHERE user_id = :user_id AND status IN ('sent', 'overdue')",
            'pending_revenue' => "SELECT SUM(total_amount) FROM dash_invoices WHERE user_id = :user_id AND status IN ('sent', 'overdue')"
        ];

        foreach ($queries as $key => $sql) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['user_id' => $user_id]);
            $stats[$key] = $stmt->fetchColumn() ?: 0;
        }
        return $stats;
    }
}

if (!function_exists('getRecentProjects')) {
    function getRecentProjects($user_id, $limit = 5) {
        global $pdo;
        $sql = "SELECT p.id, p.title AS project_name, p.status, p.total_amount, c.company AS company_name 
                FROM dash_projects p 
                LEFT JOIN dash_clients c ON p.client_id = c.id 
                WHERE p.user_id = ? 
                ORDER BY p.created_at DESC 
                LIMIT ?";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(1, $user_id);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

if (!function_exists('getRecentInvoices')) {
    function getRecentInvoices($user_id, $limit = 5) {
        global $pdo;
        $sql = "SELECT i.id, i.invoice_number, i.total_amount, i.currency, i.status, c.company AS company_name 
                FROM dash_invoices i
                LEFT JOIN dash_clients c ON i.client_id = c.id
                WHERE i.user_id = ?
                ORDER BY i.issue_date DESC
                LIMIT ?";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(1, $user_id);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

if (!function_exists('getUserSettings')) {
    function getUserSettings($user_id) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM dash_user_settings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $settings = $stmt->fetch();
        return $settings ?: ['default_currency' => 'BRL']; // Retorna um padrão
    }
}

/**
 * Constrói a cláusula SQL e os parâmetros para os relatórios de projetos.
 * Esta função agora está centralizada aqui.
 */
if (!function_exists('build_report_query_and_params')) {
    function build_report_query_and_params($filters, $user_id) {
        $sql = "FROM dash_projects p LEFT JOIN dash_clients c ON c.id = p.client_id WHERE p.user_id = :uid AND DATE(p.created_at) BETWEEN :start AND :end";
        
        $params = [
            ':uid'   => $user_id,
            ':start' => $filters['start_date'],
            ':end'   => $filters['end_date']
        ];

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $sql .= " AND p.status = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['client_id'])) {
            $sql .= " AND p.client_id = :client_id";
            $params[':client_id'] = $filters['client_id'];
        }
        if (!empty($filters['currency'])) {
            $sql .= " AND p.currency = :currency";
            $params[':currency'] = $filters['currency'];
        }
        if (isset($filters['min_value']) && $filters['min_value'] !== '') {
            $sql .= " AND p.total_amount >= :min_value";
            $params[':min_value'] = $filters['min_value'];
        }
        if (isset($filters['max_value']) && $filters['max_value'] !== '') {
            $sql .= " AND p.total_amount <= :max_value";
            $params[':max_value'] = $filters['max_value'];
        }
        
        return ['sql' => $sql, 'params' => $params];
    }
}