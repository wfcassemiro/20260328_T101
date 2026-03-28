<?php
require_once __DIR__ . '/../../config/database.php';

$mes_anterior = date('m', strtotime('-1 month'));
$ano_atual = date('Y');
$nome_mes = date('F', strtotime('-1 month'));

try {
    $stmt = $pdo->prepare("SELECT title, summary, url FROM curation_articles WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?");
    $stmt->execute([$mes_anterior, $ano_atual]);
    $artigos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtTrends = $pdo->prepare("SELECT topic_name, COUNT(*) as qtd FROM market_trends WHERE MONTH(occurrence_date) = ? GROUP BY topic_name ORDER BY qtd DESC LIMIT 5");
    $stmtTrends->execute([$mes_anterior]);
    $tendencias = $stmtTrends->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "mes" => $nome_mes,
        "ano" => $ano_atual,
        "tendencias" => $tendencias,
        "artigos" => $artigos
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}