<?php
session_start();
// Aumenta o tempo máximo de execução do script para 10 minutos (600 segundos)
set_time_limit(600);
ob_implicit_flush(true);

require_once __DIR__ . '/../config/database.php';

// Proteção: Apenas administradores podem executar este script
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: /login.php');
    exit;
}

// Função para fazer chamadas à API da Hotmart
function call_hotmart_api($url, $access_token) {
    $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $access_token];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http_code === 200) {
        return ['error' => false, 'body' => json_decode($response, true)];
    }
    return ['error' => true, 'http_code' => $http_code, 'response' => $response];
}

try {
    // --- 1. Obter Token de Acesso ---
    $basic_auth = 'Basic N2UzZDM0MmQtYWY0Zi00MTkwLTk1OWMtNmE5NzU0NmYxNDM3OjZmNjI1NzZmLTQzMzUtNDBkMC04N2FhLThhNThmMDlkZjdmZA==';
    $token_url = 'https://api-sec-vlc.hotmart.com/security/oauth/token';
    $ch_token = curl_init($token_url);
    curl_setopt($ch_token, CURLOPT_POST, 1);
    curl_setopt($ch_token, CURLOPT_POSTFIELDS, http_build_query(['grant_type' => 'client_credentials']));
    curl_setopt($ch_token, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_token, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded', 'Authorization: ' . $basic_auth]);
    $response_token = curl_exec($ch_token);
    $http_code_token = curl_getinfo($ch_token, CURLINFO_HTTP_CODE);
    curl_close($ch_token);
    if ($http_code_token !== 200) throw new Exception("Falha ao obter token de acesso da Hotmart.");
    $token_data = json_decode($response_token, true);
    $access_token = $token_data['access_token'] ?? null;
    if (!$access_token) throw new Exception("Token de acesso não encontrado.");

    // --- 2. Buscar TODOS os Alunos da Hotmart com Paginação ---
    $subdomain = 'assinaturapremiumplustranslato';
    $all_hotmart_students = [];
    $next_page_token = null;
    do {
        $query_params = ['subdomain' => $subdomain, 'max_results' => 100];
        if ($next_page_token) {
            $query_params['page_token'] = $next_page_token;
        }
        $students_url = 'https://developers.hotmart.com/club/api/v1/users?' . http_build_query($query_params);
        $students_response = call_hotmart_api($students_url, $access_token);
        if ($students_response['error']) throw new Exception('Falha ao buscar a lista de alunos na paginação. Código: ' . $students_response['http_code']);
        if (!empty($students_response['body']['items'])) {
            $all_hotmart_students = array_merge($all_hotmart_students, $students_response['body']['items']);
        }
        $next_page_token = $students_response['body']['page_info']['next_page_token'] ?? null;
    } while ($next_page_token);

    if (empty($all_hotmart_students)) throw new Exception("Nenhum aluno encontrado na Hotmart para sincronizar.");

    // --- 3. Sincronizar com o Banco de Dados Local ---
    $new_certificates_count = 0;
    
    $pdo->beginTransaction();

    foreach ($all_hotmart_students as $hotmart_user) {
        if (empty($hotmart_user['email'])) continue;

        // Verifica se o usuário local existe
        $stmt_find_user = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt_find_user->execute([$hotmart_user['email']]);
        $local_user = $stmt_find_user->fetch();

        if ($local_user && !empty($hotmart_user['user_id'])) {
            $local_user_id = $local_user['id'];
            $local_user_name = $local_user['name'];
            
            if (isset($hotmart_user['progress']['completed']) && $hotmart_user['progress']['completed'] > 0) {
                $lessons_url = 'https://developers.hotmart.com/club/api/v1/users/' . $hotmart_user['user_id'] . '/lessons?subdomain=' . $subdomain;
                $lessons_response = call_hotmart_api($lessons_url, $access_token);

                if (!$lessons_response['error'] && !empty($lessons_response['body']['lessons'])) {
                    foreach ($lessons_response['body']['lessons'] as $lesson) {
                        if (isset($lesson['is_completed']) && $lesson['is_completed'] == 1) {
                            $lecture_title = trim($lesson['page_name']);
                            
                            $stmt_find_lecture = $pdo->prepare("SELECT id FROM lectures WHERE title = ?");
                            $stmt_find_lecture->execute([$lecture_title]);
                            $lecture = $stmt_find_lecture->fetch();
                            
                            if ($lecture) {
                                $lecture_id = $lecture['id'];
                                
                                $stmt_check_cert = $pdo->prepare("SELECT id FROM certificates WHERE user_id = ? AND lecture_id = ?");
                                $stmt_check_cert->execute([$local_user_id, $lecture_id]);
                                
                                if (!$stmt_check_cert->fetch()) {
                                    $cert_id = 'cert_' . uniqid();
                                    $completion_timestamp = $lesson['completed_date'] / 1000;
                                    $issued_date = date('Y-m-d H:i:s', $completion_timestamp);
                                    
                                    $stmt_insert_cert = $pdo->prepare(
                                        "INSERT INTO certificates (id, user_id, lecture_id, user_name, lecture_title, speaker_name, duration_hours, issued_at, created_at)
                                         VALUES (?, ?, ?, ?, ?, (SELECT speaker FROM lectures WHERE id = ?), (SELECT duration_minutes/60.0 FROM lectures WHERE id = ?), ?, ?)"
                                    );
                                    $stmt_insert_cert->execute([$cert_id, $local_user_id, $lecture_id, $local_user_name, $lecture_title, $lecture_id, $lecture_id, $issued_date, $issued_date]);
                                    $new_certificates_count++;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    $pdo->commit();
    $_SESSION['admin_message'] = "Sincronização completa! Total de alunos processados: " . count($all_hotmart_students) . ". Novos certificados registrados: " . $new_certificates_count . ".";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['admin_error'] = "Erro na sincronização: " . $e->getMessage();
}

header('Location: index.php');
exit;
?>