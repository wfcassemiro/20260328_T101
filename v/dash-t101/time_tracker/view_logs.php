<?php
/**
 * Visualizador de Logs - Time Tracker Debug
 * Acesse este arquivo para ver os logs em tempo real
 */

$logFile = '/tmp/time_tracker_debug.log';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="3">
    <title>Time Tracker - Logs de Debug</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
        }
        .header {
            background: #252526;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            border-left: 4px solid #007acc;
        }
        .header h1 {
            color: #007acc;
            font-size: 24px;
            margin-bottom: 10px;
        }
        .header p {
            color: #858585;
            font-size: 14px;
        }
        .controls {
            background: #252526;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .btn {
            background: #0e639c;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover { background: #1177bb; }
        .btn-danger {
            background: #c5371a;
        }
        .btn-danger:hover { background: #d9534f; }
        .log-container {
            background: #1e1e1e;
            border: 1px solid #3e3e42;
            border-radius: 5px;
            padding: 20px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .log-entry {
            margin-bottom: 15px;
            padding: 10px;
            background: #252526;
            border-radius: 3px;
            border-left: 3px solid #858585;
        }
        .log-entry.error {
            border-left-color: #f48771;
            background: #2d1e1e;
        }
        .log-entry.success {
            border-left-color: #89d185;
            background: #1e2d1e;
        }
        .log-timestamp {
            color: #569cd6;
            font-weight: bold;
        }
        .log-message {
            color: #d4d4d4;
            margin: 5px 0;
        }
        .log-data {
            background: #1e1e1e;
            padding: 10px;
            border-radius: 3px;
            margin-top: 10px;
            color: #ce9178;
            white-space: pre-wrap;
            font-size: 12px;
        }
        .separator {
            color: #3e3e42;
            margin: 10px 0;
        }
        .no-logs {
            text-align: center;
            color: #858585;
            padding: 50px;
            font-size: 18px;
        }
        .stats {
            background: #252526;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .stat-box {
            padding: 10px;
            background: #1e1e1e;
            border-radius: 3px;
            border-left: 3px solid #007acc;
        }
        .stat-label {
            color: #858585;
            font-size: 12px;
            margin-bottom: 5px;
        }
        .stat-value {
            color: #d4d4d4;
            font-size: 20px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔍 Time Tracker - Logs de Debug</h1>
        <p>Arquivo: <?php echo $logFile; ?></p>
        <p>Atualização automática a cada 3 segundos</p>
    </div>

    <div class="controls">
        <button class="btn" onclick="location.reload()">🔄 Atualizar Agora</button>
        <a href="?clear=1" class="btn btn-danger">🗑️ Limpar Logs</a>
        <a href="time-tracker.php" class="btn">◀ Voltar ao Time Tracker</a>
    </div>

    <?php
    // Limpar logs se solicitado
    if (isset($_GET['clear'])) {
        file_put_contents($logFile, '');
        echo '<div class="controls" style="background: #1e2d1e; border-left: 4px solid #89d185;">
                ✅ Logs limpos com sucesso!
              </div>';
        header('refresh:2; url=view_logs.php');
    }

    // Ler e exibir logs
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        
        if (empty($content)) {
            echo '<div class="no-logs">📝 Nenhum log registrado ainda.<br><br>
                  Use o Time Tracker para gerar logs.</div>';
        } else {
            // Estatísticas
            $lines = explode("\n", $content);
            $totalLines = count($lines);
            $errors = substr_count($content, '❌');
            $success = substr_count($content, '✅');
            $fileSize = filesize($logFile);
            
            echo '<div class="stats">';
            echo '<div class="stat-box">
                    <div class="stat-label">Total de Linhas</div>
                    <div class="stat-value">' . number_format($totalLines) . '</div>
                  </div>';
            echo '<div class="stat-box">
                    <div class="stat-label">Erros</div>
                    <div class="stat-value" style="color: #f48771;">' . $errors . '</div>
                  </div>';
            echo '<div class="stat-box">
                    <div class="stat-label">Sucessos</div>
                    <div class="stat-value" style="color: #89d185;">' . $success . '</div>
                  </div>';
            echo '<div class="stat-box">
                    <div class="stat-label">Tamanho do Arquivo</div>
                    <div class="stat-value">' . number_format($fileSize / 1024, 2) . ' KB</div>
                  </div>';
            echo '</div>';
            
            // Processar logs
            echo '<div class="log-container">';
            
            $entries = explode('--------------------------------------------------------------------------------', $content);
            $entries = array_reverse($entries); // Mais recentes primeiro
            
            foreach ($entries as $entry) {
                if (trim($entry) === '') continue;
                
                $class = 'log-entry';
                if (strpos($entry, '❌') !== false || strpos($entry, 'ERRO') !== false) {
                    $class .= ' error';
                } elseif (strpos($entry, '✅') !== false) {
                    $class .= ' success';
                }
                
                echo '<div class="' . $class . '">';
                echo '<pre class="log-message">' . htmlspecialchars($entry) . '</pre>';
                echo '</div>';
            }
            
            echo '</div>';
        }
    } else {
        echo '<div class="no-logs">
                ⚠️ Arquivo de log não encontrado.<br><br>
                Arquivo esperado: ' . $logFile . '<br><br>
                Execute a API debug primeiro para criar o arquivo.
              </div>';
    }
    ?>

    <div class="controls" style="margin-top: 20px;">
        <p style="color: #858585;">💡 Dica: Mantenha esta página aberta enquanto usa o Time Tracker</p>
    </div>
</body>
</html>
