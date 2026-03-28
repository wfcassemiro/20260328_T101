<?php
// budget.php - Versão com suporte a Revisão e múltiplas unidades
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Configuração de Fuso Horário
date_default_timezone_set('America/Sao_Paulo');

session_start();

// Carregamento de dependências
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';
require_once __DIR__ . '/../config/dash_functions.php';

// Verifica Login
if (!isset($_SESSION['user_id']) && function_exists('isLoggedIn') && !isLoggedIn()) {
    header('Location: /login.php');
    exit;
}
$currentUserId = $_SESSION['user_id'];

// ==================== INICIALIZAÇÃO E CONSULTAS BD ====================

$customServices = [];
$currentUserName = '';
try {
    global $pdo;
    
    // Buscar nome do usuário logado
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = :uid");
    $stmt->execute([':uid' => $currentUserId]);
    $currentUserName = $stmt->fetchColumn() ?: '';
    
    // 1. Clientes - CORRIGIDO: buscar company como nome principal, name/contact_name como contato
    $stmt = $pdo->prepare("SELECT id, name, company, default_currency, contact_name, email, phone, address_line1, address_line2, address_line3 FROM dash_clients WHERE user_id = ? ORDER BY COALESCE(NULLIF(company,''), name) ASC");
    $stmt->execute([$currentUserId]);
    $clientsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fornecedores
    $stmt = $pdo->prepare("SELECT id, name FROM dash_freelancers WHERE user_id = ? ORDER BY name ASC");
    $stmt->execute([$currentUserId]);
    $providersList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Moedas
    $stmt = $pdo->prepare("SELECT setting_key FROM dash_settings WHERE user_id = ? AND setting_key LIKE 'rate_%'");
    $stmt->execute([$currentUserId]);
    $rates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $currenciesList = ['BRL', 'USD', 'EUR'];
    foreach ($rates as $r) {
        $c = strtoupper(str_replace('rate_', '', $r));
        if (!in_array($c, $currenciesList)) $currenciesList[] = $c;
    }

    // 4. Serviços
    $stmt = $pdo->prepare("SELECT setting_value FROM dash_settings WHERE user_id = ? AND setting_key = 'custom_services_list'");
    $stmt->execute([$currentUserId]);
    $res = $stmt->fetchColumn();
    $servicesList = $res ? json_decode($res, true) : ['Tradução', 'Revisão', 'Pós-edição', 'Legendagem', 'Transcrição'];
    sort($servicesList);

} catch (Exception $e) {
    error_log("Erro BD: " . $e->getMessage());
    $clientsList = []; $providersList = []; $servicesList = ['Tradução', 'Revisão'];
}

// Inicializa Sessão (somente se não existir)
if (!isset($_SESSION['analyses'])) $_SESSION['analyses'] = [];
if (!isset($_SESSION['wc_weights'])) {
    $_SESSION['wc_weights'] = [
        'Repetition' => 0.0, '101%' => 0.05, '100%' => 0.1, '95-99%' => 0.2,
        '85-94%' => 0.4, '75-84%' => 0.6, '50-74%' => 0.8, 'No Match' => 1.0
    ];
}
if (!isset($_SESSION['budget_client'])) {
    $_SESSION['budget_client'] = [
        'client_id' => '', 'client_name' => '', 'company_name' => '', 'currency' => 'BRL',
        'service' => '', 'lang_from' => '', 'lang_to' => '',
        'contact_name' => '', 'client_email' => '', 'client_phone' => '', 'client_address' => ''
    ];
}
if (!isset($_SESSION['budget_params'])) {
    $_SESSION['budget_params'] = ['markup_pct' => 30.0, 'tax_pct' => 11.5, 'use_markup' => true, 'use_tax' => true, 'skip_weights' => false];
}
if (!isset($_SESSION['budget_costs'])) $_SESSION['budget_costs'] = ['items' => []];
if (!isset($_SESSION['budget_flow_step'])) $_SESSION['budget_flow_step'] = 1;


// ==================== FUNÇÕES UTILITÁRIAS ====================

function parseBRLFloat($value) {
    if (empty($value)) return 0.0;
    $value = preg_replace('/[^\d.,]/', '', $value);
    $hasDot = strpos($value, '.') !== false;
    $hasComma = strpos($value, ',') !== false;

    if ($hasDot && $hasComma) {
        if (strrpos($value, ',') > strrpos($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }
    } elseif ($hasComma) {
        $value = str_replace(',', '.', $value);
    }
    return (float)$value;
}

// Arredondar para cima com 2 casas decimais
function ceilTo2($value) {
    return ceil($value * 100) / 100;
}

function formatDateBR($date) {
    if (empty($date)) return '';
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d ? $d->format('d-m-Y') : $date;
}

function processAnalysisCSV($csvPath, $fileName) {
    $content = file_get_contents($csvPath);
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    
    if (mb_detect_encoding($content, 'UTF-16LE, UTF-16BE', true)) {
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-16');
    } elseif (mb_detect_encoding($content, 'ISO-8859-1', true)) {
        $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
    }

    $delimiters = ["\t" => substr_count($content, "\t"), ';' => substr_count($content, ';'), ',' => substr_count($content, ',')];
    arsort($delimiters);
    $delimiter = array_key_first($delimiters);
    
    $lines = explode("\n", $content);
    $analysis = ['fileName' => $fileName, 'fuzzyMatches' => [], 'totalWords' => 0, 'totalSegments' => 0, 'weightedWordCount' => 0];
    
    $inData = false;
    $idxType = $idxSeg = $idxWrd = -1;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $row = ($delimiter == "\t") ? explode("\t", $line) : str_getcsv($line, $delimiter);
        
        if (!$inData) {
            foreach ($row as $k => $v) {
                if (stripos($v, 'Type') !== false) $idxType = $k;
                if (stripos($v, 'Segments') !== false) $idxSeg = $k;
                if (stripos($v, 'Words') !== false) $idxWrd = $k;
            }
            if ($idxType > -1 && ($idxSeg > -1 || $idxWrd > -1)) { $inData = true; continue; }
        }
        
        if ($inData) {
            if (preg_match('/^-{3,}/', $line)) break;
            if ($idxType == -1) $idxType = 0;
            if ($idxSeg == -1) $idxSeg = 1;
            if ($idxWrd == -1) $idxWrd = 2;
            
            if (!isset($row[$idxType])) continue;
            $type = $row[$idxType];
            if (stripos($type, 'Total') !== false || stripos($type, 'All') !== false) continue;
            
            $segs = isset($row[$idxSeg]) ? (int)preg_replace('/\D/', '', $row[$idxSeg]) : 0;
            $wrds = isset($row[$idxWrd]) ? (int)preg_replace('/\D/', '', $row[$idxWrd]) : 0;
            
            $cat = 'No Match';
            $t = strtolower($type);
            if (strpos($t, 'rep') !== false) $cat = 'Repetition';
            elseif (strpos($t, '101') !== false || strpos($t, 'context') !== false || strpos($t, 'ice') !== false || strpos($t, 'x-trans') !== false) $cat = '101%';
            elseif (strpos($t, '100') !== false || strpos($t, 'perfect') !== false || strpos($t, 'exact') !== false) $cat = '100%';
            elseif (preg_match('/9[5-9]/', $t)) $cat = '95-99%';
            elseif (preg_match('/8[5-9]|9[0-4]/', $t)) $cat = '85-94%';
            elseif (preg_match('/7[5-9]|8[0-4]/', $t)) $cat = '75-84%';
            elseif (preg_match('/5[0-9]|6[0-9]|7[0-4]/', $t)) $cat = '50-74%';
            
            if ($wrds >= 0 && $wrds > 0) {
                $analysis['fuzzyMatches'][] = ['category' => $cat, 'segments' => $segs, 'words' => $wrds];
                $analysis['totalWords'] += $wrds;
                $analysis['totalSegments'] += $segs;
            }
        }
    }
    if ($analysis['totalWords'] == 0) throw new Exception("Nenhum dado válido encontrado.");
    return $analysis;
}


// ==================== AJAX HANDLERS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $res = ['success' => false, 'message' => ''];
    
    try {
        global $pdo;
        $act = $_POST['ajax_action'];

        if ($act === 'add_client_db') {
            $companyName = trim($_POST['new_client_name']);
            $curr = trim($_POST['new_client_currency']);
            if (!$companyName) throw new Exception("Nome inválido");
            
            // Inserir no campo company (nome da empresa)
            $stmt = $pdo->prepare("INSERT INTO dash_clients (user_id, company, default_currency, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$currentUserId, $companyName, $curr]);
            $res = ['success' => true, 'id' => $pdo->lastInsertId(), 'name' => $companyName, 'currency' => $curr];
        }

        elseif ($act === 'add_service_db') {
            $svc = trim($_POST['new_service_name']);
            if (!$svc) throw new Exception("Nome inválido");
            
            $stmt = $pdo->prepare("SELECT setting_value FROM dash_settings WHERE user_id = ? AND setting_key = 'custom_services_list'");
            $stmt->execute([$currentUserId]);
            $currList = $stmt->fetchColumn();
            $list = $currList ? json_decode($currList, true) : ['Tradução', 'Revisão', 'Pós-edição', 'Legendagem'];
            
            if (!in_array($svc, $list)) {
                $list[] = $svc;
                sort($list);
                $stmt = $pdo->prepare("INSERT INTO dash_settings (user_id, setting_key, setting_value, created_at) VALUES (?, 'custom_services_list', ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = ?");
                $json = json_encode($list);
                $stmt->execute([$currentUserId, $json, $json]);
            }
            $res = ['success' => true, 'name' => $svc];
        }

        elseif ($act === 'add_currency_db') {
            $curr = strtoupper(trim($_POST['new_currency_code']));
            if (strlen($curr) != 3) throw new Exception("Código deve ter 3 letras (ex: USD)");
            
            $key = "rate_" . $curr;
            $stmt = $pdo->prepare("INSERT IGNORE INTO dash_settings (user_id, setting_key, setting_value, created_at) VALUES (?, ?, '1.00', NOW())");
            $stmt->execute([$currentUserId, $key]);
            $res = ['success' => true, 'code' => $curr];
        }

        elseif ($act === 'get_client_data') {
            $clientId = $_POST['client_id'];
            if ($clientId) {
                $stmt = $pdo->prepare("SELECT id, name, company, contact_name, email, phone, default_currency, address_line1, address_line2, address_line3 FROM dash_clients WHERE id = ? AND user_id = ?");
                $stmt->execute([$clientId, $currentUserId]);
                $client = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($client) {
                    // Determinar nome da empresa e contato
                    $companyName = !empty($client['company']) ? $client['company'] : $client['name'];
                    $contactName = !empty($client['contact_name']) ? $client['contact_name'] : (!empty($client['company']) ? $client['name'] : '');
                    
                    $client['display_company'] = $companyName;
                    $client['display_contact'] = $contactName;
                    $res = ['success' => true, 'client' => $client];
                } else {
                    $res = ['success' => false, 'message' => 'Cliente não encontrado'];
                }
            }
        }

        elseif ($act === 'update_client_session') {
            $service = $_POST['service'];
            $isRevisao = (mb_stripos($service, 'revis', 0, 'UTF-8') !== false);
            
            $_SESSION['budget_client'] = [
                'client_id' => $_POST['client_id'],
                'client_name' => '',
                'company_name' => '',
                'service' => $service,
                'lang_from' => $_POST['lang_from'],
                'lang_to' => $isRevisao ? '' : $_POST['lang_to'],
                'currency' => $_POST['currency'],
                'contact_name' => '',
                'client_email' => '',
                'client_phone' => '',
                'client_address' => ''
            ];
            if ($_POST['client_id']) {
                $stmt = $pdo->prepare("SELECT id, name, company, contact_name, email, phone, address_line1, address_line2, address_line3 FROM dash_clients WHERE id = ?");
                $stmt->execute([$_POST['client_id']]);
                $clientData = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($clientData) {
                    // Company é o nome da empresa, name é o contato se company existe
                    $companyName = !empty($clientData['company']) ? $clientData['company'] : $clientData['name'];
                    $contactName = !empty($clientData['contact_name']) ? $clientData['contact_name'] : (!empty($clientData['company']) ? $clientData['name'] : '');
                    
                    $_SESSION['budget_client']['company_name'] = $companyName;
                    $_SESSION['budget_client']['client_name'] = $companyName; // Compatibilidade
                    $_SESSION['budget_client']['contact_name'] = $contactName;
                    $_SESSION['budget_client']['client_email'] = $clientData['email'] ?? '';
                    $_SESSION['budget_client']['client_phone'] = $clientData['phone'] ?? '';
                    
                    // Montar endereço completo
                    $addressParts = array_filter([
                        $clientData['address_line1'] ?? '',
                        $clientData['address_line2'] ?? '',
                        $clientData['address_line3'] ?? ''
                    ]);
                    $_SESSION['budget_client']['client_address'] = implode(', ', $addressParts);
                }
            }
            
            if ($isRevisao) {
                $_SESSION['budget_flow_step'] = 4;
            } else {
                $_SESSION['budget_flow_step'] = max($_SESSION['budget_flow_step'], 2);
            }
            
            $res = ['success' => true, 'isRevisao' => $isRevisao];
        }

        elseif ($act === 'update_weights') {
            $skipWeights = ($_POST['skip_weights'] ?? '0') === '1';
            $_SESSION['budget_params']['skip_weights'] = $skipWeights;
            
            if (!$skipWeights) {
                foreach ($_POST as $k => $v) {
                    if (strpos($k, 'w_') === 0) {
                        $key = str_replace('w_', '', $k);
                        $map = ['101_' => '101%', '95_99_' => '95-99%', '85_94_' => '85-94%', '75_84_' => '75-84%', '50_74_' => '50-74%', 'No_Match' => 'No Match'];
                        if (isset($map[$key])) $key = $map[$key];
                        $_SESSION['wc_weights'][$key] = parseBRLFloat($v);
                    }
                }
                $_SESSION['budget_flow_step'] = max($_SESSION['budget_flow_step'], 3);
            } else {
                // Se pulou pesos, ir direto para custos
                $_SESSION['budget_flow_step'] = max($_SESSION['budget_flow_step'], 4);
            }
            $res = ['success' => true, 'skipWeights' => $skipWeights];
        }

        elseif ($act === 'add_cost') {
            $pid = $_POST['provider_id'];
            $pname = ($pid === 'interno') ? 'Interno' : (($pid === 'outro') ? 'Outro' : 'Freelancer');
            if (is_numeric($pid)) {
                $stmt = $pdo->prepare("SELECT name FROM dash_freelancers WHERE id = ?");
                $stmt->execute([$pid]);
                $pname = $stmt->fetchColumn() ?: 'Freelancer';
            }
            
            $service = $_SESSION['budget_client']['service'] ?? '';
            $isRevisao = (mb_stripos($service, 'revis', 0, 'UTF-8') !== false);
            $skipWeights = $_SESSION['budget_params']['skip_weights'] ?? false;
            
            $item = [
                'provider_id' => $pid, 
                'provider_name' => $pname,
                'service' => $_POST['cost_service'] ?? $service, 
                'unit_cost' => ceilTo2(parseBRLFloat($_POST['cost_value']))
            ];
            
            if ($isRevisao) {
                $item['unidade'] = $_POST['unidade'] ?? 'Palavra';
                $item['quantidade'] = parseBRLFloat($_POST['quantidade'] ?? '1');
                
                if ($item['unidade'] === 'Lauda') {
                    $item['lauda_base'] = $_POST['lauda_base'] ?? 'caracteres';
                    $item['por_lauda'] = parseBRLFloat($_POST['por_lauda'] ?? '1800');
                    $item['total_doc'] = parseBRLFloat($_POST['total_doc'] ?? '0');
                    $item['inclui_espacos'] = ($_POST['inclui_espacos'] ?? '0') === '1';
                    
                    if ($item['por_lauda'] > 0) {
                        $item['quantidade'] = ceil($item['total_doc'] / $item['por_lauda']);
                    }
                }
            } elseif ($skipWeights) {
                // Modo sem pesos: usar total de palavras
                $item['total_palavras'] = (int)($_POST['total_palavras'] ?? 0);
                $item['service'] = 'Total palavras: ' . number_format($item['total_palavras'], 0, ',', '.');
            }
            
            $_SESSION['budget_costs']['items'][] = $item;
            $res = ['success' => true];
        }

        elseif ($act === 'update_cost') {
            $idx = (int)$_POST['index'];
            if (isset($_SESSION['budget_costs']['items'][$idx])) {
                $pid = $_POST['provider_id'];
                $pname = ($pid === 'interno') ? 'Interno' : (($pid === 'outro') ? 'Outro' : 'Freelancer');
                if (is_numeric($pid)) {
                    $stmt = $pdo->prepare("SELECT name FROM dash_freelancers WHERE id = ?");
                    $stmt->execute([$pid]);
                    $pname = $stmt->fetchColumn() ?: 'Freelancer';
                }
                
                $service = $_SESSION['budget_client']['service'] ?? '';
                $isRevisao = (mb_stripos($service, 'revis', 0, 'UTF-8') !== false);
                
                $item = [
                    'provider_id' => $pid, 
                    'provider_name' => $pname,
                    'service' => $_POST['cost_service'] ?? $service, 
                    'unit_cost' => ceilTo2(parseBRLFloat($_POST['cost_value']))
                ];
                
                if ($isRevisao) {
                    $item['unidade'] = $_POST['unidade'] ?? 'Palavra';
                    $item['quantidade'] = parseBRLFloat($_POST['quantidade'] ?? '1');
                    
                    if ($item['unidade'] === 'Lauda') {
                        $item['lauda_base'] = $_POST['lauda_base'] ?? 'caracteres';
                        $item['por_lauda'] = parseBRLFloat($_POST['por_lauda'] ?? '1800');
                        $item['total_doc'] = parseBRLFloat($_POST['total_doc'] ?? '0');
                        $item['inclui_espacos'] = ($_POST['inclui_espacos'] ?? '0') === '1';
                        
                        if ($item['por_lauda'] > 0) {
                            $item['quantidade'] = ceil($item['total_doc'] / $item['por_lauda']);
                        }
                    }
                } elseif ($_SESSION['budget_params']['skip_weights'] ?? false) {
                    // Modo sem pesos: usar total de palavras
                    $item['total_palavras'] = (int)($_POST['total_palavras'] ?? 0);
                    $item['service'] = 'Total palavras: ' . number_format($item['total_palavras'], 0, ',', '.');
                }
                
                $_SESSION['budget_costs']['items'][$idx] = $item;
                $res = ['success' => true];
            } else {
                $res = ['success' => false, 'message' => 'Item não encontrado'];
            }
        }

        elseif ($act === 'remove_item') {
            $type = $_POST['type'];
            $idx = (int)$_POST['index'];
            if ($type === 'cost') array_splice($_SESSION['budget_costs']['items'], $idx, 1);
            else array_splice($_SESSION['analyses'], $idx, 1);
            $res = ['success' => true];
        }

        elseif ($act === 'calculate') {
            $_SESSION['budget_params']['markup_pct'] = parseBRLFloat($_POST['markup']);
            $_SESSION['budget_params']['tax_pct'] = parseBRLFloat($_POST['tax']);
            $_SESSION['budget_params']['use_markup'] = ($_POST['use_markup'] ?? '1') === '1';
            $_SESSION['budget_params']['use_tax'] = ($_POST['use_tax'] ?? '1') === '1';
            
            if (empty($_SESSION['budget_costs']['items'])) throw new Exception("Adicione custos primeiro.");
            
            $service = $_SESSION['budget_client']['service'] ?? '';
            $isRevisao = (mb_stripos($service, 'revis', 0, 'UTF-8') !== false);
            $skipWeights = $_SESSION['budget_params']['skip_weights'] ?? false;
            
            $ws = 0; $tw = 0; $ts = 0;
            
            if ($skipWeights) {
                // Modo sem pesos: totalizar palavras dos itens de custo
                foreach ($_SESSION['budget_costs']['items'] as $c) {
                    if (isset($c['total_palavras'])) {
                        $tw += $c['total_palavras'];
                        $ws += $c['total_palavras']; // 100% das palavras
                    }
                }
            } else {
                foreach ($_SESSION['analyses'] as $a) {
                    $ws += $a['weightedWordCount'];
                    $tw += $a['totalWords']; 
                    $ts += $a['totalSegments'];
                }
            }
            
            $costTotal = 0;
            foreach ($_SESSION['budget_costs']['items'] as $c) {
                $u = $c['unit_cost'];
                
                if ($isRevisao) {
                    $qtd = $c['quantidade'] ?? 1;
                    $costTotal += $u * $qtd;
                } elseif ($skipWeights && isset($c['total_palavras'])) {
                    // Modo sem pesos: multiplicar valor unitário pelo total de palavras
                    $costTotal += $u * $c['total_palavras'];
                } else {
                    $svc = $c['service']; 

                    if (mb_stripos($svc, 'tradu', 0, 'UTF-8') !== false) {
                        $costTotal += $u * $ws;
                    }
                    elseif (mb_stripos($svc, 'revis', 0, 'UTF-8') !== false || mb_stripos($svc, 'pós', 0, 'UTF-8') !== false || mb_stripos($svc, 'proof', 0, 'UTF-8') !== false) {
                        $costTotal += $u * $tw;
                    }
                    else {
                        $costTotal += $u;
                    }
                }
            }
            
            $mk = $_SESSION['budget_params']['use_markup'] ? $_SESSION['budget_params']['markup_pct'] : 0;
            $tx = $_SESSION['budget_params']['use_tax'] ? $_SESSION['budget_params']['tax_pct'] : 0;
            $sub = $costTotal * (1 + $mk/100);
            $final = $sub * (1 + $tx/100);
            
            // Arredondar para cima com 2 casas decimais
            $costTotal = ceilTo2($costTotal);
            $sub = ceilTo2($sub);
            $final = ceilTo2($final);
            
            $_SESSION['budget_flow_step'] = 5;
            $res = ['success' => true, 'results' => [
                'totalWords' => $tw, 'weightedSum' => ceilTo2($ws),
                'custoTotal' => $costTotal, 'subtotal' => $sub, 'impostos' => ceilTo2($final - $sub), 'precoFinal' => $final,
                'currency' => $_SESSION['budget_client']['currency']
            ]];
        }

    } catch (Exception $e) { $res['message'] = $e->getMessage(); }
    echo json_encode($res);
    exit;
}

// ==================== GERAÇÃO DE PDF ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_pdf') {
    if (!class_exists('TCPDF')) {
        $pt = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
        if (file_exists($pt)) require_once $pt;
        else die('Erro: TCPDF não encontrado.');
    }

    $d = [
        'provider' => trim($_POST['provider_name']),
        'company' => trim($_POST['company_name']),
        'contact' => trim($_POST['contact_name']),
        'validity' => formatDateBR($_POST['validity_date']),
        'delivery' => formatDateBR($_POST['delivery_date']),
        'desc' => trim($_POST['service_description']),
        'payment' => $_POST['payment_methods'] ?? [],
        'payment_other' => trim($_POST['payment_other'] ?? ''),
        'pay_date' => formatDateBR($_POST['payment_date']),
        'final_price' => parseBRLFloat($_POST['final_price']),
        'original_price' => parseBRLFloat($_POST['original_price'] ?? $_POST['final_price']),
        'discount_type' => $_POST['discount_type'] ?? 'valor',
        'discount_value' => parseBRLFloat($_POST['discount_value'] ?? '0'),
        'observations' => trim($_POST['observations'] ?? '')
    ];
    
    // Adicionar "Outros" se preenchido
    if ($d['payment_other']) {
        $d['payment'][] = $d['payment_other'];
    }
    
    $cliName = $_SESSION['budget_client']['client_name'] ?? 'Cliente';
    $curr = $_SESSION['budget_client']['currency'] ?? 'BRL';
    $service = $_SESSION['budget_client']['service'] ?? '';
    $langFrom = $_SESSION['budget_client']['lang_from'] ?? '';
    $langTo = $_SESSION['budget_client']['lang_to'] ?? '';
    $isRevisao = (mb_stripos($service, 'revis', 0, 'UTF-8') !== false);

    // Calcular desconto em R$ e em % baseado no valor ORIGINAL
    $descontoReais = 0;
    $descontoPct = 0;
    if ($d['discount_value'] > 0) {
        if ($d['discount_type'] === 'percentual') {
            $descontoPct = $d['discount_value'];
            $descontoReais = $d['original_price'] * ($descontoPct / 100);
        } else {
            $descontoReais = $d['discount_value'];
            $descontoPct = ($d['original_price'] > 0) ? ($descontoReais / $d['original_price']) * 100 : 0;
        }
    }
    
    // Valor final já vem calculado do formulário
    $valorFinal = $d['final_price'];

    class PDF extends TCPDF {
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('dejavusans', 'I', 8);
            $this->SetTextColor(128);
            $this->Cell(0, 10, 'Gerado via Dash-T101 | ' . date('d/m/Y H:i'), 0, 0, 'C');
        }
    }

    $pdf = new PDF();
    $pdf->SetCreator('Dash-T101');
    $pdf->SetMargins(20, 20, 20);
    $pdf->SetAutoPageBreak(true, 25);
    $pdf->AddPage();

    // Cabeçalho
    $pdf->SetFont('dejavusans', 'B', 24);
    $pdf->SetTextColor(74, 20, 140);
    $pdf->Cell(0, 12, 'PROPOSTA DE ORÇAMENTO', 0, 1, 'C');
    
    $pdf->SetDrawColor(74, 20, 140);
    $pdf->SetLineWidth(0.8);
    $pdf->Line(20, 35, 190, 35);
    
    $pdf->Ln(10);

    // Informações do Prestador
    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->SetTextColor(74, 20, 140);
    $pdf->Cell(0, 8, 'PRESTADOR DE SERVIÇOS', 0, 1, 'L');
    
    $pdf->SetFont('dejavusans', '', 11);
    $pdf->SetTextColor(60, 60, 60);
    $pdf->Cell(0, 7, $d['provider'], 0, 1, 'L');
    
    $pdf->Ln(8);

    // Informações do Cliente - Box
    $pdf->SetFillColor(248, 248, 255);
    $pdf->SetDrawColor(200, 200, 220);
    $pdf->RoundedRect(20, $pdf->GetY(), 170, 35, 3, '1111', 'DF');
    
    $pdf->SetXY(25, $pdf->GetY() + 5);
    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->SetTextColor(74, 20, 140);
    $pdf->Cell(0, 6, 'CLIENTE', 0, 1, 'L');
    
    $pdf->SetX(25);
    $pdf->SetFont('dejavusans', 'B', 12);
    $pdf->SetTextColor(40, 40, 40);
    $pdf->Cell(0, 7, $d['company'], 0, 1, 'L');
    
    if ($d['contact']) {
        $pdf->SetX(25);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 6, 'A/C: ' . $d['contact'], 0, 1, 'L');
    }
    
    $pdf->Ln(15);

    // Descrição do Serviço
    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->SetTextColor(74, 20, 140);
    $pdf->Cell(0, 8, 'DESCRIÇÃO DO SERVIÇO', 0, 1, 'L');
    
    $pdf->SetFont('dejavusans', '', 11);
    $pdf->SetTextColor(60, 60, 60);
    
    // Pegar descrição e remover idioma duplicado se houver
    $descText = $d['desc'] ?: $service;
    // Remover idioma se já existir na descrição (evitar duplicação)
    $descText = preg_replace('/\s*\|\s*Idioma:.*$/', '', $descText);
    // Adicionar idioma apenas se não for revisão
    if (!$isRevisao && $langFrom) {
        $descText .= " | Idioma: " . $langFrom;
        if ($langTo) $descText .= " → " . $langTo;
    }
    $pdf->MultiCell(170, 7, $descText, 0, 'L');
    
    // Detalhes da quantidade (laudas, palavras, horas)
    $costItems = $_SESSION['budget_costs']['items'] ?? [];
    if (!empty($costItems)) {
        $pdf->Ln(3);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->SetTextColor(80, 80, 80);
        
        foreach ($costItems as $item) {
            $unidade = $item['unidade'] ?? '';
            $quantidade = $item['quantidade'] ?? 0;
            
            if ($unidade === 'Lauda') {
                $laudaBase = $item['lauda_base'] ?? 'caracteres';
                $porLauda = $item['por_lauda'] ?? 0;
                $totalDoc = $item['total_doc'] ?? 0;
                $incluiEspacos = $item['inclui_espacos'] ?? false;
                
                $espacosText = '';
                if ($laudaBase === 'caracteres') {
                    $espacosText = $incluiEspacos ? ' (com espaços)' : ' (sem espaços)';
                }
                
                $pdf->Cell(0, 6, "• {$quantidade} laudas ({$porLauda} {$laudaBase}{$espacosText}/lauda = {$totalDoc} {$laudaBase} total)", 0, 1, 'L');
            } elseif ($unidade === 'Palavra') {
                $pdf->Cell(0, 6, "• {$quantidade} palavras", 0, 1, 'L');
            } elseif ($unidade === 'Hora') {
                $pdf->Cell(0, 6, "• {$quantidade} horas", 0, 1, 'L');
            }
        }
    }
    
    $pdf->Ln(8);

    // Grid de datas - Tabela
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->SetFillColor(74, 20, 140);
    $pdf->SetTextColor(255, 255, 255);
    
    $pdf->Cell(85, 8, 'VALIDADE DA PROPOSTA', 1, 0, 'C', true);
    $pdf->Cell(85, 8, 'DATA DE ENTREGA', 1, 1, 'C', true);
    
    $pdf->SetFont('dejavusans', '', 11);
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetFillColor(255, 255, 255);
    
    $pdf->Cell(85, 10, $d['validity'], 1, 0, 'C');
    $pdf->Cell(85, 10, $d['delivery'], 1, 1, 'C');
    
    $pdf->Ln(8);

    // Formas de Pagamento
    $pdf->SetFont('dejavusans', 'B', 11);
    $pdf->SetTextColor(74, 20, 140);
    $pdf->Cell(0, 8, 'FORMAS DE PAGAMENTO', 0, 1, 'L');
    
    $pdf->SetFont('dejavusans', '', 11);
    $pdf->SetTextColor(60, 60, 60);
    $paymentText = !empty($d['payment']) ? implode(' • ', $d['payment']) : 'A combinar';
    $pdf->Cell(0, 7, $paymentText, 0, 1, 'L');
    
    if ($d['pay_date']) {
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 6, 'Data de pagamento: ' . $d['pay_date'], 0, 1, 'L');
    }
    
    // Observações
    if ($d['observations']) {
        $pdf->Ln(8);
        $pdf->SetFont('dejavusans', 'B', 11);
        $pdf->SetTextColor(74, 20, 140);
        $pdf->Cell(0, 8, 'OBSERVAÇÕES', 0, 1, 'L');
        
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->MultiCell(170, 6, $d['observations'], 0, 'L');
    }
    
    $pdf->Ln(10);

    // Desconto (se houver)
    if ($descontoReais > 0) {
        $pdf->SetFillColor(255, 243, 224);
        $pdf->SetDrawColor(255, 152, 0);
        $pdf->RoundedRect(20, $pdf->GetY(), 170, 25, 3, '1111', 'DF');
        
        $pdf->SetXY(25, $pdf->GetY() + 5);
        $pdf->SetFont('dejavusans', 'B', 11);
        $pdf->SetTextColor(230, 81, 0);
        $pdf->Cell(80, 6, 'DESCONTO ESPECIAL', 0, 0, 'L');
        $pdf->SetFont('dejavusans', 'B', 14);
        $pdf->Cell(80, 6, '- ' . $curr . ' ' . number_format($descontoReais, 2, ',', '.'), 0, 1, 'R');
        
        // Mostrar percentual também
        $pdf->SetXY(25, $pdf->GetY() + 2);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->SetTextColor(180, 100, 0);
        $pdf->Cell(160, 6, '(' . number_format($descontoPct, 1, ',', '.') . '% de desconto)', 0, 1, 'R');
        
        $pdf->Ln(8);
    }

    // Valor Total - Box destacado
    $pdf->SetFillColor(74, 20, 140);
    $pdf->RoundedRect(20, $pdf->GetY(), 170, 30, 3, '1111', 'F');
    
    $pdf->SetXY(25, $pdf->GetY() + 5);
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(160, 6, 'VALOR TOTAL', 0, 1, 'C');
    
    $pdf->SetX(25);
    $pdf->SetFont('dejavusans', 'B', 20);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(160, 12, $curr . ' ' . number_format($valorFinal, 2, ',', '.'), 0, 1, 'C');

    $pdf->Output('Orcamento_' . date('Ymd_His') . '.pdf', 'D');
    exit;
}


// ==================== UPLOAD DE CSV ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv_files']['name'][0])) {
    header('Content-Type: application/json');
    $results = [];
    $ws = $_SESSION['wc_weights'];

    try {
        foreach ($_FILES['csv_files']['tmp_name'] as $idx => $tmp) {
            if ($_FILES['csv_files']['error'][$idx] !== UPLOAD_ERR_OK) continue;
            $fname = $_FILES['csv_files']['name'][$idx];
            $analysis = processAnalysisCSV($tmp, $fname);

            $wc = 0;
            foreach ($analysis['fuzzyMatches'] as $fm) {
                $w = $ws[$fm['category']] ?? 1.0;
                $wc += $fm['words'] * $w;
            }
            $analysis['weightedWordCount'] = round($wc, 2);
            $_SESSION['analyses'][] = $analysis;
        }
        
        $_SESSION['budget_flow_step'] = max($_SESSION['budget_flow_step'], 4);
        echo json_encode(['success' => true, 'count' => count($_SESSION['analyses'])]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Clear - apenas quando solicitado explicitamente
if (isset($_GET['clear'])) {
    unset($_SESSION['analyses'], $_SESSION['budget_client'], $_SESSION['budget_costs'], $_SESSION['budget_flow_step'], $_SESSION['budget_params'], $_SESSION['wc_weights']);
    header('Location: budget.php');
    exit;
}

// Define as variáveis do PHP para usar no HTML
$step = $_SESSION['budget_flow_step'] ?? 1;
$bClient = $_SESSION['budget_client'] ?? [
    'client_id' => '', 'client_name' => '', 'company_name' => '', 'currency' => 'BRL',
    'service' => '', 'lang_from' => '', 'lang_to' => '',
    'contact_name' => '', 'client_email' => '', 'client_phone' => '', 'client_address' => ''
];
$bParams = $_SESSION['budget_params'] ?? ['markup_pct' => 30.0, 'tax_pct' => 11.5, 'use_markup' => true, 'use_tax' => true, 'skip_weights' => false];

// Verifica se é revisão
$isRevisao = (mb_stripos($bClient['service'] ?? '', 'revis', 0, 'UTF-8') !== false);

// Preparar lista de clientes para JSON
$clientsJson = json_encode($clientsList);

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<style>
.profile-header-card {
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
    border-radius: 16px; padding: 25px; margin-bottom: 30px;
    display: flex; align-items: center; box-shadow: 0 10px 25px -5px rgba(124, 58, 237, 0.5);
}
.header-icon-container {
    width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%;
    display: flex; align-items: center; justify-content: center; font-size: 28px; margin-right: 20px;
}
.header-text-container h2 { margin: 0; font-size: 1.8rem; font-weight: 700; }
.header-text-container p { margin: 5px 0 0; opacity: 0.9; }

.video-card {
    background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px;
    margin-bottom: 25px; transition: transform 0.2s, opacity 0.3s;
    overflow: hidden;
}
.video-card.disabled { opacity: 0.5; pointer-events: none; filter: grayscale(0.8); }
.video-card.completed { border-color: rgba(16, 185, 129, 0.5); }
.video-card.skipped { opacity: 0.4; pointer-events: none; }
.video-card.skipped h2::after { content: 'Ignorado (Revisão)'; font-size: 0.7rem; font-weight: normal; background: #64748b; color: white; padding: 4px 10px; border-radius: 12px; }
.video-card h2 {
    background: rgba(255, 255, 255, 0.03); padding: 20px 25px; margin: 0;
    font-size: 1.25rem; border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    display: flex; justify-content: space-between; align-items: center;
}
.video-card.completed h2::after { content: '\f00c'; font-family: 'Font Awesome 5 Free'; font-weight: 900; color: #10b981; }
.video-card.completed h2 {
    background: linear-gradient(90deg, rgba(16, 185, 129, 0.4), rgba(16, 185, 129, 0.1)) !important;
    border-bottom-color: rgba(16, 185, 129, 0.5) !important;
    color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}

.vision-form-refined { padding: 25px; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #cbd5e1; font-size: 0.9rem; }

.input-group { display: flex; gap: 10px; }
.vision-input, .vision-select, textarea {
    width: 100%; padding: 12px 15px;
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 8px; color: #fff; font-size: 1rem;
    transition: border-color 0.2s;
}
.vision-input:focus, .vision-select:focus { outline: none; border-color: #8b5cf6; background: rgba(15, 23, 42, 0.8); }
.vision-input:disabled, .vision-select:disabled { opacity: 0.5; cursor: not-allowed; background: rgba(15, 23, 42, 0.3); }

/* Estilizar options e optgroup do select */
.vision-select option, .vision-select optgroup {
    background: #1e293b;
    color: #fff;
}
.vision-select optgroup {
    font-weight: bold;
    color: #94a3b8;
    background: #0f172a;
}

input[type="date"] { 
    color-scheme: dark; 
}

/* Melhorar contraste do ícone do calendário - FORÇA */
input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(1) brightness(2) !important;
    cursor: pointer;
    opacity: 1 !important;
    width: 20px;
    height: 20px;
}
input[type="date"]::-webkit-calendar-picker-indicator:hover {
    filter: invert(1) brightness(2.5) !important;
}

/* Firefox */
input[type="date"] {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='white' viewBox='0 0 16 16'%3E%3Cpath d='M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
}

/* Botão excluir com ícone branco para melhor contraste */
.btn-delete-contrast {
    background: #ef4444 !important;
    color: #fff !important;
}
.btn-delete-contrast i {
    color: #fff !important;
}

/* Container para markup e impostos alinhados */
.markup-tax-container {
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

.markup-tax-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

.markup-tax-item {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.markup-tax-input {
    display: flex;
    align-items: center;
    gap: 8px;
}

.markup-tax-input .vision-input {
    width: 100px;
}

.input-suffix {
    color: #94a3b8;
    font-size: 0.9rem;
}

/* Radio buttons para desconto */
.discount-type-radio {
    display: flex;
    gap: 20px;
    margin-bottom: 10px;
}

.discount-type-radio label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 8px 15px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.1);
    transition: all 0.2s;
}

.discount-type-radio label:hover {
    border-color: rgba(139, 92, 246, 0.5);
}

.discount-type-radio input[type="radio"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.discount-type-radio input[type="radio"]:checked + span {
    color: #c4b5fd;
}

.discount-type-radio label:has(input:checked) {
    background: rgba(139, 92, 246, 0.2);
    border-color: rgba(139, 92, 246, 0.5);
}

/* Opção para pular pesos */
.skip-weights-option {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.skip-weights-option label {
    color: #fbbf24;
}

.vision-btn {
    padding: 12px 24px; border-radius: 30px; border: none; cursor: pointer;
    font-weight: 600; font-size: 1rem; display: inline-flex; align-items: center; gap: 8px;
    transition: all 0.2s;
}
.vision-btn-primary { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white; }
.vision-btn-primary:hover { box-shadow: 0 0 15px rgba(139, 92, 246, 0.4); transform: translateY(-1px); }
.vision-btn-secondary { background: rgba(255, 255, 255, 0.1); color: white; }
.vision-btn-secondary:hover { background: rgba(255, 255, 255, 0.2); }
.vision-btn-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
.vision-btn-warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; }
.btn-mini { padding: 0 15px; font-size: 1.2rem; border-radius: 30px; }
.btn-icon { padding: 8px 12px; font-size: 0.9rem; border-radius: 30px; }

.cards-grid-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
.grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
.grid-5 { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; }

.analysis-item, .cost-item {
    background: rgba(255, 255, 255, 0.03); padding: 15px; border-radius: 8px;
    margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;
    border: 1px solid rgba(255, 255, 255, 0.05);
}
.vision-table { width: 100%; border-collapse: collapse; }
.vision-table th { text-align: left; padding: 12px; color: #94a3b8; border-bottom: 1px solid rgba(255,255,255,0.1); font-size: 0.9rem; }
.vision-table td { padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); }

.vision-modal {
    display: none; position: fixed; inset: 0;
    background: rgba(0, 0, 0, 0.8); backdrop-filter: blur(8px);
    z-index: 9999; align-items: center; justify-content: center;
}
.vision-modal.active { display: flex; animation: fadeIn 0.2s; }
.vision-modal-content {
    background: #1e293b; border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px; width: 90%; max-width: 600px; padding: 30px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    max-height: 90vh; overflow-y: auto;
}
.modal-header { display: flex; justify-content: space-between; margin-bottom: 25px; }
.modal-header h3 { margin: 0; font-size: 1.4rem; color: #fff; }
.btn-close { background: none; border: none; color: #64748b; font-size: 1.5rem; cursor: pointer; }

.text-right { text-align: right; }
.text-center { text-align: center; }
.text-left { text-align: left; }
.mt-20 { margin-top: 20px; }
.cost-summary { text-align: center; padding: 20px; background: rgba(16, 185, 129, 0.1); border-radius: 12px; margin-top: 20px; }
.final-price { font-size: 2.5rem; font-weight: 800; color: #34d399; display: block; margin: 10px 0; }

.lauda-info-display {
    background: rgba(139, 92, 246, 0.1);
    border: 1px solid rgba(139, 92, 246, 0.3);
    border-radius: 8px; padding: 10px 15px; margin-top: 10px;
    font-size: 0.9rem; color: #c4b5fd;
}

.checkbox-inline {
    display: flex; align-items: center; gap: 8px; cursor: pointer;
}
.checkbox-inline input[type="checkbox"] {
    width: 18px; height: 18px; cursor: pointer;
}

.payment-other-field {
    display: none; margin-top: 10px;
}
.payment-other-field.active { display: block; }

.discount-highlight {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-radius: 8px; padding: 10px;
}

/* Modal PDF - Melhor alinhamento dos campos */
#modalPdf .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    align-items: start;
}

#modalPdf .form-group {
    margin-bottom: 15px;
}

#modalPdf .form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #cbd5e1;
    font-size: 0.85rem;
}

.action-btns {
    display: flex; gap: 5px;
}

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

@media (max-width: 768px) {
    .cards-grid-2col { grid-template-columns: 1fr; }
    .form-row { grid-template-columns: 1fr; }
    .form-row-3 { grid-template-columns: 1fr; }
    .grid-4 { grid-template-columns: repeat(2, 1fr); }
    .grid-5 { grid-template-columns: repeat(2, 1fr); }
    .markup-tax-row { grid-template-columns: 1fr; gap: 20px; }
    .discount-type-radio { flex-direction: column; gap: 10px; }
}
</style>

<div class="main-content">
    
    <div class="profile-header-card">
        <div class="header-icon-container"><i class="fas fa-calculator"></i></div>
        <div class="header-text-container">
            <h2>Gerador guiado de orçamentos</h2>
            <p>Importe análises de CAT Tools, calcule custos e gere PDFs profissionais</p>
        </div>
    </div>
    <div class="report-nav-buttons" style="display: flex; gap: 10px; align-items: center; margin-bottom: 25px;">
        <a href="index.php" class="vision-btn vision-btn-primary">
            <i class="fas fa-arrow-left"></i>
            <span>Voltar ao Dash-T101</span>
        </a>
        <a href="?clear=1" class="vision-btn vision-btn-primary" onclick="return confirm('Limpar este orçamento e começar um novo?')">
            <i class="fas fa-plus-circle"></i>
            <span>Novo orçamento</span>
        </a>
    </div>    

    <div class="cards-grid-2col">
        <div class="video-card <?= $step >= 2 ? 'completed' : '' ?>" id="cardProject">
            <h2><span><i class="fas fa-user-circle"></i> Dados do projeto</span></h2>
            <form id="fProject" class="vision-form-refined">
                <div class="form-group">
                    <label>Cliente</label>
                    <div class="input-group">
                        <select name="client_id" id="selClient" class="vision-select">
                            <option value="">Selecione...</option>
                            <?php foreach ($clientsList as $c): 
                                // Determinar nome de exibição: company tem prioridade, senão name
                                $displayName = !empty($c['company']) ? $c['company'] : $c['name'];
                                // Determinar contato: contact_name ou name (se company existir)
                                $contactDisplay = !empty($c['contact_name']) ? $c['contact_name'] : (!empty($c['company']) ? $c['name'] : '');
                            ?>
                            <option value="<?= $c['id'] ?>" 
                                    data-curr="<?= $c['default_currency'] ?>" 
                                    data-company="<?= htmlspecialchars($displayName) ?>"
                                    data-contact="<?= htmlspecialchars($contactDisplay) ?>"
                                    data-email="<?= htmlspecialchars($c['email'] ?? '') ?>"
                                    data-phone="<?= htmlspecialchars($c['phone'] ?? '') ?>"
                                    data-address="<?= htmlspecialchars(implode(', ', array_filter([$c['address_line1'] ?? '', $c['address_line2'] ?? '', $c['address_line3'] ?? '']))) ?>"
                                    <?= ($bClient['client_id'] == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($displayName) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="vision-btn vision-btn-secondary btn-mini" onclick="openModal('modalClient')">+</button>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Serviço</label>
                        <div class="input-group">
                            <select name="service" id="selService" class="vision-select">
                                <?php foreach ($servicesList as $svc): ?>
                                <option <?= ($bClient['service'] == $svc) ? 'selected' : '' ?>><?= $svc ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="vision-btn vision-btn-secondary btn-mini" onclick="openModal('modalService')">+</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Moeda</label>
                        <div class="input-group">
                            <select name="currency" id="selCurrency" class="vision-select">
                                <?php foreach ($currenciesList as $cur): ?>
                                <option <?= ($bClient['currency'] == $cur) ? 'selected' : '' ?>><?= $cur ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="vision-btn vision-btn-secondary btn-mini" onclick="openModal('modalCurrency')">+</button>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Idioma origem</label>
                        <input type="text" name="lang_from" class="vision-input" value="<?= $bClient['lang_from'] ?>" placeholder="Ex: PT-BR">
                    </div>
                    <div class="form-group" id="langToGroup">
                        <label>Idioma destino</label>
                        <input type="text" name="lang_to" id="langToInput" class="vision-input" value="<?= $bClient['lang_to'] ?>" placeholder="Ex: EN-US">
                    </div>
                </div>

                <div class="text-center mt-20">
                    <button type="submit" class="vision-btn vision-btn-primary">Salvar e avançar <i class="fas fa-arrow-right"></i></button>
                </div>
            </form>
        </div>

        <div class="video-card <?= $isRevisao ? 'skipped' : ($step >= 3 ? 'completed' : ($step < 2 ? 'disabled' : '')) ?>" id="cardWeights">
            <h2><span><i class="fas fa-sliders-h"></i> Configuração de pesos</span></h2>
            <form id="fWeights" class="vision-form-refined">
                <div class="skip-weights-option">
                    <label class="checkbox-inline">
                        <input type="checkbox" id="chkSkipWeights" name="skip_weights" <?= ($bParams['skip_weights'] ?? false) ? 'checked' : '' ?>>
                        <span>Não usar pesos (cobrar 100% em todas as palavras)</span>
                    </label>
                    <p style="color:#94a3b8; font-size:0.8rem; margin-top:8px; margin-bottom:0;">
                        Marque esta opção se não quiser usar a análise de uma CAT Tool. O valor será calculado sobre o total de palavras.
                    </p>
                </div>
                
                <div id="weightsFieldset">
                    <p style="color:#94a3b8; margin-bottom:15px; font-size:0.9rem;">Define o percentual cobrado por faixa de fuzzy match.</p>
                    <div class="grid-4">
                        <?php 
                        // Lista ordenada de pesos válidos
                        $validWeights = ['Repetition', '101%', '100%', '95-99%', '85-94%', '75-84%', '50-74%', 'No Match'];
                        foreach ($validWeights as $k): 
                            if (!isset($_SESSION['wc_weights'][$k])) continue;
                            $v = $_SESSION['wc_weights'][$k];
                            $id = 'w_' . str_replace(['%',' ','-'], '_', $k); ?>
                        <div class="form-group">
                            <label><?= $k ?></label>
                            <input type="text" name="<?= $id ?>" class="vision-input text-center weights-input" value="<?= $v ?>">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="text-center mt-20">
                    <button type="submit" class="vision-btn vision-btn-primary">
                        <span id="weightsSubmitText">Atualizar pesos</span> <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="video-card <?= ($isRevisao || ($bParams['skip_weights'] ?? false)) ? 'skipped' : ($step >= 4 ? 'completed' : ($step < 3 ? 'disabled' : '')) ?>" id="cardAnalysis">
        <h2><span><i class="fas fa-file-csv"></i> Análise da CAT Tool</span></h2>
        <div class="vision-form-refined">
            <form id="fUpload">
                <div id="dropZone" style="background:rgba(255,255,255,0.05); border:2px dashed rgba(255,255,255,0.1); border-radius:10px; padding:30px; text-align:center; transition: all 0.3s ease;">
                    <i class="fas fa-cloud-upload-alt" style="font-size:2rem; color:#94a3b8; margin-bottom:10px;"></i>
                    <p>Arraste arquivos CSV ou clique para selecionar</p>
                    <input type="file" name="csv_files[]" multiple accept=".csv,.txt" id="fileInput" style="display:none;" onchange="document.getElementById('fUpload').dispatchEvent(new Event('submit'))">
                    <button type="button" class="vision-btn vision-btn-secondary" onclick="document.getElementById('fileInput').click()">Selecionar arquivos</button>
                </div>
            </form>

            <?php if (!empty($_SESSION['analyses'])): ?>
            <div class="mt-20">
                <?php foreach ($_SESSION['analyses'] as $idx => $a): ?>
                <div class="analysis-item">
                    <div>
                        <strong style="color:#c4b5fd;"><?= $a['fileName'] ?></strong>
                        <div style="font-size:0.85rem; color:#94a3b8;">
                            <?= number_format($a['totalWords']) ?> palavras no total | 
                            <span style="color:#fff;"><?= number_format($a['weightedWordCount']) ?> ponderadas</span>
                        </div>
                    </div>
                    <button class="vision-btn btn-close" onclick="removeItem('analysis', <?= $idx ?>)"><i class="fas fa-times"></i></button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="video-card <?= ($isRevisao && $step >= 2) || $step >= 4 ? '' : ($step < 4 && !$isRevisao ? 'disabled' : '') ?>" id="cardCosts">
        <h2><span><i class="fas fa-coins"></i> Composição dos custos</span></h2>
        <div class="vision-form-refined">
            <table class="vision-table">
                <thead>
                    <tr>
                        <?php if ($isRevisao): ?>
                        <th>Unidade</th>
                        <th>Quantidade</th>
                        <?php else: ?>
                        <?php $skipW = ($bParams['skip_weights'] ?? false); ?>
                        <th><?= $skipW ? 'Total de palavras' : 'Serviço' ?></th>
                        <?php endif; ?>
                        <th>Fornecedor</th>
                        <th>Valor unitário</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($_SESSION['budget_costs']['items'])): ?>
                    <tr><td colspan="<?= $isRevisao ? 5 : 4 ?>" class="text-center" style="padding:20px; color:#64748b;">Nenhum custo adicionado.</td></tr>
                    <?php else: foreach ($_SESSION['budget_costs']['items'] as $idx => $c): ?>
                    <tr>
                        <?php if ($isRevisao): ?>
                        <td>
                            <strong><?= $c['unidade'] ?? 'Palavra' ?></strong>
                            <?php if (($c['unidade'] ?? '') === 'Lauda'): ?>
                            <small style="display:block; color:#94a3b8; font-size:0.75rem;">
                                (<?= $c['por_lauda'] ?? 0 ?> <?= ($c['lauda_base'] ?? 'caracteres') === 'caracteres' ? 'caract.' : 'palavras' ?>/lauda<?php if (($c['lauda_base'] ?? '') === 'caracteres'): ?><?= ($c['inclui_espacos'] ?? false) ? ', c/ espaços' : ', s/ espaços' ?><?php endif; ?>)
                            </small>
                            <?php endif; ?>
                        </td>
                        <td><?= number_format($c['quantidade'] ?? 1, 0, ',', '.') ?></td>
                        <?php else: ?>
                        <td><?= $c['service'] ?></td>
                        <?php endif; ?>
                        <td><?= $c['provider_name'] ?></td>
                        <td><?= number_format($c['unit_cost'], 2, ',', '.') ?></td>
                        <td class="action-btns">
                            <button onclick="editCost(<?= $idx ?>, <?= htmlspecialchars(json_encode($c)) ?>)" class="vision-btn btn-icon vision-btn-warning" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="removeItem('cost', <?= $idx ?>)" class="vision-btn btn-icon btn-delete-contrast" title="Remover">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <form id="fAddCost" class="mt-20" style="background:rgba(0,0,0,0.2); padding:20px; border-radius:10px;">
                <input type="hidden" name="edit_index" id="editIndex" value="-1">
                
                <?php if ($isRevisao): ?>
                <div class="grid-4" style="align-items:end;">
                    <div class="form-group">
                        <label>Unidade</label>
                        <select name="unidade" id="selUnidade" class="vision-select">
                            <option value="Lauda">Lauda</option>
                            <option value="Palavra">Palavra</option>
                            <option value="Hora">Hora</option>
                        </select>
                    </div>
                    <div class="form-group" id="qtdGroup">
                        <label>Quantidade</label>
                        <input type="text" name="quantidade" id="inpQuantidade" class="vision-input" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label>Fornecedor</label>
                        <select name="provider_id" id="selProvider" class="vision-select">
                            <option value="interno">Interno</option>
                            <option value="outro">Outro</option>
                            <optgroup label="Freelancers">
                                <?php foreach ($providersList as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= $p['name'] ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Valor unitário</label>
                        <input type="text" name="cost_value" id="inpCostValue" class="vision-input" placeholder="0,00">
                    </div>
                </div>
                
                <input type="hidden" name="lauda_base" id="hiddenLaudaBase" value="caracteres">
                <input type="hidden" name="por_lauda" id="hiddenPorLauda" value="1800">
                <input type="hidden" name="total_doc" id="hiddenTotalDoc" value="0">
                <input type="hidden" name="inclui_espacos" id="hiddenIncluiEspacos" value="0">
                
                <div id="laudaInfoDisplay" class="lauda-info-display" style="display:none;">
                    <i class="fas fa-info-circle"></i>
                    <span id="laudaInfoText">Configure a lauda clicando no campo de quantidade</span>
                </div>

                <div class="text-center mt-20">
                    <button type="button" id="btnCancelEdit" class="vision-btn vision-btn-secondary" style="display:none; margin-right:10px;" onclick="cancelEdit()">
                        Cancelar
                    </button>
                    <button type="submit" id="btnSubmitCost" class="vision-btn vision-btn-success">
                        <i class="fas fa-plus"></i> <span id="btnSubmitText">Adicionar custo</span>
                    </button>
                </div>

                <?php else: ?>
                <?php $skipW = ($bParams['skip_weights'] ?? false); ?>
                <div class="form-row-3" style="align-items:end; margin-bottom:15px;">
                    <div class="form-group">
                        <label>Fornecedor</label>
                        <select name="provider_id" id="selProvider" class="vision-select">
                            <option value="interno">Interno</option>
                            <option value="outro">Outro</option>
                            <optgroup label="Freelancers">
                                <?php foreach ($providersList as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= $p['name'] ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    <?php if ($skipW): ?>
                    <div class="form-group">
                        <label>Total de palavras</label>
                        <input type="number" name="total_palavras" id="inpTotalPalavras" class="vision-input" placeholder="0">
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                        <label>Serviço</label>
                        <select name="cost_service" id="selCostService" class="vision-select">
                            <?php foreach ($servicesList as $s): ?><option><?= $s ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label>Valor unitário</label>
                        <input type="text" name="cost_value" id="inpCostValue" class="vision-input" placeholder="0,00">
                    </div>
                </div>
                <div class="text-center">
                    <button type="button" id="btnCancelEdit" class="vision-btn vision-btn-secondary" style="display:none; margin-right:10px;" onclick="cancelEdit()">
                        Cancelar
                    </button>
                    <button type="submit" id="btnSubmitCost" class="vision-btn vision-btn-success">
                        <i class="fas fa-plus"></i> <span id="btnSubmitText">Adicionar custo</span>
                    </button>
                </div>
                <?php endif; ?>
            </form>
            
            <div class="markup-tax-container mt-20">
                <div class="markup-tax-row">
                    <div class="markup-tax-item">
                        <label class="checkbox-inline">
                            <input type="checkbox" id="chkUseMarkup" <?= ($bParams['use_markup'] ?? true) ? 'checked' : '' ?>>
                            <span>Incluir markup</span>
                        </label>
                        <div class="markup-tax-input">
                            <input type="text" id="inMarkup" value="<?= $bParams['markup_pct'] ?>" class="vision-input text-center" <?= ($bParams['use_markup'] ?? true) ? '' : 'disabled' ?>>
                            <span class="input-suffix">%</span>
                        </div>
                    </div>
                    <div class="markup-tax-item">
                        <label class="checkbox-inline">
                            <input type="checkbox" id="chkUseTax" <?= ($bParams['use_tax'] ?? true) ? 'checked' : '' ?>>
                            <span>Incluir impostos</span>
                        </label>
                        <div class="markup-tax-input">
                            <input type="text" id="inTax" value="<?= $bParams['tax_pct'] ?>" class="vision-input text-center" <?= ($bParams['use_tax'] ?? true) ? '' : 'disabled' ?>>
                            <span class="input-suffix">%</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-20" id="calcArea" style="display:<?= empty($_SESSION['budget_costs']['items']) ? 'none' : 'block' ?>;">
                <button id="btnCalc" class="vision-btn vision-btn-primary" style="font-size:1.1rem; padding:15px 30px;">
                    <i class="fas fa-calculator"></i> Calcular orçamento
                </button>
            </div>

            <div id="resSummary" style="display:none;" class="cost-summary fade-in">
                <span style="font-size:0.9rem; color:#cbd5e1; text-transform:uppercase; letter-spacing:1px;">Preço final sugerido</span>
                <span id="txtFinalPrice" class="final-price">R$ 0,00</span>
                <div style="display:flex; justify-content:center; gap:20px; color:#94a3b8; font-size:0.9rem; margin-bottom:20px;">
                    <span>Base: <b id="txtSub"></b></span>
                    <span>Impostos: <b id="txtTax"></b></span>
                </div>
                <button id="btnOpenPdf" class="vision-btn vision-btn-success" style="font-size:1.1rem;">
                    <i class="fas fa-file-pdf"></i> Gerar PDF
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODALS -->
<div id="modalClient" class="vision-modal">
    <div class="vision-modal-content">
        <div class="modal-header"><h3>Novo cliente</h3><button class="btn-close" onclick="closeModal('modalClient')">&times;</button></div>
        <form id="fAddClient">
            <div class="form-group"><label>Nome da empresa</label><input type="text" name="new_client_name" class="vision-input" required></div>
            <div class="form-group"><label>Moeda padrão</label>
                <select name="new_client_currency" class="vision-select">
                    <option>BRL</option><option>USD</option><option>EUR</option>
                </select>
            </div>
            <div class="text-right"><button class="vision-btn vision-btn-primary">Cadastrar</button></div>
        </form>
    </div>
</div>

<div id="modalService" class="vision-modal">
    <div class="vision-modal-content">
        <div class="modal-header"><h3>Novo serviço</h3><button class="btn-close" onclick="closeModal('modalService')">&times;</button></div>
        <form id="fAddService">
            <div class="form-group"><label>Nome do serviço</label><input type="text" name="new_service_name" class="vision-input" placeholder="Ex: Interpretação" required></div>
            <div class="text-right"><button class="vision-btn vision-btn-primary">Salvar</button></div>
        </form>
    </div>
</div>

<div id="modalCurrency" class="vision-modal">
    <div class="vision-modal-content">
        <div class="modal-header"><h3>Nova moeda</h3><button class="btn-close" onclick="closeModal('modalCurrency')">&times;</button></div>
        <form id="fAddCurrency">
            <div class="form-group"><label>Código (3 letras)</label><input type="text" name="new_currency_code" class="vision-input" placeholder="Ex: GBP" maxlength="3" required style="text-transform:uppercase;"></div>
            <div class="text-right"><button class="vision-btn vision-btn-primary">Adicionar</button></div>
        </form>
    </div>
</div>

<!-- Modal Lauda -->
<div id="modalLauda" class="vision-modal">
    <div class="vision-modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-file-alt"></i> Configurar lauda</h3>
            <button class="btn-close" onclick="closeModal('modalLauda')">&times;</button>
        </div>
        <form id="fLauda">
            <div class="form-group">
                <label>Critério da lauda</label>
                <select name="lauda_base" id="modalLaudaBase" class="vision-select">
                    <option value="caracteres">Caracteres por lauda</option>
                    <option value="palavras">Palavras por lauda</option>
                </select>
            </div>
            
            <div id="espacosGroup" class="form-group">
                <label class="checkbox-inline">
                    <input type="checkbox" name="inclui_espacos" id="modalIncluiEspacos">
                    <span>Contagem inclui espaços</span>
                </label>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label id="lblModalPorLauda">Caracteres por lauda</label>
                    <input type="text" name="por_lauda" id="modalPorLauda" class="vision-input" value="1800" placeholder="1800">
                </div>
                <div class="form-group">
                    <label id="lblModalTotalDoc">Total de caracteres do documento</label>
                    <input type="text" name="total_doc" id="modalTotalDoc" class="vision-input" placeholder="0">
                </div>
            </div>
            <div class="form-group" style="background:rgba(139,92,246,0.1); padding:15px; border-radius:8px; text-align:center;">
                <label style="margin-bottom:5px;">Laudas calculadas</label>
                <div id="modalLaudasCalc" style="font-size:2rem; font-weight:bold; color:#c4b5fd;">0</div>
            </div>
            <div class="text-right mt-20">
                <button type="button" class="vision-btn vision-btn-secondary" onclick="closeModal('modalLauda')">Cancelar</button>
                <button type="submit" class="vision-btn vision-btn-primary">Confirmar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal PDF -->
<div id="modalPdf" class="vision-modal">
    <div class="vision-modal-content">
        <div class="modal-header"><h3><i class="fas fa-file-pdf"></i> Gerar PDF</h3><button class="btn-close" onclick="closeModal('modalPdf')">&times;</button></div>
        <form action="" method="POST" target="_blank">
            <input type="hidden" name="action" value="generate_pdf">
            
            <div class="form-group">
                <label>Prestador/Empresa emissora</label>
                <input type="text" name="provider_name" id="inpProviderName" class="vision-input" value="<?= htmlspecialchars($currentUserName) ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Cliente (empresa solicitante)</label>
                    <input type="text" name="company_name" id="inpCompanyName" class="vision-input" value="">
                </div>
                <div class="form-group">
                    <label>Aos cuidados de</label>
                    <input type="text" name="contact_name" id="inpContactName" class="vision-input">
                </div>
            </div>
            
            <div class="form-group">
                <label>Descrição dos serviços</label>
                <textarea name="service_description" id="inpServiceDesc" class="vision-input" rows="2" placeholder="Ex: Revisão de documento técnico..."></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Validade da proposta</label>
                    <input type="date" name="validity_date" id="inpValidityDate" class="vision-input">
                </div>
                <div class="form-group">
                    <label>Data de entrega</label>
                    <input type="date" name="delivery_date" id="inpDeliveryDate" class="vision-input">
                </div>
            </div>

            <div class="form-group">
                <label>Formas de pagamento</label>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
                    <label class="checkbox-inline"><input type="checkbox" name="payment_methods[]" value="PIX"> PIX</label>
                    <label class="checkbox-inline"><input type="checkbox" name="payment_methods[]" value="Boleto"> Boleto</label>
                    <label class="checkbox-inline"><input type="checkbox" name="payment_methods[]" value="Transf. Bancária"> Transferência</label>
                    <label class="checkbox-inline"><input type="checkbox" name="payment_methods[]" value="PayPal"> PayPal</label>
                    <label class="checkbox-inline"><input type="checkbox" name="payment_methods[]" value="Cartão de crédito"> Cartão</label>
                    <label class="checkbox-inline"><input type="checkbox" id="chkPaymentOther" onchange="togglePaymentOther()"> Outros</label>
                </div>
                <div id="paymentOtherField" class="payment-other-field">
                    <input type="text" name="payment_other" id="inpPaymentOther" class="vision-input" placeholder="Descreva outra forma de pagamento...">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Data de pagamento</label>
                    <input type="date" name="payment_date" id="inpPaymentDate" class="vision-input">
                </div>
                <div class="form-group">
                    <label>Desconto (opcional)</label>
                    <div class="discount-type-radio">
                        <label>
                            <input type="radio" name="discount_type" value="valor" checked onchange="updateDiscountPreview()">
                            <span>Valor</span>
                        </label>
                        <label>
                            <input type="radio" name="discount_type" value="percentual" onchange="updateDiscountPreview()">
                            <span>%</span>
                        </label>
                    </div>
                    <input type="text" name="discount_value" id="inpDiscountValue" class="vision-input" placeholder="0,00" oninput="updateDiscountPreview()">
                </div>
            </div>
            
            <div id="discountPreview" class="discount-highlight" style="display:none; margin-bottom:15px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="color:#f59e0b;"><i class="fas fa-tag"></i> Desconto aplicado</span>
                    <span id="discountPreviewText" style="font-weight:bold; color:#f59e0b;"></span>
                </div>
            </div>
            
            <div class="form-group">
                <label>Valor final</label>
                <input type="text" name="final_price" id="inpPdfPrice" class="vision-input" readonly style="background:rgba(16,185,129,0.2); font-weight:bold; color:#34d399; font-size:1.2rem;">
                <input type="hidden" name="original_price" id="inpPdfPriceOriginal" value="">
            </div>
            
            <div class="form-group">
                <label>Observações (opcional)</label>
                <textarea name="observations" id="inpObservations" class="vision-input" rows="2" placeholder="Informações adicionais para o cliente..."></textarea>
            </div>

            <div class="text-right mt-20">
                <button class="vision-btn vision-btn-success" onclick="setTimeout(()=>closeModal('modalPdf'), 500)">
                    <i class="fas fa-download"></i> Baixar PDF
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const isRevisao = <?= $isRevisao ? 'true' : 'false' ?>;
const skipWeightsInit = <?= ($bParams['skip_weights'] ?? false) ? 'true' : 'false' ?>;
const clientName = "<?= addslashes($bClient['company_name'] ?? $bClient['client_name'] ?? '') ?>";
const clientContact = "<?= addslashes($bClient['contact_name'] ?? '') ?>";
const clientEmail = "<?= addslashes($bClient['client_email'] ?? '') ?>";
const clientPhone = "<?= addslashes($bClient['client_phone'] ?? '') ?>";
const clientAddress = "<?= addslashes($bClient['client_address'] ?? '') ?>";
const serviceName = "<?= addslashes($bClient['service'] ?? '') ?>";
const langFrom = "<?= addslashes($bClient['lang_from'] ?? '') ?>";
const langTo = "<?= addslashes($bClient['lang_to'] ?? '') ?>";

const openModal = (id) => document.getElementById(id).classList.add('active');
const closeModal = (id) => document.getElementById(id).classList.remove('active');
const ajax = (data) => {
    const fd = new FormData();
    for(const k in data) fd.append(k, data[k]);
    return fetch('', {method:'POST', body:fd}).then(r=>r.json());
}

function togglePaymentOther() {
    const field = document.getElementById('paymentOtherField');
    const chk = document.getElementById('chkPaymentOther');
    field.classList.toggle('active', chk.checked);
}

function updateDiscountPreview() {
    const discountType = document.querySelector('input[name="discount_type"]:checked')?.value || 'valor';
    const discountValue = parseFloat(document.getElementById('inpDiscountValue').value.replace(/\./g, '').replace(',', '.')) || 0;
    const originalPriceStr = document.getElementById('inpPdfPriceOriginal').value;
    // Converter formato brasileiro (2.514,31) para número (2514.31)
    const originalPrice = parseFloat(originalPriceStr.replace(/\./g, '').replace(',', '.')) || 0;
    const preview = document.getElementById('discountPreview');
    const previewText = document.getElementById('discountPreviewText');
    const finalPriceInput = document.getElementById('inpPdfPrice');
    
    if (discountValue > 0 && originalPrice > 0) {
        let discountReais = 0;
        let discountPct = 0;
        
        if (discountType === 'percentual') {
            discountPct = discountValue;
            discountReais = originalPrice * (discountPct / 100);
        } else {
            discountReais = discountValue;
            discountPct = (discountReais / originalPrice) * 100;
        }
        
        // Arredondar para cima com 2 casas
        discountReais = Math.ceil(discountReais * 100) / 100;
        const finalPrice = Math.ceil((originalPrice - discountReais) * 100) / 100;
        
        previewText.textContent = `- R$ ${discountReais.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})} (${discountPct.toFixed(1).replace('.', ',')}%)`;
        preview.style.display = 'block';
        
        // Atualizar valor final
        finalPriceInput.value = finalPrice.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        finalPriceInput.style.background = 'linear-gradient(90deg, rgba(16,185,129,0.2), rgba(245,158,11,0.2))';
    } else {
        preview.style.display = 'none';
        if (originalPrice > 0) {
            finalPriceInput.value = originalPrice.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
        finalPriceInput.style.background = 'rgba(16,185,129,0.2)';
    }
}

function checkServiceType() {
    const service = document.getElementById('selService').value;
    const isRev = service.toLowerCase().includes('revis');
    const langToInput = document.getElementById('langToInput');
    
    if (isRev) {
        langToInput.disabled = true;
        langToInput.value = '';
        langToInput.placeholder = 'Não aplicável para revisão';
    } else {
        langToInput.disabled = false;
        langToInput.placeholder = 'Ex: EN-US';
    }
}

function updateModalLaudaLabels() {
    const base = document.getElementById('modalLaudaBase');
    const lblPorLauda = document.getElementById('lblModalPorLauda');
    const lblTotalDoc = document.getElementById('lblModalTotalDoc');
    const inpPorLauda = document.getElementById('modalPorLauda');
    const espacosGroup = document.getElementById('espacosGroup');
    
    if (base.value === 'caracteres') {
        lblPorLauda.textContent = 'Caracteres por lauda';
        lblTotalDoc.textContent = 'Total de caracteres do documento';
        inpPorLauda.value = '1800';
        espacosGroup.style.display = 'block';
    } else {
        lblPorLauda.textContent = 'Palavras por lauda';
        lblTotalDoc.textContent = 'Total de palavras do documento';
        inpPorLauda.value = '250';
        espacosGroup.style.display = 'none';
    }
    calcularLaudasModal();
}

function calcularLaudasModal() {
    const porLauda = parseFloat(document.getElementById('modalPorLauda')?.value.replace(',', '.')) || 0;
    const totalDoc = parseFloat(document.getElementById('modalTotalDoc')?.value.replace(',', '.')) || 0;
    
    if (porLauda > 0 && totalDoc > 0) {
        const laudas = Math.ceil(totalDoc / porLauda);
        document.getElementById('modalLaudasCalc').textContent = laudas;
    } else {
        document.getElementById('modalLaudasCalc').textContent = '0';
    }
}

function updateLaudaInfo() {
    const base = document.getElementById('hiddenLaudaBase').value;
    const porLauda = document.getElementById('hiddenPorLauda').value;
    const totalDoc = document.getElementById('hiddenTotalDoc').value;
    const qtd = document.getElementById('inpQuantidade').value;
    const incluiEspacos = document.getElementById('hiddenIncluiEspacos').value === '1';
    
    const infoDisplay = document.getElementById('laudaInfoDisplay');
    const infoText = document.getElementById('laudaInfoText');
    
    if (parseFloat(totalDoc) > 0) {
        const label = base === 'caracteres' ? 'caracteres' : 'palavras';
        let espacosText = base === 'caracteres' ? (incluiEspacos ? ', c/ espaços' : ', s/ espaços') : '';
        infoText.innerHTML = `<strong>${qtd} laudas</strong> (${porLauda} ${label}/lauda${espacosText} × ${totalDoc} ${label} total)`;
        infoDisplay.style.display = 'block';
    }
}

function editCost(idx, data) {
    document.getElementById('editIndex').value = idx;
    document.getElementById('btnSubmitText').textContent = 'Salvar alterações';
    document.getElementById('btnCancelEdit').style.display = 'inline-flex';
    
    if (isRevisao) {
        document.getElementById('selUnidade').value = data.unidade || 'Palavra';
        document.getElementById('inpQuantidade').value = data.quantidade || '';
        
        if (data.unidade === 'Lauda') {
            document.getElementById('hiddenLaudaBase').value = data.lauda_base || 'caracteres';
            document.getElementById('hiddenPorLauda').value = data.por_lauda || '1800';
            document.getElementById('hiddenTotalDoc').value = data.total_doc || '0';
            document.getElementById('hiddenIncluiEspacos').value = data.inclui_espacos ? '1' : '0';
            updateLaudaInfo();
        }
    } else {
        document.getElementById('selCostService').value = data.service || '';
    }
    
    document.getElementById('selProvider').value = data.provider_id || 'interno';
    document.getElementById('inpCostValue').value = data.unit_cost ? data.unit_cost.toString().replace('.', ',') : '';
    
    document.getElementById('fAddCost').scrollIntoView({behavior: 'smooth'});
}

function cancelEdit() {
    document.getElementById('editIndex').value = '-1';
    document.getElementById('btnSubmitText').textContent = 'Adicionar custo';
    document.getElementById('btnCancelEdit').style.display = 'none';
    document.getElementById('fAddCost').reset();
    
    if (isRevisao) {
        document.getElementById('laudaInfoDisplay').style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    checkServiceType();
    
    const selService = document.getElementById('selService');
    if (selService) {
        selService.addEventListener('change', checkServiceType);
    }
    
    const modalLaudaBase = document.getElementById('modalLaudaBase');
    const modalPorLauda = document.getElementById('modalPorLauda');
    const modalTotalDoc = document.getElementById('modalTotalDoc');
    
    if (modalLaudaBase) modalLaudaBase.addEventListener('change', updateModalLaudaLabels);
    if (modalPorLauda) modalPorLauda.addEventListener('input', calcularLaudasModal);
    if (modalTotalDoc) modalTotalDoc.addEventListener('input', calcularLaudasModal);
    
    const selUnidade = document.getElementById('selUnidade');
    const inpQuantidade = document.getElementById('inpQuantidade');
    const laudaInfoDisplay = document.getElementById('laudaInfoDisplay');
    
    if (selUnidade) {
        selUnidade.addEventListener('change', function() {
            if (this.value === 'Lauda') {
                if (laudaInfoDisplay) {
                    laudaInfoDisplay.style.display = 'block';
                    document.getElementById('laudaInfoText').innerHTML = '<i class="fas fa-hand-pointer"></i> Clique no campo quantidade para configurar a lauda';
                }
            } else {
                if (laudaInfoDisplay) laudaInfoDisplay.style.display = 'none';
                document.getElementById('hiddenTotalDoc').value = '0';
            }
        });
        
        if (selUnidade.value === 'Lauda' && laudaInfoDisplay) {
            laudaInfoDisplay.style.display = 'block';
        }
    }
    
    if (inpQuantidade) {
        inpQuantidade.addEventListener('focus', function() {
            if (selUnidade && selUnidade.value === 'Lauda') {
                openModal('modalLauda');
                this.blur();
            }
        });
    }
    
    // Checkbox markup
    const chkUseMarkup = document.getElementById('chkUseMarkup');
    const inMarkup = document.getElementById('inMarkup');
    if (chkUseMarkup && inMarkup) {
        chkUseMarkup.addEventListener('change', function() {
            inMarkup.disabled = !this.checked;
        });
    }
    
    // Checkbox impostos
    const chkUseTax = document.getElementById('chkUseTax');
    const inTax = document.getElementById('inTax');
    if (chkUseTax && inTax) {
        chkUseTax.addEventListener('change', function() {
            inTax.disabled = !this.checked;
        });
    }
    
    // Checkbox skip weights
    const chkSkipWeights = document.getElementById('chkSkipWeights');
    const weightsFieldset = document.getElementById('weightsFieldset');
    const weightsSubmitText = document.getElementById('weightsSubmitText');
    
    function updateWeightsUI() {
        if (chkSkipWeights && weightsFieldset) {
            const skip = chkSkipWeights.checked;
            weightsFieldset.style.opacity = skip ? '0.4' : '1';
            weightsFieldset.style.pointerEvents = skip ? 'none' : 'auto';
            if (weightsSubmitText) {
                weightsSubmitText.textContent = skip ? 'Pular e avançar' : 'Atualizar pesos';
            }
        }
    }
    
    if (chkSkipWeights) {
        chkSkipWeights.addEventListener('change', updateWeightsUI);
        updateWeightsUI(); // Estado inicial
    }
    
    const btnOpenPdf = document.getElementById('btnOpenPdf');
    if (btnOpenPdf) {
        btnOpenPdf.addEventListener('click', function() {
            // Preencher dados do cliente do BD
            document.getElementById('inpCompanyName').value = clientName;
            document.getElementById('inpContactName').value = clientContact;
            
            // Pegar apenas o nome do serviço (sem idioma se já estiver incluído)
            let desc = serviceName.split(' | Idioma:')[0].trim();
            // Não mostrar idioma se for revisão
            if (!isRevisao && langFrom) {
                desc += ' | Idioma: ' + langFrom;
                if (langTo) desc += ' → ' + langTo;
            }
            document.getElementById('inpServiceDesc').value = desc;
            
            const today = new Date();
            const validity = new Date(today);
            validity.setDate(today.getDate() + 5);
            const delivery = new Date(today);
            delivery.setDate(today.getDate() + 15);
            const payment = new Date(delivery);
            payment.setDate(delivery.getDate() + 30);
            
            document.getElementById('inpValidityDate').value = validity.toISOString().split('T')[0];
            document.getElementById('inpDeliveryDate').value = delivery.toISOString().split('T')[0];
            document.getElementById('inpPaymentDate').value = payment.toISOString().split('T')[0];
            
            // Guardar valor original para cálculos de desconto
            const originalPrice = document.getElementById('inpPdfPrice').value;
            document.getElementById('inpPdfPriceOriginal').value = originalPrice;
            
            // Limpar campos de desconto
            document.getElementById('inpDiscountValue').value = '';
            const radioValor = document.querySelector('input[name="discount_type"][value="valor"]');
            if (radioValor) radioValor.checked = true;
            document.getElementById('discountPreview').style.display = 'none';
            
            openModal('modalPdf');
        });
    }
});

document.getElementById('fProject').onsubmit = (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append('ajax_action', 'update_client_session');
    fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(r => {
        if(r.success) {
            if (r.isRevisao) {
                // Só scrollar para custos se for revisão
                window.location.href = window.location.pathname + '#cardCosts';
                location.reload();
            } else {
                // Não scrollar - próximo campo é Configuração de pesos (na parte superior)
                location.reload();
            }
        }
    });
};

document.getElementById('fAddClient').onsubmit = (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append('ajax_action', 'add_client_db');
    fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(r => {
        if(r.success) {
            const sel = document.getElementById('selClient');
            const opt = new Option(r.name, r.id);
            opt.selected = true;
            sel.add(opt);
            document.getElementById('selCurrency').value = r.currency;
            closeModal('modalClient');
        } else alert(r.message);
    });
};

document.getElementById('fAddService').onsubmit = (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append('ajax_action', 'add_service_db');
    fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(r => {
        if(r.success) {
            const s1 = document.getElementById('selService');
            const s2 = document.querySelector('[name="cost_service"]');
            [s1, s2].forEach(s => {
                if(s) {
                    const opt = new Option(r.name, r.name);
                    if(s === s1) opt.selected = true;
                    s.add(opt);
                }
            });
            closeModal('modalService');
        } else alert(r.message);
    });
};

document.getElementById('fAddCurrency').onsubmit = (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append('ajax_action', 'add_currency_db');
    fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(r => {
        if(r.success) {
            const sel = document.getElementById('selCurrency');
            const opt = new Option(r.code, r.code);
            opt.selected = true;
            sel.add(opt);
            closeModal('modalCurrency');
        } else alert(r.message);
    });
};

const fWeights = document.getElementById('fWeights');
if (fWeights) {
    fWeights.onsubmit = (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.append('ajax_action', 'update_weights');
        
        // Adicionar flag de skip
        const skipWeights = document.getElementById('chkSkipWeights')?.checked;
        fd.append('skip_weights', skipWeights ? '1' : '0');
        
        fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(r => { 
            if(r.success) {
                if (r.skipWeights) {
                    // Se pulou pesos, ir para custos
                    window.location.href = window.location.pathname + '#cardCosts';
                }
                location.reload(); 
            }
        });
    };
}

const fLauda = document.getElementById('fLauda');
if (fLauda) {
    fLauda.onsubmit = (e) => {
        e.preventDefault();
        
        const base = document.getElementById('modalLaudaBase').value;
        const porLauda = document.getElementById('modalPorLauda').value;
        const totalDoc = document.getElementById('modalTotalDoc').value;
        const laudas = document.getElementById('modalLaudasCalc').textContent;
        const incluiEspacos = document.getElementById('modalIncluiEspacos').checked;
        
        document.getElementById('hiddenLaudaBase').value = base;
        document.getElementById('hiddenPorLauda').value = porLauda;
        document.getElementById('hiddenTotalDoc').value = totalDoc;
        document.getElementById('hiddenIncluiEspacos').value = incluiEspacos ? '1' : '0';
        document.getElementById('inpQuantidade').value = laudas;
        
        updateLaudaInfo();
        
        closeModal('modalLauda');
    };
}

document.getElementById('fAddCost').onsubmit = (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const editIdx = document.getElementById('editIndex').value;
    
    if (editIdx !== '-1') {
        fd.append('ajax_action', 'update_cost');
        fd.append('index', editIdx);
    } else {
        fd.append('ajax_action', 'add_cost');
    }
    
    fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(r => {
        if(r.success) location.reload(); else alert(r.message);
    });
};

window.removeItem = (type, idx) => {
    if(confirm('Tem certeza?')) {
        ajax({ajax_action: 'remove_item', type: type, index: idx}).then(r => location.reload());
    }
}

const fUpload = document.getElementById('fUpload');
if (fUpload) {
    fUpload.onsubmit = (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        fetch('', {method:'POST', body:fd}).then(r=>r.json()).then(r => {
            if(r.success) location.reload(); else alert(r.message || 'Erro no upload');
        });
    }
}

const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const uploadForm = document.getElementById('fUpload');

if (dropZone && fileInput && uploadForm) {
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.style.borderColor = '#8b5cf6';
            dropZone.style.backgroundColor = 'rgba(139, 92, 246, 0.1)';
            dropZone.style.transform = 'scale(1.02)';
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.style.borderColor = 'rgba(255,255,255,0.1)';
            dropZone.style.backgroundColor = 'rgba(255,255,255,0.05)';
            dropZone.style.transform = 'scale(1)';
        }, false);
    });

    dropZone.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        let allValid = true;

        if (files.length > 0) {
            for (let i = 0; i < files.length; i++) {
                const fileName = files[i].name.toLowerCase();
                if (!fileName.endsWith('.csv') && !fileName.endsWith('.txt')) {
                    allValid = false;
                    break;
                }
            }

            if (!allValid) {
                alert('Formato inválido! Somente arquivos CSV ou TXT de análises geradas por CAT Tools são aceitos.');
                dropZone.style.borderColor = 'rgba(255,255,255,0.1)';
                dropZone.style.backgroundColor = 'rgba(255,255,255,0.05)';
                dropZone.style.transform = 'scale(1)';
                return;
            }

            fileInput.files = files;
            uploadForm.dispatchEvent(new Event('submit'));
        }
    }
}

document.getElementById('btnCalc').onclick = () => {
    const markup = document.getElementById('inMarkup').value;
    const tax = document.getElementById('inTax').value;
    const useMarkup = document.getElementById('chkUseMarkup').checked ? '1' : '0';
    const useTax = document.getElementById('chkUseTax').checked ? '1' : '0';
    
    ajax({ajax_action: 'calculate', markup: markup, tax: tax, use_markup: useMarkup, use_tax: useTax}).then(r => {
        if(r.success) {
            const d = r.results;
            const fmt = (v) => parseFloat(v).toLocaleString('pt-BR', {style:'currency', currency:d.currency, minimumFractionDigits: 2, maximumFractionDigits: 2});
            
            document.getElementById('txtFinalPrice').innerText = fmt(d.precoFinal);
            document.getElementById('inpPdfPrice').value = d.precoFinal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('txtSub').innerText = fmt(d.subtotal);
            document.getElementById('txtTax').innerText = fmt(d.impostos);
            
            document.getElementById('resSummary').style.display = 'block';
            document.getElementById('resSummary').scrollIntoView({behavior:'smooth'});
        } else alert(r.message);
    });
}

document.getElementById('selClient').onchange = (e) => {
    const opt = e.target.options[e.target.selectedIndex];
    const curr = opt.dataset.curr;
    if(curr) document.getElementById('selCurrency').value = curr;
    
    // Atualizar variáveis globais com dados do cliente selecionado
    window.selectedClientCompany = opt.dataset.company || '';
    window.selectedClientContact = opt.dataset.contact || '';
    window.selectedClientEmail = opt.dataset.email || '';
    window.selectedClientPhone = opt.dataset.phone || '';
    window.selectedClientAddress = opt.dataset.address || '';
}

if (window.location.hash === '#cardCosts') {
    setTimeout(() => {
        document.getElementById('cardCosts')?.scrollIntoView({behavior: 'smooth'});
    }, 300);
}
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>