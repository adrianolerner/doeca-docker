<?php
require 'auth.php';
verificarAdmin();
require '../config.php';
require 'logger.php';
require '../vendor/autoload.php';

use Smalot\PdfParser\Parser;

// Configurações
set_time_limit(0);
ini_set('memory_limit', '512M');
$pastaImportacao = '../importacao/';
$limitePorPagina = 15;

// --- FUNÇÃO DE LIMPEZA ---
function limparTextoPDF($texto)
{
    // 1. Corrige a codificação "mojibake" específica
    $substituicoes = [
        'â€¢' => '•',
        'â€¢' => '•',
        'Ã©' => 'é',
        'Ã¡' => 'á',
        'Ã£' => 'ã',
        'Ã³' => 'ó',
        'Ãº' => 'ú',
        'Ã' => 'Á',
        'ç' => 'c',
        'º' => 'o',
        '°' => 'o'
    ];
    $texto = strtr($texto, $substituicoes);

    // 2. Corrige UTF-8 genérico
    if (!mb_check_encoding($texto, 'UTF-8')) {
        $texto = mb_convert_encoding($texto, 'UTF-8', 'ISO-8859-1');
    }

    // 3. Remove quebras de linha e excesso de espaço
    $texto = preg_replace('/\s+/', ' ', $texto);

    return $texto;
}

// --- FUNÇÃO DATA ---
function converterDataExtenso($texto)
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

    // PRIORIDADE 0: "CASTRO, DD DE MES DE AAAA"
    if (preg_match('/CASTRO\s*[,.]?\s*(\d{1,2})\s+(?:de|d.|d\W)\s+([a-zç]+)\s+(?:de|d.|d\W)\s+(\d{4})/iu', $texto, $matches)) {
        $dia = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $mesNome = mb_strtolower($matches[2], 'UTF-8');
        $ano = $matches[3];
        foreach ($meses as $nome => $num) {
            if (stripos($nome, $mesNome) !== false || stripos($mesNome, substr($nome, 0, 3)) !== false) {
                return "$ano-$num-$dia";
            }
        }
    }

    // PRIORIDADE 1: Genérico
    if (preg_match('/(\d{1,2})\s+(?:de|d\W)\s+([a-zç]+)\s+(?:de|d\W)\s+(\d{4})/iu', $texto, $matches)) {
        $dia = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $mesNome = mb_strtolower($matches[2], 'UTF-8');
        $ano = $matches[3];
        if (isset($meses[$mesNome]))
            return "$ano-{$meses[$mesNome]}-$dia";
    }

    // PRIORIDADE 2: Curto
    if (preg_match('/(\d{1,2})[\/\.](\d{1,2})[\/\.](\d{4})/', $texto, $matches)) {
        return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
    }

    return '';
}

// --- FUNÇÃO EDIÇÃO (BLINDADA CONTRA LEIS/ANOS) ---
function detectarEdicao($texto, $anoEncontrado = null)
{

    // 1. LIMPEZA PROFUNDA (Remove Leis inclusive com ano "123/2013")
    // O regex [\d.\/-]+ consome o número da lei E o ano dela, impedindo falsos positivos
    $textoLimpo = preg_replace('/(?:Lei|Decreto|Portaria|Resolu[cç][ãa]o)\s+n?[oº°]?\s*[\d.\/-]+/iu', ' DOC_LEGAL ', $texto);

    // 2. ESTRATÉGIA "ÂNCORA NO ANO" (Ex: 2026 • 3315)
    if (preg_match('/(\d{4})\s*[•\-\|]\s*(\d{3,6})\s*(?:[•\-\|]|$)/u', $textoLimpo, $matches)) {
        $anoRegex = $matches[1];
        $numero = $matches[2];

        // Se o ano bater com o ano da data (com margem de 1 ano)
        if (!$anoEncontrado || abs($anoRegex - $anoEncontrado) <= 1) {
            if ($numero != $anoRegex) {
                return $numero;
            }
        }
    }

    // 3. ESTRATÉGIA "BOLINHAS ISOLADAS" (Estrita)
    // Usa apenas [•\-\|] como separador. Não usa \W (para evitar pegar barras de datas)
    if (preg_match_all('/(?:^|[•\-\|])\s*(\d{3,6})\s*(?:$|[•\-\|])/u', $textoLimpo, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[1] as $index => $numMatch) {

            $numero = $numMatch[0]; // O numero capturado
            $offset = $matches[0][$index][1]; // A posição

            // Se for igual ao ano encontrado, ignora
            if ($anoEncontrado && $numero == $anoEncontrado)
                continue;

            // TRAVA DE SEGURANÇA DE ANO: Se parecer um ano (1990-2050), ignora
            // (Assumindo que edições não coincidem exatamente com anos recentes, ou se coincidir, cairia na regra 2)
            if ($numero >= 1990 && $numero <= 2050)
                continue;

            // Verifica "PÁGINAS" logo a frente
            $textoApos = substr($textoLimpo, $offset + strlen($matches[0][$index][0]), 15);
            if (stripos($textoApos, 'PÁG') !== false || stripos($textoApos, 'PAG') !== false) {
                continue;
            }

            return $numero;
        }
    }

    // 4. ESTRATÉGIA RÓTULOS (Último recurso: Edição 123)
    // A limpeza do passo 1 já removeu "Lei Nº", então aqui é seguro procurar "Nº"
    if (preg_match('/(?:Edi[cç][ãa]o|Publica[cç][ãa]o|Di[áa]rio|N[oº°])\s*\.?\s*(\d{1,6})/iu', $textoLimpo, $matches)) {
        return $matches[1];
    }

    return "";
}

// --- PROCESSAMENTO DO FORMULÁRIO ---
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['importar_selecionados'])) {
    $sucesso = 0;

    if (isset($_POST['arquivos']) && is_array($_POST['arquivos'])) {
        foreach ($_POST['arquivos'] as $nomeArquivoCodificado => $dados) {

            if (!isset($dados['selecionado']))
                continue;

            $nomeOriginal = base64_decode($nomeArquivoCodificado);
            $caminhoOrigem = $pastaImportacao . $nomeOriginal;

            $numeroEdicao = trim($dados['numero']);
            $dataPublicacao = trim($dados['data']);
            $textoExtraido = $dados['texto_hidden'] ?? '';

            if (file_exists($caminhoOrigem) && !empty($numeroEdicao) && !empty($dataPublicacao)) {

                $ano = date('Y', strtotime($dataPublicacao));
                $mes = date('m', strtotime($dataPublicacao));
                $pastaRelativa = "$ano/$mes/";
                $pastaAbsoluta = "../uploads/" . $pastaRelativa;

                if (!is_dir($pastaAbsoluta)) {
                    mkdir($pastaAbsoluta, 0755, true);
                    if (file_exists('../uploads/.htaccess'))
                        copy('../uploads/.htaccess', $pastaAbsoluta . '.htaccess');
                }

                $novoNome = uniqid() . ".pdf";
                $caminhoDestino = $pastaAbsoluta . $novoNome;
                $caminhoBanco = $pastaRelativa . $novoNome;

                if (rename($caminhoOrigem, $caminhoDestino)) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO edicoes (numero_edicao, data_publicacao, arquivo_path, conteudo_indexado, criado_em) VALUES (?, ?, ?, ?, ?)");
                        $timestamp = $dataPublicacao . " 12:00:00";
                        $stmt->execute([$numeroEdicao, $dataPublicacao, $caminhoBanco, $textoExtraido, $timestamp]);
                        $sucesso++;
                        registrarLog($pdo, 'Importação Inteligente', "Edição $numeroEdicao", "Arquivo: $nomeOriginal");
                    } catch (Exception $e) {
                        $msg .= "<div class='alert alert-danger'>Erro SQL ($nomeOriginal): " . $e->getMessage() . "</div>";
                    }
                } else {
                    $msg .= "<div class='alert alert-danger'>Erro ao mover: $nomeOriginal</div>";
                }
            }
        }
        if ($sucesso > 0) {
            $msg .= "<div class='alert alert-success'>$sucesso arquivos importados! Recarregando...</div>";
            echo "<meta http-equiv='refresh' content='2'>";
        }
    }
}

// --- LEITURA DO LOTE ---
$todosArquivos = glob($pastaImportacao . '*.pdf');
$qtdTotal = count($todosArquivos);
$loteAtual = array_slice($todosArquivos, 0, $limitePorPagina);
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Importador Inteligente - DOECA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .pdf-preview-text {
            font-size: 0.75rem;
            color: #555;
            background: #fff;
            padding: 5px;
            border: 1px solid #ddd;
            max-height: 80px;
            overflow-y: auto;
            white-space: pre-wrap;
        }

        .highlight {
            background-color: #fff3cd;
            font-weight: bold;
        }
    </style>
</head>

<body class="bg-light">

    <nav class="navbar navbar-dark bg-dark mb-4 px-3">
        <a class="navbar-brand fw-bold" href="index.php"><i class="fas fa-book-open"></i> DOECA</a>
        <span class="navbar-text text-white">Importador Inteligente</span>
        <a href="ferramentas.php" class="btn btn-outline-light btn-sm">Voltar</a>
    </nav>

    <div class="container-fluid px-4">

        <?php echo $msg; ?>

        <div class="card shadow mb-5">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-robot"></i> Processamento Assistido</h5>
                <span class="badge bg-light text-dark">Fila: <?php echo $qtdTotal; ?></span>
            </div>
            <div class="card-body">

                <div class="alert alert-info py-2">
                    <small><i class="fas fa-info-circle"></i> O sistema remove Leis (Ex: 2628/2013) e busca por:
                        <strong>ANO • NÚMERO</strong>. Confira antes de salvar.</small>
                </div>

                <?php if (count($loteAtual) > 0): ?>
                    <form method="POST">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th width="3%" class="text-center"><input type="checkbox" id="selectAll" checked>
                                        </th>
                                        <th width="20%">Arquivo</th>
                                        <th width="12%">Edição (Sugerida)</th>
                                        <th width="15%">Data (Sugerida)</th>
                                        <th width="50%">Cabeçalho Detectado (Texto)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $parser = new Parser();

                                    foreach ($loteAtual as $caminhoArquivo):
                                        $nomeArquivo = basename($caminhoArquivo);
                                        $idSafe = base64_encode($nomeArquivo);

                                        $texto = "";
                                        try {
                                            $pdf = $parser->parseFile($caminhoArquivo);
                                            $pages = $pdf->getPages();
                                            if (isset($pages[0])) {
                                                // CHAMADA DA LIMPEZA
                                                $texto = limparTextoPDF($pages[0]->getText());
                                            }
                                        } catch (Exception $e) {
                                            $texto = "Erro leitura.";
                                        }

                                        // LÓGICA DE DETECÇÃO
                                        $sugestaoData = converterDataExtenso($texto);
                                        $anoEncontrado = $sugestaoData ? date('Y', strtotime($sugestaoData)) : null;
                                        $sugestaoEdicao = detectarEdicao($texto, $anoEncontrado);

                                        $classeLinha = ($sugestaoData && $sugestaoEdicao) ? 'table-success' : 'table-warning';
                                        ?>
                                        <tr class="<?php echo $classeLinha; ?>">
                                            <td class="text-center">
                                                <input type="checkbox" name="arquivos[<?php echo $idSafe; ?>][selecionado]"
                                                    class="row-check" checked>
                                                <input type="hidden" name="arquivos[<?php echo $idSafe; ?>][texto_hidden]"
                                                    value="<?php echo htmlspecialchars($texto); ?>">
                                            </td>
                                            <td>
                                                <a href="../importacao/<?php echo $nomeArquivo; ?>" target="_blank"
                                                    class="text-decoration-none small text-truncate d-block"
                                                    style="max-width: 200px;">
                                                    <i class="fas fa-file-pdf text-danger"></i> <?php echo $nomeArquivo; ?>
                                                </a>
                                            </td>
                                            <td>
                                                <input type="text" name="arquivos[<?php echo $idSafe; ?>][numero]"
                                                    class="form-control form-control-sm fw-bold"
                                                    value="<?php echo $sugestaoEdicao; ?>">
                                            </td>
                                            <td>
                                                <input type="date" name="arquivos[<?php echo $idSafe; ?>][data]"
                                                    class="form-control form-control-sm" value="<?php echo $sugestaoData; ?>">
                                            </td>
                                            <td>
                                                <div class="pdf-preview-text">
                                                    <?php echo htmlspecialchars(substr($texto, 0, 350)); ?>...
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-3 border-top pt-3">
                            <button type="button" class="btn btn-outline-secondary me-md-2" onclick="location.reload();">
                                <i class="fas fa-sync"></i> Pular / Recarregar
                            </button>
                            <button type="submit" name="importar_selecionados" class="btn btn-success btn-lg">
                                <i class="fas fa-check-double"></i> Confirmar e Importar
                            </button>
                        </div>
                    </form>

                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                        <h4>Tudo limpo!</h4>
                        <p>Não há arquivos na pasta de importação.</p>
                        <a href="importar_inteligente.php" class="btn btn-primary">Recarregar...</a>
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
    <script>
        document.getElementById('selectAll').addEventListener('change', function () {
            var checkboxes = document.querySelectorAll('.row-check');
            for (var checkbox of checkboxes) { checkbox.checked = this.checked; }
        });
    </script>
</body>

</html>