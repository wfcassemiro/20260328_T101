<?php
@set_time_limit(300); // 5 minutos max por execução
@ini_set('implicit_flush', true);
@ob_implicit_flush(true);
while (ob_get_level()) ob_end_flush();

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    exit("Acesso negado.");
}

// Configurações
define('HOTMART_BASIC_AUTH', 'Basic N2UzZDM0MmQtYWY0Zi00MTkwLTk1OWMtNmE5NzU0NmYxNDM3OjZmNjI1NzZmLTQzMzUtNDBkMC04N2FhLThhNThmMDlkZjdmZA==');
define('HOTMART_ACCESS_TOKEN_URL', 'https://api-sec-vlc.hotmart.com/security/oauth/token');
define('HOTMART_API_BASE_URL', 'https://developers.hotmart.com');
$hotmart_club_subdomain = 'assinaturapremiumplustranslato';

// Limite de usuários por execução
$USERS_PER_BATCH = 30;

// Reset manual (adicione ?reset=1 na URL para recomeçar do zero)
if (isset($_GET['reset']) && $_GET['reset'] == '1') {
    $pdo->exec("DELETE FROM hotmart_sync_state WHERE state_key = 'user_page_token'");
    $pdo->exec("TRUNCATE TABLE hotmart_lessons_cache");
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

function output($msg) {
    echo $msg;
    if (ob_get_level() > 0) ob_flush();
    flush();
}

function get_hotmart_access_token() {
    $ch = curl_init(HOTMART_ACCESS_TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'client_credentials']),
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . HOTMART_BASIC_AUTH,
            'Content-Type: application/x-www-form-urlencoded'
        ],
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) return null;
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function call_hotmart_api($url, $access_token) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) return null;
    return json_decode($response, true);
}

function norm($s) {
    $s = trim((string)$s);
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/\s+/u', ' ', $s);
    return $s;
}

function get_state($pdo, $key) {
    $stmt = $pdo->prepare("SELECT state_value FROM hotmart_sync_state WHERE state_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['state_value'] : null;
}

function set_state($pdo, $key, $value) {
    $stmt = $pdo->prepare("
        INSERT INTO hotmart_sync_state (state_key, state_value, updated_at) 
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE state_value = ?, updated_at = NOW()
    ");
    $stmt->execute([$key, $value, $value]);
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotmart Page ID Mapper v5</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #252526;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        h1 {
            color: #4ec9b0;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 10px;
        }
        .log {
            background: #1e1e1e;
            padding: 15px;
            border-radius: 4px;
            font-size: 13px;
            line-height: 1.6;
            max-height: 600px;
            overflow-y: auto;
            margin: 20px 0;
        }
        .success { color: #4ec9b0; }
        .warning { color: #dcdcaa; }
        .error { color: #f48771; }
        .info { color: #9cdcfe; }
        .progress { color: #c586c0; }
        .separator { color: #6a6a6a; margin: 10px 0; display: block; }
        .btn {
            background: #0e639c;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            margin-top: 10px;
            margin-right: 10px;
        }
        .btn:hover { background: #1177bb; }
        .btn:disabled {
            background: #3e3e3e;
            color: #6a6a6a;
            cursor: not-allowed;
        }
        .btn-reset {
            background: #d9534f;
        }
        .btn-reset:hover {
            background: #c9302c;
        }
        .stats {
            background: #2d2d30;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        .stats-item {
            display: inline-block;
            margin-right: 20px;
            padding: 5px 10px;
            background: #1e1e1e;
            border-radius: 3px;
            margin-bottom: 8px;
        }
        .stats-label { color: #858585; }
        .stats-value { color: #4ec9b0; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Hotmart Page ID Mapper v5</h1>
        <p class="info">Modo retomável com paginação persistente</p>
        
        <div class="log">
<?php

try {
    output("<span class='separator'>═══════════════════════════════════════════════════════════</span>\n");
    output("<span class='info'>Iniciando mapeamento...</span>\n");
    output("<span class='separator'>═══════════════════════════════════════════════════════════</span>\n\n");

    // 1) Garantir estrutura
    output("<span class='progress'>→ Verificando estrutura das tabelas...</span>\n");
    
    // Tabela de mapeamento
    try {
        $pdo->exec("
            ALTER TABLE hotmart_lecture_mapping
              ADD COLUMN hotmart_page_id VARCHAR(100) NULL,
              ADD UNIQUE KEY uniq_hotmart_page_id (hotmart_page_id)
        ");
        output("<span class='success'>  ✓ Coluna hotmart_page_id criada</span>\n");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false || 
            strpos($e->getMessage(), 'exists') !== false || 
            $e->getCode() === '42S21' || 
            $e->getCode() === '42000') {
            output("<span class='success'>  ✓ Coluna hotmart_page_id já existe</span>\n");
        } else {
            throw $e;
        }
    }

    // Tabela de cache de lessons
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS hotmart_lessons_cache (
                page_id VARCHAR(100) PRIMARY KEY,
                page_name VARCHAR(500),
                last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        output("<span class='success'>  ✓ Tabela de cache verificada</span>\n");
    } catch (PDOException $e) {
        output("<span class='warning'>  ⚠ Cache: " . $e->getMessage() . "</span>\n");
    }

    // Tabela de estado (para paginação)
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS hotmart_sync_state (
                state_key VARCHAR(100) PRIMARY KEY,
                state_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        output("<span class='success'>  ✓ Tabela de estado verificada</span>\n");
    } catch (PDOException $e) {
        output("<span class='warning'>  ⚠ State: " . $e->getMessage() . "</span>\n");
    }

    // 2) Carregar mapeamento local
    output("\n<span class='progress'>→ Carregando mapeamento local...</span>\n");
    $stmt = $pdo->query("SELECT lecture_id, hotmart_title, hotmart_page_id FROM hotmart_lecture_mapping");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $title_to_row = [];
    $already_with_page = 0;
    foreach ($rows as $r) {
        if (!empty($r['hotmart_page_id'])) $already_with_page++;
        $title_to_row[norm($r['hotmart_title'])] = $r;
    }
    output("<span class='info'>  Total de entradas: " . count($rows) . "</span>\n");
    output("<span class='info'>  Já com page_id: {$already_with_page}</span>\n");

    // Verificar cache atual
    $cache_count_stmt = $pdo->query("SELECT COUNT(*) as cnt FROM hotmart_lessons_cache");
    $cache_count = $cache_count_stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    output("<span class='info'>  Lessons no cache: {$cache_count}</span>\n");

    // 3) Obter token
    output("\n<span class='separator'>────────────────────────────────────────────────────────────</span>\n");
    output("<span class='progress'>→ Obtendo token OAuth...</span>\n");
    $access_token = get_hotmart_access_token();
    if (!$access_token) {
        throw new Exception("Falha na autenticação com Hotmart");
    }
    output("<span class='success'>  ✓ Token obtido com sucesso</span>\n");

    // 4) Recuperar estado da paginação
    $saved_page_token = get_state($pdo, 'user_page_token');
    
    if ($saved_page_token) {
        output("\n<span class='info'>📍 Continuando de onde parou (token salvo encontrado)</span>\n");
    } else {
        output("\n<span class='info'>🆕 Iniciando do começo (primeira execução)</span>\n");
    }

    // 5) Buscar usuários (com paginação)
    output("\n<span class='separator'>────────────────────────────────────────────────────────────</span>\n");
    output("<span class='progress'>→ Buscando usuários da Hotmart (lote de {$USERS_PER_BATCH})...</span>\n");
    
    $params = ['subdomain' => $hotmart_club_subdomain, 'max_results' => $USERS_PER_BATCH];
    if ($saved_page_token) {
        $params['page_token'] = $saved_page_token;
    }
    
    $url = HOTMART_API_BASE_URL . '/club/api/v1/users?' . http_build_query($params);
    $data = call_hotmart_api($url, $access_token);
    
    if (!$data || empty($data['items'])) {
        // Se não há mais usuários, limpar o token
        set_state($pdo, 'user_page_token', '');
        output("<span class='warning'>⚠ Nenhum usuário encontrado nesta página.</span>\n");
        output("<span class='info'>→ Provavelmente todos os usuários já foram processados.</span>\n");
        $users = [];
        $has_more_pages = false;
    } else {
        $users = $data['items'];
        $total_users = count($users);
        output("<span class='success'>  ✓ Usuários neste lote: {$total_users}</span>\n");
        
        // Verificar se há próxima página
        $next_page_token = $data['page_info']['next_page_token'] ?? null;
        $has_more_pages = !empty($next_page_token);
        
        if ($has_more_pages) {
            output("<span class='info'>  → Há mais páginas de usuários disponíveis</span>\n");
            // Salvar token para próxima execução
            set_state($pdo, 'user_page_token', $next_page_token);
        } else {
            output("<span class='info'>  → Esta é a última página de usuários</span>\n");
            // Limpar token (chegou ao fim)
            set_state($pdo, 'user_page_token', '');
        }
    }

    // 6) Processar lessons de cada usuário
    if (!empty($users)) {
        output("\n<span class='separator'>────────────────────────────────────────────────────────────</span>\n");
        output("<span class='progress'>→ Coletando lessons dos usuários...</span>\n\n");
        
        $lessons_collected = 0;
        $users_processed = 0;
        $start_time = time();

        foreach ($users as $user) {
            if (!isset($user['user_id'])) continue;
            
            $user_id = $user['user_id'];
            $user_name = $user['name'] ?? 'N/A';
            
            output("<span class='info'>  [{$users_processed}/{$total_users}] " . htmlspecialchars(substr($user_name, 0, 35)) . "...</span>\n");
            
            $url = HOTMART_API_BASE_URL . "/club/api/v1/users/{$user_id}/lessons?subdomain={$hotmart_club_subdomain}";
            $lessons_data = call_hotmart_api($url, $access_token);
            
            if ($lessons_data && isset($lessons_data['lessons'])) {
                $count_this_user = 0;
                foreach ($lessons_data['lessons'] as $lesson) {
                    $page_id = (string)($lesson['page_id'] ?? '');
                    $page_name = (string)($lesson['page_name'] ?? '');
                    
                    if ($page_id && $page_name) {
                        try {
                            $ins = $pdo->prepare("INSERT IGNORE INTO hotmart_lessons_cache (page_id, page_name) VALUES (?, ?)");
                            $ins->execute([$page_id, $page_name]);
                            if ($ins->rowCount() > 0) {
                                $lessons_collected++;
                                $count_this_user++;
                            }
                        } catch (PDOException $e) {
                            // Ignora erros de duplicata
                        }
                    }
                }
                if ($count_this_user > 0) {
                    output("<span class='success'>    ✓ +{$count_this_user} lessons novas</span>\n");
                } else {
                    output("<span class='info'>    • Sem lessons novas</span>\n");
                }
            } else {
                output("<span class='warning'>    ⚠ Sem lessons ou erro na API</span>\n");
            }
            
            $users_processed++;
            
            // Verificar tempo decorrido
            $elapsed = time() - $start_time;
            if ($elapsed > 240) { // 4 minutos
                output("\n<span class='warning'>⏱ Tempo limite se aproximando. Parando aqui para evitar timeout.</span>\n");
                output("<span class='info'>→ Execute novamente para processar mais usuários.</span>\n");
                break;
            }
            
            usleep(100000); // 100ms entre chamadas
        }
        
        output("\n<span class='success'>✓ Coletadas {$lessons_collected} lessons novas neste lote</span>\n");
    }

    // 7) Fazer mapeamento usando o cache
    output("\n<span class='separator'>────────────────────────────────────────────────────────────</span>\n");
    output("<span class='progress'>→ Mapeando lessons do cache para o banco...</span>\n");
    
    $cache_stmt = $pdo->query("SELECT page_id, page_name FROM hotmart_lessons_cache");
    $cached_lessons = $cache_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    output("<span class='info'>  Total de lessons únicas no cache: " . count($cached_lessons) . "</span>\n\n");
    
    $updates = 0;
    $conflicts = 0;
    $unmatched = [];
    $matched_preview = [];

    $pdo->beginTransaction();
    
    foreach ($cached_lessons as $lesson) {
        $page_id = $lesson['page_id'];
        $page_name = $lesson['page_name'];
        
        $nt = norm($page_name);

        if (!isset($title_to_row[$nt])) {
            $unmatched[] = [
                'title' => $page_name,
                'page_id' => $page_id
            ];
            continue;
        }

        $row = $title_to_row[$nt];

        if (!empty($row['hotmart_page_id'])) {
            if ($row['hotmart_page_id'] !== $page_id) {
                $conflicts++;
            }
            continue;
        }

        $upd = $pdo->prepare("UPDATE hotmart_lecture_mapping SET hotmart_page_id = ? WHERE lecture_id = ?");
        $upd->execute([$page_id, $row['lecture_id']]);
        if ($upd->rowCount() > 0) {
            $updates++;
            
            if (count($matched_preview) < 5) {
                $matched_preview[] = "  <span class='success'>✓ '" . htmlspecialchars(substr($page_name, 0, 40)) . "...' → {$page_id}</span>";
            }
        }
    }
    
    $pdo->commit();

    // Atualizar contagem final
    $stmt_final = $pdo->query("SELECT COUNT(*) as cnt FROM hotmart_lecture_mapping WHERE hotmart_page_id IS NOT NULL AND hotmart_page_id != ''");
    $total_mapped = $stmt_final->fetch(PDO::FETCH_ASSOC)['cnt'];

    // Resumo final
    output("\n<span class='separator'>═══════════════════════════════════════════════════════════</span>\n");
    output("<span class='success'><strong>RESUMO FINAL</strong></span>\n");
    output("<span class='separator'>═══════════════════════════════════════════════════════════</span>\n");
    
    output("</div>\n"); // Fecha .log
    
    // Stats box
    echo "<div class='stats'>";
    echo "<div class='stats-item'><span class='stats-label'>Usuários processados (este lote):</span> <span class='stats-value'>" . (isset($users_processed) ? $users_processed : 0) . "</span></div>";
    echo "<div class='stats-item'><span class='stats-label'>Lessons no cache (total):</span> <span class='stats-value'>" . count($cached_lessons) . "</span></div>";
    echo "<div class='stats-item'><span class='stats-label'>Atualizações (este lote):</span> <span class='stats-value'>{$updates}</span></div>";
    echo "<div class='stats-item'><span class='stats-label'>Total mapeado:</span> <span class='stats-value'>{$total_mapped} / " . count($rows) . "</span></div>";
    echo "<div class='stats-item'><span class='stats-label'>Conflitos:</span> <span class='stats-value'>{$conflicts}</span></div>";
    echo "<div class='stats-item'><span class='stats-label'>Sem match:</span> <span class='stats-value'>" . count($unmatched) . "</span></div>";
    echo "</div>";
    
    echo "<div class='log'>";

    if (!empty($matched_preview)) {
        output("\n<span class='info'>Exemplos de matches bem-sucedidos:</span>\n");
        foreach ($matched_preview as $preview) {
            output($preview . "\n");
        }
    }

    if (!empty($unmatched) && count($unmatched) <= 20) {
        output("\n<span class='separator'>────────────────────────────────────────────────────────────</span>\n");
        output("<span class='warning'>LESSONS SEM MATCH (todas):</span>\n");
        output("<span class='separator'>────────────────────────────────────────────────────────────</span>\n");
        foreach ($unmatched as $u) {
            output("<span class='warning'>• " . htmlspecialchars($u['title']) . "</span>\n");
            output("<span class='info'>  page_id: {$u['page_id']}</span>\n");
        }
    } elseif (!empty($unmatched)) {
        output("\n<span class='separator'>────────────────────────────────────────────────────────────</span>\n");
        output("<span class='warning'>LESSONS SEM MATCH (primeiras 15):</span>\n");
        output("<span class='separator'>────────────────────────────────────────────────────────────</span>\n");
        $shown = 0;
        foreach ($unmatched as $u) {
            output("<span class='warning'>• " . htmlspecialchars($u['title']) . "</span>\n");
            output("<span class='info'>  page_id: {$u['page_id']}</span>\n");
            if (++$shown >= 15) break;
        }
        output("\n<span class='info'>... e mais " . (count($unmatched) - 15) . " sem match.</span>\n");
    }

    output("\n<span class='separator'>═══════════════════════════════════════════════════════════</span>\n");
    if ($updates > 0) {
        output("<span class='success'>✓ SUCESSO! {$updates} page_ids mapeados neste lote.</span>\n");
    }
    
    if ($total_mapped >= count($rows)) {
        output("<span class='success'>🎉 TODOS OS LECTURES FORAM MAPEADOS!</span>\n");
        output("<span class='info'>   Agora você pode rodar a sincronização de certificados.</span>\n");
    } elseif ($total_mapped > 0) {
        output("<span class='info'>📊 Progresso: {$total_mapped} de " . count($rows) . " mapeados (" . round(($total_mapped / count($rows)) * 100, 1) . "%)</span>\n");
    }
    
    echo "</div>"; // Fecha .log
    
    if ($has_more_pages) {
        output("\n<span class='info'>💡 Há mais usuários para processar.</span>\n");
        echo "<button class='btn' onclick='location.reload()'>▶ Processar próximo lote</button>";
    } else {
        output("\n<span class='success'>✓ Todos os usuários disponíveis foram processados!</span>\n");
        echo "<button class='btn' disabled>✓ Processamento completo</button>";
    }
    
    echo "<button class='btn btn-reset' onclick='if(confirm(\"Tem certeza? Isso vai limpar todo o progresso e recomeçar do zero.\")) location.href=\"?reset=1\"'>🔄 Resetar e recomeçar</button>";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    output("\n<span class='separator'>═══════════════════════════════════════════════════════════</span>\n");
    output("<span class='error'>✗ ERRO FATAL</span>\n");
    output("<span class='separator'>═══════════════════════════════════════════════════════════</span>\n");
    output("<span class='error'>" . htmlspecialchars($e->getMessage()) . "</span>\n");
    echo "</div>"; // Fecha .log
    echo "<button class='btn' onclick='location.reload()'>🔄 Tentar novamente</button>";
    echo "<button class='btn btn-reset' onclick='if(confirm(\"Resetar tudo?\")) location.href=\"?reset=1\"'>🔄 Resetar</button>";
}

?>
        </div>
    </div>
</body>
</html>