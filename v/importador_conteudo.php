<?php
// ==========================================
// IMPORTADOR DE CONTEÚDO V2 (ALGORITMO INTELIGENTE)
// ==========================================
// Coloque este arquivo na mesma pasta dos CSVs:
// 1. Minibio.csv
// 2. Descrição.csv

header('Content-Type: text/html; charset=utf-8');
set_time_limit(300); // Aumenta tempo limite para processamento pesado

// 1. CONFIGURAÇÕES
$host    = 'localhost';
$db      = 'u335416710_t101_db';
$user    = 'u335416710_t101';
$pass    = 'Pa392ap!';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Erro de conexão DB: " . $e->getMessage());
}

echo "<style>body{font-family:sans-serif; line-height:1.5;} .ok{color:green;} .fail{color:#ccc;} .warn{color:orange;}</style>";
echo "<h1>Relatório de Importação Inteligente (V2)</h1>";

// ==========================================
// FUNÇÕES AUXILIARES
// ==========================================

// Remove acentos, minúsculas, espaços extras
function normalize($str) {
    $str = mb_strtolower($str, 'UTF-8');
    $str = str_replace(
        ['á','à','ã','â','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','õ','ô','ö','ú','ù','û','ü','ç','ñ'],
        ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n'],
        $str
    );
    $str = preg_replace('/[^a-z0-9\s]/', '', $str); // Remove pontuação
    return trim(preg_replace('/\s+/', ' ', $str));  // Remove espaços duplos
}

// Encontra o melhor match no array do banco
function findBestMatch($target, $candidatesList, $threshold = 80) {
    $targetNorm = normalize($target);
    $bestScore = 0;
    $bestId = null;
    $bestName = '';

    foreach ($candidatesList as $candidate) {
        $candidateNorm = normalize($candidate['text']);
        
        // Similaridade de texto (0 a 100)
        similar_text($targetNorm, $candidateNorm, $percent);
        
        // Bônus para "contém" (se um nome está dentro do outro)
        if (strpos($candidateNorm, $targetNorm) !== false || strpos($targetNorm, $candidateNorm) !== false) {
            $percent += 10; 
        }

        if ($percent > $bestScore) {
            $bestScore = $percent;
            $bestId = $candidate['id'];
            $bestName = $candidate['text'];
        }
    }

    if ($bestScore >= $threshold) {
        return ['id' => $bestId, 'score' => $bestScore, 'match_name' => $bestName];
    }
    return false;
}

function readCSV($csvFile) {
    $rows = [];
    if (!file_exists($csvFile)) {
        echo "<p style='color:red'>Erro: Arquivo <strong>$csvFile</strong> não encontrado.</p>";
        return false;
    }
    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        $header = fgetcsv($handle, 1000, ";"); // Pula cabeçalho
        while (($data = fgetcsv($handle, 2000, ";")) !== FALSE) {
            if (array_filter($data)) $rows[] = $data;
        }
        fclose($handle);
    }
    return $rows;
}

// ==========================================
// 1. CARREGAR DADOS DO BANCO (MEMÓRIA)
// ==========================================
// Carregamos tudo para arrays para comparar com PHP
echo "<p>Carregando dados do banco para memória...</p>";

$stmt = $pdo->query("SELECT id, speaker FROM lectures WHERE speaker IS NOT NULL AND speaker != ''");
$dbSpeakers = [];
while ($row = $stmt->fetch()) {
    $dbSpeakers[] = ['id' => $row['id'], 'text' => $row['speaker']];
}

$stmt = $pdo->query("SELECT id, title FROM lectures");
$dbTitles = [];
while ($row = $stmt->fetch()) {
    $dbTitles[] = ['id' => $row['id'], 'text' => $row['title']];
}

echo "<p>Banco carregado. Iniciando comparação...</p><hr>";

// ==========================================
// 2. PROCESSAR MINIBIOS
// ==========================================
echo "<h2>1. Processando Minibios...</h2>";
$bioData = readCSV('Minibio.csv');

if ($bioData) {
    $updated = 0; $notFound = 0;
    $stmtBio = $pdo->prepare("UPDATE lectures SET speaker_minibio = ? WHERE id = ?");

    foreach ($bioData as $row) {
        $csvSpeaker = trim($row[0] ?? '');
        $bioText    = trim($row[1] ?? '');

        if (empty($csvSpeaker) || empty($bioText)) continue;

        // Tenta encontrar o palestrante no array do banco
        // Threshold de 75% é bom para nomes (permite abreviações leves ou erros de digitação)
        $match = findBestMatch($csvSpeaker, $dbSpeakers, 75);

        if ($match) {
            $stmtBio->execute([$bioText, $match['id']]);
            $updated++;
            // Descomente abaixo para ver os matches acontecendo
            // echo "<div class='ok'>[Match {$match['score']}%] CSV: <b>$csvSpeaker</b> -> DB: <b>{$match['match_name']}</b></div>";
        } else {
            echo "<div class='fail'>Não encontrado: $csvSpeaker</div>";
            $notFound++;
        }
    }
    echo "<p><strong>Resultado Minibios:</strong> $updated atualizados, $notFound sem correspondência.</p>";
}

// ==========================================
// 3. PROCESSAR DESCRIÇÕES
// ==========================================
echo "<h2>2. Processando Descrições...</h2>";
$descData = readCSV('Descrição.csv'); // Verifique se o nome do arquivo tem caracteres especiais

if ($descData) {
    $updated = 0; $notFound = 0;
    $stmtDesc = $pdo->prepare("UPDATE lectures SET description = ? WHERE id = ?");

    foreach ($descData as $row) {
        $csvFullTitle = trim($row[1] ?? ''); // Coluna Título
        $descText     = trim($row[2] ?? '');

        if (empty($csvFullTitle) || empty($descText)) continue;

        // LIMPEZA DO TÍTULO DO CSV
        // Remove o nome do palestrante que vem depois de traços
        // Ex: "Tradução de Games — Fulano" vira "Tradução de Games"
        $separators = [' — ', ' - ', ' – ', ' -- '];
        $cleanTitle = $csvFullTitle;
        foreach ($separators as $sep) {
            if (strpos($cleanTitle, $sep) !== false) {
                $parts = explode($sep, $cleanTitle);
                $cleanTitle = trim($parts[0]);
                break;
            }
        }

        // Tenta encontrar o título no banco
        // Threshold de 80% para títulos
        $match = findBestMatch($cleanTitle, $dbTitles, 80);

        if ($match) {
            $stmtDesc->execute([$descText, $match['id']]);
            $updated++;
        } else {
            echo "<div class='fail'>Título não encontrado: <b>$cleanTitle</b> (Original: $csvFullTitle)</div>";
            $notFound++;
        }
    }
    echo "<p><strong>Resultado Descrições:</strong> $updated atualizados, $notFound sem correspondência.</p>";
}

echo "<hr><h3>Processo Finalizado.</h3>";
?>