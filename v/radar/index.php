<?php
// Arquivo: /radar/index.php
require_once __DIR__ . '/../../config/database.php'; // Ajuste o caminho para seu config

// Pega a URL. Ex: site.com/radar/?c=Ab3x ou site.com/radar/Ab3x (dependendo da config do servidor)
// Vamos assumir o padrão query param para garantir compatibilidade universal: site.com/radar/?id=Ab3x
// Se você usar URL amigável via .htaccess, o código muda pouco, mas vamos no seguro.

$slug = $_GET['id'] ?? '';

if ($slug) {
    try {
        // Busca o link original
        $stmt = $pdo->prepare("SELECT original_url, clicks FROM link_redirects WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($link) {
            // Contabiliza o clique (Métrica importante!)
            $update = $pdo->prepare("UPDATE link_redirects SET clicks = clicks + 1 WHERE slug = ?");
            $update->execute([$slug]);

            // Redireciona
            header("Location: " . $link['original_url']);
            exit;
        }
    } catch (PDOException $e) {
        // Silêncio em caso de erro
    }
}

// Se não achar o link, manda para a Home da T101
header("Location: /");
exit;