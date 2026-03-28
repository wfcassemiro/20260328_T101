<?php
// Removida a linha de log que causava duplicação e excesso de logs:
// writeToCustomLog("log_message.php foi acessado.");

// Função auxiliar para escrever no arquivo de log customizado.
// A função writeToCustomLog deve estar definida em algum lugar que possa ser acessada por este arquivo,
// ou ser recriada aqui se este for um endpoint isolado.
// Considerando sua estrutura de arquivos, a função writeToCustomLog está no _main_ script,
// então vamos garantir que a função de log para este arquivo (log_message.php) seja auto-contida.

// Log de depuração específico para este endpoint
$log_file = __DIR__ . '/api_debug.log'; // Usa o mesmo log do update_lecture_progress.php

function writeToJSLog($message) { // Renomeado para evitar conflito e ser mais específico
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] [JS_LOG] " . $message . "\n", FILE_APPEND);
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$response = ['success' => false, 'message' => ''];

$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

if (json_last_error() === JSON_ERROR_NONE && isset($input['log_message'])) {
    writeToJSLog($input['log_message']); // Agora esta é a única linha que escreve no log_message.php
    $response['success'] = true;
    $response['message'] = 'Log registrado com sucesso.';
} else {
    $response['message'] = 'Dados de log inválidos.';
    writeToJSLog("ERRO: Dados de log inválidos recebidos: " . ($raw_input ?: 'Vazio'));
}

echo json_encode($response);
?>