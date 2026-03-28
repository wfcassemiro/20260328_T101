<?php
// migrate_courses.php
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
        margin-right: 10px;
    }
    .btn-secondary {
        background: rgba(255,255,255,0.1);
    }
</style>";

// Verificar se já existem cursos
$stmt = $pdo->query("SELECT COUNT(*) FROM courses");
$count = $stmt->fetchColumn();

if ($count > 0) {
    echo "⚠️ Já existem {$count} curso(s) cadastrado(s). Deseja adicionar os cursos mesmo assim?<br><br>";
    echo "<a href='?force=1' class='btn'>Sim, adicionar</a>";
    echo "<a href='gerenciar_cursos.php' class='btn btn-secondary'>Não, ir para gerenciar</a>";
    
    if (!isset($_GET['force'])) {
        exit;
    }
    echo "<br><br>";
}

// Dados dos cursos (apenas o essencial)
$courses = [
    [
        'title' => 'Curso de CAT Tools e Tecnologias da Tradução',
        'start_date' => '2024-10-20',
        'end_date' => '2024-10-29'
    ],
    [
        'title' => 'Supercurso de Tradução de Jogos',
        'start_date' => '2024-11-03',
        'end_date' => '2024-12-03'
    ]
];

$inserted = 0;

foreach ($courses as $course) {
    $today = date('Y-m-d');
    $enrollment_open = ($course['start_date'] >= $today) ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO courses (title, start_date, end_date, enrollment_open) VALUES (?, ?, ?, ?)");
        $stmt->execute([$course['title'], $course['start_date'], $course['end_date'], $enrollment_open]);
        
        $status = $enrollment_open ? '🟢 Inscrições abertas' : '🔴 Inscrições encerradas';
        echo "✓ <strong>" . htmlspecialchars($course['title']) . "</strong> {$status}<br>";
        $inserted++;
        
    } catch (PDOException $e) {
        echo "✗ Erro: " . $e->getMessage() . "<br>";
    }
}

echo "<br><strong>✓ {$inserted} curso(s) adicionado(s) com sucesso!</strong><br><br>";
echo "<a href='gerenciar_cursos.php' class='btn'>Ir para Gerenciar Cursos</a>";
?>