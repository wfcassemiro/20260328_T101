<?php
// ==========================================
// EDITOR DE CONTEÚDO (DESCRIÇÃO E MINIBIO)
// ==========================================

// 1. CONFIGURAÇÕES
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
    die("Erro DB: " . $e->getMessage());
}

// 2. PROCESSAMENTO AJAX (SALVAR)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $id = $_POST['id'];
    $description = $_POST['description'] ?? '';
    // Corrigido para speaker_minibio
    $speaker_minibio = $_POST['speaker_minibio'] ?? '';

    $sql = "UPDATE lectures SET description = ?, speaker_minibio = ? WHERE id = ?";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$description, $speaker_minibio, $id]);
        echo json_encode(['status' => 'success', 'msg' => 'Conteúdo atualizado!']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}

// 3. BUSCAR PALESTRAS
$filter = $_GET['filter'] ?? 'all';
$sql = "SELECT * FROM lectures";

if ($filter === 'missing') {
    // Filtra onde descrição OU minibio estão vazios/nulos
    $sql .= " WHERE (description IS NULL OR description = '') OR (speaker_minibio IS NULL OR speaker_minibio = '')";
}

$sql .= " ORDER BY title ASC";

try {
    $stmt = $pdo->query($sql);
    $lectures = $stmt->fetchAll();
} catch (Exception $e) {
    die("Erro ao buscar dados. Verifique se a coluna 'speaker_minibio' existe na tabela. Erro: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editor de Conteúdo T101</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; padding: 20px; color: #333; }
        
        /* Header */
        .header { 
            background: white; 
            padding: 20px; 
            border-radius: 12px; 
            margin-bottom: 30px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.05); 
        }
        .header h2 { margin: 0 0 5px 0; color: #2c3e50; }
        
        /* Botões */
        .btn { 
            padding: 10px 20px; 
            text-decoration: none; 
            color: white; 
            border-radius: 6px; 
            font-weight: 600; 
            border: none; 
            cursor: pointer; 
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.9; }
        .btn-blue { background: #3498db; } 
        .btn-gray { background: #95a5a6; } 
        .btn-green { background: #2ecc71; }
        .btn-red { background: #e74c3c; }

        /* Grid */
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(450px, 1fr)); gap: 25px; }
        
        /* Card */
        .card { 
            background: white; 
            border-radius: 12px; 
            padding: 25px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); 
            border-left: 6px solid #e0e0e0; 
        }
        /* Borda vermelha se faltar algo */
        .card.incomplete { border-left-color: #e74c3c; background-color: #fff5f5; }
        /* Borda verde se estiver ok */
        .card.complete { border-left-color: #2ecc71; background-color: #fff; }

        .card h3 { margin-top: 0; color: #2c3e50; font-size: 1.1rem; }
        .meta { font-size: 0.9rem; color: #7f8c8d; margin-bottom: 15px; font-weight: bold; }

        /* Formulário */
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 700; color: #555; margin-bottom: 5px; }
        
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: inherit;
            resize: vertical;
            min-height: 80px;
            font-size: 0.9rem;
        }
        textarea:focus { border-color: #3498db; outline: none; }

        .status-badge {
            float: right;
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 4px;
            color: white;
            font-weight: bold;
        }
        .bg-red { background: #e74c3c; }
        .bg-green { background: #2ecc71; }

        .saving { opacity: 0.6; pointer-events: none; }
    </style>
</head>
<body>

    <div class="header">
        <div>
            <h2>📝 Editor de Conteúdo</h2>
            <p>Gerencie descrições e minibiografias</p>
        </div>
        <div>
            <a href="?filter=all" class="btn <?php echo $filter=='all'?'btn-blue':'btn-gray';?>">
                Todas (<?php echo ($filter=='all') ? count($lectures) : 'Ver'; ?>)
            </a>
            <a href="?filter=missing" class="btn <?php echo $filter=='missing'?'btn-red':'btn-gray';?>">
                Faltando Conteúdo
            </a>
        </div>
    </div>

    <div class="grid">
        <?php foreach ($lectures as $l): 
            $desc = $l['description'] ?? '';
            // Corrigido para speaker_minibio
            $bio  = $l['speaker_minibio'] ?? '';
            
            // Verifica se falta algo
            $isMissing = (empty(trim($desc)) || empty(trim($bio)));
            $statusClass = $isMissing ? 'incomplete' : 'complete';
        ?>
        <div class="card <?php echo $statusClass; ?>" id="card-<?php echo $l['id']; ?>">
            
            <?php if ($isMissing): ?>
                <span class="status-badge bg-red">Incompleto</span>
            <?php else: ?>
                <span class="status-badge bg-green">Ok</span>
            <?php endif; ?>

            <h3><?php echo htmlspecialchars($l['title']); ?></h3>
            <div class="meta">👤 <?php echo htmlspecialchars($l['speaker']); ?></div>

            <form onsubmit="saveContent(event, '<?php echo $l['id']; ?>')">
                
                <div class="form-group">
                    <label>Descrição da Palestra</label>
                    <textarea name="description" rows="4" placeholder="Cole a descrição aqui..."><?php echo htmlspecialchars($desc); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Minibio de <?php echo htmlspecialchars($l['speaker']); ?></label>
                    <textarea name="speaker_minibio" rows="3" placeholder="Cole a biografia aqui..."><?php echo htmlspecialchars($bio); ?></textarea>
                </div>

                <button type="submit" class="btn btn-blue" style="width:100%">Salvar Conteúdo</button>
            </form>
        </div>
        <?php endforeach; ?>
        
        <?php if (count($lectures) == 0): ?>
            <p style="grid-column: 1/-1; text-align: center; color: #777; padding: 40px;">
                Nenhuma palestra encontrada neste filtro. Tudo certo! 🎉
            </p>
        <?php endif; ?>
    </div>

    <script>
        function saveContent(e, id) {
            e.preventDefault();
            const form = e.target;
            const card = document.getElementById('card-' + id);
            const btn = form.querySelector('button');
            const originalText = btn.innerText;

            btn.innerText = "Salvando...";
            card.classList.add('saving');

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
                    btn.innerText = "Salvo! ✅";
                    btn.classList.remove('btn-blue');
                    btn.classList.add('btn-green');
                    
                    // Atualiza visualmente o card para "completo" se os campos foram preenchidos
                    const desc = form.querySelector('[name=description]').value.trim();
                    const bio = form.querySelector('[name=speaker_minibio]').value.trim();
                    const badge = card.querySelector('.status-badge');
                    
                    if (desc && bio) {
                        card.classList.remove('incomplete');
                        card.classList.add('complete');
                        badge.className = 'status-badge bg-green';
                        badge.innerText = 'Ok';
                    } else {
                        card.classList.remove('complete');
                        card.classList.add('incomplete');
                        badge.className = 'status-badge bg-red';
                        badge.innerText = 'Incompleto';
                    }
                    
                    setTimeout(() => {
                        btn.innerText = originalText;
                        btn.classList.add('btn-blue');
                        btn.classList.remove('btn-green');
                    }, 2000);
                } else {
                    alert('Erro: ' + data.msg);
                    btn.innerText = "Erro ❌";
                }
            })
            .catch(err => {
                console.error(err);
                alert('Erro na requisição');
                card.classList.remove('saving');
            });
        }
    </script>
</body>
</html>