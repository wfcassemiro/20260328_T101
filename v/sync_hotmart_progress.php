<?php
@set_time_limit(1800); // 30 minutos
@ini_set('implicit_flush', true);
@ob_implicit_flush(true);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

function send_message($type, $message, $progress = -1) {
    $data = ['type' => $type, 'message' => $message, 'progress' => $progress];
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush();
    flush();
}

// ===== INCLUIR HELPERS DE GERAÇÃO =====
require_once __DIR__ . '/../helpers/certificate_generator_helper.php';
require_once __DIR__ . '/../includes/certificate_pdf_generator.php';

// Logger simples para os helpers
function writeToCustomLog($message) {
    error_log($message);
    // Opcional: enviar mensagem para o frontend
    if (strpos($message, 'ERRO') !== false) {
        send_message('warning', strip_tags($message));
    }
}

require_once __DIR__ . '/../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    send_message('error', 'Acesso negado.');
    exit;
}

define('HOTMART_BASIC_AUTH', 'Basic N2UzZDM0MmQtYWY0Zi00MTkwLTk1OWMtNmE5NzU0NmYxNDM3OjZmNjI1NzZmLTQzMzUtNDBkMC04N2FhLThhNThmMDlkZjdmZA==');
define('HOTMART_ACCESS_TOKEN_URL', 'https://api-sec-vlc.hotmart.com/security/oauth/token');
define('HOTMART_API_BASE_URL', 'https://developers.hotmart.com');
$hotmart_club_subdomain = 'assinaturapremiumplustranslato';

function get_hotmart_access_token() {
    $ch = curl_init(HOTMART_ACCESS_TOKEN_URL);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => 1, CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'client_credentials']), CURLOPT_HTTPHEADER => ['Authorization: ' . HOTMART_BASIC_AUTH, 'Content-Type: application/x-www-form-urlencoded']]);
    $response = curl_exec($ch); $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($http_code !== 200) return null;
    return json_decode($response, true)['access_token'] ?? null;
}

function call_hotmart_api($url, $access_token) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $access_token, 'Content-Type: application/json'], CURLOPT_CONNECTTIMEOUT => 30, CURLOPT_TIMEOUT => 120]);
    $response = curl_exec($ch); $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($http_code !== 200) return null;
    return json_decode($response, true);
}

try {
    send_message('info', 'Iniciando sincronização...');
    $access_token = get_hotmart_access_token();
    if (!$access_token) throw new Exception("Falha na autenticação com a Hotmart.");

    // Mapeamento Robusto
    $stmt_map_page = $pdo->query("SELECT hotmart_page_id, lecture_id FROM hotmart_lecture_mapping WHERE hotmart_page_id IS NOT NULL AND hotmart_page_id != ''");
    $page_to_lecture_map = $stmt_map_page->fetchAll(PDO::FETCH_KEY_PAIR);
    
    if (empty($page_to_lecture_map)) {
        throw new Exception('Tabela de mapeamento (por page_id) está vazia. Execute o script de mapeamento primeiro.');
    }

    send_message('info', 'Buscando usuários da Hotmart...');
    $all_hotmart_users = [];
    $next_page_token = null;
    do {
        $params = ['subdomain' => $hotmart_club_subdomain, 'max_results' => 100];
        if ($next_page_token) $params['page_token'] = $next_page_token;
        $users_data = call_hotmart_api(HOTMART_API_BASE_URL . '/club/api/v1/users?' . http_build_query($params), $access_token);
        if (!empty($users_data['items'])) $all_hotmart_users = array_merge($all_hotmart_users, $users_data['items']);
        $next_page_token = $users_data['page_info']['next_page_token'] ?? null;
    } while ($next_page_token);

    if (empty($all_hotmart_users)) throw new Exception('Nenhum usuário encontrado na Hotmart.');
    $total_users = count($all_hotmart_users);

    $stmt_status = $pdo->query("SELECT last_processed_email FROM hotmart_sync_status WHERE id = 1");
    $last_email = $stmt_status->fetchColumn();
    $start_index = 0;
    if ($last_email) {
        send_message('info', "Retomando a partir de: {$last_email}");
        foreach ($all_hotmart_users as $index => $user) {
            if ($user['email'] == $last_email) {
                $start_index = $index + 1;
                break;
            }
        }
    }
    
    $users_to_process = array_slice($all_hotmart_users, $start_index);
    send_message('info', "{$total_users} usuários encontrados. Faltam " . count($users_to_process) . " para processar.");

    $certificates_created = 0; 
    $files_generated = 0;
    $users_processed_this_run = 0;

    foreach ($users_to_process as $hotmart_user) {
        if (!isset($hotmart_user['email']) || !isset($hotmart_user['user_id'])) continue;

        $pdo->prepare("UPDATE hotmart_sync_status SET last_processed_email = ? WHERE id = 1")->execute([$hotmart_user['email']]);
        
        $users_processed_this_run++;
        $progress = round((($start_index + $users_processed_this_run) / $total_users) * 100);
        send_message('progress', "Processando " . ($start_index + $users_processed_this_run) . "/{$total_users}: " . $hotmart_user['name'], $progress);

        $stmt_local_user = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND is_active = 1");
        $stmt_local_user->execute([$hotmart_user['email']]);
        if (!$local_user = $stmt_local_user->fetch(PDO::FETCH_ASSOC)) continue;
        
        $lessons_url = HOTMART_API_BASE_URL . "/club/api/v1/users/{$hotmart_user['user_id']}/lessons?" . http_build_query(['subdomain' => $hotmart_club_subdomain]);
        $lessons_data = call_hotmart_api($lessons_url, $access_token);
        
        if (isset($lessons_data['lessons'])) {
            $pdo->beginTransaction();
            foreach ($lessons_data['lessons'] as $lesson) {
                if (!empty($lesson['is_completed'])) {
                    
                    $page_id_from_api = (string)($lesson['page_id'] ?? '');
                    $lecture_id = null;

                    if ($page_id_from_api && isset($page_to_lecture_map[$page_id_from_api])) {
                        $lecture_id = $page_to_lecture_map[$page_id_from_api];
                    }
                    
                    if ($lecture_id) {
                        $stmt_check = $pdo->prepare("SELECT id FROM certificates WHERE user_id = ? AND lecture_id = ?");
                        $stmt_check->execute([$local_user['id'], $lecture_id]);
                        if ($stmt_check->fetch()) continue;
                        
                        $stmt_lecture = $pdo->prepare("SELECT title, speaker, duration_minutes FROM lectures WHERE id = ?");
                        $stmt_lecture->execute([$lecture_id]);
                        if ($lecture_details = $stmt_lecture->fetch(PDO::FETCH_ASSOC)) {
                            $new_cert_id = 'cert_' . bin2hex(random_bytes(8));
                            $duration_hours = ($lecture_details['duration_minutes'] ?? 60) / 60.0;
                            
                            // Inserir no banco
                            $stmt_insert = $pdo->prepare("INSERT INTO certificates (id, user_id, lecture_id, user_name, lecture_title, speaker_name, duration_hours, issued_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                            $stmt_insert->execute([$new_cert_id, $local_user['id'], $lecture_id, $local_user['name'], $lecture_details['title'], $lecture_details['speaker'], number_format($duration_hours, 1)]);
                            $certificates_created++;
                            
                            // ===== GERAR ARQUIVOS PNG E PDF =====
                            $certificate_data = [
                                'user_name' => $local_user['name'],
                                'lecture_title' => $lecture_details['title'],
                                'speaker_name' => $lecture_details['speaker'],
                                'duration_minutes' => $lecture_details['duration_minutes'] ?? 60
                            ];
                            
                            // Gerar PNG
                            $png_path = generateAndSaveCertificatePng(
                                $new_cert_id,
                                $certificate_data,
                                'SYNC_HOTMART',
                                'writeToCustomLog'
                            );
                            
                            // Gerar PDF
                            if ($png_path) {
                                $pdf_path = generateCertificatePDF(
                                    $new_cert_id,
                                    $certificate_data,
                                    'SYNC_HOTMART',
                                    'writeToCustomLog'
                                );
                                
                                if ($pdf_path) {
                                    $files_generated++;
                                    send_message('info', "✅ Certificado gerado: {$lecture_details['title']}");
                                }
                            }
                        }
                    }
                }
            }
            $pdo->commit();
        }
        usleep(50000); 
    }

    $pdo->prepare("UPDATE hotmart_sync_status SET last_processed_email = NULL WHERE id = 1")->execute();
    
    $message = "Sincronização concluída! Certificados criados: {$certificates_created}. Arquivos gerados: {$files_generated}.";
    send_message('done', $message);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    send_message('error', 'Erro na sincronização: ' . $e->getMessage());
}
exit;