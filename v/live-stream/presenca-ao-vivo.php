<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../config/database.php';

// Verificar se é admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: /planos.php");
    exit;
}

$page_title = 'Presença ao Vivo - Translators101';

// Data selecionada (padrão: hoje)
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Buscar participantes
$sql = "SELECT id, user_id, user_name, user_email, 
              first_access, last_activity, total_minutes 
        FROM live_presence 
        WHERE live_date = ? 
        ORDER BY user_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$selectedDate]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar datas disponíveis
$datesSql = "SELECT DISTINCT live_date FROM live_presence ORDER BY live_date DESC LIMIT 30";
$datesStmt = $pdo->query($datesSql);
$availableDates = $datesStmt->fetchAll(PDO::FETCH_COLUMN);

// Buscar informações da palestra do dia selecionado
$lectureSql = "SELECT title, speaker FROM upcoming_announcements WHERE announcement_date = ? AND is_active = 1 ORDER BY lecture_time ASC LIMIT 1";
$lectureStmt = $pdo->prepare($lectureSql);
$lectureStmt->execute([$selectedDate]);
$todayLecture = $lectureStmt->fetch(PDO::FETCH_ASSOC);

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center;">
                <div class="header-icon-container" style="background: rgba(255, 255, 255, 0.1); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <i class="fas fa-clipboard-list" style="font-size: 24px; color: #fff;"></i>
                </div>
                <div style="margin-left: 20px;">
                    <h2 style="margin: 0 0 5px 0; padding: 0; font-size: 1.5rem; color: #fff; font-weight: 600; border: none;">Presença ao Vivo</h2>
                    <p style="margin: 0; color: rgba(255, 255, 255, 0.8); font-size: 1rem;">Controle de participantes da transmissão</p>
                </div>
            </div>
            <a href="../live-stream/" class="cta-btn" style="text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Voltar à Live
            </a>
        </div>
    </div>

    <!-- Informações da Palestra do Dia -->
    <?php if ($todayLecture): ?>
    <div class="video-card" style="margin-bottom: 20px; background: rgba(142, 68, 173, 0.1); border: 1px solid rgba(142, 68, 173, 0.3);">
        <div style="display: flex; align-items: center; gap: 15px;">
            <i class="fas fa-chalkboard-teacher" style="font-size: 2rem; color: #8e44ad;"></i>
            <div>
                <h3 style="margin: 0 0 5px 0; color: #fff;">
                    <i class="fas fa-calendar-day"></i> Palestra de <?php echo date('d/m/Y', strtotime($selectedDate)); ?>
                </h3>
                <p style="margin: 0; color: #f39c12; font-size: 1.1rem; font-weight: 600;">
                    <?php echo htmlspecialchars($todayLecture['title']); ?>
                </p>
                <p style="margin: 5px 0 0 0; color: #95a5a6;">
                    <i class="fas fa-user"></i> Palestrante: <strong><?php echo htmlspecialchars($todayLecture['speaker']); ?></strong>
                </p>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="video-card" style="margin-bottom: 20px; background: rgba(231, 76, 60, 0.1); border: 1px solid rgba(231, 76, 60, 0.3);">
        <div style="display: flex; align-items: center; gap: 15px;">
            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #e74c3c;"></i>
            <div>
                <p style="margin: 0; color: #e74c3c; font-weight: 600;">
                    Nenhuma palestra agendada para <?php echo date('d/m/Y', strtotime($selectedDate)); ?>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filtro de Data e Ações -->
    <div class="video-card" style="margin-bottom: 20px;">
        <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
            <label style="color: #fff; font-weight: 600;">
                <i class="fas fa-calendar"></i> Selecionar Data:
            </label>
            <select id="dateFilter" class="date-select" onchange="window.location.href='?date=' + this.value" style="padding: 10px; border-radius: 8px; border: 1px solid #444; background: #222; color: #fff; font-size: 1rem; cursor: pointer;">
                <?php foreach ($availableDates as $date): 
                    $dateObj = new DateTime($date);
                    $formatted = $dateObj->format('d/m/Y');
                    $selected = ($date === $selectedDate) ? 'selected' : '';
                ?>
                    <option value="<?php echo $date; ?>" <?php echo $selected; ?>>
                        <?php echo $formatted; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <div style="margin-left: auto; display: flex; gap: 10px; align-items: center;">
                <!-- Botão para exportar CSV -->
                <a href="exportar-presenca-csv.php?date=<?php echo $selectedDate; ?>" class="btn-export" title="Exportar lista para CSV">
                    <i class="fas fa-file-csv"></i> Exportar CSV
                </a>
                
                <span style="background: rgba(46, 204, 113, 0.2); border: 1px solid #2ecc71; color: #2ecc71; padding: 8px 16px; border-radius: 8px; font-weight: 600;">
                    <i class="fas fa-users"></i> <?php echo count($participants); ?> Participantes
                </span>
            </div>
        </div>
    </div>

    <!-- Tabela de Participantes -->
    <div class="video-card">
        <h3 style="margin-bottom: 20px; color: #fff;">
            <i class="fas fa-list"></i> Lista de Presença - <?php echo date('d/m/Y', strtotime($selectedDate)); ?>
        </h3>
        
        <?php if (empty($participants)): ?>
            <div style="text-align: center; padding: 40px; color: #777;">
                <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px; display: block;"></i>
                <p>Nenhum participante registrado nesta data.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="presence-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><i class="fas fa-user"></i> Nome</th>
                            <th><i class="fas fa-envelope"></i> Email</th>
                            <th><i class="fas fa-clock"></i> Entrada</th>
                            <th><i class="fas fa-history"></i> Última Atividade</th>
                            <th><i class="fas fa-stopwatch"></i> Tempo Online</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participants as $index => $p): 
                            $firstAccess = new DateTime($p['first_access']);
                            $lastActivity = new DateTime($p['last_activity']);
                            $minutes = $p['total_minutes'];
                            $hours = floor($minutes / 60);
                            $mins = $minutes % 60;
                            $timeDisplay = $hours > 0 ? "{$hours}h {$mins}min" : "{$mins}min";
                        ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($p['user_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($p['user_email'] ?: 'N/A'); ?></td>
                            <td><?php echo $firstAccess->format('H:i:s'); ?></td>
                            <td><?php echo $lastActivity->format('H:i:s'); ?></td>
                            <td>
                                <span class="time-badge <?php echo $minutes >= 30 ? 'success' : ($minutes >= 15 ? 'warning' : 'info'); ?>">
                                    <?php echo $timeDisplay; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Estatísticas -->
            <div class="stats-grid" style="margin-top: 30px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <?php 
                $totalMinutes = array_sum(array_column($participants, 'total_minutes'));
                $avgMinutes = count($participants) > 0 ? round($totalMinutes / count($participants)) : 0;
                $over30min = count(array_filter($participants, fn($p) => $p['total_minutes'] >= 30));
                ?>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-value"><?php echo count($participants); ?></div>
                    <div class="stat-label">Total de Participantes</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-value"><?php echo $avgMinutes; ?> min</div>
                    <div class="stat-label">Tempo Médio</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-certificate"></i></div>
                    <div class="stat-value"><?php echo $over30min; ?></div>
                    <div class="stat-label">Elegíveis (30+ min)</div>
                </div>
            </div>

            <!-- Instruções -->
            <div style="margin-top: 30px; padding: 20px; background: rgba(52, 152, 219, 0.1); border: 1px solid rgba(52, 152, 219, 0.3); border-radius: 8px;">
                <h4 style="color: #3498db; margin: 0 0 10px 0;">
                    <i class="fas fa-info-circle"></i> Como emitir certificados
                </h4>
                <ol style="color: #95a5a6; margin: 0; padding-left: 20px;">
                    <li>Clique no botão "Exportar CSV" acima</li>
                    <li>Salve o arquivo em seu computador</li>
                    <li>Acesse a página de geração de certificados</li>
                    <li>Importe o arquivo CSV na funcionalidade de importação</li>
                    <li>Os certificados serão gerados automaticamente para todos os participantes</li>
                </ol>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.table-responsive {
    overflow-x: auto;
}

.presence-table {
    width: 100%;
    border-collapse: collapse;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 8px;
    overflow: hidden;
}

.presence-table thead {
    background: rgba(142, 68, 173, 0.3);
}

.presence-table th,
.presence-table td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    color: #fff;
}

.presence-table th {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    color: #8e44ad;
}

.presence-table tbody tr:hover {
    background: rgba(142, 68, 173, 0.1);
}

.time-badge {
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 0.85rem;
    font-weight: 600;
}

.time-badge.success {
    background: rgba(46, 204, 113, 0.2);
    border: 1px solid #2ecc71;
    color: #2ecc71;
}

.time-badge.warning {
    background: rgba(241, 196, 15, 0.2);
    border: 1px solid #f1c40f;
    color: #f1c40f;
}

.time-badge.info {
    background: rgba(52, 152, 219, 0.2);
    border: 1px solid #3498db;
    color: #3498db;
}

.btn-export {
    background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
    border: none;
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-export:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(46, 204, 113, 0.4);
}

.stat-card {
    background: rgba(142, 68, 173, 0.1);
    border: 1px solid rgba(142, 68, 173, 0.3);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    border-color: rgba(142, 68, 173, 0.6);
}

.stat-icon {
    font-size: 2rem;
    color: #8e44ad;
    margin-bottom: 10px;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 0.9rem;
    color: #95a5a6;
    text-transform: uppercase;
    letter-spacing: 1px;
}

@media (max-width: 768px) {
    .presence-table {
        font-size: 0.85rem;
    }
    
    .presence-table th,
    .presence-table td {
        padding: 10px;
    }
}
</style>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>