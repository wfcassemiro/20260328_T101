<?php
// save_watch_progress.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método não permitido']);
        exit;
    }

    $user_id       = isset($_POST['user_id']) ? trim((string)$_POST['user_id']) : '';
    $lecture_id    = isset($_POST['lecture_id']) ? trim((string)$_POST['lecture_id']) : '';
    $watched       = isset($_POST['watched_seconds']) ? (int)$_POST['watched_seconds'] : 0;
    $lecture_title = isset($_POST['lecture_title']) ? trim((string)$_POST['lecture_title']) : null;

    if ($user_id === '' || $lecture_id === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'user_id e lecture_id são obrigatórios']);
        exit;
    }

    if ($watched < 0) $watched = 0;

    // Estratégia:
    // - Buscar registro atual pelo par (user_id, lecture_id)
    // - Atualizar last_watched_seconds com o maior valor visto
    // - Acumular accumulated_watch_time com delta positivo
    // - Atualizar resource com lecture_title apenas para compatibilidade (sem usar como chave)

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id, last_watched_seconds, accumulated_watch_time, watch_sessions, watch_segments
        FROM access_logs
        WHERE user_id = ?
          AND lecture_id = ?
          AND action = 'watch'
        ORDER BY updated_at DESC, created_at DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id, $lecture_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $now = date('Y-m-d H:i:s');

    if ($row) {
        $prev_last = (int)($row['last_watched_seconds'] ?? 0);
        $prev_acc  = (int)($row['accumulated_watch_time'] ?? 0);

        // delta só aumenta (se o usuário retroceder o vídeo, não desconta)
        $delta = $watched - $prev_last;
        if ($delta < 0) $delta = 0;

        $new_last = max($prev_last, $watched);
        $new_acc  = $prev_acc + $delta;

        // Atualiza segmentos (opcional / leve)
        $segments = [];
        if (!empty($row['watch_segments'])) {
            $decoded = json_decode((string)$row['watch_segments'], true);
            if (is_array($decoded)) $segments = $decoded;
        }
        // registra um ponto simples; não explode a coluna
        $segments[] = ['t' => time(), 's' => $watched];
        if (count($segments) > 200) {
            $segments = array_slice($segments, -200);
        }
        $segments_json = json_encode($segments, JSON_UNESCAPED_UNICODE);

        $update = $pdo->prepare("
            UPDATE access_logs
            SET resource = COALESCE(?, resource),
                last_watched_seconds = ?,
                accumulated_watch_time = ?,
                watch_sessions = COALESCE(watch_sessions, 1),
                watch_segments = ?,
                updated_at = ?
            WHERE id = ?
        ");
        $update->execute([
            $lecture_title ?: null,
            $new_last,
            $new_acc,
            $segments_json,
            $now,
            $row['id']
        ]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'mode' => 'update',
            'last_watched_seconds' => $new_last,
            'accumulated_watch_time' => $new_acc
        ]);
        exit;
    }

    // Não achou por lecture_id: tenta migrar o legado por resource (título), se vier lecture_title
    if ($lecture_title) {
        $legacy = $pdo->prepare("
            SELECT id, last_watched_seconds, accumulated_watch_time, watch_sessions, watch_segments
            FROM access_logs
            WHERE user_id = ?
              AND resource = ?
              AND action = 'watch'
              AND (lecture_id IS NULL OR lecture_id = '')
            ORDER BY updated_at DESC, created_at DESC, id DESC
            LIMIT 1
        ");
        $legacy->execute([$user_id, $lecture_title]);
        $legacyRow = $legacy->fetch(PDO::FETCH_ASSOC);

        if ($legacyRow) {
            // “Reaproveita” o registro legado: só preenche lecture_id e segue como update
            $prev_last = (int)($legacyRow['last_watched_seconds'] ?? 0);
            $prev_acc  = (int)($legacyRow['accumulated_watch_time'] ?? 0);

            $delta = $watched - $prev_last;
            if ($delta < 0) $delta = 0;

            $new_last = max($prev_last, $watched);
            $new_acc  = $prev_acc + $delta;

            $segments = [];
            if (!empty($legacyRow['watch_segments'])) {
                $decoded = json_decode((string)$legacyRow['watch_segments'], true);
                if (is_array($decoded)) $segments = $decoded;
            }
            $segments[] = ['t' => time(), 's' => $watched];
            if (count($segments) > 200) $segments = array_slice($segments, -200);

            $updateLegacy = $pdo->prepare("
                UPDATE access_logs
                SET lecture_id = ?,
                    last_watched_seconds = ?,
                    accumulated_watch_time = ?,
                    watch_segments = ?,
                    updated_at = ?
                WHERE id = ?
            ");
            $updateLegacy->execute([
                $lecture_id,
                $new_last,
                $new_acc,
                json_encode($segments, JSON_UNESCAPED_UNICODE),
                $now,
                $legacyRow['id']
            ]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'mode' => 'migrated_legacy',
                'last_watched_seconds' => $new_last,
                'accumulated_watch_time' => $new_acc
            ]);
            exit;
        }
    }

    // Insere novo
    $segments = [['t' => time(), 's' => $watched]];

    $insert = $pdo->prepare("
        INSERT INTO access_logs
            (user_id, action, resource, lecture_id, ip_address, user_agent, created_at, updated_at,
             last_watched_seconds, accumulated_watch_time, watch_sessions, skip_count, certificate_generated, watch_segments)
        VALUES
            (?, 'watch', ?, ?, ?, ?, NOW(), NOW(),
             ?, ?, 1, 0, 0, ?)
    ");

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $insert->execute([
        $user_id,
        $lecture_title ?: null,
        $lecture_id,
        $ip,
        $ua,
        $watched,
        $watched, // primeira amostra: acumulado = watched
        json_encode($segments, JSON_UNESCAPED_UNICODE)
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'mode' => 'insert',
        'last_watched_seconds' => $watched,
        'accumulated_watch_time' => $watched
    ]);
    exit;

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao salvar progresso: ' . $e->getMessage()]);
    exit;
}