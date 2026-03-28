<?php
// Script simples para testar a conexão e verificar se tudo funciona

echo "<h2>🔧 Teste do Sistema de Palestras</h2>";

// Testar conexão com banco
try {
    require_once __DIR__ . '/../config/database.php';
    echo "✅ Conexão com banco: <strong>OK</strong><br>";
    
    // Verificar se existem palestras
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM lectures WHERE category = 'upcoming'");
    $result = $stmt->fetch();
    echo "✅ Palestras no banco: <strong>" . $result['total'] . "</strong><br>";
    
    // Verificar estrutura da tabela
    $stmt = $pdo->query("DESCRIBE lectures");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "✅ Colunas da tabela lectures: " . implode(', ', $columns) . "<br>";
    
    // Verificar se pasta de upload existe
    $uploadDir = __DIR__ . '/../images/lectures/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        echo "✅ Pasta de upload criada: <strong>" . $uploadDir . "</strong><br>";
    } else {
        echo "✅ Pasta de upload existe: <strong>" . $uploadDir . "</strong><br>";
    }
    
    // Verificar permissões
    if (is_writable($uploadDir)) {
        echo "✅ Pasta de upload tem permissão de escrita: <strong>OK</strong><br>";
    } else {
        echo "❌ Pasta de upload NÃO tem permissão de escrita<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erro de conexão: " . $e->getMessage() . "<br>";
}

echo "<br><h3>📋 Instruções:</h3>";
echo "1. Se todos os itens estão ✅, o sistema deve funcionar<br>";
echo "2. Acesse sua home page para testar<br>";
echo "3. Se você é admin, verá os botões de edição<br>";
echo "4. Use o arquivo <strong>index.php</strong> da pasta Entregas_1<br>";

echo "<br><h3>🚀 Para usar em produção:</h3>";
echo "1. Copie os arquivos da pasta Entregas_1 para sua pasta principal<br>";
echo "2. Substitua o index.php original<br>";
echo "3. Teste a funcionalidade admin<br>";
?>