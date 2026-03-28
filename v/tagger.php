<?php
// ==========================================
// FERRAMENTA VISUAL DE TAGUEAMENTO MANUAL
// ==========================================

// 1. CONFIGURAÇÕES DE BANCO DE DADOS
$host    = 'localhost';
$db      = 'u335416710_t101_db';
$user    = 'u335416710_t101';
$pass    = 'Pa392ap!';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Erro de conexão com o banco: " . $e->getMessage());
}

// 2. MAPA DE TAGS (Coluna no BD => Nome para Exibir)
$tagsMap = [
    'Tipos' => [
        'is_translation'    => 'Tradução',
        'is_interpretation' => 'Interpretação',
        'is_revision'       => 'Revisão',
        'is_dubbing'        => 'Dublagem',
        'is_subtitling'     => 'Legendagem',
        'is_literary'       => 'Literária',
    ],
    'Especialidades' => [
        'is_medical'   => 'Médica',
        'is_legal'     => 'Jurídica',
        'is_technical' => 'Técnica',
        'is_gaming'    => 'Games',
        'is_marketing' => 'Marketing',
    ],
    'Separação Nova' => [
        'is_career'    => 'Carreira/Negócios', // Nova coluna
        'is_wellness'  => 'Cuidados/Saúde',    // Antiga (usada para ambos antes)
    ],
    'Outros' => [
        'is_tools'    => 'Ferramentas (CAT)',
        'is_beginner' => 'Iniciante/Geral',
        'is_language' => 'Língua/Gramática',
        'is_course'   => 'Curso',
    ]
];

// 3. PROCESSAMENTO AJAX (SALVAR)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $id = $_POST['id'];
    $selectedTags = $_POST['tags'] ?? []; 

    // Prepara atualização dinâmica baseada no $tagsMap
    $updates = [];
    $params = [];

    // Itera por todas as tags possíveis para definir 1 ou 0
    foreach ($tagsMap as $group) {
        foreach ($group as $col => $label) {
            $val = in_array($col, $selectedTags) ? 1 : 0;
            $updates[] = "$col = ?";
            $params[] = $val;
        }
    }
    
    $sql = "UPDATE lectures SET " . implode(', ', $updates) . " WHERE id = ?";
    $params[] = $id;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['status' => 'success', 'msg' => 'Salvo com sucesso!']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'msg' => 'Erro DB: ' . $e->getMessage()]);
    }
    exit;
}

// 4. LÓGICA DE FILTRAGEM E BUSCA
$filter = $_GET['filter'] ?? 'all';
$sql = "SELECT * FROM lectures";

// Filtros WHERE
if ($filter === 'pending') {
    // Filtra onde a soma de todas as tags é 0
    $colsSum = [];
    foreach ($tagsMap as $group) {
        foreach ($group as $col => $label) $colsSum[] = $col;
    }
    // Verifica se colunas existem antes de somar (evita erro se coluna nova não existir no array)
    if (!empty($colsSum)) {
        $sql .= " WHERE (" . implode(' + ', $colsSum) . ") = 0";
    }
} 
elseif ($filter === 'review_wellness') {
    // Filtra itens marcados com a tag antiga para separar
    $sql .= " WHERE is_wellness = 1";
}

$sql .= " ORDER BY title ASC";

try {
    $stmt = $pdo->query($sql);
    $lectures = $stmt->fetchAll();
} catch (Exception $e) {
    die("Erro ao buscar palestras. Verifique se criou a coluna 'is_career'. Erro: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tagueador T101</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; padding: 20px; color: #333; }
        
        /* Header e Botões */
        .header { 
            background: white; 
            padding: 20px; 
            border-radius: 12px; 
            margin-bottom: 30px; 
            display: flex; 
            flex-wrap: wrap; 
            justify-content: space-between; 
            align-items: center; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.05); 
        }
        .header h2 { margin: 0 0 5px 0; color: #2c3e50; }
        .header p { margin: 0; color: #7f8c8d; }
        
        .filters { display: flex; gap: 10px; }
        .btn { 
            padding: 10px 20px; 
            text-decoration: none; 
            color: white; 
            border-radius: 6px; 
            font-weight: 600; 
            border: none; 
            cursor: pointer; 
            font-size: 0.9rem;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.9; }
        .btn-blue { background: #3498db; } 
        .btn-gray { background: #95a5a6; } 
        .btn-green { background: #2ecc71; }
        .btn-orange { background: #e67e22; }

        /* Grid de Cards */
        .grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); 
            gap: 25px; 
        }
        .card { 
            background: white; 
            border-radius: 12px; 
            padding: 25px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); 
            border-left: 6px solid #e0e0e0; 
            transition: transform 0.2s; 
        }
        .card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .card.tagged { border-left-color: #2ecc71; background-color: #fafffb; }
        
        /* Conteúdo do Card */
        .card h3 { margin-top: 0; color: #2c3e50; font-size: 1.1rem; margin-bottom: 5px; }
        .meta { font-size: 0.85rem; color: #7f8c8d; margin-bottom: 15px; font-weight: 600; text-transform: uppercase; }
        .desc { 
            font-size: 0.9rem; 
            color: #555; 
            height: 70px; 
            overflow-y: auto; 
            margin-bottom: 20px; 
            background: #f9f9f9; 
            padding: 10px; 
            border-radius: 6px;
            line-height: 1.4;
        }

        /* Checkboxes e Tags */
        .tags-container { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 15px; 
            margin-bottom: 20px; 
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        .tag-group { flex: 1; min-width: 110px; }
        .tag-group h4 { 
            font-size: 0.7rem; 
            text-transform: uppercase; 
            color: #aaa; 
            margin: 0 0 8px 0; 
            font-weight: 700; 
            letter-spacing: 0.5px;
        }
        .tag-item { 
            display: flex; 
            align-items: center; 
            font-size: 0.85rem; 
            margin-bottom: 6px; 
            cursor: pointer; 
            color: #444;
        }
        .tag-item input { margin-right: 8px; transform: scale(1.2); cursor: pointer; }
        
        /* Highlight para as tags de separação */
        .highlight-group label { color: #d35400; font-weight: 600; }

        /* Loading */
        .saving { opacity: 0.6; pointer-events: none; filter: grayscale(1); }
    </style>
</head>
<body>

    <div class="header">
        <div>
            <h2>🏷️ Tagueador de Palestras</h2>
            <p>Gerenciamento de metadados para o Recomendador</p>
        </div>
        <div class="filters">
            <a href="?filter=all" class="btn <?php echo $filter=='all'?'btn-blue':'btn-gray';?>">
                Todas (<?php echo ($filter=='all') ? count($lectures) : 'Ver'; ?>)
            </a>
            
            <a href="?filter=pending" class="btn <?php echo $filter=='pending'?'btn-blue':'btn-gray';?>">
                Pendentes
            </a>

            <a href="?filter=review_wellness" class="btn <?php echo $filter=='review_wellness'?'btn-orange':'btn-gray';?>">
                Revisar (Antigas)
            </a>
        </div>
    </div>

    <div class="grid">
        <?php 
        if (count($lectures) == 0) {
            echo "<p style='grid-column: 1/-1; text-align:center; color:#888; padding:40px;'>Nenhuma palestra encontrada neste filtro.</p>";
        }

        foreach ($lectures as $l): 
            // Verifica se tem tags para definir cor da borda
            $hasTags = false;
            foreach ($tagsMap as $g) {
                foreach ($g as $k => $v) {
                    if (isset($l[$k]) && $l[$k] == 1) $hasTags = true;
                }
            }
        ?>
        <div class="card <?php echo $hasTags ? 'tagged' : ''; ?>" id="card-<?php echo $l['id']; ?>">
            <h3><?php echo htmlspecialchars($l['title']); ?></h3>
            <div class="meta">👤 <?php echo htmlspecialchars($l['speaker']); ?></div>
            
            <div class="desc" title="<?php echo htmlspecialchars($l['description']); ?>">
                <?php echo mb_strimwidth(strip_tags($l['description']), 0, 180, "..."); ?>
            </div>

            <form onsubmit="saveTags(event, '<?php echo $l['id']; ?>')">
                <div class="tags-container">
                    <?php foreach ($tagsMap as $groupName => $tags): 
                        $isHighlight = ($groupName === 'Separação Nova');
                    ?>
                    <div class="tag-group <?php echo $isHighlight ? 'highlight-group' : ''; ?>">
                        <h4 <?php echo $isHighlight ? 'style="color:#e67e22;"' : ''; ?>>
                            <?php echo $groupName; ?>
                        </h4>
                        
                        <?php foreach ($tags as $col => $label): ?>
                            <label class="tag-item">
                                <input type="checkbox" name="tags[]" value="<?php echo $col; ?>" 
                                    <?php echo (isset($l[$col]) && $l[$col] == 1) ? 'checked' : ''; ?>> 
                                <?php echo $label; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-blue" style="width:100%">Salvar Tags</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
        function saveTags(e, id) {
            e.preventDefault();
            const form = e.target;
            const card = document.getElementById('card-' + id);
            const btn = form.querySelector('button');
            const originalText = btn.innerText;

            // Feedback Visual de Carregamento
            btn.innerText = "Salvando...";
            card.classList.add('saving');

            // Prepara dados
            const formData = new FormData(form);
            formData.append('action', 'save');
            formData.append('id', id);

            fetch('?', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                card.classList.remove('saving');
                
                if (data.status === 'success') {
                    // Sucesso
                    btn.innerText = "Salvo! ✅";
                    btn.classList.remove('btn-blue');
                    btn.classList.add('btn-green');
                    card.classList.add('tagged'); // Marca visualmente como tagueado
                    
                    // Reseta botão após 1.5s
                    setTimeout(() => {
                        btn.innerText = originalText;
                        btn.classList.add('btn-blue');
                        btn.classList.remove('btn-green');
                    }, 1500);
                } else {
                    alert('Erro ao salvar: ' + data.msg);
                    btn.innerText = "Erro ❌";
                }
            })
            .catch(err => {
                console.error(err);
                alert('Erro de conexão com o servidor.');
                card.classList.remove('saving');
                btn.innerText = "Erro ❌";
            });
        }
    </script>
</body>
</html>