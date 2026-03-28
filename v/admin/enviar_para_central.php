<?php
session_start();
require_once __DIR__ . '/../config/database.php';
date_default_timezone_set('America/Sao_Paulo');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    die("Acesso negado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    // Armazena na sessão para o emails.php capturar
    $_SESSION['draft_to_send'] = [
        'subject' => $_POST['subject'],
        'content' => $_POST['content']
    ];

    // Marca o rascunho como enviado no histórico do Radar
    if (isset($_POST['draft_id'])) {
        $stmt = $pdo->prepare("UPDATE monthly_newsletters_drafts SET status = 'sent' WHERE id = ?");
        $stmt->execute([$_POST['draft_id']]);
    }

    header('Location: emails.php?from=radar');
    exit;
}