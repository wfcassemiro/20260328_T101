<?php
require_once 'auth_check.php';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload de Glossário - Admin</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background-color: #1a1a1a; 
            color: #ffffff; 
        }
        .container { max-width: 800px; margin: 0 auto; }
        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 30px; 
            padding-bottom: 20px; 
            border-bottom: 1px solid #333; 
        }
        .user-info { color: #ccc; }
        form { 
            background-color: #2a2a2a; 
            padding: 30px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
        }
        label { 
            display: block; 
            margin-top: 15px; 
            font-weight: bold; 
            color: #fff; 
        }
        input[type="text"], input[type="number"], input[type="file"] { 
            width: 100%; 
            padding: 12px; 
            margin-top: 8px; 
            background-color: #1a1a1a; 
            border: 1px solid #444; 
            border-radius: 4px; 
            color: #fff; 
            box-sizing: border-box;
        }
        input[type="file"] { padding: 8px; }
        button { 
            background-color: #8b5cf6; 
            color: white; 
            padding: 12px 24px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            margin-top: 20px; 
            font-size: 16px;
        }
        button:hover { background-color: #7c3aed; }
        .message { 
            padding: 15px; 
            margin: 15px 0; 
            border-radius: 4px; 
            background-color: #065f46; 
            border: 1px solid #047857; 
            color: #d1fae5; 
        }
        .error { 
            background-color: #7f1d1d; 
            border: 1px solid #991b1b; 
            color: #fecaca; 
        }
        .success { 
            background-color: #065f46; 
            border: 1px solid #047857; 
            color: #d1fae5; 
        }
        .action-btn {
            background-color: #059669; 
            color: white; 
            padding: 10px 20px;
            text-decoration: none; 
            border-radius: 4px; 
            display: inline-block;
            margin: 8px 5px 0 0;
        }
        .action-btn:hover { background-color: #047857; }
        .logout-btn {
            background-color: #dc2626; 
            color: white; 
            padding: 8px 16px;
            text-decoration: none; 
            border-radius: 4px;
        }
        .logout-btn:hover { background-color: #b91c1c; }
        small { color: #ccc; display: block; margin-top: 5px; }
        .admin-menu {
            background-color: #333;
            padding: 10px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        .admin-menu a {
            color: #fff;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }
        .admin-menu a:hover {
            background-color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📚 Upload de Glossário</h1>
            <div class="user-info">
                Logado como: <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong>
                <a href="/logout.php" class="logout-btn">Sair</a>
            </div>
        </div>

        <div class="admin-menu">
            <a href="/admin/index.php">Dashboard</a>
            <a href="/admin/glossary/upload_form.php">Upload Glossário</a>
            <a href="/admin/users.php">Gerenciar Usuários</a>
            <!-- Adicione outros links do menu de administração aqui -->
        </div>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message <?php echo isset($_SESSION['error']) ? 'error' : (isset($_SESSION['success']) ? 'success' : ''); ?>">
                <?php echo $_SESSION['message']; ?>
                <?php if (isset($_SESSION['success']) && $_SESSION['success']): ?>
                    <br><a href="upload_form.php" class="action-btn">⬆️ Carregar Outro Arquivo</a>
                <?php endif; ?>
            </div>
            <?php 
            unset($_SESSION['message']); 
            unset($_SESSION['error']);
            unset($_SESSION['success']);
            ?>
        <?php endif; ?>

        <form method="post" action="upload.php" enctype="multipart/form-data">
            <label for="area">Nome da área:</label>
            <input type="text" name="area" id="area" required placeholder="Ex: Medicina, Direito, Tecnologia">
            
            <label for="source_lang">Idioma de origem:</label>
            <input type="text" name="source_lang" id="source_lang" required placeholder="Ex: Português, Inglês, Espanhol">
            
            <label for="target_lang">Idioma de chegada:</label>
            <input type="text" name="target_lang" id="target_lang" required placeholder="Ex: Português, Inglês, Espanhol">
            
            <label for="file">Arquivo:</label>
            <input type="file" name="file" id="file" accept=".xlsx,.pdf,.docx,.csv" required>
            <small>Formatos aceitos: XLSX, PDF, DOCX, CSV (máx. 10MB)</small>
            
            <label for="pdf_start_page">Página inicial do glossário (apenas para PDF):</label>
            <input type="number" name="pdf_start_page" id="pdf_start_page" min="1" placeholder="Deixe em branco se não for PDF">
            <small>Especifique a partir de qual página o glossário começa no PDF</small>
            
            <button type="submit">📤 Enviar e Processar</button>
        </form>

        <div style="margin-top: 30px; padding: 20px; background-color: #2a2a2a; border-radius: 8px;">
            <h3>ℹ️ Como usar:</h3>
            <ol style="color: #ccc; line-height: 1.6;">
                <li>Preencha os metadados do glossário (área e idiomas)</li>
                <li>Faça upload do arquivo contendo os termos</li>
                <li>Para PDFs, especifique a página onde o glossário começa</li>
                <li>O CSV será gerado e baixado automaticamente</li>
                <li>Após o download, você pode carregar outro arquivo</li>
            </ol>
        </div>
    </div>
</body>
</html>

