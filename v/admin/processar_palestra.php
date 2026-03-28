<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

$message = ''; $message_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $webhook_url = 'https://ia-n8n.448usc.easypanel.host/webhook/transcricao-direta';
    $dados = [];

    if (isset($_FILES['transcricao']) && $_FILES['transcricao']['size'] > 0) {
        $dados['tipo'] = 'pos_palestra';
        $dados['texto'] = file_get_contents($_FILES['transcricao']['tmp_name']);
    } elseif (!empty($_POST['resumo_palestra'])) {
        $dados['tipo'] = 'pre_palestra';
        $dados['resumo'] = $_POST['resumo_palestra'];
        
        // Unifica múltiplos palestrantes e bios para o n8n
        $palestrantes = $_POST['nome_palestrante'];
        $bios = $_POST['minibio'];
        
        $dados['palestrante'] = implode(', ', array_filter($palestrantes));
        $dados['bio'] = '';
        foreach($palestrantes as $index => $nome) {
            if(!empty($nome)) {
                $dados['bio'] .= "Palestrante: $nome\nBio: " . ($bios[$index] ?? 'N/A') . "\n\n";
            }
        }
    }

    if (!empty($dados)) {
        $ch = curl_init($webhook_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status == 200) {
            $message = "Dados enviados com sucesso! Verifique seu e-mail em instantes.";
            $message_type = 'success';
        } else {
            $message = "Erro ao processar: " . $status;
            $message_type = 'error';
        }
    }
}

$page_title = 'Central de IA - Translators101';
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
    
    /* Upload Styling */
    .file-drop-area {
        position: relative;
        display: flex;
        align-items: center;
        flex-direction: column;
        padding: 40px;
        border: 2px dashed rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.02);
        transition: 0.3s;
        cursor: pointer;
    }
    .file-drop-area:hover { border-color: #34C759; background: rgba(52, 199, 89, 0.05); }
    .file-input {
        position: absolute;
        left: 0; top: 0; height: 100%; width: 100%;
        opacity: 0; cursor: pointer;
    }
    
    .btn-add {
        background: none; border: 1px dashed #AF52DE; color: #AF52DE;
        padding: 8px; border-radius: 6px; cursor: pointer; margin-bottom: 15px;
        font-size: 0.9em; transition: 0.3s;
    }
    .btn-add:hover { background: rgba(175, 82, 222, 0.1); }

    .speaker-entry {
        border-bottom: 1px solid rgba(255,255,255,0.1);
        margin-bottom: 15px;
        padding-bottom: 5px;
    }
    
    .btn-pre { background: #6366f1; border: none; }
    .btn-pos { background: #34C759; border: none; }
    .cta-btn:hover { opacity: 0.9; transform: translateY(-1px); }
</style>

<?php
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-robot"></i> Central de Inteligência T101</h1>
            <p>Escolha entre anunciar uma palestra futura ou processar os insights de uma realizada.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="message-card <?php echo $message_type; ?>-message" style="display:block; margin: 20px;">
            <p><i class="fas fa-info-circle"></i> <?php echo $message; ?></p>
        </div>
    <?php endif; ?>

    <div class="ia-grid">
        <div class="ia-card">
            <h2 style="margin-top:0;"><i class="fas fa-bullhorn"></i> Marketing: Anúncio</h2>
            <p style="color: rgba(255,255,255,0.6); margin-bottom:20px;">Crie convites magnéticos para as redes sociais.</p>
            
            <form method="POST">
                <div id="speakers-container">
                    <div class="speaker-entry">
                        <label class="ia-label">Palestrante:</label>
                        <input type="text" name="nome_palestrante[]" class="ia-input" placeholder="Ex: William Cassemiro" required>
                        
                        <label class="ia-label">Minibio:</label>
                        <textarea name="minibio[]" class="ia-input" style="height: 60px;" placeholder="Currículo resumido..."></textarea>
                    </div>
                </div>
                
                <button type="button" class="btn-add" onclick="addSpeaker()">
                    <i class="fas fa-plus"></i> Adicionar outro palestrante
                </button>

                <label class="ia-label">Resumo da Palestra:</label>
                <textarea name="resumo_palestra" class="ia-input" style="height: 120px; margin-top: 10px;" placeholder="Sobre o que será a palestra?" required></textarea>
                
                <button type="submit" class="cta-btn btn-pre">Gerar Anúncios de Marketing</button>
            </form>
        </div>

        <div class="ia-card" style="border-color: rgba(52, 199, 89, 0.3);">
            <h2 style="margin-top:0;"><i class="fas fa-file-video"></i> Conteúdo: Insights</h2>
            <p style="color: rgba(255,255,255,0.6); margin-bottom:20px;">Transforme a legenda (.srt) em cortes e ganchos.</p>
            
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <label class="ia-label">Arquivo de Legenda:</label>
                <div class="file-drop-area" id="dropArea">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 3em; color: rgba(255,255,255,0.2);"></i>
                    <p id="fileName" style="margin-top:15px;">Arraste o .srt ou clique para selecionar</p>
                    <input type="file" name="transcricao" id="fileInput" class="file-input" accept=".srt" required>
                </div>
                
                <p style="margin: 15px 0; font-size: 0.8em; color: rgba(255,255,255,0.4); text-align: center;">
                    Somente arquivos .srt gerados por transcrição.
                </p>
                
                <button type="submit" class="cta-btn btn-pos" style="margin-top: auto;">Extrair Insights Pós-Palestra</button>
            </form>
        </div>
    </div>
</div>

<script>
function addSpeaker() {
    const container = document.getElementById('speakers-container');
    const newEntry = document.createElement('div');
    newEntry.className = 'speaker-entry';
    newEntry.innerHTML = `
        <label class="ia-label">Palestrante:</label>
        <input type="text" name="nome_palestrante[]" class="ia-input" placeholder="Nome do palestrante">
        <label class="ia-label">Minibio:</label>
        <textarea name="minibio[]" class="ia-input" style="height: 60px;" placeholder="Minibio..."></textarea>
    `;
    container.appendChild(newEntry);
}

// Feedback visual do nome do arquivo
const fileInput = document.getElementById('fileInput');
const fileNameDisplay = document.getElementById('fileName');
const dropArea = document.getElementById('dropArea');

fileInput.addEventListener('change', function(e) {
    if (this.files && this.files.length > 0) {
        fileNameDisplay.textContent = "Arquivo selecionado: " + this.files[0].name;
        fileNameDisplay.style.color = "#34C759";
        dropArea.style.borderColor = "#34C759";
    }
});
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>