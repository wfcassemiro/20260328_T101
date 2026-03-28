class VideoWatchTracker {
    constructor(options) {
        this.userId = options.userId;
        this.lectureId = options.lectureId;
        this.lectureTitle = options.lectureTitle || '';
        this.requiredPercentage = (typeof options.requiredPercentage === 'number') ? options.requiredPercentage : 0.85;

        // duração da palestra (minutos) vinda do PHP (lectures.duration_minutes)
        this.durationMinutes = (typeof options.durationMinutes === 'number') ? options.durationMinutes : 0;

        this.videoElement = options.videoElement;
        this.progressBar = options.progressBar;
        this.progressText = options.progressText;
        this.certificateButton = options.certificateButton;

        this.watchedSeconds = 0;
        this.accumulatedWatchTime = 0;

        this.saveInterval = null;
        this.lastSavedSeconds = 0;

        this.init();
    }

    async init() {
        await this.loadProgress();
        this.setupEventListeners();
        this.startTracking();
        this.updateUI();
    }

    getDurationSeconds() {
        // prioridade: duração real do elemento de vídeo (se disponível)
        const elDur = this.videoElement && isFinite(this.videoElement.duration) ? this.videoElement.duration : 0;
        if (elDur && elDur > 0) return elDur;

        // fallback: duration_minutes do banco
        if (this.durationMinutes && this.durationMinutes > 0) return this.durationMinutes * 60;

        return 0;
    }

    getWatchedPercentage() {
        const duration = this.getDurationSeconds();
        if (!duration || duration <= 0) return 0;
        return Math.min(1, this.watchedSeconds / duration);
    }

    canGenerateCertificate() {
        return this.getWatchedPercentage() >= this.requiredPercentage;
    }

    async loadProgress() {
        try {
            const params = new URLSearchParams({
                user_id: this.userId,
                lecture_title: this.lectureTitle
            });

            const response = await fetch(`/get_watch_progress.php?${params.toString()}`, {
                credentials: 'same-origin'
            });

            const data = await response.json();
            if (data && data.success) {
                this.watchedSeconds = parseInt(data.last_watched_seconds || 0, 10);
                this.accumulatedWatchTime = parseInt(data.accumulated_watch_time || 0, 10);

                // se accumulated existir, usa como fonte
                if (this.accumulatedWatchTime > 0) {
                    this.watchedSeconds = this.accumulatedWatchTime;
                }

                this.lastSavedSeconds = this.watchedSeconds;

                // posiciona o vídeo se fizer sentido
                if (this.videoElement && this.watchedSeconds > 0 && isFinite(this.videoElement.duration) && this.videoElement.duration > 0) {
                    const safeSeek = Math.min(this.watchedSeconds, Math.max(0, this.videoElement.duration - 2));
                    this.videoElement.currentTime = safeSeek;
                }
            }
        } catch (err) {
            console.error('Erro ao carregar progresso:', err);
        }
    }

    setupEventListeners() {
        if (!this.videoElement) return;

        this.videoElement.addEventListener('timeupdate', () => {
            const current = Math.floor(this.videoElement.currentTime || 0);
            if (current > this.watchedSeconds) {
                this.watchedSeconds = current;
                this.updateUI();
            }
        });

        this.videoElement.addEventListener('pause', () => {
            this.saveProgress(true);
        });

        this.videoElement.addEventListener('ended', () => {
            // marca como concluído
            this.saveProgress(true);
            this.updateUI();
        });

        window.addEventListener('beforeunload', () => {
            // tenta salvar no unload
            this.saveProgress(true);
        });

        if (this.certificateButton) {
            this.certificateButton.addEventListener('click', (e) => {
                if (!this.canGenerateCertificate()) {
                    e.preventDefault();
                    alert('Você precisa assistir pelo menos 85% da palestra para gerar o certificado.');
                    return;
                }
            });
        }
    }

    startTracking() {
        // salva a cada 10s
        this.saveInterval = setInterval(() => {
            this.saveProgress(false);
        }, 10000);
    }

    async saveProgress(force = false) {
        try {
            // evita spam se não mudou e não é "force"
            if (!force && this.watchedSeconds <= this.lastSavedSeconds) return;

            const formData = new FormData();
            formData.append('user_id', this.userId);
            formData.append('lecture_id', this.lectureId);
            formData.append('watched_seconds', String(this.watchedSeconds));
            formData.append('lecture_title', this.lectureTitle);

            const response = await fetch('/save_watch_progress.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });

            const data = await response.json();
            if (data && data.success) {
                this.lastSavedSeconds = this.watchedSeconds;

                // se backend devolve accumulated, adota
                if (typeof data.accumulated_watch_time !== 'undefined') {
                    const acc = parseInt(data.accumulated_watch_time || 0, 10);
                    if (acc > 0) {
                        this.accumulatedWatchTime = acc;
                        this.watchedSeconds = acc;
                    }
                }

                this.updateUI();
            }
        } catch (err) {
            console.error('Erro ao salvar progresso:', err);
        }
    }

    updateUI() {
        const pct = this.getWatchedPercentage();
        const pctText = Math.round(pct * 100);

        if (this.progressBar) {
            this.progressBar.style.width = `${pctText}%`;
        }

        if (this.progressText) {
            this.progressText.textContent = `${pctText}% assistido`;
        }

        if (this.certificateButton) {
            this.certificateButton.disabled = !this.canGenerateCertificate();
        }
    }
}

// Função global (mantém compatibilidade com seu HTML)
window.generateCertificate = async function(lectureId, userId) {
    try {
        const formData = new FormData();
        formData.append('lecture_id', lectureId);
        formData.append('user_id', userId);

        const response = await fetch('/generate_certificate.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });

        const data = await response.json();
        if (data && data.success) {
            alert('Certificado gerado com sucesso!');
            // Se você já redireciona/atualiza, adapte aqui
            window.location.reload();
        } else {
            alert((data && data.error) ? data.error : 'Erro ao gerar certificado.');
        }
    } catch (err) {
        console.error(err);
        alert('Erro ao gerar certificado.');
    }
};