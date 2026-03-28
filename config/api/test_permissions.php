<?php
/**
 * Script de teste de permissões de escrita.
 * Coloque este arquivo na pasta que você quer testar e acesse-o via navegador.
 */

$test_log_file = __DIR__ . '/permissions_test.log';
$timestamp = date('Y-m-d H:i:s');
$message = "[$timestamp] Teste de permissão de escrita realizado com sucesso.\n";

// Tenta escrever no arquivo. O '@' suprime avisos para que possamos controlar a mensagem de erro.
if (@file_put_contents($test_log_file, $message, FILE_APPEND) !== false) {
    echo "<h1>Teste de Permissão: <span style='color: green;'>SUCESSO!</span></h1>";
    echo "<p>O arquivo 'permissions_test.log' foi criado/atualizado em: <code>" . htmlspecialchars($test_log_file) . "</code></p>";
    echo "<p>Isso confirma que o diretório <code>" . htmlspecialchars(__DIR__) . "</code> tem permissões de escrita.</p>";
} else {
    echo "<h1>Teste de Permissão: <span style='color: red;'>FALHA!</span></h1>";
    echo "<p><strong>ERRO: Não foi possível escrever no arquivo.</strong></p>";
    echo "<p>Isso geralmente indica um problema de permissão de escrita na pasta <code>" . htmlspecialchars(__DIR__) . "</code>.</p>";
    echo "<p>Por favor, siga as instruções abaixo para verificar e ajustar as permissões.</p>";
    // Opcional: Para mais detalhes, você pode descomentar a linha abaixo (pode expor caminhos no servidor)
    // echo "<p>Detalhes do erro: " . error_get_last()['message'] . "</p>";
}
?>
