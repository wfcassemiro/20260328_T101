<?php
// Script de Diagnóstico de Ambiente PHP
// Este script verifica se a extensão cURL está instalada e ativa.
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico do Servidor</title>
    <style>
        body { font-family: sans-serif; margin: 40px; background-color: #f0f0f0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .success { border: 2px solid #28a745; background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; }
        .error { border: 2px solid #dc3545; background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; }
        h1 { color: #333; }
        code { background-color: #eee; padding: 2px 5px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Diagnóstico do Servidor para API Hotmart</h1>
        
        <?php if (function_exists('curl_init')): ?>
            <div class="success">
                <h2>Tudo Certo!</h2>
                <p>A extensão <strong>PHP cURL</strong> está instalada e ativa no seu servidor.</p>
                <p>Isso é uma boa notícia, mas significa que a causa do erro 502 é outra, possivelmente relacionada à configuração do Nginx ou PHP-FPM. O próximo passo seria verificar os logs de erro do servidor.</p>
            </div>
        <?php else: ?>
            <div class="error">
                <h2>Problema Encontrado!</h2>
                <p>A extensão <strong>PHP cURL</strong> <strong>NÃO</strong> está instalada ou habilitada neste servidor.</p>
                <p>O script não pode se comunicar com a API da Hotmart sem ela, o que causa o erro <code>502 Bad Gateway</code> instantâneo.</p>
                <hr>
                <h3>Como Resolver:</h3>
                <p>Entre em contato com o suporte técnico da sua empresa de hospedagem e envie a seguinte mensagem para eles:</p>
                <p><strong>"Olá, estou tentando executar um script PHP que se conecta a uma API externa e preciso que a extensão <code>php-curl</code> seja habilitada para o meu site v.translators101.com. Podem fazer isso para mim, por favor?"</strong></p>
                <p>Eles saberão exatamente o que fazer. Assim que eles confirmarem que a extensão foi ativada, a página de progresso dos alunos deverá funcionar.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>