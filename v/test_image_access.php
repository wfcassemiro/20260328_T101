<?php
// Teste de acesso direto às imagens

echo "<h2>🔍 Teste de Acesso às Imagens</h2>";

// Descobrir o caminho real do documento
$documentRoot = $_SERVER['DOCUMENT_ROOT'];
$currentDir = __DIR__;
$scriptPath = $_SERVER['SCRIPT_NAME'];

echo "<h3>📁 Informações do Servidor:</h3>";
echo "Document Root: <strong>$documentRoot</strong><br>";
echo "Diretório atual: <strong>$currentDir</strong><br>";
echo "Script Path: <strong>$scriptPath</strong><br>";
echo "Server Name: <strong>" . $_SERVER['SERVER_NAME'] . "</strong><br>";

// Verificar onde estão as imagens
$imageDir = __DIR__ . '/../images/announcements/';
echo "<br><h3>📂 Localização das Imagens:</h3>";
echo "Pasta física: <strong>$imageDir</strong><br>";
echo "Pasta existe: " . (is_dir($imageDir) ? "✅ Sim" : "❌ Não") . "<br>";

if (is_dir($imageDir)) {
    $files = glob($imageDir . "*.{jpg,jpeg,png,webp}", GLOB_BRACE);
    echo "Arquivos encontrados: <strong>" . count($files) . "</strong><br>";
    
    foreach ($files as $file) {
        $fileName = basename($file);
        echo "- $fileName<br>";
        
        // Testar diferentes caminhos de URL
        $testPaths = [
            '/images/announcements/' . $fileName,
            'images/announcements/' . $fileName,
            '../images/announcements/' . $fileName,
            './images/announcements/' . $fileName,
            '/Entregas_1/../images/announcements/' . $fileName
        ];
        
        echo "<div style='margin-left: 20px; border: 1px solid #ddd; padding: 10px; margin: 5px 0;'>";
        echo "<strong>Testando caminhos para: $fileName</strong><br>";
        
        foreach ($testPaths as $path) {
            $fullUrl = 'https://' . $_SERVER['SERVER_NAME'] . $path;
            echo "Caminho: <code>$path</code> → ";
            echo "<a href='$fullUrl' target='_blank'>Testar URL</a><br>";
        }
        echo "</div>";
        break; // Testar apenas o primeiro arquivo
    }
}

// Verificar se pasta images existe no document root
$imagesInDocRoot = $documentRoot . '/images/';
echo "<br><h3>🌐 Verificação Web:</h3>";
echo "Pasta /images no document root: <strong>$imagesInDocRoot</strong><br>";
echo "Existe: " . (is_dir($imagesInDocRoot) ? "✅ Sim" : "❌ Não") . "<br>";

// Tentar criar symlink ou copiar arquivo para teste
if (is_dir($imageDir)) {
    $files = glob($imageDir . "*.{jpg,jpeg,png,webp}", GLOB_BRACE);
    if (!empty($files)) {
        $testFile = $files[0];
        $fileName = basename($testFile);
        
        // Criar pasta images no document root se não existir
        if (!is_dir($imagesInDocRoot)) {
            mkdir($imagesInDocRoot, 0755, true);
        }
        
        $announcementsDir = $imagesInDocRoot . 'announcements/';
        if (!is_dir($announcementsDir)) {
            mkdir($announcementsDir, 0755, true);
        }
        
        // Copiar arquivo de teste
        $testDestination = $announcementsDir . $fileName;
        if (copy($testFile, $testDestination)) {
            echo "<br>✅ Arquivo de teste copiado para: $testDestination<br>";
            $testUrl = 'https://' . $_SERVER['SERVER_NAME'] . '/images/announcements/' . $fileName;
            echo "🔗 <a href='$testUrl' target='_blank'>Testar acesso direto: $testUrl</a><br>";
            echo "<br>Preview do teste:<br>";
            echo "<img src='/images/announcements/$fileName' style='max-width: 200px; border: 1px solid #ddd;' onerror='this.style.border=\"2px solid red\"; this.alt=\"ERRO: Não carregou\";'>";
        }
    }
}

echo "<br><a href='index.php'>← Voltar para a home</a>";
?>