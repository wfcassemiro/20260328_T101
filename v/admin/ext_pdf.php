<?php
require 'vendor/autoload.php';

use Smalot\PdfParser\Parser;

session_start();

function extrairTextoPaginas($arquivoPdf, $paginaInicio, $paginaFim) {
    $parser = new Parser();
    $pdf = $parser->parseFile($arquivoPdf);
    $pages = $pdf->getPages();

    $texto = '';
    for ($i = $paginaInicio - 1; $i <= $paginaFim - 1 && $i < count($pages); $i++) {
        $texto .= $pages[$i]->getText() . "\n";
    }
    return $texto;
}

function heuristicaExtracao($texto, $nomeArea) {
    // Tenta extrair bloco da área
    $patternArea = '/' . preg_quote($nomeArea, '/') . '(.*?)(?:\n[A-Z][a-z]+.*|\z)/s';
    if (preg_match($patternArea, $texto, $matches)) {
        $textoArea = trim($matches[1]);
    } else {
        $textoArea = $texto;
    }

    $linhas = preg_split('/\r\n|\r|\n/', $textoArea);

    $termos = [];
    $termoAtual = null;

    foreach ($linhas as $linha) {
        $linha = trim($linha);
        if ($linha === '') continue;

        if (preg_match('/^[^\s]/', $linha)) {
            $termoAtual = $linha;
            if (!isset($termos[$termoAtual])) {
                $termos[$termoAtual] = [];
            }
        } else {
            if ($termoAtual !== null) {
                $termos[$termoAtual][] = $linha;
            }
        }
    }

    foreach ($termos as $termo => $definicoes) {
        if (empty($definicoes)) {
            $termos[$termo][] = '';
        }
    }

    return $termos;
}

function extrairComRegras($texto, $regras) {
    // $regras: array com chaves 'identifica_termo' e 'identifica_acepcao'
    // Cada regra é uma regex para identificar linhas de termo e acepção

    $linhas = preg_split('/\r\n|\r|\n/', $texto);

    $termos = [];
    $termoAtual = null;

    foreach ($linhas as $linha) {
        $linha = trim($linha);
        if ($linha === '') continue;

        if (preg_match($regras['identifica_termo'], $linha)) {
            $termoAtual = $linha;
            if (!isset($termos[$termoAtual])) {
                $termos[$termoAtual] = [];
            }
        } elseif ($termoAtual !== null && preg_match($regras['identifica_acepcao'], $linha)) {
            $termos[$termoAtual][] = $linha;
        }
    }

    foreach ($termos as $termo => $definicoes) {
        if (empty($definicoes)) {
            $termos[$termo][] = '';
        }
    }

    return $termos;
}

function gerarCsv($termos, $idiomaOrigem, $idiomaDestino, $nomeArea) {
    $nomeArquivo = strtolower(str_replace(' ', '_', $nomeArea)) . '-' . strtolower($idiomaOrigem) . '-' . strtolower($idiomaDestino) . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
    $output = fopen('php://output', 'w');

    // Cabeçalho
    fputcsv($output, ['Termo', 'Acepção', 'Idioma Origem', 'Idioma Destino']);

    foreach ($termos as $termo => $definicoes) {
        foreach ($definicoes as $definicao) {
            fputcsv($output, [$termo, $definicao, $idiomaOrigem, $idiomaDestino]);
        }
    }

    fclose($output);
    exit;
}

// Fluxo da aplicação

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step']) && $_POST['step'] === 'upload') {
        // Upload do PDF e parâmetros
        if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
            echo "Erro no upload do arquivo PDF.";
            exit;
        }

        $nomeArea = trim($_POST['nome_area']);
        $paginaInicio = intval($_POST['pagina_inicio']);
        $paginaFim = intval($_POST['pagina_fim']);
        $idiomaOrigem = trim($_POST['idioma_origem']);
        $idiomaDestino = trim($_POST['idioma_destino']);

        $tmpName = $_FILES['pdf']['tmp_name'];
        $nomeArquivo = $_FILES['pdf']['name'];

        // Salvar arquivo temporariamente
        $destino = __DIR__ . '/uploads/' . basename($nomeArquivo);
        if (!is_dir(__DIR__ . '/uploads')) {
            mkdir(__DIR__ . '/uploads', 0777, true);
        }
        move_uploaded_file($tmpName, $destino);

        $textoExtraido = extrairTextoPaginas($destino, $paginaInicio, $paginaFim);

        // Salvar dados na sessão para próximos passos
        $_SESSION['pdf_path'] = $destino;
        $_SESSION['nome_area'] = $nomeArea;
        $_SESSION['idioma_origem'] = $idiomaOrigem;
        $_SESSION['idioma_destino'] = $idiomaDestino;
        $_SESSION['texto_extraido'] = $textoExtraido;

        // Mostrar trecho para validação
        $linhas = preg_split('/\r\n|\r|\n/', $textoExtraido);
        $trecho = implode("\n", array_slice($linhas, 0, 30));

        echo "<h2>Trecho extraído das páginas $paginaInicio a $paginaFim</h2>";
        echo "<pre style='background:#eee;padding:10px;border:1px solid #ccc;max-height:400px;overflow:auto;'>".htmlspecialchars($trecho)."</pre>";

        echo "<form method='post'>";
        echo "<input type='hidden' name='step' value='confirmacao'>";
        echo "<p>A estrutura dos termos e acepções está clara e correta para extração automática?</p>";
        echo "<button type='submit' name='confirmacao' value='sim'>Sim</button> ";
        echo "<button type='submit' name='confirmacao' value='nao'>Não</button>";
        echo "</form>";

        exit;

    } elseif (isset($_POST['step']) && $_POST['step'] === 'confirmacao') {
        if ($_POST['confirmacao'] === 'sim') {
            // Extrair com heurística automática e gerar CSV
            $termos = heuristicaExtracao($_SESSION['texto_extraido'], $_SESSION['nome_area']);
            gerarCsv($termos, $_SESSION['idioma_origem'], $_SESSION['idioma_destino'], $_SESSION['nome_area']);
        } else {
            // Mostrar formulário para o usuário informar regras
            echo "<h2>Informe as regras para identificar termos e acepções</h2>";
            echo "<form method='post'>";
            echo "<input type='hidden' name='step' value='extrair_com_regras'>";
            echo "<p>Regex para identificar linhas de <b>termo</b> (ex: ^[A-Z].*): <br><input type='text' name='regex_termo' required style='width:100%;' value='^[A-Z].*'></p>";
            echo "<p>Regex para identificar linhas de <b>acepção</b> (ex: ^\s+.*): <br><input type='text' name='regex_acepcao' required style='width:100%;' value='^\s+.*'></p>";
            echo "<button type='submit'>Extrair e gerar CSV</button>";
            echo "</form>";
            exit;
        }
    } elseif (isset($_POST['step']) && $_POST['step'] === 'extrair_com_regras') {
        $regexTermo = $_POST['regex_termo'];
        $regexAcepcao = $_POST['regex_acepcao'];

        $regras = [
            'identifica_termo' => '/' . $regexTermo . '/',
            'identifica_acepcao' => '/' . $regexAcepcao . '/',
        ];

        $termos = extrairComRegras($_SESSION['texto_extraido'], $regras);
        gerarCsv($termos, $_SESSION['idioma_origem'], $_SESSION['idioma_destino'], $_SESSION['nome_area']);
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <title>Extração de Termos de PDF para CSV</title>
</head>
<body>
    <h1>Upload do PDF e Parâmetros</h1>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="step" value="upload" />
        <p>
            <label>Arquivo PDF: <input type="file" name="pdf" accept="application/pdf" required /></label>
        </p>
        <p>
            <label>Nome da Área: <input type="text" name="nome_area" required /></label>
        </p>
        <p>
            <label>Página Início: <input type="number" name="pagina_inicio" min="1" required /></label>
        </p>
        <p>
            <label>Página Final: <input type="number" name="pagina_fim" min="1" required /></label>
        </p>
        <p>
            <label>Idioma de Origem: <input type="text" name="idioma_origem" required /></label>
        </p>
        <p>
            <label>Idioma de Destino: <input type="text" name="idioma_destino" required /></label>
        </p>
        <button type="submit">Enviar</button>
    </form>
</body>
</html>