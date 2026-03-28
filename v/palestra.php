<?php
session_start();
require_once 'config/database.php';

if (!hasVideotecaAccess()) {
    header('Location: /login.php');
    exit;
}

$page_title = 'Palestra - Translators101';
$lecture_id = $_GET['id'] ?? null;
$user_id = $_SESSION['user_id'];

function writeToCustomLog($message) {
    $log_file = __DIR__ . '/certificate_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] [PALESTRA] $message\n", FILE_APPEND);
}

if (!$lecture_id) {
    header('Location: /videoteca.php');
    exit;
}

// Buscar detalhes da palestra
try {
    $stmt = $pdo->prepare("SELECT * FROM lectures WHERE id = ?");
    $stmt->execute([$lecture_id]);
    $lecture = $stmt->fetch();

    if (!$lecture) {
        header('Location: /videoteca.php');
        exit;
    }
} catch (Exception $e) {
    header('Location: /videoteca.php');
    exit;
}

// Registrar acesso e obter progresso inicial
$user_progress_seconds = 0;
try {
    $stmt = $pdo->prepare("SELECT last_watched_seconds FROM access_logs WHERE user_id = ? AND resource = ? AND action = 'view_lecture'");
    $stmt->execute([$user_id, $lecture['title']]);
    $existing_log = $stmt->fetch();

    if ($existing_log) {
        $user_progress_seconds = (float)($existing_log['last_watched_seconds'] ?? 0);
    } else {
        $stmt = $pdo->prepare("INSERT INTO access_logs (user_id, action, resource, ip_address, user_agent, last_watched_seconds) VALUES (?, 'view_lecture', ?, ?, ?, 0)");
        $stmt->execute([
            $user_id,
            $lecture['title'],
            $_SERVER['REMOTE_ADDR'] ?? 'N/A',
            $_SERVER['HTTP_USER_AGENT'] ?? 'N/A'
        ]);
    }
} catch (Exception $e) {
    writeToCustomLog("ERRO: " . $e->getMessage());
}

// Buscar palestras relacionadas
$related_lectures = [];
try {
    if (!empty($lecture['category'])) {
        $stmt = $pdo->prepare("SELECT * FROM lectures WHERE category = ? AND id != ? LIMIT 3");
        $stmt->execute([$lecture['category'], $lecture_id]);
        $related_lectures = $stmt->fetchAll();
    }
} catch (Exception $e) { }

// Verificar se já existe certificado
$existing_certificate_id = null;
try {
    $stmt = $pdo->prepare("SELECT id FROM certificates WHERE user_id = ? AND lecture_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user_id, $lecture_id]);
    $existing_certificate = $stmt->fetch();
    if ($existing_certificate) {
        $existing_certificate_id = $existing_certificate['id'];
    }
} catch (Exception $e) { }

include __DIR__ . '/vision/includes/head.php';
include __DIR__ . '/vision/includes/header.php';
include __DIR__ . '/vision/includes/sidebar.php';
?>

<main class="main-content">

    <!-- Player da Palestra -->
    <div class="video-card">
        <h1 style="margin-bottom: 20px;"><?php echo htmlspecialchars($lecture['title']); ?></h1>
        <div class="video-container" id="videoWrapper">
            <?php echo $lecture['embed_code']; ?>
        </div>
    </div>

    <!-- Certificado de Conclusão -->
    <div class="video-card">
        <h2><i class="fas fa-certificate"></i> Certificado de Conclusão</h2>
        <div id="certificateStatus" class="certificate-status">
            <?php if ($existing_certificate_id): ?>
                <div class="alert-success">
                    <i class="fas fa-check-circle"></i> Você já tem certificado para esta palestra!
                    <div style="margin-top: 15px;">
                        <a href="/view_certificate_files.php?id=<?php echo htmlspecialchars($existing_certificate_id); ?>" class="cta-btn">
                            <i class="fas fa-eye"></i> Ver certificado
                        </a>
                        <a href="/download_certificate_files.php?id=<?php echo htmlspecialchars($existing_certificate_id); ?>" class="cta-btn" style="margin-left: 10px;">
                            <i class="fas fa-download"></i> Baixar PDF
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div id="certificateMessage" class="alert-warning">
                    <i class="fas fa-hourglass-half"></i> Assista à palestra para habilitar a geração do certificado.
                </div>
                <button id="generateCertificateBtn" class="cta-btn" disabled style="margin-top: 15px;">
                    <i class="fas fa-lock"></i> Gerar certificado
                </button>
                <p class="certificate-requirement">
                    <i class="fas fa-info-circle"></i> Requer 85% de visualização (antifraude ativo)
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Progresso de Visualização -->
    <div class="video-card">
        <h2><i class="fas fa-chart-line"></i> Progresso de Visualização</h2>
        <div id="progressText" class="progress-text">Conectando ao player...</div>
        <div class="progress-container">
            <div id="progressBar" class="progress-bar"></div>
        </div>
        <div class="anti-fraud-notice">
            <i class="fas fa-shield-alt"></i> Status: <span id="autoStatus">🔄 Inicializando...</span>
        </div>
        <div class="progress-status">
            <div class="status-item">
                <span>Tempo assistido:</span>
                <span id="simpleTimerDisplay">0:00</span>
            </div>
            <div class="status-item">
                <span>Fluxo:</span>
                <span id="naturalProgress" class="status-ok">Aguardando Play</span>
            </div>
        </div>
    </div>

</main>

<style>
.video-container { position: relative; width: 100%; background: #000; border-radius: 15px; overflow: hidden; margin: 20px 0; }
.certificate-status { padding: 20px; border-radius: 10px; margin: 20px 0; }
.progress-text { font-size: 1.1em; color: #fff; margin: 15px 0; }
.progress-container { width: 100%; height: 12px; background: rgba(255,255,255,0.1); border-radius: 6px; overflow: hidden; margin: 20px 0; }
.progress-bar { height: 100%; background: linear-gradient(90deg, #8e44ad, #bd72e8); width: 0%; transition: width 0.3s linear; }
.anti-fraud-notice { background: rgba(241,196,15,0.1); color: #f1c40f; padding: 12px; border-radius: 8px; margin: 15px 0; border: 1px solid rgba(241,196,15,0.3); }
.progress-status { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 20px 0; }
.status-item { display: flex; justify-content: space-between; padding: 12px; background: rgba(255,255,255,0.05); border-radius: 8px; }
.status-ok { color: #2ecc71; }
.status-bad { color: #e74c3c; }
#generateCertificateBtn:disabled { opacity: 0.5; cursor: not-allowed; background: #666 !important; }
#generateCertificateBtn.btn-ready { background: #27ae60 !important; color: #fff !important; cursor: pointer; animation: pulse 2s infinite; }
@keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.02); } 100% { transform: scale(1); } }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const LECTURE_ID = <?php echo json_encode($lecture_id); ?>;
    const USER_ID = <?php echo json_encode($user_id); ?>;
    const DURATION = <?php echo (int)(($lecture['duration_minutes'] ?? 0) * 60); ?>;
    const REQUIRED = Math.floor(DURATION * 0.85);
    
    let totalWatched = <?php echo (float)$user_progress_seconds; ?>;
    let lastSavedAt = Date.now();
    let isPlaying = false;
    let lastPlayerTime = null;

    const progressText = document.getElementById('progressText');
    const progressBar = document.getElementById('progressBar');
    const generateBtn = document.getElementById('generateCertificateBtn');
    const autoStatus = document.getElementById('autoStatus');
    const naturalProgressEl = document.getElementById('naturalProgress');
    const timerDisplay = document.getElementById('simpleTimerDisplay');

    function formatTime(s) {
        s = Math.floor(s);
        return Math.floor(s/60) + ":" + String(s%60).padStart(2, '0');
    }

    function updateUI() {
        const pct = Math.min((totalWatched / DURATION) * 100, 100);
        const certPct = Math.min((totalWatched / REQUIRED) * 100, 100);
        
        if (progressText) progressText.textContent = `${pct.toFixed(1)}% assistido (${formatTime(totalWatched)} / ${formatTime(DURATION)})`;
        if (progressBar) progressBar.style.width = certPct + "%";
        if (timerDisplay) timerDisplay.textContent = formatTime(totalWatched);

        if (totalWatched >= REQUIRED && generateBtn && generateBtn.disabled) {
            generateBtn.disabled = false;
            generateBtn.classList.add('btn-ready');
            generateBtn.innerHTML = '<i class="fas fa-certificate"></i> Gerar Certificado';
            document.getElementById('certificateMessage').className = 'alert-success';
            document.getElementById('certificateMessage').innerHTML = '✅ Requisito atingido!';
        }
    }

    async function openCertificate() {
        generateBtn.disabled = true;
        generateBtn.innerHTML = 'Gerando...';
        try {
            const res = await fetch('/generate_certificate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ lecture_id: LECTURE_ID, user_id: USER_ID })
            });
            const data = await res.json();
            if (data.success) {
                window.open(`/view_certificate_files.php?id=${data.certificate_id}`, '_blank');
                location.reload();
            } else { alert(data.message); generateBtn.disabled = false; }
        } catch (e) { alert("Erro na geração."); generateBtn.disabled = false; }
    }
    if (generateBtn) generateBtn.onclick = openCertificate;

    function save() {
        fetch('/config/api/save_simple_progress.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ lecture_id: LECTURE_ID, user_id: USER_ID, watched_seconds: Math.floor(totalWatched) })
        });
    }

    let retry = 0;
    function connect() {
        const iframe = document.querySelector('iframe[id^="panda-"]') || document.querySelector('#videoWrapper iframe');
        if (!iframe) {
            if (retry++ < 30) setTimeout(connect, 500);
            return;
        }

        const targetId = iframe.id.replace(/^panda-/, '');
        window.pandascripttag = window.pandascripttag || [];
        window.pandascripttag.push(function () {
            const player = new PandaPlayer(targetId, {
                onReady: () => {
                    autoStatus.textContent = "✅ Player Pronto";
                    player.onEvent(e => {
                        if (e.message === 'panda_play') { isPlaying = true; autoStatus.textContent = "▶️ Reproduzindo"; }
                        if (e.message === 'panda_pause') { isPlaying = false; autoStatus.textContent = "⏸️ Pausado"; }
                        
                        if (e.message === 'panda_timeupdate') {
                            const current = Number(e.currentTime);
                            
                            if (lastPlayerTime !== null && isPlaying) {
                                const diff = current - lastPlayerTime;
                                
                                // Se o tempo passou entre 0 e 2 segundos (fluxo normal)
                                if (diff > 0 && diff < 2) {
                                    totalWatched += diff;
                                    naturalProgressEl.textContent = "OK";
                                    naturalProgressEl.className = "status-ok";
                                    updateUI();
                                } else if (diff >= 2) {
                                    naturalProgressEl.textContent = "Salto Bloqueado";
                                    naturalProgressEl.className = "status-bad";
                                }
                            }
                            lastPlayerTime = current;

                            if (Date.now() - lastSavedAt > 10000) {
                                save();
                                lastSavedAt = Date.now();
                            }
                        }
                    });
                }
            });
        });
    }

    const s = document.createElement('script');
    s.src = 'https://player.pandavideo.com.br/api.v2.js';
    s.async = true;
    s.onload = connect;
    document.head.appendChild(s);

    updateUI();
});
</script>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>