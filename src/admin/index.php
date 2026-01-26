<?php
require 'auth.php';
require '../config.php';
require 'logger.php';
require '../vendor/autoload.php';
use Smalot\PdfParser\Parser;

$msg = "";

// 1. SEGURANÇA: Bloqueia exclusão se não for admin
if (isset($_GET['excluir'])) {
    if ($_SESSION['usuario_nivel'] !== 'admin') {
        $msg = "<div class='alert alert-danger'>Acesso negado. Apenas administradores podem excluir.</div>";
    } else {
        $id = (int) $_GET['excluir'];

        // CORREÇÃO AQUI: Adicionado 'numero_edicao' ao SELECT
        $stmt = $pdo->prepare("SELECT arquivo_path, numero_edicao FROM edicoes WHERE id = ?");
        $stmt->execute([$id]);
        $edicao = $stmt->fetch();

        if ($edicao) {
            $caminhoArquivo = "../uploads/" . $edicao['arquivo_path'];

            // Prepara a exclusão
            $stmtDelete = $pdo->prepare("DELETE FROM edicoes WHERE id = ?");

            // Registra o log ANTES de confirmar a exclusão visual, mas agora com o dado correto
            registrarLog($pdo, 'Exclusão', "Edição " . $edicao['numero_edicao'], "ID: $id excluído");

            if ($stmtDelete->execute([$id])) {
                // Remove o arquivo físico
                if (file_exists($caminhoArquivo) && is_file($caminhoArquivo)) {
                    unlink($caminhoArquivo);
                }
                $msg = "<div class='alert alert-success alert-dismissible fade show'>Edição excluída! <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            }
        }
    }
}

// UPLOAD (Permitido para Editores e Admins)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['pdf_file'])) {
    $numero = $_POST['numero'];
    $data = $_POST['data'];
    $ext = pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION);

    if (strtolower($ext) === 'pdf') {
        $novoNome = uniqid() . ".pdf";
        $ano = date('Y');
        $mes = date('m');
        $pastaRelativa = "$ano/$mes/";
        $pastaAbsoluta = "../uploads/" . $pastaRelativa;

        if (!is_dir($pastaAbsoluta)) {
            mkdir($pastaAbsoluta, 0755, true);
            if (file_exists('../uploads/.htaccess')) {
                copy('../uploads/.htaccess', $pastaAbsoluta . '.htaccess');
            }
        }

        $destino = $pastaAbsoluta . $novoNome;
        $caminhoParaBanco = $pastaRelativa . $novoNome;

        if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $destino)) {

            // --- NOVO: Extração de Texto ---
            $conteudoTexto = "";
            try {
                $parser = new Parser();
                $pdf = $parser->parseFile($destino);
                $conteudoTexto = $pdf->getText();
            } catch (Exception $e) {
                // Se der erro ao ler o PDF, segue o baile (salva vazio), mas loga o erro
                // Opcional: registrarLog($pdo, 'Erro OCR', "Edição $numero", $e->getMessage());
            }
            // -------------------------------

            // SQL atualizada para incluir conteudo_indexado
            $sql = "INSERT INTO edicoes (numero_edicao, data_publicacao, arquivo_path, conteudo_indexado) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$numero, $data, $caminhoParaBanco, $conteudoTexto]); // <--- Passando o texto aqui

            registrarLog($pdo, 'Publicação', "Edição $numero", "Arquivo: $novoNome");
            $msg = "<div class='alert alert-success'>Publicado e indexado com sucesso!</div>";
        }
    } else {
        $msg = "<div class='alert alert-warning'>Apenas PDF permitido.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Painel Admin - DOECA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 px-3">
        <a class="navbar-brand fw-bold" href="index.php"><i class="fas fa-book-open"></i> DOECA</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link active fw-bold text-warning" href="index.php">Publicações</a>
                </li>
                <?php if ($_SESSION['usuario_nivel'] === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="usuarios.php">Gerenciar Usuários</a></li>
                    <li class="nav-item"><a class="nav-link" href="historico.php">Auditoria</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                <?php if ($_SESSION['usuario_nivel'] === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="ferramentas.php">Ferramentas</a></li>
                <?php endif; ?>
            </ul>
            <span class="navbar-text me-3 text-white"><a href="perfil.php"
                    class="navbar-text me-3 text-white text-decoration-none" title="Alterar minha senha">
                    <i class="fas fa-user-circle"></i> Olá, <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?>
                </a></span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">Sair</a>
        </div>
    </nav>

    <div class="container-fluid px-4">
        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="card shadow border-0">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-plus-circle"></i> Nova Publicação
                    </div>
                    <div class="card-body">
                        <?php echo $msg; ?>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Número da Edição</label>
                                <input type="text" name="numero" class="form-control" placeholder="Ex: 1234/2023"
                                    required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Data da Publicação</label>
                                <input type="date" name="data" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Arquivo PDF</label>
                                <input type="file" name="pdf_file" class="form-control" accept=".pdf" required>
                            </div>
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-upload"></i> Publicar
                            </button>
                        </form>
                    </div>
                </div>

                <div class="mt-3 d-grid">
                    <a href="../index.php" target="_blank" class="btn btn-outline-secondary">
                        <i class="fas fa-external-link-alt"></i> Ver Site Principal
                    </a>
                </div>
            </div>

            <div class="col-md-9">
                <div class="card shadow border-0">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 text-secondary"><i class="fas fa-list"></i> Gerenciar Edições</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="tabelaAdmin" class="table table-striped table-hover" style="width:100%">
                                <thead class="table-light">
                                    <tr>
                                        <th>Edição</th>
                                        <th>Data</th>
                                        <th>Arquivo</th>
                                        <?php if ($_SESSION['usuario_nivel'] === 'admin'): ?>
                                            <th class="text-center" width="150">Ações</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $pdo->query("SELECT * FROM edicoes ORDER BY data_publicacao DESC, id DESC");
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $dataPub = date('d/m/Y', strtotime($row['data_publicacao']));

                                        // Usa ../arquivo.php para download seguro também no admin
                                        $linkPdf = "../arquivo.php?id={$row['id']}";

                                        echo "<tr>
                                                <td>{$row['numero_edicao']}</td>
                                                <td>{$dataPub}</td>
                                                <td><a href='{$linkPdf}' target='_blank' class='text-decoration-none'><i class='fas fa-file-pdf text-danger'></i> Ver PDF</a></td>";

                                        // 3. Só mostra os botões se for Admin
                                        if ($_SESSION['usuario_nivel'] === 'admin') {
                                            echo "<td class='text-center'>
                                                    <a href='editar.php?id={$row['id']}' class='btn btn-sm btn-warning' title='Editar'><i class='fas fa-edit'></i> Editar</a>
                                                    <a href='index.php?excluir={$row['id']}' class='btn btn-sm btn-danger' title='Excluir' onclick=\"return confirm('Confirmar exclusão?');\"><i class='fas fa-trash'></i> Apagar</a>
                                                  </td>";
                                        }

                                        echo "</tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function () {
            $('#tabelaAdmin').DataTable({
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json' },
                order: [[1, "desc"]]
            });
        });
    </script>
</body>

</html>