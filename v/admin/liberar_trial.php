<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/whatsapp_config.php';

$page_title = 'Liberar Acesso Trial - Admin';

// Token de liberação (pode vir via GET ou POST)
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$action = $_POST['action'] ?? '';

$user = null;
$message = '';
$error = '';
$whatsapp_link = '';
$show_form = false;

// Buscar usuário pelo token
if ($token) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM users 
            WHERE trial_approval_token = ? 
            AND deleted_at IS NULL
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = 'Token inválido ou usuário não encontrado.';
        } elseif ($user['trial_approved']) {
            $message = 'Este usuário já foi aprovado anteriormente.';
        } else {
            $show_form = true;
        }
    } catch (PDOException $e) {
        $error = 'Erro ao buscar usuário: ' . $e->getMessage();
    }
}

// Processar aprovação
if ($action === 'approve' && $user && !$user['trial_approved']) {
    try {
        // Atualizar usuário para aprovado
        $stmt = $pdo->prepare("
            UPDATE users 
            SET trial_approved = 1, 
                trial_approved_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$user['id']]);
        
        // Gerar link do WhatsApp
        $trialType = $user['role'];
        $whatsappMessage = $trialType === 'trial_7d' 
            ? WHATSAPP_MSG_TRIAL_7D_APROVADO 
            : WHATSAPP_MSG_TRIAL_24H_APROVADO;
        
        $result = sendWhatsAppMessage($user['whatsapp'], $whatsappMessage, $user['name']);
        
        if ($result['success'] && $result['method'] === 'wa.me') {
            $whatsapp_link = $result['link'];
            $message = 'Usuário aprovado com sucesso! Clique no botão abaixo para enviar o WhatsApp.';
        } elseif ($result['success'] && $result['method'] === 'evolution_api') {
            $message = 'Usuário aprovado e WhatsApp enviado automaticamente!';
        } else {
            $message = 'Usuário aprovado, mas houve um erro ao preparar o WhatsApp: ' . ($result['error'] ?? 'Erro desconhecido');
        }
        
        $show_form = false;
        
        // Recarregar dados do usuário
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $user = $stmt->fetch();
        
        // Log
        error_log("[Trial] Usuário {$user['email']} aprovado por admin. Role: {$trialType}");
        
    } catch (PDOException $e) {
        $error = 'Erro ao aprovar usuário: ' . $e->getMessage();
    }
}

// Processar rejeição
if ($action === 'reject' && $user && !$user['trial_approved']) {
    try {
        // Excluir usuário ou marcar como rejeitado
        $stmt = $pdo->prepare("
            UPDATE users 
            SET deleted_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$user['id']]);
        
        $message = 'Solicitação rejeitada. O usuário foi removido.';
        $show_form = false;
        $user = null;
        
    } catch (PDOException $e) {
        $error = 'Erro ao rejeitar: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: white;
        }
        .container {
            max-width: 500px;
            width: 100%;
        }
        .card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
        }
        .logo {
            font-size: 3rem;
            margin-bottom: 20px;
        }
        .logo.trial_7d { color: #10b981; }
        .logo.trial_24h { color: #ec4899; }
        h1 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        .subtitle {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 30px;
        }
        .user-info {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            text-align: left;
        }
        .user-info-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .user-info-item:last-child {
            border-bottom: none;
        }
        .user-info-item i {
            width: 20px;
            color: #c084fc;
        }
        .user-info-item label {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.85rem;
            min-width: 80px;
        }
        .user-info-item span {
            color: white;
            font-weight: 500;
        }
        .trial-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        .trial-badge.trial_7d {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        .trial-badge.trial_24h {
            background: rgba(236, 72, 153, 0.2);
            color: #ec4899;
        }
        .actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        .btn {
            flex: 1;
            padding: 14px 20px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
            text-decoration: none;
        }
        .btn-approve {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        .btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }
        .btn-reject {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .btn-reject:hover {
            background: rgba(239, 68, 68, 0.3);
        }
        .btn-whatsapp {
            background: linear-gradient(135deg, #25D366, #128C7E);
            color: white;
            width: 100%;
            padding: 16px;
            font-size: 1.1rem;
        }
        .btn-whatsapp:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 211, 102, 0.3);
        }
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }
        .alert-warning {
            background: rgba(245, 158, 11, 0.2);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #f59e0b;
        }
        .status-approved {
            color: #10b981;
            font-weight: 600;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            margin-top: 20px;
            font-size: 0.9rem;
        }
        .back-link:hover {
            color: white;
        }
        .no-token {
            color: rgba(255, 255, 255, 0.6);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <?php if (!$token): ?>
                <div class="logo"><i class="fas fa-link-slash"></i></div>
                <h1>Link Inválido</h1>
                <p class="no-token">Nenhum token de liberação foi fornecido.</p>
                <a href="/admin/index.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Voltar ao painel
                </a>
                
            <?php elseif ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <a href="/admin/index.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Voltar ao painel
                </a>
                
            <?php elseif ($message && !$show_form): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                
                <?php if ($whatsapp_link): ?>
                    <a href="<?php echo htmlspecialchars($whatsapp_link); ?>" target="_blank" class="btn btn-whatsapp">
                        <i class="fab fa-whatsapp"></i> Enviar WhatsApp
                    </a>
                    <p style="margin-top: 15px; font-size: 0.85rem; color: rgba(255,255,255,0.6);">
                        Clique no botão acima para abrir o WhatsApp e enviar a mensagem de liberação.
                    </p>
                <?php endif; ?>
                
                <?php if ($user): ?>
                    <div class="user-info" style="margin-top: 20px;">
                        <div class="user-info-item">
                            <i class="fas fa-user"></i>
                            <label>Nome:</label>
                            <span><?php echo htmlspecialchars($user['name']); ?></span>
                        </div>
                        <div class="user-info-item">
                            <i class="fas fa-envelope"></i>
                            <label>Email:</label>
                            <span><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <div class="user-info-item">
                            <i class="fas fa-check-circle"></i>
                            <label>Status:</label>
                            <span class="status-approved">Aprovado</span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <a href="usuarios.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Ver todos os usuários
                </a>
                
            <?php elseif ($show_form && $user): ?>
                <div class="logo <?php echo $user['role']; ?>">
                    <?php if ($user['role'] === 'trial_7d'): ?>
                        <i class="fas fa-calendar-week"></i>
                    <?php else: ?>
                        <i class="fas fa-clock"></i>
                    <?php endif; ?>
                </div>
                
                <h1>Solicitação de Acesso</h1>
                <p class="subtitle">Um novo usuário solicitou acesso trial</p>
                
                <div class="trial-badge <?php echo $user['role']; ?>">
                    <?php if ($user['role'] === 'trial_7d'): ?>
                        <i class="fas fa-calendar-week"></i> Acesso de 7 Dias
                    <?php else: ?>
                        <i class="fas fa-clock"></i> Degustação 24h (3 palestras)
                    <?php endif; ?>
                </div>
                
                <div class="user-info">
                    <div class="user-info-item">
                        <i class="fas fa-user"></i>
                        <label>Nome:</label>
                        <span><?php echo htmlspecialchars($user['name']); ?></span>
                    </div>
                    <div class="user-info-item">
                        <i class="fas fa-envelope"></i>
                        <label>Email:</label>
                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="user-info-item">
                        <i class="fab fa-whatsapp"></i>
                        <label>WhatsApp:</label>
                        <span><?php echo htmlspecialchars(formatPhoneDisplay($user['whatsapp'])); ?></span>
                    </div>
                    <div class="user-info-item">
                        <i class="fas fa-calendar"></i>
                        <label>Cadastro:</label>
                        <span><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></span>
                    </div>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="actions">
                        <button type="submit" name="action" value="approve" class="btn btn-approve">
                            <i class="fas fa-check"></i> Aprovar
                        </button>
                        <button type="submit" name="action" value="reject" class="btn btn-reject" 
                                onclick="return confirm('Tem certeza que deseja rejeitar esta solicitação?');">
                            <i class="fas fa-times"></i> Rejeitar
                        </button>
                    </div>
                </form>
                
                <a href="usuarios.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Ver todos os usuários
                </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>