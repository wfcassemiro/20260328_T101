<?php
// webhook/receber_rascunho_mensal.php
require_once __DIR__ . '/../../config/database.php';
date_default_timezone_set('America/Sao_Paulo');

header('Content-Type: application/json; charset=UTF-8');

/**
 * Lê o body e tenta obter dados tanto de JSON (php://input) quanto de form-data ($_POST).
 * Também tenta extrair campos em cenários comuns do n8n.
 */
$rawInput = file_get_contents('php://input');
$data = null;

// 1) Tenta JSON puro
if ($rawInput !== false && trim($rawInput) !== '') {
    $tmp = json_decode($rawInput, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
        $data = $tmp;
    }
}

// 2) Fallback: form-data / x-www-form-urlencoded
if (!is_array($data)) {
    if (!empty($_POST) && is_array($_POST)) {
        $data = $_POST;
    }
}

// 3) Se ainda não veio, tenta interpretar body como querystring
if (!is_array($data) && is_string($rawInput) && trim($rawInput) !== '') {
    $qs = [];
    parse_str($rawInput, $qs);
    if (!empty($qs)) {
        $data = $qs;
    }
}

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        "error" => "Payload inválido (nem JSON nem form-data).",
        "hint"  => "No n8n, prefira enviar JSON com Content-Type: application/json.",
    ]);
    exit;
}

// Aceita alguns nomes alternativos, caso você mude no n8n no futuro
$conteudo = $data['conteudo_html'] ?? $data['html'] ?? $data['content'] ?? null;

if ($conteudo === null || $conteudo === '') {
    http_response_code(400);
    echo json_encode(["error" => "Dados incompletos: conteudo_html ausente"]);
    exit;
}

try {
    $cleanHtml = (string)$conteudo;

    // 1) Remove cercas de markdown e crases soltas
    $cleanHtml = preg_replace('/```(?:html)?/i', '', $cleanHtml);
    $cleanHtml = str_replace('`', '', $cleanHtml);

    // 2) Se vier escapado como &lt;div&gt;..., desescapa
    // ENT_QUOTES para pegar &quot; e &#039;
    $cleanHtml = html_entity_decode($cleanHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // 3) Normaliza espaços
    $cleanHtml = trim($cleanHtml);

    // Se quiser, dá pra rejeitar caso ainda não pareça HTML
    // (evita salvar texto puro acidental)
    // if (stripos($cleanHtml, '<div') === false && stripos($cleanHtml, '<h2') === false) {
    //     throw new Exception("Conteúdo não parece HTML após limpeza.");
    // }

    $periodo = $data['periodo'] ?? date('m/Y');
    $assunto = $data['assunto'] ?? 'Radar T101: Resumo Mensal';

    $stmt = $pdo->prepare("
        INSERT INTO monthly_newsletters_drafts (month_year, email_subject, email_content)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$periodo, $assunto, $cleanHtml]);

    echo json_encode(["status" => "success", "message" => "Rascunho mensal salvo."]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}