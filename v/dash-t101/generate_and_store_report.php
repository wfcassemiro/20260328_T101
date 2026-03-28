<?php
session_start();
// Habilita a exibição de erros para depuração. REMOVA EM PRODUÇÃO!
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Caminhos dos arquivos de configuração
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';
require_once __DIR__ . '/../config/dash_functions.php';

// --- INÍCIO DO NOVO BLOCO DE DEPURAÇÃO TCPDF ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Define o caminho exato para o arquivo principal do TCPDF
// __DIR__ é o diretório do script atual (ex: .../v/dash-t101)
// /../ sobe para .../v/
$tcpdf_path = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';

// 2. Limpa o cache de caminho de arquivo (importante para depuração)
clearstatcache();

// 3. Verifica se o arquivo realmente existe nesse caminho
if (!file_exists($tcpdf_path)) {
    // Se não existir, falha com uma mensagem de erro clara
    // realpath() nos dirá o caminho absoluto que o servidor tentou ler
    die("Erro: O arquivo TCPDF não foi encontrado no caminho esperado.
         <br><br>
         <b>Caminho que o script tentou acessar:</b> " . htmlspecialchars($tcpdf_path) . "
         <br><br>
         <b>O diretório `__DIR__` atual é:</b> " . __DIR__ . "
         <br><br>
         Verifique se este caminho está correto no seu servidor.");
}

// 4. Tenta carregar o arquivo
require_once($tcpdf_path);

// 5. Verifica se a classe foi carregada após a inclusão
if (!class_exists('TCPDF')) {
    // Se o arquivo foi carregado, mas a classe não existe, é um problema
    // na biblioteca (provavelmente permissões ou falha nos 'requires' internos dela).
    die("Erro: A biblioteca TCPDF foi encontrada em " . htmlspecialchars($tcpdf_path) . ", mas a classe 'TCPDF' não pôde ser carregada. 
         Isto pode ser um problema de permissão de arquivo ou um download corrompido da biblioteca.");
}
// --- FIM DO NOVO BLOCO DE DEPURAÇÃO TCPDF ---

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('isLoggedIn') || !isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$request_body = json_decode(file_get_contents('php://input'), true);
$format = $request_body['format'] ?? 'csv';
$filters = $request_body['filters'] ?? [];

if (empty($filters['start_date']) || empty($filters['end_date'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datas de início e fim são obrigatórias.']);
    exit;
}

try {
    global $pdo;
    $query_parts = build_report_query_and_params($filters, $user_id);
    $sql = "SELECT p.title AS project_name, c.company AS company_name, p.status, p.total_amount, p.currency, p.created_at " . $query_parts['sql'] . " ORDER BY p.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($query_parts['params']);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $reports_dir = __DIR__ . '/generated_reports';
    if (!is_dir($reports_dir)) {
        mkdir($reports_dir, 0775, true);
    }
    
    $file_extension = $format === 'pdf' ? 'html' : 'csv';
    $filename = 'relatorio_' . str_replace('-', '', $user_id) . '_' . time() . '.' . $file_extension;
    $filepath = $reports_dir . '/' . $filename;
    
    $description = "Relatório de " . date('d/m/Y', strtotime($filters['start_date'])) . " a " . date('d/m/Y', strtotime($filters['end_date']));

    if ($format === 'csv') {
        $output = fopen($filepath, 'w');
        fputcsv($output, ['Projeto', 'Cliente', 'Status', 'Valor', 'Moeda', 'Data de Criação']);
        foreach ($projects as $project) {
            fputcsv($output, [$project['project_name'], $project['company_name'] ?? 'N/A', $project['status'], $project['total_amount'], $project['currency'], $project['created_at']]);
        }
        fclose($output);
    } else { // PDF (gerado como HTML para impressão em A4)
        $html_content = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Relatório de Projetos</title>';
        $html_content .= '<style>
            @page { size: A4; margin: 20mm; }
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 10pt; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            h1 { font-size: 18pt; margin-bottom: 5px;} 
            p { font-size: 10pt; margin: 2px 0; }
            .footer { position: fixed; bottom: -25mm; left: 0mm; right: 0mm; width:100%; text-align: center; font-size: 8pt; color: #777; }
        </style></head><body>';
        $html_content .= '<h1>Relatório de Projetos</h1><p>' . htmlspecialchars($description) . '</p>';
        $html_content .= '<table><thead><tr><th>Projeto</th><th>Cliente</th><th>Status</th><th>Valor</th><th>Data</th></tr></thead><tbody>';
        foreach ($projects as $p) {
            $html_content .= '<tr><td>' . htmlspecialchars($p['project_name']) . '</td><td>' . htmlspecialchars($p['company_name'] ?? 'N/A') . '</td><td>' . htmlspecialchars($p['status']) . '</td><td>' . htmlspecialchars(number_format($p['total_amount'], 2, ',', '.') . ' ' . $p['currency']) . '</td><td>' . htmlspecialchars(date('d/m/Y', strtotime($p['created_at']))) . '</td></tr>';
        }
        $html_content .= '</tbody></table>';
        $html_content .= '<div class="footer">Gerado pelo Dash-T101, o sistema de gerenciamento de projetos da Translators101.</div>';
        $html_content .= '</body></html>';
        file_put_contents($filepath, $html_content);
    }
    
    $stmt_insert = $pdo->prepare("INSERT INTO dash_generated_reports (user_id, file_name, file_path, report_type, report_description) VALUES (?, ?, ?, ?, ?)");
    $stmt_insert->execute([$user_id, $filename, $filepath, strtoupper($format), $description]);

    echo json_encode(['success' => true, 'message' => 'Relatório gerado e salvo com sucesso.']);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Report Generation Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro no servidor ao gerar o relatório.']);
}
exit;