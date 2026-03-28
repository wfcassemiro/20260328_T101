<?php
session_start();
require_once 'config/database.php';

// Função auxiliar para escrever no arquivo de log customizado.
function writeToCustomLog($message) {
    $log_file = __DIR__ . '/certificate_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] [PALESTRA] $message\n", FILE_APPEND);
}

writeToCustomLog("DEBUG: Script palestra.php iniciado.");

// Variáveis para mensagens de feedback ao usuário
$message = '';
$message_type = ''; // 'success', 'info', 'error'

// INICIALIZAÇÃO DA VARIÁVEL PARA EVITAR O AVISO
$certificate_generated = false;
$generated_certificate_id = null;

// CORREÇÃO: Nova função de verificação de acesso
function hasPalestraAccess() {
    return isset($_SESSION['user_role']) && ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'subscriber');
}

// Verificar acesso
if (!hasPalestraAccess()) {
    writeToCustomLog("INFO: Usuário não tem permissão para acessar a palestra. Redirecionando para /planos.php.");
    header('Location: /planos.php?redirect=palestra');
    exit;
}
writeToCustomLog("DEBUG: Usuário tem permissão.");

$lecture_id = $_GET['id'] ?? '';

if (empty($lecture_id)) {
    writeToCustomLog("ERRO: ID da palestra vazio. Redirecionando para /videoteca.php.");
    header('Location: /videoteca.php');
    exit;
}
writeToCustomLog("DEBUG: ID da palestra recebido: " . $lecture_id);

try {
    // Buscar palestra
    $stmt = $pdo->prepare("SELECT * FROM lectures WHERE id = ?");
    $stmt->execute([$lecture_id]);
    $lecture = $stmt->fetch();
    
    if (!$lecture) {
        writeToCustomLog("ERRO: Palestra com ID " . $lecture_id . " não encontrada no banco de dados. Redirecionando para /videoteca.php.");
        header('Location: /videoteca.php');
        exit;
    }
    writeToCustomLog("DEBUG: Palestra '" . $lecture['title'] . "' encontrada. Duração em minutos: " . ($lecture['duration_minutes'] ?? 'NULL')); 
    
    // Log de acesso à palestra
    $user_id_from_session = $_SESSION['user_id'] ?? null;
    $user_id_for_log = null; 

    if ($user_id_from_session) {
        try {
            $stmt_check_user = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmt_check_user->execute([$user_id_from_session]);
            if ($stmt_check_user->fetch()) {
                $user_id_for_log = $user_id_from_session; 
                writeToCustomLog("DEBUG: User ID da sessão ('" . $user_id_from_session . "') é válido e existe na tabela 'users'.");
            } else {
                writeToCustomLog("ALERTA CRÍTICO: User ID da sessão ('" . $user_id_from_session . "') NÃO existe na tabela 'users'. Isso pode causar problemas de rastreamento de progresso.");
                $user_id_for_log = null; 
            }
        } catch (PDOException $e) {
            writeToCustomLog("ERRO: PDOException ao verificar User ID da sessão: " . $e->getMessage());
            $user_id_for_log = null;
        }
    } else {
        writeToCustomLog("DEBUG: User ID não encontrado na sessão. Inserindo NULL para user_id_log.");
        $user_id_for_log = null;
    }

    $ip_address_log = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
    $user_agent_log = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';

    // Para evitar inserir um novo log toda vez, vamos atualizar se já existir
    $stmt_check_log = $pdo->prepare("SELECT id, last_watched_seconds FROM access_logs WHERE user_id = ? AND resource = ? AND action = 'view_lecture'");
    $stmt_check_log->execute([$user_id_for_log, $lecture['title']]);
    $existing_access_log = $stmt_check_log->fetch();

    if ($existing_access_log) {
        // Atualizar o log existente com o tempo de visualização
        $stmt_update_log = $pdo->prepare("UPDATE access_logs SET updated_at = NOW(), ip_address = ?, user_agent = ? WHERE id = ?");
        $stmt_update_log->execute([$ip_address_log, $user_agent_log, $existing_access_log['id']]);
        writeToCustomLog("INFO: Log de acesso atualizado para palestra '" . $lecture['title'] . "'.");
    } else {
        // Inserir novo log apenas se user_id_for_log NÃO for NULL
        if ($user_id_for_log !== null) {
            $stmt = $pdo->prepare("INSERT INTO access_logs (user_id, action, resource, ip_address, user_agent) VALUES (?, 'view_lecture', ?, ?, ?)");
            $stmt->execute([$user_id_for_log, $lecture['title'], $ip_address_log, $user_agent_log]);
            writeToCustomLog("INFO: Novo log de acesso registrado para palestra '" . $lecture['title'] . "' (User ID: " . ($user_id_for_log) . ").");
        } else {
            writeToCustomLog("ALERTA: Não foi possível registrar log de acesso para palestra '" . $lecture['title'] . "'. User ID inválido/nulo na sessão. O progresso NÃO será salvo.");
        }
    }

    // OBTÉM O PROGESSO ATUAL DO USUÁRIO NA PALESTRA
    $user_progress_seconds = $existing_access_log['last_watched_seconds'] ?? 0;
    writeToCustomLog("DEBUG: Progresso inicial carregado do DB (user_progress_seconds): " . $user_progress_seconds);
    
    // Buscar palestras relacionadas (mesma categoria)
    $stmt = $pdo->prepare("SELECT * FROM lectures WHERE category = ? AND id != ? ORDER BY created_at DESC LIMIT 4");
    $stmt->execute([$lecture['category'], $lecture_id]);
    $related_lectures = $stmt->fetchAll();
    writeToCustomLog("DEBUG: Palestras relacionadas buscadas para categoria '" . $lecture['category'] . "'.");
    
} catch(PDOException $e) {
    writeToCustomLog("ERRO: PDOException ao buscar palestra ou registrar log: " . $e->getMessage());
    header('Location: /videoteca.php');
    exit;
} catch(Exception $e) {
    writeToCustomLog("ERRO: Exceção inesperada ao buscar palestra ou registrar log: " . $e->getMessage());
    header('Location: /videoteca.php');
    exit;
}

$page_title = $lecture['title'];
$page_description = substr($lecture['description'], 0, 160) . '...';

// Verificar se já existe certificado para este usuário e palestra (para exibição inicial da página)
$existing_certificate = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM certificates WHERE user_id = ? AND lecture_id = ?");
    $stmt->execute([$_SESSION['user_id'], $lecture_id]);
    $existing_certificate = $stmt->fetch();
    writeToCustomLog("DEBUG: Verificação de certificado existente para exibição inicial. ID existente: " . ($existing_certificate['id'] ?? 'Nenhum'));
} catch(PDOException $e) {
    writeToCustomLog("ERRO: PDOException ao verificar certificado existente para exibição inicial: " . $e->getMessage());
    $existing_certificate = null;
}

// INÍCIO DO HTML E JAVASCRIPT PARA MONITORAR VÍDEO
include 'includes/header.php';
?>

<div class="min-h-screen px-4 py-8">
    <div class="max-w-6xl mx-auto">
        <nav class="mb-6">
            <ol class="flex space-x-2 text-sm text-gray-400">
                <li><a href="/" class="hover:text-purple-400">Home</a></li>
                <li><i class="fas fa-chevron-right"></i></li>
                <li><a href="/videoteca.php" class="hover:text-purple-400">Videoteca</a></li>
                <li><i class="fas fa-chevron-right"></i></li>
                <li class="text-white"><?php echo htmlspecialchars($lecture['title']); ?></li>
            </ol>
        </nav>

        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg <?php 
                if ($message_type === 'success') echo 'bg-green-600 bg-opacity-20 border border-green-600 text-green-400';
                elseif ($message_type === 'info') echo 'bg-blue-600 bg-opacity-20 border border-blue-600 text-blue-400';
                elseif ($message_type === 'error') echo 'bg-red-600 bg-opacity-20 border border-red-600 text-red-400';
            ?>" id="serverMessageDiv">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <script>
                // Esconde a mensagem do PHP após 10 segundos
                document.addEventListener('DOMContentLoaded', () => {
                    const serverMessageDiv = document.getElementById('serverMessageDiv');
                    if (serverMessageDiv) {
                        setTimeout(() => {
                            serverMessageDiv.style.display = 'none';
                        }, 10000);
                    }
                });
            </script>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <div class="video-container mb-6">
                    <?php 
                    // Extrai o ID do div do Panda Video para o JavaScript
                    $panda_player_div_id = '';
                    if (preg_match('/<div[^>]*id="([^"]*panda-[^"]*)"[^>]*>/i', $lecture['embed_code'], $matches)) {
                        $panda_player_div_id = $matches[1];
                    }
                     writeToCustomLog("DEBUG: Panda Player Div ID extraído do embed: '" . $panda_player_div_id . "'");
                    echo $lecture['embed_code']; 
                    ?>
                </div>
                
                <div>
                    <h1 class="text-3xl font-bold mb-4"><?php echo htmlspecialchars($lecture['title']); ?></h1>
                    
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="flex items-center text-purple-400">
                            <i class="fas fa-user mr-2"></i>
                            <span class="font-semibold"><?php echo htmlspecialchars($lecture['speaker']); ?></span>
                        </div>
                        <div class="flex items-center text-gray-400">
                            <i class="fas fa-clock mr-2"></i>
                            <span><?php echo $lecture['duration_minutes']; ?> minutos</span>
                        </div>
                        <div class="flex items-center text-gray-400">
                            <i class="fas fa-tag mr-2"></i>
                            <span><?php echo htmlspecialchars($lecture['category']); ?></span>
                        </div>
                    </div>
                    
                    <div class="prose prose-invert max-w-none">
                        <p class="text-lg text-gray-300 leading-relaxed">
                            <?php echo nl2br(htmlspecialchars($lecture['description'])); ?>
                        </p>
                    </div>
                    
                    <?php 
                    $tags = json_decode($lecture['tags'] ?? '[]', true);
                    if (!empty($tags)): 
                    ?>
                        <div class="mt-6">
                            <h4 class="font-semibold mb-3">Tags:</h4>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach($tags as $tag): ?>
                                    <span class="bg-gray-800 text-purple-400 px-3 py-1 rounded-full text-sm">
                                        #<?php echo htmlspecialchars($tag); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="lg:col-span-1">
                <div class="bg-gray-900 rounded-lg p-6 mb-6">
                    <h3 class="text-xl font-bold mb-4">Detalhes da Palestra</h3>
                    <div class="space-y-3">
                        <div>
                            <strong class="text-gray-400">Palestrante:</strong> 
                            <span class="text-white"><?php echo htmlspecialchars($lecture['speaker']); ?></span>
                        </div>
                        <div>
                            <strong class="text-gray-400">Duração:</strong> 
                            <span class="text-white"><?php echo $lecture['duration_minutes']; ?> min</span>
                        </div>
                        <div>
                            <strong class="text-gray-400">Categoria:</strong> 
                            <span class="text-white"><?php echo htmlspecialchars($lecture['category']); ?></span>
                        </div>
                        <div>
                            <strong class="text-gray-400">Publicado em:</strong> 
                            <span class="text-white"><?php echo date('d/m/Y', strtotime($lecture['created_at'])); ?></span>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-900 rounded-lg p-6 mb-6">
                    <h3 class="text-xl font-bold mb-4">Certificado de Conclusão</h3>
                    
                    <div class="space-y-3">
                        <?php if ($existing_certificate): ?>
                             <p class="text-green-400 text-sm mb-3">
                                <i class="fas fa-check-circle mr-2"></i>Você já concluiu esta palestra!
                            </p>
                            <a href="/download_certificate.php?id=<?php echo $existing_certificate['id']; ?>" 
                               target="_blank"
                               class="block w-full text-center bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded transition-colors">
                                Baixar meu certificado (PDF)
                            </a>
                            <p class="text-gray-400 text-xs text-center mt-2">
                                Emitido em <?php echo date('d/m/Y', strtotime($existing_certificate['issued_at'])); ?>
                            </p>
                        <?php else: ?>
                            <p id="certificateMessage" class="text-yellow-400 text-sm mb-3">
                                Assista à palestra para habilitar a geração do certificado.
                            </p>
                            <form id="certificateForm">
                                <button type="button" name="generate_certificate" id="generateCertificateBtn"
                                        class="block w-full text-center bg-gray-700 text-white font-bold py-2 px-4 rounded transition-colors opacity-50 cursor-not-allowed"
                                        disabled>
                                    Gerar Certificado
                                </button>
                            </form>
                            <p class="text-gray-400 text-xs text-center mt-2">
                                <i class="fas fa-shield-alt mr-1"></i>Requer 85% de visualização sequencial (anti-fraude ativo)
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$existing_certificate): ?>
                <div class="bg-gray-900 rounded-lg p-6 mb-6">
                    <h3 class="text-xl font-bold mb-4">Progresso Seguro de Visualização</h3>
                    
                    <div class="space-y-3">
                        <div class="flex justify-between text-sm text-gray-400">
                            <span>Tempo Sequencial</span>
                            <span id="progressText">0% (0s / 0s)</span>
                        </div>
                        
                        <div class="w-full bg-gray-800 rounded-full h-3">
                            <div id="progressBar" class="bg-gradient-to-r from-purple-600 to-purple-400 h-3 rounded-full transition-all duration-500" style="width: 0%"></div>
                        </div>
                        
                        <div class="flex justify-between text-xs text-gray-500">
                            <span id="securityStatus">🛡️ Sistema anti-fraude ativo</span>
                            <span id="statusText">Carregando player...</span>
                        </div>
                        
                        <!-- Indicadores de segurança -->
                        <div class="mt-3 p-2 bg-gray-800 rounded text-xs">
                            <div class="flex justify-between items-center">
                                <span>Segmentos assistidos:</span>
                                <span id="segmentsProgress">0/0</span>
                            </div>
                            <div class="flex justify-between items-center mt-1">
                                <span>Progressão natural:</span>
                                <span id="naturalProgress" class="text-green-400">✓ OK</span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($related_lectures)): ?>
                    <div class="bg-gray-900 rounded-lg p-6">
                        <h3 class="text-xl font-bold mb-4">Palestras Relacionadas</h3>
                        <div class="space-y-4">
                            <?php foreach($related_lectures as $related): ?>
                                <div class="flex space-x-3 cursor-pointer hover:bg-gray-800 rounded-lg p-2 transition-colors"
                                     onclick="window.location.href='/palestra.php?id=<?php echo $related['id']; ?>'">
                                    <div class="w-20 h-14 bg-gradient-to-br from-purple-600 to-purple-800 rounded flex-shrink-0 flex items-center justify-center">
                                        <?php if ($related['thumbnail_url']): ?>
                                            <img 
                                                src="<?php echo htmlspecialchars($related['thumbnail_url']); ?>" 
                                                alt="<?php echo htmlspecialchars($related['title']); ?>"
                                                class="w-full h-full object-cover rounded"
                                            />
                                        <?php else: ?>
                                            <i class="fas fa-play text-white"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h4 class="font-medium text-sm line-clamp-2 mb-1">
                                            <?php echo htmlspecialchars($related['title']); ?>
                                        </h4>
                                        <p class="text-purple-400 text-xs mb-1">
                                            <?php echo htmlspecialchars($related['speaker']); ?>
                                        </p>
                                        <p class="text-gray-400 text-xs">
                                            <?php echo $related['duration_minutes']; ?> min
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-4">
                            <a href="/videoteca.php?category=<?php echo urlencode($lecture['category']); ?>" 
                               class="text-purple-400 hover:text-purple-300 text-sm">
                                Ver mais palestras de <?php echo htmlspecialchars($lecture['category']); ?>
                                <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script async src="https://player.pandavideo.com.br/api.v2.js"></script>
<script>
    // ===== SISTEMA SIMPLES BASEADO EM TEMPO DE SESSÃO =====
    const LECTURE_ID = "<?php echo $lecture_id; ?>";
    const LECTURE_DURATION_SECONDS = <?php echo ($lecture['duration_minutes'] ?? 0) * 60; ?>;
    const USER_ID = "<?php echo $_SESSION['user_id'] ?? ''; ?>";
    const REQUIRED_WATCH_SECONDS = Math.floor(LECTURE_DURATION_SECONDS * 0.85); // 85% DA DURAÇÃO TOTAL
    const PANDA_PLAYER_DIV_ID = "<?php echo $panda_player_div_id; ?>";
    
    // MODO DEBUG PARA TESTE
    const DEBUG_MODE = true;
    
    // ===== VARIÁVEIS SIMPLES DE CONTROLE =====
    let sessionTracker = {
        isPlaying: false,
        sessionStartTime: 0,
        totalWatchedSeconds: <?php echo $user_progress_seconds; ?>, // Do DB
        lastSaveTime: 0,
        playerReady: false
    };
    
    console.log("🎬 SISTEMA DE CERTIFICADOS: 85% da duração necessário");
    console.log(`📊 Progresso atual: ${sessionTracker.totalWatchedSeconds}s de ${REQUIRED_WATCH_SECONDS}s necessários (${((sessionTracker.totalWatchedSeconds / REQUIRED_WATCH_SECONDS) * 100).toFixed(1)}%)`);
    console.log(`🎯 Duração total da palestra: ${LECTURE_DURATION_SECONDS}s (${Math.floor(LECTURE_DURATION_SECONDS/60)} min)`);
    
    function debugLog(message) {
        if (DEBUG_MODE) {
            console.log(`🔍 [TRACKER] ${message}`);
        }
    }
    
    // ===== SISTEMA SIMPLES DE TRACKING =====
    let pandaPlayerInstance;
    let isIframeEmbed = false;
    
    window.pandascripttag = window.pandascripttag || [];
    window.pandascripttag.push(function () {
        debugLog("Panda API carregada - inicializando sistema simples");
        
        detectIframeEmbed();
        
        if (PANDA_PLAYER_DIV_ID && !isIframeEmbed) {
            try {
                pandaPlayerInstance = new PandaPlayer(PANDA_PLAYER_DIV_ID, {
                    onReady: onPlayerReady,
                    onEvent: onPlayerEvent,
                    onError: onPlayerError
                });
                debugLog("PandaPlayer direto inicializado");
            } catch (error) {
                debugLog("Erro no player direto, usando iframe: " + error.message);
                isIframeEmbed = true;
                initializeSimpleIframeHandling();
            }
        } else {
            isIframeEmbed = true;
            initializeSimpleIframeHandling();
        }
    });
    
    function detectIframeEmbed() {
        const iframes = document.querySelectorAll('iframe[src*="pandavideo"], iframe[src*="panda"]');
        if (iframes.length > 0 || (PANDA_PLAYER_DIV_ID && document.getElementById(PANDA_PLAYER_DIV_ID)?.querySelector('iframe'))) {
            isIframeEmbed = true;
            debugLog("Iframe embed detectado");
        }
    }
    
    function initializeSimpleIframeHandling() {
        debugLog("Inicializando sistema simples para iframe");
        updateStatusText("🎬 Player carregado - Aguardando play automático...");
        
        // Listener mais abrangente para eventos do Panda
        window.addEventListener("message", function(event) {
            const data = event.data;
            if (data && typeof data === 'object' && data.message && data.message.startsWith('panda_')) {
                debugLog(`📨 Evento capturado: ${data.message}`);
                handleSimplePandaEvent(data);
            }
        }, false);
        
        // DETECÇÃO MAIS AGRESSIVA: Verificar mudanças no iframe
        let lastIframeCheck = 0;
        const iframeMonitor = setInterval(() => {
            const iframe = document.querySelector('iframe[src*="pandavideo"], iframe[src*="panda"]');
            if (iframe && iframe.contentWindow) {
                try {
                    // Tentar detectar se vídeo está tocando indiretamente
                    const currentCheck = Date.now();
                    if (currentCheck - lastIframeCheck > 2000) { // A cada 2 segundos
                        lastIframeCheck = currentCheck;
                        
                        // Se ainda não iniciou tracking e player parece pronto
                        if (!sessionTracker.isPlaying && sessionTracker.playerReady) {
                            debugLog("🔍 Verificando se vídeo pode estar tocando...");
                            // Não fazer nada ainda, só monitorar
                        }
                    }
                } catch (e) {
                    // Ignorar erros de cross-origin
                }
            }
        }, 2000);
        
        // Criar controles manuais mais cedo (2 segundos) para garantir que funcionem
        // MAS APENAS PARA ADMIN
        setTimeout(() => {
            const isAdmin = <?php echo (isAdmin()) ? 'true' : 'false'; ?>;
            
            if (isAdmin) {
                createSimpleManualControls();
                debugLog("Controles manuais criados (usuário admin)");
            } else {
                debugLog("Controles manuais ocultados (usuário normal)");
            }
            
            // Se depois de 8 segundos não iniciou automaticamente, mostrar aviso
            setTimeout(() => {
                if (!sessionTracker.isPlaying) {
                    if (isAdmin) {
                        updateStatusText("⚠️ Auto-start falhou - Use controles manuais abaixo");
                    } else {
                        updateStatusText("⚠️ Clique em PLAY no vídeo para começar a contagem");
                    }
                }
            }, 8000);
        }, 2000);
    }
    
    // MANIPULADOR SUPER SIMPLES - SÓ LIGA/DESLIGA O CRONÔMETRO
    function handleSimplePandaEvent(data) {
        debugLog(`Evento recebido: ${data.message}`);
        
        if (data.message === 'panda_ready') {
            sessionTracker.playerReady = true;
            updateStatusText("🎬 Player pronto - Aguardando play...");
            debugLog("Player pronto - aguardando play");
        }
        else if (data.message === 'panda_play') {
            debugLog("🎯 EVENTO PLAY DETECTADO - Iniciando tracking automático");
            updateStatusText("🎯 Play detectado - Iniciando contagem automática");
            startSimpleTracking();
        }
        else if (data.message === 'panda_pause') {
            debugLog("🎯 EVENTO PAUSE DETECTADO");
            pauseSimpleTracking();
        }
        else if (data.message === 'panda_ended') {
            debugLog("🎯 EVENTO ENDED DETECTADO");
            pauseSimpleTracking();
        }
        else if (data.message === 'panda_timeupdate' || data.message === 'panda_allData') {
            // Para timeupdate, verificar se vídeo está tocando nos dados
            if (data.playerData && !data.playerData.paused && !sessionTracker.isPlaying) {
                debugLog("🎯 TIMEUPDATE indicou que vídeo está tocando - Iniciando tracking");
                startSimpleTracking();
            } else if (data.playerData && data.playerData.paused && sessionTracker.isPlaying) {
                debugLog("🎯 TIMEUPDATE indicou que vídeo pausou");
                pauseSimpleTracking();
            }
        }
        
        // Sempre atualizar interface
        updateSimpleInterface();
    }
    
    // CRONÔMETRO SIMPLES - SÓ CONTA TEMPO REAL
    function startSimpleTracking() {
        if (sessionTracker.isPlaying) return; // Já está contando
        
        sessionTracker.isPlaying = true;
        sessionTracker.sessionStartTime = Date.now();
        
        debugLog("▶️ Começou a contar tempo");
        updateStatusText("▶️ Contando tempo - " + formatTime(sessionTracker.totalWatchedSeconds));
        
        // Atualizar cronômetro a cada segundo PARA SALVAMENTO
        if (window.simpleTrackingInterval) clearInterval(window.simpleTrackingInterval);
        window.simpleTrackingInterval = setInterval(() => {
            if (sessionTracker.isPlaying) {
                const currentSessionTime = Math.floor((Date.now() - sessionTracker.sessionStartTime) / 1000);
                
                // ATUALIZAR INTERFACE APENAS A CADA 10 SEGUNDOS para estabilizar
                if (currentSessionTime % 10 === 0 || currentSessionTime <= 10) {
                    const currentTotal = sessionTracker.totalWatchedSeconds + currentSessionTime;
                    updateStatusText("▶️ Contando: " + formatTime(currentTotal));
                    
                    // Atualizar interface principal a cada 10s
                    const tempTotal = sessionTracker.totalWatchedSeconds;
                    sessionTracker.totalWatchedSeconds = currentTotal;
                    updateSimpleInterface(); // Atualizar barra de progresso
                    sessionTracker.totalWatchedSeconds = tempTotal; // Voltar ao valor real
                }
                
                // Salvar a cada 10 segundos
                if (currentSessionTime > 0 && currentSessionTime % 10 === 0) {
                    // Salvar o progresso real
                    sessionTracker.totalWatchedSeconds += currentSessionTime;
                    sessionTracker.sessionStartTime = Date.now(); // Reset do timer da sessão
                    saveSimpleProgress();
                    debugLog(`💾 Auto-salvamento: ${sessionTracker.totalWatchedSeconds}s`);
                }
            }
        }, 1000);
    }
    
    function pauseSimpleTracking() {
        if (!sessionTracker.isPlaying) return; // Já pausado
        
        sessionTracker.isPlaying = false;
        clearInterval(window.simpleTrackingInterval);
        
        // Adicionar tempo da sessão ao total FINAL
        const sessionTime = Math.floor((Date.now() - sessionTracker.sessionStartTime) / 1000);
        sessionTracker.totalWatchedSeconds += sessionTime;
        
        debugLog(`⏸️ Pausou - sessão: ${sessionTime}s, total final: ${sessionTracker.totalWatchedSeconds}s`);
        updateStatusText("⏸️ Pausado - Total: " + formatTime(sessionTracker.totalWatchedSeconds));
        
        // Salvar progresso final
        saveSimpleProgress();
        updateSimpleInterface(); // Atualizar interface com valor final
    }
    
    function saveSimpleProgress() {
        if (!USER_ID) return;
        
        const payload = {
            lecture_id: LECTURE_ID,
            user_id: USER_ID,
            watched_seconds: sessionTracker.totalWatchedSeconds,
            total_duration_seconds: LECTURE_DURATION_SECONDS
        };
        
        debugLog(`💾 Salvando: ${sessionTracker.totalWatchedSeconds}s`);
        
        fetch('/config/api/save_simple_progress.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                debugLog(`✅ Salvo: ${sessionTracker.totalWatchedSeconds}s`);
            } else {
                debugLog(`❌ Erro ao salvar: ${data.message}`);
            }
        })
        .catch(error => debugLog(`❌ Erro na requisição: ${error.message}`));
    }
    
    function updateSimpleInterface() {
        // Atualizar barra de progresso
        const progressText = document.getElementById('progressText');
        const progressBar = document.getElementById('progressBar');
        const certificateMessage = document.getElementById('certificateMessage');
        const generateBtn = document.getElementById('generateCertificateBtn');
        
        if (progressText && progressBar) {
            const percentage = (sessionTracker.totalWatchedSeconds / REQUIRED_WATCH_SECONDS) * 100;
            const totalPercentageOfVideo = (sessionTracker.totalWatchedSeconds / LECTURE_DURATION_SECONDS) * 100;
            const minutes = Math.floor(sessionTracker.totalWatchedSeconds / 60);
            const seconds = sessionTracker.totalWatchedSeconds % 60;
            const totalMinutes = Math.floor(LECTURE_DURATION_SECONDS / 60);
            const totalSecondsCalc = Math.floor(LECTURE_DURATION_SECONDS % 60);
            
            progressText.textContent = `${totalPercentageOfVideo.toFixed(1)}% assistido (${minutes}:${seconds.toString().padStart(2, '0')} / ${totalMinutes}:${totalSecondsCalc.toString().padStart(2, '0')}) - Certificado aos 85%`;
            progressBar.style.width = `${Math.min(percentage, 100)}%`;
            
            if (percentage >= 100) {
                progressBar.className = "bg-gradient-to-r from-green-600 to-green-400 h-3 rounded-full transition-all duration-500";
            } else {
                progressBar.className = "bg-gradient-to-r from-purple-600 to-purple-400 h-3 rounded-full transition-all duration-500";
            }
        }
        
        // Atualizar botão de certificado
        if (certificateMessage && generateBtn) {
            if (sessionTracker.totalWatchedSeconds >= REQUIRED_WATCH_SECONDS) {
                generateBtn.disabled = false;
                generateBtn.classList.remove('opacity-50', 'cursor-not-allowed', 'bg-gray-700');
                generateBtn.classList.add('bg-purple-600', 'hover:bg-purple-700');
                certificateMessage.textContent = "🎉 Certificado disponível! (85% completados)";
                certificateMessage.classList.remove('text-yellow-400');
                certificateMessage.classList.add('text-green-400');
            } else {
                const remaining = REQUIRED_WATCH_SECONDS - sessionTracker.totalWatchedSeconds;
                const remainingMinutes = Math.ceil(remaining / 60);
                generateBtn.disabled = true;
                generateBtn.classList.add('opacity-50', 'cursor-not-allowed', 'bg-gray-700');
                generateBtn.classList.remove('bg-purple-600', 'hover:bg-purple-700');
                certificateMessage.textContent = `Assista mais ${remainingMinutes} min para habilitar certificado (${remaining}s restantes)`;
                certificateMessage.classList.remove('text-green-400');
                certificateMessage.classList.add('text-yellow-400');
            }
        }
    }
    
    function formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }
    
    function updateStatusText(text) {
        const statusElement = document.getElementById('statusText');
        if (statusElement) {
            statusElement.textContent = text;
        }
    }
    
    // Criar controles manuais SUPER SIMPLES
    function createSimpleManualControls() {
        const statusElement = document.getElementById('statusText');
        if (!statusElement || document.getElementById('simpleControls')) return; // Já existe
        
        const controlsHtml = `
            <div id="simpleControls" class="mt-3 p-3 bg-blue-900 rounded-lg border border-blue-600">
                <p class="text-blue-400 text-xs mb-2">⚡ Controles Simples (se automático falhar):</p>
                <div class="space-x-2">
                    <button id="simpleStartBtn" class="bg-green-600 hover:bg-green-700 text-white text-xs px-3 py-1 rounded">
                        ▶️ Iniciar Cronômetro
                    </button>
                    <button id="simplePauseBtn" class="bg-yellow-600 hover:bg-yellow-700 text-white text-xs px-3 py-1 rounded" style="display:none;">
                        ⏸️ Pausar Cronômetro  
                    </button>
                    <button id="simpleResetBtn" class="bg-red-600 hover:bg-red-700 text-white text-xs px-2 py-1 rounded">
                        🔄 Reset
                    </button>
                    <span id="simpleTimer" class="text-xs text-blue-300 ml-2">Total: ${formatTime(sessionTracker.totalWatchedSeconds)}</span>
                </div>
                <p class="text-xs text-gray-500 mt-1">Cronômetro simples - conta tempo real de visualização</p>
            </div>
        `;
        
        statusElement.parentNode.insertAdjacentHTML('afterend', controlsHtml);
        
        // Eventos dos botões
        document.getElementById('simpleStartBtn').onclick = () => {
            debugLog("🎮 MANUAL: Iniciando cronômetro");
            
            document.getElementById('simpleStartBtn').style.display = 'none';
            document.getElementById('simplePauseBtn').style.display = 'inline-block';
            
            startSimpleTracking();
        };
        
        document.getElementById('simplePauseBtn').onclick = () => {
            debugLog("🎮 MANUAL: Pausando cronômetro");
            
            document.getElementById('simpleStartBtn').style.display = 'inline-block';
            document.getElementById('simplePauseBtn').style.display = 'none';
            
            pauseSimpleTracking();
        };
        
        document.getElementById('simpleResetBtn').onclick = () => {
            debugLog("🎮 MANUAL: Reset completo");
            
            // Parar cronômetro se estiver rodando
            if (sessionTracker.isPlaying) {
                pauseSimpleTracking();
            }
            
            // Reset dos botões
            document.getElementById('simpleStartBtn').style.display = 'inline-block';
            document.getElementById('simplePauseBtn').style.display = 'none';
            
            // Reset das variáveis
            sessionTracker.totalWatchedSeconds = <?php echo $user_progress_seconds; ?>;
            
            updateSimpleInterface();
            updateStatusText("🔄 Cronômetro resetado");
            
            document.getElementById('simpleTimer').textContent = `Total: ${formatTime(sessionTracker.totalWatchedSeconds)}`;
        };
        
        // Atualizar timer manual A CADA 10 SEGUNDOS para estabilizar
        setInterval(() => {
            const timerElement = document.getElementById('simpleTimer');
            if (timerElement) {
                if (sessionTracker.isPlaying) {
                    const currentSessionTime = Math.floor((Date.now() - sessionTracker.sessionStartTime) / 1000);
                    const displayTotal = sessionTracker.totalWatchedSeconds + currentSessionTime;
                    timerElement.textContent = `Total: ${formatTime(displayTotal)} (contando...)`;
                } else {
                    timerElement.textContent = `Total: ${formatTime(sessionTracker.totalWatchedSeconds)}`;
                }
            }
        }, 10000); // 10 segundos para estabilizar
    }
    
    // Handlers para player direto (se não for iframe)
    function onPlayerReady() {
        debugLog("Player direto pronto");
        sessionTracker.playerReady = true;
        updateStatusText("🎬 Player pronto - Aperte play");
    }
    
    function onPlayerError(error) {
        debugLog("Erro no player: " + error.message);
        updateStatusText("❌ Erro no player - use controles manuais");
    }
    
    function onPlayerEvent(event) {
        debugLog("Evento do player direto: " + event.message);
        
        if (event.message === 'panda_play') {
            startSimpleTracking();
        } else if (event.message === 'panda_pause' || event.message === 'panda_ended') {
            pauseSimpleTracking();
        }
    }
    
    // ===== INICIALIZAÇÃO =====
    document.addEventListener('DOMContentLoaded', () => {
        debugLog("Sistema simples carregado");
        updateSimpleInterface();
        
        // Configurar botão de certificado
        const generateBtn = document.getElementById('generateCertificateBtn');
        if (generateBtn) {
            generateBtn.addEventListener('click', function(event) {
                event.preventDefault();
                
                this.disabled = true;
                this.textContent = 'Gerando...';
                
                debugLog("Solicitando certificado simples");
                
                fetch('./generate_simple_certificate.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        lecture_id: LECTURE_ID,
                        user_id: USER_ID,
                        watched_seconds: sessionTracker.totalWatchedSeconds
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        debugLog("✅ Certificado gerado!");
                        alert('🎉 Certificado gerado com sucesso!');
                        window.open('/download_certificate.php?id=' + data.certificate_id, '_blank');
                        
                        this.textContent = 'Baixar certificado (PDF)';
                        this.disabled = false;
                    } else {
                        debugLog("❌ Erro: " + data.message);
                        alert('❌ Erro: ' + data.message);
                        this.disabled = false;
                        this.textContent = 'Gerar Certificado';
                    }
                })
                .catch(error => {
                    debugLog("❌ Erro na requisição: " + error.message);
                    alert('❌ Erro inesperado. Tente novamente.');
                    this.disabled = false;
                    this.textContent = 'Gerar Certificado';
                });
            });
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
