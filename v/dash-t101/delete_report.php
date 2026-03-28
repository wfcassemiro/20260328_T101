<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Receber ID do relatório via POST
$report_id = $_POST['report_id'] ?? null;

if (!$report_id || !is_numeric($report_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do relatório inválido']);
    exit;
}

try {
    global $pdo;
    
    // Buscar relatório e verificar propriedade do usuário
    $stmt = $pdo->prepare("SELECT * FROM dash_reports WHERE id = ? AND user_id = ?");
    $stmt->execute([$report_id, $user_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Relatório não encontrado ou você não tem permissão para excluí-lo']);
        exit;
    }
    
    // CORREÇÃO: Construir o caminho físico correto do arquivo no servidor
    // O caminho é construído a partir do diretório atual + /generated_reports/ + nome do arquivo.
    $file_path_to_delete = __DIR__ . '/generated_reports/' . $report['report_name'];
    
    // Remover arquivo físico se existir
    if (file_exists($file_path_to_delete)) {
        if (!unlink($file_path_to_delete)) {
            // Loga o erro, mas não impede a exclusão do registro no banco
            error_log("Falha ao excluir o arquivo físico: " . $file_path_to_delete);
        }
    } else {
        // Loga um aviso se o arquivo não for encontrado, para fins de debug
        error_log("Arquivo de relatório não encontrado para exclusão: " . $file_path_to_delete);
    }
    
    // Remover registro do banco de dados
    $stmt = $pdo->prepare("DELETE FROM dash_reports WHERE id = ? AND user_id = ?");
    $stmt->execute([$report_id, $user_id]);
    
    echo json_encode(['success' => true, 'message' => 'Relatório excluído com sucesso']);
    
} catch (Exception $e) {
    error_log('Erro ao excluir relatório: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro interno no servidor ao tentar excluir o relatório.']);
}