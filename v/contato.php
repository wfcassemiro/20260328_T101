<?php
session_start();
require_once __DIR__ . '/config/database.php';

$page_title = 'Contato';
$page_description = 'Entre em contato com o Translators101. Estamos aqui para ajudar com suas dúvidas e sugestões.';

$success_message = '';
$error_message = '';

// Variáveis para preencher o formulário
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$subject = $_POST['subject'] ?? '';
$message_text = $_POST['message'] ?? '';

/**
 * Função para enviar o email usando a função mail() do PHP.
 * Nota: Para produção, é altamente recomendável usar PHPMailer via SMTP.
 */
function sendEmail($name, $email, $subject, $message_text, $pdo) {
    // Endereço de destino (O único endereço para onde as mensagens do formulário de contato devem ir)
    $to = 'contato@translators101.com.br'; 
    $email_subject = "Novo Contato: " . $subject . " de " . $name;
    
    $body = "Nome: " . $name . "\n";
    $body .= "Email: " . $email . "\n";
    $body .= "Assunto: " . $subject . "\n\n";
    $body .= "Mensagem:\n" . $message_text;

    // Configuração dos headers para evitar que caia no spam (usando o email do remetente no Reply-To)
    $headers = "From: " . $name . " <" . $email . ">\r\n";
    $headers .= "Reply-To: " . $email . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // Tenta enviar o email
    $mail_sent = @mail($to, $email_subject, $body, $headers);
    
    // Loga o contato no banco de dados (Mantendo sua funcionalidade de log)
    try {
        $stmt = $pdo->prepare("INSERT INTO access_logs (action, resource, ip_address, user_agent) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            'contact_form',
            "Name: $name, Email: $email, Subject: $subject",
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
    } catch(PDOException $e) {
        // Falha silenciosa no log
    }

    return $mail_sent;
}

// Processar envio de mensagem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message_text = trim($_POST['message']);
    
    // Validações básicas
    if (empty($name) || empty($email) || empty($subject) || empty($message_text)) {
        $error_message = 'Todos os campos são obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Email inválido.';
    } else {
        if (sendEmail($name, $email, $subject, $message_text, $pdo)) {
            $success_message = 'Mensagem enviada com sucesso! Retornaremos o contato em breve.';
            // Limpar campos após envio bem-sucedido
            $name = $email = $subject = $message_text = '';
        } else {
            $error_message = 'Erro ao enviar a mensagem. Por favor, tente novamente ou use o WhatsApp.';
        }
    }
}

include __DIR__ . '/vision/includes/head.php';
?>

<style>
/* ============================================
   ESTILOS ADICIONAIS PARA FORMULÁRIO (APPLE VISION)
   ============================================ */
:root {
    --brand-purple: #7B61FF;
}

/* Contêiner Principal do Formulário - Coluna da Direita */
.contact-form-container {
    background: rgba(255, 255, 255, 0.05); /* Fundo sutil */
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 30px; 
    padding: 40px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    color: #fff;
    /* Alinha o card do formulário ao topo da coluna */
    align-self: flex-start; 
}

/* Estilo para Rótulos (Labels) */
.contact-form-container label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    font-size: 0.95rem;
    color: #fff;
}

/* Estilo para Campos de Input, Select e Textarea */
.contact-form-container input[type="text"],
.contact-form-container input[type="email"],
.contact-form-container select,
.contact-form-container textarea {
    width: 100%;
    padding: 12px 16px;
    border-radius: 30px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.1); 
    color: #fff;
    font-size: 1rem;
    font-family: 'Inter', sans-serif;
    transition: all 0.3s ease;
    box-sizing: border-box;
}

.contact-form-container input::placeholder,
.contact-form-container textarea::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

.contact-form-container input:focus,
.contact-form-container select:focus,
.contact-form-container textarea:focus {
    outline: none;
    border-color: var(--brand-purple);
    background: rgba(123, 97, 255, 0.1);
    box-shadow: 0 0 0 3px rgba(123, 97, 255, 0.2);
}

.contact-form-container textarea {
    resize: vertical;
}

/* Estilo para o Select (Dropdown) */
.contact-form-container select {
    appearance: none;
    cursor: pointer;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Cpath fill='%237B61FF' d='M8 11L3 6h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 16px center;
    padding-right: 30px;
}

/* Estilo do Botão Padrão (CTA) */
.cta-btn {
    padding: 12px 24px;
    border-radius: 30px; 
    border: none;
    background: linear-gradient(135deg, var(--brand-purple), #483D8B); 
    color: #fff;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center; 
    text-decoration: none;
}

.cta-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(123, 97, 255, 0.4);
}

/* Estilo específico para cards de contato e canais */
.channel-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 30px; /* Mantém o arredondamento */
    padding: 20px;
}

/* Estilo para o cartão na seção FAQ */
.video-card.fade-item .video-info {
    text-align: center;
}
.video-card {
    border-radius: 30px; /* Mantém o arredondamento */
}

/* NOVO CSS PARA ALINHAMENTO */

/* Container da Grade Principal (Onde a mágica acontece) */
.main-content > div:nth-child(2) {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    margin-bottom: 40px;
    /* Alinhamento ao topo para ambas as colunas */
    align-items: flex-start; 
}

/* Coluna da Esquerda (Informações de Contato) */
.contact-info-column {
    display: flex;
    flex-direction: column;
    /* Remove a centralização para que o conteúdo comece à esquerda */
    align-items: flex-start;
    /* Adiciona padding superior equivalente ao padding do formulário (40px)
       para alinhar verticalmente os cards de canal com o formulário. */
    padding-top: 0px;
}

.contact-info-cards-container {
    /* Container para os 4 cards da esquerda */
    display: flex;
    flex-direction: column;
    gap: 20px;
    width: 100%;
}

.channel-card > div {
    /* Garante que o padding dos canais comece no mesmo lugar */
    padding: 0;
}

/* Responsividade */
@media (max-width: 992px) {
    .main-content > div:nth-child(2) {
        grid-template-columns: 1fr;
    }
    .contact-form-container {
        padding: 30px;
    }
    .contact-info-column {
        align-items: center; /* Centraliza tudo em telas menores */
        padding-top: 0; /* Remove padding extra em mobile */
    }
    /* Cards de canal alinhados ao centro em mobile */
    .channel-card > div {
        flex-direction: row;
        align-items: center !important; 
        justify-content: flex-start;
    }
    .channel-card h3, .channel-card p, .channel-card a {
        text-align: left !important;
    }
}
@media (max-width: 576px) {
    .contact-form-container form > div:first-child {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include __DIR__ . '/vision/includes/header.php'; ?>

<?php include __DIR__ . '/vision/includes/sidebar.php'; ?>

<main class="main-content">
    <!-- Hero Section -->
    <section class="glass-hero">
        <h1><i class="fas fa-envelope" style="margin-right: 10px;"></i>Entre em contato</h1>
        <p>Estamos aqui para ajudar! Entre em contato por qualquer um dos canais abaixo.</p>
    </section>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 40px;">
        <!-- Coluna Esquerda: Informações de Contato -->
        <div class="contact-info-column">
            
            <!-- Canais de Contato -->
            <div class="contact-info-cards-container">
                
                <div class="video-card channel-card">
                    <div style="display: flex; align-items: center; gap: 20px;">
                        <div style="background: var(--brand-purple); border-radius: 50%; padding: 15px; flex-shrink: 0;">
                            <i class="fas fa-envelope" style="color: white; font-size: 1.5rem;"></i>
                        </div>
                        <div style="flex: 1; text-align: left;">
                            <h3 style="font-size: 1.2rem; font-weight: 600; margin-bottom: 8px; color: #fff;">Email</h3>
                            <a href="mailto:contato@translators101.com.br" style="color: var(--brand-purple); text-decoration: none; font-weight: 500;">
                                contato@translators101.com.br
                            </a>
                            <p style="color: #bbb; font-size: 0.9rem; margin-top: 5px;">Respondemos em até 24 horas</p>
                        </div>
                    </div>
                </div>
                
                <div class="video-card channel-card">
                    <div style="display: flex; align-items: center; gap: 20px;">
                        <div style="background: #25d366; border-radius: 50%; padding: 15px; flex-shrink: 0;">
                            <i class="fab fa-whatsapp" style="color: white; font-size: 1.5rem;"></i>
                        </div>
                        <div style="flex: 1; text-align: left;">
                            <h3 style="font-size: 1.2rem; font-weight: 600; margin-bottom: 8px; color: #fff;">Whatsapp</h3>
                            <a href="https://wa.me/5519982600771" target="_blank" style="color: #25d366; text-decoration: none; font-weight: 500;">
                                (19) 98260-0771
                            </a>
                            <p style="color: #bbb; font-size: 0.9rem; margin-top: 5px;">Atendimento em horário comercial</p>
                        </div>
                    </div>
                </div>
                
                <div class="video-card channel-card">
                    <div style="display: flex; align-items: center; gap: 20px;">
                        <div style="background: #3498db; border-radius: 50%; padding: 15px; flex-shrink: 0;">
                            <i class="fas fa-clock" style="color: white; font-size: 1.5rem;"></i>
                        </div>
                        <div style="flex: 1; text-align: left;">
                            <h3 style="font-size: 1.2rem; font-weight: 600; margin-bottom: 8px; color: #fff;">Horário de atendimento</h3>
                            <p style="color: #fff; font-weight: 500;">Segunda a sexta: 9h às 18h</p>
                            <p style="color: #bbb; font-size: 0.9rem; margin-top: 5px;">Horário de Brasília</p>
                        </div>
                    </div>
                </div>
            
                <!-- Tipos de Suporte (Agora o quarto card, dentro do mesmo container) -->
                <div class="video-card contact-form-container" style="padding: 25px; text-align: left; width: 100%;">
                    <div class="video-info">
                        <h3 style="font-size: 1.3rem; font-weight: 600; margin-bottom: 20px; color: #fff;">
                            <i class="fas fa-question-circle" style="margin-right: 10px; color: #FFD700;"></i>
                            Como podemos ajudar?
                        </h3>
                        <div style="display: flex; flex-direction: column; gap: 12px; font-size: 0.95rem;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <i class="fas fa-question-circle" style="color: var(--brand-purple); font-size: 1.1rem;"></i>
                                <span style="color: #ddd;">Dúvidas sobre planos e assinaturas</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <i class="fas fa-cog" style="color: var(--brand-purple); font-size: 1.1rem;"></i>
                                <span style="color: #ddd;">Suporte técnico e problemas de acesso</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <i class="fas fa-lightbulb" style="color: var(--brand-purple); font-size: 1.1rem;"></i>
                                <span style="color: #ddd;">Sugestões de conteúdo e melhorias</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <i class="fas fa-certificate" style="color: var(--brand-purple); font-size: 1.1rem;"></i>
                                <span style="color: #ddd;">Problemas com certificados</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <i class="fas fa-handshake" style="color: var(--brand-purple); font-size: 1.1rem;"></i>
                                <span style="color: #ddd;">Parcerias e colaborações</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <i class="fas fa-building" style="color: var(--brand-purple); font-size: 1.1rem;"></i>
                                <span style="color: #ddd;">Soluções corporativas</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <i class="fas fa-times-circle" style="color: var(--brand-purple); font-size: 1.1rem;"></i>
                                <span style="color: #ddd;">Cancelamento</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <i class="fas fa-ellipsis-h" style="color: var(--brand-purple); font-size: 1.1rem;"></i>
                                <span style="color: #ddd;">Outros</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Coluna Direita: Formulário de Contato -->
        <div class="contact-form-container">
            <h2 style="font-size: 2rem; font-weight: bold; margin-bottom: 30px; color: #fff;">
                <i class="fas fa-paper-plane" style="margin-right: 10px; color: #FFD700;"></i>
                Envie uma mensagem
            </h2>
            
            <?php if ($success_message): ?>
                <div class="alert-success" style="margin-bottom: 25px;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert-error" style="margin-bottom: 25px;">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <div>
                <form method="POST" style="display: flex; flex-direction: column; gap: 20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label for="name">Nome completo *</label>
                            <input
                                type="text"
                                name="name"
                                id="name"
                                value="<?php echo htmlspecialchars($name); ?>"
                                required
                            />
                        </div>
                        
                        <div>
                            <label for="email">Email *</label>
                            <input
                                type="email"
                                name="email"
                                id="email"
                                value="<?php echo htmlspecialchars($email); ?>"
                                required
                            />
                        </div>
                    </div>
                    
                    <div>
                        <label for="subject">Assunto *</label>
                        <select name="subject" id="subject" required>
                            <option value="">Selecione o assunto</option>
                            <option value="Dúvidas sobre planos" <?php echo $subject === 'Dúvidas sobre planos' ? 'selected' : ''; ?>>Dúvidas sobre planos</option>
                            <option value="Suporte técnico" <?php echo $subject === 'Suporte técnico' ? 'selected' : ''; ?>>Suporte técnico</option>
                            <option value="Problemas com certificados" <?php echo $subject === 'Problemas com certificados' ? 'selected' : ''; ?>>Problemas com certificados</option>
                            <option value="Sugestões de conteúdo" <?php echo $subject === 'Sugestões de conteúdo' ? 'selected' : ''; ?>>Sugestões de conteúdo</option>
                            <option value="Parcerias" <?php echo $subject === 'Parcerias' ? 'selected' : ''; ?>>Parcerias</option>
                            <option value="Soluções corporativas" <?php echo $subject === 'Soluções corporativas' ? 'selected' : ''; ?>>Soluções corporativas</option>
                            <option value="Cancelamento" <?php echo $subject === 'Cancelamento' ? 'selected' : ''; ?>>Cancelamento</option>
                            <option value="Outros" <?php echo $subject === 'Outros' ? 'selected' : ''; ?>>Outros</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="message">Mensagem *</label>
                        <textarea
                            name="message"
                            id="message"
                            rows="6"
                            placeholder="Descreva sua dúvida ou mensagem..."
                            required
                        ><?php echo htmlspecialchars($message_text); ?></textarea>
                    </div>
                    
                    <button type="submit" name="send_message" class="cta-btn" style="width: 100%;">
                        <i class="fas fa-paper-plane" style="margin-right: 8px;"></i>Enviar mensagem
                    </button>
                </form>
                
                <p style="color: #bbb; font-size: 0.9rem; margin-top: 15px; text-align: center;">
                    * Campos obrigatórios. Responderemos sua mensagem em até 24 horas.
                </p>
            </div>
        </div>
    </div>
    
    <!-- FAQ Rápido -->
    <section class="vision-form" style="margin-top: 60px;">
        <h2 style="font-size: 2rem; font-weight: bold; text-align: center; margin-bottom: 40px; color: #fff;">
            <i class="fas fa-question-circle" style="margin-right: 10px; color: var(--brand-purple);"></i>
            Perguntas mais frequentes
        </h2>
        
        <div class="video-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
            <div class="video-card fade-item">
                <div class="video-info" style="text-align: center;">
                    <div style="font-size: 3rem; margin-bottom: 15px; color: var(--brand-purple);">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <h4 style="font-weight: 600; margin-bottom: 15px; color: #fff;">Como cancelar minha assinatura?</h4>
                    <p style="color: #ddd; margin-bottom: 20px; font-size: 0.9rem;">
                        O cancelamento pode ser feito a qualquer momento sem complicações.
                    </p>
                    <a href="/faq.php#cancelamento" class="cta-btn" style="font-size: 0.9rem; padding: 8px 16px;">
                        Ver detalhes <i class="fas fa-arrow-right" style="margin-left: 5px;"></i>
                    </a>
                </div>
            </div>

            <div class="video-card fade-item">
                <div class="video-info" style="text-align: center;">
                    <div style="font-size: 3rem; margin-bottom: 15px; color: var(--brand-purple);">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <h4 style="font-weight: 600; margin-bottom: 15px; color: #fff;">Como gerar certificados?</h4>
                    <p style="color: #ddd; margin-bottom: 20px; font-size: 0.9rem;">
                        Certificados são gerados automaticamente após assistir às palestras.
                    </p>
                    <a href="/faq.php#certificados" class="cta-btn" style="font-size: 0.9rem; padding: 8px 16px;">
                        Ver detalhes <i class="fas fa-arrow-right" style="margin-left: 5px;"></i>
                    </a>
                </div>
            </div>

            <div class="video-card fade-item">
                <div class="video-info" style="text-align: center;">
                    <div style="font-size: 3rem; margin-bottom: 15px; color: var(--brand-purple);">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <h4 style="font-weight: 600; margin-bottom: 15px; color: #fff;">Qual plano escolher?</h4>
                    <p style="color: #ddd; margin-bottom: 20px; font-size: 0.9rem;">
                        Todos os planos têm acesso completo. Quanto maior o período, maior a economia.
                    </p>
                    <a href="/planos.php" class="cta-btn" style="font-size: 0.9rem; padding: 8px 16px;">
                        Ver planos <i class="fas fa-arrow-right" style="margin-left: 5px;"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 40px;">
            <!-- Botão Ver Todas as Perguntas com o padrão visual da classe .cta-btn -->
            <a href="/faq.php" class="cta-btn">
                <i class="fas fa-list" style="margin-right: 8px;"></i>Ver todas as perguntas
            </a>
        </div>
    </section>
</main>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>
