<?php
// Tenta forçar o relatório de erros para ver o que está acontecendo
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Caminhos de Inclusão Ajustados
$database_path = __DIR__ . '/../config/database.php';
$helper_path = __DIR__ . '/../includes/certificate_generator_helper.php';
$pdf_generator_path = __DIR__ . '/../includes/certificate_pdf_generator.php';

if (!file_exists($database_path)) {
    die("ERRO FATAL: Arquivo de configuração de banco de dados não encontrado em: " . $database_path);
}
if (!file_exists($helper_path)) {
    die("ERRO FATAL: Arquivo de funções helper não encontrado em: " . $helper_path);
}
if (!file_exists($pdf_generator_path)) {
    die("ERRO FATAL: Arquivo de geração de PDF não encontrado em: " . $pdf_generator_path);
}

require_once $database_path;
require_once $helper_path;
require_once $pdf_generator_path;

// 1. Configuração de Lote Reduzido e Tempo Aumentado
$certificates_per_batch = 30; // Reduzido para 10 para evitar timeout/memória
$reload_delay = 7; // Aumentado para 7 segundos

// 2. Função de log (simples para output no navegador)
function writeToCustomLog($message) {
    echo "";
}

// 3. Verificação de funções críticas
if (!function_exists('generateAndSaveCertificatePng') || !function_exists('generateCertificatePDF')) {
    die("ERRO FATAL: As funções de geração de certificado (PNG/PDF) não foram carregadas corretamente. Verifique os arquivos helper e pdf_generator.");
}

try {
    // Tenta desativar o buffer de saída do servidor para que o auto-reload funcione melhor.
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    ob_implicit_flush(true);

    // Conexão PDO (Mantida para redundância, assumindo que database.php a define)
    if (!isset($pdo)) {
        $host    = 'localhost';
        $db      = 'u335416710_t101_db';
        $user    = 'u335416710_t101';
        $pass    = 'Pa392ap!';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, $user, $pass, $options);
    }

    // Contar quantos certificados ainda faltam
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM certificates WHERE file_path IS NULL");
    $total_pending = $stmt->fetch()['total'];

    // Início da saída HTML
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
    
    // Tenta o auto-reload via cabeçalho HTTP (mais robusto)
    if ($total_pending > 0 && !headers_sent()) {
        header("Refresh: $reload_delay; url=" . $_SERVER['PHP_SELF']);
    }

    echo "</head><body>";
    echo "<h2>Geração Automática de Certificados</h2>";
    echo "<p><strong>Certificados pendentes: $total_pending</strong> (Processando em lotes de $certificates_per_batch)</p>";

    if ($total_pending == 0) {
        echo "<p style='color: green; font-size: 20px;'>✓ Todos os certificados foram gerados!</p>";
        echo "</body></html>";
        exit;
    }

    // Buscar certificados sem arquivo
    $sql = "SELECT id, user_name, lecture_title, speaker_name, duration_hours, certificate_code FROM certificates WHERE file_path IS NULL LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $certificates_per_batch, PDO::PARAM_INT);
    $stmt->execute();
    $certificates = $stmt->fetchAll();

    echo "<p>Processando lote de até $certificates_per_batch certificados...</p>";
    echo "<ul>";

    $processed = 0;
    $errors = 0;

    foreach ($certificates as $cert) {
        $user_name = $cert['user_name'];
        $lecture_title = $cert['lecture_title'];
        $cert_id = $cert['id'];
        $duration_hours = (float)$cert['duration_hours']; 
        
        $certificate_data = [
            'user_name' => $cert['user_name'],
            'lecture_title' => $cert['lecture_title'],
            'speaker_name' => $cert['speaker_name'],
            'duration_minutes' => $duration_hours * 60, 
            'certificate_code' => $cert['certificate_code'] 
        ];

        echo "<li>Gerando certificado #$cert_id para <strong>" . htmlspecialchars($user_name) . "</strong> - " . htmlspecialchars($lecture_title) . "... ";
        
        $png_path = false;
        $pdf_path = null; 
        
        try {
            // Gerar PNG
            $png_path = generateAndSaveCertificatePng(
                $cert_id,
                $certificate_data,
                "GENERATE_MISSING_PNG",
                'writeToCustomLog' 
            );

            // Tentar gerar o PDF
            if ($png_path) {
                $pdf_path = generateCertificatePDF(
                    $cert_id,
                    $certificate_data,
                    "GENERATE_MISSING_PDF",
                    'writeToCustomLog' 
                );
            } else {
                 throw new Exception("Falha ao gerar o arquivo PNG. Não foi possível prosseguir com o PDF.");
            }

            // Atualizar banco (incluindo pdf_path - pressupondo que o SQL foi executado)
            $update_sql = "UPDATE certificates 
                           SET file_path = :png_path, pdf_path = :pdf_path 
                           WHERE id = :id";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([
                ':png_path' => $png_path,
                ':pdf_path' => $pdf_path, 
                ':id' => $cert_id
            ]);

            echo "<span style='color: green;'>✓ OK</span> (PNG: " . ($png_path ? "Gerado" : "Falha") . ", PDF: " . ($pdf_path ? "Gerado" : "Falha/Fallback") . ")</li>";
            $processed++;

        } catch (Exception $e) {
            echo "<span style='color: red;'>✗ ERRO: " . htmlspecialchars($e->getMessage()) . "</span></li>";
            $errors++;
        }
    }

    echo "</ul>";
    echo "<p><strong>Processados neste lote: $processed</strong></p>";
    if ($errors > 0) {
        echo "<p style='color: orange;'>Erros: $errors</p>";
    }

    $remaining = $total_pending - $processed;
    echo "<p>Restam aproximadamente: <strong>$remaining</strong> certificados</p>";

    if ($remaining > 0) {
        echo "<p>Recarregando em $reload_delay segundos...</p>";
    } else {
        echo "<p style='color: green; font-size: 20px;'>✓ Todos os certificados foram gerados!</p>";
    }
    
    echo "</body></html>";

} catch (PDOException $e) {
    // Se a conexão falhar ou houver um erro de SQL
    die("ERRO DE BANCO DE DADOS: " . htmlspecialchars($e->getMessage()));
} catch (Exception $e) {
    // Se qualquer outra exceção ocorrer
    die("ERRO FATAL: " . htmlspecialchars($e->getMessage()));
}
?>