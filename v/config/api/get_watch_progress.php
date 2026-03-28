<?php
// get_watch_progress.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/database.php';

try {
    $user_id    = isset($_GET['user_id']) ? trim((string)$_GET['user_id']) : '';
    $lecture_id = isset($_GET['lecture_id']) ? trim((string)$_GET['lecture_id']) : '';
    $title      = isset($_GET['lecture_title']) ? trim((string)$_GET['lecture_title']) : '';

    if ($user_id === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'user_id é obrigatório']);
        exit;
    }

    if ($lecture_id !== '') {
        $stmt = $pdo->prepare("
            SELECT last_watched_seconds, accumulated_watch_time
            FROM access_logs
            WHERE user_id = ?
              AND lecture_id = ?
              AND action = 'watch'
            ORDER BY updated_at DESC, created_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([$user_id, $lecture_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            echo json_encode([
                'success' => true,
                'last_watched_seconds' => (int)($row['last_watched_seconds'] ?? 0),
                'accumulated_watch_time' => (int)($row['accumulated_watch_time'] ?? 0),
                'source' => 'lecture_id'
            ]);
            exit;
        }
    }

    // fallback legado por título (resource)
    if ($title !== '') {
        $stmt = $pdo->prepare("
            SELECT last_watched_seconds, accumulated_watch_time
            FROM access_logs
            WHERE user_id = ?
              AND resource = ?
              AND action = 'watch'
            ORDER BY updated_at DESC, created_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([$user_id, $title]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'last_watched_seconds' => (int)($row['last_watched_seconds'] ?? 0),
            'accumulated_watch_time' => (int)($row['accumulated_watch_time'] ?? 0),
            'source' => 'resource_fallback'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'last_watched_seconds' => 0,
        'accumulated_watch_time' => 0,
        'source' => 'none'
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao carregar progresso: ' . $e->getMessage()]);
    exit;
}