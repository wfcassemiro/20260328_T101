<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
// Caminhos para database
$paths = [
    __DIR__ . '/../config/database.php',
    __DIR__ . '/../../config/database.php',
    $_SERVER['DOCUMENT_ROOT'] . '/config/database.php'
];

$pdo = null;
foreach ($paths as $path) {
    if (file_exists($path)) { require_once $path; break; }
}

if (!$pdo) die("Erro crítico: Banco de dados não encontrado.");

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $live_embed = $_POST['live_embed_code'] ?? '';
    $chat_embed = $_POST['chat_embed_code'] ?? ''; // Mantido para retrocompatibilidade
    $panda_url  = $_POST['panda_chat_url'] ?? '';  // NOVO CAMPO
    
    // Lógica robusta para o Checkbox
    $live_status = isset($_POST['live_status']) ? '1' : '0';

    try {
        $sql = "INSERT INTO site_settings (setting_key, setting_value, updated_at) 
                VALUES (:key, :v1, NOW()) 
                ON DUPLICATE KEY UPDATE setting_value = :v2, updated_at = NOW()";
        
        $stmt = $pdo->prepare($sql);
        
        // Salva Vídeo
        $stmt->execute(['key' => 'live_embed_code', 'v1' => $live_embed, 'v2' => $live_embed]);
        
        // Salva Chat Genérico (Opcional)
        $stmt->execute(['key' => 'chat_embed_code', 'v1' => $chat_embed, 'v2' => $chat_embed]);

        // Salva URL do Panda (NOVO)
        $stmt->execute(['key' => 'panda_chat_url', 'v1' => $panda_url, 'v2' => $panda_url]);
        
        // Salva Status
        $stmt->execute(['key' => 'live_status', 'v1' => $live_status, 'v2' => $live_status]);

        $message = '<div class="alert success"><i class="fas fa-check"></i> Configurações atualizadas!</div>';
    } catch (PDOException $e) {
        $message = '<div class="alert error"><i class="fas fa-exclamation-triangle"></i> Erro: ' . $e->getMessage() . '</div>';
    }
}

// Buscar dados atuais
$current_video = '';
$current_chat = '';
$current_panda = '';
$current_status = '0';

try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('live_embed_code', 'chat_embed_code', 'live_status', 'panda_chat_url')");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $current_video = $settings['live_embed_code'] ?? '';
    $current_chat = $settings['chat_embed_code'] ?? '';
    $current_panda = $settings['panda_chat_url'] ?? '';
    $current_status = $settings['live_status'] ?? '0';
} catch (Exception $e) {}

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero" style="margin-bottom: 30px;">
        <div style="display: flex; align-items: center;">
            <div class="header-icon-container" style="background: rgba(255, 255, 255, 0.1); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i class="fas fa-cogs" style="font-size: 24px; color: #fff;"></i>
            </div>
            <div class="header-text-container" style="margin-left: 20px; align-items: center;">
                <h2 style="margin: 0 0 5px 0; padding: 0; font-size: 1.5rem; color: #fff; font-weight: 600; border: none;">Configuração da Transmissão</h2>
                <p style="margin: 0; color: rgba(255, 255, 255, 0.8); font-size: 1rem;">Gerencie os embeds e o status</p>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="video-card">
            <?php echo $message; ?>
            <form method="post">
                <div class="form-group" style="background: rgba(142, 68, 173, 0.1); padding: 20px; border-radius: 8px; border: 1px solid rgba(142, 68, 173, 0.3); margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <label style="color: #fff; font-weight: bold; font-size: 1.1rem; display: block; margin-bottom: 5px;">Status da Transmissão</label>
                        <span style="color: #ccc; font-size: 0.9rem;">Liga/Desliga o badge "Ao Vivo".</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="live_status" value="1" <?php echo ($current_status == '1') ? 'checked' : ''; ?>>
                        <span class="slider round"></span>
                    </label>
                </div>

                <div class="form-group" style="margin-bottom: 25px;">
                    <label style="display:block; color:var(--brand-purple, #8e44ad); font-weight:bold; margin-bottom:8px;">Embed do Vídeo (Panda/YouTube)</label>
                    <textarea name="live_embed_code" style="width: 100%; height: 100px; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 15px; border-radius: 8px; font-family: monospace;"><?php echo htmlspecialchars($current_video); ?></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 25px; background: rgba(0,0,0,0.3); padding: 15px; border-radius: 8px; border-left: 3px solid #f39c12;">
                    <label style="display:block; color: #f39c12; font-weight:bold; margin-bottom:8px;">
                        <i class="fas fa-comments"></i> Link do Chat Convidados (Panda Video)
                    </label>
                    <small style="display:block; color:#aaa; margin-bottom:10px;">
                        Copie apenas o URL do `src` do iframe do Panda. Ex: <em>https://player-vz.../embed/live_chat.html?c=...</em>
                    </small>
                    <input type="text" name="panda_chat_url" value="<?php echo htmlspecialchars($current_panda); ?>" 
                           style="width: 100%; background: #222; border: 1px solid #444; color: #fff; padding: 10px; border-radius: 4px;">
                </div>

                <div class="form-group" style="margin-bottom: 30px; opacity: 0.7;">
                    <label style="display:block; color: #aaa; font-weight:bold; margin-bottom:8px;">Embed de Chat Genérico (Opcional/Legacy)</label>
                    <small style="color:#777;">Use apenas se quiser substituir completamente o chat nativo do sistema por um iframe externo.</small>
                    <textarea name="chat_embed_code" style="width: 100%; height: 80px; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); color: #888; padding: 15px; border-radius: 8px; font-family: monospace;"><?php echo htmlspecialchars($current_chat); ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="cta-btn" style="border:none; cursor:pointer;"><i class="fas fa-save"></i> Salvar Configurações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: 500; }
    .success { background: rgba(46, 204, 113, 0.2); border: 1px solid #2ecc71; color: #2ecc71; }
    .error { background: rgba(231, 76, 60, 0.2); border: 1px solid #e74c3c; color: #e74c3c; }
    .cta-btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; font-size: 1rem; font-weight: bold; border-radius: 30px; background: #8e44ad; color: #fff; text-decoration: none; transition: transform 0.2s; }
    .cta-btn:hover { background: #5e3370; transform: translateY(-2px); }
    .switch { position: relative; display: inline-block; width: 60px; height: 34px; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; }
    .slider:before { position: absolute; content: ""; height: 26px; width: 26px; left: 4px; bottom: 4px; background-color: white; transition: .4s; }
    input:checked + .slider { background-color: #2ecc71; }
    input:checked + .slider:before { transform: translateX(26px); }
    .slider.round { border-radius: 34px; }
    .slider.round:before { border-radius: 50%; }
</style>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>