<?php
session_start();
require_once __DIR__ . '/../config/database.php';
date_default_timezone_set('America/Sao_Paulo');

// Segurança: Apenas Admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php'); exit;
}

// Busca o rascunho pendente mais recente
$draft = $pdo->query("SELECT * FROM monthly_newsletters_drafts WHERE status = 'pending' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$page_title = 'Revisão de Newsletter Mensal';
// Inclui os assets do Vision
include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';

// Função para imprimir conteúdo dentro de <textarea> com segurança (sem “quebrar” o textarea)
function escape_for_textarea($value) {
    $value = (string)$value;

    // Evita que alguém feche o textarea e injete HTML fora dele
    $value = str_ireplace('</textarea', '&lt;/textarea', $value);

    // Em textarea você quer ver o HTML “cru”, então não escape < e >.
    // Mas precisa escapar &, < e > para não virar HTML? Na prática, o textarea mostra texto,
    // mas o parser HTML ainda interpreta algumas sequências. O mais seguro é escapar & e < >
    // porém isso voltaria a mostrar tags como texto.
    //
    // Como seu objetivo é EDITAR o HTML, o correto é mostrar o HTML como texto aqui.
    // Portanto: mantemos escapado (para edição) e usamos um preview renderizado para leitura.
    // Assim você tem os dois: edição e leitura real.
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Preview renderizado (bloqueia <script> por segurança mínima)
function safe_preview_html($html) {
    $html = (string)$html;
    // remove scripts
    $html = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $html);
    return $html;
}
?>

<style>
    /* Estilos específicos para a área de revisão */
    .editor-wrapper {
        display: flex;
        justify-content: center;
        padding: 20px;
    }
    
    .editor-container { 
        background: #1c1c1e; 
        padding: 40px; 
        border-radius: 16px; 
        width: 100%;
        max-width: 900px;
        border: 1px solid rgba(255,255,255,0.1); 
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    }

    .subject-group {
        margin-bottom: 25px;
    }

    .subject-label { 
        color: #c084fc; 
        font-weight: 600; 
        display: block; 
        margin-bottom: 10px; 
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .subject-input { 
        width: 100%; 
        padding: 15px; 
        font-size: 1.2rem; 
        background: rgba(0,0,0,0.3); 
        color: #fff !important; 
        border: 1px solid #333; 
        border-radius: 8px; 
        transition: border-color 0.3s;
    }
    
    .subject-input:focus {
        border-color: #c084fc;
        outline: none;
    }

    /* Área de preview (renderizada) em fundo branco */
    .preview-area {
        width: 100%;
        padding: 30px;
        background-color: #ffffff !important;
        color: #1a1a1a !important;
        border-radius: 8px;
        border: 1px solid rgba(0,0,0,0.08);
        overflow: auto;
    }

    /* Textarea para edição do HTML */
    .content-area { 
        width: 100%; 
        min-height: 320px; 
        padding: 20px; 
        background: rgba(0,0,0,0.3) !important;
        color: #fff !important;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        line-height: 1.5; 
        font-size: 13px;
        border-radius: 8px; 
        border: 1px solid #333;
        resize: vertical;
    }

    .action-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid rgba(255,255,255,0.1);
    }

    .btn-forward { 
        background: linear-gradient(135deg, #AF52DE 0%, #8A2BE2 100%); 
        color: white; 
        padding: 15px 35px; 
        border: none; 
        border-radius: 50px; 
        font-weight: bold; 
        cursor: pointer; 
        font-size: 1rem; 
        display: flex;
        align-items: center;
        gap: 10px;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .btn-forward:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 5px 15px rgba(175, 82, 222, 0.4);
    }

    .btn-discard {
        color: #ef4444;
        background: transparent;
        border: 1px solid rgba(239, 68, 68, 0.3);
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.9rem;
        transition: all 0.2s;
    }

    .btn-discard:hover {
        background: rgba(239, 68, 68, 0.1);
        border-color: #ef4444;
    }

    .tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 12px;
    }
    .tab-btn {
        background: rgba(255,255,255,0.06);
        border: 1px solid rgba(255,255,255,0.12);
        color: #fff;
        padding: 8px 12px;
        border-radius: 999px;
        cursor: pointer;
        font-size: 0.85rem;
    }
    .tab-btn.active {
        background: rgba(192,132,252,0.25);
        border-color: rgba(192,132,252,0.5);
    }
</style>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-edit"></i> Revisão de Newsletter Mensal</h1>
            <p>Revise o texto gerado pela IA. Ao avançar, o conteúdo será carregado na Central de E-mails.</p>
        </div>
    </div>

    <div class="editor-wrapper">
        <?php if ($draft): ?>
        <form action="enviar_para_central.php" method="POST" class="editor-container">
            <input type="hidden" name="draft_id" value="<​?php echo (int)$draft['id']; ?>">
            
            <div class="subject-group">
                <label class="subject-label"><i class="fas fa-heading"></i> Assunto do E-mail</label>
                <input type="text" name="subject" class="subject-input" value="<​?php echo htmlspecialchars($draft['email_subject'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required placeholder="Defina um assunto atraente...">
            </div>

            <div class="subject-group">
                <label class="subject-label"><i class="fas fa-eye"></i> Conteúdo (Modo Leitura)</label>

                <div class="tabs">
                    <button type="button" class="tab-btn active" data-tab="preview">Preview</button>
                    <button type="button" class="tab-btn" data-tab="html">HTML (editar)</button>
                </div>

                <div id="tab-preview" class="preview-area">
                    <?php echo safe_preview_html($draft['email_content']); ?>
                </div>

                <div id="tab-html" style="display:none;">
                    <textarea name="content" id="editor" class="content-area" required><?php echo escape_for_textarea($draft['email_content']); ?></textarea>
                </div>
            </div>

            <div class="action-bar">
                <button type="button" class="btn-discard" onclick="if(confirm('Tem certeza? Isso não pode ser desfeito.')) window.location.href='?delete=<?php echo (int)$draft['id']; ?>'">
                    <i class="fas fa-trash"></i> Descartar
                </button>

                <button type="submit" class="btn-forward">
                    <span>Enviar para Central de E-mails</span>
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </form>
        <?php else: ?>
            <div class="video-card glass-card" style="text-align: center; padding: 60px;">
                <i class="fas fa-check-circle" style="font-size: 4rem; color: #10b981; margin-bottom: 20px;"></i>
                <h3>Tudo limpo!</h3>
                <p style="color: rgba(255,255,255,0.6);">Não há rascunhos de newsletter pendentes para revisão no momento.</p>
                <a href="emails.php" class="cta-btn" style="margin-top: 20px; display: inline-block;">Ir para Central de E-mails</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Tabs Preview / HTML
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabPreview = document.getElementById('tab-preview');
    const tabHtml = document.getElementById('tab-html');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            tabBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const tab = btn.getAttribute('data-tab');
            if (tab === 'preview') {
                tabPreview.style.display = '';
                tabHtml.style.display = 'none';
            } else {
                tabPreview.style.display = 'none';
                tabHtml.style.display = '';
            }
        });
    });

    // Script para auto-ajustar a altura do textarea conforme o texto (somente quando visível)
    function autosizeTextarea(el) {
        if (!el) return;
        el.style.height = "auto";
        el.style.height = (el.scrollHeight + 10) + "px";
    }

    window.addEventListener('load', function() {
        const editor = document.getElementById('editor');
        autosizeTextarea(editor);

        if (editor) {
            editor.addEventListener("input", function() {
                autosizeTextarea(editor);
            }, false);
        }
    });
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>