<?php
// ==========================================
// MATCH MAKER V5: BUSCA MANUAL & DROPDOWN
// ==========================================

ini_set('memory_limit', '512M');
set_time_limit(300);

session_start();

// 1. CONEXÃO
$host    = 'localhost';
$db      = 'u335416710_t101_db';
$user    = 'u335416710_t101';
$pass    = 'Pa392ap!';
$charset = 'utf8mb4';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (\PDOException $e) {
    die("Erro Conexão: " . $e->getMessage());
}

// 2. AJAX SAVE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    while (ob_get_level()) ob_end_clean(); 
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'] ?? '';
        $id     = (int)($_POST['id'] ?? 0);
        $text   = $_POST['text'] ?? '';

        if ($id <= 0) throw new Exception("ID inválido ($id). Selecione uma palestra.");
        if (empty($text)) throw new Exception("Texto vazio.");

        if ($action === 'save_bio') {
            $stmt = $pdo->prepare("UPDATE lectures SET speaker_minibio = ? WHERE id = ?");
            $msg = 'Minibio atualizada!';
        } 
        elseif ($action === 'save_desc') {
            $stmt = $pdo->prepare("UPDATE lectures SET description = ? WHERE id = ?");
            $msg = 'Descrição atualizada!';
        } 
        else {
            throw new Exception("Ação desconhecida.");
        }
        
        $stmt->execute([$text, $id]);
        echo json_encode(['status' => 'success', 'msg' => $msg]);

    } catch (Exception $e) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}

// 3. FUNÇÕES AUXILIARES
function cleanStr($str) {
    $str = mb_strtolower($str, 'UTF-8');
    return trim(preg_replace('/[^a-z0-9\s]/', '', $str));
}

function calculateScore($csvKey, $dbTarget) {
    // Algoritmo simples para ordenar as sugestões automáticas
    $c = cleanStr($csvKey);
    $d = cleanStr($dbTarget);
    if ($c === $d) return 100;
    if (strpos($c, $d) !== false || strpos($d, $c) !== false) return 80;
    similar_text($c, $d, $perc);
    return $perc;
}

function readCSV($file) {
    $rows = [];
    if (file_exists($file) && ($h = fopen($file, "r")) !== FALSE) {
        $line = fgets($h);
        $sep = (substr_count($line, ';') > substr_count($line, ',')) ? ';' : ',';
        rewind($h);
        fgetcsv($h, 0, $sep); 
        while (($d = fgetcsv($h, 0, $sep)) !== FALSE) if(array_filter($d)) $rows[] = $d;
        fclose($h);
    }
    return $rows;
}

// 4. CARREGAR DADOS
// Carrega TODAS as palestras para montar o dropdown
$allLectures = $pdo->query("SELECT id, title, speaker, description, speaker_minibio FROM lectures ORDER BY title ASC")->fetchAll();

$csvBios = readCSV('Minibio.csv');
$csvDescs = readCSV('Descrição.csv');

// Parâmetros
$mode = $_GET['mode'] ?? 'desc'; 
$showAll = isset($_GET['all']) && $_GET['all'] == '1';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Match Maker V5 (Manual Select)</title>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f6f8; padding: 20px; color: #333; }
        
        .header { text-align: center; margin-bottom: 20px; }
        .controls { text-align: center; margin-bottom: 30px; }
        
        .nav-btn { 
            padding: 10px 25px; background: white; text-decoration: none; color: #555; 
            border-radius: 4px; margin: 0 5px; font-weight: bold; border: 1px solid #ccc;
        }
        .nav-btn.active { background: #3498db; color: white; border-color: #2980b9; }

        /* Card Layout */
        .match-card { 
            display: flex; background: white; margin-bottom: 25px; border-radius: 8px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.08); overflow: hidden; border: 1px solid #e1e4e8;
            transition: all 0.3s;
        }
        
        .col-source { flex: 0 0 45%; padding: 20px; background: #fff; border-right: 1px solid #eee; }
        .col-target { flex: 1; padding: 20px; background: #fcfcfc; }
        
        h3 { margin-top: 0; color: #3498db; font-size: 12px; text-transform: uppercase; font-weight: 800; }
        
        .main-title { font-size: 16px; font-weight: 700; color: #2c3e50; display: block; margin-bottom: 8px; }
        .content-box { 
            background: #f8f9fa; padding: 10px; border-radius: 4px; 
            font-size: 13px; color: #555; line-height: 1.4; 
            max-height: 150px; overflow-y: auto; border: 1px solid #eee;
        }
        
        /* Sugestões Automáticas */
        .auto-suggestions { margin-bottom: 20px; border-bottom: 1px dashed #ddd; padding-bottom: 15px; }
        .suggestion-btn {
            display: block; width: 100%; text-align: left;
            background: #fff; border: 1px solid #ddd; padding: 10px;
            margin-bottom: 5px; border-radius: 6px; cursor: pointer;
            transition: 0.2s;
        }
        .suggestion-btn:hover { border-color: #3498db; background: #f0f7fb; }
        .suggestion-btn strong { color: #333; display: block; font-size: 14px; }
        .suggestion-btn small { color: #888; font-size: 12px; }
        .score-badge { float: right; font-size: 11px; background: #eee; padding: 2px 6px; border-radius: 4px; }

        /* Busca Manual */
        .manual-search label { display: block; font-weight: bold; font-size: 13px; margin-bottom: 5px; }
        .btn-save-manual {
            margin-top: 10px; width: 100%; background: #27ae60; color: white; border: none;
            padding: 10px; border-radius: 4px; font-weight: bold; cursor: pointer;
        }
        .btn-save-manual:hover { background: #219150; }

        /* Select2 Custom */
        .select2-container { width: 100% !important; }
        .select2-container .select2-selection--single { height: 38px; border-color: #ccc; }
        .select2-selection__rendered { line-height: 36px !important; }

        /* Overlay */
        .processing-overlay {
            position: absolute; top:0; left:0; width:100%; height:100%;
            background: rgba(255,255,255,0.9); display: none;
            align-items: center; justify-content: center; z-index: 10;
            color: #27ae60; font-size: 18px; font-weight: bold; flex-direction: column;
        }
    </style>
</head>
<body>

<div class="header">
    <h2>⚡ Match Maker V5: Busca Manual</h2>
</div>

<div class="controls">
    <a href="?mode=desc&all=<?= $showAll?1:0 ?>" class="nav-btn <?= $mode=='desc'?'active':'' ?>">Descrições</a>
    <a href="?mode=bio&all=<?= $showAll?1:0 ?>" class="nav-btn <?= $mode=='bio'?'active':'' ?>">Minibios</a>
    
    <div style="margin-top: 10px;">
        <?php if($showAll): ?>
            <a href="?mode=<?= $mode ?>&all=0" style="color:red; font-size:14px;">[Ocultar Preenchidos]</a>
        <?php else: ?>
            <a href="?mode=<?= $mode ?>&all=1" style="color:green; font-size:14px;">[Mostrar Tudo]</a>
        <?php endif; ?>
    </div>
</div>

<div class="container" style="max-width: 1100px; margin: 0 auto;">
    <?php
    $count = 0;
    $dataSet = ($mode == 'desc') ? $csvDescs : $csvBios;

    foreach ($dataSet as $row) {
        if ($count >= 15) break; // Limite de 15 por página para não pesar o Select2

        // PREPARA CSV
        if ($mode == 'desc') {
            $csvFullTitle = $row[1] ?? '';
            $csvContent   = $row[2] ?? '';
            $parts = preg_split('/( — | - | – )/', $csvFullTitle);
            $csvKey = trim($parts[0]);
        } else {
            $csvFullTitle = $row[0] ?? ''; // Nome
            $csvKey = $csvFullTitle;
            $csvContent   = $row[1] ?? '';
        }
        
        if (empty($csvKey) || empty($csvContent)) continue;

        // PREPARA SUGESTÕES AUTOMÁTICAS
        $suggestions = [];
        foreach ($allLectures as $lect) {
            // Filtro de preenchidos
            if (!$showAll) {
                if ($mode == 'desc' && !empty($lect['description'])) continue;
                if ($mode == 'bio' && !empty($lect['speaker_minibio'])) continue;
            }

            $target = ($mode == 'desc') ? $lect['title'] : $lect['speaker'];
            $score = calculateScore($csvKey, $target);
            
            if ($score > 40) {
                $suggestions[] = ['id' => $lect['id'], 'text' => $target, 'sub' => $lect['speaker'], 'score' => $score];
            }
        }
        usort($suggestions, function($a, $b) { return $b['score'] <=> $a['score']; });
        $top3 = array_slice($suggestions, 0, 3);

        // Se filtro de vazios ativo e sem sugestões, verifica se vale mostrar para busca manual
        // Aqui mostramos sempre para permitir a busca manual
        
        $count++;
    ?>
    
    <div class="match-card" id="card-<?= $count ?>">
        <div class="processing-overlay">
            <i class="fas fa-check fa-2x"></i> Salvo!
        </div>

        <div class="col-source">
            <h3>Origem (CSV)</h3>
            <span class="main-title"><?= htmlspecialchars($csvFullTitle) ?></span>
            <div class="content-box">
                <?= nl2br(htmlspecialchars(mb_strimwidth($csvContent, 0, 300, "..."))) ?>
            </div>
            <textarea id="payload-<?= $count ?>" style="display:none;"><?= htmlspecialchars($csvContent) ?></textarea>
        </div>

        <div class="col-target">
            
            <?php if(!empty($top3)): ?>
            <div class="auto-suggestions">
                <h3>Sugestões Automáticas</h3>
                <?php foreach($top3 as $sug): ?>
                <button type="button" class="suggestion-btn" onclick="saveMatch(<?= $sug['id'] ?>, <?= $count ?>)">
                    <span class="score-badge"><?= round($sug['score']) ?>%</span>
                    <strong><?= htmlspecialchars($sug['text']) ?></strong>
                    <small><?= htmlspecialchars($sug['sub']) ?></small>
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="manual-search">
                <label><i class="fas fa-search"></i> Vincular Manualmente:</label>
                <select class="select2-lecture" id="select-<?= $count ?>" style="width: 100%;">
                    <option value="">Digite para buscar...</option>
                    <?php 
                    // Monta as opções (Idealmente seria via AJAX para performance, mas para <2000 itens é ok)
                    foreach ($allLectures as $l) {
                        $label = ($mode == 'desc') 
                            ? $l['title'] . " (" . $l['speaker'] . ")" 
                            : $l['speaker'] . " - " . $l['title'];
                        echo "<option value='{$l['id']}'>" . htmlspecialchars($label) . "</option>";
                    } 
                    ?>
                </select>
                <button type="button" class="btn-save-manual" onclick="saveManual(<?= $count ?>)">
                    Vincular e Salvar
                </button>
            </div>

        </div>
    </div>
    <?php } ?>

    <?php if ($count == 0): ?>
        <div style="text-align:center; padding:50px; color:#888;">
            <h2>Nenhuma pendência encontrada!</h2>
            <p>Se quiser editar itens já preenchidos, clique em <strong>[Mostrar Tudo]</strong> no topo.</p>
        </div>
    <?php endif; ?>

</div>

<script>
$(document).ready(function() {
    // Inicializa Select2 em todos os dropdowns
    $('.select2-lecture').select2({
        placeholder: "Pesquise por nome ou título...",
        allowClear: true
    });
});

function saveManual(cardId) {
    const selectId = '#select-' + cardId;
    const dbId = $(selectId).val();
    
    if (!dbId) {
        alert("Por favor, selecione uma palestra na lista ou use uma sugestão automática.");
        return;
    }
    saveMatch(dbId, cardId);
}

function saveMatch(dbId, cardId) {
    const textArea = document.getElementById('payload-' + cardId);
    const card = document.getElementById('card-' + cardId);
    const overlay = card.querySelector('.processing-overlay');
    
    // UI Loading
    overlay.style.display = 'flex';

    const text = textArea.value;
    const urlParams = new URLSearchParams(window.location.search);
    const mode = urlParams.get('mode') || 'desc';
    const action = (mode === 'desc') ? 'save_desc' : 'save_bio';

    const formData = new FormData();
    formData.append('action', action);
    formData.append('id', dbId);
    formData.append('text', text);

    fetch(window.location.href.split('?')[0], {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            setTimeout(() => {
                card.style.opacity = '0';
                card.style.height = '0px';
                card.style.marginBottom = '0px';
                setTimeout(() => card.remove(), 500);
            }, 800);
        } else {
            alert('Erro: ' + data.msg);
            overlay.style.display = 'none';
        }
    })
    .catch(error => {
        console.error(error);
        alert('Erro de comunicação. Verifique o console.');
        overlay.style.display = 'none';
    });
}
</script>

</body>
</html>