<?php
session_start();
require_once __DIR__ . '/config/database.php';

$page_title = 'Visualizar Certificado - Translators101';
$certificate_id = $_GET['id'] ?? null;

$error = '';
$certificate = null;

if (!$certificate_id) {
    $error = 'ID do certificado não fornecido.';
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   l.title as lecture_title, 
                   l.speaker, 
                   u.name as user_name,
                   al.accumulated_watch_time,
                   al.last_watched_seconds
            FROM certificates c
            LEFT JOIN lectures l ON c.lecture_id = l.id
            LEFT JOIN users u ON c.user_id = u.id
            LEFT JOIN access_logs al ON (c.user_id = al.user_id AND al.resource = l.title AND al.certificate_generated = 1)
            WHERE c.id = ?
        ");
        $stmt->execute([$certificate_id]);
        $certificate = $stmt->fetch();

        if (!$certificate) {
            $error = 'Certificado não encontrado.';
        }

    } catch (PDOException $e) {
        $error = 'Erro ao buscar certificado: ' . $e->getMessage();
    }
}

include __DIR__ . '/vision/includes/head.php';
?>

<div class="main-content" style="padding: 0;">
    <?php if ($error): ?>
        <div class="video-card" style="margin: 20px;">
            <div class="alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="videoteca.php" class="cta-btn">
                    <i class="fas fa-video"></i> Ir para Videoteca
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Certificado em tela cheia -->
        <div class="certificate-fullscreen">
            <div class="certificate-container">
                <!-- Header do certificado -->
                <div class="cert-header">
                    <div class="cert-logo">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h1>CERTIFICADO DE CONCLUSÃO</h1>
                    <div class="cert-institution">
                        <h2>TRANSLATORS101</h2>
                        <p>Educação Continuada para Tradutores</p>
                    </div>
                </div>

                <!-- Corpo do certificado -->
                <div class="cert-body">
                    <div class="cert-text">
                        <p class="cert-declaration">
                            Certificamos que
                        </p>
                        
                        <h3 class="cert-student-name">
                            <?php echo strtoupper(htmlspecialchars($certificate['user_name'])); ?>
                        </h3>
                        
                        <p class="cert-completion">
                            concluiu com êxito a palestra
                        </p>
                        
                        <h4 class="cert-course-title">
                            "<?php echo htmlspecialchars($certificate['lecture_title']); ?>"
                        </h4>
                        
                        <p class="cert-speaker">
                            ministrada por <strong><?php echo htmlspecialchars($certificate['speaker']); ?></strong>
                        </p>
                        
                        <?php 
                        $watch_time = 0;
                        if ($certificate['accumulated_watch_time']) {
                            $watch_time = floor($certificate['accumulated_watch_time'] / 60);
                        } elseif ($certificate['last_watched_seconds']) {
                            $watch_time = floor($certificate['last_watched_seconds'] / 60);
                        }
                        
                        if ($watch_time > 0): 
                        ?>
                            <p class="cert-duration">
                                com carga horária de <?php echo $watch_time; ?> minutos
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Detalhes do certificado -->
                    <div class="cert-details">
                        <div class="cert-date">
                            <p>Data de conclusão:</p>
                            <p><strong><?php echo date('d \d\e F \d\e Y', strtotime($certificate['created_at'])); ?></strong></p>
                        </div>
                        
                        <div class="cert-code">
                            <p>Código de verificação:</p>
                            <p><strong><?php echo strtoupper(substr(md5($certificate['id'] . $certificate['created_at']), 0, 12)); ?></strong></p>
                        </div>
                    </div>
                </div>

                <!-- Footer do certificado -->
                <div class="cert-footer">
                    <div class="cert-signature">
                        <div class="signature-line"></div>
                        <p><strong>Translators101</strong></p>
                        <p>Plataforma de Educação Continuada</p>
                    </div>
                    
                    <div class="cert-seal">
                        <div class="seal-circle">
                            <i class="fas fa-certificate"></i>
                            <div class="seal-text">
                                <span>T101</span>
                                <span>OFICIAL</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="cert-qr">
                        <div class="qr-code">
                            <i class="fas fa-qrcode"></i>
                        </div>
                        <p>Verificação Online</p>
                    </div>
                </div>

                <!-- Watermark -->
                <div class="cert-watermark">
                    <i class="fas fa-graduation-cap"></i>
                </div>
            </div>

            <!-- Ações -->
            <div class="cert-actions">
                <a href="download_certificate.php?id=<?php echo $certificate['id']; ?>&download=1" class="cert-btn">
                    <i class="fas fa-download"></i> Baixar PDF
                </a>
                
                <button onclick="window.print()" class="cert-btn">
                    <i class="fas fa-print"></i> Imprimir
                </button>
                
                <a href="videoteca.php" class="cert-btn secondary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
/* Estilo específico para visualização do certificado */
body {
    background: #f5f5f5;
    margin: 0;
    padding: 0;
}

.certificate-fullscreen {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.certificate-container {
    background: white;
    width: 100%;
    max-width: 900px;
    min-height: 600px;
    padding: 60px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.1);
    border-radius: 8px;
    position: relative;
    font-family: 'Times New Roman', serif;
}

.cert-header {
    text-align: center;
    margin-bottom: 40px;
    border-bottom: 3px solid var(--brand-purple);
    padding-bottom: 30px;
}

.cert-logo {
    font-size: 4rem;
    color: var(--brand-purple);
    margin-bottom: 15px;
}

.cert-header h1 {
    font-size: 2.5rem;
    color: var(--brand-purple);
    margin: 0;
    font-weight: bold;
    letter-spacing: 3px;
}

.cert-institution h2 {
    font-size: 1.8rem;
    color: #333;
    margin: 15px 0 5px 0;
    font-weight: bold;
}

.cert-institution p {
    color: #666;
    font-size: 1rem;
    margin: 0;
}

.cert-body {
    text-align: center;
    margin: 40px 0;
}

.cert-declaration {
    font-size: 1.3rem;
    color: #333;
    margin-bottom: 20px;
}

.cert-student-name {
    font-size: 2.2rem;
    color: var(--brand-purple);
    margin: 20px 0;
    font-weight: bold;
    text-decoration: underline;
    text-decoration-color: var(--brand-purple);
}

.cert-completion {
    font-size: 1.3rem;
    color: #333;
    margin: 20px 0;
}

.cert-course-title {
    font-size: 1.6rem;
    color: #333;
    margin: 25px 0;
    font-weight: bold;
    font-style: italic;
}

.cert-speaker {
    font-size: 1.2rem;
    color: #333;
    margin: 20px 0;
}

.cert-duration {
    font-size: 1.1rem;
    color: #666;
    margin-top: 15px;
}

.cert-details {
    display: flex;
    justify-content: space-around;
    margin: 40px 0;
    padding: 20px 0;
    border-top: 1px solid #ddd;
    border-bottom: 1px solid #ddd;
}

.cert-date, .cert-code {
    text-align: center;
}

.cert-date p:first-child, .cert-code p:first-child {
    font-size: 0.9rem;
    color: #666;
    margin-bottom: 5px;
}

.cert-date p:last-child, .cert-code p:last-child {
    font-size: 1.1rem;
    color: #333;
    margin: 0;
}

.cert-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 50px;
}

.cert-signature {
    text-align: center;
    flex: 1;
}

.signature-line {
    width: 200px;
    height: 2px;
    background: #333;
    margin: 0 auto 10px auto;
}

.cert-signature p {
    margin: 5px 0;
    color: #333;
}

.cert-seal {
    flex: 1;
    display: flex;
    justify-content: center;
}

.seal-circle {
    width: 120px;
    height: 120px;
    border: 4px solid var(--brand-purple);
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    position: relative;
    background: rgba(142,68,173,0.1);
}

.seal-circle i {
    font-size: 2rem;
    color: var(--brand-purple);
    margin-bottom: 5px;
}

.seal-text {
    text-align: center;
    font-size: 0.8rem;
    font-weight: bold;
    color: var(--brand-purple);
}

.seal-text span {
    display: block;
    line-height: 1;
}

.cert-qr {
    flex: 1;
    text-align: center;
}

.qr-code {
    width: 80px;
    height: 80px;
    border: 2px solid #333;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px auto;
    font-size: 2rem;
}

.cert-qr p {
    margin: 0;
    font-size: 0.9rem;
    color: #666;
}

.cert-watermark {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-45deg);
    font-size: 15rem;
    color: rgba(142,68,173,0.05);
    z-index: 0;
    pointer-events: none;
}

.cert-actions {
    margin-top: 30px;
    display: flex;
    justify-content: center;
    gap: 15px;
    flex-wrap: wrap;
}

.cert-btn {
    padding: 12px 24px;
    background: var(--brand-purple);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.cert-btn:hover {
    background: rgba(142,68,173,0.8);
    transform: translateY(-2px);
}

.cert-btn.secondary {
    background: #666;
}

.cert-btn.secondary:hover {
    background: #555;
}

/* Estilo para impressão */
@media print {
    .cert-actions {
        display: none;
    }
    
    .certificate-container {
        box-shadow: none;
        max-width: none;
        width: 100%;
    }
    
    body {
        background: white;
    }
}

/* Responsivo */
@media (max-width: 768px) {
    .certificate-container {
        padding: 30px 20px;
    }
    
    .cert-header h1 {
        font-size: 1.8rem;
        letter-spacing: 2px;
    }
    
    .cert-student-name {
        font-size: 1.8rem;
    }
    
    .cert-course-title {
        font-size: 1.3rem;
    }
    
    .cert-footer {
        flex-direction: column;
        gap: 30px;
        text-align: center;
    }
    
    .cert-details {
        flex-direction: column;
        gap: 20px;
    }
}
</style>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>

