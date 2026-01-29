<?php
require 'auth.php';
verificarAdmin();
require '../config.php';
require 'logger.php';

// Configurações para evitar timeout em bancos grandes
set_time_limit(0);
ini_set('memory_limit', '512M');

$msg = "";

// Lógica de Geração do Backup
if (isset($_POST['gerar_backup'])) {

    $nomeZip = 'backup_doeca_' . date('Y-m-d_H-i') . '.zip';
    $caminhoZip = sys_get_temp_dir() . '/' . $nomeZip;

    // Cria o arquivo ZIP
    $zip = new ZipArchive();
    if ($zip->open($caminhoZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        $msg = "<div class='alert alert-danger'>Erro: Não foi possível criar o arquivo ZIP temporário.</div>";
    } else {

        // 1. Busca todas as edições no banco
        $stmt = $pdo->query("SELECT * FROM edicoes ORDER BY data_publicacao DESC");
        $edicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Cria o conteúdo do CSV na memória
        // Cabeçalho: Edicao; Data; NomeArquivoRenomeado
        $csvConteudo = "Edicao;Data;NomeArquivo\n";

        $totalArquivos = 0;
        $totalSucesso = 0;

        foreach ($edicoes as $row) {
            $totalArquivos++;

            // Caminho real no servidor (ex: ../uploads/2026/01/hash.pdf)
            $caminhoReal = '../uploads/' . $row['arquivo_path'];

            if (file_exists($caminhoReal)) {

                // Formata a data e edição para o nome do arquivo
                $dataIso = $row['data_publicacao']; // YYYY-MM-DD
                $dataBr = date('d/m/Y', strtotime($dataIso));

                // Sanitiza o número da edição para nome de arquivo (troca barras por underline)
                // Ex: "123/2026" vira "123_2026"
                $edicaoSanitizada = str_replace(['/', '\\', ':'], '_', $row['numero_edicao']);

                // Define o novo nome padronizado: AAAA-MM-DD__EDICAO.pdf
                // Adicionamos o ID no final para garantir unicidade caso haja edições com mesmo número
                $novoNome = "{$dataIso}__{$edicaoSanitizada}__{$row['id']}.pdf";

                // Adiciona o arquivo ao ZIP com o novo nome
                $zip->addFile($caminhoReal, "arquivos/" . $novoNome);

                // Adiciona linha ao CSV
                $csvConteudo .= "{$row['numero_edicao']};{$dataBr};{$novoNome}\n";

                $totalSucesso++;
            }
        }

        // 3. Adiciona o CSV ao ZIP
        $zip->addFromString('index.csv', $csvConteudo);

        // 4. Adiciona um Leia-me
        $readme = "BACKUP DO SISTEMA DOECA\n";
        $readme .= "Gerado em: " . date('d/m/Y H:i') . "\n";
        $readme .= "Total de Edições: $totalArquivos\n";
        $readme .= "Arquivos encontrados: $totalSucesso\n\n";
        $readme .= "INSTRUÇÕES:\n";
        $readme .= "1. A pasta 'arquivos' contém os PDFs renomeados (Data__Edicao__ID.pdf).\n";
        $readme .= "2. O arquivo 'index.csv' contém os metadados para reimportação.\n";
        $zip->addFromString('LEIA_ME.txt', $readme);

        $zip->close();

        // Registrar Log
        registrarLog($pdo, 'Backup', 'Exportação Completa', "Gerado ZIP com $totalSucesso arquivos.");

        // Força o Download do ZIP
        if (file_exists($caminhoZip)) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($nomeZip) . '"');
            header('Content-Length: ' . filesize($caminhoZip));
            header('Pragma: no-cache');
            readfile($caminhoZip);

            // Remove o arquivo temporário após download
            unlink($caminhoZip);
            exit;
        } else {
            $msg = "<div class='alert alert-danger'>Erro ao gerar o arquivo para download.</div>";
        }
    }
}

// Estatísticas para exibir na tela antes de baixar
$totalEdicoes = $pdo->query("SELECT COUNT(*) FROM edicoes")->fetchColumn();
$tamanhoEstimado = $pdo->query("SELECT COUNT(*) * 0.5 FROM edicoes")->fetchColumn(); // Estima 500kb por arquivo (chute)
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Exportar Dados - DOECA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <nav class="navbar navbar-dark bg-dark mb-4 px-3">
        <a class="navbar-brand fw-bold" href="#"><i class="fas fa-book-open"></i> DOECA</a>
        <span class="navbar-text text-white">Exportação e Backup</span>
        <a href="ferramentas.php" class="btn btn-outline-light btn-sm">Voltar</a>
    </nav>

    <div class="container">

        <?php echo $msg; ?>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-file-export"></i> Exportar Acervo Completo</h5>
                    </div>
                    <div class="card-body text-center py-5">

                        <div class="mb-4">
                            <i class="fas fa-archive fa-5x text-secondary mb-3"></i>
                            <h3>Backup Padronizado</h3>
                            <p class="text-muted">
                                Esta ferramenta gera um arquivo <strong>.ZIP</strong> contendo todas as edições
                                cadastradas.
                            </p>
                        </div>

                        <div class="row text-start mb-4">
                            <div class="col-md-6 offset-md-3">
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Total de Edições
                                        <span class="badge bg-primary rounded-pill"><?php echo $totalEdicoes; ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Estrutura do Arquivo
                                        <span class="badge bg-secondary">ZIP</span>
                                    </li>
                                    <li class="list-group-item">
                                        <i class="fas fa-check text-success"></i> PDFs Renomeados (Data + Edição)
                                    </li>
                                    <li class="list-group-item">
                                        <i class="fas fa-check text-success"></i> Inclui Planilha
                                        <strong>index.csv</strong>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <form method="POST">
                            <button type="submit" name="gerar_backup" class="btn btn-success btn-lg px-5">
                                <i class="fas fa-download"></i> Gerar e Baixar Backup
                            </button>
                        </form>

                        <p class="mt-3 text-muted small">
                            <i class="fas fa-exclamation-triangle text-warning"></i>
                            O processo pode levar alguns minutos dependendo do tamanho do acervo.
                        </p>

                    </div>
                </div>
            </div>
        </div>
        <footer class="text-center mt-5 py-4 text-muted">
            <small>©
                <?php echo date('Y'); ?> Adriano Lerner Biesek | Prefeitura Municipal de Castro (PR)<br>Feito com <i
                    class="fa fa-heart text-danger"></i> para o serviço público.
            </small>
        </footer>
    </div>
</body>

</html>