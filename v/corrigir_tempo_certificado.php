<?php
/**
 * CORREÇÃO TEMPORÁRIA - TEMPO MÍNIMO PARA CERTIFICADO
 * 
 * Este arquivo ajusta o tempo mínimo necessário para gerar certificado
 * de 85% da palestra para apenas 1 minuto (para fins de teste)
 * 
 * IMPORTANTE: Após os testes, restaurar para 85% da duração real
 */

// Fazer backup do arquivo original
$original_file = 'generate_certificate.php';
$backup_file = 'generate_certificate.backup.' . date('Y-m-d-H-i-s') . '.php';

if (file_exists($original_file)) {
    copy($original_file, $backup_file);
    echo "✅ Backup criado: $backup_file\n";
} else {
    echo "❌ Arquivo original não encontrado: $original_file\n";
    exit;
}

// Ler o conteúdo atual
$content = file_get_contents($original_file);

// Fazer as correções necessárias
$corrections = [
    // Correção 1: Ajustar o cálculo do tempo necessário
    [
        'search' => '$required_time = ($lecture['duration_minutes'] ?? 60) * 60 * 0.1; // 85%',
        'replace' => '$required_time = 60; // TESTE: 1 minuto fixo (era 85% da duração)'
    ],

    // Correção 2: Atualizar a mensagem de erro
    [
        'search' => ''message' => 'Você precisa assistir pelo menos 85% da palestra para gerar o certificado'',
        'replace' => ''message' => 'MODO TESTE: Você precisa assistir pelo menos 1 minuto da palestra para gerar o certificado''
    ],

    // Correção 3: Adicionar log específico para modo teste
    [
        'search' => 'logDebug("Verificação de tempo", [',
        'replace' => 'logDebug("MODO TESTE - Verificação de tempo (1 min necessário)", ['
    ]
];

$modified = false;
foreach ($corrections as $correction) {
    if (strpos($content, $correction['search']) !== false) {
        $content = str_replace($correction['search'], $correction['replace'], $content);
        $modified = true;
        echo "✅ Correção aplicada: " . substr($correction['search'], 0, 50) . "...\n";
    } else {
        echo "⚠️  Texto não encontrado: " . substr($correction['search'], 0, 50) . "...\n";
    }
}

if ($modified) {
    // Salvar o arquivo corrigido
    file_put_contents($original_file, $content);
    echo "\n✅ Arquivo corrigido com sucesso!\n";
    echo "\n📋 RESUMO DAS ALTERAÇÕES:\n";
    echo "- Tempo necessário: 85% da palestra → 1 minuto fixo\n";
    echo "- Mensagem atualizada para indicar modo teste\n";
    echo "- Logs específicos para identificar modo teste\n";
    echo "\n⚠️  LEMBRETE: Após os testes, restaurar usando:\n";
    echo "   cp $backup_file $original_file\n";
} else {
    echo "\n❌ Nenhuma correção foi aplicada. Verifique o arquivo.\n";
}

echo "\n🔧 Para restaurar o arquivo original após os testes:\n";
echo "<?php copy('$backup_file', '$original_file'); echo 'Arquivo restaurado!'; ?>\n";
?>