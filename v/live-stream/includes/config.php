<?php
// includes/config.php
session_start();

// Configuração básica do banco
$host = "localhost";
$user = "root";
$pass = "";
$db   = "translators101";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error);
}
?>