<?php
/**
 * Script para criar a tabela hotmart_logs automaticamente
 * Execute este arquivo uma vez para configurar a integração Hotmart
 */

session_start();
require_once __DIR__ . '/../config/database.php';

// Verificar se é admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$success = false;
$error = '';
$table_exists = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_table'])) {
    try {
        // Se estiver usando mock, simular sucesso
        if ($pdo instanceof MockPDO) {
            $success = true;
            $message = 'Tabela criada com sucesso no ambiente de desenvolvimento (mock).';
        } else {
            // Verificar se a tabela já existe
            $stmt = $pdo->query("SHOW TABLES LIKE 'hotmart_logs'");
            if ($stmt && $stmt->fetchColumn()) {
                $table_exists = true;
                $message = 'A tabela hotmart_logs já existe no banco de dados.';
            } else {
                // Criar a tabela
                $sql = "CREATE TABLE IF NOT EXISTS `hotmart_logs` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `event_type` varchar(100) NOT NULL COMMENT 'Tipo do evento (PURCHASE_COMPLETE, etc.)',
                  `status` enum('success','error') NOT NULL DEFAULT 'success',
                  `user_email` varchar(255) DEFAULT NULL,
                  `user_name` varchar(255) DEFAULT NULL,
                  `transaction_id` varchar(100) DEFAULT NULL,
                  `product_id` varchar(100) DEFAULT NULL,
                  `product_name` varchar(255) DEFAULT NULL,
                  `price` decimal(10,2) DEFAULT NULL,
                  `currency` varchar(10) DEFAULT 'BRL',
                  `hotmart_status` varchar(50) DEFAULT NULL,
                  `webhook_data` text COMMENT 'Dados JSON completos do webhook',
                  `response_data` text COMMENT 'Resposta do processamento',
                  `error_message` varchar(500) DEFAULT NULL,
                  `ip_address` varchar(45) DEFAULT NULL,
                  `user_agent` text,
                  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `idx_event_type` (`event_type`),
                  KEY `idx_status` (`status`),
                  KEY `idx_user_email` (`user_email`),
                  KEY `idx_created_at` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                
                $pdo->exec($sql);
                
                // Inserir dados de exemplo
                $stmt = $pdo->prepare("INSERT INTO `hotmart_logs` (`event_type`, `status`, `user_email`, `user_name`, `transaction_id`, `product_id`, `product_name`, `price`, `currency`, `hotmart_status`, `webhook_data`, `response_data`, `ip_address`, `user_agent`) VALUES
                    ('PURCHASE_COMPLETE', 'success', 'exemplo@email.com', 'Usuário Exemplo', 'HP123456789', 'PROD001', 'Curso Translators101', 197.00, 'BRL', 'APPROVED', '{\"event\":\"PURCHASE_COMPLETE\",\"data\":{\"buyer\":{\"email\":\"exemplo@email.com\",\"name\":\"Usuário Exemplo\"}}}', 'Usuário criado com sucesso', '192.168.1.1', 'Hotmart-Webhook/1.0')");
                
                $stmt->execute();
                
                $success = true;
                $message = 'Tabela hotmart_logs criada com sucesso no banco de dados!';
            }
        }
    } catch (PDOException $e) {
        $error = 'Erro ao criar tabela: ' . $e->getMessage();
    }
}

include __DIR__ . '/../vision/includes/head.php';
?>

<style>
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(135deg, #1a1a1a 0%, #2d1b45 100%);
        color: white;
        min-height: 100vh;
        margin: 0;
        padding: 20px;
    }

    .container {
        max-width: 800px;
        margin: 0 auto;
    }

    .glass-hero {
        background: rgba(25, 25, 25, 0.8);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 40px;
        margin-bottom: 30px;
        text-align: center;
    }

    .glass-hero h1 {
        color: #c084fc;
        font-size: 2.5rem;
        margin-bottom: 15px;
    }

    .video-card {
        background: rgba(30, 30, 30, 0.6);
        border-radius: 15px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        padding: 30px;
        margin-bottom: 20px;
    }

    .cta-btn {
        background: linear-gradient(135deg, #c084fc, #a855f7);
        color: white;
        padding: 15px 30px;
        text-decoration: none;
        border-radius: 12px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        font-size: 1rem;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .cta-btn:hover {
        background: linear-gradient(135deg, #a855f7, #9333ea);
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(192, 132, 252, 0.3);
    }

    .success-alert {
        background: rgba(16, 185, 129, 0.2);
        border: 1px solid rgba(16, 185, 129, 0.5);
        color: #10f981;
        padding: 15px 20px;
        border-radius: 12px;
        margin: 20px 0;
        font-weight: 500;
    }

    .error-alert {
        background: rgba(239, 68, 68, 0.2);
        border: 1px solid rgba(239, 68, 68, 0.5);
        color: #ff6b6b;
        padding: 15px 20px;
        border-radius: 12px;
        margin: 20px 0;
        font-weight: 500;
    }

    .info-alert {
        background: rgba(59, 130, 246, 0.2);
        border: 1px solid rgba(59, 130, 246, 0.5);
        color: #3b82f6;
        padding: 15px 20px;
        border-radius: 12px;
        margin: 20px 0;
        font-weight: 500;
    }

    .back-btn {
        background: rgba(255, 255, 255, 0.1);
        color: rgba(255, 255, 255, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .back-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        color: white;
    }

    code {
        background: rgba(0, 0, 0, 0.3);
        padding: 2px 6px;
        border-radius: 4px;
        color: #10b981;
        font-family: 'Courier New', monospace;
    }
</style>

<div class="container">
    <div class="glass-hero">
        <h1><i class="fas fa-database"></i> Configurar Tabela Hotmart</h1>
        <p>Utilitário para criar a tabela hotmart_logs automaticamente</p>
    </div>

    <?php if ($success): ?>
        <div class="success-alert">
            <i class="fas fa-check-circle"></i>
            <?php echo $message; ?>
        </div>
        
        <div class="video-card">
            <h3><i class="fas fa-arrow-right"></i> Próximos Passos</h3>
            <p>A tabela foi criada com sucesso! Agora você pode:</p>
            <ul style="color: #ddd; margin: 15px 0;">
                <li>Acessar a página de monitoramento da integração Hotmart</li>
                <li>Configurar o webhook no painel da Hotmart</li>
                <li>Testar a integração com transações reais</li>
            </ul>
            
            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <a href="hotmart.php" class="cta-btn">
                    <i class="fas fa-chart-bar"></i> Ver Logs Hotmart
                </a>
                <a href="index.php" class="cta-btn back-btn">
                    <i class="fas fa-arrow-left"></i> Voltar ao Admin
                </a>
            </div>
        </div>
    
    <?php elseif ($table_exists): ?>
        <div class="info-alert">
            <i class="fas fa-info-circle"></i>
            <?php echo $message; ?>
        </div>
        
        <div class="video-card">
            <h3><i class="fas fa-check-circle"></i> Tabela Já Configurada</h3>
            <p>A tabela <code>hotmart_logs</code> já existe no seu banco de dados. Você pode acessar os logs diretamente.</p>
            
            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <a href="hotmart.php" class="cta-btn">
                    <i class="fas fa-chart-bar"></i> Ver Logs Hotmart
                </a>
                <a href="index.php" class="cta-btn back-btn">
                    <i class="fas fa-arrow-left"></i> Voltar ao Admin
                </a>
            </div>
        </div>
    
    <?php elseif ($error): ?>
        <div class="error-alert">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo $error; ?>
        </div>
        
        <div class="video-card">
            <h3><i class="fas fa-question-circle"></i> Erro na Criação</h3>
            <p>Houve um problema ao criar a tabela. Você pode tentar novamente ou criar manualmente via phpMyAdmin.</p>
            
            <form method="POST" style="margin-top: 20px;">
                <button type="submit" name="create_table" class="cta-btn">
                    <i class="fas fa-redo"></i> Tentar Novamente
                </button>
            </form>
        </div>
    
    <?php else: ?>
        <div class="video-card">
            <h3><i class="fas fa-database"></i> Criar Tabela hotmart_logs</h3>
            <p style="color: #ddd; margin-bottom: 20px;">
                Este utilitário criará automaticamente a tabela <code>hotmart_logs</code> no banco de dados.
                Esta tabela é necessária para armazenar os logs dos webhooks da Hotmart.
            </p>
            
            <div style="background: rgba(0, 0, 0, 0.2); padding: 20px; border-radius: 10px; margin: 20px 0;">
                <h4 style="color: #c084fc; margin-bottom: 15px;">O que será criado:</h4>
                <ul style="color: #ddd;">
                    <li>Tabela <code>hotmart_logs</code> com estrutura completa</li>
                    <li>Índices otimizados para performance</li>
                    <li>Dados de exemplo para teste</li>
                    <li>Configuração UTF-8 para suporte completo</li>
                </ul>
            </div>
            
            <div style="background: rgba(243, 156, 18, 0.1); padding: 15px; border-radius: 10px; border-left: 4px solid #f39c12; margin: 20px 0;">
                <h5 style="color: #f39c12; margin-bottom: 8px;"><i class="fas fa-exclamation-triangle"></i> Atenção</h5>
                <p style="color: #ddd; font-size: 0.9rem; margin: 0;">
                    Este processo criará uma nova tabela no banco de dados. 
                    Certifique-se de ter backup se necessário.
                </p>
            </div>
            
            <form method="POST" style="text-align: center;">
                <button type="submit" name="create_table" class="cta-btn" style="font-size: 1.1rem; padding: 18px 40px;">
                    <i class="fas fa-plus-circle"></i> Criar Tabela Hotmart
                </button>
            </form>
            
            <div style="margin-top: 20px; text-align: center;">
                <a href="hotmart.php" class="cta-btn back-btn">
                    <i class="fas fa-arrow-left"></i> Voltar sem Criar
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>