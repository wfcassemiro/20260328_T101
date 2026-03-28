<?php
session_start();
require_once __DIR__ . '/config/database.php';

// Verificar se está logado
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action']) || $input['action'] !== 'generate_report') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    exit;
}

try {
    // Buscar dados do usuário
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        exit;
    }
    
    // Buscar todos os certificados do usuário com dados das palestras
    $stmt = $pdo->prepare("
        SELECT c.*, 
               l.title as lecture_title,
               l.speaker as speaker_name,
               l.description as lecture_description,
               l.duration_minutes
        FROM certificates c
        LEFT JOIN lectures l ON c.lecture_id = l.id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $certificates = $stmt->fetchAll();
    
    if (empty($certificates)) {
        echo json_encode(['success' => false, 'message' => 'Nenhum certificado encontrado para gerar relatório']);
        exit;
    }
    
    // Verificar se TCPDF está disponível
    $tcpdf_paths = [
        __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php',
        __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php',
        '/usr/share/php/tcpdf/tcpdf.php',
        '/var/www/html/vendor/tecnickcom/tcpdf/tcpdf.php'
    ];
    
    $tcpdf_found = false;
    foreach ($tcpdf_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $tcpdf_found = true;
            break;
        }
    }
    
    if (!$tcpdf_found) {
        // Tentar usar a classe diretamente se estiver no include_path
        if (class_exists('TCPDF')) {
            $tcpdf_found = true;
        }
    }
    
    if (!$tcpdf_found) {
        echo json_encode(['success' => false, 'message' => 'Biblioteca TCPDF não encontrada. Instale via: composer require tecnickcom/tcpdf']);
        exit;
    }
    
    // Criar diretório de relatórios se não existir
    $reports_dir = __DIR__ . '/reports';
    if (!is_dir($reports_dir)) {
        mkdir($reports_dir, 0755, true);
    }
    
    // Gerar relatório PDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    
    // Configurações do documento
    $pdf->SetCreator('Translators101');
    $pdf->SetAuthor('Translators101');
    $pdf->SetTitle('Relatório de Educação Continuada - ' . $user['name']);
    $pdf->SetSubject('Relatório de Educação Continuada Profissional');
    
    // Configurações de página
    $pdf->SetMargins(20, 30, 20);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(15);
    $pdf->SetAutoPageBreak(TRUE, 25);
    
    // Remover header e footer padrão
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Adicionar página
    $pdf->AddPage();
    
    // Configurar fonte
    $pdf->SetFont('helvetica', '', 12);
    
    // Cabeçalho do relatório
    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->SetTextColor(142, 68, 173); // Cor roxa da marca
    $pdf->Cell(0, 15, 'RELATÓRIO DE EDUCAÇÃO CONTINUADA', 0, 1, 'C');
    
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 10, 'TRANSLATORS101', 0, 1, 'C');
    
    $pdf->Ln(5);
    $pdf->SetDrawColor(142, 68, 173);
    $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
    $pdf->Ln(10);
    
    // Mensagem de confirmação
    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetTextColor(0, 0, 0);
    $confirmation_text = "A Translators101 confirma que " . $user['name'] . " assistiu a todas as palestras informadas neste relatório, cumprindo os requisitos de participação e obtendo os certificados correspondentes.";
    $pdf->MultiCell(0, 6, $confirmation_text, 0, 'J');
    $pdf->Ln(10);
    
    // Dados do participante
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(142, 68, 173);
    $pdf->Cell(0, 8, 'DADOS DO PARTICIPANTE', 0, 1, 'L');
    $pdf->Ln(2);
    
    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(30, 6, 'Nome:', 0, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 6, $user['name'], 0, 1, 'L');
    
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(30, 6, 'Email:', 0, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 6, $user['email'], 0, 1, 'L');
    
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(30, 6, 'Data:', 0, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 6, date('d/m/Y H:i'), 0, 1, 'L');
    
    $pdf->Ln(10);
    
    // Lista de palestras e certificados
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(142, 68, 173);
    $pdf->Cell(0, 8, 'PALESTRAS ASSISTIDAS E CERTIFICADOS', 0, 1, 'L');
    $pdf->Ln(5);
    
    $total_hours = 0;
    $domain = $_SERVER['HTTP_HOST'];
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    
    foreach ($certificates as $index => $cert) {
        $cert_number = $index + 1;
        $duration_hours = $cert['duration_hours'] ?? 0;
        $total_hours += $duration_hours;
        
        // Título da palestra
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 8, $cert_number . '. ' . ($cert['lecture_title'] ?: 'Título não disponível'), 0, 1, 'L');
        
        // Detalhes da palestra
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(80, 80, 80);
        
        if ($cert['speaker_name']) {
            $pdf->Cell(20, 5, 'Palestrante:', 0, 0, 'L');
            $pdf->Cell(0, 5, $cert['speaker_name'], 0, 1, 'L');
        }
        
        $pdf->Cell(20, 5, 'Duração:', 0, 0, 'L');
        $pdf->Cell(0, 5, number_format($duration_hours, 1) . ' horas', 0, 1, 'L');
        
        $pdf->Cell(20, 5, 'Conclusão:', 0, 0, 'L');
        $pdf->Cell(0, 5, date('d/m/Y', strtotime($cert['created_at'])), 0, 1, 'L');
        
        // Links
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(142, 68, 173);
        
        // Link para download PNG (apenas para usuário logado)
        $download_url = $protocol . $domain . '/download_certificate_files.php?id=' . $cert['id'];
        $pdf->Cell(25, 5, 'Download PNG:', 0, 0, 'L');
        $pdf->Cell(0, 5, $download_url, 0, 1, 'L');
        
        // Link para verificação (público)
        $verify_url = $protocol . $domain . '/verificar_certificado.php?id=' . $cert['id'];
        $pdf->Cell(25, 5, 'Verificação:', 0, 0, 'L');
        $pdf->Cell(0, 5, $verify_url, 0, 1, 'L');
        
        $pdf->Ln(8);
        
        // Quebra de página se necessário
        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
        }
    }
    
    // Totalização
    $pdf->Ln(5);
    $pdf->SetDrawColor(142, 68, 173);
    $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
    $pdf->Ln(5);
    
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(142, 68, 173);
    $pdf->Cell(0, 8, 'TOTALIZAÇÃO', 0, 1, 'L');
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 8, 'Total de palestras assistidas: ' . count($certificates), 0, 1, 'L');
    $pdf->Cell(0, 8, 'Total de horas de capacitação: ' . number_format($total_hours, 1) . ' horas', 0, 1, 'L');
    
    $pdf->Ln(10);
    
    // Rodapé
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 5, 'Este relatório foi gerado automaticamente em ' . date('d/m/Y H:i:s'), 0, 1, 'C');
    $pdf->Cell(0, 5, 'Translators101 - Educação Continuada para Tradutores', 0, 1, 'C');
    $pdf->Cell(0, 5, 'Todos os certificados podem ser verificados através dos links fornecidos', 0, 1, 'C');
    
    // Gerar nome único do arquivo
    $filename = 'relatorio_educacao_continuada_' . $user_id . '_' . date('Y-m-d_H-i-s') . '.pdf';
    $filepath = $reports_dir . '/' . $filename;
    
    // Salvar PDF
    $pdf->Output($filepath, 'F');
    
    // Verificar se arquivo foi criado
    if (!file_exists($filepath) || filesize($filepath) == 0) {
        echo json_encode(['success' => false, 'message' => 'Erro ao gerar arquivo PDF']);
        exit;
    }
    
    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Relatório PDF gerado com sucesso',
        'report_path' => $filepath,
        'download_url' => '/download_report.php?file=' . $filename,
        'certificates_count' => count($certificates),
        'total_hours' => number_format($total_hours, 1)
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao gerar relatório PDF: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno ao gerar relatório: ' . $e->getMessage()
    ]);
}
?>