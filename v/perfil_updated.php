<?php
require_once __DIR__ . '/../config/database.php';

// Verificar se está logado
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Verificar se o usuário tem permissão (apenas usuário próprio ou admin)
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['subscriber','admin'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Buscar dados completos do usuário
try {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header('Location: logout.php');
        exit;
    }
} catch (Exception $e) {
    $error = 'Erro ao carregar dados do perfil.';
}

// Buscar certificados do usuário para estatísticas
$certificates_stats = [];
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_certificates,
               SUM(duration_hours) as total_hours
        FROM certificates 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $certificates_stats = $stmt->fetch();
} catch (Exception $e) {
    // Ignorar erro para não quebrar a página
    $certificates_stats = ['total_certificates' => 0, 'total_hours' => 0];
}

// Buscar lista de palestras que o usuário quer assistir (watchlist)
$user_watchlist = [];
try {
    $stmt = $pdo->prepare("
        SELECT w.id as watchlist_id,
               w.added_at,
               l.id as lecture_id,
               l.title,
               l.speaker,
               l.description,
               l.category,
               l.duration_minutes,
               l.thumbnail_url,
               l.is_featured
        FROM user_watchlist w
        JOIN lectures l ON w.lecture_id = l.id
        WHERE w.user_id = ?
        ORDER BY w.added_at DESC
    ");
    $stmt->execute([$user_id]);
    $user_watchlist = $stmt->fetchAll();
} catch (Exception $e) {
    // Ignorar erro para não quebrar a página
}

// Buscar lista de certificados/palestras do usuário (mais recente primeiro)
$user_certificates = [];
try {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               l.title as lecture_title,
               l.speaker as speaker_name,
               l.duration_minutes
        FROM certificates c
        LEFT JOIN lectures l ON c.lecture_id = l.id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $user_certificates = $stmt->fetchAll();
} catch (Exception $e) {
    // Ignorar erro para não quebrar a página
}

// Processar atualização do perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (!empty($name) && !empty($email)) {
        try {
            // Verificar se email já existe (exceto o próprio usuário)
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $stmt->execute([$email, $user_id]);
            
            if ($stmt->fetch()) {
                $error = 'Este email já está sendo usado por outro usuário.';
            } else {
                // Atualizar dados básicos
                $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
                $stmt->execute([$name, $email, $user_id]);
                
                // Atualizar sessão
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                
                $message = 'Dados atualizados com sucesso!';
                
                // Atualizar senha se fornecida
                if (!empty($new_password)) {
                    if (password_verify($current_password, $user['password_hash'])) {
                        if ($new_password === $confirm_password) {
                            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                            $stmt->execute([$new_hash, $user_id]);
                            $message .= ' Senha alterada com sucesso!';
                        } else {
                            $error = 'A confirmação da nova senha não confere.';
                        }
                    } else {
                        $error = 'Senha atual incorreta.';
                    }
                }
                
                // Recarregar dados do usuário
                $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            }
        } catch (Exception $e) {
            $error = 'Erro ao atualizar perfil: ' . $e->getMessage();
        }
    } else {
        $error = 'Nome e email são obrigatórios.';
    }
}

$page_title = 'Meu Perfil - Translators101';
$page_description = 'Gerencie suas informações pessoais e configurações de conta';

include __DIR__ . '/vision/includes/head.php';
include __DIR__ . '/vision/includes/header.php';
include __DIR__ . '/vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-user-circle"></i> Meu Perfil</h1>
            <p>Gerencie suas informações pessoais e configurações de conta</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Navigation Quick Actions -->
    <div class="profile-nav-section">
        <div class="quick-actions-grid">
            <a href="videoteca.php" class="quick-action-card">
                <div class="action-icon">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="action-content">
                    <h3>Videoteca</h3>
                    <p>Acesse suas aulas e conteúdos</p>
                </div>
            </a>
            
            <a href="glossarios.php" class="quick-action-card">
                <div class="action-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="action-content">
                    <h3>Glossários</h3>
                    <p>Consulte termos e definições</p>
                </div>
            </a>
            
            <a href="dash-t101/index.php" class="quick-action-card">
                <div class="action-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="action-content">
                    <h3>Dashboard</h3>
                    <p>Visualize seu progresso</p>
                </div>
            </a>
        </div>
    </div>

    <!-- NOVA SEÇÃO: Minha Lista de Palestras para Assistir -->
    <?php if (!empty($user_watchlist)): ?>
    <div class="video-card profile-card">
        <div class="card-header">
            <h2><i class="fas fa-list-ul"></i> Minha Lista</h2>
            <p class="text-light">Palestras que você salvou para assistir</p>
        </div>
        
        <div class="watchlist-grid">
            <?php foreach ($user_watchlist as $item): ?>
            <div class="watchlist-item" onclick="location.href='/palestra.php?id=<?php echo $item['lecture_id']; ?>'">
                <div class="watchlist-thumbnail">
                    <?php if (!empty($item['thumbnail_url'])): ?>
                        <img src="<?php echo htmlspecialchars($item['thumbnail_url']); ?>" 
                             alt="<?php echo htmlspecialchars($item['title']); ?>"
                             class="watchlist-image">
                    <?php else: ?>
                        <div class="watchlist-placeholder">
                            <i class="fas fa-video"></i>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($item['is_featured'])): ?>
                        <span class="watchlist-badge featured">
                            <i class="fas fa-star"></i> Destaque
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="watchlist-info">
                    <h4><?php echo htmlspecialchars($item['title']); ?></h4>
                    
                    <?php if (!empty($item['speaker'])): ?>
                        <p class="watchlist-speaker">
                            <i class="fas fa-user"></i>
                            <?php echo htmlspecialchars($item['speaker']); ?>
                        </p>
                    <?php endif; ?>
                    
                    <div class="watchlist-meta">
                        <?php if (!empty($item['category'])): ?>
                            <span class="watchlist-category">
                                <?php echo htmlspecialchars($item['category']); ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php if (!empty($item['duration_minutes'])): ?>
                            <span class="watchlist-duration">
                                <i class="fas fa-clock"></i>
                                <?php echo $item['duration_minutes']; ?>min
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="watchlist-actions" onclick="event.stopPropagation();">
                        <button class="btn-small btn-remove" 
                                onclick="removeFromWatchlist('<?php echo $item['lecture_id']; ?>', this)">
                            <i class="fas fa-times"></i> Remover
                        </button>
                        
                        <span class="watchlist-added">
                            Adicionado em <?php echo date('d/m/Y', strtotime($item['added_at'])); ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="video-card profile-card">
        <div class="card-header">
            <h2><i class="fas fa-list-ul"></i> Minha Lista</h2>
        </div>
        <div class="no-watchlist">
            <div class="no-watchlist-icon">
                <i class="fas fa-bookmark"></i>
            </div>
            <h3>Sua lista está vazia</h3>
            <p>Adicione palestras à sua lista para assisti-las mais tarde. Vá até a videoteca e marque as palestras que deseja assistir.</p>
            <a href="videoteca.php" class="cta-btn">
                <i class="fas fa-video"></i>
                Ir para videoteca
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Primera linha: Informações pessoais e Alterar senha -->
    <div class="profile-row">
        <!-- Informações Pessoais -->
        <div class="video-card profile-card">
            <div class="card-header">
                <h2><i class="fas fa-id-card"></i> Informações Pessoais</h2>
            </div>
            
            <form method="POST" class="vision-form profile-form">
                <div class="profile-form-content">
                    <div class="form-fields">
                        <div class="form-group">
                            <label for="name">
                                <i class="fas fa-user"></i> Nome Completo
                            </label>
                            <input type="text" id="name" name="name" required 
                                   value="<?php echo htmlspecialchars($user['name']); ?>">
                        </div>

                        <div class="form-group">
                            <label for="email">
                                <i class="fas fa-envelope"></i> Email
                            </label>
                            <input type="email" id="email" name="email" required 
                                   value="<?php echo htmlspecialchars($user['email']); ?>">
                        </div>
                    </div>

                    <div class="form-actions-right">
                        <button type="submit" class="cta-btn">
                            <i class="fas fa-save"></i> Salvar Alterações
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Alterar Senha -->
        <div class="video-card profile-card">
            <div class="card-header">
                <h2><i class="fas fa-lock"></i> Alterar Senha</h2>
                <p class="text-light">Deixe em branco se não quiser alterar a senha</p>
            </div>
            
            <form method="POST" class="vision-form profile-form">
                <!-- Campos ocultos para manter os dados do perfil -->
                <input type="hidden" name="name" value="<?php echo htmlspecialchars($user['name']); ?>">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                
                <div class="profile-form-content">
                    <div class="form-fields">
                        <div class="form-group">
                            <label for="current_password">
                                <i class="fas fa-key"></i> Senha Atual
                            </label>
                            <input type="password" id="current_password" name="current_password" 
                                   placeholder="Digite sua senha atual">
                        </div>

                        <div class="form-group">
                            <label for="new_password">
                                <i class="fas fa-lock"></i> Nova Senha
                            </label>
                            <input type="password" id="new_password" name="new_password" 
                                   placeholder="Digite a nova senha">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">
                                <i class="fas fa-lock"></i> Confirmar Nova Senha
                            </label>
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   placeholder="Confirme a nova senha">
                        </div>
                    </div>

                    <div class="form-actions-right">
                        <button type="submit" class="cta-btn">
                            <i class="fas fa-shield-alt"></i> Alterar Senha
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Segunda linha: Account Info, Estatísticas e Relatório/Segurança -->
    <div class="profile-row profile-row-three">
        <!-- Account Information -->
        <div class="video-card profile-card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Informações da Conta</h3>
            </div>
            <div class="account-info">
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-tag"></i> Tipo de Conta:</span>
                    <span class="status-badge status-<?php echo $user['role']; ?>">
                        <?php echo ucfirst($user['role'] ?? 'subscriber'); ?>
                    </span>
                </div>
                
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-check-circle"></i> Status:</span>
                    <span class="status-badge <?php echo ($user['is_active'] ?? true) ? 'status-completed' : 'status-pending'; ?>">
                        <?php echo ($user['is_active'] ?? true) ? 'Ativa' : 'Inativa'; ?>
                    </span>
                </div>
                
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-calendar"></i> Membro desde:</span>
                    <span class="info-value"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></span>
                </div>
                
                <?php if (isset($user['updated_at']) && $user['updated_at']): ?>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-clock"></i> Última atualização:</span>
                        <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($user['updated_at'])); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Subscription Information -->
                <?php if (($user['is_subscriber'] ?? false) || !empty($user['subscription_type'])): ?>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-star"></i> Plano:</span>
                        <span class="status-badge status-premium">
                            <?php 
                            $subscription_labels = [
                                'monthly' => 'Mensal',
                                'quarterly' => 'Trimestral', 
                                'biannual' => 'Semestral',
                                'annual' => 'Anual'
                            ];
                            echo $subscription_labels[$user['subscription_type']] ?? ucfirst($user['subscription_type'] ?? 'Ativo');
                            ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($user['subscription_expires'])): ?>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-calendar-alt"></i> Expira em:</span>
                            <span class="info-value"><?php echo date('d/m/Y', strtotime($user['subscription_expires'])); ?></span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistics -->
        <div class="video-card profile-card">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar"></i> Estatísticas</h3>
            </div>
            <div class="compact-stats-grid">
                <div class="compact-stat-item">
                    <div class="compact-stat-icon">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <div class="compact-stat-content">
                        <div class="compact-stat-number"><?php echo ($certificates_stats['total_certificates'] ?? 0); ?></div>
                        <div class="compact-stat-label">Certificados</div>
                    </div>
                </div>
                
                <div class="compact-stat-item">
                    <div class="compact-stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="compact-stat-content">
                        <div class="compact-stat-number"><?php echo number_format(($certificates_stats['total_hours'] ?? 0), 1); ?>h</div>
                        <div class="compact-stat-label">Horas</div>
                    </div>
                </div>
                
                <div class="compact-stat-item">
                    <div class="compact-stat-icon">
                        <i class="fas fa-list-ul"></i>
                    </div>
                    <div class="compact-stat-content">
                        <div class="compact-stat-number"><?php echo count($user_watchlist); ?></div>
                        <div class="compact-stat-label">Na Lista</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Relatório e Segurança Combinados -->
        <div class="video-card profile-card">
            <div class="card-header">
                <h3><i class="fas fa-file-pdf"></i> Relatório & Segurança</h3>
            </div>
            
            <!-- Relatório Section -->
            <div class="report-section-compact">
                <p class="text-light">Gere um relatório completo com todas as suas capacitações.</p>
                
                <div class="report-actions-compact">
                    <button id="generateReportBtn" onclick="generateReport()" class="cta-btn btn-small-cta">
                        <i class="fas fa-file-download"></i>
                        Gerar Relatório
                    </button>
                    
                    <div id="reportStatus" class="report-status" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i>
                        Gerando...
                    </div>
                    
                    <div id="reportDownload" class="report-download" style="display: none;">
                        <div class="alert-success">
                            <i class="fas fa-check-circle"></i>
                            Relatório pronto!
                            <div style="margin-top: 10px;">
                                <a id="downloadReportLink" href="#" class="cta-btn btn-small-cta" target="_blank">
                                    <i class="fas fa-download"></i>
                                    Baixar PDF
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Divider -->
            <hr class="section-divider">

            <!-- Security Info -->
            <div class="security-info">
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-key"></i> Senha:</span>
                    <span class="security-status">
                        <i class="fas fa-check-circle text-success"></i> Protegida
                    </span>
                </div>
                
                <div class="info-item">
                    <span class="info-label"><i class="fas fa-user-shield"></i> Autenticação:</span>
                    <span class="security-status">
                        <i class="fas fa-check-circle text-success"></i> Ativa
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Palestras Assistidas -->
    <?php if (!empty($user_certificates)): ?>
    <div class="video-card profile-card">
        <div class="card-header">
            <h2><i class="fas fa-list-alt"></i> Palestras Assistidas</h2>
            <p class="text-light">Lista das suas capacitações concluídas, da mais recente para a mais antiga</p>
        </div>
        
        <div class="certificates-list">
            <?php foreach ($user_certificates as $index => $cert): ?>
            <div class="certificate-item">
                <div class="certificate-info">
                    <div class="certificate-header">
                        <h4><?php echo htmlspecialchars($cert['lecture_title'] ?: 'Título não disponível'); ?></h4>
                        <span class="certificate-date">
                            <i class="fas fa-calendar"></i>
                            <?php echo date('d/m/Y', strtotime($cert['created_at'])); ?>
                        </span>
                    </div>
                    
                    <div class="certificate-details">
                        <?php if ($cert['speaker_name']): ?>
                        <div class="detail">
                            <i class="fas fa-user"></i>
                            <span><?php echo htmlspecialchars($cert['speaker_name']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="detail">
                            <i class="fas fa-clock"></i>
                            <span><?php echo number_format($cert['duration_hours'] ?? 0, 1); ?> horas</span>
                        </div>
                        
                        <div class="detail">
                            <i class="fas fa-fingerprint"></i>
                            <span class="certificate-id"><?php echo htmlspecialchars($cert['id']); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="certificate-actions">
                    <a href="view_certificate_files.php?id=<?php echo $cert['id']; ?>" 
                       class="btn-small" target="_blank">
                        <i class="fas fa-eye"></i> Ver
                    </a>
                    
                    <a href="download_certificate_files.php?id=<?php echo $cert['id']; ?>" 
                       class="btn-small">
                        <i class="fas fa-download"></i> Baixar PNG
                    </a>
                    
                    <a href="verificar_certificado.php?id=<?php echo $cert['id']; ?>" 
                       class="btn-small btn-verify" target="_blank">
                        <i class="fas fa-shield-check"></i> Verificar
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="video-card profile-card">
        <div class="card-header">
            <h2><i class="fas fa-list-alt"></i> Palestras Assistidas</h2>
        </div>
        <div class="no-certificates">
            <div class="no-certificates-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <h3>Nenhuma palestra concluída ainda</h3>
            <p>Comece sua jornada de educação continuada assistindo às palestras disponíveis na videoteca.</p>
            <a href="videoteca.php" class="cta-btn">
                <i class="fas fa-video"></i>
                Ir para videoteca
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function generateReport() {
    const generateBtn = document.getElementById('generateReportBtn');
    const reportStatus = document.getElementById('reportStatus');
    const reportDownload = document.getElementById('reportDownload');
    
    // Mostrar status de carregamento
    generateBtn.style.display = 'none';
    reportStatus.style.display = 'flex';
    reportDownload.style.display = 'none';
    
    // Fazer requisição para gerar relatório
    fetch('generate_report.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'generate_report'
        })
    })
    .then(response => response.json())
    .then(data => {
        reportStatus.style.display = 'none';
        
        if (data.success) {
            // Mostrar link de download
            document.getElementById('downloadReportLink').href = data.download_url;
            reportDownload.style.display = 'block';
            
            console.log('✅ Relatório gerado com sucesso:', data.report_path);
        } else {
            // Mostrar erro e reativar botão
            alert('❌ Erro ao gerar relatório: ' + (data.message || 'Erro desconhecido'));
            generateBtn.style.display = 'inline-flex';
        }
    })
    .catch(error => {
        console.error('❌ Erro na requisição:', error);
        reportStatus.style.display = 'none';
        generateBtn.style.display = 'inline-flex';
        alert('❌ Erro inesperado ao gerar relatório. Tente novamente.');
    });
}

function removeFromWatchlist(lectureId, buttonElement) {
    // Mostrar loading no botão
    const originalText = buttonElement.innerHTML;
    buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Removendo...';
    buttonElement.disabled = true;
    
    // Fazer requisição para remover da watchlist
    fetch('api_watchlist.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            lecture_id: lectureId,
            action: 'remove'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remover o item da lista com animação
            const watchlistItem = buttonElement.closest('.watchlist-item');
            watchlistItem.style.opacity = '0';
            watchlistItem.style.transform = 'translateX(-20px)';
            
            setTimeout(() => {
                watchlistItem.remove();
                
                // Verificar se a lista ficou vazia
                const remainingItems = document.querySelectorAll('.watchlist-item');
                if (remainingItems.length === 0) {
                    location.reload(); // Recarregar para mostrar o estado vazio
                }
            }, 300);
        } else {
            // Restaurar botão em caso de erro
            buttonElement.innerHTML = originalText;
            buttonElement.disabled = false;
            alert('Erro ao remover palestra da lista: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        // Restaurar botão em caso de erro
        buttonElement.innerHTML = originalText;
        buttonElement.disabled = false;
        console.error('Erro de rede:', error);
        alert('Erro inesperado ao remover da lista. Tente novamente.');
    });
}
</script>

<style>
    .quick-action-card {
      margin-bottom: 20px;
    }
    
    /* Estilos para a watchlist no perfil */
    .watchlist-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .watchlist-item {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 16px;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .watchlist-item:hover {
        background: rgba(255, 255, 255, 0.08);
        border-color: rgba(255, 255, 255, 0.2);
        transform: translateY(-2px);
    }
    
    .watchlist-thumbnail {
        position: relative;
        width: 100%;
        height: 120px;
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 12px;
    }
    
    .watchlist-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .watchlist-placeholder {
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, var(--brand-purple), #5e3370);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: rgba(255, 255, 255, 0.7);
    }
    
    .watchlist-badge {
        position: absolute;
        top: 8px;
        right: 8px;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    
    .watchlist-badge.featured {
        background: linear-gradient(135deg, #ff6b35, #f7931e);
        color: white;
    }
    
    .watchlist-info h4 {
        color: white;
        margin-bottom: 8px;
        font-size: 1rem;
        line-height: 1.3;
    }
    
    .watchlist-speaker {
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.85rem;
        margin-bottom: 8px;
    }
    
    .watchlist-meta {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
        flex-wrap: wrap;
    }
    
    .watchlist-category {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: white;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: capitalize;
    }
    
    .watchlist-duration {
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.75rem;
    }
    
    .watchlist-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 12px;
        padding-top: 8px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .btn-remove {
        background: #dc2626;
        color: white;
        border: none;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.75rem;
        cursor: pointer;
        transition: background 0.3s ease;
    }
    
    .btn-remove:hover {
        background: #b91c1c;
    }
    
    .watchlist-added {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.7rem;
    }
    
    .no-watchlist {
        text-align: center;
        padding: 40px 20px;
        color: rgba(255, 255, 255, 0.8);
    }
    
    .no-watchlist-icon {
        font-size: 3rem;
        margin-bottom: 20px;
        opacity: 0.6;
    }
    
    .no-watchlist h3 {
        color: white;
        margin-bottom: 12px;
    }
    
    .no-watchlist p {
        margin-bottom: 24px;
        max-width: 400px;
        margin-left: auto;
        margin-right: auto;
    }

    /* Responsividade para a watchlist */
    @media (max-width: 768px) {
        .watchlist-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
<?php include __DIR__ . '/vision/includes/footer.php'; ?>