<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email_config.php';

// Verificação de Admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("Acesso negado.");
}

// Tenta carregar a função do seu arquivo oficial, mas define um fallback se falhar
if (file_exists(__DIR__ . '/../config/email.php')) {
    require_once __DIR__ . '/../config/email.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['draft_id'])) {
    $draft_id = $_POST['draft_id'];
    $subject = $_POST['subject'];
    $content = $_POST['content'];
    $action = $_POST['action'];

    // Função interna de disparo caso a global não seja carregada
    $disparar = function($para, $assunto, $corpo) {
        // Tenta usar a sua função existente primeiro
        if (function_exists('sendEmail')) {
            return sendEmail($para, $assunto, $corpo);
        } elseif (function_exists('sendCustomEmail')) {
            return sendCustomEmail($para, $assunto, $corpo);
        }
        return false; 
    };

    // --- CENÁRIO A: TESTE ---
    if ($action === 'send_test') {
        $test_email = filter_var($_POST['test_email'], FILTER_SANITIZE_EMAIL);
        if (empty($test_email)) {
            $_SESSION['admin_error'] = "E-mail de teste inválido.";
            header('Location: revisar_mensal.php'); exit;
        }

        $test_content = str_replace('{{nome}}', 'William (Teste)', $content);

        if ($disparar($test_email, "[TESTE] " . $subject, $test_content)) {
            $_SESSION['admin_message'] = "E-mail de teste enviado para $test_email.";
        } else {
            $_SESSION['admin_error'] = "Erro: A função de envio não foi encontrada ou falhou. Verifique se o config/email.php está correto.";
        }
        header('Location: revisar_mensal.php'); exit;
    }

    // --- CENÁRIO B: ENVIO MASSIVO ---
    if ($action === 'send_all') {
        try {
            $stmt = $pdo->query("SELECT email, first_name FROM users WHERE is_active = 1");
            $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $success_count = 0;
            foreach ($subscribers as $user) {
                $personal_content = str_replace('{{nome}}', $user['first_name'], $content);
                if ($disparar($user['email'], $subject, $personal_content)) {
                    $success_count++;
                }
                usleep(150000); // 150ms delay conforme emails.php
            }

            $stmtUpdate = $pdo->prepare("UPDATE monthly_newsletters_drafts SET status = 'sent' WHERE id = ?");
            $stmtUpdate->execute([$draft_id]);

            $_SESSION['admin_message'] = "Newsletter enviada para $success_count assinantes.";
            header('Location: emails.php'); exit;
        } catch (Exception $e) {
            $_SESSION['admin_error'] = "Erro: " . $e->getMessage();
            header('Location: revisar_mensal.php'); exit;
        }
    }
}