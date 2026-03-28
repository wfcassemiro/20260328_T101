<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');

header('Content-Type: application/json; charset=utf-8');

// Mesma conexão usada em arquivo_radar.php
require_once __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

$nome  = trim((string)($_POST['nome']  ?? ''));
$email = trim((string)($_POST['email'] ?? ''));

if ($nome === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Informe nome e e-mail válidos.']);
    exit;
}

// Formulário já fica oculto para logados, mas reforça aqui
if (isset($_SESSION['user_id'])) {
    echo json_encode(['success' => true, 'message' => 'Você já está logado em nossa plataforma.']);
    exit;
}

try {
    // 1) Verifica se já é usuário cadastrado (assinante ou não)
    $stmtUser = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmtUser->execute([$email]);
    if ($stmtUser->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success' => true, 'message' => 'Você já está cadastrado em nossa base!']);
        exit;
    }

    // 2) Busca lead existente por email
    $stmtLead = $pdo->prepare("SELECT id FROM leads WHERE email = ? LIMIT 1");
    $stmtLead->execute([$email]);
    $lead = $stmtLead->fetch(PDO::FETCH_ASSOC);

    if ($lead && !empty($lead['id'])) {
        $lead_id = $lead['id'];

        // Opcional: atualiza nome + opt-in (sem mexer em origem/tipo)
        $stmtUpd = $pdo->prepare("
            UPDATE leads
               SET nome = ?,
                   pode_receber_email = 1,
                   updated_at = NOW()
             WHERE id = ?
             LIMIT 1
        ");
        $stmtUpd->execute([$nome, $lead_id]);
    } else {
        // 3) Cria novo lead
        $lead_id = 'lead_' . uniqid('', true);

        // whatsapp é NOT NULL na tabela → passa string vazia
        $stmtIns = $pdo->prepare("
            INSERT INTO leads (id, nome, email, whatsapp, fonte, tipo, pode_receber_email, created_at, updated_at)
            VALUES (?, ?, ?, '', 'radar_t101', 'newsletter', 1, NOW(), NOW())
        ");
        $stmtIns->execute([$lead_id, $nome, $email]);
    }

    // 4) Registra interesse "radar_t101" (sem apagar outros)
    $stmtInt = $pdo->prepare("
        INSERT IGNORE INTO lead_interesses (lead_id, interesse, created_at)
        VALUES (?, 'radar_t101', NOW())
    ");
    $stmtInt->execute([$lead_id]);

    echo json_encode(['success' => true, 'message' => 'Inscrição realizada com sucesso! Você receberá o Radar T101 toda segunda.']);
    exit;

} catch (PDOException $e) {
    error_log('inscrever_newsletter.php PDOException: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar inscrição. Tente novamente.']);
    exit;
}