<?php
/**
 * RSS Feed Fetcher - Translators 101 Dashboard
 * 
 * Este script busca notícias dos RSS feeds de Slator, Nimdzi e ProZ.com
 * e salva no arquivo news.json para o dashboard.
 * 
 * COMO USAR:
 * 1. Coloque em: v.translators101.com/api/fetch_news.php
 * 2. Configure um CRON job para executar a cada hora:
 *    0 * * * * php /caminho/para/api/fetch_news.php
 * 
 * Ou execute manualmente acessando:
 *    https://v.translators101.com/api/fetch_news.php?run=1
 */

// Configuração dos feeds RSS
$RSS_FEEDS = [
    'slator' => [
        'name' => 'Slator',
        'url' => 'https://slator.com/feed/',
        'limit' => 5
    ],
    'nimdzi' => [
        'name' => 'Nimdzi',
        'url' => 'https://www.nimdzi.com/feed/',
        'limit' => 5
    ],
    'proz' => [
        'name' => 'ProZ.com',
        'url' => 'https://go.proz.com/blog/rss.xml',
        'limit' => 5
    ]
];

$OUTPUT_FILE = __DIR__ . '/news.json';

/**
 * Busca e parseia um feed RSS
 */
function fetchRSSFeed($url, $source, $limit = 5) {
    $items = [];
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Translators101 Dashboard/1.0'
        ]
    ]);
    
    $content = @file_get_contents($url, false, $context);
    
    if ($content === false) {
        error_log("Failed to fetch RSS from: $url");
        return $items;
    }
    
    // Suprimir warnings de XML mal formatado
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($content);
    
    if ($xml === false) {
        error_log("Failed to parse RSS from: $url");
        return $items;
    }
    
    $count = 0;
    foreach ($xml->channel->item as $item) {
        if ($count >= $limit) break;
        
        $items[] = [
            'id' => 'news-' . $source . '-' . md5((string)$item->link),
            'source' => $source,
            'title' => trim((string)$item->title),
            'link' => trim((string)$item->link),
            'description' => trim(strip_tags((string)$item->description)),
            'pubDate' => date('c', strtotime((string)$item->pubDate))
        ];
        
        $count++;
    }
    
    return $items;
}

/**
 * Busca todas as notícias e salva no arquivo JSON
 */
function updateNewsFile($feeds, $outputFile) {
    $allNews = [];
    
    foreach ($feeds as $source => $config) {
        echo "Fetching {$config['name']}... ";
        $items = fetchRSSFeed($config['url'], $source, $config['limit']);
        echo count($items) . " items\n";
        $allNews = array_merge($allNews, $items);
    }
    
    // Ordenar por data (mais recentes primeiro)
    usort($allNews, function($a, $b) {
        return strtotime($b['pubDate']) - strtotime($a['pubDate']);
    });
    
    // Salvar arquivo JSON
    $data = [
        'news' => $allNews,
        'lastUpdated' => date('c'),
        'sources' => array_keys($feeds)
    ];
    
    $result = file_put_contents(
        $outputFile, 
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    
    if ($result === false) {
        echo "ERROR: Failed to write to $outputFile\n";
        return false;
    }
    
    echo "\nSaved " . count($allNews) . " news items to $outputFile\n";
    return true;
}

// Executar se chamado via CLI ou com parâmetro ?run=1
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    header('Content-Type: text/plain');
    echo "=== Translators 101 - RSS Feed Fetcher ===\n\n";
    
    updateNewsFile($RSS_FEEDS, $OUTPUT_FILE);
    
    echo "\nDone!\n";
} else {
    // Retornar JSON info se acessado diretamente
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    
    echo json_encode([
        'service' => 'RSS Feed Fetcher',
        'usage' => 'Add ?run=1 to fetch news or setup as CRON job',
        'feeds' => array_keys($RSS_FEEDS),
        'output' => 'news.json'
    ], JSON_PRETTY_PRINT);
}
