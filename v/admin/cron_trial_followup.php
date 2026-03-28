<?php
// cron_trial_followup.php
// Script para envio automático de emails pós-trial e relatório ao admin
// Deve ser configurado no Cron Job para rodar 1x ao dia (ex: 09:00 da manhã)

// Definir fuso horário
date_default_timezone_set('America/Sao_Paulo');

// Caminhos relativos (ajuste se necessário, assumindo que está na mesma pasta de leads_fundo.php)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email_config.php';
require_once __DIR__ . '/../config/email.php';

// Configurações
$admin_email = 'wrbl.traduz@gmail.com';
$admin_name = 'William Cassemiro';
$batch_delay = 1; // Segundos entre envios para não sobrecarregar

// Template do Email para o Lead
$subject_lead = 'Seu teste terminou, mas sua evolução não precisa parar';
$body_template = "Olá [NOME],

Espero que o seu período de testes na Translators101 tenha sido produtivo!

Nesses últimos dias, você teve um \"gostinho\" do que é fazer parte da maior comunidade de capacitação na tradução e interpretação. Mas a verdade é que o acesso gratuito é apenas a ponta do iceberg.

Ao assinar, você garante:

Acesso ilimitado a todas as nossas palestras e ferramentas.
Uma nova palestra ao vivo toda semana.
Certificados com validação por QR Code.
Atualização constante sobre as ferramentas e tendências que estão moldando o mercado, como o uso de IA na tradução.
Networking qualificado com profissionais que enfrentam os mesmos desafios que você.
Conteúdo prático, pensado por quem entende as dores e as delícias da nossa profissão.

Para que você continue conosco e leve sua carreira para o próximo nível, preparei duas condições especiais exclusivas para o encerramento do seu teste:

📌 Plano Mensal: de R$ 53 por R$ 45 nos primeiros dois meses (15% de desconto)
https://hotm.io/mensal15

📌 Plano Anual: R$ 349, em até 12 vezes no cartão (menos de R$ 35/mês)
https://hotm.io/Anual349

Se tiver dúvidas, entre em contato pelo WhatsApp (+55 19 98260 0771).

Um abraço.

William Cassemiro
Translators101";

// Inicializar contadores e listas
$sent_list = [];
$error_list = [];

// Função auxiliar para verificar expiração (Mesma lógica do leads_fundo.php)
function isTrialExpired($user) {
    // Usa trial_started_at, ou fallback para trial_approved_at
    $startDateString = !empty($user['trial_started_at']) ? $user['trial_started_at'] : ($user['trial_approved_at'] ?? null);

    if (empty($startDateString)) {
        return false; // Não iniciou, não expirou
    }

    $now = new DateTime();
    $trialStarted = new DateTime($startDateString);
    $expirationDate = clone $trialStarted;

    if ($user['role'] === 'trial_7d') {
        $expirationDate->modify('+7 days');
    } elseif ($user['role'] === 'trial_24h') {
        $expirationDate->modify('+24 hours');
    } else {
        return false;
    }

    return $now > $expirationDate;
}

echo "Iniciando verificação de trials expirados...<br>";

try {
    // 1. Buscar usuários candidatos (Trial, Ativos e que AINDA NÃO receberam o email)
    // Importante: A coluna trial_mail_sent deve ter sido criada no BD
    $stmt = $pdo->prepare("
        SELECT id, name, email, role, trial_started_at, trial_approved_at 
        FROM users 
        WHERE role IN ('trial_7d', 'trial_24h') 
        AND is_active = 1
        AND (trial_mail_sent IS NULL OR trial_mail_sent = 0)
    ");
    $stmt->execute();
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Candidatos encontrados (não processados): " . count($candidates) . "<br>";

    $emailSender = new EmailSender();

    foreach ($candidates as $user) {
        // 2. Verificar se realmente expirou
        if (isTrialExpired($user)) {
            echo "Processando: {$user['name']} ({$user['email']})... ";

            // Personalizar mensagem
            $message = str_replace('[NOME]', $user['name'], $body_template);
            $html_content = nl2br(htmlspecialchars($message)); 
            // Se tiver um template wrapper na classe EmailTemplates, ideal usar aqui, 
            // mas como é script solto, vamos enviar o HTML básico ou usar wrapper se disponível.
            // Assumindo uso direto para garantir funcionamento:
            
            // Tentar enviar
            try {
                $sent = $emailSender->sendEmail(
                    $user['email'],
                    $user['name'],
                    $subject_lead,
                    $html_content
                );

                if ($sent) {
                    // 3. Atualizar BD para não enviar novamente
                    $updateStmt = $pdo->prepare("UPDATE users SET trial_mail_sent = 1 WHERE id = ?");
                    $updateStmt->execute([$user['id']]);

                    $sent_list[] = [
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'role' => $user['role']
                    ];
                    echo "ENVIADO.<br>";
                } else {
                    $error_list[] = "Falha no envio para: {$user['email']}";
                    echo "FALHA NO ENVIO.<br>";
                }

            } catch (Exception $e) {
                $error_list[] = "Erro exceção para {$user['email']}: " . $e->getMessage();
                echo "ERRO: " . $e->getMessage() . "<br>";
            }

            // Pausa para evitar bloqueio de SMTP
            sleep($batch_delay);
        }
    }

    // 4. Enviar Relatório para o Admin (Se houve envios)
    if (!empty($sent_list)) {
        $report_subject = "Relatório Diário: Emails de Trial Expirado Enviados";
        
        $report_html = "<h2>Relatório de Envios Automáticos</h2>";
        $report_html .= "<p>Hoje foram enviados emails de conversão para os seguintes leads expirados:</p>";
        $report_html .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
        $report_html .= "<tr style='background: #f0f0f0;'><th>Nome</th><th>Email</th><th>Tipo Trial</th></tr>";
        
        foreach ($sent_list as $item) {
            $report_html .= "<tr>";
            $report_html .= "<td>{$item['name']}</td>";
            $report_html .= "<td>{$item['email']}</td>";
            $report_html .= "<td>{$item['role']}</td>";
            $report_html .= "</tr>";
        }
        $report_html .= "</table>";
        
        if (!empty($error_list)) {
            $report_html .= "<h3>Erros/Falhas:</h3><ul>";
            foreach ($error_list as $err) {
                $report_html .= "<li>$err</li>";
            }
            $report_html .= "</ul>";
        }

        $report_html .= "<p>Total enviado: " . count($sent_list) . "</p>";
        $report_html .= "<p>Data: " . date('d/m/Y H:i:s') . "</p>";

        // Enviar para o admin
        $emailSender->sendEmail(
            $admin_email,
            $admin_name,
            $report_subject,
            $report_html
        );
        
        echo "<hr>Relatório enviado para o admin.<br>";
    } else {
        echo "<hr>Nenhum trial expirado encontrado hoje para envio.<br>";
    }

} catch (PDOException $e) {
    echo "Erro fatal de Banco de Dados: " . $e->getMessage();
    // Opcional: Enviar email de alerta técnico para o admin
} catch (Exception $e) {
    echo "Erro fatal: " . $e->getMessage();
}
?>