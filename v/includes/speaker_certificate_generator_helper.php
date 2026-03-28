<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Gera e salva o PDF do certificado de palestrante.
 * - Mesmo formato base dos certificados de público, mas com:
 *   - Cargo e RG do signatário fixos
 *   - Data apresentada é a da palestra (não a data de emissão)
 *   - QR code aponta para verificação
 *
 * Retorna caminho absoluto do arquivo gerado ou false.
 */
function generateAndSaveSpeakerCertificatePdf($certificate_id, $data, $logCallback = null) {
    try {
        // Diretório de saída
        $baseDir = __DIR__ . '/../certificates_speakers';
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0775, true);
        }

        $pdfPath = $baseDir . '/speaker_certificate_' . $certificate_id . '.pdf';
        $qrPath  = $baseDir . '/speaker_qr_' . $certificate_id . '.png';

        // URL de verificação
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host   = $_SERVER['HTTP_HOST'] ?? 'v.translators101.com';
        $verifyUrl = $scheme . '://' . $host . '/verificar_certificado_palestrante.php?id=' . urlencode($certificate_id);

        // 1) Gerar QR Code (via Google Charts ou lib; aqui uso Google Charts)
        $qrApi = 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . urlencode($verifyUrl) . '&choe=UTF-8';
        $qrData = @file_get_contents($qrApi);
        if ($qrData === false) {
            if ($logCallback) call_user_func($logCallback, "Falha ao gerar QR para $certificate_id");
            return false;
        }
        @file_put_contents($qrPath, $qrData);

        // 2) Montar HTML (design minimalista; ajuste para seu layout)
        // Assinatura fixada conforme pedido
        $signer_name = $data['signer_name'] ?? 'Rodrigo';
        $signer_rg   = 'RG 20.009.396-4';
        $signer_role = 'CEO da Translators101';

        $speaker_name  = htmlspecialchars($data['speaker_name'] ?? '');
        $lecture_title = htmlspecialchars($data['lecture_title'] ?? '');
        $duration_hours = number_format((float)($data['duration_hours'] ?? 1.0), 1, ',', '');
        $lecture_date  = $data['lecture_date'] ? date('d/m/Y', strtotime($data['lecture_date'])) : '';

        // Importante: não usar data de emissão na arte
        $html = '
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Certificado de Palestrante</title>
<style>
  @page { size: A4 landscape; margin: 25mm; }
  body { font-family: Arial, Helvetica, sans-serif; color: #111; }
  .wrap { border: 6px solid #8e44ad; border-radius: 16px; padding: 28px; position: relative; }
  .header { text-align: center; margin-bottom: 12px; }
  .brand { font-size: 20px; color: #8e44ad; letter-spacing: 1px; }
  .title { font-size: 36px; font-weight: 800; margin: 10px 0 5px; }
  .subtitle { color: #555; font-size: 14px; }
  .content { margin-top: 28px; line-height: 1.5; font-size: 18px; }
  .name { font-size: 28px; font-weight: 700; color: #111; text-align: center; margin: 16px 0; }
  .lecture { margin-top: 20px; font-size: 18px; color: #222; }
  .meta { display: flex; gap: 24px; margin-top: 14px; font-size: 16px; color: #333; }
  .footer { display:flex; justify-content: space-between; align-items: flex-end; margin-top: 40px; }
  .sign { text-align: left; }
  .sign .line { width: 320px; height: 2px; background: #8e44ad; margin-bottom: 8px; }
  .sign .who { font-weight: 700; }
  .sign .role { color: #555; }
  .qr { text-align: right; }
  .qr img { width: 110px; height: 110px; border: 3px solid #8e44ad; border-radius: 8px; }
  .verify { font-size: 12px; color: #555; margin-top: 6px; max-width: 340px; }
  .id { position: absolute; bottom: 12px; left: 28px; color: #777; font-size: 12px; }
</style>
</head>
<body>
  <div class="wrap">
    <div class="header">
      <div class="brand">Translators101</div>
      <div class="title">Certificado de Palestrante</div>
      <div class="subtitle">Atestamos que ministrou a palestra conforme os dados abaixo</div>
    </div>

    <div class="content">
      <div class="name">' . $speaker_name . '</div>
      <div class="lecture">
        Palestra: <strong>' . $lecture_title . '</strong>
      </div>
      <div class="meta">
        <div>CH: <strong>' . $duration_hours . ' hora(s)</strong></div>' .
        ($lecture_date ? '<div>Data da palestra: <strong>' . $lecture_date . '</strong></div>' : '') . '
      </div>
    </div>

    <div class="footer">
      <div class="sign">
        <div class="line"></div>
        <div class="who">' . htmlspecialchars($signer_name) . '</div>
        <div class="role">' . $signer_rg . ' — ' . $signer_role . '</div>
      </div>
      <div class="qr">
        <img src="' . htmlspecialchars($qrPath) . '" alt="QR">
        <div class="verify">Escaneie para verificar a autenticidade ou acesse: ' . htmlspecialchars($verifyUrl) . '</div>
      </div>
    </div>

    <div class="id">ID: ' . htmlspecialchars($certificate_id) . '</div>
  </div>
</body>
</html>
        ';

        // 3) Gerar PDF a partir do HTML
        // Se você já usa Dompdf/Mpdf/Weasyprint, chame aqui. Vou usar Dompdf como exemplo:
        if (!class_exists('Dompdf\Dompdf')) {
            // Tente carregar Dompdf se existir vendor
            $vendorAutoload = __DIR__ . '/../vendor/autoload.php';
            if (file_exists($vendorAutoload)) {
                require_once $vendorAutoload;
            }
        }

        if (class_exists('Dompdf\Dompdf')) {
            $dompdf = new Dompdf\Dompdf(['isRemoteEnabled' => true]);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            file_put_contents($pdfPath, $dompdf->output());
        } else {
            // Fallback: salvar HTML caso não tenha DOMPDF (para depuração)
            $htmlPath = $baseDir . '/speaker_certificate_' . $certificate_id . '.html';
            file_put_contents($htmlPath, $html);
            // Informe para configurar o gerador de PDF
            return $htmlPath;
        }

        return $pdfPath;
    } catch (Exception $e) {
        if ($logCallback) call_user_func($logCallback, "Erro PDF: " . $e->getMessage());
        return false;
    }
}