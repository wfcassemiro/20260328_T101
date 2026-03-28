<?php
// config/dash_database.php
// Configuração do banco de dados para o Dash-T101

// Usar as mesmas configurações do translators101.com
$host = 'localhost'; 
$db   = 'u335416710_t101_db'; // Mesmo banco do translators101
$user = 'u335416710_t101';
$pass = 'Pa392ap!';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $dash_pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Erro de Conexão com o Banco de Dados (Dash-T101): " . $e->getMessage());
}

// Funções auxiliares para o Dash-T101
function getDashPDO() {
    global $dash_pdo;
    return $dash_pdo;
}

// Função para verificar se o usuário tem acesso ao Dash-T101
function hasDashAccess() {
    // Por enquanto, todos os usuários logados têm acesso
    // Futuramente pode ser restrito a assinantes premium
    return isLoggedIn();
}

// Função para obter o ID do usuário atual
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Função para gerar número de fatura único
function generateInvoiceNumber() {
    return 'INV-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

// Função para formatar moeda
function formatCurrency($amount) {
    return 'R$ ' . number_format($amount, 2, ',', '.');
}

// Função para formatar data
function formatDate($date) {
    if (!$date) return '';
    return date('d/m/Y', strtotime($date));
}

// Função para calcular total do projeto
function calculateProjectTotal($wordCount, $ratePerWord) {
    return $wordCount * $ratePerWord;
}
?>

