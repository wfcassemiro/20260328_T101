<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Verificar se é admin
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

header('Content-Type: application/json');

try {
    // Buscar palestras da tabela upcoming_announcements
    // Usando a mesma query do arquivo palestras_agendadas.php
    $stmt = $pdo->query("
        SELECT 
            id,
            title,
            speaker,
            announcement_date,
            lecture_time,
            description,
            image_path,
            video_embed,
            is_active
        FROM upcoming_announcements
        ORDER BY announcement_date DESC, lecture_time DESC
    ");
    
    $lectures_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $lectures = [];
    
    foreach ($lectures_data as $lecture) {
        if (!empty($lecture['announcement_date'])) {
            $date = new DateTime($lecture['announcement_date']);
            $lectures[] = [
                'id' => $lecture['id'],
                'title' => $lecture['title'] ?? 'Sem título',
                'speaker' => $lecture['speaker'] ?? 'Sem palestrante',
                'formatted_date' => $date->format('d/m/Y'),
                'date_input' => $date->format('Y-m-d'),
                'lecture_time' => !empty($lecture['lecture_time']) ? substr($lecture['lecture_time'], 0, 5) : '19:00',
                'description' => $lecture['description'] ?? ''
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'lectures' => $lectures,
        'count' => count($lectures)
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao buscar palestras: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar palestras',
        'message' => $e->getMessage()
    ]);
}
?>