<?php
session_start();
require_once __DIR__ . '/config/database.php';

// REMOVIDO: Redirecionamento obrigatório para login.php
// Mas mantemos a lógica para saber se o usuário está logado para a sidebar/header
$is_logged_in = isset($_SESSION['user_id']);
$session_user_id = $_SESSION['user_id'] ?? null;

$page_title = 'Download de Certificado - Translators101';
$certificate_id = $_GET['id'] ?? null;
$lecture_id = $_GET['lecture_id'] ?? null;

$error = '';
$certificate = null;

try {
    if ($certificate_id) {
        // Busca baseada no UUID (Acesso Público)
        $stmt = $pdo->prepare("
            SELECT c.*, l.title as lecture_title, l.speaker, u.name as user_name_db
            FROM certificates c
            LEFT JOIN lectures l ON c.lecture_id = l.id
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$certificate_id]);
        $certificate = $stmt->fetch();
    } elseif ($lecture_id && $is_logged_in) {
        // Busca baseada no ID da palestra (Exige login para saber de QUEM é o certificado)
        $stmt = $pdo->prepare("
            SELECT c.*, l.title as lecture_title, l.speaker, u.name as user_name_db
            FROM certificates c
            LEFT JOIN lectures l ON c.lecture_id = l.id
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.lecture_id = ? AND c.user_id = ?
        ");
        $stmt->execute([$lecture_id, $session_user_id]);
        $certificate = $stmt->fetch();
    }

    if (!$certificate) {
        $error = 'Certificado não encontrado ou acesso restrito.';
    }

} catch (PDOException $e) {
    $error = 'Erro ao buscar certificado: ' . $e->getMessage();
}

// Processamento do download em PDF via FPDF (Mantido integralmente)
if ($certificate && isset($_GET['download'])) {
    require_once __DIR__ . '/fpdf/fpdf.php';
    $filename = 'certificado_' . $certificate['id'] . '.pdf';
    // Link para o motor de arquivos que acabamos de configurar
    $image_url = "https://v.translators101.com/download_certificate_files.php?id=" . urlencode($certificate['id']);
    
    $image_content = file_get_contents($image_url);
    if ($image_content === false) die('Erro ao baixar imagem do certificado.');

    $tmp_image = tempnam(sys_get_temp_dir(), 'cert_') . '.png';
    file_put_contents($tmp_image, $image_content);

    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->Image($tmp_image, 0, 0, $pdf->GetPageWidth(), $pdf->GetPageHeight());
    unlink($tmp_image);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $pdf->Output('I', $filename);
    exit;
}

include __DIR__ . '/vision/includes/head.php';
include __DIR__ . '/vision/includes/header.php';
include __DIR__ . '/vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-certificate"></i> Download de Certificado</h1>
            <p>Baixe seu comprovante oficial de conclusão</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="video-card">
            <div class="alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
            <div style="text-align: center; margin-top: 30px;"><a href="index.php" class="cta-btn">Página Inicial</a></div>
        </div>
    <?php else: ?>
        <div class="video-card">
            <h2><i class="fas fa-award"></i> Certificado de Conclusão</h2>
            <div class="certificate-preview">
                <div class="certificate-header">
                    <div class="certificate-logo"><i class="fas fa-certificate"></i></div>
                    <h3>Certificado de Conclusão</h3>
                    <p>Translators101 - Educação Continuada</p>
                </div>

                <div class="certificate-body">
                    <p class="certificate-text">
                        Certificamos que <strong><?php echo htmlspecialchars($certificate['user_name'] ?: $certificate['user_name_db']); ?></strong> concluiu com êxito a palestra:
                    </p>
                    <h4 class="lecture-title"><?php echo htmlspecialchars($certificate['lecture_title']); ?></h4>
                    <p class="speaker-info">Ministrada por: <strong><?php echo htmlspecialchars($certificate['speaker']); ?></strong></p>
                    
                    <div class="certificate-details">
                        <div class="detail-item"><i class="fas fa-calendar"></i><span>Data: <?php echo date('d/m/Y', strtotime($certificate['created_at'])); ?></span></div>
                        <div class="detail-item"><i class="fas fa-shield-alt"></i><span>Verificação: <?php echo strtoupper(substr(md5($certificate['id']), 0, 8)); ?></span></div>
                    </div>
                </div>

                <div class="certificate-footer">
                    <div class="signature-area"><div class="signature-line"></div><p>Translators101</p></div>
                    <div class="certificate-seal"><i class="fas fa-stamp"></i></div>
                </div>
            </div>

            <div class="certificate-actions">
                <a href="download_certificate_files.php?id=<?php echo $certificate['id']; ?>&format=pdf" class="cta-btn">
                    <i class="fas fa-download"></i> Baixar PDF
                </a>
                <a href="view_certificate_files.php?id=<?php echo $certificate['id']; ?>" class="page-btn" target="_blank">
                    <i class="fas fa-external-link-alt"></i> Visualizar
                </a>
                <button onclick="shareCertificate()" class="page-btn"><i class="fas fa-share-alt"></i> Compartilhar</button>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
/* VISION UI - ESTILOS MANTIDOS */
.certificate-preview { background: white; color: #333; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); max-width: 800px; margin: 20px auto; }
.certificate-header { text-align: center; border-bottom: 3px solid #c084fc; padding-bottom: 20px; margin-bottom: 30px; }
.lecture-title { font-size: 1.5rem; color: #c084fc; margin: 20px 0; font-weight: bold; text-align: center; }
.detail-item { display: flex; align-items: center; gap: 8px; background: #f8f9fa; padding: 10px 15px; border-radius: 6px; font-size: 0.9rem; }
.certificate-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 40px; border-top: 1px solid #ddd; padding-top: 20px; }
.signature-line { width: 180px; height: 1px; background: #333; margin-bottom: 5px; }
.certificate-actions { display: flex; justify-content: center; gap: 15px; margin-top: 30px; }
</style>

<script>
function shareCertificate() {
    const url = window.location.href;
    if (navigator.share) {
        navigator.share({ title: 'Meu Certificado - T101', url: url });
    } else {
        navigator.clipboard.writeText(url).then(() => alert('Link copiado!'));
    }
}
</script>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>