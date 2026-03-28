<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Processar formulário de interesse via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_signup'])) {
    header('Content-Type: application/json');
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $course_interest = trim($_POST['course_interest'] ?? '');
    $other_course = trim($_POST['other_course'] ?? '');
    
    if (!empty($name) && !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            $final_interest = ($course_interest === 'Outros' && !empty($other_course)) 
                ? "Outros: " . $other_course 
                : $course_interest;
            
            $stmt = $pdo->prepare("INSERT INTO course_signups (name, email, course_interest, other_course) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $final_interest, $other_course]);
            
            echo json_encode(['success' => true, 'message' => 'Interesse registrado. Obrigado!']);
            exit;
            
        } catch (PDOException $e) {
            error_log("Erro ao inserir interesse em curso: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erro ao processar. Por favor, tente novamente.']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Por favor, preencha todos os campos corretamente.']);
        exit;
    }
}

$page_title = 'Cursos - Translators101';
$page_description = 'Conheça os cursos oferecidos pela Translators101 e em parceria. Especialize-se em tradução de jogos e outras áreas.';

// Buscar e ORDENAR cursos do banco de dados
try {
    $stmt = $pdo->query("SELECT * FROM courses 
                        ORDER BY 
                            CASE 
                                WHEN enrollment_open = 1 THEN 1 
                                WHEN course_type = 'Gravado' THEN 2
                                ELSE 3 
                            END, 
                            start_date DESC");
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $courses = [];
    error_log("Erro ao buscar cursos: " . $e->getMessage());
}

include __DIR__ . '/../vision/includes/head.php';
?>

<style>
/* Estilos específicos para a página de Cursos */
.main-content {
    padding-top: 90px; /* Ajuste para não ficar embaixo do menu */
}

.courses-hero {
    text-align: center;
    padding: 3rem 1rem;
}

.courses-hero h1 {
    font-size: 3rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
}

.courses-hero p {
    font-size: 1.2rem;
    color: var(--text-muted);
    max-width: 700px;
    margin: 0 auto;
}

.courses-section {
    padding: 3rem 1rem;
}

.section-title {
    text-align: center;
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
}

.section-subtitle {
    text-align: center;
    font-size: 1.2rem;
    color: var(--text-muted);
    margin-bottom: 3rem;
    max-width: 700px;
    margin-left: auto;
    margin-right: auto;
}

.courses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
}

.course-card {
    background: var(--glass-bg);
    border-radius: 20px;
    border: 1px solid var(--glass-border);
    overflow: hidden;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}

.course-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(168, 85, 247, 0.3);
    border-color: var(--brand-purple);
}

.course-image-container {
    width: 100%;
    aspect-ratio: 16 / 9;
    overflow: hidden;
    background: #000;
    position: relative;
}

.course-image {
    width: 100%;
    height: 100%;
    object-fit: cover; /* Mudei para cover para preencher melhor */
    display: block;
}

.course-content {
    padding: 2rem;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

.course-badge {
    display: inline-block;
    background: linear-gradient(135deg, var(--accent-gold), #e67e22);
    color: white;
    padding: 0.4rem 1rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 1rem;
    width: fit-content;
}

.course-card h3 {
    font-size: 1.6rem;
    margin-bottom: 1rem;
    color: var(--text-primary);
    line-height: 1.3;
}

.course-card p {
    font-size: 1.05rem;
    line-height: 1.6;
    color: var(--text-muted);
    margin-bottom: 1.5rem;
    flex-grow: 1;
}

.course-features {
    list-style: none;
    padding: 0;
    margin: 1.5rem 0;
}

.course-features li {
    padding: 0.6rem 0;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.95rem;
}

.course-features i {
    color: var(--accent-green);
    font-size: 1.1rem;
}

.course-cta {
    display: inline-block;
    background: linear-gradient(135deg, var(--brand-purple), #6a1b9a);
    color: white;
    padding: 1rem 2rem;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    font-size: 1.1rem;
    text-align: center;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(168, 85, 247, 0.3);
}

.course-cta:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(168, 85, 247, 0.5);
    background: linear-gradient(135deg, #9b59b6, #8e44ad);
}

/* Seção de Newsletter/Inscrição */
.newsletter-section {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 3rem 2rem;
    max-width: 800px;
    margin: 3rem auto;
    text-align: center;
}

.newsletter-section h3 {
    font-size: 2rem;
    margin-bottom: 1rem;
    color: var(--text-primary);
}

.newsletter-section p {
    font-size: 1.1rem;
    color: var(--text-muted);
    margin-bottom: 2rem;
}

.signup-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    max-width: 500px;
    margin: 0 auto;
}

.form-group {
    display: flex;
    flex-direction: column;
    text-align: left;
}

.form-group label {
    font-size: 0.95rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 0.9rem 1.2rem;
    border-radius: 12px;
    border: 1px solid var(--glass-border);
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    font-size: 1rem;
    transition: all 0.3s ease;
    font-family: inherit;
}

.form-group textarea {
    resize: vertical;
    min-height: 80px;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--brand-purple);
    box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.2);
}

.form-group select {
    cursor: pointer;
}

.form-group.hidden {
    display: none;
}

.submit-btn {
    padding: 1rem 2rem;
    background: linear-gradient(135deg, var(--accent-gold), #e67e22);
    color: white;
    border: none;
    border-radius: 30px;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(243, 156, 18, 0.3);
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(243, 156, 18, 0.5);
}

.submit-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.form-message {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1rem;
    font-weight: 600;
    font-size: 1.05rem;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.form-message.success {
    background: rgba(39, 174, 96, 0.9);
    border: 1px solid var(--accent-green);
    color: white;
    box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
}

.form-message.error {
    background: rgba(231, 76, 60, 0.2);
    border: 1px solid #e74c3c;
    color: #e74c3c;
}

/* Seção de Benefícios */
.benefits-section {
    padding-top: 0;
}

@media(max-width: 768px) {
    .courses-hero h1 { font-size: 2.2rem; flex-direction: column; }
    .courses-grid { grid-template-columns: 1fr; }
    .newsletter-section { padding: 2rem 1.5rem; }
}
</style>

<?php
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    
    <div class="glass-hero courses-hero">
        <div class="hero-content">
            <h1><i class="fas fa-graduation-cap"></i> Cursos Translators101</h1>
            <p>Aprimore suas habilidades e especialize-se com nossos cursos desenvolvidos por profissionais experientes do mercado de tradução.</p>
        </div>
    </div>

    <section class="courses-section">
        <div class="glass-section">
            <h2 class="section-title">Cursos da Translators101 e de parceiros</h2>
            <p class="section-subtitle">Escolha o curso ideal para impulsionar sua carreira na tradução.</p>
            
            <div class="courses-grid">
            
                <?php if (empty($courses)): ?>
                    <p style="text-align: center; grid-column: 1 / -1;">Nenhum curso disponível no momento. Volte em breve!</p>
                <?php else: ?>
                    <?php foreach ($courses as $course): ?>
                        <div class="course-card">
                            <div class="course-image-container">
                                <img src="<?= htmlspecialchars($course['image_path'] ?? 'https://placehold.co/1920x1080/8e44ad/ffff?text=' . urlencode($course['title'])) ?>" alt="<?= htmlspecialchars($course['title']) ?>" class="course-image">
                            </div>
                            <div class="course-content">
                                <?php if (!empty($course['badge_text'])): ?>
                                    <span class="course-badge"><?= htmlspecialchars($course['badge_text']) ?></span>
                                <?php endif; ?>
                                
                                <h3><?= htmlspecialchars($course['title']) ?></h3>
                                <p><?= htmlspecialchars($course['short_description']) ?></p>
                                
                                <?php if (!empty($course['features'])): ?>
                                <ul class="course-features">
                                    <?php 
                                    $features = explode("\n", trim($course['features']));
                                    foreach ($features as $feature): 
                                        if (!empty(trim($feature))):
                                    ?>
                                    <li><i class="fas fa-check-circle"></i> <?= htmlspecialchars(trim($feature)) ?></li>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </ul>
                                <?php endif; ?>
                                
                                <?php if ((bool)$course['enrollment_open']): ?>
                                    <a href="<?= htmlspecialchars($course['page_url']) ?>" class="course-cta" target="_blank" rel="noopener noreferrer">Saiba mais</a>
                                <?php else: ?>
                                    <?php if ($course['course_type'] === 'Gravado'): ?>
                                         <a href="<?= htmlspecialchars($course['page_url']) ?>" class="course-cta">Acessar curso gravado</a>
                                    <?php else: ?>
                                        <a href="#interesse" class="course-cta" style="background: linear-gradient(135deg, #777, #555); box-shadow: 0 5px 15px rgba(0,0,0,0.3);">😢 Eu quero!</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </div>
    </section>

    <section class="courses-section benefits-section">
        <div class="glass-section">
            <h2 class="section-title">Por que escolher nossos cursos?</h2>
            <div class="courses-grid">
                <div class="course-card" style="border-color: var(--accent-gold);">
                    <div class="course-content" style="text-align: center;">
                        <div style="font-size: 3rem; color: var(--accent-gold); margin-bottom: 1rem;"><i class="fas fa-star"></i></div>
                        <h3>Instrutores experientes</h3>
                        <p>Aprenda com profissionais que atuam no mercado e têm experiência real em grandes projetos.</p>
                    </div>
                </div>
                <div class="course-card" style="border-color: var(--accent-green);">
                    <div class="course-content" style="text-align: center;">
                        <div style="font-size: 3rem; color: var(--accent-green); margin-bottom: 1rem;"><i class="fas fa-laptop-code"></i></div>
                        <h3>Conteúdo prático</h3>
                        <p>Foco em habilidades aplicáveis no dia a dia, com exercícios e projetos reais.</p>
                    </div>
                </div>
                <div class="course-card" style="border-color: var(--brand-purple);">
                    <div class="course-content" style="text-align: center;">
                        <div style="font-size: 3rem; color: var(--brand-purple); margin-bottom: 1rem;"><i class="fas fa-users"></i></div>
                        <h3>Comunidade ativa</h3>
                        <p>Acesso a grupos exclusivos para networking e troca de experiências com outros alunos.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="courses-section" id="interesse">
        <div class="newsletter-section">
            <h3><i class="fas fa-bell"></i> O curso que você quer não está disponível?</h3>
            <p>Estamos sempre prontos para criar ou reabrir cursos. Inscreva-se aqui para receber informações e promoções de nossos lançamentos.</p>
            
            <div id="formMessageContainer"></div>
            
            <form method="POST" class="signup-form" id="signupForm">
                <div class="form-group">
                    <label for="name">Nome completo *</label>
                    <input type="text" id="name" name="name" required placeholder="Seu nome">
                </div>
                <div class="form-group">
                    <label for="email">E-mail *</label>
                    <input type="email" id="email" name="email" required placeholder="seu@email.com">
                </div>
                <div class="form-group">
                    <label for="course_interest">Curso de interesse</label>
                    <select id="course_interest" name="course_interest">
                        <option value="">Selecione uma opção</option>
                        <option value="Tradução de Jogos">Tradução de jogos</option>
                        <option value="CAT Tools">CAT Tools</option>
                        <option value="Tradução Literária">Tradução literária</option>
                        <option value="Tradução Técnica">Tradução técnica</option>
                        <option value="Revisão de Textos">Revisão de textos</option>
                        <option value="Outros">Outros</option>
                    </select>
                </div>
                <div class="form-group hidden" id="otherCourseGroup">
                    <label for="other_course">Em qual curso você tem interesse? *</label>
                    <textarea id="other_course" name="other_course" placeholder="Descreva o curso de seu interesse"></textarea>
                </div>
                <button type="submit" class="submit-btn" id="submitBtn">
                    <i class="fas fa-paper-plane"></i> Quero receber as novidades
                </button>
            </form>
        </div>
    </section>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const courseSelect = document.getElementById('course_interest');
    const otherCourseGroup = document.getElementById('otherCourseGroup');
    const otherCourseInput = document.getElementById('other_course');
    const signupForm = document.getElementById('signupForm');
    const submitBtn = document.getElementById('submitBtn');
    const formMessageContainer = document.getElementById('formMessageContainer');
    
    if (courseSelect && otherCourseGroup) {
        courseSelect.addEventListener('change', function() {
            if (this.value === 'Outros') {
                otherCourseGroup.classList.remove('hidden');
                otherCourseInput.required = true;
            } else {
                otherCourseGroup.classList.add('hidden');
                otherCourseInput.required = false;
                otherCourseInput.value = '';
            }
        });
    }
    
    if (signupForm) {
        signupForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
            
            const formData = new FormData(signupForm);
            formData.append('ajax_signup', '1');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showMessage(data.message, data.success);
                if (data.success) {
                    signupForm.reset();
                    otherCourseGroup.classList.add('hidden');
                    otherCourseInput.required = false;
                }
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Quero receber as novidades';
            })
            .catch(error => {
                console.error('Erro:', error);
                showMessage('Erro ao processar. Por favor, tente novamente.', false);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Quero receber as novidades';
            });
        });
    }
    
    function showMessage(message, isSuccess) {
        const existingMessage = formMessageContainer.querySelector('.form-message');
        if (existingMessage) {
            existingMessage.remove();
        }
        
        const messageDiv = document.createElement('div');
        messageDiv.className = 'form-message ' + (isSuccess ? 'success' : 'error');
        messageDiv.innerHTML = '<i class="fas fa-' + (isSuccess ? 'check-circle' : 'exclamation-circle') + '"></i> ' + message;
        
        formMessageContainer.appendChild(messageDiv);
        
        if (isSuccess) {
            setTimeout(() => {
                messageDiv.style.opacity = '0';
                messageDiv.style.transform = 'translateY(-10px)';
                messageDiv.style.transition = 'all 0.3s ease';
                setTimeout(() => { messageDiv.remove(); }, 300);
            }, 5000);
        }
    }
});
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>