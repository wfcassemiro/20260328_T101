<?php
/**
 * Painel de Administração - Translators 101 Dashboard
 * 
 * Este arquivo permite gerenciar os eventos que aparecem no dashboard.
 * Coloque na pasta: v.translators101.com/api/admin.php
 * 
 * IMPORTANTE: Altere a senha abaixo!
 */

// ============================================
// CONFIGURAÇÃO - ALTERE A SENHA AQUI!
// ============================================
$ADMIN_PASSWORD = 'translators101admin'; // MUDE ESTA SENHA!

// ============================================
// NÃO EDITE ABAIXO DESTA LINHA
// ============================================

session_start();

$events_file = __DIR__ . '/events.json';
$message = '';
$message_type = '';

// Verificar login
if (isset($_POST['login'])) {
    if ($_POST['password'] === $ADMIN_PASSWORD) {
        $_SESSION['logged_in'] = true;
    } else {
        $message = 'Senha incorreta!';
        $message_type = 'error';
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Carregar eventos
function loadEvents($file) {
    if (file_exists($file)) {
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        return $data['events'] ?? [];
    }
    return [];
}

// Salvar eventos
function saveEvents($file, $events) {
    $data = [
        'events' => $events,
        'lastUpdated' => date('c'),
        'source' => 'Translators101 Admin'
    ];
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Processar ações (apenas se logado)
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    $events = loadEvents($events_file);
    
    // Adicionar evento
    if (isset($_POST['add_event'])) {
        $new_event = [
            'id' => 'evt-' . uniqid(),
            'title' => [
                'pt-BR' => $_POST['title_pt'],
                'en' => $_POST['title_en']
            ],
            'description' => [
                'pt-BR' => $_POST['description_pt'],
                'en' => $_POST['description_en']
            ],
            'date' => $_POST['date'],
            'time' => $_POST['time'],
            'location' => [
                'pt-BR' => $_POST['location_pt'],
                'en' => $_POST['location_en']
            ],
            'type' => $_POST['type'],
            'price' => $_POST['price'],
            'priceValue' => $_POST['price_value'],
            'url' => $_POST['url'],
            'image' => $_POST['image']
        ];
        $events[] = $new_event;
        saveEvents($events_file, $events);
        $message = 'Evento adicionado com sucesso!';
        $message_type = 'success';
    }
    
    // Excluir evento
    if (isset($_GET['delete'])) {
        $delete_id = $_GET['delete'];
        $events = array_filter($events, function($e) use ($delete_id) {
            return $e['id'] !== $delete_id;
        });
        $events = array_values($events);
        saveEvents($events_file, $events);
        $message = 'Evento excluído!';
        $message_type = 'success';
    }
}

$events = loadEvents($events_file);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Translators 101 Dashboard</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            color: #fff;
            padding: 20px;
        }
        .container { max-width: 1000px; margin: 0 auto; }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }
        .header h1 { font-size: 1.5rem; }
        .header img { height: 40px; }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        .btn-primary { background: #8b5cf6; color: white; }
        .btn-primary:hover { background: #7c3aed; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-secondary { background: rgba(255,255,255,0.2); color: white; }
        .card {
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }
        .card h2 { margin-bottom: 15px; font-size: 1.2rem; }
        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
            color: rgba(255,255,255,0.8);
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            background: rgba(255,255,255,0.1);
            color: white;
            font-size: 14px;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #8b5cf6;
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .events-list { display: grid; gap: 15px; }
        .event-item {
            display: flex;
            align-items: center;
            gap: 15px;
            background: rgba(255,255,255,0.05);
            padding: 15px;
            border-radius: 8px;
        }
        .event-item img {
            width: 80px;
            height: 45px;
            object-fit: cover;
            border-radius: 6px;
            background: rgba(255,255,255,0.1);
        }
        .event-info { flex: 1; }
        .event-info h3 { font-size: 14px; margin-bottom: 5px; }
        .event-info p { font-size: 12px; color: rgba(255,255,255,0.6); }
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .message.success { background: rgba(34, 197, 94, 0.2); border: 1px solid #22c55e; }
        .message.error { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; }
        .login-form {
            max-width: 400px;
            margin: 100px auto;
        }
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']): ?>
        <!-- Login Form -->
        <div class="login-form">
            <div class="card">
                <h2>🔐 Admin - Translators 101</h2>
                <?php if ($message): ?>
                <div class="message <?= $message_type ?>"><?= $message ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label>Senha</label>
                        <input type="password" name="password" required autofocus>
                    </div>
                    <button type="submit" name="login" class="btn btn-primary" style="width:100%">Entrar</button>
                </form>
            </div>
        </div>
        <?php else: ?>
        <!-- Admin Panel -->
        <div class="header">
            <div style="display:flex;align-items:center;gap:15px;">
                <img src="https://v.translators101.com/images/logo.png" alt="Logo">
                <h1>Painel de Eventos</h1>
            </div>
            <a href="?logout=1" class="btn btn-secondary">Sair</a>
        </div>

        <?php if ($message): ?>
        <div class="message <?= $message_type ?>"><?= $message ?></div>
        <?php endif; ?>

        <!-- Add Event Form -->
        <div class="card">
            <h2>➕ Adicionar Novo Evento</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Título (Português)</label>
                        <input type="text" name="title_pt" required placeholder="Ex: Congresso ABRATES 2026">
                    </div>
                    <div class="form-group">
                        <label>Título (Inglês)</label>
                        <input type="text" name="title_en" required placeholder="Ex: ABRATES Congress 2026">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Descrição (Português)</label>
                        <textarea name="description_pt" rows="2" placeholder="Descrição breve do evento"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Descrição (Inglês)</label>
                        <textarea name="description_en" rows="2" placeholder="Brief event description"></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Data</label>
                        <input type="date" name="date" required>
                    </div>
                    <div class="form-group">
                        <label>Horário</label>
                        <input type="time" name="time" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Local (Português)</label>
                        <input type="text" name="location_pt" required placeholder="Ex: São Paulo, Brasil">
                    </div>
                    <div class="form-group">
                        <label>Local (Inglês)</label>
                        <input type="text" name="location_en" required placeholder="Ex: São Paulo, Brazil">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="type" required>
                            <option value="online">Online</option>
                            <option value="inPerson">Presencial</option>
                            <option value="hybrid">Híbrido</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Preço</label>
                        <select name="price" required>
                            <option value="free">Gratuito</option>
                            <option value="paid">Pago</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Valor (se pago)</label>
                        <input type="text" name="price_value" placeholder="Ex: R$ 350 ou $695">
                    </div>
                    <div class="form-group">
                        <label>URL do Evento</label>
                        <input type="url" name="url" required placeholder="https://...">
                    </div>
                </div>

                <div class="form-group">
                    <label>URL da Imagem (opcional)</label>
                    <input type="url" name="image" placeholder="https://... (deixe vazio se não tiver)">
                </div>

                <button type="submit" name="add_event" class="btn btn-primary">Adicionar Evento</button>
            </form>
        </div>

        <!-- Events List -->
        <div class="card">
            <h2>📅 Eventos Cadastrados (<?= count($events) ?>)</h2>
            <?php if (empty($events)): ?>
            <p style="color:rgba(255,255,255,0.6)">Nenhum evento cadastrado ainda.</p>
            <?php else: ?>
            <div class="events-list">
                <?php foreach ($events as $event): ?>
                <div class="event-item">
                    <?php if (!empty($event['image'])): ?>
                    <img src="<?= htmlspecialchars($event['image']) ?>" alt="">
                    <?php else: ?>
                    <div style="width:80px;height:45px;background:rgba(139,92,246,0.3);border-radius:6px;display:flex;align-items:center;justify-content:center;">📅</div>
                    <?php endif; ?>
                    <div class="event-info">
                        <h3><?= htmlspecialchars($event['title']['pt-BR'] ?? '') ?></h3>
                        <p>
                            <?= $event['date'] ?? '' ?> às <?= $event['time'] ?? '' ?> • 
                            <?= htmlspecialchars($event['location']['pt-BR'] ?? '') ?>
                        </p>
                    </div>
                    <a href="?delete=<?= urlencode($event['id']) ?>" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja excluir este evento?')">Excluir</a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="card" style="text-align:center;color:rgba(255,255,255,0.5);font-size:12px;">
            <p>Translators 101 Dashboard Admin • Os eventos são exibidos automaticamente no dashboard</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
