<?php
// update_courses_table_simple.php
require_once __DIR__ . '/../config/database.php';

echo "<style>
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        color: #fff;
        padding: 40px;
        line-height: 1.6;
    }
    .btn {
        display: inline-block;
        padding: 10px 20px;
        background: #007AFF;
        color: white;
        text-decoration: none;
        border-radius: 8px;
        margin-top: 20px;
    }
</style>";

echo "<h2>Atualizando estrutura da tabela 'courses' (versão simplificada)...</h2><br>";

try {
    // Recriar tabela com estrutura simplificada
    $pdo->exec("DROP TABLE IF EXISTS courses");
    
    $pdo->exec("CREATE TABLE courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE,
        enrollment_open TINYINT(1) DEFAULT 1,
        manual_close TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    echo "✓ Tabela 'courses' recriada com estrutura simplificada!<br><br>";
    echo "<strong>Colunas:</strong><br>";
    echo "• id (auto)<br>";
    echo "• title (nome do curso)<br>";
    echo "• start_date (data de início)<br>";
    echo "• end_date (data de término - opcional)<br>";
    echo "• enrollment_open (inscrições abertas: 1=sim, 0=não)<br>";
    echo "• manual_close (fechamento manual: 1=sim, 0=não)<br>";
    echo "• created_at / updated_at (auto)<br>";
    
    echo "<br><a href='migrate_courses.php' class='btn'>Continuar para Migração de Cursos</a>";
    
} catch (PDOException $e) {
    echo "✗ Erro: " . $e->getMessage();
}
?>