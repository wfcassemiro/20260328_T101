/**
 * Time Tracker * JavaScript
 * Sistema de rastreamento de tempo
 */

// Configuracao da *API (precisa bater com *PHP)
const API_URL = window.API_URL || '/dash-t101/api_time_tracker.php';

// Estado Global
const state = {
    runningEntry: null,
    timerInterval: null,
    isPaused: false,
    currentProject: null,
    projects: [],
    tasks: [],
    entries: []
};

// Inicializacao
document.addEventListener('DOMContentLoaded', function() {
    console.log('=== [TT] DOMContentLoaded disparado - iniciando app ===');
    console.log('[TT] API_URL em JS:', API_URL);
    initializeApp();
});

function initializeApp() {
    console.log('[TT] initializeApp() chamado');

    try {
        console.log('[TT] 1) Carregando projetos...');
        loadProjects();

        console.log('[TT] 2) Carregando entries...');
        loadEntries();

        console.log('[TT] 3) Verificando timer ativo...');
        checkRunningTimer();

        console.log('[TT] 4) Configurando event listeners...');
        setupEventListeners();

        console.log('[TT] 5) Iniciando atualizacao do timer...');
        startTimerUpdate();

        console.log('[TT] initializeApp() concluido com sucesso!');
    } catch (error) {
        console.error('[TT] ERRO na inicializacao:', error);
        alert('Erro na inicializacao do Time Tracker: ' + error.message);
    }
}

// ==== EVENT LISTENERS ====
function setupEventListeners() {
    console.log('[TT] setupEventListeners() chamado');

    const startBtn = document.getElementById('startButton');
    const pauseBtn = document.getElementById('pauseButton');
    const resumeBtn = document.getElementById('resumeButton');
    const stopBtn = document.getElementById('stopButton');
    const projectSelect = document.getElementById('timerProject');
    const quickForm = document.getElementById('quickProjectForm');

    console.log('[TT]   startButton existe?', !!startBtn);
    console.log('[TT]   pauseButton existe?', !!pauseBtn);
    console.log('[TT]   resumeButton existe?', !!resumeBtn);
    console.log('[TT]   stopButton existe?', !!stopBtn);
    console.log('[TT]   timerProject existe?', !!projectSelect);
    console.log('[TT]   quickProjectForm existe?', !!quickForm);

    if (startBtn) startBtn.addEventListener('click', startTimer);
    if (pauseBtn) pauseBtn.addEventListener('click', pauseTimer);
    if (resumeBtn) resumeBtn.addEventListener('click', resumeTimer);
    if (stopBtn) stopBtn.addEventListener('click', stopTimer);

    if (projectSelect) {
        projectSelect.addEventListener('change', function() {
            console.log('[TT] timerProject change, novo valor:', this.value);
            const projectId = this.value;
            const taskSelect = document.getElementById('timerTask');

            if (projectId) {
                taskSelect.disabled = false;
                loadTasksForSelect(projectId);
            } else {
                taskSelect.disabled = true;
                taskSelect.innerHTML = '<option value="">Selecione uma tarefa</option>';
            }
        });
    }

    if (quickForm) {
        quickForm.addEventListener('submit', function(e) {
            console.log('[TT] submit quickProjectForm disparado');
            createQuickProject(e);
        });
    }

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tabName = this.dataset.tab;
            switchTab(tabName);
        });
    });
}

function switchTab(tabName) {
    console.log('[TT] switchTab:', tabName);

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tabName);
    });
    
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.toggle('active', content.id === tabName + 'Tab');
    });

    if (tabName === 'projects') {
        loadProjects();
    } else if (tabName === 'history') {
        loadEntries();
    }
}

// ==== TIMER FUNCTIONS ====
function startTimer() {
    console.log('[TT] startTimer() chamado');
    const description = document.getElementById('timerDescription').value;
    const projectId = document.getElementById('timerProject').value || null;
    const taskId = document.getElementById('timerTask').value || null;

    console.log('[TT]   description:', description);
    console.log('[TT]   projectId:', projectId);
    console.log('[TT]   taskId:', taskId);
    
    const formData = new FormData();
    formData.append('action', 'entry_start');
    formData.append('description', description);
    if (projectId) formData.append('project_id', projectId);
    if (taskId) formData.append('task_id', taskId);
    
    fetch(API_URL, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('[TT] startTimer() status:', response.status, response.statusText);
        return response.text();
    })
    .then(text => {
        console.log('[TT] startTimer() corpo bruto:');
        console.log(text);
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('[TT] startTimer() JSON.parse ERRO:', e.message);
            alert('Erro ao interpretar resposta da API ao iniciar cronometro');
            return;
        }
        if (data.success) {
            showNotification('Cronometro iniciado!', 'success');
            checkRunningTimer();
        } else {
            showNotification(data.error || 'Erro ao iniciar cronometro', 'error');
        }
    })
    .catch(error => {
        console.error('[TT] startTimer() ERRO:', error);
        showNotification('Erro ao iniciar cronometro', 'error');
    });
}

function pauseTimer() {
    if (!state.runningEntry) return;
    console.log('[TT] pauseTimer() chamado para ID:', state.runningEntry.id);
    
    const formData = new FormData();
    formData.append('action', 'entry_pause');
    formData.append('id', state.runningEntry.id);
    
    fetch(API_URL, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            state.isPaused = true;
            updateTimerUI();
            showNotification('Cronometro pausado', 'info');
        } else {
            showNotification(data.error || 'Erro ao pausar', 'error');
        }
    })
    .catch(error => {
        console.error('[TT] pauseTimer() ERRO:', error);
        showNotification('Erro ao pausar', 'error');
    });
}

function resumeTimer() {
    if (!state.runningEntry) return;
    console.log('[TT] resumeTimer() chamado para ID:', state.runningEntry.id);
    
    const formData = new FormData();
    formData.append('action', 'entry_resume');
    formData.append('id', state.runningEntry.id);
    
    fetch(API_URL, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            state.isPaused = false;
            updateTimerUI();
            showNotification('Cronometro retomado', 'success');
        } else {
            showNotification(data.error || 'Erro ao retomar', 'error');
        }
    })
    .catch(error => {
        console.error('[TT] resumeTimer() ERRO:', error);
        showNotification('Erro ao retomar', 'error');
    });
}

function stopTimer() {
    if (!state.runningEntry) return;
    if (!confirm('Deseja parar o cronometro?')) return;
    console.log('[TT] stopTimer() chamado para ID:', state.runningEntry.id);
    
    const formData = new FormData();
    formData.append('action', 'entry_stop');
    formData.append('id', state.runningEntry.id);
    
    fetch(API_URL, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification('Cronometro parado! Duracao: ' + data.duration_formatted, 'success');
            state.runningEntry = null;
            state.isPaused = false;
            resetTimerUI();
            loadEntries();
        } else {
            showNotification(data.error || 'Erro ao parar', 'error');
        }
    })
    .catch(error => {
        console.error('[TT] stopTimer() ERRO:', error);
        showNotification('Erro ao parar', 'error');
    });
}

function checkRunningTimer() {
    console.log('[TT] checkRunningTimer() chamado');
    fetch(API_URL + '?action=entry_running')
        .then(r => r.json())
        .then(data => {
            console.log('[TT] checkRunningTimer() resposta:', data);
            if (data.success && data.entry) {
                state.runningEntry = data.entry;
                state.isPaused = data.entry.is_paused || false;
                updateTimerUI();
            } else {
                state.runningEntry = null;
                resetTimerUI();
            }
        })
        .catch(error => {
            console.error('[TT] checkRunningTimer() ERRO:', error);
        });
}

function startTimerUpdate() {
    console.log('[TT] startTimerUpdate() chamado');
    state.timerInterval = setInterval(() => {
        if (state.runningEntry && !state.isPaused) {
            updateTimerDisplay();
        }
    }, 1000);
}

function updateTimerDisplay() {
    if (!state.runningEntry) return;
    
    const startTime = new Date(state.runningEntry.start_time.replace(' ', 'T'));
    const now = new Date();
    const elapsed = Math.floor((now - startTime) / 1000);
    const pausedDuration = parseInt(state.runningEntry.paused_duration) || 0;
    
    let duration = elapsed - pausedDuration;
    
    if (state.runningEntry.paused_at) {
        const pausedAt = new Date(state.runningEntry.paused_at.replace(' ', 'T'));
        const currentPause = Math.floor((now - pausedAt) / 1000);
        duration -= currentPause;
    }
    
    document.querySelector('.time-digits').textContent = formatDuration(Math.max(0, duration));
}

function updateTimerUI() {
    if (state.runningEntry) {
        document.getElementById('timerDescription').value = state.runningEntry.description || '';
        if (state.runningEntry.project_id) {
            document.getElementById('timerProject').value = state.runningEntry.project_id;
            document.getElementById('timerProject').disabled = true;
        }
        if (state.runningEntry.task_id) {
            document.getElementById('timerTask').value = state.runningEntry.task_id;
            document.getElementById('timerTask').disabled = true;
        }
        
        let info = '';
        if (state.runningEntry.project_name) {
            info += '<span style="color: ' + (state.runningEntry.project_color || '#7B61FF') + '">';
            info += '* ' + state.runningEntry.project_name;
            if (state.runningEntry.task_name) {
                info += ' - ' + state.runningEntry.task_name;
            }
            info += '</span>';
        }
        document.getElementById('timerInfo').innerHTML = info;
        
        document.getElementById('startButton').style.display = 'none';
        
        if (state.isPaused) {
            document.getElementById('pauseButton').style.display = 'none';
            document.getElementById('resumeButton').style.display = 'inline-flex';
        } else {
            document.getElementById('pauseButton').style.display = 'inline-flex';
            document.getElementById('resumeButton').style.display = 'none';
        }
        
        document.getElementById('stopButton').style.display = 'inline-flex';
        
        updateTimerDisplay();
    } else {
        resetTimerUI();
    }
}

function resetTimerUI() {
    document.querySelector('.time-digits').textContent = '00:00:00';
    document.getElementById('timerInfo').innerHTML = '';
    document.getElementById('timerDescription').value = '';
    document.getElementById('timerProject').value = '';
    document.getElementById('timerProject').disabled = false;
    document.getElementById('timerTask').value = '';
    document.getElementById('timerTask').disabled = true;
    
    document.getElementById('startButton').style.display = 'inline-flex';
    document.getElementById('pauseButton').style.display = 'none';
    document.getElementById('resumeButton').style.display = 'none';
    document.getElementById('stopButton').style.display = 'none';
}

// ==== QUICK PROJECT MODAL ====
function openQuickProjectModal() {
    console.log('[TT] openQuickProjectModal()');
    document.getElementById('quickProjectModal').classList.add('active');
    document.getElementById('quickProjectName').focus();
}

function closeQuickProjectModal() {
    console.log('[TT] closeQuickProjectModal()');
    document.getElementById('quickProjectModal').classList.remove('active');
    document.getElementById('quickProjectForm').reset();
}

function createQuickProject(event) {
    event.preventDefault();
    console.log('[TT] createQuickProject() chamado');

    const name = document.getElementById('quickProjectName').value.trim();
    const client = document.getElementById('quickClientName').value.trim();

    console.log('[TT]   Nome:', name);
    console.log('[TT]   Cliente:', client);

    if (!name) {
        alert('Nome do projeto e obrigatorio');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'project_create_quick');
    formData.append('name', name);
    formData.append('client', client);

    console.log('[TT]   Enviando para API_URL:', API_URL);

    fetch(API_URL, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('[TT] createQuickProject() status:', response.status, response.statusText);
        return response.text();
    })
    .then(text => {
        console.log('[TT] createQuickProject() corpo bruto:');
        console.log(text);

        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('[TT] createQuickProject() JSON.parse ERRO:', e.message);
            alert('Erro ao interpretar resposta da API ao criar projeto. Veja console.');
            return;
        }

        console.log('[TT] createQuickProject() JSON:', data);

        if (data.success) {
            const projectId = data.project_id || (data.data && data.data.project_id);
            showNotification(data.message || 'Projeto criado', 'success');
            closeQuickProjectModal();
            loadProjects();

            if (projectId) {
                setTimeout(() => {
                    console.log('[TT] createQuickProject() selecionando projeto:', projectId);
                    document.getElementById('timerProject').value = projectId;
                }, 500);
            } else {
                console.warn('[TT] createQuickProject() sucesso sem project_id');
            }
        } else {
            showNotification(data.error || 'Erro ao criar projeto', 'error');
        }
    })
    .catch(error => {
        console.error('[TT] createQuickProject() ERRO:', error);
        showNotification('Erro ao criar projeto', 'error');
    });
}

// ==== PROJECTS ====
function loadProjects() {
    const url = API_URL + '?action=project_list';
    console.log('[TT] loadProjects() - URL chamada:', url);
    
    fetch(url)
        .then(response => {
            console.log('[TT] loadProjects() - status:', response.status, response.statusText);
            return response.text();
        })
        .then(text => {
            console.log('[TT] loadProjects() - corpo bruto:');
            console.log(text);

            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('[TT] loadProjects() JSON.parse ERRO:', e.message);
                alert('Erro ao interpretar resposta da API de projetos. Veja o console.');
                return;
            }

            console.log('[TT] loadProjects() - JSON parseado:', data);

            if (data.success) {
                const projects = data.projects || (data.data && data.data.projects) || [];
                state.projects = projects;
                console.log('[TT] loadProjects() - projetos recebidos:', projects.length);
                updateProjectSelects();
            } else {
                console.error('[TT] loadProjects() - API erro:', data.error);
                alert('Erro ao carregar projetos: ' + (data.error || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('[TT] loadProjects() - ERRO fetch:', error);
            alert('Erro de conexao ao carregar projetos: ' + error.message);
        });
}

function updateProjectSelects() {
    const timerSelect = document.getElementById('timerProject');
    const filterSelect = document.getElementById('filterProject');

    console.log('[TT] updateProjectSelects() - timerSelect existe?', !!timerSelect, 'filterSelect existe?', !!filterSelect);
    
    if (!timerSelect || !filterSelect) return;
    
    const optionsTimer =
        '<option value="">Selecione um projeto</option>' +
        state.projects.map(p => '<option value="' + p.id + '">' + escapeHtml(p.name) + '</option>').join('');
    
    const optionsFilter =
        '<option value="">Todos os projetos</option>' +
        state.projects.map(p => '<option value="' + p.id + '">' + escapeHtml(p.name) + '</option>').join('');
    
    timerSelect.innerHTML = optionsTimer;
    filterSelect.innerHTML = optionsFilter;
}

// ==== TASKS ====
function openTasksModal(projectId, projectName) {
    state.currentProject = projectId;
    document.getElementById('tasksModalTitle').textContent = 'Tarefas: ' + projectName;
    document.getElementById('tasksModal').classList.add('active');
    loadTasks(projectId);
}

function closeTasksModal() {
    document.getElementById('tasksModal').classList.remove('active');
    state.currentProject = null;
}

function loadTasks(projectId) {
    fetch(API_URL + '?action=task_list&project_id=' + projectId)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderTasks(data.tasks);
            }
        })
        .catch(error => {
            console.error('[TT] loadTasks() ERRO:', error);
        });
}

function loadTasksForSelect(projectId) {
    fetch(API_URL + '?action=task_list&project_id=' + projectId)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const taskSelect = document.getElementById('timerTask');
                taskSelect.innerHTML =
                    '<option value="">Selecione uma tarefa</option>' +
                    data.tasks.map(t => '<option value="' + t.id + '">' + escapeHtml(t.name) + '</option>').join('');
            }
        })
        .catch(error => {
            console.error('[TT] loadTasksForSelect() ERRO:', error);
        });
}

function renderTasks(tasks) {
    const container = document.getElementById('tasksList');
    
    if (!container) return;

    if (tasks.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: rgba(255,255,255,0.5); padding: 20px;">Nenhuma tarefa criada</p>';
        return;
    }
    
    container.innerHTML = tasks.map(task => 
        '<div class="task-item">' +
            '<div>' +
                '<div class="task-name">' + escapeHtml(task.name) + '</div>' +
                '<div class="task-meta">' + task.entry_count + ' registros * ' + task.duration_formatted + '</div>' +
            '</div>' +
            '<button class="btn btn-sm btn-icon btn-danger" onclick="deleteTask(\'' + task.id + '\')">' +
                '<i class="fas fa-trash"></i>' +
            '</button>' +
        '</div>'
    ).join('');
}

function createTask() {
    const name = document.getElementById('newTaskName').value.trim();
    
    if (!name) {
        showNotification('Digite o nome da tarefa', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'task_create');
    formData.append('project_id', state.currentProject);
    formData.append('name', name);
    
    fetch(API_URL, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            document.getElementById('newTaskName').value = '';
            loadTasks(state.currentProject);
        } else {
            showNotification(data.error || 'Erro ao criar tarefa', 'error');
        }
    })
    .catch(error => {
        console.error('[TT] createTask() ERRO:', error);
        showNotification('Erro ao criar tarefa', 'error');
    });
}

function deleteTask(taskId) {
    if (!confirm('Deseja realmente deletar esta tarefa?')) return;
    
    const formData = new FormData();
    formData.append('action', 'task_delete');
    formData.append('id', taskId);
    
    fetch(API_URL, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            loadTasks(state.currentProject);
        } else {
            showNotification(data.error || 'Erro ao deletar tarefa', 'error');
        }
    })
    .catch(error => {
        console.error('[TT] deleteTask() ERRO:', error);
        showNotification('Erro ao deletar tarefa', 'error');
    });
}

// ==== ENTRIES ====
function loadEntries() {
    console.log('[TT] loadEntries() chamado');
    const projectFilterEl = document.getElementById('filterProject');
    const projectFilter = projectFilterEl ? projectFilterEl.value : '';
    let url = API_URL + '?action=entry_list&limit=50';
    
    if (projectFilter) {
        url += '&project_id=' + projectFilter;
    }

    console.log('[TT] loadEntries() URL:', url);
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            console.log('[TT] loadEntries() resposta:', data);
            if (data.success) {
                state.entries = data.entries;
                renderEntries();
            }
        })
        .catch(error => {
            console.error('[TT] loadEntries() ERRO:', error);
        });
}

function renderEntries() {
    const container = document.getElementById('entriesList');
    if (!container) return;
    
    if (state.entries.length === 0) {
        container.innerHTML = 
            '<div class="empty-state">' +
                '<i class="fas fa-clock"></i>' +
                '<h3>Nenhum registro encontrado</h3>' +
                '<p>Inicie o cronometro para comecar a rastrear tempo</p>' +
            '</div>';
        return;
    }
    
    container.innerHTML = state.entries.map(entry => {
        const date = new Date(entry.start_time.replace(' ', 'T'));
        const dateStr = date.toLocaleDateString('pt-BR');
        const timeStr = date.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
        
        return 
            '<div class="entry-item">' +
                '<div class="entry-color" style="background: ' + (entry.project_color || '#7B61FF') + ';"></div>' +
                '<div class="entry-details">' +
                    '<div class="entry-description">' + (entry.description || 'Sem descricao') + '</div>' +
                    '<div class="entry-meta">' +
                        (entry.project_name ? '<span><i class="fas fa-folder"></i> ' + escapeHtml(entry.project_name) + '</span>' : '') +
                        (entry.task_name ? '<span><i class="fas fa-tasks"></i> ' + escapeHtml(entry.task_name) + '</span>' : '') +
                        '<span><i class="fas fa-calendar"></i> ' + dateStr + '</span>' +
                        '<span><i class="fas fa-clock"></i> ' + timeStr + '</span>' +
                    '</div>' +
                '</div>' +
                '<div class="entry-duration">' + entry.duration_formatted + '</div>' +
                '<div class="entry-actions">' +
                    '<button class="btn btn-sm btn-icon btn-danger" onclick="deleteEntry(\'' + entry.id + '\')">' +
                        '<i class="fas fa-trash"></i>' +
                    '</button>' +
                '</div>' +
            '</div>';
    }).join('');
}

function deleteEntry(entryId) {
    if (!confirm('Deseja realmente deletar este registro?')) return;
    
    const formData = new FormData();
    formData.append('action', 'entry_delete');
    formData.append('id', entryId);
    
    fetch(API_URL, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            loadEntries();
        } else {
            showNotification(data.error || 'Erro ao deletar registro', 'error');
        }
    })
    .catch(error => {
        console.error('[TT] deleteEntry() ERRO:', error);
        showNotification('Erro ao deletar registro', 'error');
    });
}

// ==== UTILITY FUNCTIONS ====
function formatDuration(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    
    return String(hours).padStart(2, '0') + ':' + 
           String(minutes).padStart(2, '0') + ':' + 
           String(secs).padStart(2, '0');
}

function escapeHtml(text) {
    if (text == null) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) {
        return map[m];
    });
}

function showNotification(message, type) {
    type = type || 'info';
    const icon = type === 'success' ? '[OK]' : type === 'error' ? '[ERRO]' : '[INFO]';
    console.log('[' + type.toUpperCase() + '] ' + message);
    if (type === 'error') {
        alert(icon + ' ' + message);
    }
}