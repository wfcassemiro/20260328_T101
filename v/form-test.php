<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<h1>🧪 Teste de Formulário Simples</h1>';

// PRIMEIRO: Mostrar se formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo '<div style="background: yellow; padding: 15px; margin: 10px 0;">';
    echo '<h2>📬 FORMULÁRIO FOI ENVIADO!</h2>';
    echo '<p>Método: ' . $_SERVER['REQUEST_METHOD'] . '</p>';
    echo '<p>Dados POST recebidos:</p>';
    echo '<pre>' . print_r($_POST, true) . '</pre>';
    echo '</div>';
    
    // Testar login se dados foram enviados
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    
    if ($email && $senha) {
        echo '<h3>🔐 Processando Login...</h3>';
        
        try {
            require_once 'config/database.php';
            
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($senha, $user['password_hash'])) {
                echo '<div style="background: lightgreen; padding: 15px;">';
                echo '<h2>✅ LOGIN SUCESSO!</h2>';
                echo '<p>Usuário: ' . htmlspecialchars($user['name']) . '</p>';
                echo '<p>Email: ' . htmlspecialchars($user['email']) . '</p>';
                echo '</div>';
            } else {
                echo '<div style="background: lightcoral; padding: 15px;">';
                echo '<h2>❌ LOGIN FALHOU</h2>';
                echo '<p>Email encontrado: ' . ($user ? 'SIM' : 'NÃO') . '</p>';
                if ($user) {
                    echo '<p>Senha correta: NÃO</p>';
                }
                echo '</div>';
            }
            
        } catch (Exception $e) {
            echo '<p>Erro: ' . $e->getMessage() . '</p>';
        }
    }
} else {
    echo '<p>ℹ️ Formulário ainda não foi enviado (método: ' . $_SERVER['REQUEST_METHOD'] . ')</p>';
}
?>

<hr>

<form method="POST" action="" style="background: #f8f9fa; padding: 20px; border: 2px solid #007bff; margin: 20px 0;">
    <h3>🔑 Teste Ultra-Simples</h3>
    
    <p>
        <label><strong>Email:</strong></label><br>
        <input type="email" name="email" value="wrbl.traduz@gmail.com" style="width: 300px; padding: 10px; border: 2px solid #ccc;">
    </p>
    
    <p>
        <label><strong>Senha:</strong></label><br>
        <input type="password" name="senha" style="width: 300px; padding: 10px; border: 2px solid #ccc;">
    </p>
    
    <p>
        <button type="submit" style="background: #28a745; color: white; padding: 15px 30px; border: none; font-size: 16px; cursor: pointer; border-radius: 5px;">
            🚀 TESTAR AGORA
        </button>
    </p>
</form>

<div style="background: #e7f3ff; padding: 15px; margin: 20px 0; border-left: 5px solid #2196F3;">
    <h4>🔍 O que este teste faz:</h4>
    <ul>
        <li>✅ Mostra se o formulário foi enviado</li>
        <li>✅ Exibe todos os dados recebidos</li>
        <li>✅ Testa o login com password_verify</li>
        <li>✅ Mostra erros detalhados</li>
    </ul>
</div>

<script>
document.querySelector('form').addEventListener('submit', function(e) {
    console.log('Formulário sendo enviado!');
    console.log('Email:', document.querySelector('input[name="email"]').value);
    console.log('Senha preenchida:', document.querySelector('input[name="senha"]').value.length > 0);
});
</script>