<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/email.php'; // Sua função de envio de e-mail

// Verifica se é você (admin)
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    die("Acesso negado.");
}

$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

if (!$id || !$action) die("Parâmetros inválidos.");

if ($action === 'aprovar') {
    // 1. Marca como aprovado na DB
    $stmt = $pdo->prepare("UPDATE weekly_newsletters SET status = 'aprovado' WHERE id = ?");
    $stmt->execute([$id]);

    // 2. Busca o conteúdo e os assinantes
    $news = $pdo->prepare("SELECT * FROM weekly_newsletters WHERE id = ?");
    $news->execute([$id]);
    $conteudo = $news->fetch();

    $assinantes = $pdo->query("SELECT email FROM newsletter_subscribers WHERE active = 1")->fetchAll(PDO::FETCH_COLUMN);

    // 3. Envia para a lista (Loop de envio)
    foreach ($assinantes as $email) {
        enviarEmail($email, "Radar T101 Disponível: " . date('d/m'), $conteudo['content']);
    }

    echo "<h1>Radar publicado e enviado aos assinantes!</h1>";
    echo "<a href='arquivo_radar.php'>Ir para a página do Radar</a>";
}