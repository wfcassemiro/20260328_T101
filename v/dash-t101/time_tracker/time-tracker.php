<?php
/**
 * Time Tracker - V5.5 (Correção de Dependências do Header)
 */
session_start(); 

date_default_timezone_set('America/Sao_Paulo');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ==== INCLUSÃO DAS DEPENDÊNCIAS (CRÍTICO PARA O HEADER FUNCIONAR) ====
// Ajustando os caminhos para subir 2 níveis (de /time_tracker/ para a raiz)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
// Tenta incluir as funções do dashboard se existirem, pois o header.php costuma usar isLoggedIn()
if (file_exists(__DIR__ . '/../../config/dash_functions.php')) {
    require_once __DIR__ . '/../../config/dash_functions.php';
}
// ====================================================================

// Verificar se o usuário está logado
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header('Location: /login.php');
    exit;
}

$page_title = 'Time Tracker & Pomodoro';

// Includes visuais (Subindo 2 níveis)
@include __DIR__ . '/../../vision/includes/head.php';
@include __DIR__ . '/../../vision/includes/header.php';
@include __DIR__ . '/../../vision/includes/sidebar.php';
?>

<style>
    .main-content { padding-bottom: 50px; }
    
    /* Header */
    .profile-header-card { display: flex; align-items: center; justify-content: center; padding: 20px; margin-bottom: 20px; background: linear-gradient(135deg, var(--brand-purple), #4a148c); border-radius: 20px; gap: 20px; }
    .header-icon { background: rgba(255,255,255,0.1); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; color: #fff; }
    
    /* Navegação (Voltar e Relatórios) */
    .nav-back-container { margin-bottom: 25px; display: flex; justify-content: flex-start; gap: 10px; }
    .btn-back-dash {
        padding: 10px 20px; background: rgba(255,255,255,0.1); color: #fff; border-radius: 20px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; border: 1px solid rgba(255,255,255,0.1); transition: 0.2s;
    }
    .btn-back-dash:hover { background: rgba(255,255,255,0.2); transform: translateY(-2px); }
    .btn-report-link {
        background: rgba(124, 77, 255, 0.2); border-color: rgba(124, 77, 255, 0.4);
    }
    .btn-report-link:hover { background: rgba(124, 77, 255, 0.4); }

    /* Tabs de Modo */
    .mode-switcher { display: flex; justify-content: center; gap: 15px; margin-bottom: 20px; }
    .mode-btn { padding: 10px 25px; border-radius: 30px; background: rgba(255,255,255,0.05); color: #aaa; cursor: pointer; border: 1px solid transparent; font-weight: 600; transition: 0.3s; }
    .mode-btn.active { background: var(--brand-purple); color: #fff; box-shadow: 0 4px 15px rgba(124, 77, 255, 0.4); }
    
    /* Timer Card */
    .timer-section { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 40px 20px; text-align: center; backdrop-filter: blur(10px); transition: border-color 0.3s; }
    .timer-section.pomodoro-mode { border-color: #ff6b6b; background: rgba(255, 107, 107, 0.05); }
    .timer-section.break-mode { border-color: #34c759; background: rgba(52, 199, 89, 0.05); }

    .time-digits { font-size: 80px; font-weight: 200; letter-spacing: -2px; margin-bottom: 10px; color: #fff; font-variant-numeric: tabular-nums; line-height: 1; }
    .timer-info { height: 24px; margin-bottom: 30px; color: #aaa; font-size: 16px; }
    
    /* Controls */
    .timer-controls { max-width: 700px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
    .timer-input-group { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; justify-content: center; }
    .vision-input { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 12px 15px; color: #fff; flex: 1; outline: none; min-width: 200px; }
    .vision-select { background: #1a1a2e; color: #fff; }
    
    /* Pomodoro Settings */
    .pomo-settings { display: none; gap: 15px; justify-content: center; margin-bottom: 30px; background: rgba(0,0,0,0.2); padding: 20px; border-radius: 16px; flex-wrap: wrap; }
    .pomo-settings.active { display: flex; }
    .pomo-input-group { display: flex; flex-direction: column; gap: 8px; align-items: center; min-width: 80px; }
    .pomo-input-group label { font-size: 0.85rem; color: #ccc; font-weight: 500; text-align: center; }
    .pomo-input { width: 80px; padding: 10px; text-align: center; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05); color: #fff; font-size: 1.1rem; font-weight: bold; outline: none; }
    .pomo-input:focus { border-color: var(--brand-purple); background: rgba(255,255,255,0.1); }

    .pomo-cycle-wrapper { display: flex; align-items: center; justify-content: center; gap: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 0 10px; height: 43px; }
    .btn-reset-cycle { background: none; border: none; color: #aaa; cursor: pointer; font-size: 0.9rem; padding: 5px; transition: 0.2s; }
    .btn-reset-cycle:hover { color: #fff; transform: rotate(180deg); }

    .pomo-status { display: none; margin-bottom: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
    .pomo-status.work { color: #ff6b6b; display: block; }
    .pomo-status.break { color: #34c759; display: block; }

    /* Buttons */
    .btn-main { padding: 15px 40px; border-radius: 30px; font-weight: 800; font-size: 1.1rem; cursor: pointer; border: 0; color: #fff; display: inline-flex; align-items: center; gap: 10px; transition: 0.2s; }
    .btn-start { background: linear-gradient(135deg, #7c4dff, #b388ff); }
    .btn-stop { background: rgba(220, 53, 69, 0.2); border: 1px solid rgba(220, 53, 69, 0.5); color: #ff6b6b; }
    .btn-icon { width: 46px; height: 46px; background: rgba(255,255,255,0.1); border-radius: 12px; border: 0; color: #fff; cursor: pointer; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
    .btn-icon:hover { background: var(--brand-purple); }
    .btn-alarm { background: #ff3b30; color: white; box-shadow: 0 0 15px rgba(255, 59, 48, 0.6); animation: pulse 1s infinite; }
    @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } }

    /* Histórico */
    .entries-list { display: flex; flex-direction: column; gap: 10px; margin-top: 20px; }
    .entry-item { background: rgba(255,255,255,0.03); padding: 15px; border-radius: 12px; display: flex; align-items: center; gap: 15px; }
    .entry-tag { font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; font-weight: bold; margin-right: 5px; }
    .tag-pomodoro { background: rgba(255, 107, 107, 0.2); color: #ff6b6b; }
    .tag-manual { background: rgba(124, 77, 255, 0.2); color: #b388ff; }
    .project-option-time-only { color: #ffca28; }
    
    /* Modal */
    .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; display: none; align-items: center; justify-content: center; backdrop-filter: blur(5px); }
    .modal-content { background: #1e1e2d; padding: 30px; border-radius: 20px; width: 90%; max-width: 400px; border: 1px solid rgba(255,255,255,0.1); }
</style>

<div class="main-content">

    <div class="profile-header-card">
        <div class="header-icon"><i class="fas fa-clock"></i></div>
        <div>
            <h2 style="margin:0; color:#fff;">Time Tracker</h2>
            <p style="margin:0; color:#ccc;">Controle manual ou Pomodoro</p>
        </div>
    </div>

    <div class="nav-back-container">
        <a href="../index.php" class="btn-back-dash"><i class="fas fa-arrow-left"></i> Voltar ao Dash-T101</a>
        <a href="report_time_tracker.php" class="btn-back-dash btn-report-link"><i class="fas fa-chart-bar"></i> Relatórios</a>
    </div>

    <div class="mode-switcher">
        <div class="mode-btn active" id="modeManual" onclick="switchMode('manual')"><i class="fas fa-stopwatch"></i> Cronômetro</div>
        <div class="mode-btn" id="modePomodoro" onclick="switchMode('pomodoro')"><i class="fas fa-tomato"></i> Pomodoro</div>
    </div>

    <div class="timer-section" id="timerCard">
        <div class="pomo-status" id="pomoStatus">Foco total</div>
        <div class="time-digits">00:00:00</div>
        <div class="timer-info" id="timerInfo"></div>

        <div class="pomo-settings" id="pomoSettings">
            <div class="pomo-input-group">
                <label>Foco (min)</label>
                <input type="number" id="pomoWork" class="pomo-input" value="25" onchange="savePomoSettings()">
            </div>
            <div class="pomo-input-group">
                <label>Pausa curta</label>
                <input type="number" id="pomoShort" class="pomo-input" value="5" onchange="savePomoSettings()">
            </div>
            <div class="pomo-input-group">
                <label>Pausa longa</label>
                <input type="number" id="pomoLong" class="pomo-input" value="20" onchange="savePomoSettings()">
            </div>
            <div class="pomo-input-group">
                <label>Ciclo</label>
                <div class="pomo-cycle-wrapper">
                    <span style="color:#fff; font-weight:bold; font-size: 1.2rem; padding: 0 10px;" id="pomoCycleDisplay">1</span>
                    <button class="btn-reset-cycle" onclick="resetPomoCycle()" title="Resetar ciclos"><i class="fas fa-sync-alt"></i></button>
                </div>
            </div>
        </div>
        
        <div class="timer-controls">
            <div class="timer-input-group">
                <input type="text" id="timerDescription" class="vision-input" placeholder="O que você vai fazer?">
                <select id="timerProject" class="vision-input" style="max-width:200px; color:#fff;">
                    <option value="">Projeto (Opcional)</option>
                </select>
                <button type="button" class="btn-icon" onclick="openQuickProjectModal()" title="Novo controle de tempo">
                    <i class="fas fa-plus"></i>
                </button>
            </div>

            <div style="display:flex; justify-content:center; gap:15px;">
                <button id="startButton" class="btn-main btn-start" onclick="startAction()">
                    <i class="fas fa-play"></i> <span id="btnStartLabel">INICIAR</span>
                </button>
                <button id="stopButton" class="btn-main btn-stop" onclick="stopAction()" style="display:none;">
                    <i class="fas fa-stop"></i> PARAR
                </button>
                <button id="stopAlarmButton" class="btn-main btn-alarm" onclick="stopAlarm()" style="display:none;">
                    <i class="fas fa-bell-slash"></i> PARAR ALARME
                </button>
            </div>
        </div>
    </div>

    <div style="margin-top:40px;">
        <h3 style="color:#fff;">Histórico recente</h3>
        <div id="entriesList" class="entries-list">Carregando...</div>
    </div>

    <div class="nav-back-container">
        <a href="../index.php" class="btn-back-dash"><i class="fas fa-arrow-left"></i> Voltar ao Dash-T101</a>
        <a href="report_time_tracker.php" class="btn-back-dash btn-report-link"><i class="fas fa-chart-bar"></i> Relatórios</a>
    </div>

</div>

<div id="quickProjectModal" class="modal">
    <div class="modal-content">
        <h3 style="color:#fff; margin-top:0;"><i class="fas fa-plus-circle"></i> Novo controle de tempo</h3>
        <div style="margin-bottom: 20px;">
            <label style="color:#aaa; font-size:0.9rem; display:block; margin-bottom:5px;">Nome do projeto</label>
            <input type="text" id="quickProjectName" class="vision-input" style="width:100%;">
        </div>
        <div style="text-align:right;">
            <button onclick="closeQuickProjectModal()" class="btn-main btn-stop" style="padding:10px 20px; font-size:0.9rem;">Cancelar</button>
            <button onclick="createQuickProject()" class="btn-main btn-start" style="padding:10px 20px; font-size:0.9rem;">Criar</button>
        </div>
    </div>
</div>

<?php @include __DIR__ . '/../../vision/includes/footer.php'; ?>

<script>
let currentPath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
if (currentPath === '/') currentPath = '';
const API_URL = window.location.origin + currentPath + '/api_time_tracker.php';

const audioCtx = new (window.AudioContext || window.webkitAudioContext)();

function playSoftAlarm() {
    if (audioCtx.state === 'suspended') audioCtx.resume();
    const playBeep = () => {
        const osc = audioCtx.createOscillator();
        const gn = audioCtx.createGain();
        osc.connect(gn);
        gn.connect(audioCtx.destination);
        osc.type = 'sine';
        osc.frequency.setValueAtTime(440, audioCtx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(880, audioCtx.currentTime + 0.5);
        gn.gain.setValueAtTime(0.1, audioCtx.currentTime);
        gn.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 1.5);
        osc.start();
        osc.stop(audioCtx.currentTime + 1.5);
    };
    playBeep();
    state.alarmInterval = setInterval(playBeep, 2000);
}

let titleInterval = null;
let originalTitle = document.title;

function flashTitle() {
    if (titleInterval) return;
    let isAlert = false;
    titleInterval = setInterval(() => {
        document.title = isAlert ? "🔔 ACABOU!" : originalTitle;
        isAlert = !isAlert;
    }, 1000);
}

function stopFlashTitle() {
    clearInterval(titleInterval);
    titleInterval = null;
    document.title = originalTitle;
}

const state = {
    mode: 'manual', runningEntry: null, pomoPhase: 'work', pomoCycle: 1, pomoTimeLeft: 25 * 60, pomoInterval: null, pomoIsRunning: false, alarmInterval: null, manualStartTime: null, manualServerDuration: 0, manualInterval: null, projects: []
};

document.addEventListener('DOMContentLoaded', () => {
    loadProjects();
    loadEntries();
    if(localStorage.getItem('t101_pomo_work')) document.getElementById('pomoWork').value = localStorage.getItem('t101_pomo_work');
    if(localStorage.getItem('t101_pomo_short')) document.getElementById('pomoShort').value = localStorage.getItem('t101_pomo_short');
    if(localStorage.getItem('t101_pomo_long')) document.getElementById('pomoLong').value = localStorage.getItem('t101_pomo_long');
    checkServerRunning();
});

window.openQuickProjectModal = function() {
    const modal = document.getElementById('quickProjectModal');
    modal.style.display = 'flex';
    document.getElementById('quickProjectName').value = '';
    document.getElementById('quickProjectName').focus();
};

window.closeQuickProjectModal = function() {
    document.getElementById('quickProjectModal').style.display = 'none';
};

function createQuickProject(force = false) {
    const name = document.getElementById('quickProjectName').value.trim();
    if(!name) return;
    const fd = new FormData(); fd.append('action','project_create_quick'); fd.append('name',name);
    if (force) fd.append('force', 'true');

    fetch(API_URL,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if(d.success) {
            closeQuickProjectModal();
            loadProjects(); 
            setTimeout(() => { const select = document.getElementById('timerProject'); if(select) select.value = d.project_id; }, 500);
        } else if (d.code === 'DUPLICATE_NAME') {
            if (confirm('Já existe um projeto com este nome. Deseja criar um duplicado mesmo assim?')) { createQuickProject(true); }
        } else { alert(d.error || 'Erro ao criar projeto'); }
    });
}

function switchMode(mode) {
    if (state.runningEntry || state.pomoIsRunning) return alert('Pare o timer atual antes de trocar de modo.');
    state.mode = mode;
    document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('mode' + (mode.charAt(0).toUpperCase() + mode.slice(1))).classList.add('active');
    
    const card = document.getElementById('timerCard');
    const settings = document.getElementById('pomoSettings');
    const status = document.getElementById('pomoStatus');
    
    if (mode === 'pomodoro') {
        card.classList.add('pomodoro-mode');
        settings.classList.add('active');
        status.style.display = 'block';
        resetPomodoroDisplay();
    } else {
        card.className = 'timer-section';
        settings.classList.remove('active');
        status.style.display = 'none';
        document.querySelector('.time-digits').textContent = '00:00:00';
    }
}

function savePomoSettings() {
    localStorage.setItem('t101_pomo_work', document.getElementById('pomoWork').value);
    localStorage.setItem('t101_pomo_short', document.getElementById('pomoShort').value);
    localStorage.setItem('t101_pomo_long', document.getElementById('pomoLong').value);
    if (state.mode === 'pomodoro' && !state.pomoIsRunning) resetPomodoroDisplay();
}

function resetPomoCycle() {
    if(confirm('Resetar contador de ciclos para 1?')) {
        state.pomoCycle = 1;
        resetPomodoroDisplay();
    }
}

function startAction() {
    const desc = document.getElementById('timerDescription').value.trim();
    if (!desc && state.mode === 'pomodoro' && state.pomoPhase === 'work') {
        alert('Digite o nome da tarefa.'); document.getElementById('timerDescription').focus(); return;
    }
    if (state.mode === 'manual') startManualTimer(); else startPomodoroTimer();
}

function stopAction() {
    if (state.mode === 'manual') stopManualTimer(); else stopPomodoroTimer();
}

function resetPomodoroDisplay() {
    let mins = 25;
    if (state.pomoPhase === 'work') mins = parseInt(document.getElementById('pomoWork').value);
    else if (state.pomoPhase === 'short_break') mins = parseInt(document.getElementById('pomoShort').value);
    else mins = parseInt(document.getElementById('pomoLong').value);
    state.pomoTimeLeft = mins * 60;
    updatePomoVisuals();
}

function updatePomoVisuals() {
    const m = Math.floor(state.pomoTimeLeft / 60);
    const s = state.pomoTimeLeft % 60;
    document.querySelector('.time-digits').textContent = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
    const status = document.getElementById('pomoStatus');
    const card = document.getElementById('timerCard');
    document.getElementById('pomoCycleDisplay').textContent = state.pomoCycle;
    if (state.pomoPhase === 'work') { status.textContent = "FOCO TOTAL"; status.className = 'pomo-status work'; card.className = 'timer-section pomodoro-mode'; } 
    else { status.textContent = "PAUSA / DESCANSO"; status.className = 'pomo-status break'; card.className = 'timer-section break-mode'; }
}

function startPomodoroTimer() {
    const desc = document.getElementById('timerDescription').value;
    const pid = document.getElementById('timerProject').value;
    document.getElementById('startButton').style.display = 'none';
    document.getElementById('stopButton').style.display = 'inline-flex';
    state.pomoIsRunning = true;
    if (state.pomoPhase === 'work') {
        const fd = new FormData(); fd.append('action', 'entry_start'); fd.append('description', desc + ' (Pomodoro)'); fd.append('project_id', pid); fd.append('entry_type', 'pomodoro_work');
        fetch(API_URL, {method:'POST', body:fd}).then(r=>r.json()).then(d=>{ if(d.success) state.runningEntry = d.entry; });
    }
    state.pomoInterval = setInterval(() => {
        state.pomoTimeLeft--;
        updatePomoVisuals();
        document.title = `(${Math.floor(state.pomoTimeLeft/60)}m) Pomodoro`;
        if (state.pomoTimeLeft <= 0) triggerAlarm();
    }, 1000);
}

function triggerAlarm() {
    clearInterval(state.pomoInterval);
    state.pomoIsRunning = false;
    if (state.runningEntry) {
        const fd = new FormData(); fd.append('action', 'entry_stop'); fd.append('id', state.runningEntry.id);
        fetch(API_URL, {method:'POST', body:fd}).then(() => { state.runningEntry = null; loadEntries(); });
    }
    document.getElementById('stopButton').style.display = 'none';
    document.getElementById('stopAlarmButton').style.display = 'inline-flex';
    playSoftAlarm();
    flashTitle(); 
}

function stopAlarm() {
    if (state.alarmInterval) clearInterval(state.alarmInterval);
    if (audioCtx.state === 'running') audioCtx.suspend();
    stopFlashTitle(); 
    document.getElementById('stopAlarmButton').style.display = 'none';
    document.getElementById('startButton').style.display = 'inline-flex';
    document.title = 'Time Tracker';
    if (state.pomoPhase === 'work') { if (state.pomoCycle % 4 === 0) state.pomoPhase = 'long_break'; else state.pomoPhase = 'short_break'; } 
    else { state.pomoPhase = 'work'; state.pomoCycle++; }
    resetPomodoroDisplay();
}

function stopPomodoroTimer() {
    clearInterval(state.pomoInterval);
    state.pomoIsRunning = false;
    document.getElementById('startButton').style.display = 'inline-flex';
    document.getElementById('stopButton').style.display = 'none';
    if (state.runningEntry) {
        const fd = new FormData(); fd.append('action', 'entry_stop'); fd.append('id', state.runningEntry.id);
        fetch(API_URL, {method:'POST', body:fd}).then(() => { state.runningEntry = null; loadEntries(); });
    }
    resetPomodoroDisplay();
}

function startManualTimer() {
    const desc = document.getElementById('timerDescription').value;
    const pid = document.getElementById('timerProject').value;
    const fd = new FormData(); fd.append('action', 'entry_start'); fd.append('description', desc); fd.append('project_id', pid); fd.append('entry_type', 'manual');
    fetch(API_URL, {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
        if(d.success) { state.runningEntry = d.entry; if (!state.runningEntry) checkServerRunning(); else { state.localStartTime = Date.now(); state.manualServerDuration = 0; updateManualUI(); state.manualInterval = setInterval(manualTick, 1000); } }
    });
}
function stopManualTimer() {
    if (!state.runningEntry) return;
    const fd = new FormData(); fd.append('action', 'entry_stop'); fd.append('id', state.runningEntry.id);
    fetch(API_URL, {method:'POST', body:fd}).then(r=>r.json()).then(d=>{ if(d.success) { clearInterval(state.manualInterval); state.runningEntry = null; updateManualUI(); loadEntries(); } });
}
function manualTick() {
    if(!state.runningEntry) return;
    const diff = Math.floor((Date.now() - state.localStartTime)/1000);
    const total = state.manualServerDuration + diff;
    document.querySelector('.time-digits').textContent = formatSecs(total);
    document.title = `${formatSecs(total)} - Time Tracker`;
}
function updateManualUI() {
    const start = document.getElementById('startButton');
    const stop = document.getElementById('stopButton');
    if (state.runningEntry) { start.style.display = 'none'; stop.style.display = 'inline-flex'; document.getElementById('timerDescription').value = state.runningEntry.description; if(state.runningEntry.project_id) document.getElementById('timerProject').value = state.runningEntry.project_id; } 
    else { start.style.display = 'inline-flex'; stop.style.display = 'none'; document.querySelector('.time-digits').textContent = '00:00:00'; }
}
function checkServerRunning() {
    fetch(API_URL + '?action=entry_running').then(r=>r.json()).then(d=>{
        if(d.success && d.entry) {
            state.runningEntry = d.entry;
            if (d.entry.entry_type && d.entry.entry_type.includes('pomodoro')) { switchMode('pomodoro'); } 
            else { switchMode('manual'); state.manualServerDuration = parseInt(d.entry.duration) || 0; state.localStartTime = Date.now(); if(state.manualInterval) clearInterval(state.manualInterval); state.manualInterval = setInterval(manualTick, 1000); updateManualUI(); }
        }
    });
}
function loadProjects() {
    fetch(API_URL + '?action=project_list').then(r=>r.json()).then(d=>{
        if(d.success) {
            const s = document.getElementById('timerProject');
            s.innerHTML = '<option value="">Selecione projeto (Opcional)</option>' + d.projects.map(p => `<option value="${p.id}" class="${p.is_time_tracker_only==1?'project-option-time-only':''}">${p.is_time_tracker_only==1?'⏱ ':''}${p.name}</option>`).join('');
        }
    });
}
function loadEntries() {
    fetch(API_URL + '?action=entry_list').then(r=>r.json()).then(d=>{
        if(d.success) {
            const c = document.getElementById('entriesList');
            if(d.entries.length===0) { c.innerHTML = '<div style="text-align:center;color:#888">Sem registros</div>'; return;}
            c.innerHTML = d.entries.map(e => `<div class="entry-item"><div style="flex:1"><span class="entry-tag ${e.entry_type.includes('pomodoro')?'tag-pomodoro':'tag-manual'}">${e.type_label||'Manual'}</span><strong>${e.description||'Sem descrição'}</strong><div style="font-size:0.85rem;color:#aaa">${e.project_name||'-'}</div></div><div style="font-family:monospace;font-size:1.1rem;font-weight:bold;">${e.duration_formatted}</div><button onclick="deleteEntry('${e.id}')" style="background:none;border:0;color:#ff3b30;cursor:pointer;"><i class="fas fa-trash"></i></button></div>`).join('');
        }
    });
}
function deleteEntry(id) { if(confirm('Apagar?')) { const fd = new FormData(); fd.append('action','entry_delete'); fd.append('id', id); fetch(API_URL,{method:'POST',body:fd}).then(()=>loadEntries()); } }
function formatSecs(s){ const h=Math.floor(s/3600),m=Math.floor((s%3600)/60),sec=s%60; return String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(sec).padStart(2,'0'); }
</script>