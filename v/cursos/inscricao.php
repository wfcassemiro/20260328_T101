<?php
include('config/database.php'); // usa a conexão PDO existente

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome  = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $curso = trim($_POST['curso'] ?? '');

    if ($nome && $email) {
        try {
            $stmt = $pdo->prepare("INSERT INTO course_signups (nome, email, curso) VALUES (:nome, :email, :curso)");
            $stmt->execute([
                ':nome'  => $nome,
                ':email' => $email,
                ':curso' => $curso
            ]);

            echo "<script>alert('Inscrição registrada.'); window.location.href='index.php';</script>";
        } catch (PDOException $e) {
            echo "<script>alert('Erro ao salvar a inscrição.');</script>";
        }
    } else {
        echo "<script>alert('Por favor, preencha o nome e o e-mail.'); window.history.back();</script>";
    }
}
?>