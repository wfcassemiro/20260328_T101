<?php
/**
 * Funções Auxiliares para o Dashboard
 * Usado por projects.php e outros arquivos do dashboard
 */

// Incluir database se ainda não foi incluído
if (!isset($pdo)) {
    require_once __DIR__ . '/database.php';
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
            'EUR' => '€'
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
            'inactive' => 'Inativo'
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
            'inactive' => '#808080'
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
