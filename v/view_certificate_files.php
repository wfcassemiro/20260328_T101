<?php
session_start();
require_once 'config/database.php';

// Função auxiliar para logs
function writeToCustomLog($message) {
    $log_file = __DIR__ . '/certificate_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] [VIEW] $message\n", FILE_APPEND);
}

$certificate_id = $_GET['id'] ?? '';
$action = $_GET['action'] ?? 'view'; // 'view' = interface, 'display' = mostrar imagem

if (empty($certificate_id)) {
    writeToCustomLog("ERRO: ID do certificado não fornecido.");
    header('Location: index.php');
    exit;
}

try {
    // Buscar dados do certificado (LEFT JOIN para aceitar convidados sem user_id)
    $stmt = $pdo->prepare("
        SELECT c.*, l.description as lecture_description
        FROM certificates c
        LEFT JOIN lectures l ON c.lecture_id = l.id
        WHERE c.id = ?
    ");
    $stmt->execute([$certificate_id]);
    $certificate_data = $stmt->fetch();

    if (!$certificate_data) {
        writeToCustomLog("INFO: Certificado ID " . $certificate_id . " não encontrado.");
        header('Location: index.php?error=not_found');
        exit;
    }

    /** * LÓGICA DE ACESSO PÚBLICO:
     * Removemos a verificação obrigatória de login e a trava de proprietário.
     * Como o UUID é complexo, ele serve como a chave de acesso ao certificado.
     */
    $is_logged_in = isset($_SESSION['user_id']);
    $is_admin = (isset($_SESSION['is_admin']) && $_SESSION['is_admin']);

    // Buscar arquivo PNG
    $png_path = __DIR__ . '/certificates/certificate_' . $certificate_id . '.png';
    if (!file_exists($png_path) || filesize($png_path) == 0) {
        $png_path = null;
    }

    // Ação display (usada pela tag <img> para renderizar o PNG)
    if ($action === 'display' && $png_path) {
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: image/png');
        header('Content-Disposition: inline; filename="Certificado.png"');
        header('Content-Length: ' . filesize($png_path));
        readfile($png_path);
        exit;
    }

} catch (Exception $e) {
    writeToCustomLog("ERRO FATAL: " . $e->getMessage());
    $certificate_data = null;
    $png_path = null;
}

// Interface Completa Vision UI
include __DIR__ . '/vision/includes/head.php';
include __DIR__ . '/vision/includes/header.php';
include __DIR__ . '/vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-certificate"></i> Visualizar Certificado</h1>
            <p>Certificado oficial emitido por Translators101</p>
        </div>
    </div>

    <?php if ($certificate_data && $png_path): ?>
    <div class="certificate-viewer">
        <div class="certificate-info">
            <div class="info-grid">
                <div class="info-item">
                    <i class="fas fa-user"></i>
                    <div>
                        <strong>Participante</strong>
                        <span><?php echo htmlspecialchars($certificate_data['user_name']); ?></span>
                    </div>
                </div>
                
                <div class="info-item">
                    <i class="fas fa-video"></i>
                    <div>
                        <strong>Palestra</strong>
                        <span><?php echo htmlspecialchars($certificate_data['lecture_title']); ?></span>
                    </div>
                </div>
                
                <div class="info-item">
                    <i class="fas fa-microphone"></i>
                    <div>
                        <strong>Palestrante</strong>
                        <span><?php echo htmlspecialchars($certificate_data['speaker_name']); ?></span>
                    </div>
                </div>
                
                <div class="info-item">
                    <i class="fas fa-clock"></i>
                    <div>
                        <strong>Duração</strong>
                        <span><?php echo $certificate_data['duration_hours']; ?> horas</span>
                    </div>
                </div>
                
                <div class="info-item">
                    <i class="fas fa-calendar"></i>
                    <div>
                        <strong>Emitido em</strong>
                        <span><?php echo date('d/m/Y H:i', strtotime($certificate_data['issued_at'])); ?></span>
                    </div>
                </div>
                
                <div class="info-item">
                    <i class="fas fa-shield-check"></i>
                    <div>
                        <strong>ID do Certificado</strong>
                        <span style="font-size: 0.8rem;"><?php echo htmlspecialchars($certificate_id); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="certificate-preview">
            <div class="preview-container">
                <img src="view_certificate_files.php?id=<?php echo urlencode($certificate_id); ?>&action=display" 
                     alt="Certificado de <?php echo htmlspecialchars($certificate_data['user_name']); ?>"
                     class="certificate-image"
                     onclick="openCertificateModal()">
                <div class="preview-overlay">
                    <div class="zoom-icon">
                        <i class="fas fa-search-plus"></i>
                        <span>Clique para ampliar</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="certificate-actions">
            <div class="actions-grid">
                <a href="download_certificate_files.php?id=<?php echo urlencode($certificate_id); ?>&format=png" 
                   class="action-btn download-btn">
                    <i class="fas fa-download"></i>
                    <div>
                        <strong>Baixar PNG</strong>
                        <span>Alta qualidade</span>
                    </div>
                </a>
                
                <a href="download_certificate_files.php?id=<?php echo urlencode($certificate_id); ?>&format=pdf" 
                   class="action-btn pdf-btn">
                    <i class="fas fa-file-pdf"></i>
                    <div>
                        <strong>Baixar PDF</strong>
                        <span>Documento portátil</span>
                    </div>
                </a>
                
                <button onclick="shareCertificate()" class="action-btn share-btn">
                    <i class="fas fa-share-alt"></i>
                    <div>
                        <strong>Compartilhar</strong>
                        <span>Link de verificação</span>
                    </div>
                </button>
                
                <button onclick="printCertificate()" class="action-btn print-btn">
                    <i class="fas fa-print"></i>
                    <div>
                        <strong>Imprimir</strong>
                        <span>Versão física</span>
                    </div>
                </button>
            </div>
        </div>

        <div class="certificate-navigation">
            <?php if ($is_logged_in): ?>
                <a href="perfil.php" class="nav-btn primary">
                    <i class="fas fa-user"></i> Voltar ao Perfil
                </a>
            <?php else: ?>
                <a href="index.php" class="nav-btn primary">
                    <i class="fas fa-home"></i> Ir para Home
                </a>
            <?php endif; ?>
            
            <a href="videoteca.php" class="nav-btn secondary">
                <i class="fas fa-video"></i> Ver mais palestras
            </a>
        </div>
    </div>

    <?php else: ?>
    <div class="error-state">
        <div class="error-content">
            <i class="fas fa-exclamation-triangle" style="font-size: 4rem; color: #e74c3c; margin-bottom: 20px;"></i>
            <h2>Certificado não disponível</h2>
            <p>O arquivo físico deste certificado ainda não foi gerado ou foi removido.</p>
            <div class="error-actions mt-4">
                <a href="index.php" class="cta-btn">Voltar ao Início</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div id="certificateModal" class="certificate-modal" onclick="closeCertificateModal()">
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        <img id="modalImage" src="" alt="Certificado Ampliado">
    </div>
</div>

<style>
/* VISION UI - ESTILOS COMPLETOS */
.certificate-viewer { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
.certificate-info, .certificate-preview, .certificate-actions {
    background: rgba(25, 25, 25, 0.7); backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 30px; margin-bottom: 30px;
}
.info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
.info-item { display: flex; align-items: center; gap: 15px; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 12px; }
.info-item i { font-size: 1.5rem; color: #c084fc; min-width: 30px; }
.info-item strong { display: block; color: #fff; font-size: 0.8rem; text-transform: uppercase; opacity: 0.7; }
.info-item span { color: #eee; font-weight: 500; }

.preview-container { position: relative; display: inline-block; max-width: 100%; cursor: pointer; }
.certificate-image { max-width: 100%; border-radius: 12px; transition: 0.3s; }
.preview-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; opacity: 0; transition: 0.3s; border-radius: 12px; }
.preview-container:hover .preview-overlay { opacity: 1; }
.zoom-icon { color: #fff; text-align: center; }

.actions-grid { display: flex; flex-wrap: wrap; gap: 16px; justify-content: center; }
.action-btn { flex: 1 1 200px; min-width: 200px; max-width: 250px; display: flex; align-items: center; gap: 12px; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 12px; color: #fff; text-decoration: none; transition: 0.3s; border: 1px solid rgba(255,255,255,0.1); }
.action-btn:hover { background: rgba(255,255,255,0.1); transform: translateY(-2px); }
.download-btn i { color: #2ecc71; } .pdf-btn i { color: #e74c3c; } .share-btn i { color: #3498db; } .print-btn i { color: #f39c12; }

.certificate-navigation { display: flex; gap: 20px; justify-content: center; flex-wrap: wrap; }
.nav-btn { padding: 12px 24px; border-radius: 10px; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 8px; transition: 0.3s; }
.nav-btn.primary { background: #c084fc; color: #fff; }
.nav-btn.secondary { background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.1); }

.certificate-modal { display: none; position: fixed; z-index: 9999; inset: 0; background: rgba(0,0,0,0.9); backdrop-filter: blur(10px); }
.modal-content { position: relative; margin: auto; top: 50%; transform: translateY(-50%); text-align: center; width: 90%; max-width: 1100px; }
.modal-close { position: absolute; top: -40px; right: 0; color: #fff; font-size: 40px; cursor: pointer; }
#modalImage { max-width: 100%; border-radius: 8px; box-shadow: 0 0 50px rgba(0,0,0,0.5); }

@media (max-width: 768px) { .nav-btn { width: 100%; justify-content: center; } }
</style>

<script>
function openCertificateModal() {
    const modal = document.getElementById('certificateModal');
    const modalImg = document.getElementById('modalImage');
    const sourceImg = document.querySelector('.certificate-image');
    modal.style.display = "block";
    modalImg.src = sourceImg.src;
    document.body.style.overflow = "hidden";
}

function closeCertificateModal() {
    document.getElementById('certificateModal').style.display = "none";
    document.body.style.overflow = "auto";
}

function shareCertificate() {
    const url = window.location.href;
    if (navigator.share) {
        navigator.share({ title: 'Meu Certificado Translators101', url: url });
    } else {
        navigator.clipboard.writeText(url).then(() => alert('Link copiado!'));
    }
}

function printCertificate() {
    const printWin = window.open('view_certificate_files.php?id=<?php echo $certificate_id; ?>&action=display', '_blank');
    printWin.onload = function() { printWin.print(); };
}

document.addEventListener('keydown', (e) => { if(e.key === "Escape") closeCertificateModal(); });
</script>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>