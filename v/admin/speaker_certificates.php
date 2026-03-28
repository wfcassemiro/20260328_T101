<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/speaker_certificate_generator_helper.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
  header('Location: /login.php');
  exit;
}

$page_title = 'Gerenciar Certificados de Palestrantes - Admin';
$message = '';
$error = '';

function generateUUID() {
  return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000,
    mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
  );
}

function writeToCustomLog($msg) {
  $log_file = __DIR__ . '/../certificate_errors.log';
  @file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . "] [SPEAKER_CERT] $msg\n", FILE_APPEND);
}

function calcHoursFromMinutes($minutes) {
  $minutes = (int)($minutes ?? 0);
  if ($minutes <= 30) return 0.5;
  if ($minutes <= 60) return 1.0;
  if ($minutes <= 90) return 1.5;
  return ceil(($minutes / 60) * 2) / 2.0;
}

try {
  // Carregar listas
  // Opção 1: palestrantes são os speakers das palestras (uniques de lectures)
  $stmt = $pdo->query("SELECT DISTINCT speaker AS speaker_name FROM lectures WHERE speaker IS NOT NULL AND speaker <> '' ORDER BY speaker");
  $speakers = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Palestras
  $stmt = $pdo->query("SELECT id, title, speaker, duration_minutes, (CASE WHEN DATE(date) IS NOT NULL THEN DATE(date) END) AS lecture_date FROM lectures ORDER BY title");
  $lectures = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
  $speakers = [];
  $lectures = [];
  $error = 'Erro ao carregar dados: ' . $e->getMessage();
}

// Ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['generate_speaker_certificate'])) {
    $lecture_id = $_POST['lecture_id'] ?? '';
    $speaker_name = $_POST['speaker_name'] ?? '';
    $speaker_user_id = $_POST['speaker_user_id'] ?? ''; // opcional
    $manual_lecture_date = $_POST['lecture_date'] ?? ''; // opcional quando não há no BD

    if (empty($lecture_id) || empty($speaker_name)) {
      $error = 'Selecione a palestra e o palestrante.';
    } else {
      try {
        // Buscar palestra
        $stmt = $pdo->prepare("SELECT id, title, speaker, duration_minutes, (CASE WHEN DATE(date) IS NOT NULL THEN DATE(date) END) AS lecture_date FROM lectures WHERE id = ?");
        $stmt->execute([$lecture_id]);
        $lecture = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$lecture) throw new Exception('Palestra não encontrada.');

        // Calcular horas
        $duration_hours = calcHoursFromMinutes($lecture['duration_minutes'] ?? 0);

        // Data da palestra
        $lecture_date = $lecture['lecture_date'] ?: ($manual_lecture_date ?: null);

        // Evitar duplicidade: um certificado por palestra/palestrante
        $stmt = $pdo->prepare("SELECT id FROM speaker_certificates WHERE lecture_id = ? AND speaker_name = ?");
        $stmt->execute([$lecture_id, $speaker_name]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
          $message = 'Já existe um certificado para este palestrante nesta palestra. ID: ' . $existing['id'];
        } else {
          $certificate_id = generateUUID();

          // Salvar no banco
          $stmt = $pdo->prepare("
            INSERT INTO speaker_certificates (id, speaker_user_id, lecture_id, speaker_name, lecture_title, duration_hours, lecture_date, issued_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
          ");
          $stmt->execute([
            $certificate_id,
            $speaker_user_id ?: null,
            $lecture_id,
            $speaker_name,
            $lecture['title'],
            $duration_hours,
            $lecture_date
          ]);

          // Gerar PDF
          $pdfPath = generateAndSaveSpeakerCertificatePdf($certificate_id, [
            'speaker_name'   => $speaker_name,
            'lecture_title'  => $lecture['title'],
            'duration_hours' => $duration_hours,
            'lecture_date'   => $lecture_date,
            // Assinatura fixa
            'signer_name'    => 'Rodrigo'
          ], 'writeToCustomLog');

          if ($pdfPath) {
            // Auditoria
            $stmt = $pdo->prepare("INSERT INTO speaker_certificate_audit_log (action, certificate_id, lecture_id, admin_user_id, details, created_at) VALUES ('GENERATE', ?, ?, ?, ?, NOW())");
            $stmt->execute([$certificate_id, $lecture_id, $_SESSION['user_id'], 'Gerado para ' . $speaker_name . ' - ' . $lecture['title']]);

            $message = 'Certificado de palestrante gerado com sucesso. ID: ' . $certificate_id;
          } else {
            // rollback lógico
            $pdo->prepare("DELETE FROM speaker_certificates WHERE id = ?")->execute([$certificate_id]);
            $error = 'Erro ao gerar o arquivo PDF do certificado.';
          }
        }
      } catch (Exception $e) {
        $error = 'Erro ao gerar certificado: ' . $e->getMessage();
      }
    }
  }

  if (isset($_POST['delete_speaker_certificate'])) {
    $cert_id = $_POST['certificate_id'] ?? '';
    if (empty($cert_id)) {
      $error = 'ID do certificado é obrigatório.';
    } else {
      try {
        // Remover arquivo
        $pdf = __DIR__ . '/../certificates_speakers/speaker_certificate_' . $cert_id . '.pdf';
        $qr  = __DIR__ . '/../certificates_speakers/speaker_qr_' . $cert_id . '.png';
        if (file_exists($pdf)) @unlink($pdf);
        if (file_exists($qr)) @unlink($qr);

        $pdo->prepare("DELETE FROM speaker_certificates WHERE id = ?")->execute([$cert_id]);
        $pdo->prepare("INSERT INTO speaker_certificate_audit_log (action, certificate_id, lecture_id, admin_user_id, details, created_at)
          SELECT 'DELETE', id, lecture_id, ?, CONCAT('Deletado: ', speaker_name, ' - ', lecture_title), NOW() FROM speaker_certificates WHERE id = ?")->execute([$_SESSION['user_id'], $cert_id]);

        $message = 'Certificado de palestrante deletado com sucesso.';
      } catch (Exception $e) {
        $error = 'Erro ao deletar certificado: ' . $e->getMessage();
      }
    }
  }
}

// Carregar certificados recentes
try {
  $stmt = $pdo->query("
    SELECT s.*, l.speaker AS db_speaker, l.title AS db_title 
    FROM speaker_certificates s
    LEFT JOIN lectures l ON s.lecture_id = l.id
    ORDER BY s.issued_at DESC
    LIMIT 100
  ");
  $speaker_certs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $speaker_certs = [];
}

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>
<div class="main-content">
  <div class="glass-hero">
    <div class="hero-content">
      <h1><i class="fas fa-user-tie"></i> Certificados de Palestrantes</h1>
      <p>Emita certificados no mesmo padrão, com QR e ID</p>
    </div>
  </div>

  <?php if ($message): ?>
  <div class="success-alert"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="error-alert"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <div class="video-card glass-card">
    <h2><i class="fas fa-plus-circle"></i> Gerar Certificado de Palestrante</h2>
    <form method="POST" class="admin-form">
      <div class="form-group">
        <label for="lecture_id">Selecionar Palestra</label>
        <select name="lecture_id" id="lecture_id" class="form-control" required>
          <option value="">Escolha...</option>
          <?php foreach ($lectures as $lec): ?>
            <option value="<?php echo htmlspecialchars($lec['id']); ?>">
              <?php echo htmlspecialchars($lec['title'] . ' — ' . $lec['speaker'] . ' (' . ($lec['duration_minutes'] ?? 0) . ' min)'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="speaker_name">Selecionar/Informar Palestrante</label>
        <input list="speakers_list" name="speaker_name" id="speaker_name" class="form-control" placeholder="Digite o nome do palestrante" required>
        <datalist id="speakers_list">
          <?php foreach ($speakers as $sp): ?>
            <option value="<?php echo htmlspecialchars($sp['speaker_name']); ?>"></option>
          <?php endforeach; ?>
        </datalist>
      </div>

      <div class="form-group">
        <label for="speaker_user_id">Usuário (opcional, se o palestrante também for assinante)</label>
        <input type="text" name="speaker_user_id" id="speaker_user_id" class="form-control" placeholder="ID do usuário (UUID)">
      </div>

      <div class="form-group">
        <label for="lecture_date">Data da palestra (opcional, se não constar no BD)</label>
        <input type="date" name="lecture_date" id="lecture_date" class="form-control">
      </div>

      <div class="options-row">
        <button type="submit" name="generate_speaker_certificate" class="cta-btn">
          <i class="fas fa-certificate"></i> Gerar Certificado
        </button>
      </div>
    </form>
  </div>

  <div class="video-card glass-card">
    <h2><i class="fas fa-list"></i> Certificados de Palestrante Recentes</h2>

    <?php if (empty($speaker_certs)): ?>
      <div class="empty-state">
        <i class="fas fa-certificate"></i>
        <p>Nenhum certificado encontrado.</p>
      </div>
    <?php else: ?>
      <div class="table-container">
        <table class="certificates-table">
          <thead>
            <tr>
              <th>Palestrante</th>
              <th>Palestra</th>
              <th>CH</th>
              <th>Data da palestra</th>
              <th>Arquivo</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($speaker_certs as $sc): 
              $pdfPath = __DIR__ . '/../certificates_speakers/speaker_certificate_' . $sc['id'] . '.pdf';
              $exists = file_exists($pdfPath);
            ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($sc['speaker_name']); ?></strong></td>
                <td><?php echo htmlspecialchars($sc['lecture_title']); ?></td>
                <td><?php echo number_format((float)$sc['duration_hours'], 1); ?>h</td>
                <td><?php echo $sc['lecture_date'] ? date('d/m/Y', strtotime($sc['lecture_date'])) : '-'; ?></td>
                <td>
                  <?php if ($exists): ?>
                    <span class="file-status exists">✅ PDF</span>
                  <?php else: ?>
                    <span class="file-status missing">❌ Arquivo</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="action-buttons">
                    <a href="/view_speaker_certificate.php?id=<?php echo urlencode($sc['id']); ?>" target="_blank" class="action-btn view-btn" title="Visualizar">
                      <i class="fas fa-eye"></i>
                    </a>
                    <a href="/download_speaker_certificate.php?id=<?php echo urlencode($sc['id']); ?>" class="action-btn download-btn" title="Download">
                      <i class="fas fa-download"></i>
                    </a>
                    <a href="/verificar_certificado_palestrante.php?id=<?php echo urlencode($sc['id']); ?>" target="_blank" class="action-btn verify-btn" title="Verificar">
                      <i class="fas fa-shield-check"></i>
                    </a>
                    <form method="POST" onsubmit="return confirm('Deletar este certificado?');" style="display:inline;">
                      <input type="hidden" name="certificate_id" value="<?php echo htmlspecialchars($sc['id']); ?>">
                      <button type="submit" name="delete_speaker_certificate" class="action-btn delete-btn" title="Deletar">
                        <i class="fas fa-trash"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../vision/includes/footer.php'; ?>