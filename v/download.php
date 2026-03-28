<?php
session_start();
require_once 'config/database.php';

// Função para exibir erro com estilo Apple Vision
function showError($title, $message, $backUrl = '/glossarios.php') {
    $page_title = $title;
    include __DIR__ . '/vision/includes/head.php';
    include __DIR__ . '/vision/includes/header.php';
    include __DIR__ . '/vision/includes/sidebar.php';
    ?>
    <div class="main-content">
        <div class="glass-hero">
            <div class="hero-content">
                <h1><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($title); ?></h1>
                <p><?php echo htmlspecialchars($message); ?></p>
                <div class="flex gap-2">
                    <a href="<?php echo htmlspecialchars($backUrl); ?>" class="cta-btn">
                        <i class="fas fa-arrow-left"></i> Voltar aos Glossários
                    </a>
                </div>
            </div>
        </div>

        <div class="video-card">
            <h2><i class="fas fa-info-circle"></i> O que aconteceu?</h2>
            <div class="alert-error">
                <i class="fas fa-times-circle"></i>
                <strong>Erro:</strong> <?php echo htmlspecialchars($message); ?>
            </div>
            
            <div class="dashboard-sections">
                <div>
                    <h3><i class="fas fa-question-circle"></i> Possíveis Causas</h3>
                    <ul class="list-disc ml-6">
                        <li>O arquivo foi removido do servidor</li>
                        <li>Link expirado ou inválido</li>
                        <li>Problemas temporários no sistema</li>
                        <li>Arquivo em manutenção</li>
                    </ul>
                </div>
                <div>
                    <h3><i class="fas fa-tools"></i> O que fazer?</h3>
                    <ul class="list-disc ml-6">
                        <li>Tente novamente em alguns minutos</li>
                        <li>Verifique se o link está correto</li>
                        <li>Entre em contato com o suporte</li>
                        <li>Procure por uma versão alternativa</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/vision/includes/footer.php';
    exit;
}

// Redireciona se não estiver logado
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Recebe o ID do arquivo
$file_id = $_GET['id'] ?? null;

if (empty($file_id)) {
    showError('ID Inválido', 'Nenhum arquivo foi especificado para download.');
}

try {
    // Busca o arquivo no banco de dados pelo ID
    $stmt = $pdo->prepare("SELECT * FROM glossary_files WHERE id = ? AND is_active = 1");
    $stmt->execute([$file_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        showError('Arquivo Não Encontrado', 'O arquivo solicitado não existe ou não está mais disponível.');
    }

    // Múltiplas tentativas de localização do arquivo
    $possiblePaths = [
        // Caminho direto baseado no download_url
        __DIR__ . $file['download_url'],
        // Caminho com pasta pai (caso esteja um nível acima)
        __DIR__ . '/..' . $file['download_url'],
        // Caminho absoluto se download_url já for completo
        $file['download_url'],
        // Caminho alternativo na pasta uploads
        __DIR__ . '/uploads/glossarios/' . basename($file['download_url']),
        // Caminho alternativo um nível acima
        __DIR__ . '/../uploads/glossarios/' . basename($file['download_url'])
    ];

    $server_file_path = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path) && is_readable($path)) {
            $server_file_path = $path;
            break;
        }
    }

    // Verifica se o arquivo foi encontrado
    if (!$server_file_path) {
        // Log detalhado para debug
        error_log("Arquivo não encontrado. Tentativas:");
        foreach ($possiblePaths as $i => $path) {
            error_log("  Tentativa " . ($i + 1) . ": " . $path . " - " . (file_exists($path) ? 'EXISTS' : 'NOT FOUND'));
        }
        
        showError(
            'Arquivo Não Encontrado no Servidor', 
            'O arquivo existe no sistema mas não foi localizado no servidor. Entre em contato com o administrador.'
        );
    }

    // Registra o download para estatísticas
    try {
        $stmt = $pdo->prepare("UPDATE glossary_files SET download_count = COALESCE(download_count, 0) + 1 WHERE id = ?");
        $stmt->execute([$file_id]);
    } catch (Exception $e) {
        // Não interrompe o download se falhar ao atualizar contador
        error_log("Erro ao atualizar contador de downloads: " . $e->getMessage());
    }

    // Limpa qualquer saída anterior ANTES dos headers
    if (ob_get_length()) {
        ob_end_clean();
    }

    // Define o nome do arquivo com codificação segura para acentos
    $extension = strtolower($file['file_type'] ?? pathinfo($server_file_path, PATHINFO_EXTENSION));
    $safe_filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $file['title']) . '.' . $extension;

    // Determina o Content-Type baseado na extensão
    $contentTypes = [
        'pdf' => 'application/pdf',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'csv' => 'text/csv',
        'txt' => 'text/plain'
    ];
    $contentType = $contentTypes[$extension] ?? 'application/octet-stream';

    // Headers para download do arquivo
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $contentType);
    header('Content-Transfer-Encoding: binary');
    header('Content-Disposition: attachment; filename="' . $safe_filename . '"; filename*=UTF-8\'\'' . rawurlencode($safe_filename));
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($server_file_path));

    // Envia o arquivo
    readfile($server_file_path);
    exit;

} catch (Exception $e) {
    error_log("Erro no download: " . $e->getMessage());
    showError(
        'Erro no Download', 
        'Ocorreu um erro inesperado ao processar sua solicitação: ' . $e->getMessage()
    );
}
?>