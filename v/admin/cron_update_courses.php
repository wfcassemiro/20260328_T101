<?php
// cron_update_courses.php
require_once __DIR__ . '/../config/database.php';

try {
    $today = date('Y-m-d');
    
    // Fechar inscrições automaticamente apenas se não foi fechado manualmente
    $stmt = $pdo->prepare("UPDATE courses 
                          SET enrollment_open = 0 
                          WHERE start_date < ? 
                          AND enrollment_open = 1 
                          AND manual_close = 0");
    $stmt->execute([$today]);
    $closed = $stmt->rowCount();
    
    if ($closed > 0) {
        error_log("Cron: {$closed} curso(s) fechados automaticamente em {$today}");
    }
    
    echo "✓ {$closed} curso(s) atualizados\n";
    
} catch (PDOException $e) {
    error_log("Erro no cron: " . $e->getMessage());
    echo "✗ Erro: " . $e->getMessage() . "\n";
}
?>