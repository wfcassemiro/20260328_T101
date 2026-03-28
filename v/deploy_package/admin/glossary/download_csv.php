<?php
session_start();

// Verificar se existe um CSV para download
if (!isset($_SESSION['csv_info'])) {
    $_SESSION['message'] = 'Nenhum CSV foi gerado. Processe um arquivo primeiro.';
    $_SESSION['error'] = true;
    header("Location: upload_form.php");
    exit();
}

$csvInfo = $_SESSION['csv_info'];
$termsCount = $_SESSION['csv_terms_count'] ?? 0;
$metadata = $_SESSION['metadata'] ?? [];

// Processar download se solicitado
if (isset($_GET['download']) && $_GET['download'] === 'true') {
    $filepath = $csvInfo['filepath'];
    
    if (file_exists($filepath)) {
        // Headers para download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $csvInfo['filename'] . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        
        // Enviar arquivo
        readfile($filepath);
        exit();
    } else {
        $_SESSION['message'] = 'Arquivo CSV não encontrado.';
        $_SESSION['error'] = true;
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download do Glossário CSV</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .container { max-width: 600px; }
        .info-box { 
            background-color: #e7f3ff; border: 1px solid #b3d9ff; 
            padding: 15px; border-radius: 5px; margin: 20px 0; 
        }
        .success { 
            background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; 
        }
        .error { 
            background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; 
        }
        .download-btn { 
            background-color: #28a745; color: white; padding: 12px 24px; 
            text-decoration: none; border-radius: 5px; display: inline-block; 
            margin: 10px 5px; font-weight: bold;
        }
        .download-btn:hover { background-color: #218838; }
        .back-btn { 
            background-color: #6c757d; color: white; padding: 10px 20px; 
            text-decoration: none; border-radius: 5px; display: inline-block; 
            margin: 10px 5px;
        }
        .back-btn:hover { background-color: #545b62; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Glossário CSV Gerado</h2>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="info-box <?php echo isset($_SESSION['error']) ? 'error' : 'success'; ?>">
                <?php echo $_SESSION['message']; ?>
            </div>
            <?php 
            unset($_SESSION['message']); 
            unset($_SESSION['error']);
            ?>
        <?php endif; ?>

        <div class="info-box">
            <h3>Informações do Arquivo</h3>
            <table>
                <tr>
                    <th>Nome do Arquivo:</th>
                    <td><?php echo htmlspecialchars($csvInfo['filename']); ?></td>
                </tr>
                <tr>
                    <th>Área:</th>
                    <td><?php echo htmlspecialchars($metadata['area'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <th>Idioma de Origem:</th>
                    <td><?php echo htmlspecialchars($metadata['source_lang'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <th>Idioma de Chegada:</th>
                    <td><?php echo htmlspecialchars($metadata['target_lang'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <th>Total de Termos:</th>
                    <td><?php echo $termsCount; ?></td>
                </tr>
                <tr>
                    <th>Arquivo Original:</th>
                    <td><?php echo htmlspecialchars($metadata['original_filename'] ?? 'N/A'); ?></td>
                </tr>
            </table>
        </div>

        <div class="info-box">
            <h3>Estrutura do CSV</h3>
            <p>O arquivo CSV gerado contém as seguintes colunas:</p>
            <ul>
                <li><strong>Termo Original:</strong> O termo extraído do arquivo original</li>
                <li><strong>Tradução:</strong> Campo vazio para preenchimento posterior</li>
                <li><strong>Área:</strong> A área especificada durante o upload</li>
                <li><strong>Observações:</strong> Campo vazio para anotações adicionais</li>
            </ul>
            <p><em>Codificação: UTF-8 com BOM (compatível com Excel)</em></p>
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <a href="?download=true" class="download-btn">📥 Baixar CSV</a>
            <a href="upload_form.php" class="back-btn">🔄 Processar Novo Arquivo</a>
        </div>

        <?php if (file_exists($csvInfo['filepath'])): ?>
            <div class="info-box">
                <h3>Preview do Arquivo (primeiras 10 linhas)</h3>
                <table>
                    <?php
                    $file = fopen($csvInfo['filepath'], 'r');
                    // Pular BOM UTF-8
                    $bom = fread($file, 3);
                    if ($bom !== "\xEF\xBB\xBF") {
                        rewind($file);
                    }
                    
                    $lineCount = 0;
                    while (($data = fgetcsv($file, 0, ',')) !== FALSE && $lineCount < 10) {
                        echo '<tr>';
                        foreach ($data as $cell) {
                            if ($lineCount === 0) {
                                echo '<th>' . htmlspecialchars($cell) . '</th>';
                            } else {
                                echo '<td>' . htmlspecialchars($cell) . '</td>';
                            }
                        }
                        echo '</tr>';
                        $lineCount++;
                    }
                    fclose($file);
                    ?>
                </table>
                <?php if ($termsCount > 9): ?>
                    <p><em>... e mais <?php echo ($termsCount - 9); ?> linhas</em></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>