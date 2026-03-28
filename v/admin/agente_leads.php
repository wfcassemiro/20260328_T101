<?php
session_start();
require_once __DIR__ . '/../config/database.php';
date_default_timezone_set('America/Sao_Paulo');

// Segurança
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

$message = ''; $message_type = '';
// ATENÇÃO: Confirme se esta URL é a de PRODUCTION que pegamos no n8n
$webhook_url = 'https://ia-n8n.448usc.easypanel.host/webhook-test/processar-leads'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = [];

    // --- CENÁRIO 1: Entrada Manual (Único Lead) ---
    if (isset($_POST['single_nome']) && !empty($_POST['single_nome'])) {
        $payload[] = [
            'nome' => $_POST['single_nome'],
            'perfil' => $_POST['single_perfil']
        ];
    } 
    // --- CENÁRIO 2: Upload de CSV (Lote) ---
    elseif (isset($_FILES['csv_file']) && $_FILES['csv_file']['size'] > 0) {
        $filename = $_FILES['csv_file']['tmp_name'];
        if (($handle = fopen($filename, "r")) !== FALSE) {
            // Detecção automática de separador (; ou ,)
            $firstLine = fgets($handle);
            rewind($handle);
            $separator = (strpos($firstLine, ';') !== false) ? ';' : ',';

            // Pula cabeçalho? (Opcional: assume que a primeira linha é cabeçalho)
            fgetcsv($handle, 1000, $separator);

            while (($data = fgetcsv($handle, 1000, $separator)) !== FALSE) {
                if (isset($data[0]) && !empty($data[0])) {
                    $nome = mb_convert_encoding($data[0], "UTF-8", "auto");
                    $perfil = isset($data[1]) ? mb_convert_encoding($data[1], "UTF-8", "auto") : "Perfil não informado";
                    
                    $payload[] = [
                        'nome' => trim($nome),
                        'perfil' => trim($perfil)
                    ];
                }
            }
            fclose($handle);
        }
    }

    // --- ENVIO PARA O N8N ---
    if (!empty($payload)) {
        // Estrutura o JSON para o n8n: { "leads": [ ... ] }
        $jsonData = json_encode(['leads' => $payload]);

        $ch = curl_init($webhook_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData)
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200) {
            $qtd = count($payload);
            $s = $qtd > 1 ? 's' : '';
            $message = "Sucesso! $qtd lead$s enviado$s para análise. Verifique seu e-mail.";
            $message_type = 'success';
        } else {
            $message = "Erro de conexão com a IA (Cód: $httpCode). Verifique se o workflow está ativo.";
            $message_type = 'error';
        }
    } else {
        $message = "Nenhum dado válido foi encontrado para envio.";
        $message_type = 'error';
    }
}

$page_title = 'Prospector de Leads - T101';
include __DIR__ . '/../vision/includes/head.php';
?>

<style>
    .ia-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin: 20px; }
    @media (max-width: 900px) { .ia-grid { grid-template-columns: 1fr; } }
    
    .ia-card { 
        background: rgba(255, 255, 255, .05); 
        padding: 25px; 
        border-radius: 12px; 
        border: 1px solid rgba(255, 255, 255, .1);
        display: flex;
        flex-direction: column;
    }
    
    .ia-input {
        width: 100%;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: white;
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 15px;
        font-family: inherit;
        transition: border 0.3s;
    }
    
    .ia-input:focus { border-color: #AF52DE; outline: none; }
    .ia-label { display: block; margin-bottom: 8px; font-weight: bold; color: #AF52DE; }
    
    /* Upload Area */
    .file-drop-area {
        border: 2px dashed rgba(255, 255, 255, 0.2);
        padding: 40px; text-align: center; border-radius: 12px;
        cursor: pointer; position: relative; transition: 0.3s;
        margin-bottom: 20px; flex-grow: 1; display: flex;
        flex-direction: column; justify-content: center; align-items: center;
    }
    .file-drop-area:hover { border-color: #AF52DE; background: rgba(175, 82, 222, 0.05); }
    .file-input { position: absolute; left: 0; top: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
    
    .cta-btn { width: 100%; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.2s; margin-top: auto;}
    .btn-manual { background: #6366f1; }
    .btn-manual:hover { background: #4f46e5; }
    .btn-csv { background: #AF52DE; }
    .btn-csv:hover { background: #9c40c9; }
</style>

<?php include __DIR__ . '/../vision/includes/header.php'; include __DIR__ . '/../vision/includes/sidebar.php'; ?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-users-viewfinder"></i> Prospector de Leads</h1>
            <p>Gere abordagens personalizadas (B2C) para atrair alunos e tradutores.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="message-card <?php echo $message_type; ?>-message" style="display:block; margin: 20px;">
            <p><i class="fas fa-info-circle"></i> <?php echo $message; ?></p>
        </div>
    <?php endif; ?>

    <div class="ia-grid">
        
        <div class="ia-card">
            <h2 style="margin-top:0;"><i class="fas fa-user"></i> Lead Único</h2>
            <p style="color: rgba(255,255,255,0.6); margin-bottom:20px;">Copie e cole os dados de um perfil específico.</p>
            
            <form method="POST">
                <label class="ia-label">Nome do Lead:</label>
                <input type="text" name="single_nome" class="ia-input" placeholder="Ex: João da Silva" required>
                
                <label class="ia-label">Texto do Perfil (Sobre/Experiência):</label>
                <textarea name="single_perfil" class="ia-input" style="height: 150px;" placeholder="Cole aqui o texto do LinkedIn..." required></textarea>
                
                <button type="submit" class="cta-btn btn-manual">Processar Lead Agora</button>
            </form>
        </div>

        <div class="ia-card">
            <h2 style="margin-top:0;"><i class="fas fa-table"></i> Lote (CSV)</h2>
            <p style="color: rgba(255,255,255,0.6); margin-bottom:20px;">Processe dezenas de leads de uma vez.</p>
            
            <form method="POST" enctype="multipart/form-data" style="height: 100%; display: flex; flex-direction: column;">
                <div class="file-drop-area" id="dropArea">
                    <i class="fas fa-file-csv" style="font-size: 3em; color: rgba(255,255,255,0.2);"></i>
                    <p id="fileName" style="margin-top:15px;">Arraste o CSV ou clique aqui</p>
                    <input type="file" name="csv_file" id="fileInput" class="file-input" accept=".csv">
                </div>
                
                <p style="font-size: 0.8em; color: rgba(255,255,255,0.4); text-align: center; margin-bottom: 15px;">
                    Colunas obrigatórias: <strong>Nome, Perfil</strong>
                </p>
                
                <button type="submit" class="cta-btn btn-csv">Processar Lista CSV</button>
            </form>
        </div>

    </div>
</div>

<script>
// Feedback visual para o arquivo CSV
const fileInput = document.getElementById('fileInput');
const fileName = document.getElementById('fileName');
const dropArea = document.getElementById('dropArea');

fileInput.addEventListener('change', function() {
    if (this.files && this.files.length > 0) {
        fileName.textContent = this.files[0].name;
        fileName.style.color = "#AF52DE";
        dropArea.style.borderColor = "#AF52DE";
    }
});
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>