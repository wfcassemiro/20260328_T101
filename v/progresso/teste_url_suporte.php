<?php
// =============================================================================
// CONFIGURAÇÕES E CREDENCIAIS
// =============================================================================
$hotmart_club_subdomain = 'assinaturapremiumplustranslato';
$basic_auth = 'Basic N2UzZDM0MmQtYWY0Zi00MTkwLTk1OWMtNmE5NzU0NmYxNDM3OjZmNjI1NzZmLTQzMzUtNDBkMC04N2FhLThhNThmMDlkZjdmZA==';

// Variáveis para armazenar os resultados
$students = [];
$error_message = null;
$access_token = null;

// =============================================================================
// PARTE 1: OBTER O TOKEN DE ACESSO
// =============================================================================
$token_url = 'https://api-sec-vlc.hotmart.com/security/oauth/token';
$ch_token = curl_init($token_url);
curl_setopt($ch_token, CURLOPT_POST, 1);
curl_setopt($ch_token, CURLOPT_POSTFIELDS, http_build_query(['grant_type' => 'client_credentials']));
curl_setopt($ch_token, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch_token, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'Authorization: ' . $basic_auth
]);
curl_setopt($ch_token, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch_token, CURLOPT_TIMEOUT, 20);

$response_token = curl_exec($ch_token);
$http_code_token = curl_getinfo($ch_token, CURLINFO_HTTP_CODE);
curl_close($ch_token);

if ($http_code_token === 200) {
    $token_data = json_decode($response_token, true);
    $access_token = $token_data['access_token'] ?? null;
    if (!$access_token) {
        $error_message = "Token de acesso não encontrado na resposta da API.";
    }
} else {
    $error_message = "Erro ao obter token. Código: $http_code_token. Resposta: " . htmlspecialchars($response_token);
}

// =============================================================================
// PARTE 2: SE O TOKEN FOI OBTIDO, BUSCAR OS ALUNOS
// =============================================================================
if ($access_token) {
    $query_params = [
        'subdomain' => $hotmart_club_subdomain,
        'max_results' => 500
    ];

    $students_url = 'https://developers.hotmart.com/club/api/v1/users?' . http_build_query($query_params);

    $ch_students = curl_init($students_url);
    curl_setopt($ch_students, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_students, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ]);
    curl_setopt($ch_students, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch_students, CURLOPT_TIMEOUT, 40);

    $response_students = curl_exec($ch_students);
    $http_code_students = curl_getinfo($ch_students, CURLINFO_HTTP_CODE);
    curl_close($ch_students);

    if ($http_code_students === 200) {
        $students_data = json_decode($response_students, true);
        $students = $students_data['items'] ?? [];
    } else {
        $error_message = "Erro ao buscar alunos. Código: $http_code_students. Resposta: " . htmlspecialchars($response_students);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progresso dos Alunos - Translators101</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; background-color: #f4f4f9; color: #333; }
        .container { max-width: 900px; margin: 40px auto; padding: 20px; background-color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-radius: 8px; }
        h1 { color: #2c3e50; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #ddd; }
        th { background-color: #ecf0f1; font-weight: 600; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .error-box { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; margin-top: 20px; border-radius: 4px; white-space: pre-wrap; word-wrap: break-word; }
        .info-box { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; padding: 15px; margin-top: 20px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Progresso dos Alunos</h1>

        <?php if ($error_message): ?>
            <div class="error-box">
                <p>Ocorreu um erro ao carregar os dados:</p>
                <p><?php echo $error_message; ?></p>
            </div>
        <?php elseif (empty($students)): ?>
             <div class="info-box">
                <p>Nenhum aluno encontrado.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Progresso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <?php
                                // CORREÇÃO: Verifica se as informações existem antes de tentar exibi-las
                                $nome = isset($student['user']['name']) ? $student['user']['name'] : 'Informação Indisponível';
                                $email = isset($student['user']['email']) ? $student['user']['email'] : 'Informação Indisponível';
                                $progresso = isset($student['progress']['percentage']) ? $student['progress']['percentage'] . '%' : 'N/A';
                            ?>
                            <td><?php echo htmlspecialchars($nome); ?></td>
                            <td><?php echo htmlspecialchars($email); ?></td>
                            <td><?php echo htmlspecialchars($progresso); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>