<?php
require_once '../config.php';

header("Content-Type: text/html; charset=UTF-8");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Overlay Live Chat</title>
<style>
  body {
    margin: 0;
    padding: 0;
    background: transparent; /* OBS precisa disso */
    font-family: "Inter", sans-serif;
  }

  .overlay-message {
    font-size: 2rem;
    font-weight: bold;
    color: #fff;
    background: rgba(142, 68, 173, 0.85);
    padding: 18px 28px;
    border-radius: 14px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.6);
    display: inline-block;
    animation: fadeInUp 0.6s ease forwards;
  }

  @keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to   { opacity: 1; transform: translateY(0); }
  }
</style>
</head>
<body>
<?php
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("
        SELECT c.message, u.nome as user_name
        FROM live_chat_messages c
        JOIN usuarios u ON c.user_id = u.id
        WHERE c.show_on_screen = 1
        ORDER BY c.created_at DESC
        LIMIT 1
    ");

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $nome = htmlspecialchars($row['user_name']);
        $msg  = htmlspecialchars($row['message']);
        echo "<div class='overlay-message'><strong>{$nome}:</strong> {$msg}</div>";
    }
} catch (PDOException $e) {
    // silencioso em produção
}
?>
</body>
</html>