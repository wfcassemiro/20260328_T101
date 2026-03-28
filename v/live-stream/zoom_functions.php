<?php
/**
 * Funções para Interação com a API do Zoom e o Banco de Dados Local.
 * Este arquivo contém toda a lógica para criar, buscar, deletar e sincronizar reuniões.
 */

// Inclui o arquivo de autenticação que lida com o token de acesso.
require_once __DIR__ . '/zoom_auth.php';

date_default_timezone_set('America/Sao_Paulo');

/**
 * Escreve uma mensagem de log no arquivo de debug.
 *
 * @param string $message A mensagem a ser registrada.
 */
function writeToZoomLog($message) {
    $logFile = __DIR__ . '/zoom_debug_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

/**
 * Cria a tabela 'zoom_meetings' no banco de dados se ela não existir.
 */
function createZoomMeetingsTable() {
    global $pdo;
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS zoom_meetings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                meeting_id BIGINT UNIQUE NOT NULL,
                topic VARCHAR(255) NOT NULL,
                start_time DATETIME NOT NULL,
                duration INT NOT NULL,
                agenda TEXT,
                join_url VARCHAR(255) NOT NULL,
                password VARCHAR(255),
                show_live TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    } catch (PDOException $e) {
        die("Erro ao criar tabela do Zoom: " . $e->getMessage());
    }
}

/**
 * Busca reuniões futuras do banco de dados local.
 *
 * @param int $limit O número máximo de reuniões a serem retornadas.
 * @return array Lista de reuniões.
 */
function getActiveMeetingsFromDatabase($limit = 10) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM zoom_meetings WHERE start_time >= NOW() ORDER BY start_time ASC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        writeToZoomLog("Erro ao buscar reuniões do BD: " . $e->getMessage());
        return [];
    }
}

/**
 * Busca a reunião que está marcada como 'show_live' e que está acontecendo agora.
 *
 * @return array|null A reunião ativa ou null se nenhuma for encontrada.
 */
function getCurrentMeeting() {
    global $pdo;
    try {
        $stmt = $pdo->query("
            SELECT * FROM zoom_meetings 
            WHERE show_live = 1 
            AND start_time <= NOW() 
            AND (start_time + INTERVAL duration MINUTE) >= NOW()
            LIMIT 1
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        writeToZoomLog("Erro ao buscar reunião atual: " . $e->getMessage());
        return null;
    }
}

/**
 * Alterna a exibição de uma reunião no live stream.
 * Garante que apenas uma reunião possa estar ativa por vez.
 *
 * @param string $meetingId O ID da reunião a ser atualizada.
 * @param int $showLive 1 para exibir, 0 para ocultar.
 * @return bool True em sucesso, false em falha.
 */
function toggleMeetingLiveDisplay($meetingId, $showLive) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        // Desativa TODAS as outras reuniões para garantir que apenas uma fique ativa.
        if ($showLive == 1) {
            $stmtReset = $pdo->prepare("UPDATE zoom_meetings SET show_live = 0 WHERE meeting_id != ?");
            $stmtReset->execute([$meetingId]);
        }
        
        // Atualiza a reunião específica com o status desejado (0 ou 1).
        $stmtUpdate = $pdo->prepare("UPDATE zoom_meetings SET show_live = ? WHERE meeting_id = ?");
        $stmtUpdate->execute([$showLive, $meetingId]);
        
        $pdo->commit();
        return true;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        writeToZoomLog("ERRO no toggleMeetingLiveDisplay: " . $e->getMessage());
        return false;
    }
}

/**
 * Testa a autenticação com a API do Zoom buscando informações do usuário.
 *
 * @return array Resultado com status de sucesso e dados ou mensagem de erro.
 */
function testZoomAuth() {
    $result = zoomApiRequest('/users/me');
    if ($result['success']) {
        return ['success' => true, 'user' => $result['data']];
    }
    return ['success' => false, 'message' => $result['error']];
}

/**
 * Cria uma nova reunião no Zoom e a salva no banco de dados local.
 *
 * @param string $topic Título da reunião.
 * @param string $startTime Data e hora de início.
 * @param int $duration Duração em minutos.
 * @param string $agenda Descrição opcional.
 * @return array Resultado da operação.
 */
function createZoomMeeting($topic, $startTime, $duration, $agenda = '') {
    $postData = [
        'topic' => $topic,
        'type' => 2, // 2 = Reunião agendada
        'start_time' => (new DateTime($startTime))->format('Y-m-d\TH:i:s'),
        'duration' => $duration,
        'agenda' => $agenda,
        'timezone' => 'America/Sao_Paulo',
        'settings' => ['join_before_host' => true]
    ];

    $result = zoomApiRequest('/users/me/meetings', $postData, 'POST');

    if ($result['success']) {
        global $pdo;
        $meeting = $result['data'];
        try {
            $stmt = $pdo->prepare("
                INSERT INTO zoom_meetings (meeting_id, topic, start_time, duration, agenda, join_url, password) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $meeting['id'],
                $meeting['topic'],
                $meeting['start_time'],
                $meeting['duration'],
                $meeting['agenda'] ?? '',
                $meeting['join_url'],
                $meeting['password'] ?? ''
            ]);
            return ['success' => true, 'meeting' => $meeting];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erro ao salvar no BD: ' . $e->getMessage()];
        }
    }
    return ['success' => false, 'error' => $result['error']];
}

/**
 * Adiciona uma reunião existente do Zoom ao banco de dados local.
 *
 * @param string $meetingIdOrUrl ID ou URL da reunião.
 * @return array Resultado da operação.
 */
function addExistingMeeting($meetingIdOrUrl) {
    // Extrai o ID numérico da URL ou do texto
    preg_match('/(\d{10,11})/', $meetingIdOrUrl, $matches);
    if (empty($matches)) {
        return ['success' => false, 'error' => 'ID ou URL da reunião inválido.'];
    }
    $meetingId = $matches[0];

    $result = zoomApiRequest('/meetings/' . $meetingId);

    if ($result['success']) {
        global $pdo;
        $meeting = $result['data'];
        try {
            $stmt = $pdo->prepare("
                REPLACE INTO zoom_meetings (meeting_id, topic, start_time, duration, agenda, join_url, password) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $meeting['id'],
                $meeting['topic'],
                $meeting['start_time'],
                $meeting['duration'],
                $meeting['agenda'] ?? '',
                $meeting['join_url'],
                $meeting['password'] ?? ''
            ]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erro ao salvar no BD: ' . $e->getMessage()];
        }
    }
    return ['success' => false, 'error' => $result['error']];
}

/**
 * Deleta uma reunião do Zoom e do banco de dados local.
 *
 * @param string $meetingId ID da reunião.
 * @return array Resultado da operação.
 */
function deleteZoomMeeting($meetingId) {
    global $pdo;
    
    // Deleta do Zoom (API não retorna erro se já não existir)
    zoomApiRequest('/meetings/' . $meetingId, [], 'DELETE');

    // Deleta do banco de dados local
    try {
        $stmt = $pdo->prepare("DELETE FROM zoom_meetings WHERE meeting_id = ?");
        $stmt->execute([$meetingId]);
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Erro ao deletar do BD: ' . $e->getMessage()];
    }
}

/**
 * Sincroniza todas as reuniões futuras do Zoom com o banco de dados local.
 *
 * @return array Resultado da operação.
 */
function syncZoomMeetings() {
    $result = zoomApiRequest('/users/me/meetings?type=upcoming&page_size=300');

    if (!$result['success']) {
        return ['success' => false, 'error' => $result['error']];
    }

    global $pdo;
    $meetings = $result['data']['meetings'] ?? [];
    $syncedCount = 0;

    try {
        foreach ($meetings as $meeting) {
            $stmt = $pdo->prepare("
                REPLACE INTO zoom_meetings (meeting_id, topic, start_time, duration, agenda, join_url, password) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $meeting['id'],
                $meeting['topic'],
                $meeting['start_time'],
                $meeting['duration'],
                $meeting['agenda'] ?? '',
                $meeting['join_url'],
                $meeting['password'] ?? ''
            ]);
            $syncedCount++;
        }
        return ['success' => true, 'synced' => $syncedCount];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Erro ao sincronizar com o BD: ' . $e->getMessage()];
    }
}