/**
 * Time Tracker - JavaScript V3 (Final)
 * Lógica visual corrigida
 */

// LOG DE VERIFICAÇÃO - Se não aparecer no console, limpe o cache!
console.log('%c VERSÃO V3 CARREGADA - LÓGICA VISUAL CORRIGIDA ', 'background: #222; color: #bada55; font-size: 20px');

const currentPath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
const API_URL = window.API_URL || (window.location.origin + currentPath + '/api_time_tracker.php');

const state = {
    runningEntry: null,
    timerInterval: null,
    isPaused: false,
    projects: [],
    entries: [],
    // Controle de tempo local
    localStartTime: null,
    serverDurationAtStart: 0
};

document.addEventListener('DOMContentLoaded', () => {
    initializeApp();
});

function initializeApp() {
    loadProjects();
    loadEntries();
    checkRunningTimer();
    setupEventListeners();
    
    // Inicia loop visual (1 segundo)
    if (state.timerInterval) clearInterval(state.timerInterval);
    state.timerInterval = setInterval(updateTimerTick, 1000);
}

// ==== LÓGICA VISUAL (O SEGREDO) ====
function updateTimerTick() {
    if (!state.runningEntry || state.isPaused) return;

    // Cálculo: (Agora - Momento que iniciou localmente) + Duração que veio do banco
    const now = Date.now();
    const secondsPassedLocally = Math.floor((now - state.localStartTime) / 1000);
    const totalSeconds = state.serverDurationAtStart + secondsPassedLocally;

    const formatted = formatDuration(totalSeconds);
    
    // Atualiza DOM
    const display = document.querySelector('.time-digits');
    if(display) {
        display.textContent = formatted;
        display.style.opacity = '1';
    }
    document.title = `${formatted} - Time Tracker`;
}

// ==== AÇÕES ====

function startTimer() {
    const description = document.getElementById('timerDescription').value;
    const projectId = document.getElementById('timerProject').value;
    const taskId = document.getElementById('timerTask').value;

    showLoading(true);

    const formData = new FormData();
    formData.append('action', 'entry_start');
    formData.append('description', description);
    if (projectId) formData.append('project_id', projectId);
    if (taskId) formData.append('task_id', taskId);

    fetch(API_URL, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        showLoading(false);
        if (data.success) {
            console.log('[TT] Start Sucesso. Dados:', data.entry);
            // Aplica estado imediatamente sem esperar refresh
            if (data.entry) {
                applyRunningState(data.entry);
                showNotification('Iniciado!', 'success');
            } else {
                checkRunningTimer();
            }
        } else {
            showNotification(data.error, 'error');
        }
    })
    .catch(err => {
        showLoading(false);
        showNotification('Erro de conexão', 'error');
    });
}

function checkRunningTimer() {
    fetch(API_URL + '?action=entry_running')
    .then(r => r.json())
    .then(data => {
        if (data.success && data.entry) {
            applyRunningState(data.entry);
        } else {
            state.runningEntry = null;
            resetTimerUI();
        }
    });
}

function applyRunningState(entry) {
    state.runningEntry = entry;
    state.isPaused = entry.is_paused || false;
    
    // SINCRONIZAÇÃO CRÍTICA
    state.localStartTime = Date.now();
    state.serverDurationAtStart = parseInt(entry.duration) || 0;
    
    updateTimerUI();
    
    // Força atualização imediata do texto
    const display = document.querySelector('.time-digits');
    if(display) display.textContent = formatDuration(state.serverDurationAtStart);
}

function pauseTimer() {
    if (!state.runningEntry) return;
    const fd = new FormData(); fd.append('action', 'entry_pause'); fd.append('id', state.runningEntry.id);
    fetch(API_URL, { method: 'POST', body: fd }).then(r=>r.json()).then(d=>{
        if(d.success) { state.isPaused = true; updateTimerUI(); }
    });
}

function resumeTimer() {
    if (!state.runningEntry) return;
    const fd = new FormData(); fd.append('action', 'entry_resume'); fd.append('id', state.runningEntry.id);
    fetch(API_URL, { method: 'POST', body: fd }).then(r=>r.json()).then(d=>{
        if(d.success) { checkRunningTimer(); } // Recarrega para ajustar o tempo base do servidor
    });
}

function stopTimer() {
    if (!state.runningEntry) return;
    const fd = new FormData(); fd.append('action', 'entry_stop'); fd.append('id', state.runningEntry.id);
    fetch(API_URL, { method: 'POST', body: fd }).then(r=>r.json()).then(d=>{
        if(d.success) { 
            state.runningEntry = null; 
            resetTimerUI(); 
            loadEntries(); 
            document.title = 'Time Tracker';
        }
    });
}

// ==== UI & UTILS ====

function updateTimerUI() {
    if (state.runningEntry) {
        document.getElementById('startButton').style.display = 'none';
        document.getElementById('stopButton').style.display = 'inline-flex';
        
        document.getElementById('timerDescription').value = state.runningEntry.description || '';
        const projSelect = document.getElementById('timerProject');
        if (projSelect && state.runningEntry.project_id) {
            projSelect.value = state.runningEntry.project_id;
            projSelect.disabled = true;
        }

        let info = '';
        if (state.runningEntry.project_name) info += `<span>● ${escapeHtml(state.runningEntry.project_name)}</span>`;
        document.getElementById('timerInfo').innerHTML = info;

        if (state.isPaused) {
            document.getElementById('pauseButton').style.display = 'none';
            document.getElementById('resumeButton').style.display = 'inline-flex';
            document.querySelector('.time-digits').style.opacity = '0.5';
        } else {
            document.getElementById('pauseButton').style.display = 'inline-flex';
            document.getElementById('resumeButton').style.display = 'none';
            document.querySelector('.time-digits').style.opacity = '1';
        }
    } else {
        resetTimerUI();
    }
}

function resetTimerUI() {
    const display = document.querySelector('.time-digits');
    if(display) { display.textContent = '00:00:00'; display.style.opacity = '1'; }
    
    document.getElementById('timerInfo').innerHTML = '';
    document.getElementById('timerDescription').value = '';
    document.getElementById('timerProject').disabled = false;
    document.getElementById('timerTask').disabled = true;
    
    document.getElementById('startButton').style.display = 'inline-flex';
    document.getElementById('pauseButton').style.display = 'none';
    document.getElementById('resumeButton').style.display = 'none';
    document.getElementById('stopButton').style.display = 'none';
}

function loadProjects() {
    fetch(API_URL + '?action=project_list').then(r=>r.json()).then(d=>{
        if(d.success) {
            state.projects = d.projects;
            const s = document.getElementById('timerProject');
            const current = s.value;
            s.innerHTML = '<option value="">Selecione um projeto</option>' + state.projects.map(p=>`<option value="${p.id}">${escapeHtml(p.name)}</option>`).join('');
            if(current) s.value = current;
            
            const f = document.getElementById('filterProject');
            if(f) f.innerHTML = '<option value="">Todos</option>' + state.projects.map(p=>`<option value="${p.id}">${escapeHtml(p.name)}</option>`).join('');
        }
    });
}

function loadEntries() {
    const f = document.getElementById('filterProject') ? document.getElementById('filterProject').value : '';
    let url = API_URL + '?action=entry_list&limit=50';
    if(f) url += '&project_id='+f;
    fetch(url).then(r=>r.json()).then(d=>{ if(d.success) renderEntries(d.entries); });
}

function renderEntries(entries) {
    const c = document.getElementById('entriesList');
    if(!entries || entries.length===0) { c.innerHTML = '<div style="padding:20px;text-align:center;color:#888">Sem registros</div>'; return; }
    c.innerHTML = entries.map(e => `
        <div class="entry-item">
            <div class="entry-color" style="background:#7B61FF"></div>
            <div class="entry-details">
                <div class="entry-description">${escapeHtml(e.description || 'Sem descrição')}</div>
                <div class="entry-meta">${e.project_name ? `<span><i class="fas fa-folder"></i> ${escapeHtml(e.project_name)}</span>` : ''} <span><i class="fas fa-clock"></i> ${new Date(e.start_time.replace(' ','T')).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'})}</span></div>
            </div>
            <div class="entry-duration">${e.duration_formatted}</div>
            <button class="btn btn-sm btn-icon btn-danger" onclick="deleteEntry('${e.id}')" style="margin-left:10px"><i class="fas fa-trash"></i></button>
        </div>`).join('');
}

function deleteEntry(id) {
    if(!confirm('Apagar?')) return;
    const fd = new FormData(); fd.append('action', 'entry_delete'); fd.append('id', id);
    fetch(API_URL, {method:'POST', body:fd}).then(()=>loadEntries());
}

function createQuickProject(e) {
    e.preventDefault();
    const name = document.getElementById('quickProjectName').value;
    const fd = new FormData(); fd.append('action', 'project_create_quick'); fd.append('name', name);
    fetch(API_URL, {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
        if(d.success) {
            closeQuickProjectModal();
            loadProjects();
            showNotification('Criado', 'success');
            setTimeout(() => { const s = document.getElementById('timerProject'); if(s) s.value = d.project_id; }, 500);
        }
    });
}

function setupEventListeners() {
    document.getElementById('startButton')?.addEventListener('click', startTimer);
    document.getElementById('pauseButton')?.addEventListener('click', pauseTimer);
    document.getElementById('resumeButton')?.addEventListener('click', resumeTimer);
    document.getElementById('stopButton')?.addEventListener('click', stopTimer);
    document.getElementById('quickProjectForm')?.addEventListener('submit', createQuickProject);
    document.getElementById('timerProject')?.addEventListener('change', function() {
        const pid = this.value;
        const taskSelect = document.getElementById('timerTask');
        taskSelect.disabled = !pid;
        if(pid) loadTasks(pid);
    });
}
function loadTasks(pid) {
    fetch(API_URL + '?action=task_list&project_id=' + pid).then(r=>r.json()).then(d=>{
        const s = document.getElementById('timerTask');
        s.innerHTML = '<option value="">Selecione Tarefa</option>' + d.tasks.map(t=>`<option value="${t.id}">${escapeHtml(t.name)}</option>`).join('');
    });
}
function formatDuration(s) { const h=Math.floor(s/3600), m=Math.floor((s%3600)/60), sec=s%60; return String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(sec).padStart(2,'0'); }
function escapeHtml(t) { return t ? t.replace(/&/g,'&amp;').replace(/</g,'&lt;') : ''; }
function showLoading(show) { const b = document.getElementById('startButton'); if(b) { b.disabled = show; b.innerHTML = show ? '<i class="fas fa-spinner fa-spin"></i>' : '<i class="fas fa-play"></i> Iniciar'; } }
function showNotification(msg, type) { const d = document.createElement('div'); d.className = `toast-notification toast-${type} show`; d.innerHTML = msg; document.body.appendChild(d); setTimeout(()=>d.remove(), 3000); }
function openQuickProjectModal() { document.getElementById('quickProjectModal').classList.add('active'); }
function closeQuickProjectModal() { document.getElementById('quickProjectModal').classList.remove('active'); }