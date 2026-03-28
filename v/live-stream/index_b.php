<?php
session_start();
date_default_timezone_set('America/Sao_Paulo');

if (isset($_GET['popup_chat']) && $_GET['popup_chat'] == '1') {
   echo "<script>window.location.href='chat-popup-standalone.php';</script>"; exit; 
}

require_once __DIR__ . '/../../config/database.php';

if (!function_exists('isLoggedIn')) { function isLoggedIn() { return isset($_SESSION['user_id']); } }
if (!function_exists('isAdmin')) { 
    function isAdmin() { return (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') || (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'); } 
}

if (!isLoggedIn()) { header("Location: /planos.php"); exit; }

$live_embed_code = ''; $chat_embed_code = ''; $panda_chat_url = ''; $live_status = '0';
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('live_embed_code', 'chat_embed_code', 'live_status', 'panda_chat_url')");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $live_embed_code = $settings['live_embed_code'] ?? '';
    $chat_embed_code = $settings['chat_embed_code'] ?? '';
    $panda_chat_url  = $settings['panda_chat_url'] ?? ''; 
    $live_status     = $settings['live_status'] ?? '0';
} catch (PDOException $e) {}

$is_live_active = (trim($live_status) === '1');
$use_external_chat = !empty(trim($chat_embed_code));
$current_user_is_admin = isAdmin();
$current_user_name = addslashes($_SESSION['user_name'] ?? $_SESSION['nome'] ?? 'Você');

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    
    <div class="glass-hero">
        <div style="display: flex; align-items: center;">
            <div class="header-icon-container" style="background: rgba(255, 255, 255, 0.1); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i class="fas fa-broadcast-tower" style="font-size: 24px; color: #fff;"></i>
            </div>
            <div class="header-text-container" style="margin-left: 20px;">
                <h2 style="margin: 0; color: #fff; font-weight: 600;">Live Stream Translators101</h2>
                <div style="display: flex; align-items: center; gap: 15px; margin-top:5px;">
                    <?php if ($is_live_active): ?>
                        <span class="live-badge pulse" style="background: rgba(46, 204, 113, 0.2); border: 1px solid #2ecc71; color: #2ecc71; padding: 2px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: bold; text-transform: uppercase;">
                            <i class="fas fa-circle" style="font-size: 6px;"></i> Ao Vivo
                        </span>
                    <?php else: ?>
                        <span class="live-badge" style="background: rgba(149, 165, 166, 0.2); border: 1px solid #95a5a6; color: #95a5a6; padding: 2px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: bold;">
                            Offline
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="live-container" id="liveGridContainer">
        
        <div class="player-section">
            <div class="video-card player-card" style="position: relative;">
                <div id="broadcast-overlay" class="broadcast-overlay" style="display: none;">
                    <div class="broadcast-content"></div>
                </div>

                <?php if ($is_live_active): ?>
                <div class="live-player" style="width:100%; height:100%;">
                    <div class="player-container">
                        <?php echo $live_embed_code; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="offline-player">
                    <div class="offline-content">
                        <i class="fas fa-video-slash"></i>
                        <h3>A transmissão acabou</h3>
                        <p>Confira a videoteca.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="chat-section">
            <div class="video-card chat-card" id="chatCardContainer">
                
                <?php if ($use_external_chat): ?>
                    <div class="chat-embed-wrapper"><?php echo $chat_embed_code; ?></div>
                <?php else: ?>
                    
                    <div class="chat-header">
                        <div style="flex:1; display:flex; align-items:center; gap: 10px;">
                            <?php if ($current_user_is_admin): ?>
                                <button class="tab-btn active" onclick="switchChatTab('local')" id="tabBtn-local">Chat T101</button>
                                <button class="tab-btn" onclick="switchChatTab('panda')" id="tabBtn-panda" style="opacity:0.6;">Panda</button>
                            <?php else: ?>
                                <h3 style="margin:0; font-size:1rem;"><i class="fas fa-comments"></i> Chat T101</h3>
                            <?php endif; ?>
                        </div>
                        <div class="chat-controls">
                            <button class="control-btn" onclick="openPopupChat()" title="Destacar"><i class="fas fa-external-link-alt"></i></button>
                            <button class="control-btn" onclick="toggleChat()" title="Minimizar"><i class="fas fa-minus"></i></button>
                            <?php if ($current_user_is_admin): ?>
                                <button class="control-btn" onclick="clearChat()" title="Limpar"><i class="fas fa-broom"></i></button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div id="chatContent-local" class="chat-content-active">
                        <div class="chat-messages" id="chatMessagesContainer">
                            <div class="system-message"><i class="fas fa-info-circle"></i> Bem-vindo ao chat!</div>
                        </div>
                        
                        <div id="mainEmojiPicker" class="emoji-picker">
                            <?php $emojis = ['😀','😂','😍','🥰','😎','🤔','😭','😡','👍','👎','👏','🔥','🎉','❤️','✅']; foreach($emojis as $em) { echo "<div class='emoji-item' onclick=\"insertMainEmoji('$em')\">$em</div>"; } ?>
                        </div>

                        <?php if ($is_live_active): ?>
                            <div style="padding: 0 15px; background: rgba(0,0,0,0.3); display: flex; justify-content: flex-end;">
                                <label class="scroll-toggle" style="padding: 5px 0;">
                                    <input type="checkbox" id="autoScrollCheckMain" checked> <span>Auto-scroll</span>
                                </label>
                            </div>
                            <div class="chat-input-container">
                                <form id="chatForm">
                                    <div class="chat-input-group">
                                        <button type="button" class="emoji-btn" onclick="toggleMainEmojiPicker()"><i class="far fa-smile"></i></button>
                                        <input type="text" id="chatInput" placeholder="Digite..." autocomplete="off" required>
                                        <button type="submit" class="send-btn"><i class="fas fa-paper-plane"></i></button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="chat-input-container">
                                <div class="chat-offline-message"><i class="fas fa-clock"></i> Chat disponível na live.</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($current_user_is_admin): ?>
                    <div id="chatContent-panda" class="chat-content-hidden">
                        <div style="flex:1; background:#000; position:relative;">
                            <?php if (!empty($panda_chat_url)): ?>
                                <iframe src="<?php echo htmlspecialchars($panda_chat_url); ?>" style="border:none; width:100%; height:100%;"></iframe>
                            <?php else: ?>
                                <div style="display:flex; align-items:center; justify-content:center; height:100%; color:#777; padding:20px;">Link Panda ausente.</div>
                            <?php endif; ?>
                        </div>
                        <div class="chat-input-container" style="background:#2c1a35; border-top:1px solid #8e44ad;">
                            <div style="display:flex; gap:5px; margin-bottom:5px;">
                                <input type="text" id="manualOverlayUser" placeholder="Nome" style="flex:1; padding:5px; background:#111; border:1px solid #444; color:#fff; border-radius:4px;">
                                <button type="button" class="control-btn" onclick="sendManualOverlay()" style="background:#8e44ad; color:#fff; border:none;">Exibir</button>
                            </div>
                            <textarea id="manualOverlayMsg" placeholder="Mensagem..." style="width:100%; height:40px; background:#111; border:1px solid #444; color:#fff; border-radius:4px; padding:5px; resize:none;"></textarea>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($current_user_is_admin && !$use_external_chat): ?>
    <div class="video-card overlay-preview" style="margin-bottom:30px; margin-top:20px;">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; padding:15px; border-bottom:1px solid rgba(255,255,255,0.1);">
            <h2 style="margin:0; font-size:1.1rem; color:#fff;"><i class="fas fa-tv"></i> Overlay</h2>
            <button type="button" class="cta-btn" onclick="clearRemoteOverlay()" style="padding:6px 12px; font-size:0.8rem; background:#e74c3c; border:none; color:#fff; border-radius:4px; cursor:pointer;">Limpar Tela</button>
        </div>
        <div id="overlayControlPanel" class="overlay-content" style="padding:15px; text-align:center; color:#777;">
            <p>Nenhuma mensagem.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
/* CSS OTIMIZADO */
.live-container { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 40px; align-items: stretch; }
@media (max-width: 900px) { .live-container { grid-template-columns: 1fr; } }

.video-card, .chat-card { display: flex; flex-direction: column; background: rgba(0,0,0,0.2); border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.15); overflow: hidden; height: 100%; }
.player-container { position: relative; padding-bottom: 56.25%; height: 0; background: #000; width: 100%; }
.player-container iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none; }
.offline-player { flex: 1; display: flex; align-items: center; justify-content: center; background: #222; min-height: 400px; text-align: center; color: #777; }

.chat-header { flex-shrink: 0; padding: 10px 15px; border-bottom: 1px solid rgba(255,255,255,0.15); background: rgba(142, 68, 173, 0.1); display: flex; justify-content: space-between; align-items: center; }
#chatContent-local, #chatContent-panda { display: flex; flex-direction: column; flex: 1; height: 100%; overflow: hidden; }
.chat-content-active { display: flex !important; }
.chat-content-hidden { display: none !important; }

.chat-messages { flex-grow: 1; padding: 15px; overflow-y: auto; background: rgba(0,0,0,0.1); height: 0; }
.chat-input-container { padding: 10px 15px; background: rgba(0,0,0,0.3); flex-shrink: 0; }
.chat-input-group { display: flex; gap: 10px; }
.chat-input-group input { flex: 1; padding: 10px; border-radius: 20px; border: 1px solid #444; background: #222; color: #fff; }

.send-btn { background: #8e44ad; border: none; color: white; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display:flex; align-items:center; justify-content:center; }
.control-btn { background: transparent; border: none; color: #aaa; cursor: pointer; padding: 5px; font-size: 1rem; transition: 0.2s; }
.control-btn:hover { color: #fff; transform: scale(1.1); }
.emoji-btn { background: transparent; border: none; color: #ccc; cursor: pointer; font-size: 1.2rem; padding: 0 10px; }
.tab-btn { background: transparent; border: none; color: #aaa; padding: 5px 10px; cursor: pointer; font-weight: 600; font-size: 0.9rem; transition:0.3s; }
.tab-btn.active { color: #fff; background: rgba(142,68,173,0.3); border-radius: 15px; }
.scroll-toggle { font-size: 0.8rem; color: #ccc; cursor: pointer; display: flex; align-items: center; gap: 5px; }

/* REAÇÕES OTIMIZADAS PARA CLS */
/* min-height evita que a mensagem pule quando a reação é adicionada */
.reaction-bar { display: flex; gap: 5px; margin-top: 5px; flex-wrap: wrap; min-height: 24px; } 
.reaction-pill { background: rgba(255,255,255,0.1); border-radius: 10px; padding: 2px 8px; font-size: 0.75rem; cursor: pointer; user-select: none; transition: 0.2s; }
.reaction-pill:hover { background: rgba(255,255,255,0.3); }

/* Pickers */
.emoji-picker { position: absolute; bottom: 80px; left: 15px; background: #222; border: 1px solid #555; border-radius: 8px; padding: 10px; display: none; grid-template-columns: repeat(5, 1fr); gap: 5px; width: 200px; box-shadow: 0 5px 15px rgba(0,0,0,0.5); z-index: 200; }
.emoji-item { cursor: pointer; padding: 5px; text-align: center; font-size: 1.2rem; border-radius: 4px; }
.emoji-item:hover { background: rgba(255,255,255,0.1); }
.mini-emoji-picker { position: absolute; background: #333; border: 1px solid #555; padding: 5px; border-radius: 5px; display: none; gap: 5px; z-index: 50; margin-top: -30px; box-shadow: 0 2px 5px rgba(0,0,0,0.5); }
.mini-emoji-picker span { cursor: pointer; font-size: 1.2rem; padding: 0 4px; transition: transform 0.1s; }
.mini-emoji-picker span:hover { transform: scale(1.3); }

/* OVERLAY */
.broadcast-overlay { position: absolute; bottom: 30px; left: 40px; width: auto; max-width: 80%; pointer-events: none; z-index: 100; animation: slideUp 0.5s ease; }
.broadcast-content { background: rgba(0, 0, 0, 0.8); border-left: 5px solid #8e44ad; color: #fff; padding: 15px 25px; border-radius: 4px; }
.overlay-user-name { color: #f39c12; font-size: 1rem; font-weight: 700; text-transform: uppercase; }
.overlay-message-text { color: #fff; font-size: 1.3rem; margin-top: 5px; }
@keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

/* SCROLLBAR */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: #111; }
::-webkit-scrollbar-thumb { background: #444; border-radius: 3px; }
</style>

<script>
    const CURRENT_USER_NAME = '<?php echo $current_user_name; ?>';
    const IS_ADMIN = <?php echo $current_user_is_admin ? 'true' : 'false'; ?>;
    let lastMessageId = 0;
    let popupWindow = null;
    let activePickerId = null;
    
    // CACHE DE ESTADO DAS REAÇÕES (CRÍTICO PARA PERFORMANCE)
    // Isso evita re-renderizar o DOM se nada mudou, salvando o INP.
    const reactionsState = {}; 

    // --- LAYOUT ---
    function openPopupChat() { popupWindow = window.open('?popup_chat=1', 'ChatT101', 'width=400,height=600'); }
    function toggleChat() { const c = document.getElementById('chatCardContainer'); c.style.display = (c.style.display==='none') ? 'flex' : 'none'; }

    // --- ABAS ---
    function switchChatTab(tab) {
        if(!IS_ADMIN) return;
        document.querySelectorAll('.tab-btn').forEach(b => { b.classList.remove('active'); b.style.opacity='0.6'; });
        const btn = document.getElementById('tabBtn-'+tab);
        btn.classList.add('active'); btn.style.opacity='1';
        document.getElementById('chatContent-local').className = (tab==='local') ? 'chat-content-active' : 'chat-content-hidden';
        document.getElementById('chatContent-panda').className = (tab==='panda') ? 'chat-content-active' : 'chat-content-hidden';
    }

    // --- POLLING ---
    function pollUpdates() {
        // Usa timestamp para evitar cache
        fetch(`live-stream-api.php?action=poll&last_id=${lastMessageId}&t=${Date.now()}`)
        .then(r => r.json())
        .then(data => {
            // 1. Mensagens Novas
            if(data.messages) {
                data.messages.forEach(msg => {
                    if(parseInt(msg.id) > lastMessageId) { 
                        appendMessage(msg); lastMessageId = parseInt(msg.id); 
                    }
                });
                triggerAutoScroll();
            }
            
            // 2. Reações (STATE DIFFING)
            if(data.reactions_update) {
                for (const [msgId, reactions] of Object.entries(data.reactions_update)) {
                    // Só chama a função de atualizar o DOM se os dados mudaram
                    // Convertemos o objeto para string para comparação rápida
                    const currentStateStr = JSON.stringify(reactions);
                    if (reactionsState[msgId] !== currentStateStr) {
                        updateReactionsDOM(msgId, reactions);
                        reactionsState[msgId] = currentStateStr; // Atualiza o cache
                    }
                }
            }
            
            updateOverlay(data.overlay);
            if(data.presence && IS_ADMIN) updatePresence(data.presence);
        })
        .catch(e => console.error(e));
    }
    
    // --- MENSAGENS ---
    function appendMessage(msg) {
        const box = document.getElementById('chatMessagesContainer');
        if(document.getElementById(`msg-${msg.id}`)) return;
        
        const div = document.createElement('div');
        div.className = 'chat-message';
        div.id = `msg-${msg.id}`;
        div.style.marginBottom = '10px';
        div.style.padding = '10px';
        div.style.background = 'rgba(255,255,255,0.05)';
        div.style.borderRadius = '8px';
        
        const userColor = msg.is_admin ? '#e74c3c' : '#8e44ad';
        const badge = msg.is_admin ? ' <i class="fas fa-crown" title="Admin"></i>' : '';
        
        let html = `
            <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                <strong style="color:${userColor}">${msg.user_name}${badge}</strong>
                <small style="color:#666; font-size:0.75rem;">${new Date(msg.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</small>
            </div>
            <div style="color:#ddd; word-wrap:break-word;">${msg.message}</div>
            <div class="reaction-bar"></div>
        `;
        
        if(IS_ADMIN) {
            const u = String(msg.user_name).replace(/'/g, "\\'").replace(/"/g, '&quot;');
            const m = String(msg.message).replace(/'/g, "\\'").replace(/"/g, '&quot;').replace(/\n/g, ' ');
            html += `<div style="margin-top:5px;"><button onclick="sendToOverlayAPI('${u}', '${m}')" style="background:none; border:none; color:#3498db; font-size:0.75rem; cursor:pointer; padding:0; display:flex; align-items:center; gap:5px;"><i class="fas fa-tv"></i> Exibir</button></div>`;
        }
        
        div.innerHTML = html;
        box.appendChild(div);
        
        // Inicializa o cache vazio para essa mensagem
        reactionsState[msg.id] = "";
        updateReactionsDOM(msg.id, msg.reactions);
    }

    // --- REAÇÕES (DOM Update) ---
    function updateReactionsDOM(msgId, reactions) {
        const el = document.getElementById(`msg-${msgId}`);
        if(!el) return;
        
        // Preserva o picker se estiver aberto
        const isPickerOpen = (activePickerId === parseInt(msgId));
        const pickerStyle = isPickerOpen ? 'display:flex' : 'display:none';
        
        let html = '';
        if(reactions && Object.keys(reactions).length > 0) {
            for(const [em, count] of Object.entries(reactions)) {
                html += `<span class="reaction-pill" id="r-${msgId}-${em}" onclick="sendReaction(${msgId}, '${em}')">${em} ${count}</span>`;
            }
        }
        html += `<span onclick="toggleMiniPicker(${msgId})" style="cursor:pointer; font-size:0.8rem; color:#777; margin-left:5px; padding:2px;"><i class="far fa-plus-square"></i></span>
                 <div class="mini-emoji-picker" id="picker-${msgId}" style="${pickerStyle}">
                    <span onclick="sendReaction(${msgId}, '👍')">👍</span>
                    <span onclick="sendReaction(${msgId}, '👏')">👏</span>
                    <span onclick="sendReaction(${msgId}, '❤️')">❤️</span>
                    <span onclick="sendReaction(${msgId}, '😂')">😂</span>
                 </div>`;
                 
        const bar = el.querySelector('.reaction-bar');
        // Double check para evitar repaint desnecessário
        if(bar.innerHTML !== html) bar.innerHTML = html;
    }

    function sendReaction(id, emoji) {
        // OPTIMISTIC UI: Atualiza visualmente ANTES do servidor responder
        // Isso dá a sensação de 0ms de latência
        const pill = document.getElementById(`r-${id}-${emoji}`);
        if(pill) {
            const parts = pill.innerText.split(' ');
            if(parts.length > 1) {
                const newCount = parseInt(parts[1]) + 1; // Incrementa visualmente
                pill.innerText = `${emoji} ${newCount}`;
            }
        }

        // Atualiza o cache local para evitar que o próximo poll (que pode ter dados antigos) reverta a mudança visual
        // Isso é um truque para evitar "flicker"
        let currentCache = reactionsState[id] ? JSON.parse(reactionsState[id]) : {};
        if(!currentCache[emoji]) currentCache[emoji] = 0;
        currentCache[emoji]++; 
        reactionsState[id] = JSON.stringify(currentCache);

        const fd = new FormData(); fd.append('action', 'react'); fd.append('message_id', id); fd.append('emoji', emoji);
        fetch('live-stream-api.php', {method:'POST', body:fd}).then(() => {
            activePickerId = null;
            document.querySelectorAll('.mini-emoji-picker').forEach(p => p.style.display='none');
        });
    }
    
    function toggleMiniPicker(id) {
        document.querySelectorAll('.mini-emoji-picker').forEach(p => p.style.display='none');
        if(activePickerId === id) { activePickerId = null; return; }
        const p = document.getElementById(`picker-${id}`);
        if(p) { p.style.display = 'flex'; activePickerId = id; }
    }

    // --- OVERLAY ---
    function sendToOverlayAPI(user, text) {
        const fd = new FormData(); fd.append('action', 'set_overlay'); fd.append('data', JSON.stringify({user:user, text:text}));
        fetch('live-stream-api.php', {method:'POST', body:fd});
    }
    function updateOverlay(data) {
        const ov = document.getElementById('broadcast-overlay');
        const pl = document.getElementById('overlayControlPanel');
        if(!data || !data.text) {
            ov.style.display = 'none'; 
            if(pl) pl.innerHTML='<p style="text-align:center; color:#777">Nenhuma mensagem.</p>';
            return;
        }
        const h = `<div class="overlay-user-name">${data.user}</div><div class="overlay-message-text">${data.text}</div>`;
        ov.querySelector('.broadcast-content').innerHTML = h; ov.style.display = 'block';
        if(pl) pl.innerHTML = `<div style="background:#222; padding:10px; border-left:3px solid #f39c12;">${h}</div>`;
    }
    function clearRemoteOverlay() {
        const fd = new FormData(); fd.append('action', 'set_overlay'); fd.append('data', '');
        fetch('live-stream-api.php', {method:'POST', body:fd});
    }
    function sendManualOverlay() {
        const u = document.getElementById('manualOverlayUser').value; const m = document.getElementById('manualOverlayMsg').value;
        if(u && m) sendToOverlayAPI(u, m);
    }

    // --- UTILS ---
    function triggerAutoScroll() {
        const chk = document.getElementById('autoScrollCheckMain');
        const box = document.getElementById('chatMessagesContainer');
        if(chk && chk.checked && box) box.scrollTop = box.scrollHeight;
    }
    function clearChat() {
        if(!confirm("Apagar tudo?")) return;
        const fd = new FormData(); fd.append('action', 'clear_all_messages');
        fetch('live-stream-api.php', {method:'POST', body:fd}).then(() => {
            document.getElementById('chatMessagesContainer').innerHTML = ''; lastMessageId=0;
            // Limpa cache de estado também
            for (var member in reactionsState) delete reactionsState[member];
        });
    }
    function toggleMainEmojiPicker() { const p = document.getElementById('mainEmojiPicker'); p.style.display = (p.style.display==='grid')?'none':'grid'; }
    function insertMainEmoji(e) { document.getElementById('chatInput').value += e; document.getElementById('chatInput').focus(); toggleMainEmojiPicker(); }
    
    // Form Submit
    const f = document.getElementById('chatForm');
    if(f) f.addEventListener('submit', e => {
        e.preventDefault();
        const i = document.getElementById('chatInput');
        if(!i.value.trim()) return;
        const fd = new FormData(); fd.append('action', 'send_message'); fd.append('message', i.value.trim());
        fetch('live-stream-api.php', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{ if(d.success){ i.value=''; pollUpdates(); } });
    });

    setInterval(pollUpdates, 3000);
    pollUpdates();
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>