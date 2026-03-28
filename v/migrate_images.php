<?php
// Script para mover imagens para o document root

echo "<h2>🚚 Migração de Imagens</h2>";

$documentRoot = $_SERVER['DOCUMENT_ROOT'];
$sourceDir = __DIR__ . '/../images/announcements/';
$targetDir = $documentRoot . '/images/announcements/';

echo "Origem: <strong>$sourceDir</strong><br>";
echo "Destino: <strong>$targetDir</strong><br>";

// Criar pasta de destino
if (!is_dir($targetDir)) {
    if (mkdir($targetDir, 0755, true)) {
        echo "✅ Pasta de destino criada<br>";
    } else {
        echo "❌ Erro ao criar pasta de destino<br>";
        exit;
    }
} else {
    echo "✅ Pasta de destino já existe<br>";
}

// Migrar arquivos
if (is_dir($sourceDir)) {
    $files = glob($sourceDir . "*.{jpg,jpeg,png,webp}", GLOB_BRACE);
    echo "<br><h3>📁 Migrando " . count($files) . " arquivo(s):</h3>";
    
    foreach ($files as $sourceFile) {
        $fileName = basename($sourceFile);
        $targetFile = $targetDir . $fileName;
        
        if (copy($sourceFile, $targetFile)) {
            echo "✅ $fileName migrado<br>";
            
            // Testar acesso
            $testUrl = 'https://' . $_SERVER['SERVER_NAME'] . '/images/announcements/' . $fileName;
            echo "&nbsp;&nbsp;&nbsp;🔗 <a href='$testUrl' target='_blank'>Testar acesso</a><br>";
        } else {
            echo "❌ Erro ao migrar $fileName<br>";
        }
    }
    
    // Atualizar banco de dados para usar caminhos corretos
    require_once __DIR__ . '/../config/database.php';
    
    echo "<br><h3>🗄️ Atualizando banco de dados:</h3>";
    
    try {
        $stmt = $pdo->prepare("
            UPDATE upcoming_announcements 
            SET image_path = CONCAT('/images/announcements/', SUBSTRING_INDEX(image_path, '/', -1))
            WHERE image_path IS NOT NULL AND image_path != ''
        ");
        $stmt->execute();
        
        echo "✅ Caminhos no banco atualizados<br>";
        
        // Mostrar resultado
        $stmt = $pdo->query("SELECT id, title, image_path FROM upcoming_announcements WHERE image_path IS NOT NULL");
        $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<br><h4>📋 Resultado final:</h4>";
        foreach ($announcements as $announcement) {
            echo "- " . htmlspecialchars($announcement['title']) . " → " . htmlspecialchars($announcement['image_path']) . "<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Erro ao atualizar banco: " . $e->getMessage() . "<br>";
    }
    
} else {
    echo "❌ Pasta de origem não encontrada<br>";
}

echo "<br><h3>🏠 Teste Final:</h3>";
echo "<a href='index.php' class='btn'>Ver Home Page</a> | ";
echo "<a href='debug_imagens.php' class='btn'>Debug Imagens</a>";

echo "<style>.btn { background: #007cba; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; margin: 5px; }</style>";
?>