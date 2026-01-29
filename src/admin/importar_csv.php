<?php
// admin/importar.php
require 'auth.php';
verificarAdmin(); // Apenas admin pode acessar
require '../config.php';
require 'logger.php';
require '../vendor/autoload.php';

use Smalot\PdfParser\Parser;

// Configurações de Execução
set_time_limit(0);
ini_set('memory_limit', '512M');

$pastaImportacao = '../importacao/';
$msg = "";
$logProcessamento = [];

// --- FUNÇÕES AUXILIARES DE DATA E TEXTO ---

function limparTextoPDF($texto)
{
    // Corrige codificação e remove lixo (Igual ao Importador Inteligente)
    $substituicoes = ['â€¢' => '•', 'â€¢' => '•', 'Ã©' => 'é', 'Ã¡' => 'á', 'Ã£' => 'ã', 'Ã³' => 'ó', 'Ãº' => 'ú', 'Ã' => 'Á', 'ç' => 'c', 'º' => 'o', '°' => 'o'];
    $texto = strtr($texto, $substituicoes);
    if (!mb_check_encoding($texto, 'UTF-8'))
        $texto = mb_convert_encoding($texto, 'UTF-8', 'ISO-8859-1');
    return preg_replace('/\s+/', ' ', $texto);
}

function buscarDataNoTexto($texto)
{
    $meses = [
        'janeiro' => '01',
        'fevereiro' => '02',
        'março' => '03',
        'marco' => '03',
        'abril' => '04',
        'maio' => '05',
        'junho' => '06',
        'julho' => '07',
        'agosto' => '08',
        'setembro' => '09',
        'outubro' => '10',
        'novembro' => '11',
        'dezembro' => '12'
    ];

    // 1. Cabeçalho Castro (Prioridade Alta)
    if (preg_match('/CASTRO\s*[,.]?\s*(\d{1,2})\s+(?:de|d.|d\W)\s+([a-zç]+)\s+(?:de|d.|d\W)\s+(\d{4})/iu', $texto, $matches)) {
        $mesNome = mb_strtolower($matches[2], 'UTF-8');
        foreach ($meses as $nome => $num) {
            if (stripos($nome, $mesNome) !== false || stripos($mesNome, substr($nome, 0, 3)) !== false) {
                return "{$matches[3]}-$num-" . str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            }
        }
    }
    // 2. Genérico Extenso
    if (preg_match('/(\d{1,2})\s+(?:de|d\W)\s+([a-zç]+)\s+(?:de|d\W)\s+(\d{4})/iu', $texto, $matches)) {
        $mesNome = mb_strtolower($matches[2], 'UTF-8');
        if (isset($meses[$mesNome]))
            return "{$matches[3]}-{$meses[$mesNome]}-" . str_pad($matches[1], 2, '0', STR_PAD_LEFT);
    }
    // 3. Numérico (dd/mm/aaaa)
    if (preg_match('/(\d{1,2})[\/\.](\d{1,2})[\/\.](\d{4})/', $texto, $matches)) {
        return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
    }
    return null;
}

function converterDataBrParaIso($data)
{
    // Converte 31/12/2023 ou 31-12-2023 para 2023-12-31
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', trim($data), $parts)) {
        return $parts[3] . '-' . str_pad($parts[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($parts[1], 2, '0', STR_PAD_LEFT);
    }
    return $data;
}

// --- PROCESSAMENTO DO LOTE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['iniciar_importacao'])) {

    $arquivos = glob($pastaImportacao . '*.pdf');
    $parser = new Parser();
    $sucesso = 0;
    $erros = 0;
    $ignorados = 0;

    // 1. Processar CSV se enviado
    $dadosCSV = [];
    if (isset($_FILES['arquivo_csv']) && $_FILES['arquivo_csv']['error'] == 0) {
        $csvFile = $_FILES['arquivo_csv']['tmp_name'];

        // Detecção Automática de Delimitador (; ou ,)
        $delimitador = ';'; // Padrão
        $handleCheck = fopen($csvFile, "r");
        $primeiraLinha = fgets($handleCheck);
        fclose($handleCheck);
        if ($primeiraLinha && substr_count($primeiraLinha, ',') > substr_count($primeiraLinha, ';')) {
            $delimitador = ',';
        }

        $handle = fopen($csvFile, "r");
        if ($handle) {
            $ignorarHeader = isset($_POST['ignorar_header']);
            while (($linha = fgetcsv($handle, 1000, $delimitador)) !== FALSE) {
                if ($ignorarHeader) {
                    $ignorarHeader = false;
                    continue;
                }

                // Esperado: Coluna 0 = Edição, Coluna 1 = Data, Coluna 2 = NomeArquivo
                if (count($linha) >= 3) {
                    $nomeArq = trim($linha[2]);
                    $dadosCSV[$nomeArq] = [
                        'edicao' => trim($linha[0]),
                        'data' => converterDataBrParaIso(trim($linha[1]))
                    ];
                }
            }
            fclose($handle);
            $logProcessamento[] = "<span class='text-info fw-bold'>CSV Carregado (Delimitador: '$delimitador'): " . count($dadosCSV) . " registros.</span>";
        }
    }

    // 2. Iterar Arquivos
    foreach ($arquivos as $arquivoOrigem) {
        $nomeArquivo = basename($arquivoOrigem);
        $numeroEd = null;
        $dataPub = null;
        $origemDados = "";
        $textoExtraidoPreview = ""; // Para exibir no log se precisar

        // ESTRATÉGIA A: Dados via CSV
        if (isset($dadosCSV[$nomeArquivo])) {
            $numeroEd = $dadosCSV[$nomeArquivo]['edicao'];
            $dataPub = $dadosCSV[$nomeArquivo]['data'];
            $origemDados = "CSV";
        }

        // ESTRATÉGIA B: Tenta achar 3 a 5 dígitos no nome do arquivo
        // Ex: "doe_3315.pdf", "Edição 1234.pdf", "3315.pdf"
        elseif (preg_match('/(\d{3,5})/', $nomeArquivo, $matches)) {
            $numeroEd = $matches[1];
            $origemDados = "Nome ($numeroEd) + OCR";

            // Já que pegamos o número pelo nome, PRECISAMOS da data pelo conteúdo
            try {
                $pdfTemp = $parser->parseFile($arquivoOrigem);
                $paginas = $pdfTemp->getPages();
                if (isset($paginas[0])) {
                    $textoTemp = limparTextoPDF($paginas[0]->getText());
                    $textoExtraidoPreview = substr($textoTemp, 0, 100) . "..."; // Guarda um pedacinho pro log
                    $dataPub = buscarDataNoTexto($textoTemp);
                }
            } catch (Exception $e) {
                // Falha silenciosa na leitura prévia
                $textoExtraidoPreview = "Falha OCR";
            }
        }

        // --- EXECUÇÃO DA IMPORTAÇÃO ---
        if ($numeroEd && $dataPub) {

            // Prepara Pastas
            $ano = date('Y', strtotime($dataPub));
            $mes = date('m', strtotime($dataPub));

            // Validação de ano (Segurança)
            if ($ano < 1900 || $ano > 2100) {
                $erros++;
                $logProcessamento[] = "<span class='text-danger'>Erro ($nomeArquivo): Data inválida detectada ($dataPub).</span>";
                continue;
            }

            $pastaRelativa = "$ano/$mes/";
            $pastaAbsoluta = "../uploads/" . $pastaRelativa;

            if (!is_dir($pastaAbsoluta)) {
                mkdir($pastaAbsoluta, 0755, true);
                if (file_exists('../uploads/.htaccess'))
                    copy('../uploads/.htaccess', $pastaAbsoluta . '.htaccess');
            }

            // Move e Renomeia
            $novoNome = uniqid() . ".pdf";
            $caminhoDestino = $pastaAbsoluta . $novoNome;
            $caminhoBanco = $pastaRelativa . $novoNome;

            if (rename($arquivoOrigem, $caminhoDestino)) {

                // Extrai Texto Completo para Indexação (Se já não leu antes)
                $textoConteudo = "";
                try {
                    $pdf = $parser->parseFile($caminhoDestino);
                    $textoConteudo = $pdf->getText();
                } catch (Exception $e) {
                    $logProcessamento[] = "<span class='text-warning'>Aviso ($nomeArquivo): PDF com texto ilegível.</span>";
                }

                // Salva no Banco
                try {
                    $stmt = $pdo->prepare("INSERT INTO edicoes (numero_edicao, data_publicacao, arquivo_path, conteudo_indexado, criado_em) VALUES (?, ?, ?, ?, ?)");
                    $timestamp = $dataPub . " 12:00:00";
                    $stmt->execute([$numeroEd, $dataPub, $caminhoBanco, $textoConteudo, $timestamp]);

                    registrarLog($pdo, 'Importação em Lote', "Edição $numeroEd", "Origem: $origemDados | Arq: $nomeArquivo");
                    $sucesso++;

                    // Log detalhado com o cabeçalho (se disponível)
                    $detalheExtra = $textoExtraidoPreview ? "<br><small class='text-muted'>Cabeçalho: " . htmlspecialchars($textoExtraidoPreview) . "</small>" : "";

                    $logProcessamento[] = "<span class='text-success'>OK ($origemDados): $nomeArquivo -> <strong>Ed. $numeroEd</strong> em <strong>$dataPub</strong>$detalheExtra</span>";

                } catch (Exception $e) {
                    $erros++;
                    $logProcessamento[] = "<span class='text-danger'>Erro SQL ($nomeArquivo): " . $e->getMessage() . "</span>";
                }
            } else {
                $erros++;
                $logProcessamento[] = "<span class='text-danger'>Erro de Permissão: Não foi possível mover $nomeArquivo</span>";
            }

        } else {
            $ignorados++;
            $motivo = "Desconhecido";
            if (!$numeroEd)
                $motivo = "Número da edição não encontrado no nome nem CSV";
            elseif (!$dataPub)
                $motivo = "Número ($numeroEd) ok, mas DATA não encontrada no PDF";

            $logProcessamento[] = "<span class='text-warning'>Ignorado ($nomeArquivo): $motivo.</span>";
        }
    }

    $msg = "<div class='alert alert-info'>Lote finalizado. Sucessos: <b>$sucesso</b>. Erros: <b>$erros</b>. Ignorados: <b>$ignorados</b>.</div>";
}

// Listagem para preview
$arquivosNaFila = glob($pastaImportacao . '*.pdf');
$qtdArquivos = count($arquivosNaFila);
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Importação em Lote - DOECA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .log-box {
            max-height: 400px;
            overflow-y: auto;
            background: #212529;
            color: #fff;
            font-family: monospace;
            padding: 15px;
            border-radius: 5px;
            font-size: 0.85rem;
        }

        .log-box span {
            display: block;
            margin-bottom: 5px;
            border-bottom: 1px solid #333;
            padding-bottom: 2px;
        }

        .text-success {
            color: #2ecc71 !important;
        }

        .text-danger {
            color: #e74c3c !important;
        }

        .text-warning {
            color: #f1c40f !important;
        }

        .text-info {
            color: #3498db !important;
        }
    </style>
</head>

<body class="bg-light">

    <nav class="navbar navbar-dark bg-dark mb-4 px-3">
        <a class="navbar-brand fw-bold" href="index.php"><i class="fas fa-book-open"></i> DOECA</a>
        <span class="navbar-text text-white">Importador Automático</span>
        <a href="ferramentas.php" class="btn btn-outline-light btn-sm">Voltar</a>
    </nav>

    <div class="container">

        <div class="row">
            <div class="col-md-5 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-cogs"></i> Configuração</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-secondary small">
                            <strong><i class="fas fa-folder"></i> Pasta Origem:</strong>
                            <code>/doeca/importacao/</code><br>
                            Total de arquivos: <strong><?php echo $qtdArquivos; ?></strong>
                        </div>

                        <form method="POST" enctype="multipart/form-data"
                            onsubmit="return confirm('Iniciar importação? Isso pode demorar.');">

                            <div class="mb-3">
                                <label class="form-label fw-bold">1. CSV de Apoio (Opcional)</label>
                                <input type="file" name="arquivo_csv" class="form-control" accept=".csv">
                                <div class="form-text">
                                    Aceita separador <b>;</b> ou <b>,</b><br>
                                    Colunas: <code>Edicao; Data; NomeArquivo.pdf</code>
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="ignorar_header"
                                        id="ignorar_header" checked>
                                    <label class="form-check-label small" for="ignorar_header">Ignorar 1ª linha
                                        (cabeçalho)</label>
                                </div>
                            </div>

                            <hr>

                            <div class="mb-3">
                                <label class="form-label fw-bold">2. Modo Automático (OCR)</label>
                                <p class="small text-muted">
                                    Para arquivos fora do CSV, o sistema vai:
                                    <br>1. Buscar 3 a 5 números no nome do arquivo (ex: <code>doe_3315.pdf</code>).
                                    <br>2. Se achar, lerá o PDF para encontrar a Data.
                                </p>
                            </div>

                            <button type="submit" name="iniciar_importacao" class="btn btn-success w-100" <?php echo $qtdArquivos == 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-play"></i> Iniciar Importação
                            </button>
                            <div class="text-center py-5 text-muted">
                                <p>Ao incluir arquivos novos clique em recarregar.</p>
                                <a href="importar_csv.php" class="btn btn-primary w-100">Recarregar...</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <?php if (!empty($msg))
                    echo $msg; ?>

                <div class="card shadow bg-dark text-white">
                    <div
                        class="card-header border-bottom border-secondary d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-terminal"></i> Log de Execução</span>
                        <?php if (!empty($logProcessamento)): ?>
                            <span class="badge bg-secondary"><?php echo count($logProcessamento); ?> eventos</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <div class="log-box">
                            <?php if (empty($logProcessamento)): ?>
                                <span class="text-muted text-center py-5 d-block">Aguardando início do processo...</span>
                            <?php else: ?>
                                <?php foreach ($logProcessamento as $linha)
                                    echo $linha; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($qtdArquivos > 0): ?>
                    <div class="mt-3">
                        <small class="text-muted">Arquivos encontrados na pasta (Exemplo):</small>
                        <ul class="list-group list-group-flush small">
                            <?php
                            $limit = 0;
                            foreach ($arquivosNaFila as $arq) {
                                if ($limit++ >= 5)
                                    break;
                                echo "<li class='list-group-item bg-light'>" . basename($arq) . "</li>";
                            }
                            if ($qtdArquivos > 5)
                                echo "<li class='list-group-item bg-light text-muted'>... e mais " . ($qtdArquivos - 5) . "</li>";
                            ?>
                        </ul>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
    <footer class="text-center mt-5 py-4 text-muted">
        <small>©
            <?php echo date('Y'); ?> Adriano Lerner Biesek | Prefeitura Municipal de Castro (PR)<br>Feito com <i
                class="fa fa-heart text-danger"></i> para o serviço público.
        </small>
    </footer>
</body>

</html>