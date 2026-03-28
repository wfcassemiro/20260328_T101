<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/config/database.php';

$id = $_GET['id'] ?? '';
$page_title = 'Verificação - Certificado de Palestrante';
include __DIR__ . '/vision/includes/head.php';
include __DIR__ . '/vision/includes/header.php';
include __DIR__ . '/vision/includes/sidebar.php';

$cert = null;
if ($id && isset($pdo)) {
  try {
    // **CORREÇÃO:** Buscar a 'lecture_date' manual direto da tabela 'certificates'
    // Não precisamos mais do JOIN com 'lectures' para a data.
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            speaker_name, 
            lecture_title, 
            duration_hours, 
            file_path,
            lecture_date -- **NOVO:** Lendo a data correta
        FROM 
            certificates
        WHERE 
            id = ? 
            AND user_name = speaker_name -- Garante que é um certificado de palestrante
    ");
    $stmt->execute([$id]);
    $cert = $stmt->fetch(PDO::FETCH_ASSOC);
  } catch (PDOException $e) {
    // Lidar com o erro de banco de dados, se necessário
    error_log("Erro ao verificar certificado: " . $e.getMessage());
    $cert = null;
  }
}
?>
<div class="main-content">
  <div class="glass-hero">
    <div class="hero-content">
      <h1><i class="fas fa-shield-check"></i> Verificação de Certificado de Palestrante</h1>
      <p>Confirmação de autenticidade</p>
    </div>
  </div>
  <div class="video-card glass-card">
    <?php if ($cert): ?>
      <h2>Certificado Válido ✅</h2>
      <p><strong>ID:</strong> <?php echo htmlspecialchars($cert['id']); ?></p>
      <p><strong>Palestrante:</strong> <?php echo htmlspecialchars($cert['speaker_name']); ?></p>
      <p><strong>Palestra:</strong> <?php echo htmlspecialchars($cert['lecture_title']); ?></p>
      <p><strong>Carga horária:</strong> <?php echo number_format((float)$cert['duration_hours'], 1); ?>h</p>
      <p><strong>Data da palestra:</strong> <?php echo $cert['lecture_date'] ? date('d/m/Y', strtotime($cert['lecture_date'])) : '—'; ?></p>
      <div style="margin-top:15px;">
        <a class="cta-btn" href="<?php echo htmlspecialchars($cert['file_path']); ?>" target="_blank">Visualizar PDF</a>
        <a class="cta-btn" href="<?php echo htmlspecialchars($cert['file_path']); ?>" download>Baixar PDF</a>
      </div>
    <?php else: ?>
      <h2>Certificado Inválido ❌</h2>
      <p>O ID de certificado <strong><?php echo htmlspecialchars($id); ?></strong> não foi encontrado em nossos registros de palestrantes ou é inválido.</p>
      <p>Por favor, verifique o link ou entre em contato com o suporte.</p>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/vision/includes/footer.php'; ?>