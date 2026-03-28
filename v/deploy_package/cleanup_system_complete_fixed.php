<?php
/**
 * SCRIPT DE LIMPEZA COMPLETO DO SISTEMA TRANSLATORS101.COM
 * Versao corrigida - sem problemas de aspas e caracteres especiais
 */

set_time_limit(300);
ini_set('memory_limit', '256M');

// Configuracoes
$BASE_DIR = __DIR__;
$BACKUP_DIR = $BASE_DIR . '/cleanup_backups/';
$DRY_RUN = isset($_GET['test']) ? true : false;

// Criar pasta de backup
if (!is_dir($BACKUP_DIR)) {
    mkdir($BACKUP_DIR, 0755, true);
}

// Lista completa de arquivos para exclusao organizados por categoria
$files_to_delete = array(
    
    'debug_e_setup' => array(
        'setup_glossary_system.php',
        'debug_glossarios_link.php',
        'debug_glossarios_simples.php', 
        'debug_download.php',
        'admin/test_simples.php',
        'admin/test_funcoes.php',
        'cleanup_basic.php',
        'cleanup_simple.php',
        'cleanup_system_fixed.php'
    ),
    
    'backups_e_old' => array(
        'js/video_watch_tracker.backup.2025-06-11-13-43-36.js',
        'js/video_watch_tracker.js.backup.2025-06-11-11-16-41',
        'js/video_watch_tracker.old',
        'js/video_watch_tracker.backup.2025-06-11-13-51-02.js',
        'encontrar_5_2_percent.php.backup.2025-06-11-11-16-41',
        'teste_forcado_ttf.php.backup.2025-06-11-11-16-41',
        'includes/header.old',
        'hotmart.old',
        'palestra.old',
        'index.old',
        'generate_certificate.old_3',
        'generate_certificate.old_4',
        'generate_certificate.old_2',
        'generate_certificate.old_1',
        'generate_certificate.old',
        'config/hotmart.old',
        'config/database.old',
        'login.old',
        'debug_hotmart_completo.old',
        'diagnostico_certificado.php.backup.2025-06-11-11-16-41',
        'view_certificate_files_old.php',
        'download_certificate_old.php',
        'hotmart_webhook.old',
        'diagnostico_fontes.php.backup.2025-06-11-11-16-41',
        'generate_certificate.backup.2025-06-11-13-30-24.php',
        'corrigir_certificado.php.backup.2025-06-11-11-16-41',
        'palestra.php.backup.2025-06-11-11-16-41',
        'dash-t101/index.old',
        'generate_certificate.backup.simple.2025-06-11-13-49-35.php',
        'diagnostico_qual_funcao.php.backup.2025-06-11-11-16-41',
        'videoteca.old',
        'diagnostico_certificado.old'
    ),
    
    'testes_e_debug' => array(
        'corrigir_tabela_certificates.php',
        'install_final.php',
        'conversor_template.php',
        'diagnostico_qual_funcao.php',
        'view_certificate_hostinger.php',
        'teste_forcado_ttf.php',
        'teste_sem_palestrante.php',
        'emergency_diagnosis.php',
        'restaurar_certificado.php',
        'mapeador_ultra_simples.php',
        'gerenciador_certificados.php',
        'encontrar_5_2_percent.php',
        'testar_sintaxe.php',
        'teste_sistema_hostinger.php',
        'emergency_certificates.php',
        'teste_certificado_corrigido.php',
        'diagnostico_fontes.php',
        'debug_hotmart_completo.php',
        'view_logs_atualizado.php',
        'migrar_banco.php',
        'corrigir_uuid_automatico.php',
        'fix_links_emergency.php',
        'process_panda_csv.php',
        'COORDENADAS_EXEMPLO.php',
        'debug_center.php',
        'debug_certificado.php',
        'teste_certificado_simples.php',
        'corrigir_tempo_certificado_v3.php',
        'test_titulo_longo.php',
        'testar_titulo_longo.php',
        'teste_final_hotmart.php',
        'corrigir_javascript.php',
        'verificacao_completa.php',
        'diagnostico_erro500.php',
        'certificate_logger.php',
        'corrigir_tempo_certificado_v2.php',
        'certificados_final.php',
        'import_lectures_from_panda_csv.php',
        'ext_pdf.php',
        'gerar_certificado_teste.php',
        'corrigir_sintaxe_linha164.php',
        'database/test_direct.php',
        'configurar_sistema.php',
        'diagnostico_certificado.php',
        'certificados_final_2.php',
        'migrar_banco_hostinger.php',
        'teste_certificado_final.php',
        'css/teste_sistema_hostinger.php',
        'funcao_alternativa_ttf.php',
        'configurar_hostinger.php',
        'debug_certificate_paths.php',
        'teste_certificado_direto.php',
        'EXEMPLO_IMPLEMENTACAO.php',
        'test_panda_integration.html',
        'teste_uuid.php',
        'teste_hotmart_api.php',
        'test_api_fix.php',
        'listar_certificados_fisicos.php',
        'verificar_certificado.php',
        'test_hotmart_api.php',
        'debug_real_data.php',
        'debug_certificado_completo.php',
        'analisar_palestra.php',
        'corrigir_certificado.php',
        'gerenciador_certificados_corrigido.php',
        'migrate_hotmart.php',
        'teste_conectividade_hotmart.php',
        'definir_senha.php',
        'corrigir_senha.php',
        'corrigir_linha164_simples.php',
        'generate_simple_certificate.php',
        'palestra_teste_js.php',
        'teste_certificado_melhorado.php',
        'debug_view.php',
        'correcao_completa_certificado.php',
        'corrigir_banco.php',
        'teste_conflitos.php',
        'test_titulo_longo.py'
    ),
    
    'logs_e_csvs' => array(
        'import_lectures.log',
        'panda_videos_formatted.csv',
        'process_panda_csv.log',
        'panda_9-6_formated.csv',
        'database_migration_video_progress.sql',
        'database_setup.sql',
        'panda_9-6.csv',
        'palestras_para_importar.csv',
        'pv_rootvideos (11).csv',
        'certificate_errors.log',
        'pv_rootvideos (13).csv'
    ),
    
    'markdown_e_docs' => array(
        'CERTIFICADO_SEM_PALESTRANTE.md',
        'EXEMPLOS_EMBED_PANDA_VIDEO.md',  
        'CORRECAO_ID_PLAYER.md',
        'README_FINAL_SEM_PALESTRANTE.md',
        'SISTEMA_FUNCIONANDO_PERFEITAMENTE.md',
        'IMPLEMENTACAO_FINALIZADA.md',
        'GUIA_IMPLEMENTACAO_TRACKING_VIDEO.md',
        'README_INSTALACAO.md',
        'README.md',
        'INSTRUCOES_PRODUCAO.md',
        'INSTRUCOES_CORRECAO_UUID.md',
        'README_SEM_CONFLITOS.md',
        'TITULO_LONGO_IMPLEMENTADO.md',
        'database/update_glossary_table.sql'
    ),
    
    'scripts_e_shell' => array(
        'instalar_hostinger.sh'
    ),
    
    'duplicatas' => array(
        'auth_check.php',
        'save_secure_progress.php', 
        'log_security_event.php',
        'config_database.php',
        'dash_database.php',
        'dash_functions.php'
    )
);

// Pastas para verificar se estao vazias
$folders_to_check = array(
    'generated_csv',
    'admin/uploads', 
    'dash_t101_php'
);

// Funcoes auxiliares
function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB');
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}

function addToZip($zip, $file_path, $archive_path) {
    if (file_exists($file_path)) {
        if (is_dir($file_path)) {
            $zip->addEmptyDir($archive_path);
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($file_path),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $relativePath = $archive_path . '/' . str_replace($file_path . '/', '', $file->getRealPath());
                    $zip->addFile($file->getRealPath(), $relativePath);
                }
            }
        } else {
            $zip->addFile($file_path, $archive_path);
        }
        return true;
    }
    return false;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpeza Completa do Sistema - Translators101</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        .category { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 8px; }
        .success { background: #d4edda; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; }
        .file-list { max-height: 200px; overflow-y: auto; background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-box { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; }
        .btn-success { background: #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Limpeza Completa do Sistema - Translators101.com</h1>
        
        <?php if ($DRY_RUN): ?>
            <div class="warning">
                <h2>MODO TESTE ATIVADO</h2>
                <p>Nenhum arquivo sera excluido. Apenas simulacao.</p>
                <p><a href="?run=1" class="btn btn-danger">EXECUTAR LIMPEZA REAL</a></p>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['run']) || $DRY_RUN): ?>
            
            <?php
            // Estatisticas iniciais
            $total_files = 0;
            $total_size = 0;
            $categories_stats = array();
            
            foreach ($files_to_delete as $category => $files) {
                $category_size = 0;
                $category_count = 0;
                
                foreach ($files as $file) {
                    $file_path = $BASE_DIR . '/' . $file;
                    if (file_exists($file_path)) {
                        $category_count++;
                        $size = is_dir($file_path) ? 0 : filesize($file_path);
                        $total_size += $size; // Corrigido para adicionar ao total_size
                        $category_size += $size; // Corrigido para adicionar ao category_size
                    }
                }
                
                $categories_stats[$category] = array(
                    'count' => $category_count,
                    'size' => $category_size
                );
                
                $total_files += $category_count;
            }
            ?>

            <div class="stats">
                <div class="stat-box">
                    <h3>Total de Arquivos</h3>
                    <div style="font-size: 2em;"><?php echo $total_files; ?></div>
                </div>
                <div class="stat-box">
                    <h3>Espaco a Liberar</h3>
                    <div style="font-size: 2em;"><?php echo formatBytes($total_size); ?></div>
                </div>
                <div class="stat-box">
                    <h3>Modo</h3>
                    <div style="font-size: 1.5em;"><?php echo $DRY_RUN ? 'TESTE' : 'REAL'; ?></div>
                </div>
            </div>

            <?php if (!$DRY_RUN): ?>
            
            <?php
            // Criar backup ZIP
            echo '<div class="info"><h2>Criando Backup...</h2>';
            
            $backup_filename = 'cleanup_backup_' . date('Y-m-d_H-i-s') . '.zip';
            $backup_path = $BACKUP_DIR . $backup_filename;
            
            $zip = new ZipArchive();
            if ($zip->open($backup_path, ZipArchive::CREATE) === TRUE) {
                
                $backup_count = 0;
                foreach ($files_to_delete as $category => $files) {
                    foreach ($files as $file) {
                        $file_path = $BASE_DIR . '/' . $file;
                        if (file_exists($file_path)) {
                            if (addToZip($zip, $file_path, $category . '/' . $file)) {
                                $backup_count++;
                            }
                        }
                    }
                }
                
                $zip->close();
                echo '<p>Backup criado: <strong>' . $backup_filename . '</strong></p>';
                echo '<p>Localizacao: <code>' . $backup_path . '</code></p>';
                echo '<p>Arquivos no backup: <strong>' . $backup_count . '</strong></p>';
                echo '<p>Tamanho do backup: <strong>' . formatBytes(filesize($backup_path)) . '</strong></p>';
                
            } else {
                echo '<p style="color:red;">ERRO ao criar backup ZIP!</p>';
                exit;
            }
            
            echo '</div>';
            ?>
            
            <?php endif; ?>

            <!-- Processamento por categoria -->
            <?php
            $deleted_files = 0;
            $deleted_size = 0;
            $errors = array();
            
            foreach ($files_to_delete as $category => $files) {
                echo '<div class="category">';
                echo '<h3>' . ucfirst(str_replace('_', ' ', $category)) . ' (' . $categories_stats[$category]['count'] . ' arquivos)</h3>';
                
                $category_deleted = 0;
                $category_errors = array();
                
                echo '<div class="file-list">';
                foreach ($files as $file) {
                    $file_path = $BASE_DIR . '/' . $file;
                    
                    if (file_exists($file_path)) {
                        $size = is_dir($file_path) ? 0 : filesize($file_path);
                        
                        if ($DRY_RUN) {
                            echo "SERIA EXCLUIDO: " . $file . " (" . formatBytes($size) . ")\n";
                            $category_deleted++;
                        } else {
                            if (is_dir($file_path)) {
                                if (rmdir($file_path)) {
                                    echo "PASTA EXCLUIDA: " . $file . "\n";
                                    $category_deleted++;
                                } else {
                                    echo "ERRO AO EXCLUIR PASTA: " . $file . "\n";
                                    $category_errors[] = $file;
                                }
                            } else {
                                if (unlink($file_path)) {
                                    echo "ARQUIVO EXCLUIDO: " . $file . " (" . formatBytes($size) . ")\n";
                                    $category_deleted++;
                                    $deleted_size += $size;
                                } else {
                                    echo "ERRO AO EXCLUIR: " . $file . "\n";
                                    $category_errors[] = $file;
                                }
                            }
                        }
                    } else {
                        echo "NAO EXISTE: " . $file . "\n";
                    }
                }
                echo '</div>';
                
                $deleted_files += $category_deleted;
                $errors = array_merge($errors, $category_errors);
                
                echo '<p><strong>Resultado:</strong> ' . $category_deleted . '/' . $categories_stats[$category]['count'] . ' processados</p>';
                echo '</div>';
            }
            ?>

            <!-- Verificar pastas vazias -->
            <div class="category">
                <h3>Verificando Pastas Vazias</h3>
                <div class="file-list">
                <?php
                foreach ($folders_to_check as $folder) {
                    $folder_path = $BASE_DIR . '/' . $folder;
                    if (is_dir($folder_path)) {
                        $files_in_folder = scandir($folder_path);
                        $files_in_folder = array_diff($files_in_folder, array('.', '..', '.htaccess'));
                        
                        if (empty($files_in_folder)) {
                            if ($DRY_RUN) {
                                echo "PASTA VAZIA SERIA EXCLUIDA: " . $folder . "\n";
                            } else {
                                if (rmdir($folder_path)) {
                                    echo "PASTA VAZIA EXCLUIDA: " . $folder . "\n";
                                } else {
                                    echo "ERRO AO EXCLUIR PASTA VAZIA: " . $folder . "\n";
                                }
                            }
                        } else {
                            echo "PASTA NAO VAZIA: " . $folder . " (" . count($files_in_folder) . " itens)\n";
                        }
                    } else {
                        echo "PASTA NAO EXISTE: " . $folder . "\n";
                    }
                }
                ?>
                </div>
            </div>

            <!-- Resumo final -->
            <div class="success">
                <h2>Resumo da Operacao</h2>
                <?php if (!$DRY_RUN): ?>
                    <p>Backup criado: <?php echo $backup_filename; ?></p>
                <?php endif; ?>
                <p>Arquivos processados: <?php echo $deleted_files; ?>/<?php echo $total_files; ?></p>
                <?php if (!$DRY_RUN): ?>
                    <p>Espaco liberado: <?php echo formatBytes($deleted_size); ?></p>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <p style="color:red;">Erros: <?php echo count($errors); ?> arquivos nao puderam ser excluidos</p>
                    <details>
                        <summary>Ver erros</summary>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
            </div>

            <?php if (!$DRY_RUN): ?>
                <div class="warning">
                    <h3>Importante</h3>
                    <p>1. Teste o site para garantir que tudo funciona</p>
                    <p>2. Se houver problemas, voce pode restaurar arquivos do backup</p>
                    <p>3. Mantenha o backup por pelo menos 30 dias</p>
                    <p>4. <strong>Exclua este script apos o uso!</strong></p>
                </div>
            <?php endif; ?>

        <?php else: ?>
            
            <!-- Tela inicial -->
            <div class="info">
                <h2>Previa da Limpeza Completa</h2>
                <p>Este script ira:</p>
                <ul>
                    <li>Criar backup ZIP de todos os arquivos antes da exclusao</li>
                    <li>Excluir arquivos desnecessarios organizados por categoria</li>
                    <li>Gerar relatorio detalhado do que foi feito</li>
                    <li>Permitir restauracao atraves do backup</li>
                </ul>
            </div>
            
            <div class="category">
                <h3>Categorias que serao processadas:</h3>
                <?php foreach ($files_to_delete as $category => $files): ?>
                    <p><strong><?php echo ucfirst(str_replace('_', ' ', $category)); ?>:</strong> <?php echo count($files); ?> arquivos</p>
                <?php endforeach; ?>
                <p><strong>Total estimado:</strong> ~<?php 
                $total_estimate = 0;
                foreach ($files_to_delete as $files) {
                    $total_estimate += count($files);
                }
                echo $total_estimate;
                ?> arquivos</p>
            </div>

            <div style="text-align: center; margin: 30px 0;">
                <a href="?test=1" class="btn">EXECUTAR TESTE (Simulacao)</a>
                <a href="?run=1" class="btn btn-danger">EXECUTAR LIMPEZA REAL</a>
            </div>
            
            <div class="warning">
                <h3>AVISO IMPORTANTE</h3>
                <p>Certifique-se de ter feito backup manual antes de executar a limpeza real!</p>
                <p>Esta versao completa processara mais arquivos que a versao basica.</p>
            </div>
            
        <?php endif; ?>
        
    </div>
</body>
</html>
