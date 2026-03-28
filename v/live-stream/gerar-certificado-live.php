<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../config/database.php';

// Função auxiliar para logs
function writeToLiveCertLog($message) {
    $log_file = __DIR__ . '/../certificate_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] [LIVE_CERT] $message\n", FILE_APPEND);
}

writeToLiveCertLog("Script gerar-certificado-live.php iniciado");

header('Content-Type: application/json');

// Verificar se o usuário está logado e é admin
if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas admins podem gerar certificados.']);
    writeToLiveCertLog("ERRO: Usuário não autorizado");
    exit;
}

// Obter dados da requisição
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos recebidos']);
    writeToLiveCertLog("ERRO: Dados inválidos recebidos");
    exit;
}

$target_user_id = $input['user_id'] ?? '';
$live_date = $input['live_date'] ?? date('Y-m-d');

writeToLiveCertLog("Dados recebidos - User: $target_user_id, Date: $live_date");

// Validação básica
if (empty($target_user_id)) {
    echo json_encode(['success' => false, 'message' => 'ID do usuário é obrigatório']);
    writeToLiveCertLog("ERRO: User ID ausente");
    exit;
}

try {
    // Buscar dados do usuário
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$target_user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        writeToLiveCertLog("ERRO: Usuário não encontrado - ID: $target_user_id");
        exit;
    }

    // Buscar dados de presença
    $stmt = $pdo->prepare("SELECT * FROM live_presence WHERE user_id = ? AND live_date = ?");
    $stmt->execute([$target_user_id, $live_date]);
    $presence = $stmt->fetch();

    if (!$presence) {
        echo json_encode(['success' => false, 'message' => 'Registro de presença não encontrado para esta data']);
        writeToLiveCertLog("ERRO: Presença não encontrada - User: $target_user_id, Date: $live_date");
        exit;
    }

    // Verificar tempo mínimo (30 minutos)
    if ($presence['total_minutes'] < 30) {
        echo json_encode([
            'success' => false,
            'message' => "Tempo insuficiente. Usuário ficou apenas {$presence['total_minutes']} minutos (mínimo: 30 min)"
        ]);
        writeToLiveCertLog("ERRO: Tempo insuficiente - {$presence['total_minutes']} minutos");
        exit;
    }

    // BUSCAR INFORMAÇÕES DA PALESTRA DO DIA AUTOMATICAMENTE
    $stmt = $pdo->prepare("SELECT id, title, speaker FROM upcoming_announcements WHERE announcement_date = ? AND is_active = 1 ORDER BY lecture_time ASC LIMIT 1");
    $stmt->execute([$live_date]);
    $announcement = $stmt->fetch();

    // Definir título e palestrante
    if ($announcement) {
        $lecture_title = $announcement['title'];
        $speaker_name = $announcement['speaker'];
        writeToLiveCertLog("Palestra encontrada no BD - Título: $lecture_title, Palestrante: $speaker_name");
    } else {
        $lecture_title = 'Palestra ao Vivo - ' . date('d/m/Y', strtotime($live_date));
        $speaker_name = 'Translators101';
        writeToLiveCertLog("AVISO: Nenhuma palestra agendada para $live_date - Usando valores padrão");
    }

    // Calcular duração em horas (arredondar para próxima meia hora)
    $duration_hours = $presence['total_minutes'] / 60;
    if ($duration_hours <= 0.5) {
        $duration_hours = 0.5;
    } elseif ($duration_hours <= 1.0) {
        $duration_hours = 1.0;
    } elseif ($duration_hours <= 1.5) {
        $duration_hours = 1.5;
    } else {
        $duration_hours = ceil($duration_hours * 2) / 2;
    }

    // Criar um lecture_id único para a live
    $live_lecture_id = 'live-' . $live_date;

    // Verificar se existe registro na tabela lectures para esta live
    $stmtCheckLecture = $pdo->prepare("SELECT id FROM lectures WHERE id = ?");
    $stmtCheckLecture->execute([$live_lecture_id]);
    $existingLecture = $stmtCheckLecture->fetch();

    if (!$existingLecture) {
        writeToLiveCertLog("Criando registro temporário na tabela lectures para: $live_lecture_id");
        
        $insertLectureSql = "INSERT INTO lectures (id, title, speaker, duration_minutes, description, created_at) 
                            VALUES (?, ?, ?, ?, ?, NOW())
                            ON DUPLICATE KEY UPDATE title = VALUES(title), speaker = VALUES(speaker)";
        $stmtInsertLecture = $pdo->prepare($insertLectureSql);
        $stmtInsertLecture->execute([
            $live_lecture_id,
            $lecture_title,
            $speaker_name,
            $presence['total_minutes'],
            'Transmissão ao vivo - ' . date('d/m/Y', strtotime($live_date))
        ]);
        
        writeToLiveCertLog("Registro criado com sucesso na tabela lectures");
    }

    // Verificar se já existe certificado para esta live
    $stmt = $pdo->prepare("SELECT id FROM certificates WHERE user_id = ? AND lecture_id = ?");
    $stmt->execute([$target_user_id, $live_lecture_id]);
    $existing_certificate = $stmt->fetch();

    if ($existing_certificate) {
        // Verificar se o arquivo PDF existe
        $pdf_path = __DIR__ . '/../certificates/' . $existing_certificate['id'] . '.pdf';
        
        if (!file_exists($pdf_path)) {
            writeToLiveCertLog("INFO: Certificado existe no BD mas PDF não existe - Regenerando: " . $existing_certificate['id']);
            
            // Chamar o sistema de regeneração
            $regenerate_url = 'https://v.translators101.com/generate_certificate.php';
            $post_data = json_encode([
                'certificate_id' => $existing_certificate['id'],
                'regenerate' => true
            ]);
            
            $ch = curl_init($regenerate_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $response = curl_exec($ch);
            curl_close($ch);
            
            writeToLiveCertLog("Regeneração chamada - Resposta: " . substr($response, 0, 200));
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Certificado já existe para esta live',
            'certificate_id' => $existing_certificate['id'],
            'already_exists' => true
        ]);
        writeToLiveCertLog("INFO: Certificado já existe - ID: " . $existing_certificate['id']);
        exit;
    }

    // Gerar UUID para o certificado
    function generateUUID() {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }

    $certificate_id = generateUUID();

    // Inserir certificado no banco
    $stmt = $pdo->prepare("
        INSERT INTO certificates
        (id, user_id, lecture_id, user_name, lecture_title, speaker_name, duration_hours, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $certificate_id,
        $target_user_id,
        $live_lecture_id,
        $user['name'],
        $lecture_title,
        $speaker_name,
        $duration_hours
    ]);

    writeToLiveCertLog("SUCESSO: Certificado inserido no BD - ID: $certificate_id");

    // CHAMAR O SISTEMA DE GERAÇÃO DE PDF
    writeToLiveCertLog("Chamando sistema de geração de PDF...");
    
    $generate_url = 'https://v.translators101.com/generate_certificate.php';
    $post_data = json_encode([
        'certificate_id' => $certificate_id
    ]);
    
    $ch = curl_init($generate_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Cookie: ' . $_SERVER['HTTP_COOKIE'] ?? ''
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $pdf_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    writeToLiveCertLog("Resposta da geração de PDF - HTTP Code: $http_code, Response: " . substr($pdf_response, 0, 500));
    
    // Verificar se o PDF foi criado
    $pdf_path = __DIR__ . '/../certificates/' . $certificate_id . '.pdf';
    $pdf_created = file_exists($pdf_path);
    
    if (!$pdf_created) {
        writeToLiveCertLog("AVISO: PDF não foi criado automaticamente. Caminho verificado: $pdf_path");
    } else {
        writeToLiveCertLog("SUCESSO: PDF criado em: $pdf_path");
    }

    writeToLiveCertLog("SUCESSO FINAL: Certificado completo - User: {$user['name']}, Palestra: $lecture_title");

    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Certificado gerado com sucesso!',
        'certificate_id' => $certificate_id,
        'user_name' => $user['name'],
        'lecture_title' => $lecture_title,
        'speaker_name' => $speaker_name,
        'duration_hours' => $duration_hours,
        'total_minutes' => $presence['total_minutes'],
        'pdf_created' => $pdf_created,
        'certificate_data' => [
            'id' => $certificate_id,
            'user_name' => $user['name'],
            'lecture_title' => $lecture_title,
            'speaker_name' => $speaker_name,
            'duration_hours' => $duration_hours,
            'date' => $live_date
        ]
    ]);

} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
    writeToLiveCertLog("ERRO PDO: " . $e->getMessage());
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro inesperado: ' . $e->getMessage()]);
    writeToLiveCertLog("ERRO: " . $e->getMessage());
}
?>