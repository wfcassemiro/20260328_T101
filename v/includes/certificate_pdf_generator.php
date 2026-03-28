<?php
// includes/certificate_pdf_generator.php

/**
 * Gera um certificado em formato PDF usando TCPDF com design T101 CORRIGIDO
 * @param string $certificate_id UUID do certificado
 * @param array $certificate_data Dados do certificado
 * @param string $log_prefix Prefixo para logs
 * @param callable $logger Função de log
 * @return string|false Caminho do PDF gerado ou false em caso de erro
 */
function generateCertificatePDF($certificate_id, $certificate_data, $log_prefix, $logger) {
    $logger("DEBUG: [$log_prefix] Iniciando geração de PDF T101 CORRIGIDO para ID: $certificate_id");
    
    // Verificar se TCPDF está disponível
    $tcpdf_paths = [
        __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php',
        __DIR__ . '/../tcpdf/tcpdf.php',
        '/usr/share/php/tcpdf/tcpdf.php'
    ];
    
    $tcpdf_found = false;
    foreach ($tcpdf_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $tcpdf_found = true;
            $logger("DEBUG: [$log_prefix] TCPDF encontrado em: $path");
            break;
        }
    }
    
    if (!$tcpdf_found) {
        $logger("ERRO: [$log_prefix] TCPDF não encontrado. Usando fallback para PNG->PDF");
        return generatePDFFromPNG($certificate_id, $certificate_data, $log_prefix, $logger);
    }
    
    try {
        // Criar nova instância TCPDF em paisagem (A4 landscape)
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        
        // Configurações do documento
        $pdf->SetCreator('Translators101');
        $pdf->SetAuthor('Translators101');
        $pdf->SetTitle('Certificado de Participação T101');
        $pdf->SetSubject('Certificado Digital T101');
        
        // Remover header e footer padrão
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Adicionar página
        $pdf->AddPage();
        
        // Definir margens mínimas
        $pdf->SetMargins(5, 5, 5);
        
        // Cores T101
        $purple_color = array(142, 68, 173); // #8e44ad - cor oficial T101
        $black_color = array(0, 0, 0);
        $red_color = array(220, 53, 69);
        
        // CORREÇÃO: Verificar se existe template T101 e aplicar de forma correta
        $template_path = __DIR__ . '/../images/template.png';
        $template_applied = false;
        
        if (file_exists($template_path)) {
            // Usar template oficial T101 como fundo - POSIÇÃO CORRIGIDA
            $pdf->Image($template_path, 0, 0, 297, 210, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);
            $template_applied = true;
            $logger("DEBUG: [$log_prefix] Template T101 oficial aplicado como fundo");
        }
        
        if (!$template_applied) {
            // Criar fundo limpo se template não existir
            $pdf->SetFillColor(245, 245, 245);
            $pdf->Rect(0, 0, 297, 210, 'F');
            
            // Adicionar bordas decorativas simples
            $pdf->SetDrawColor($purple_color[0], $purple_color[1], $purple_color[2]);
            $pdf->SetLineWidth(3);
            $pdf->Rect(10, 10, 277, 190);
            $pdf->SetLineWidth(1);
            $pdf->Rect(15, 15, 267, 180);
            
            $logger("DEBUG: [$log_prefix] Template alternativo criado");
        }
        
        // ========== LAYOUT COM POSIÇÕES ABSOLUTAS (SEM SOBREPOSIÇÃO) ==========
        
        // Aguardar template ser aplicado antes de adicionar texto
        if ($template_applied) {
            // Com template: usar posições que não conflitem com o design
            $start_y = 60;
        } else {
            // Sem template: usar posições livres
            $start_y = 30;
        }
        
        // 🏢 Título Principal (posição absoluta)
        $pdf->SetFont('helvetica', 'B', 24);
        $pdf->SetTextColor($purple_color[0], $purple_color[1], $purple_color[2]);
        $pdf->SetXY(20, $start_y);
        $pdf->Cell(257, 8, 'TRANSLATORS101', 0, 0, 'C');
        
        $pdf->SetXY(20, $start_y + 12);
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->Cell(257, 8, 'CERTIFICADO DE PARTICIPAÇÃO', 0, 0, 'C');
        
        // 👤 Nome do Usuário (posição absoluta)
        $pdf->SetFont('helvetica', '', 14);
        $pdf->SetTextColor($black_color[0], $black_color[1], $black_color[2]);
        $pdf->SetXY(20, $start_y + 35);
        $pdf->Cell(257, 6, 'Certificamos que', 0, 0, 'C');
        
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetTextColor($purple_color[0], $purple_color[1], $purple_color[2]);
        $pdf->SetXY(20, $start_y + 45);
        $pdf->Cell(257, 8, strtoupper($certificate_data['user_name']), 0, 0, 'C');
        
        // 📚 Texto de participação
        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetTextColor($black_color[0], $black_color[1], $black_color[2]);
        $pdf->SetXY(20, $start_y + 60);
        $pdf->Cell(257, 6, 'participou da palestra', 0, 0, 'C');
        
        // 🎯 Título da Palestra (com quebra inteligente e posição absoluta)
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor($purple_color[0], $purple_color[1], $purple_color[2]);
        
        $lecture_title = $certificate_data['lecture_title'];
        if (strlen($lecture_title) > 70) {
            // Quebrar em duas linhas
            $words = explode(' ', $lecture_title);
            $line1 = '';
            $line2 = '';
            $switch_to_line2 = false;
            
            foreach ($words as $word) {
                if (!$switch_to_line2 && strlen($line1 . ' ' . $word) <= 70) {
                    $line1 .= ($line1 ? ' ' : '') . $word;
                } else {
                    $switch_to_line2 = true;
                    $line2 .= ($line2 ? ' ' : '') . $word;
                }
            }
            
            $pdf->SetXY(20, $start_y + 75);
            $pdf->Cell(257, 6, '"' . trim($line1) . '"', 0, 0, 'C');
            if (!empty($line2)) {
                $pdf->SetXY(20, $start_y + 85);
                $pdf->Cell(257, 6, '"' . trim($line2) . '"', 0, 0, 'C');
                $next_y = $start_y + 95;
            } else {
                $next_y = $start_y + 85;
            }
        } else {
            $pdf->SetXY(20, $start_y + 75);
            $pdf->Cell(257, 6, '"' . $lecture_title . '"', 0, 0, 'C');
            $next_y = $start_y + 85;
        }
        
        // 👨‍🏫 Palestrante e duração (posição absoluta)
        $pdf->SetFont('helvetica', '', 11);
        $pdf->SetTextColor($black_color[0], $black_color[1], $black_color[2]);
        
        $duration_hours = $certificate_data['duration_minutes'] / 60;
        if ($duration_hours <= 0.5) {
            $duration_text = '0.5h';
        } elseif ($duration_hours <= 1.0) {
            $duration_text = '1.0h';
        } elseif ($duration_hours <= 1.5) {
            $duration_text = '1.5h';
        } else {
            $duration_text = ceil($duration_hours * 2) / 2 . 'h';
        }
        
        $pdf->SetXY(20, $next_y + 10);
        $pdf->Cell(257, 5, 'ministrada por ' . $certificate_data['speaker_name'] . ' com carga horária de ' . $duration_text, 0, 0, 'C');
        
        // 📅 Data de emissão (posição absoluta)
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetXY(20, $next_y + 25);
        $pdf->Cell(257, 4, 'Emitido em ' . date('d/m/Y'), 0, 0, 'C');
        
        // 🆔 ID do certificado (posição absoluta)
        $pdf->SetTextColor($red_color[0], $red_color[1], $red_color[2]);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY(20, $next_y + 35);
        $pdf->Cell(257, 3, 'ID: ' . $certificate_id, 0, 0, 'C');
        
        // 📱 QR Code (posição absoluta - CORRIGIDO)
        $qr_added = false;
        if (file_exists(__DIR__ . '/../qr_generator.php')) {
            require_once __DIR__ . '/../qr_generator.php';
            $verification_url = generateVerificationURL($certificate_id);
            $qr_result = generateQRCode($verification_url, 150);
            
            if ($qr_result['success']) {
                // Criar arquivo temporário para o QR code
                $temp_qr = tempnam(sys_get_temp_dir(), 'qr_cert_') . '.png';
                file_put_contents($temp_qr, $qr_result['data']);
                
                // POSIÇÃO CORRIGIDA: Canto inferior direito sem sobreposição
                $qr_x = 240;  // Mais à direita
                $qr_y = 160;  // Mais abaixo
                $qr_size = 30; // Menor para não sobrepor
                
                // Adicionar QR code
                $pdf->Image($temp_qr, $qr_x, $qr_y, $qr_size, $qr_size, 'PNG');
                
                // Adicionar texto explicativo do QR code
                $pdf->SetFont('helvetica', '', 6);
                $pdf->SetTextColor($black_color[0], $black_color[1], $black_color[2]);
                $pdf->SetXY($qr_x - 5, $qr_y + $qr_size + 2);
                $pdf->Cell(40, 2, 'Verificação', 0, 0, 'C');
                
                // Limpar arquivo temporário
                @unlink($temp_qr);
                
                $qr_added = true;
                $logger("DEBUG: [$log_prefix] QR Code T101 adicionado ao PDF (posição corrigida)");
            }
        }
        
        if (!$qr_added) {
            $logger("AVISO: [$log_prefix] QR Code não pôde ser adicionado ao PDF");
        }
        
        // Salvar PDF
        $cert_dir = __DIR__ . '/../certificates';
        if (!is_dir($cert_dir)) {
            mkdir($cert_dir, 0755, true);
        }
        
        $pdf_filename = 'certificate_' . $certificate_id . '.pdf';
        $pdf_path = $cert_dir . '/' . $pdf_filename;
        
        $pdf->Output($pdf_path, 'F');
        
        $logger("SUCESSO: [$log_prefix] PDF T101 CORRIGIDO gerado em: $pdf_path");
        return $pdf_path;
        
    } catch (Exception $e) {
        $logger("ERRO: [$log_prefix] Erro na geração PDF T101: " . $e->getMessage());
        return false;
    }
}

/**
 * Fallback: Converte PNG T101 existente para PDF (MELHORADO)
 */
function generatePDFFromPNG($certificate_id, $certificate_data, $log_prefix, $logger) {
    $logger("DEBUG: [$log_prefix] Usando fallback PNG T101 -> PDF MELHORADO");
    
    // Verificar se PNG T101 existe
    $png_path = __DIR__ . '/../certificates/certificate_' . $certificate_id . '.png';
    if (!file_exists($png_path)) {
        $logger("ERRO: [$log_prefix] PNG T101 não encontrado para conversão: $png_path");
        return false;
    }
    
    try {
        // Usar Imagick se disponível (melhor qualidade)
        if (extension_loaded('imagick')) {
            $imagick = new Imagick($png_path);
            
            // Configurações para melhor qualidade
            $imagick->setImageFormat('pdf');
            $imagick->setImageResolution(300, 300);
            $imagick->setImageCompressionQuality(95);
            
            // Ajustar para formato A4 paisagem se necessário
            $imagick->resizeImage(2480, 1754, Imagick::FILTER_LANCZOS, 1); // A4 landscape 300dpi
            
            $pdf_path = __DIR__ . '/../certificates/certificate_' . $certificate_id . '.pdf';
            $imagick->writeImage($pdf_path);
            $imagick->clear();
            
            $logger("SUCESSO: [$log_prefix] PDF gerado via Imagick MELHORADO do PNG T101: $pdf_path");
            return $pdf_path;
        }
        
        $logger("AVISO: [$log_prefix] Imagick não disponível - PDF não gerado");
        return false;
        
    } catch (Exception $e) {
        $logger("ERRO: [$log_prefix] Exceção no fallback PNG T101 -> PDF: " . $e->getMessage());
        return false;
    }
}
?>