<?php
// Define o fuso horário para GMT-3
date_default_timezone_set('America/Sao_Paulo');

require_once 'config/database.php';

$page_title = 'Verificação de Certificado - Translators101';
$certificate_id = $_GET['id'] ?? '';
$certificate = null;
$error = '';
$success = false;

if (empty($certificate_id)) {
    $error = 'ID do certificado não fornecido.';
} else {
    try {
        // Query que funciona para Assinantes e Convidados
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   COALESCE(u.email, 'Convidado Externo') as display_email,
                   l.description as lecture_description
            FROM certificates c
            LEFT JOIN users u ON c.user_id = u.id
            LEFT JOIN lectures l ON c.lecture_id = l.id
            WHERE c.id = ?
        ");
        $stmt->execute([$certificate_id]);
        $certificate = $stmt->fetch();

        if ($certificate) { $success = true; } 
        else { $error = 'Certificado não encontrado.'; }
    } catch (PDOException $e) {
        $error = 'Erro interno no sistema.';
    }
}

include __DIR__ . '/vision/includes/head.php';
include __DIR__ . '/vision/includes/header.php';
include __DIR__ . '/vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="no-print">
        <div class="glass-hero">
            <div class="hero-content">
                <h1><i class="fas fa-shield-check"></i> Verificação de Autenticidade</h1>
                <p>Validando credenciais Translators101</p>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="verification-status-badge success">
                <i class="fas fa-check-circle"></i>
                <span>Certificado Válido e Autêntico</span>
            </div>
        <?php endif; ?>
    </div>

    <div class="certificate-print-container <?php echo !$success ? 'no-print' : ''; ?>">
        
        <div class="print-header">
            <img src="images/Logo Webp.svg" alt="Translators101" class="print-logo">
            <div class="print-title">
                <h1>Relatório de Verificação de Autenticidade</h1>
                <p>Emitido em: <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="print-content">
                <div class="print-row">
                    <div class="print-col">
                        <label>Participante</label>
                        <div class="value"><?php echo htmlspecialchars($certificate['user_name']); ?></div>
                        <div class="sub-value"><?php echo htmlspecialchars($certificate['display_email']); ?></div>
                    </div>
                    <div class="print-col">
                        <label>Palestra / Evento</label>
                        <div class="value"><?php echo htmlspecialchars($certificate['lecture_title']); ?></div>
                    </div>
                </div>

                <div class="print-row">
                    <div class="print-col">
                        <label>Palestrante</label>
                        <div class="value"><?php echo htmlspecialchars($certificate['speaker_name']); ?></div>
                    </div>
                    <div class="print-col">
                        <label>Carga Horária</label>
                        <div class="value"><?php echo $certificate['duration_hours']; ?> horas</div>
                    </div>
                </div>

                <div class="print-row security-row">
                    <div class="print-col">
                        <label>ID de Verificação (UUID)</label>
                        <div class="value mono"><?php echo htmlspecialchars($certificate['id']); ?></div>
                    </div>
                    <div class="print-col">
                        <label>Data de Conclusão</label>
                        <div class="value"><?php echo date('d/m/Y', strtotime($certificate['issued_at'])); ?></div>
                    </div>
                </div>

                <div class="print-footer-info">
                    <p><strong>Hash de Segurança:</strong> <?php echo strtoupper(md5($certificate['id'] . $certificate['issued_at'])); ?></p>
                    <p>Este documento confirma que a credencial acima foi validada digitalmente nos servidores da Translators101.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="print-error">
                <p>Erro: <?php echo $error; ?></p>
            </div>
        <?php endif; ?>

        <div class="print-footer">
            Translators101 - Educação Continuada para Tradutores e Intérpretes<br>
            www.translators101.com.br
        </div>
    </div>

    <div class="certificate-navigation no-print mt-4">
        <?php if ($success): ?>
            <button onclick="window.print()" class="nav-btn primary">
                <i class="fas fa-print"></i> Imprimir Documento
            </button>
            <a href="view_certificate_files.php?id=<?php echo urlencode($certificate['id']); ?>" class="nav-btn secondary">
                <i class="fas fa-eye"></i> Ver Certificado
            </a>
        <?php endif; ?>
        <a href="index.php" class="nav-btn secondary">Voltar ao Início</a>
    </div>
</div>

<style>
/* --- ESTILOS PARA TELA (VISION UI) --- */
.glass-hero { background: rgba(25, 25, 25, 0.7); padding: 40px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; text-align: center; }
.verification-status-badge { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 15px; border-radius: 12px; max-width: 500px; margin: 0 auto 30px; background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #10f981; font-weight: bold; }
.nav-btn { padding: 12px 25px; border-radius: 10px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer; transition: 0.3s; }
.nav-btn.primary { background: #c084fc; color: #fff; }
.nav-btn.secondary { background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.1); }
.certificate-navigation { display: flex; gap: 15px; justify-content: center; }

/* Esconder container de impressão na tela para não duplicar */
.certificate-print-container { display: none; }

/* --- ESTILOS EXCLUSIVOS PARA IMPRESSÃO --- */
@media print {
    /* Esconder tudo que não é o documento */
    body * { visibility: hidden; background: white !important; color: black !important; }
    .no-print, header, footer, .sidebar, .nav-btn, .glass-hero, .verification-status-badge { display: none !important; }
    
    /* Mostrar apenas o container de impressão */
    .certificate-print-container, .certificate-print-container * { visibility: visible; }
    .certificate-print-container {
        display: block !important;
        position: absolute;
        left: 0; top: 0;
        width: 100%;
        padding: 40px;
        background: white !important;
        color: black !important;
        font-family: "Segoe UI", Arial, sans-serif;
    }

    .print-header { display: flex; align-items: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
    .print-logo { height: 60px; margin-right: 20px; }
    .print-title h1 { font-size: 18pt; margin: 0; color: #333; }
    .print-title p { font-size: 10pt; color: #666; margin: 5px 0 0; }

    .print-row { display: flex; gap: 20px; margin-bottom: 25px; }
    .print-col { flex: 1; }
    .print-col label { display: block; font-size: 9pt; text-transform: uppercase; color: #777; font-weight: bold; margin-bottom: 5px; }
    .print-col .value { font-size: 12pt; font-weight: bold; color: #000; }
    .print-col .sub-value { font-size: 10pt; color: #555; }
    .value.mono { font-family: "Courier New", monospace; color: #c084fc !important; }

    .security-row { background: #f9f9f9; padding: 15px; border-radius: 8px; border: 1px solid #eee; }
    .print-footer-info { margin-top: 40px; padding: 20px; border-top: 1px dashed #ccc; font-size: 9pt; line-height: 1.5; color: #444; }
    .print-footer { position: fixed; bottom: 20px; left: 0; width: 100%; text-align: center; font-size: 8pt; color: #888; border-top: 1px solid #eee; padding-top: 10px; }
}
</style>