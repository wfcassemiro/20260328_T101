<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/trial_functions.php';
require_once __DIR__ . '/config/whatsapp_config.php';

// Configuração de email - carregar na ordem correta
require_once __DIR__ . '/config/email_config.php';
if (file_exists(__DIR__ . '/config/email.php')) {
    require_once __DIR__ . '/config/email.php';
}

$page_title = 'Degustação - três palestras grátis - Translators101';
$page_description = 'Crie sua conta e assista a até três palestras gratuitamente';

$message = '';
$error = '';
$success = false;

// Se já está logado
if (isset($_SESSION['user_id'])) {
    header('Location: /videoteca.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $country_code = trim($_POST['country_code'] ?? '+55');
    $whatsapp_number = trim($_POST['whatsapp_number'] ?? '');
    
    // Validações
    if (empty($name)) {
        $error = 'Por favor, informe seu nome.';
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor, informe um e-mail válido.';
    } elseif (empty($password) || strlen($password) < 6) {
        $error = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($password !== $password_confirm) {
        $error = 'As senhas não coincidem.';
    } else {
        // Validar WhatsApp
        $phoneValidation = validatePhone($country_code, $whatsapp_number);
        if (!$phoneValidation['valid']) {
            $error = $phoneValidation['error'];
        } else {
            $whatsapp = $phoneValidation['phone'];
            
            // Verificar se pode receber trial
            $canReceive = canReceiveTrial($pdo, $email, 'trial_24h');
            
            if (!$canReceive['can_receive']) {
                $error = $canReceive['message'];
            } else {
                try {
                    // Verificar se email já existe
                    $stmt = $pdo->prepare("SELECT id, role, had_trial_24h FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    $existingUser = $stmt->fetch();
                    
                    if ($existingUser) {
                        if ($existingUser['had_trial_24h']) {
                            $error = 'Você já utilizou a degustação anteriormente. Para continuar acessando, assine o plano completo.';
                        } else {
                            $error = 'Este e-mail já está cadastrado. Faça login ou use outro e-mail.';
                        }
                    } else {
                        // Criar novo usuário com status pendente
                        $userId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                            mt_rand(0, 0xffff),
                            mt_rand(0, 0x0fff) | 0x4000,
                            mt_rand(0, 0x3fff) | 0x8000,
                            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                        );
                        
                        // Token para aprovação
                        $approvalToken = bin2hex(random_bytes(32));
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO users (id, name, email, password_hash, whatsapp, role, trial_lectures_watched, trial_approval_token, trial_approved, is_active, created_at)
                            VALUES (?, ?, ?, ?, ?, 'trial_24h', '[]', ?, 0, 1, NOW())
                        ");
                        $stmt->execute([$userId, $name, $email, password_hash($password, PASSWORD_DEFAULT), $whatsapp, $approvalToken]);
                        
                        // Enviar email para o admin
                        $adminEmail = 'wrbl.traduz@gmail.com';
                        $approvalLink = "https://v.translators101.com/admin/liberar_trial.php?token=" . $approvalToken;
                        
                        $emailSubject = "[Trial 24h] Nova solicitação: " . $name;
                        $emailBody = "
                        <h2>Nova solicitação de Degustação (24h)</h2>
                        <p>Um novo usuário solicitou acesso trial:</p>
                        <table style='border-collapse: collapse; margin: 20px 0;'>
                            <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Nome:</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>{$name}</td></tr>
                            <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Email:</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>{$email}</td></tr>
                            <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>WhatsApp:</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>" . formatPhoneDisplay($whatsapp) . "</td></tr>
                            <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Tipo:</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>Degustação 24h (3 palestras)</td></tr>
                            <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Data:</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>" . date('d/m/Y H:i') . "</td></tr>
                        </table>
                        <p><a href='{$approvalLink}' style='display: inline-block; padding: 12px 24px; background: #ec4899; color: white; text-decoration: none; border-radius: 8px; font-weight: bold;'>Clique aqui para aprovar ou rejeitar</a></p>
                        <p style='color: #666; font-size: 12px; margin-top: 20px;'>Link direto: {$approvalLink}</p>
                        ";
                        
                        // Tentar enviar email
                        if (function_exists('isEmailConfigured') && isEmailConfigured()) {
                            try {
                                $emailSender = new EmailSender();
                                $htmlContent = EmailTemplates::getCustomEmailTemplate($emailSubject, $emailBody);
                                $emailSender->sendEmail($adminEmail, 'Admin T101', $emailSubject, $htmlContent);
                            } catch (Exception $e) {
                                error_log("[Trial] Erro ao enviar email para admin: " . $e->getMessage());
                            }
                        }
                        
                        $success = true;
                        $message = 'Cadastro realizado com sucesso!';
                    }
                    
                } catch (PDOException $e) {
                    error_log("Erro ao criar conta trial_24h: " . $e->getMessage());
                    $error = 'Erro ao criar conta. Tente novamente.';
                }
            }
        }
    }
}

include __DIR__ . '/vision/includes/head.php';
include __DIR__ . '/vision/includes/header.php';
?>

<style>
.trial-hero {
    background: linear-gradient(135deg, rgba(236, 72, 153, 0.2), rgba(139, 92, 246, 0.1));
    border: 1px solid rgba(236, 72, 153, 0.3);
    border-radius: 20px;
    padding: 40px;
    text-align: center;
    margin-bottom: 30px;
}

.trial-hero h1 {
    color: #ec4899;
    margin-bottom: 15px;
}

.trial-hero .trial-badge {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, rgba(236, 72, 153, 0.3), rgba(139, 92, 246, 0.2));
    padding: 15px 30px;
    border-radius: 30px;
    font-size: 1.3rem;
    font-weight: 700;
    color: #ec4899;
    margin-bottom: 20px;
}

.trial-benefits {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

.benefit-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
}

.benefit-item i {
    font-size: 1.5rem;
    color: #ec4899;
}

.trial-form-card {
    max-width: 500px;
    margin: 0 auto;
    padding: 35px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: white;
}

.form-group input {
    width: 100%;
    padding: 14px 16px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.3);
    color: white;
    font-size: 1rem;
    box-sizing: border-box;
}

.form-group input:focus {
    outline: none;
    border-color: #ec4899;
    box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.15);
}

/* Phone Input Container */
.phone-input-container {
    display: flex;
    gap: 10px;
}

.country-input-wrapper {
    display: flex;
    flex-direction: column;
    gap: 5px;
    width: 160px;
    flex-shrink: 0;
}

.country-code-input {
    width: 100% !important;
    padding: 14px 10px !important;
    text-align: center;
    font-weight: 600;
    font-size: 1.1rem !important;
}

.country-select {
    width: 100%;
    padding: 8px 5px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(0, 0, 0, 0.3);
    color: white;
    font-size: 0.8rem;
    cursor: pointer;
}

.country-select:focus {
    outline: none;
    border-color: #ec4899;
}

.country-select optgroup {
    background: #1a1a1a;
    color: #ec4899;
    font-weight: 600;
}

.country-select option {
    background: #2a2a2a;
    color: white;
    padding: 5px;
}

.phone-input-container input[type="tel"] {
    flex: 1;
}

.submit-btn {
    width: 100%;
    padding: 16px;
    background: linear-gradient(135deg, #ec4899, #8b5cf6);
    border: none;
    border-radius: 10px;
    color: white;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(236, 72, 153, 0.3);
}

.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.alert-success {
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #10b981;
}

.alert-error {
    background: rgba(239, 68, 68, 0.15);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.login-link {
    text-align: center;
    margin-top: 20px;
    color: rgba(255, 255, 255, 0.7);
}

.login-link a {
    color: #ec4899;
    font-weight: 600;
}

.info-box {
    background: rgba(139, 92, 246, 0.1);
    border: 1px solid rgba(139, 92, 246, 0.3);
    border-radius: 10px;
    padding: 15px;
    margin-top: 20px;
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.8);
}

.info-box i {
    color: #8b5cf6;
    margin-right: 8px;
}

.info-box ul {
    margin: 10px 0 0 20px;
    padding: 0;
}

.info-box li {
    margin: 5px 0;
}

.success-card {
    text-align: center;
    padding: 40px;
}

.success-icon {
    font-size: 4rem;
    color: #ec4899;
    margin-bottom: 20px;
}

.success-card h2 {
    color: #ec4899;
    margin-bottom: 15px;
}

.success-card p {
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 10px;
}

.whatsapp-notice {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: rgba(37, 211, 102, 0.1);
    border: 1px solid rgba(37, 211, 102, 0.3);
    padding: 15px 25px;
    border-radius: 10px;
    margin: 20px 0;
    color: #25D366;
}

.whatsapp-notice i {
    font-size: 1.5rem;
}

@media (max-width: 768px) {
    .phone-input-container {
        flex-direction: column;
    }
    
    .country-input-wrapper {
        width: 100%;
    }
}
</style>

<div class="main-content">
    <div class="trial-hero">
        <div class="trial-badge">
            <i class="fas fa-star"></i>
            DEGUSTAÇÃO GRÁTIS
        </div>
        <h1><i class="fas fa-play-circle"></i> Assista 3 Palestras Grátis</h1>
        <p>Conheça a qualidade do nosso conteúdo sem compromisso!</p>
        
        <div class="trial-benefits">
            <div class="benefit-item">
                <i class="fas fa-video"></i>
                <span>3 palestras à sua escolha</span>
            </div>
            <div class="benefit-item">
                <i class="fas fa-clock"></i>
                <span>24 horas de acesso</span>
            </div>
            <div class="benefit-item">
                <i class="fas fa-redo"></i>
                <span>Assista quantas vezes quiser</span>
            </div>
        </div>
    </div>

    <div class="video-card trial-form-card">
        <?php if ($success): ?>
            <div class="success-card">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2>Cadastro Recebido!</h2>
                <p>Sua solicitação de acesso foi enviada com sucesso.</p>
                
                <div class="whatsapp-notice">
                    <i class="fab fa-whatsapp"></i>
                    <span>Você receberá a liberação pelo WhatsApp cadastrado em até <strong>8 horas</strong>.</span>
                </div>
                
                <p style="font-size: 0.9rem; color: rgba(255,255,255,0.6);">
                    Fique atento às mensagens no seu WhatsApp!
                </p>
                
                <a href="/" class="submit-btn" style="display: inline-block; text-align: center; text-decoration: none; margin-top: 20px; max-width: 300px;">
                    <i class="fas fa-home"></i> Voltar para a página inicial
                </a>
            </div>
        <?php else: ?>
            <h2 style="text-align: center; margin-bottom: 25px;"><i class="fas fa-user-plus"></i> Criar Conta</h2>
            
            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="name"><i class="fas fa-user"></i> Nome completo</label>
                    <input type="text" id="name" name="name" required 
                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                           placeholder="Seu nome completo">
                </div>
                
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> E-mail</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           placeholder="seu@email.com">
                </div>
                
                <div class="form-group">
                    <label for="whatsapp"><i class="fab fa-whatsapp"></i> WhatsApp (com código do país)</label>
                    <div class="phone-input-container">
                        <div class="country-input-wrapper">
                            <input type="text" id="country_code" name="country_code" class="country-code-input" 
                                   value="<?php echo htmlspecialchars($_POST['country_code'] ?? '+55'); ?>" 
                                   maxlength="5" placeholder="+55" required>
                            <select id="country_select" class="country-select" onchange="selectCountry(this)">
                                <option value="">Selecione...</option>
                                <optgroup label="América do Sul">
                                    <option value="+55">🇧🇷 Brasil (+55)</option>
                                    <option value="+54">🇦🇷 Argentina (+54)</option>
                                    <option value="+56">🇨🇱 Chile (+56)</option>
                                    <option value="+57">🇨🇴 Colômbia (+57)</option>
                                    <option value="+51">🇵🇪 Peru (+51)</option>
                                    <option value="+598">🇺🇾 Uruguai (+598)</option>
                                </optgroup>
                                <optgroup label="América do Norte e Central">
                                    <option value="+1">🇺🇸 EUA / 🇨🇦 Canadá (+1)</option>
                                    <option value="+52">🇲🇽 México (+52)</option>
                                </optgroup>
                                <optgroup label="Europa">
                                    <option value="+351">🇵🇹 Portugal (+351)</option>
                                    <option value="+34">🇪🇸 Espanha (+34)</option>
                                    <option value="+33">🇫🇷 França (+33)</option>
                                    <option value="+49">🇩🇪 Alemanha (+49)</option>
                                    <option value="+44">🇬🇧 Reino Unido (+44)</option>
                                    <option value="+39">🇮🇹 Itália (+39)</option>
                                    <option value="+41">🇨🇭 Suíça (+41)</option>
                                </optgroup>
                                <optgroup label="África">
                                    <option value="+244">🇦🇴 Angola (+244)</option>
                                    <option value="+258">🇲🇿 Moçambique (+258)</option>
                                    <option value="+238">🇨🇻 Cabo Verde (+238)</option>
                                </optgroup>
                                <optgroup label="Outros">
                                    <option value="+81">🇯🇵 Japão (+81)</option>
                                    <option value="+61">🇦🇺 Austrália (+61)</option>
                                </optgroup>
                                <option value="outro">✏️ Outro (digitar código)</option>
                            </select>
                        </div>
                        <input type="tel" id="whatsapp_number" name="whatsapp_number" placeholder="99999-9999" required
                               value="<?php echo htmlspecialchars($_POST['whatsapp_number'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Senha</label>
                    <input type="password" id="password" name="password" required 
                           minlength="6" placeholder="Mínimo 6 caracteres">
                </div>
                
                <div class="form-group">
                    <label for="password_confirm"><i class="fas fa-lock"></i> Confirmar senha</label>
                    <input type="password" id="password_confirm" name="password_confirm" required 
                           placeholder="Digite a senha novamente">
                </div>
                
                <button type="submit" class="submit-btn">
                    <i class="fas fa-play"></i> Solicitar minha degustação
                </button>
                
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>Como funciona:</strong>
                    <ul>
                        <li>O WhatsApp será usado para envio da liberação de acesso</li>
                        <li>Você receberá a confirmação em até 8 horas</li>
                        <li>Após a aprovação, escolha três palestras para assistir</li>
                        <li>O período de 24h começa quando você der play na primeira palestra</li>
                        <li>Se você assistir a mesma palestra mais de uma vez, ela conta como apenas uma das três</li>
                        <li>Não é permitido obter mais de um período gratuito.</li>
                    </ul>
                </div>
            </form>
            
            <p class="login-link">
                Já tem uma conta? <a href="login.php">Fazer login</a>
            </p>
        <?php endif; ?>
    </div>
</div>

<script>
function selectCountry(select) {
    const value = select.value;
    const codeInput = document.getElementById('country_code');
    
    if (value === 'outro') {
        codeInput.value = '+';
        codeInput.focus();
        codeInput.select();
    } else if (value) {
        codeInput.value = value;
    }
}

document.getElementById('country_code')?.addEventListener('input', function(e) {
    let value = e.target.value;
    if (!value.startsWith('+')) {
        value = '+' + value.replace(/[^0-9]/g, '');
    } else {
        value = '+' + value.substring(1).replace(/[^0-9]/g, '');
    }
    e.target.value = value;
});
</script>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>