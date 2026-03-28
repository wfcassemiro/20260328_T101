class VideoWatchTracker {
    constructor(videoElement, userId, lectureId, lectureTitle, requiredPercentage = 1) {
        this.video = videoElement;
        this.userId = userId;
        this.lectureId = lectureId;
        this.lectureTitle = lectureTitle;
        this.requiredPercentage = requiredPercentage;

        // Controle de tempo
        this.watchedSeconds = 0;
        this.lastPosition = 0;
        this.updateInterval = null;
        this.saveInterval = null;

        // Estado
        this.isPlaying = false;
        this.lastUpdateTime = Date.now();

        this.init();
    }

    async init() {
        await this.loadProgress();
        this.setupEventListeners();
        this.startTracking();
    }

    async loadProgress() {
        try {
            const response = await fetch(`/config/api/get_watch_progress.php?user_id=${this.userId}&lecture_title=${encodeURIComponent(this.lectureTitle)}`);
            const data = await response.json();

            if (data.success) {
                this.watchedSeconds = data.watched_seconds || 0;

                // Restaurar posição do vídeo se necessário
                if (this.watchedSeconds > 0 && this.video.currentTime < this.watchedSeconds) {
                    this.video.currentTime = this.watchedSeconds;
                }

                this.updateUI();
                console.log(`Progresso carregado: ${this.watchedSeconds}s`);
            }
        } catch (error) {
            console.error('Erro ao carregar progresso:', error);
        }
    }

    setupEventListeners() {
        // Eventos de reprodução
        this.video.addEventListener('play', () => {
            this.isPlaying = true;
            this.lastUpdateTime = Date.now();
            console.log('Vídeo iniciado');
        });

        this.video.addEventListener('pause', () => {
            this.isPlaying = false;
            this.updateWatchedTime();
            console.log('Vídeo pausado');
        });

        this.video.addEventListener('ended', () => {
            this.isPlaying = false;
            this.updateWatchedTime();
            this.saveProgress();
            console.log('Vídeo finalizado');
        });

        // Atualizar posição atual
        this.video.addEventListener('timeupdate', () => {
            this.handleTimeUpdate();
        });

        // Salvar progresso quando sair da página
        window.addEventListener('beforeunload', () => {
            this.updateWatchedTime();
            this.saveProgress();
        });
    }

    startTracking() {
        // Atualizar tempo assistido a cada segundo
        this.updateInterval = setInterval(() => {
            if (this.isPlaying) {
                this.updateWatchedTime();
            }
        }, 1000);

        // Salvar progresso a cada 10 segundos
        this.saveInterval = setInterval(() => {
            if (this.isPlaying) {
                this.saveProgress();
            }
        }, 10000);
    }

    updateWatchedTime() {
        if (this.isPlaying && this.video.currentTime > this.lastPosition) {
            const currentTime = this.video.currentTime;

            // Só contar tempo se não houve pulo grande
            if (currentTime - this.lastPosition < 5) {
                this.watchedSeconds = Math.max(this.watchedSeconds, currentTime);
            }

            this.lastPosition = currentTime;
            this.updateUI();
        }
    }

    handleTimeUpdate() {
        const currentTime = this.video.currentTime;

        // Detectar se o usuário pulou para frente
        if (currentTime > this.lastPosition + 2) {
            console.log(`Pulo detectado: ${this.lastPosition.toFixed(2)}s → ${currentTime.toFixed(2)}s`);
        }

        this.lastPosition = currentTime;
    }

    async saveProgress() {
        try {
            const response = await fetch('/config/api/save_watch_progress.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: this.userId,
                    lecture_id: this.lectureId,
                    lecture_title: this.lectureTitle,
                    watched_seconds: this.watchedSeconds
                })
            });

            const data = await response.json();
            if (data.success) {
                console.log(`Progresso salvo: ${this.watchedSeconds}s`);
            } else {
                console.error('Erro ao salvar progresso:', data.error);
            }
        } catch (error) {
            console.error('Erro ao salvar progresso:', error);
        }
    }

    updateUI() {
        const watchedPercentage = this.getWatchedPercentage();
        const canGenerateCertificate = this.canGenerateCertificate();

        // Atualizar elementos da UI
        const progressElement = document.getElementById('watch-progress');
        if (progressElement) {
            progressElement.textContent = `Assistido: ${watchedPercentage.toFixed(1)}%`;
        }

        const certificateBtn = document.getElementById('generate-certificate-btn');
        if (certificateBtn) {
            certificateBtn.disabled = !canGenerateCertificate;

            if (canGenerateCertificate) {
                certificateBtn.textContent = 'Gerar Certificado';
                certificateBtn.className = 'btn btn-success';
            } else {
                certificateBtn.textContent = `Assista ${this.requiredPercentage}% para gerar certificado`;
                certificateBtn.className = 'btn btn-warning';
            }
        }

        // Atualizar barra de progresso se existir
        const progressBar = document.getElementById('progress-bar');
        if (progressBar) {
            progressBar.style.width = watchedPercentage + '%';
        }
    }

    getWatchedPercentage() {
        if (!this.video.duration || this.video.duration === 0) return 0;
        return (this.watchedSeconds / this.video.duration) * 100;
    }

    canGenerateCertificate() {
        const watchedPercentage = this.getWatchedPercentage();
        return watchedPercentage >= this.requiredPercentage;
    }

    // Método público para verificar se pode gerar certificado
    checkCertificateEligibility() {
        return {
            canGenerate: this.canGenerateCertificate(),
            watchedPercentage: this.getWatchedPercentage(),
            requiredPercentage: this.requiredPercentage,
            watchedSeconds: this.watchedSeconds,
            totalDuration: this.video.duration
        };
    }

    // Limpar intervalos quando não precisar mais
    destroy() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
        if (this.saveInterval) {
            clearInterval(this.saveInterval);
        }
        this.updateWatchedTime();
        this.saveProgress();
    }
}

// Função para inicializar o tracker (compatível com sistema existente)
function initVideoTracker(videoElement, userId, lectureId, lectureTitle, requiredPercentage = 1) {
    return new VideoWatchTracker(videoElement, userId, lectureId, lectureTitle, requiredPercentage);
}

// Função para gerar certificado (compatível com sistema existente)
async function generateCertificate(lectureId, userId) {
    try {
        const response = await fetch('./generate_certificate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                lecture_id: lectureId,
                user_id: userId
            })
        });

        const data = await response.json();

        if (data.success) {
            alert('Certificado gerado com sucesso!');
            // Redirecionar para visualizar certificado
            window.location.href = `/view_certificate.php?id=${data.certificate_id}`;
        } else {
            alert('Erro: ' + data.message);
        }
    } catch (error) {
        console.error('Erro ao gerar certificado:', error);
        alert('Erro inesperado ao gerar certificado. Tente novamente.');
    }
}