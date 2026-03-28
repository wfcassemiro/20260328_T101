<?php
/**
 * qr_generator.php
 * Funções para gerar URLs de verificação e QR Codes.
 */

// Função auxiliar para escrever no arquivo de log customizado.
// Verifica se a função já existe antes de declará-la para evitar erros de redeclaração.
if (!function_exists('writeToCustomLog')) {
    function writeToCustomLog($message) {
        $log_file = __DIR__ . '/certificate_errors.log'; 
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($log_file, "[$timestamp] [QR_GEN] $message\n", FILE_APPEND);
    }
}

// Definição da constante global para o caminho do script de verificação.
// ATENÇÃO: Se o seu verificar_certificado.php NÃO estiver na raiz do seu domínio,
// você precisará ajustar este caminho. Por exemplo, se estiver em 'seusite.com/validacao/verificar_certificado.php',
// mude para '/validacao/verificar_certificado.php'.
if (!defined('BASE_VERIFICATION_PATH')) {
    define('BASE_VERIFICATION_PATH', '/verificar_certificado.php'); 
}

/**
 * Gera a URL para verificação do certificado.
 * Esta URL será incorporada ao QR Code.
 * @param string $certificate_id O ID único do certificado.
 * @return string A URL completa para o script de verificação.
 */
function generateVerificationURL($certificate_id) {
    // Determine o protocolo (http ou https)
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    // Obtenha o host (seu domínio)
    $host = $_SERVER['HTTP_HOST'];
    
    $verification_url = $protocol . "://" . $host . BASE_VERIFICATION_PATH . '?id=' . urlencode($certificate_id);

    writeToCustomLog("DEBUG: URL de verificação gerada: " . $verification_url);
    return $verification_url;
}

/**
 * Gera um QR Code como string de dados brutos de imagem (PNG).
 * Utiliza a API pública qr-server.com.
 * @param string $data Os dados a serem codificados no QR Code (geralmente uma URL).
 * @param int $size O tamanho do QR Code em pixels (largura e altura).
 * @return array Um array associativo com 'success' (bool) e 'data' (dados brutos da imagem) ou 'error'.
 */
function generateQRCode($data, $size = 150) {
    $api_url = "https://api.qrserver.com/v1/create-qr-code/";
    $params = http_build_query([
        'size' => $size . 'x' . $size,
        'data' => $data,
        'format' => 'png',
        'ecc' => 'M' // Error Correction Capability (L, M, Q, H)
    ]);
    
    $qr_url = $api_url . '?' . $params;
    
    // Baixar QR code usando file_get_contents com contexto para timeout e user_agent
    $context = stream_context_create([
        'http' => [
            'timeout' => 10, // Timeout de 10 segundos
            'user_agent' => 'Mozilla/5.0 (compatible; Translators101CertificateGenerator/1.0)'
        ]
    ]);
    
    $qr_image_data = @file_get_contents($qr_url, false, $context);
    
    if ($qr_image_data === false) {
        writeToCustomLog("ERRO: Falha ao gerar QR Code da API: " . $qr_url);
        return ['success' => false, 'error' => 'Falha ao buscar imagem do QR Code da API externa.'];
    }

    // Retorna os dados brutos da imagem (não base64)
    return ['success' => true, 'data' => $qr_image_data];
}

/**
 * Adiciona um QR Code (dados brutos de imagem) a uma imagem GD existente.
 * @param resource $base_image A imagem GD de base.
 * @param string $qr_image_data Os dados brutos da imagem do QR Code.
 * @param int $x Posição X para colar o QR Code.
 * @param int $y Posição Y para colar o QR Code.
 * @param int $size O tamanho desejado do QR Code na imagem base (largura/altura).
 */
function addQRCodeToImage($base_image, $qr_image_data, $x, $y, $size) {
    $qr_image = @imagecreatefromstring($qr_image_data);
    if ($qr_image) {
        // Redimensiona e copia o QR Code para a imagem base
        imagecopyresampled($base_image, $qr_image, $x, $y, 0, 0, $size, $size, imagesx($qr_image), imagesy($qr_image));
        imagedestroy($qr_image);
        writeToCustomLog("DEBUG: QR Code colado na imagem base.");
        return true;
    } else {
        writeToCustomLog("ERRO: Falha ao criar imagem GD do QR Code a partir dos dados brutos.");
        return false;
    }
}

// --- Funções de Teste (mantidas do seu arquivo original para referência) ---

/**
 * Teste de geração de QR Code
 */
if (isset($_GET['test_qr'])) {
    header('Content-Type: image/png');
    
    $test_data = $_GET['data'] ?? 'https://translators101.com/verificar_certificado.php?id=test-123';
    $result = generateQRCode($test_data, 200);
    
    if ($result['success']) {
        echo $result['data'];
    } else {
        // Criar imagem de erro
        $error_img = imagecreate(200, 200);
        $white = imagecolorallocate($error_img, 255, 255, 255);
        $red = imagecolorallocate($error_img, 255, 0, 0);
        imagefill($error_img, 0, 0, $white);
        imagestring($error_img, 3, 50, 90, 'QR Error', $red);
        imagepng($error_img);
        imagedestroy($error_img);
    }
    exit;
}

/**
 * Exemplo de uso
 */
if (isset($_GET['example'])) {
    echo "<h1>🔗 Teste de QR Code</h1>";
    echo "<h2>QR Code de teste:</h2>";
    echo "<img src='?test_qr=1&data=" . urlencode('https://translators101.com/verificar_certificado.php?id=vision-php-upgrade') . "' alt='QR Code'>";
    
    echo "<h2>URLs de teste:</h2>";
    echo "<ul>";
    echo "<li><a href='?test_qr=1&data=https://translators101.com'>QR simples</a></li>";
    echo "<li><a href='?test_qr=1&data=" . urlencode('https://translators101.com/verificar_certificado.php?id=test-123') . "'>QR verificação</a></li>";
    echo "</ul>";
    
    echo "<h2>Código de exemplo:</h2>";
    echo "<pre>";
    echo htmlspecialchars('$qr_result = generateQRCode($verification_url, 100);
if ($qr_result["success"]) {
    addQRCodeToImage($certificate_image, $qr_result["data"], $x, $y, 100);
}');
    echo "</pre>";
    exit;
}
?>
