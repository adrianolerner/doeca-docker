<?php
// admin/importar.php
require 'auth.php';
verificarAdmin(); // Apenas admin pode acessar
require '../config.php';
require 'logger.php';
require '../vendor/autoload.php';

use Smalot\PdfParser\Parser;

// Aumenta o tempo de execução para processar muitos arquivos
set_time_limit(0);
ini_set('memory_limit', '512M'); // Aumenta memória para PDFs pesados

$pastaImportacao = '../importacao/';
$msg = "";
$logProcessamento = [];

// PROCESSAMENTO DO LOTE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['iniciar_importacao'])) {

    // Pega todos os arquivos .pdf da pasta
    $arquivos = glob($pastaImportacao . '*.pdf');
    $parser = new Parser();
    $sucesso = 0;
    $erros = 0;

    foreach ($arquivos as $arquivoOrigem) {
        $nomeArquivo = basename($arquivoOrigem);

        // Tenta extrair DATA e NÚMERO do nome do arquivo
        // Padrão esperado: YYYY-MM-DD__NUMERO.pdf (Ex: 2023-12-31__1050.pdf)
        // O separador é duplo underline "__" para evitar confusão
        if (preg_match('/^(\d{4}-\d{2}-\d{2})__(.+)\.pdf$/i', $nomeArquivo, $matches)) {
            $dataPub = $matches[1]; // 2023-12-31
            $numeroEd = str_replace('_', '/', $matches[2]); // 1050 (ou 1050_2023 vira 1050/2023 se quiser)

            // 1. Prepara diretório de destino (uploads/ANO/MES)
            $ano = date('Y', strtotime($dataPub));
            $mes = date('m', strtotime($dataPub));
            $pastaRelativa = "$ano/$mes/";
            $pastaAbsoluta = "../uploads/" . $pastaRelativa;

            if (!is_dir($pastaAbsoluta)) {
                mkdir($pastaAbsoluta, 0755, true);
                // Proteção .htaccess
                if (file_exists('../uploads/.htaccess')) {
                    copy('../uploads/.htaccess', $pastaAbsoluta . '.htaccess');
                }
            }

            // 2. Novo nome único para o sistema
            $novoNome = uniqid() . ".pdf";
            $caminhoDestino = $pastaAbsoluta . $novoNome;
            $caminhoBanco = $pastaRelativa . $novoNome;

            // 3. Move o arquivo e processa
            if (rename($arquivoOrigem, $caminhoDestino)) {

                // Extração de Texto (PDF Parser)
                $textoConteudo = "";
                try {
                    $pdf = $parser->parseFile($caminhoDestino);
                    $textoConteudo = $pdf->getText();
                } catch (Exception $e) {
                    $textoConteudo = ""; // Segue mesmo sem texto
                    $logProcessamento[] = "<span class='text-warning'>Aviso ($nomeArquivo): PDF ilegível para busca. Importado.</span>";
                }

                // Insere no Banco
                try {
                    $stmt = $pdo->prepare("INSERT INTO edicoes (numero_edicao, data_publicacao, arquivo_path, conteudo_indexado, criado_em) VALUES (?, ?, ?, ?, ?)");
                    // Usa a data do arquivo para 'criado_em' também, para manter histórico consistente no dashboard
                    $timestamp = $dataPub . " 12:00:00";
                    $stmt->execute([$numeroEd, $dataPub, $caminhoBanco, $textoConteudo, $timestamp]);

                    registrarLog($pdo, 'Importação em Lote', "Edição $numeroEd", "Arquivo original: $nomeArquivo");
                    $sucesso++;
                    $logProcessamento[] = "<span class='text-success'>OK: $nomeArquivo -> Edição $numeroEd ($dataPub)</span>";

                } catch (Exception $e) {
                    $erros++;
                    $logProcessamento[] = "<span class='text-danger'>Erro SQL ($nomeArquivo): " . $e->getMessage() . "</span>";
                }

            } else {
                $erros++;
                $logProcessamento[] = "<span class='text-danger'>Erro: Falha ao mover arquivo $nomeArquivo</span>";
            }

        } else {
            $erros++;
            $logProcessamento[] = "<span class='text-danger'>Ignorado ($nomeArquivo): Nome fora do padrão 'AAAA-MM-DD__NUMERO.pdf'</span>";
        }
    }

    $msg = "<div class='alert alert-info'>Processamento finalizado. Sucessos: <b>$sucesso</b>. Erros: <b>$erros</b>.</div>";
}

// LISTAGEM INICIAL (Para o usuário ver o que tem na pasta antes de rodar)
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
        }

        .log-box span {
            display: block;
            margin-bottom: 5px;
            border-bottom: 1px solid #333;
        }
    </style>
</head>

<body class="bg-light">

    <nav class="navbar navbar-dark bg-dark mb-4 px-3">
        <a class="navbar-brand fw-bold" href="index.php"><i class="fas fa-book-open"></i> DOECA</a>
        <span class="navbar-text text-white">Ferramenta de Importação de Legado</span>
        <a href="ferramentas.php" class="btn btn-outline-light btn-sm">Voltar</a>
    </nav>

    <div class="container">

        <div class="card shadow mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-boxes"></i> Importador de Acervo</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-secondary">
                    <h6><i class="fas fa-info-circle"></i> Instruções:</h6>
                    <ol>
                        <li>Acesse a pasta <code>/doeca/importacao/</code> no servidor.</li>
                        <li>Copie seus arquivos PDF antigos para lá.</li>
                        <li><strong>Regra Obrigatória:</strong> Renomeie os arquivos para o formato: <br>
                            <code>AAAA-MM-DD__NUMERO.pdf</code> (Ex: <code>2022-05-10__1240.pdf</code>).
                        </li>
                        <li>O sistema lerá a data e o número do nome do arquivo, fará o OCR e moverá para a pasta final.
                        </li>
                    </ol>
                </div>

                <hr>

                <h4 class="mb-3">Arquivos na fila: <span class="badge bg-primary"><?php echo $qtdArquivos; ?></span>
                </h4>

                <?php if ($qtdArquivos > 0): ?>

                    <div class="table-responsive mb-3" style="max-height: 200px; overflow-y: auto;">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Arquivo Encontrado</th>
                                    <th>Data Detectada</th>
                                    <th>Edição Detectada</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $previewLimit = 0;
                                foreach ($arquivosNaFila as $arq):
                                    if ($previewLimit++ > 50)
                                        break; // Mostra só os primeiros 50 no preview
                                    $nome = basename($arq);
                                    $valido = preg_match('/^(\d{4}-\d{2}-\d{2})__(.+)\.pdf$/i', $nome, $m);
                                    ?>
                                    <tr>
                                        <td><?php echo $nome; ?></td>
                                        <td><?php echo $valido ? date('d/m/Y', strtotime($m[1])) : '-'; ?></td>
                                        <td><?php echo $valido ? $m[2] : '-'; ?></td>
                                        <td>
                                            <?php if ($valido): ?>
                                                <span class="badge bg-success">Pronto</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Nome Inválido</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if ($qtdArquivos > 50)
                            echo "<p class='text-muted text-center'>... e mais " . ($qtdArquivos - 50) . " arquivos.</p>"; ?>
                    </div>

                    <form method="POST"
                        onsubmit="return confirm('Isso pode levar vários minutos dependendo da quantidade. Não feche a janela.');">
                        <button type="submit" name="iniciar_importacao" class="btn btn-success btn-lg w-100">
                            <i class="fas fa-play"></i> Iniciar Importação e Indexação
                        </button>
                    </form>

                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                        <h4>Tudo limpo!</h4>
                        <p>Não há arquivos na pasta de importação.</p>
                        <a href="importar.php" class="btn btn-primary">Recarregar...</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($msg)): ?>
            <?php echo $msg; ?>
            <div class="card shadow bg-dark text-white">
                <div class="card-header border-bottom border-secondary">Log de Processamento</div>
                <div class="card-body p-0">
                    <div class="log-box">
                        <?php foreach ($logProcessamento as $linha)
                            echo $linha; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
    <footer class="text-center mt-5 py-4 text-muted">
        <small>©
            <?php echo date('Y'); ?> Adriano Lerner Biesek | Prefeitura Municipal de Castro (PR)<br>Feito com <i
                class="fa fa-heart text-danger"></i> para o serviço público.
        </small>
    </footer>
</body>

</html>