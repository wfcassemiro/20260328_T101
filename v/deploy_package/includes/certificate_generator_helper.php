<?php
// helpers/certificate_generator_helper.php

// Dependências: GD extension, qr_generator.php
// Este helper não deve iniciar sessões ou requerer database diretamente.
// Ele deve ser incluído por outros scripts (generate_certificate.php, view_certificate_files.php)

/**
 * Quebra um título em duas linhas de forma inteligente (não corta palavras)
 * 
 * @param string $title O título a ser quebrado
 * @param int $max_chars_per_line Máximo de caracteres por linha (aproximado)
 * @return array Array com [linha1, linha2] 
 */
function smartTitleWrap($title, $max_chars_per_line = 55) {
    $title = trim($title);
    $words = explode(' ', $title);
    
    if (count($words) <= 1) {
        // Se tem apenas uma palavra muito longa, cortar mesmo
        if (strlen($title) > $max_chars_per_line) {
            return [
                substr($title, 0, $max_chars_per_line),
                substr($title, $max_chars_per_line)
            ];
        }
        return [$title, ''];
    }
    
    $line1 = '';
    $line2 = '';
    $current_line_length = 0;
    $switching_to_line2 = false;
    
    foreach ($words as $word) {
        $word_length = strlen($word);
        
        // Se ainda não passou para linha 2
        if (!$switching_to_line2) {
            // Verifica se cabe na linha 1
            $new_length = $current_line_length + ($current_line_length > 0 ? 1 : 0) + $word_length;
            
            if ($new_length <= $max_chars_per_line) {
                // Cabe na linha 1
                $line1 .= ($line1 ? ' ' : '') . $word;
                $current_line_length = $new_length;
            } else {
                // Não cabe na linha 1, passar para linha 2
                $switching_to_line2 = true;
                $line2 = $word;
                $current_line_length = $word_length;
            }
        } else {
            // Já está na linha 2
            $line2 .= ' ' . $word;
        }
    }
    
    // Rebalancear se as linhas estão muito desproporcionais
    $line1_length = strlen($line1);
    $line2_length = strlen($line2);
    $length_difference = abs($line1_length - $line2_length);
    
    // Se a diferença for muito grande (mais de 30 chars), tentar rebalancear
    if ($length_difference > 30) {
        if ($line1_length > $line2_length) {
            // Linha 1 muito longa, mover palavras para linha 2
            $words_line1 = explode(' ', $line1);
            $keep_words = [];
            $move_words = [];
            
            foreach (array_reverse($words_line1) as $word) {
                $test_line1 = implode(' ', array_reverse($keep_words));
                if (strlen($test_line1) >= ($max_chars_per_line * 0.7)) {
                    $move_words[] = $word;
                } else {
                    $keep_words[] = $word;
                }
            }
            
            if (!empty($move_words)) {
                $line1 = implode(' ', array_reverse($keep_words));
                $line2 = implode(' ', array_reverse($move_words)) . ($line2 ? ' ' . $line2 : '');
            }
        } else {
            // Linha 2 muito longa, mover palavras para linha 1
            $words_line2 = explode(' ', $line2);
            $move_words = [];
            $remaining_words = [];
            
            foreach ($words_line2 as $word) {
                if (strlen($line1 . ' ' . implode(' ', $move_words) . ' ' . $word) <= $max_chars_per_line) {
                    $move_words[] = $word;
                } else {
                    $remaining_words[] = $word;
                }
            }
            
            if (!empty($move_words)) {
                $line1 .= ' ' . implode(' ', $move_words);
                $line2 = implode(' ', $remaining_words);
            }
        }
    }
    
    return [trim($line1), trim($line2)];
}

/**
 * Gera o arquivo PNG do certificado e o salva no diretório de certificados.
 *
 * @param string $certificate_id O ID UUID do certificado.
 * @param array $certificate_data Os dados do certificado (user_name, lecture_title, speaker_name, duration_minutes).
 * @param string $log_prefix Prefixo para as mensagens de log (ex: "GENERATE_CERT").
 * @param callable $logger Função de callback para logar mensagens (ex: writeToCustomLog).
 * @return string|false O caminho completo para o arquivo PNG gerado se sucesso, false se falha.
 */
function generateAndSaveCertificatePng(
    $certificate_id,
    $certificate_data,
    $log_prefix,
    $logger
) {
    $user_name = $certificate_data['user_name'] ?: 'Usuário Padrão';
    $lecture_title = $certificate_data['lecture_title'] ?: 'Título da Palestra Padrão';
    $speaker_name = $certificate_data['speaker_name'] ?: 'Palestrante Padrão';
    $duration_minutes = $certificate_data['duration_minutes'] ?? 0;

    // Calcular duração em horas
    if ($duration_minutes <= 0.5 * 60) {
        $duration_hours = '0.5';
    } elseif ($duration_minutes <= 1.0 * 60) {
        $duration_hours = '1.0';
    } elseif ($duration_minutes <= 1.5 * 60) {
        $duration_hours = '1.5';
    } else {
        $duration_hours = ceil($duration_minutes / 60 * 2) / 2;
    }
    $duration_text = $duration_hours . 'h';

    $logger("DEBUG: [$log_prefix] Iniciando geração de PNG para ID: $certificate_id");

    if (!extension_loaded('gd')) {
        $logger("ALERTA: [$log_prefix] Extensão GD não carregada. Não é possível gerar o certificado.");
        return false;
    }

    $template_path = __DIR__ . '/../images/template.png'; // Ajustado o caminho
    if (!file_exists($template_path)) {
        $logger("ERRO: [$log_prefix] Template PNG não encontrado em: " . $template_path);
        return false;
    }

    try {
        $image = imagecreatefrompng($template_path);
        if (!$image) {
            $logger("ERRO: [$log_prefix] Erro ao carregar imagem do template para geração.");
            return false;
        }

        if (function_exists('mb_convert_encoding')) {
            $user_name = mb_convert_encoding($user_name, 'UTF-8', 'auto');
            $lecture_title = mb_convert_encoding($lecture_title, 'UTF-8', 'auto');
            $speaker_name = mb_convert_encoding($speaker_name, 'UTF-8', 'auto');
            $duration_text = mb_convert_encoding($duration_text, 'UTF-8', 'auto');
            $logger("DEBUG: [$log_prefix] Conversão UTF-8 aplicada para geração.");
        }

        $black = imagecolorallocate($image, 0, 0, 0);
        $red = imagecolorallocate($image, 255, 0, 0);

        $width = imagesx($image);
        $height = imagesy($image);
        $logger("DEBUG: [$log_prefix] Dimensões do template: Largura=$width, Altura=$height");

        $font_paths = [
            __DIR__ . '/../fonts/DejaVuSans-Bold.ttf', // Ajustado o caminho
            __DIR__ . '/../fonts/DejaVuSans.ttf',      // Ajustado o caminho
            __DIR__ . '/../fonts/arialbd.ttf',         // Ajustado o caminho
            __DIR__ . '/../fonts/arial.ttf',
            // Adicione outros caminhos de sistema se souber deles na Hostinger
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans.ttf',
        ];

        $font_path = null;
        $font_path_bold = null;

        $logger("DEBUG: [$log_prefix] Procurando fontes TTF para geração...");
        foreach ($font_paths as $path) {
            if (file_exists($path)) {
                if (strpos(strtolower($path), 'bold') !== false || strpos(strtolower($path), 'arialbd') !== false) {
                    $font_path_bold = $path;
                    $logger("DEBUG: [$log_prefix] Fonte TTF Bold encontrada em: " . $path);
                }
                if ($font_path === null) {
                    $font_path = $path;
                    $logger("DEBUG: [$log_prefix] Fonte TTF padrão encontrada em: " . $path);
                }
                if ($font_path !== null && $font_path_bold !== null) {
                    break;
                }
            }
        }

        if ($font_path_bold === null) {
            $font_path_bold = $font_path;
            $logger("ALERTA: [$log_prefix] Fonte bold específica não encontrada. Usando fonte padrão para textos bold.");
        }
        
        if (!$font_path) {
             $logger("ERRO: [$log_prefix] Nenhuma fonte TTF válida encontrada após varrer caminhos.");
             return false;
        }

        $logger("DEBUG: [$log_prefix] Usando imagettftext para geração.");

        // Coordenadas e tamanhos baseados no template A4 (3509 x 2481 px)
        // 👤 Nome do Usuário
        $font_size_name = 80;
        $bbox_name = imagettfbbox($font_size_name, 0, $font_path_bold, $user_name);
        $text_width_name = $bbox_name[2] - $bbox_name[0];
        $x_name = ($width - $text_width_name) / 2;
        $y_name = 1100;
        imagettftext($image, $font_size_name, 0, $x_name, $y_name, $black, $font_path_bold, $user_name);
        $logger("DEBUG: [$log_prefix] Nome do usuário adicionado (X: " . $x_name . ", Y: " . $y_name . ").");

        // 📚 Título da Palestra (MELHORADO: quebra de linha para títulos longos + CENTRALIZADO)
        $logger("DEBUG: [$log_prefix] Processando título: '$lecture_title' (tamanho: " . strlen($lecture_title) . " caracteres)");
        
        if (strlen($lecture_title) > 55) {
            $logger("DEBUG: [$log_prefix] Título longo detectado - aplicando quebra de linha inteligente");
            
            // Fonte menor para títulos longos
            $font_size_title = 50;
            
            // Quebra inteligente em duas linhas (não corta palavras)
            $title_lines = smartTitleWrap($lecture_title, 55);
            $line1 = $title_lines[0];
            $line2 = $title_lines[1] ?? '';
            
            $logger("DEBUG: [$log_prefix] Linha 1: '$line1'");
            $logger("DEBUG: [$log_prefix] Linha 2: '$line2'");
            
            // Posição Y mais centrada (sem palestrante, podemos centralizar melhor)
            $y_title_start = 1350; // Mais centrado verticalmente
            $line_spacing = 60; // Espaçamento entre linhas
            
            // Renderizar linha 1
            $bbox_line1 = imagettfbbox($font_size_title, 0, $font_path_bold, $line1);
            $text_width_line1 = $bbox_line1[2] - $bbox_line1[0];
            $x_line1 = ($width - $text_width_line1) / 2;
            imagettftext($image, $font_size_title, 0, $x_line1, $y_title_start, $black, $font_path_bold, $line1);
            $logger("DEBUG: [$log_prefix] Linha 1 adicionada (X: " . $x_line1 . ", Y: " . $y_title_start . ")");
            
            // Renderizar linha 2 (se existir)
            if (!empty($line2)) {
                $bbox_line2 = imagettfbbox($font_size_title, 0, $font_path_bold, $line2);
                $text_width_line2 = $bbox_line2[2] - $bbox_line2[0];
                $x_line2 = ($width - $text_width_line2) / 2;
                $y_line2 = $y_title_start + $line_spacing;
                imagettftext($image, $font_size_title, 0, $x_line2, $y_line2, $black, $font_path_bold, $line2);
                $logger("DEBUG: [$log_prefix] Linha 2 adicionada (X: " . $x_line2 . ", Y: " . $y_line2 . ")");
            }
            
        } else {
            $logger("DEBUG: [$log_prefix] Título curto - usando layout centralizado");
            
            // Comportamento para títulos curtos - mais centralizado
            $font_size_title = 70;
            $display_title = $lecture_title;
            $bbox_title = imagettfbbox($font_size_title, 0, $font_path_bold, $display_title);
            $text_width_title = $bbox_title[2] - $bbox_title[0];
            $x_title = ($width - $text_width_title) / 2;
            $y_title = 1400; // Mais centrado verticalmente
            imagettftext($image, $font_size_title, 0, $x_title, $y_title, $black, $font_path_bold, $display_title);
            $logger("DEBUG: [$log_prefix] Título curto centralizado adicionado (X: " . $x_title . ", Y: " . $y_title . ")");
        }

        // ⏰ Duração da Palestra
        $font_size_duration = 70;
        $duration_text_display = $duration_text;
        $bbox_duration = imagettfbbox($font_size_duration, 0, $font_path_bold, $duration_text_display);
        $text_width_duration = $bbox_duration[2] - $bbox_duration[0];
        $x_duration = 2000;
        $y_duration = 1570;
        imagettftext($image, $font_size_duration, 0, $x_duration, $y_duration, $black, $font_path_bold, $duration_text_display);
        $logger("DEBUG: [$log_prefix] Duração da palestra adicionada (X: " . $x_duration . ", Y: " . $y_duration . ").");

        // 📅 Data de Emissão
        $font_size_date = 70;
        $date_text = date('d/m/Y');
        $x_date = 850;
        $y_date = 2330;
        imagettftext($image, $font_size_date, 0, $x_date, $y_date, $black, $font_path, $date_text);
        $logger("DEBUG: [$log_prefix] Data de emissão adicionada (X: " . $x_date . ", Y: " . $y_date . ").");

        // 🆔 ID do Certificado - ***** MODIFICAÇÃO AQUI: Mostrar ID completa *****
        $font_size_id = 30; 
        $id_text = 'ID: ' . $certificate_id; // Removido substr
        $x_id = 280;
        $y_id = $y_date + 100;
        imagettftext($image, $font_size_id, 0, $x_id, $y_id, $red, $font_path, $id_text);
        $logger("DEBUG: [$log_prefix] ID do certificado adicionado (X: " . $x_id . ", Y: " . $y_id . ").");

        // 📱 QR Code
        $qr_size = 300;
        $padding_qr_x = 220;
        $padding_qr_y = 50;
        $qr_x = $width - $qr_size - $padding_qr_x;
        $qr_y = $height - $qr_size - $padding_qr_y;

        if (file_exists(__DIR__ . '/../qr_generator.php')) {
            require_once __DIR__ . '/../qr_generator.php';
            $verification_url = generateVerificationURL($certificate_id);
            $qr_result = generateQRCode($verification_url, $qr_size);
            if ($qr_result['success']) {
                addQRCodeToImage($image, $qr_result['data'], $qr_x, $qr_y, $qr_size);
                $logger("DEBUG: [$log_prefix] QR Code adicionado à imagem durante a geração (X: " . $qr_x . ", Y: " . $qr_y . ", Tamanho: " . $qr_size . ").");
            } else {
                $logger("ERRO: [$log_prefix] Falha ao gerar QR Code durante a geração: " . $qr_result['error']);
            }
        } else {
            $logger("ALERTA: [$log_prefix] qr_generator.php não encontrado durante a geração.");
        }

        $cert_dir = __DIR__ . '/../certificates';
        if (!is_dir($cert_dir)) {
            mkdir($cert_dir, 0755, true);
            $logger("DEBUG: [$log_prefix] Diretório de certificados criado durante a geração: " . $cert_dir);
        }

        $filename = 'certificate_' . $certificate_id . '.png';
        $generated_path = $cert_dir . '/' . $filename;

        if (imagepng($image, $generated_path, 9)) {
            $logger("DEBUG: [$log_prefix] imagepng() retornou SUCESSO. Verificando se o arquivo gerado é um PNG válido.");
            $image_info = @getimagesize($generated_path);
            if ($image_info && $image_info['mime'] == 'image/png') {
                $logger("INFO: [$log_prefix] Certificado PNG gerado e salvo com sucesso em: " . $generated_path . ". É um PNG válido.");
                imagedestroy($image);
                return $generated_path;
            } else {
                $logger("ERRO: [$log_prefix] Certificado PNG gerado, mas NÃO é um PNG válido ou está corrompido: " . $generated_path . ". MIME: " . ($image_info['mime'] ?? 'N/A'));
                @unlink($generated_path);
                imagedestroy($image);
                return false;
            }
        } else {
            $logger("ERRO: [$log_prefix] Erro ao salvar o arquivo PNG do certificado gerado. Verifique permissões ou espaço em disco.");
            imagedestroy($image);
            return false;
        }

    } catch (Exception $e) {
        $logger("ERRO FATAL: [$log_prefix] Exceção na geração PNG: " . $e->getMessage());
        return false;
    }
}