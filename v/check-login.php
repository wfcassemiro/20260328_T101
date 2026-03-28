<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<h1>🔐 Teste Manual de Login</h1>';

// Incluir database
try {
    require_once 'config/database.php';
    echo '✅ Banco conectado<br><br>';
} catch (Exception $e) {
    echo '❌ Erro no banco: ' . $e->getMessage();
    exit;
}

// Processar login se enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    echo '<h2>📝 Dados Recebidos:</h2>';
    echo 'Email: ' . htmlspecialchars($email) . '<br>';
    echo 'Senha: ' . str_repeat('*', strlen($password)) . '<br><br>';
    
    if (!empty($email) && !empty($password)) {
        try {
            // Buscar usuário
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                echo '✅ Usuário encontrado: ' . htmlspecialchars($user['name']) . '<br>';
                echo 'ID: ' . $user['id'] . '<br>';
                echo 'Role: ' . ($user['role'] ?? 'N/A') . '<br>';
                
                // Verificar senha
                if (password_verify($password, $user['password_hash'])) {
                    echo '✅ Senha correta!<br>';
                    
                    // Simular login
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'] ?? 'subscriber';
                    $_SESSION['is_subscriber'] = 1;
                    
                    echo '✅ Sessão criada com sucesso!<br>';
                    echo '<a href=\"dash-t101/\">Ir para Dashboard</a><br>';
                    
                } else {
                    echo '❌ Senha incorreta<br>';
                    
                    // Testar outros métodos de hash
                    if (md5($password) === $user['password_hash']) {
                        echo '💡 Senha usa MD5 - precisa migrar para password_hash<br>';
                    } elseif (sha1($password) === $user['password_hash']) {
                        echo '💡 Senha usa SHA1 - precisa migrar para password_hash<br>';
                    }
                }
            } else {
                echo '❌ Usuário não encontrado com este email<br>';
                
                // Mostrar usuários disponíveis (apenas emails)
                $stmt = $pdo->query('SELECT email FROM users LIMIT 5');
                echo '<br>📋 Primeiros 5 emails cadastrados:<br>';
                while ($row = $stmt->fetch()) {
                    echo '- ' . htmlspecialchars($row['email']) . '<br>';
                }
            }
            
        } catch (Exception $e) {
            echo '❌ Erro na consulta: ' . $e->getMessage() . '<br>';
        }
    } else {
        echo '❌ Email e senha são obrigatórios<br>';
    }
}
?>

<form method=\"POST\" style=\"background: #f5f5f5; padding: 20px; margin: 20px 0;\">
    <h3>🔑 Teste de Login</h3>
    <p><label>Email: <input type=\"email\" name=\"email\" required style=\"width: 300px; padding: 5px;\"></label></p>
    <p><label>Senha: <input type=\"password\" name=\"password\" required style=\"width: 300px; padding: 5px;\"></label></p>
    <p><button type=\"submit\" style=\"padding: 10px 20px; background: #007cba; color: white; border: none;\">Testar Login</button></p>
</form>

<p><strong>💡 Dica:</strong> Use um email que você sabe que existe no banco de dados.</p>
<p><strong>🔍 Nota:</strong> O teste também verifica se as senhas estão em MD5/SHA1 (formato antigo).</p>"
Observation: Create successful: /app/test-login-fixed.php