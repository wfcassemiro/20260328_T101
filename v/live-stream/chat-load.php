<?php
// Iniciar sessão se não estiver ativa
date_default_timezone_set('America/Sao_Paulo');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';

// Verificar se usuário está logado
if (!isLoggedIn()) {
    echo "<div class='chat-message system-message'>";
    echo "<i class='fas fa-exclamation-triangle'></i> ";
    echo "Você precisa estar logado para ver as mensagens do chat.";
    echo "</div>";
    exit;
}

// Em um ambiente real, carregaria mensagens do banco
// Para demonstração, vamos mostrar algumas mensagens fictícias
$isAdmin = isAdmin();
$sample_messages = [
    [
        'id' => 1,
        'user_name' => 'Sistema',
        'message' => 'Bem-vindos ao chat da live!',
        'created_at' => date('Y-m-d H:i:s', strtotime('-5 minutes'))
    ],
    [
        'id' => 2,
        'user_name' => 'Admin',
        'message' => 'Boa noite pessoal! Vamos começar a palestra em breve.',
        'created_at' => date('Y-m-d H:i:s', strtotime('-2 minutes'))
    ]
];

foreach ($sample_messages as $msg) {
    $time = date("H:i", strtotime($msg['created_at']));
    
    echo "<div class='chat-message'>";
    echo "<div class='message-header'>";
    echo "<strong class='username'>" . htmlspecialchars($msg['user_name']) . "</strong>";
    echo "<small class='timestamp'>[$time]</small>";
    echo "</div>";
    echo "<div class='message-content'>";
    echo nl2br(htmlspecialchars($msg['message']));
    echo "</div>";

    // Mostra botão de overlay apenas para admins
    if ($isAdmin && $msg['user_name'] !== 'Sistema') {
        echo "<div class='message-actions'>";
        echo "<button class='btn-overlay' data-id='" . $msg['id'] . "'>";
        echo "<i class='fas fa-tv'></i> Exibir na Tela";
        echo "</button>";
        echo "</div>";
    }

    echo "</div>";
}
?>