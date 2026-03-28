<?php
session_start();

// Se não está logado, redireciona para homepage
if (!isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit();
}

// Se confirmou logout
if (isset($_POST['confirm_logout']) && $_POST['confirm_logout'] === 'yes') {
    // Destruir sessão
    session_destroy();
    
    // Redirecionar para página de vendas
    header('Location: /index.php');
    exit();
}

// Se cancelou logout
if (isset($_POST['confirm_logout']) && $_POST['confirm_logout'] === 'no') {
    // Redirecionar de volta para videoteca
    header('Location: /videoteca.php');
    exit();
}

$page_title = 'Confirmar Saída';
$page_description = 'Confirme se deseja sair da sua conta.';

include __DIR__ . '/vision/includes/head.php';
?>

<?php include __DIR__ . '/vision/includes/header.php'; ?>

<?php include __DIR__ . '/vision/includes/sidebar.php'; ?>

<main class="main-content">
    <section class="glass-hero logout-confirm-section">
        <div class="logout-confirm-container">
            <div class="logout-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            
            <h1 class="logout-title">Confirmar saída</h1>
            <p class="logout-message">Tem certeza de que deseja sair da sua conta?</p>
            
            <div class="logout-actions">
                <form method="POST" class="logout-form">
                    <button type="submit" name="confirm_logout" value="yes" class="btn btn-danger logout-btn">
                        <i class="fas fa-check"></i> Sim, quero sair
                    </button>
                    
                    <button type="submit" name="confirm_logout" value="no" class="btn btn-secondary cancel-btn">
                        <i class="fas fa-times"></i> Não, continuar logado
                    </button>
                </form>
            </div>
            
            <div class="logout-info">
                <p><i class="fas fa-info-circle"></i> Ao sair, você será redirecionado para a página inicial e precisará fazer login novamente para acessar o conteúdo.</p>
            </div>
        </div>
    </section>
</main>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>

<style>
.logout-confirm-section {
    min-height: 70vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.logout-confirm-container {
    max-width: 500px;
    text-align: center;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 60px 40px;
    border: 2px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

.logout-icon {
    margin-bottom: 30px;
}

.logout-icon i {
    font-size: 4rem;
    color: var(--accent-red, #e74c3c);
}

.logout-title {
    font-size: 2.2rem;
    color: var(--text-primary);
    margin-bottom: 20px;
    font-weight: 600;
}

.logout-message {
    font-size: 1.2rem;
    color: var(--text-secondary);
    margin-bottom: 40px;
    line-height: 1.6;
}

.logout-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-bottom: 30px;
}

.logout-btn, .cancel-btn {
    padding: 15px 30px;
    font-size: 1.1rem;
    font-weight: 600;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.logout-btn {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: white;
}

.logout-btn:hover {
    background: linear-gradient(135deg, #c0392b, #a93226);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(231, 76, 60, 0.4);
}

.cancel-btn {
    background: linear-gradient(135deg, var(--brand-purple, #8e44ad), #7d3c98);
    color: white;
}

.cancel-btn:hover {
    background: linear-gradient(135deg, #7d3c98, #6c3483);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(142, 68, 173, 0.4);
}

.logout-info {
    background: rgba(255, 255, 255, 0.05);
    padding: 20px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.logout-info p {
    margin: 0;
    font-size: 0.95rem;
    color: var(--text-muted, #d4d4d4);
    line-height: 1.5;
}

.logout-info i {
    color: var(--accent-gold, #f39c12);
    margin-right: 8px;
}

@media (max-width: 768px) {
    .logout-confirm-container {
        margin: 20px;
        padding: 40px 30px;
    }
    
    .logout-title {
        font-size: 1.8rem;
    }
    
    .logout-message {
        font-size: 1.1rem;
    }
    
    .logout-btn, .cancel-btn {
        padding: 12px 24px;
        font-size: 1rem;
    }
}
</style>