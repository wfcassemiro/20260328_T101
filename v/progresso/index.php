<?php
// Aumenta o tempo máximo de execução do script para 10 minutos (600 segundos)
// Essencial para processar todos os alunos sem ser interrompido pelo servidor.
set_time_limit(600);
ob_implicit_flush(true);

// =============================================================================
// FUNÇÃO PARA FAZER CHAMADAS À API (com retorno de erro detalhado)
// =============================================================================
function call_hotmart_api($url, $access_token = null) {
    $headers = ['Content-Type: application/json'];
    if ($access_token) {
        $headers[] = 'Authorization: Bearer ' . $access_token;
    }

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
    // Retorna um array de erro para diagnóstico
    return ['error' => true, 'http_code' => $http_code, 'response' => $response];
}

// =============================================================================
// FUNÇÃO PRINCIPAL PARA BUSCAR TODOS OS DADOS
// =============================================================================
function fetch_all_student_data() {
    // --- Configurações ---
    $hotmart_club_subdomain = 'assinaturapremiumplustranslato';
    $basic_auth = 'Basic N2UzZDM0MmQtYWY0Zi00MTkwLTk1OWMtNmE5NzU0NmYxNDM3OjZmNjI1NzZmLTQzMzUtNDBkMC04N2FhLThhNThmMDlkZjdmZA==';

    // --- Parte 1: Obter Token ---
    $token_url = 'https://api-sec-vlc.hotmart.com/security/oauth/token';
    $ch_token = curl_init($token_url);
    curl_setopt($ch_token, CURLOPT_POST, 1);
    curl_setopt($ch_token, CURLOPT_POSTFIELDS, http_build_query(['grant_type' => 'client_credentials']));
    curl_setopt($ch_token, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_token, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded', 'Authorization: ' . $basic_auth]);
    $response_token = curl_exec($ch_token);
    $http_code_token = curl_getinfo($ch_token, CURLINFO_HTTP_CODE);
    curl_close($ch_token);

    if ($http_code_token !== 200) { return ['error' => 'Falha ao obter token de acesso.']; }
    $token_data = json_decode($response_token, true);
    $access_token = $token_data['access_token'] ?? null;
    if (!$access_token) { return ['error' => 'Token de acesso não encontrado.']; }

    // --- Parte 2: Buscar TODOS os Alunos com Paginação ---
    $all_students = [];
    $next_page_token = null;
    do {
        $query_params = ['subdomain' => $hotmart_club_subdomain, 'max_results' => 100];
        if ($next_page_token) {
            $query_params['page_token'] = $next_page_token;
        }

        $students_url = 'https://developers.hotmart.com/club/api/v1/users?' . http_build_query($query_params);
        $students_response = call_hotmart_api($students_url, $access_token);
        
        if ($students_response['error']) { 
            return ['error' => 'Falha ao buscar a lista de alunos na paginação. Código: ' . $students_response['http_code']];
        }

        if (!empty($students_response['body']['items'])) {
            $all_students = array_merge($all_students, $students_response['body']['items']);
        }
        $next_page_token = $students_response['body']['page_info']['next_page_token'] ?? null;

    } while ($next_page_token);

    // --- Parte 3: Buscar Lições Concluídas para cada aluno ---
    foreach ($all_students as $key => $student) {
        $completed_lessons_names = [];
        $has_progress = isset($student['progress']['completed']) && $student['progress']['completed'] > 0;
        
        if ($has_progress && isset($student['user_id'])) {
            $user_id = $student['user_id'];
            $lessons_url = 'https://developers.hotmart.com/club/api/v1/users/' . $user_id . '/lessons?subdomain=' . $hotmart_club_subdomain;
            $lessons_response = call_hotmart_api($lessons_url, $access_token);

            if ($lessons_response['error']) {
                $completed_lessons_names[] = "[Erro ao buscar lições (Código: " . $lessons_response['http_code'] . ")]";
            } elseif (isset($lessons_response['body']['lessons']) && !empty($lessons_response['body']['lessons'])) {
                // CORREÇÃO APLICADA AQUI, COM BASE NOS DADOS INSPECIONADOS
                foreach ($lessons_response['body']['lessons'] as $lesson) {
                    if (isset($lesson['is_completed']) && $lesson['is_completed'] == 1) {
                        $completed_lessons_names[] = $lesson['page_name'];
                    }
                }
            }
        }
        $all_students[$key]['completed_lessons'] = $completed_lessons_names;
    }
    
    return ['students' => $all_students];
}

// =============================================================================
// LÓGICA DE EXPORTAÇÃO PARA CSV
// =============================================================================
if (isset($_POST['export_csv'])) {
    $data = fetch_all_student_data();
    $students = $data['students'] ?? [];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=progresso_alunos_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); 
    fputcsv($output, ['Nome', 'Email', 'Progresso (%)', 'Lições Concluídas']);

    if (!empty($students)) {
        foreach ($students as $student) {
            $nome = $student['name'] ?? 'N/A';
            $email = $student['email'] ?? 'N/A';
            $progresso = $student['progress']['completed_percentage'] ?? 'N/A';
            $licoes = !empty($student['completed_lessons']) ? implode("\n", $student['completed_lessons']) : 'Nenhuma';
            fputcsv($output, [$nome, $email, $progresso, $licoes]);
        }
    }

    fclose($output);
    exit;
}

// =============================================================================
// LÓGICA PARA EXIBIÇÃO NA PÁGINA
// =============================================================================
$page_data = fetch_all_student_data();
$students = $page_data['students'] ?? [];
$error_message = $page_data['error'] ?? null;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progresso dos Alunos - Translators101</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; background-color: #f4f4f9; color: #333; }
        .container { max-width: 1200px; margin: 40px auto; padding: 20px; background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-radius: 8px; }
        h1 { color: #2c3e50; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; table-layout: fixed; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #ddd; word-wrap: break-word; vertical-align: top; }
        th { background-color: #ecf0f1; font-weight: 600; }
        th:nth-child(1), th:nth-child(2) { width: 25%; }
        th:nth-child(3) { width: 10%; }
        th:nth-child(4) { width: 40%; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .error-box { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; margin-top: 20px; border-radius: 4px; }
        .info-box { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; padding: 15px; margin-top: 20px; border-radius: 4px; }
        .export-button { background-color: #27ae60; color: white; padding: 10px 15px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; float: right; margin-bottom: 10px; }
        .export-button:hover { background-color: #229954; }
        .header-area { overflow: hidden; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-area">
            <form method="post">
                <button type="submit" name="export_csv" class="export-button">Exportar para CSV</button>
            </form>
            <h1>Progresso dos Alunos</h1>
        </div>

        <?php if ($error_message): ?>
            <div class="error-box"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php elseif (empty($students)): ?>
            <div class="info-box"><p>Nenhum aluno encontrado.</p></div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Progresso</th>
                        <th>Lições Concluídas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <?php
                                $nome = $student['name'] ?? 'N/A';
                                $email = $student['email'] ?? 'N/A';
                                $progresso = isset($student['progress']['completed_percentage']) ? $student['progress']['completed_percentage'] . '%' : 'N/A';
                                $licoes_html = !empty($student['completed_lessons']) ? implode('<br>', array_map('htmlspecialchars', $student['completed_lessons'])) : 'Nenhuma';
                            ?>
                            <td><?php echo htmlspecialchars($nome); ?></td>
                            <td><?php echo htmlspecialchars($email); ?></td>
                            <td><?php echo htmlspecialchars($progresso); ?></td>
                            <td><?php echo $licoes_html; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>