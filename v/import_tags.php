<?php
// ==========================================
// SCRIPT DE IMPORTAÇÃO V6 - O "CUPIDO" (LADO A LADO)
// ==========================================

ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. CONFIGURAÇÕES
// ------------------------------------------
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
    die("❌ Erro DB: " . $e->getMessage());
}

// 2. FUNÇÕES
// ------------------------------------------
function normalizeString($str) {
    if (!$str) return "";
    $str = mb_strtolower($str, 'UTF-8');
    $str = preg_replace('/[áàãâä]/u', 'a', $str);
    $str = preg_replace('/[éèêë]/u', 'e', $str);
    $str = preg_replace('/[íìîï]/u', 'i', $str);
    $str = preg_replace('/[óòõôö]/u', 'o', $str);
    $str = preg_replace('/[úùûü]/u', 'u', $str);
    $str = preg_replace('/[ç]/u', 'c', $str);
    $str = preg_replace('/[^a-z0-9\s]/', '', $str);
    return trim($str);
}

function checkTokenMatch($csvTitle, $dbTitle) {
    $ignore = ['de', 'da', 'do', 'em', 'para', 'com', 'um', 'uma', 'a', 'o', 'e', 'na', 'no', 'aula', 'curso'];
    $csvTokens = array_filter(explode(' ', normalizeString($csvTitle)));
    $dbTitleNorm = normalizeString($dbTitle);
    
    $matches = 0;
    $totalRelevantTokens = 0;

    foreach ($csvTokens as $token) {
        if (strlen($token) < 3 || in_array($token, $ignore)) continue;
        $totalRelevantTokens++;
        if (strpos($dbTitleNorm, $token) !== false) {
            $matches++;
        }
    }
    if ($totalRelevantTokens == 0) return false;
    return (($matches / $totalRelevantTokens) >= 0.75); // Baixei um pouco para 75%
}

// 3. INDEXAÇÃO
// ------------------------------------------
$stmt = $pdo->query("SELECT id, title, speaker FROM lectures ORDER BY title ASC");
$dbLectures = $stmt->fetchAll();

// Mapeamento auxiliar para busca rápida
$speakerIndex = [];
foreach ($dbLectures as $lecture) {
    $keySpeaker = str_replace(' ', '', normalizeString($lecture['speaker']));
    if (!empty($keySpeaker)) {
        $speakerIndex[$keySpeaker][] = $lecture['id'];
    }
}

$columnMap = [
    4 => 'is_translation', 5 => 'is_interpretation', 6 => 'is_revision', 
    7 => 'is_tools', 8 => 'is_beginner', 9 => 'is_language', 
    10 => 'is_wellness', 11 => 'is_marketing', 12 => 'is_dubbing',
    13 => 'is_subtitling', 14 => 'is_literary', 15 => 'is_gaming',
    16 => 'is_legal', 17 => 'is_medical', 18 => 'is_technical', 19 => 'is_course'
];

$csvFile = 'Palestras gerais.csv';
if (!file_exists($csvFile)) die("❌ Arquivo CSV não encontrado.");

$handle = fopen($csvFile, "r");
$firstLine = fgets($handle);
$delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ";" : ",";
rewind($handle);
fgetcsv($handle, 0, $delimiter);

$updatedCount = 0;
$notFoundCount = 0;
$failures = [];
$matchedDbIds = []; // ARRAY PARA RASTREAR QUEM JÁ DEU MATCH
$rowNumber = 1;

// 4. PROCESSAMENTO
// ------------------------------------------
while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
    $rowNumber++;
    if (!is_array($data) || count($data) < 3 || empty(trim($data[1] ?? ''))) continue;

    $csvTitle = trim($data[1]);
    $csvSpeaker = trim($data[2] ?? '');
    
    $csvTitleNorm = str_replace(' ', '', normalizeString($csvTitle));
    $csvSpeakerKey = str_replace(' ', '', normalizeString($csvSpeaker));
    
    $foundId = null;

    // 1. Título (Contém)
    foreach ($dbLectures as $lecture) {
        $dbTitleNorm = str_replace(' ', '', normalizeString($lecture['title']));
        if (strpos($dbTitleNorm, $csvTitleNorm) !== false || strpos($csvTitleNorm, $dbTitleNorm) !== false) {
            $foundId = $lecture['id'];
            break;
        }
    }

    // 2. Palestrante Único
    if (!$foundId && !empty($csvSpeakerKey) && isset($speakerIndex[$csvSpeakerKey])) {
        if (count($speakerIndex[$csvSpeakerKey]) === 1) {
            $foundId = $speakerIndex[$csvSpeakerKey][0];
        }
    }

    // 3. Tokens
    if (!$foundId) {
        foreach ($dbLectures as $lecture) {
            if (!empty($csvSpeakerKey)) {
                $dbSpeakerKey = str_replace(' ', '', normalizeString($lecture['speaker']));
                if ($dbSpeakerKey !== $csvSpeakerKey) continue;
            }
            if (checkTokenMatch($csvTitle, $lecture['title'])) {
                $foundId = $lecture['id'];
                break;
            }
        }
    }

    // ATUALIZAÇÃO
    if ($foundId) {
        $matchedDbIds[] = $foundId; // <--- MARCA COMO ENCONTRADO
        
        $updates = [];
        $params = [];
        foreach ($columnMap as $index => $dbColumn) {
            if (isset($data[$index])) {
                $val = (strtolower(trim($data[$index])) == 'x') ? 1 : 0;
                $updates[] = "$dbColumn = ?";
                $params[] = $val;
            }
        }
        if (!empty($updates)) {
            $sql = "UPDATE lectures SET " . implode(', ', $updates) . " WHERE id = ?";
            $params[] = $foundId;
            $stmtUpdate = $pdo->prepare($sql);
            $stmtUpdate->execute($params);
            $updatedCount++;
        }
    } else {
        $failures[] = ['row' => $rowNumber, 'title' => $csvTitle, 'speaker' => $csvSpeaker];
        $notFoundCount++;
    }
}
fclose($handle);

// 5. CALCULAR OS "ORFÃOS DO BANCO"
// ------------------------------------------
$orphanDbLectures = [];
foreach ($dbLectures as $lecture) {
    if (!in_array($lecture['id'], $matchedDbIds)) {
        $orphanDbLectures[] = $lecture;
    }
}

// 6. RELATÓRIO LADO A LADO
// ------------------------------------------
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Discrepâncias</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f4f4f4; }
        .container { display: flex; gap: 20px; }
        .col { flex: 1; background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h2 { border-bottom: 2px solid #ccc; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #eee; padding: 8px; text-align: left; }
        tr:hover { background: #f9f9f9; }
        .badge-fail { color: red; font-weight: bold; }
        .badge-warn { color: orange; font-weight: bold; }
        .alert { background: #dff0d8; padding: 10px; border-radius: 4px; color: #3c763d; margin-bottom: 20px;}
    </style>
</head>
<body>

    <div class="alert">
        <strong>Resultado Final:</strong><br>
        ✅ <?php echo $updatedCount; ?> palestras conectadas com sucesso.<br>
        ⚠️ <?php echo $notFoundCount; ?> palestras do Excel sobraram.<br>
        ⚠️ <?php echo count($orphanDbLectures); ?> palestras do Banco sobraram (não receberam tags).
    </div>

    <div class="container">
        <div class="col">
            <h2 class="badge-fail">1. Excel (Não Encontradas)</h2>
            <p>Estas palestras estão no seu arquivo, mas o sistema não achou um par no banco.</p>
            <table>
                <thead>
                    <tr>
                        <th>Linha</th>
                        <th>Título no Excel</th>
                        <th>Palestrante</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($failures as $f): ?>
                    <tr>
                        <td><?php echo $f['row']; ?></td>
                        <td><?php echo $f['title']; ?></td>
                        <td><strong><?php echo $f['speaker']; ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="col">
            <h2 class="badge-warn">2. Banco de Dados (Disponíveis)</h2>
            <p>Estas palestras existem no banco, mas ninguém deu "match" com elas.</p>
            <table>
                <thead>
                    <tr>
                        <th>Título no Banco (Copie este nome para o Excel)</th>
                        <th>Palestrante</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orphanDbLectures as $dbL): ?>
                    <tr>
                        <td style="color:blue"><?php echo $dbL['title']; ?></td>
                        <td><strong><?php echo $dbL['speaker']; ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>