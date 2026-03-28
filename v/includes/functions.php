<?php
/**
 * Funções Auxiliares - Time Tracker
 */

// Gerar UUID v4
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Formatar segundos para HH:MM:SS
function formatDuration($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
}

// Formatar segundos para formato legível
function formatDurationHuman($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    if ($hours > 0) {
        return $hours . 'h ' . $minutes . 'min';
    } elseif ($minutes > 0) {
        return $minutes . 'min';
    } else {
        return $seconds . 's';
    }
}

// Converter HH:MM:SS para segundos
function durationToSeconds($duration) {
    $parts = explode(':', $duration);
    if (count($parts) === 3) {
        return ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2];
    }
    return 0;
}

// Sanitizar entrada
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Validar cor hexadecimal
function isValidColor($color) {
    return preg_match('/^#[0-9A-Fa-f]{6}$/', $color);
}

// Resposta JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Resposta de erro
function jsonError($message, $status = 400) {
    jsonResponse(['success' => false, 'error' => $message], $status);
}

// Resposta de sucesso
function jsonSuccess($data = [], $message = 'Success') {
    jsonResponse(array_merge(['success' => true, 'message' => $message], $data));
}

// Calcular duração entre duas datas
function calculateDuration($startTime, $endTime, $pausedDuration = 0) {
    $start = new DateTime($startTime);
    $end = new DateTime($endTime);
    $diff = $end->getTimestamp() - $start->getTimestamp();
    return max(0, $diff - $pausedDuration);
}

// Cores padrão para projetos
function getDefaultColors() {
    return [
        '#7B61FF', // Roxo
        '#FF6B9D', // Rosa
        '#4ECDC4', // Turquesa
        '#FFE66D', // Amarelo
        '#FF6B6B', // Vermelho
        '#95E1D3', // Verdeágua
        '#FFA07A', // Salmão
        '#87CEEB', // Azul céu
        '#DDA0DD', // Ameixa
        '#F0E68C', // Caqui
    ];
}

// Obter iniciais do nome
function getInitials($name) {
    $words = explode(' ', $name);
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

// Exportar para CSV
function exportToCSV($data, $filename) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF"; // BOM para UTF-8
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        // Cabeçalhos
        fputcsv($output, array_keys($data[0]));
        
        // Dados
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

// Obter período de datas
function getDateRange($period) {
    $end = new DateTime();
    $start = clone $end;
    
    switch ($period) {
        case 'today':
            $start->setTime(0, 0, 0);
            break;
        case 'yesterday':
            $start->modify('-1 day')->setTime(0, 0, 0);
            $end->modify('-1 day')->setTime(23, 59, 59);
            break;
        case 'this_week':
            $start->modify('monday this week')->setTime(0, 0, 0);
            break;
        case 'last_week':
            $start->modify('monday last week')->setTime(0, 0, 0);
            $end->modify('sunday last week')->setTime(23, 59, 59);
            break;
        case 'this_month':
            $start->modify('first day of this month')->setTime(0, 0, 0);
            break;
        case 'last_month':
            $start->modify('first day of last month')->setTime(0, 0, 0);
            $end->modify('last day of last month')->setTime(23, 59, 59);
            break;
        case 'this_year':
            $start->setDate($start->format('Y'), 1, 1)->setTime(0, 0, 0);
            break;
        default:
            $start->setTime(0, 0, 0);
    }
    
    return [
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $end->format('Y-m-d H:i:s')
    ];
}
