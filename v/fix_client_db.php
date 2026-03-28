<?php
/**
 * Atualização DB: Suporte a Pomodoro e Projetos de Tempo
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/config/database.php';

echo "<h1>Atualizando Estrutura para Time Tracker V4</h1>";

try {
    // 1. Adicionar flag em Projetos (para distinguir projetos de controle de tempo)
    // is_time_tracker_only: 1 = Sim (não conta financeiro), 0 = Não (Projeto normal)
    $stmt = $pdo->query("SHOW COLUMNS FROM dash_projects LIKE 'is_time_tracker_only'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE dash_projects ADD COLUMN is_time_tracker_only TINYINT(1) DEFAULT 0 AFTER status");
        echo "<p style='color:green'>✅ Coluna <b>is_time_tracker_only</b> adicionada a projetos.</p>";
    }

    // 2. Adicionar tipo de entrada em Time Entries (para saber se foi manual ou pomodoro)
    $stmt = $pdo->query("SHOW COLUMNS FROM time_entries LIKE 'entry_type'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE time_entries ADD COLUMN entry_type VARCHAR(20) DEFAULT 'manual' AFTER is_running");
        // Valores possíveis: 'manual', 'pomodoro_work', 'pomodoro_break'
        echo "<p style='color:green'>✅ Coluna <b>entry_type</b> adicionada a registros de tempo.</p>";
    }

    echo "<hr><h3>Atualização Concluída!</h3>";
    echo "<a href='time_tracker/time-tracker.php'>Voltar ao Time Tracker</a>";

} catch (PDOException $e) {
    die("<h3 style='color:red'>Erro: " . $e->getMessage() . "</h3>");
}
?>