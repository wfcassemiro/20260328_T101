<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php'); exit;
}

$page_title = 'Radar T101 - Curadoria Multiplataforma';
$week = date('W');

// Busca a execução mais recente
$newsletter = $pdo->query("SELECT * FROM weekly_newsletters ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$articles = $pdo->query("SELECT * FROM curation_articles ORDER BY id DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../vision/includes/head.php';
?>
<style>
    .radar-grid { display: grid; grid-template-columns: 350px 1fr; gap: 30px; margin: 20px; }
    @media(max-width:900px){ .radar-grid { grid-template-columns: 1fr; } }
    
    .news-item { background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 3px solid #AF52DE; }
    .news-source { font-size: 0.8em; color: #AF52DE; text-transform: uppercase; font-weight: bold; }
    .news-title { margin: 5px 0; font-size: 1.1em; color: #fff; }
    .news-link { color: #aaa; font-size: 0.9em; text-decoration: none; }

    .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; overflow-x: auto; }
    .tab-btn { background: transparent; border: none; color: rgba(255,255,255,0.6); padding: 10px 20px; cursor: pointer; font-weight: bold; border-radius: 5px; transition: 0.3s; white-space: nowrap; }
    .tab-btn.active { background: #AF52DE; color: #fff; }

    .content-box { 
        background: #fff; color: #333; padding: 30px; border-radius: 8px; 
        font-family: -apple-system, sans-serif; line-height: 1.6; 
        display: none; min-height: 300px; white-space: pre-wrap; 
    }
    .content-box.active { display: block; }
    .prompt-box { background: #1a1a1a; color: #00ff9d; font-family: monospace; border: 1px solid #00ff9d; }

    .action-bar { display: flex; justify-content: flex-end; margin-bottom: 10px; }
    .btn-copy { background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 5px; cursor: pointer; }
</style>

<?php include __DIR__ . '/../vision/includes/header.php'; include __DIR__ . '/../vision/includes/sidebar.php'; ?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-satellite-dish"></i> Radar T101</h1>
            <p>Curadoria estratégica e geração multiplataforma (Semana <?php echo $week; ?>).</p>
        </div>
    </div>

    <div class="radar-grid">
        <aside>
            <h3><i class="fas fa-rss"></i> Fontes Processadas</h3>
            <?php foreach($articles as $art): ?>
                <div class="news-item">
                    <div class="news-source"><?php echo htmlspecialchars($art['news_source']); ?></div>
                    <h4 class="news-title"><?php echo htmlspecialchars($art['title']); ?></h4>
                    <a href="<?php echo htmlspecialchars($art['url']); ?>" target="_blank" class="news-link"><i class="fas fa-external-link-alt"></i> Ver Original</a>
                </div>
            <?php endforeach; ?>
        </aside>

        <section>
            <div class="tabs">
                <button class="tab-btn active" onclick="openTab(event, 'newsletter-tab')">Newsletter</button>
                <button class="tab-btn" onclick="openTab(event, 'linkedin')">LinkedIn</button>
                <button class="tab-btn" onclick="openTab(event, 'twitter')">X (Thread)</button>
                <button class="tab-btn" onclick="openTab(event, 'instagram')">Instagram</button>
                <button class="tab-btn" onclick="openTab(event, 'prompt')">Prompt Imagem</button>
            </div>

            <div class="action-bar">
                <button onclick="copyCurrentTab()" class="btn-copy"><i class="fas fa-copy"></i> Copiar Conteúdo</button>
                <span id="copy-msg" style="color: #34C759; margin-left:10px; display: none;">Copiado!</span>
            </div>

            <?php if($newsletter): ?>
                <div id="newsletter-tab" class="content-box active"><?php echo htmlspecialchars($newsletter['compiled_newsletter'] ?? 'Nenhuma newsletter compilada encontrada.'); ?></div>
                <div id="linkedin" class="content-box"><?php echo htmlspecialchars($newsletter['newsletter_content']); ?></div>
                <div id="twitter" class="content-box"><?php echo htmlspecialchars($newsletter['twitter_content']); ?></div>
                <div id="instagram" class="content-box"><?php echo htmlspecialchars($newsletter['instagram_content']); ?></div>
                <div id="prompt" class="content-box prompt-box"><?php echo htmlspecialchars($newsletter['image_prompt']); ?></div>
            <?php else: ?>
                <div class="content-box active" style="text-align:center; color:#999; padding-top:100px;">Aguardando nova execução do n8n...</div>
            <?php endif; ?>
        </section>
    </div>
</div>

<script>
function openTab(evt, tabName) {
    let i, x, tablinks;
    x = document.getElementsByClassName("content-box");
    for (i = 0; i < x.length; i++) { x[i].classList.remove('active'); }
    tablinks = document.getElementsByClassName("tab-btn");
    for (i = 0; i < tablinks.length; i++) { tablinks[i].classList.remove('active'); }
    document.getElementById(tabName).classList.add('active');
    evt.currentTarget.classList.add('active');
}

function copyCurrentTab() {
    const activeBox = document.querySelector('.content-box.active');
    navigator.clipboard.writeText(activeBox.innerText).then(() => {
        const msg = document.getElementById('copy-msg');
        msg.style.display = 'inline';
        setTimeout(() => { msg.style.display = 'none'; }, 2000);
    });
}
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>