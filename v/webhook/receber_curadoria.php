<?php
/**
 * receber_curadoria.php (ATUALIZADO)
 * * Webhook que recebe dados do n8n após a curadoria semanal.
 * Processa os artigos, encurta URLs e salva a newsletter com status 'draft'.
 */

require_once __DIR__ . '/../config/database.php';

date_default_timezone_set('America/Sao_Paulo');

// Recebe o input JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    die(json_encode(["error" => "Invalid JSON"]));
}

/**
 * Função para encurtar URLs no texto
 * URLs do domínio translators101.com não são encurtadas
 */
function shortenUrlsInText($text, $pdo) {
    $pattern = '/\b(?:https?:\/\/|www\.)\S+\b/i';
    
    return preg_replace_callback($pattern, function($matches) use ($pdo) {
        $url = $matches[0];
        
        // Não encurta URLs internas
        if (strpos($url, 'translators101.com') !== false) {
            return $url;
        }
        
        // Gera um slug único de 5 caracteres
        $slug = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 5);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO link_redirects (slug, original_url) VALUES (?, ?)");
            $stmt->execute([$slug, $url]);
            return "https://v.translators101.com/radar/?id=" . $slug;
        } catch (Exception $e) {
            return $url;
        }
    }, $text);
}

try {
    // 1. Higienização e Encurtamento de URLs
    $compiledNewsletter = shortenUrlsInText(str_replace(['*', '#', '`'], '', $data['compiled_newsletter'] ?? ''), $pdo);
    $newsletterContent = shortenUrlsInText(str_replace(['*', '#', '`'], '', $data['newsletter_content'] ?? $data['compiled_newsletter'] ?? ''), $pdo);
    $linkedinText = shortenUrlsInText(str_replace(['*', '#', '`'], '', $data['linkedin_content'] ?? ''), $pdo);
    
    $twitterText = str_replace(['*', '#', '`'], '', $data['twitter_content'] ?? '');
    $instaText = str_replace(['*', '#', '`'], '', $data['instagram_content'] ?? '');
    $imagePrompt = $data['image_prompt'] ?? '';
    
    $week = date('W');

    // Deserializa os arrays caso o n8n tenha enviado como string
    $articlesArray = [];
    if (isset($data['articles'])) {
        $articlesArray = is_string($data['articles']) ? json_decode($data['articles'], true) : $data['articles'];
    }

    $topicosArray = [];
    if (isset($data['topicos_chave'])) {
        $topicosArray = is_string($data['topicos_chave']) ? json_decode($data['topicos_chave'], true) : $data['topicos_chave'];
    }

    // 2. REGISTRO INDIVIDUAL DE ARTIGOS
    if (!empty($articlesArray) && is_array($articlesArray)) {
        $stmtArt = $pdo->prepare("
            INSERT INTO curation_articles (title, url, summary, week_number, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        foreach ($articlesArray as $article) {
            $source = $article['source'] ?? 'Fonte';
            $title = $article['title'] ?? 'Sem Título';
            
            if (stripos($source, 'Slator') !== false) {
                $title = "[PAYWALL] [{$source}] " . $title;
            } else {
                $title = "[{$source}] " . $title;
            }
            
            try {
                $stmtArt->execute([
                    $title,
                    $article['url'] ?? '',
                    $article['summary'] ?? '',
                    $week
                ]);
            } catch (Exception $e) {
                error_log("[Radar] Erro ao inserir artigo: " . $e->getMessage());
            }
        }
    }

    // 3. REGISTRO DE TENDÊNCIAS
    if (!empty($topicosArray) && is_array($topicosArray)) {
        $stmtTrend = $pdo->prepare("
            INSERT INTO market_trends (topic_name) 
            VALUES (?) 
            ON DUPLICATE KEY UPDATE topic_name = topic_name
        ");
        
        foreach ($topicosArray as $topico) {
            $topico_limpo = trim($topico);
            if (!empty($topico_limpo)) {
                try {
                    $stmtTrend->execute([$topico_limpo]);
                } catch (Exception $e) {
                    error_log("[Radar] Erro ao inserir tendência: " . $e->getMessage());
                }
            }
        }
    }

    // 4. Salva Newsletter Semanal com STATUS DRAFT e todos os novos campos
    $newsletterId = null;
    $articlesJson = !empty($articlesArray) ? json_encode($articlesArray, JSON_UNESCAPED_UNICODE) : null;
    $topicosJson = !empty($topicosArray) ? json_encode($topicosArray, JSON_UNESCAPED_UNICODE) : null;
    
    if (!empty($compiledNewsletter) || !empty($newsletterContent)) {
        $sql = "INSERT INTO weekly_newsletters 
                (week_number, compiled_newsletter, newsletter_content, linkedin_content, twitter_content, 
                 instagram_content, image_prompt, topicos_chave, articles, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW())";
        
        $stmtNews = $pdo->prepare($sql);
        $stmtNews->execute([
            $week,
            $compiledNewsletter,
            $newsletterContent,
            $linkedinText,
            $twitterText,
            $instaText,
            $imagePrompt,
            $topicosJson,
            $articlesJson
        ]);
        
        $newsletterId = $pdo->lastInsertId();
    }

    // Retorna sucesso
    header('Content-Type: application/json');
    echo json_encode([
        "status" => "success", 
        "id" => $newsletterId,
        "message" => "Newsletter salva com status draft. Aguardando aprovação."
    ]);

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["error" => $e->getMessage()]);
}