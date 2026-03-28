<?php
session_start();
require_once __DIR__ . '/../config/database.php';
date_default_timezone_set('America/Sao_Paulo');

// Verificação de autenticação
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

$page_title = 'Verificação de Certificados - Palestras Mapeadas';

// Buscar palestras recentemente mapeadas
$timeframes = [
    '24h' => '24 HOUR',
    '7d' => '7 DAY',
    '30d' => '30 DAY',
    'all' => 'all'
];

$selected_timeframe = isset($_GET['timeframe']) ? $_GET['timeframe'] : '7d';

// Inicializar variáveis
$mapped_lectures = [];
$certificate_stats = [];
$total_certificates = 0;
$total_users_with_certificates = 0;
$error_message = null;

try {
    // Buscar palestras mapeadas (ordenar por ID - últimas primeiro)
    $sql = "
        SELECT 
            hlm.id as mapping_id,
            hlm.hotmart_title,
            hlm.lecture_id,
            hlm.lecture_title,
            l.speaker,
            l.duration_minutes,
            l.created_at as lecture_created_at
        FROM hotmart_lecture_mapping hlm
        LEFT JOIN lectures l ON l.id = hlm.lecture_id
        ORDER BY hlm.id DESC
    ";
    
    // Aplicar limite se não for 'all'
    if ($selected_timeframe !== 'all') {
        $limits = ['24h' => 10, '7d' => 50, '30d' => 200];
        $limit = isset($limits[$selected_timeframe]) ? $limits[$selected_timeframe] : 50;
        $sql .= " LIMIT $limit";
    }
    
    $stmt = $pdo->query($sql);
    if ($stmt) {
        $mapped_lectures = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$mapped_lectures) {
            $mapped_lectures = [];
        }
    } else {
        $mapped_lectures = [];
        $error_message = "Erro ao executar query de palestras mapeadas.";
    }
    
    if (!$mapped_lectures) {
        $mapped_lectures = [];
    }

    // Para cada palestra mapeada, verificar certificados emitidos
    foreach ($mapped_lectures as $lecture) {
        // Buscar TODOS os certificados desta palestra
        $stmt = $pdo->prepare("
            SELECT 
                c.id,
                c.user_id,
                c.user_name,
                c.user_email,
                c.issued_at,
                c.generated_at,
                c.certificate_code,
                u.email as user_email_db,
                u.name as user_name_db
            FROM certificates c
            LEFT JOIN users u ON u.id = c.user_id
            WHERE c.lecture_id = ?
            ORDER BY c.issued_at DESC
        ");
        $stmt->execute([$lecture['lecture_id']]);
        $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $certs_before = 0;

        // Usuários que visualizaram mas não têm certificado
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                al.user_id,
                u.name,
                u.email,
                MAX(al.updated_at) as last_view,
                SUM(al.accumulated_watch_time) as total_watch_time
            FROM access_logs al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE al.lecture_id = ?
            AND al.user_id NOT IN (
                SELECT user_id FROM certificates WHERE lecture_id = ?
            )
            GROUP BY al.user_id, u.name, u.email
            HAVING SUM(al.accumulated_watch_time) > 0
            ORDER BY total_watch_time DESC
        ");
        $stmt->execute([$lecture['lecture_id'], $lecture['lecture_id']]);
        $users_without_cert = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $certificate_stats[] = [
            'lecture' => $lecture,
            'certificates_after' => $certificates,
            'certificates_before' => $certs_before,
            'users_without_cert' => $users_without_cert
        ];

        $total_certificates += count($certificates);
        $total_users_with_certificates += count($certificates);
    }

} catch (PDOException $e) {
    $error_message = "Erro ao buscar dados: " . $e->getMessage();
    $mapped_lectures = [];
    $certificate_stats = [];
} catch (Exception $e) {
    $error_message = "Erro inesperado: " . $e->getMessage();
    $mapped_lectures = [];
    $certificate_stats = [];
}

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<style>
    .certificates-page-container {
        padding: 2rem;
        max-width: 100%;
    }

    .certificates-hero {
        background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(56, 142, 60, 0.1));
        padding: 2rem;
        border-radius: 16px;
        margin-bottom: 2rem;
        border: 1px solid rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
    }

    .certificates-hero h1 {
        font-size: 2rem;
        font-weight: 700;
        margin: 0 0 0.5rem;
        background: linear-gradient(135deg, #4CAF50, #81C784);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .certificates-hero p {
        margin: 0;
        opacity: 0.8;
        font-size: 1.1rem;
    }

    .filter-section {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }

    .filter-btn {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        background: rgba(255, 255, 255, 0.05);
        color: #fff;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
        text-decoration: none;
        display: inline-block;
    }

    .filter-btn:hover {
        background: rgba(255, 255, 255, 0.1);
        transform: translateY(-2px);
    }

    .filter-btn.active {
        background: linear-gradient(135deg, #7B61FF, #483D8B);
        border-color: #7B61FF;
    }

    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: rgba(255, 255, 255, 0.05);
        padding: 1.5rem;
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        text-align: center;
        backdrop-filter: blur(10px);
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        background: linear-gradient(135deg, #FFD700, #FFA500);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin: 0.5rem 0;
    }

    .stat-label {
        font-size: 0.9rem;
        opacity: 0.7;
        margin: 0;
    }

    .lecture-card {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        backdrop-filter: blur(10px);
    }

    .lecture-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid rgba(255, 255, 255, 0.1);
    }

    .lecture-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #fff;
        margin-bottom: 0.5rem;
    }

    .lecture-meta {
        font-size: 0.9rem;
        opacity: 0.7;
        line-height: 1.6;
    }

    .cert-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        margin: 0.25rem;
    }

    .cert-badge.success {
        background: rgba(76, 175, 80, 0.2);
        border: 1px solid rgba(76, 175, 80, 0.5);
        color: #81C784;
    }

    .cert-badge.warning {
        background: rgba(255, 193, 7, 0.2);
        border: 1px solid rgba(255, 193, 7, 0.5);
        color: #FFD54F;
    }

    .user-list {
        max-height: 400px;
        overflow-y: auto;
        padding-right: 0.5rem;
    }

    .user-item {
        padding: 1rem;
        margin-bottom: 0.75rem;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
    }

    .user-item strong {
        color: #FFD700;
    }

    .user-item small {
        display: block;
        margin-top: 0.25rem;
        opacity: 0.7;
    }

    .no-data {
        text-align: center;
        padding: 3rem;
        opacity: 0.5;
    }

    .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        background: linear-gradient(135deg, #7B61FF, #483D8B);
        border: none;
        border-radius: 8px;
        color: #fff;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .back-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(123, 97, 255, 0.4);
    }

    .alert-danger {
        background: rgba(244, 67, 54, 0.2);
        border: 1px solid rgba(244, 67, 54, 0.5);
        color: #E57373;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }

    .user-list::-webkit-scrollbar {
        width: 6px;
    }

    .user-list::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 3px;
    }

    .user-list::-webkit-scrollbar-thumb {
        background: rgba(255, 215, 0, 0.5);
        border-radius: 3px;
    }

    .user-list::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 215, 0, 0.7);
    }
</style>

<div class="main-content">
    <div class="certificates-page-container">
        <!-- Hero Section -->
        <div class="certificates-hero">
            <h1><i class="fas fa-certificate"></i> Verificação de Certificados</h1>
            <p>Acompanhe os certificados emitidos para palestras que foram mapeadas</p>
        </div>

        <!-- Filtro de Período -->
        <div class="filter-section">
            <a href="?timeframe=24h" class="filter-btn <?php echo $selected_timeframe === '24h' ? 'active' : ''; ?>">
                Últimas 24 horas
            </a>
            <a href="?timeframe=7d" class="filter-btn <?php echo $selected_timeframe === '7d' ? 'active' : ''; ?>">
                Últimos 7 dias
            </a>
            <a href="?timeframe=30d" class="filter-btn <?php echo $selected_timeframe === '30d' ? 'active' : ''; ?>">
                Últimos 30 dias
            </a>
            <a href="?timeframe=all" class="filter-btn <?php echo $selected_timeframe === 'all' ? 'active' : ''; ?>">
                Todos
            </a>
        </div>

        <!-- Estatísticas Gerais -->
        <div class="stats-row">
            <div class="stat-card">
                <i class="fas fa-link" style="font-size: 2rem; color: #2196F3;"></i>
                <div class="stat-number"><?php echo is_array($mapped_lectures) ? count($mapped_lectures) : 0; ?></div>
                <p class="stat-label">Palestras Mapeadas</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-certificate" style="font-size: 2rem; color: #4CAF50;"></i>
                <div class="stat-number"><?php echo isset($total_certificates) ? $total_certificates : 0; ?></div>
                <p class="stat-label">Certificados Emitidos</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-users" style="font-size: 2rem; color: #FFD700;"></i>
                <div class="stat-number"><?php echo isset($total_users_with_certificates) ? $total_users_with_certificates : 0; ?></div>
                <p class="stat-label">Usuários Certificados</p>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($mapped_lectures)): ?>
            <div class="lecture-card">
                <div class="no-data">
                    <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                    <p>Nenhuma palestra mapeada no período selecionado.</p>
                </div>
            </div>
        <?php else: ?>
            <!-- Lista de Palestras -->
            <?php foreach ($certificate_stats as $stat): 
                $lecture = $stat['lecture'];
                $certs_after = $stat['certificates_after'];
                $certs_before = $stat['certificates_before'];
                $users_without = $stat['users_without_cert'];
            ?>
                <div class="lecture-card">
                    <div class="lecture-header">
                        <div>
                            <div class="lecture-title">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <?php echo htmlspecialchars($lecture['lecture_title']); ?>
                            </div>
                            <div class="lecture-meta">
                                <strong>Hotmart:</strong> <?php echo htmlspecialchars($lecture['hotmart_title']); ?><br>
                                <strong>Palestrante:</strong> <?php echo htmlspecialchars($lecture['speaker']); ?> | 
                                <strong>Duração:</strong> <?php echo $lecture['duration_minutes']; ?> min
                            </div>
                        </div>
                        <div>
                            <span class="cert-badge success">
                                <i class="fas fa-certificate"></i> <?php echo count($certs_after); ?> certificados
                            </span>
                            <?php if (count($users_without) > 0): ?>
                                <span class="cert-badge warning">
                                    <i class="fas fa-exclamation-triangle"></i> <?php echo count($users_without); ?> pendentes
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <!-- Certificados Emitidos -->
                        <div>
                            <h5 style="margin-bottom: 1rem; color: #4CAF50;">
                                <i class="fas fa-check-circle"></i> Certificados Emitidos
                            </h5>
                            <?php if (empty($certs_after)): ?>
                                <p style="opacity: 0.5; text-align: center;">Nenhum certificado emitido para esta palestra.</p>
                            <?php else: ?>
                                <div class="user-list">
                                    <?php foreach ($certs_after as $cert): ?>
                                        <div class="user-item">
                                            <strong><?php echo htmlspecialchars($cert['user_name'] ?: $cert['user_name_db']); ?></strong>
                                            <small>
                                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($cert['user_email'] ?: $cert['user_email_db']); ?>
                                            </small>
                                            <small>
                                                <i class="fas fa-clock"></i> Emitido em: <?php echo date('d/m/Y H:i', strtotime($cert['issued_at'])); ?>
                                            </small>
                                            <?php if ($cert['certificate_code']): ?>
                                                <small>
                                                    <i class="fas fa-barcode"></i> Código: <?php echo htmlspecialchars($cert['certificate_code']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Usuários Pendentes -->
                        <div>
                            <h5 style="margin-bottom: 1rem; color: #FFC107;">
                                <i class="fas fa-hourglass-half"></i> Usuários Sem Certificado
                            </h5>
                            <?php if (empty($users_without)): ?>
                                <p style="opacity: 0.5; text-align: center; color: #4CAF50;">
                                    <i class="fas fa-check"></i> Todos os usuários que assistiram já receberam certificado!
                                </p>
                            <?php else: ?>
                                <div class="user-list">
                                    <?php foreach ($users_without as $user): ?>
                                        <div class="user-item">
                                            <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                            <small>
                                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                                            </small>
                                            <small>
                                                <i class="fas fa-eye"></i> Última visualização: <?php echo date('d/m/Y H:i', strtotime($user['last_view'])); ?>
                                            </small>
                                            <small>
                                                <i class="fas fa-clock"></i> Tempo assistido: <?php echo round($user['total_watch_time'] / 60, 1); ?> min
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Botão Voltar -->
        <div style="text-align: center; margin-top: 2rem;">
            <a href="map_lectures_interface_vision.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Voltar para Mapeamento
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>