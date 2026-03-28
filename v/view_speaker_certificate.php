<?php
require_once __DIR__ . '/config/database.php';
$id = $_GET['id'] ?? '';
if (!$id) { http_response_code(400); echo 'ID inválido'; exit; }

$pdf = __DIR__ . '/certificates_speakers/speaker_certificate_' . basename($id) . '.pdf';
if (file_exists($pdf)) {
  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="speaker_certificate_' . $id . '.pdf"');
  readfile($pdf);
} else {
  echo 'Arquivo não encontrado.';
}