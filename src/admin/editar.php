<?php
require 'auth.php';
require '../config.php';
require 'logger.php';

if ($_SESSION['usuario_nivel'] !== 'admin') {
    // Se não for admin, redireciona de volta para o index
    header("Location: index.php");
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: index.php");
    exit;
}

// Busca dados atuais
$stmt = $pdo->prepare("SELECT * FROM edicoes WHERE id = ?");
$stmt->execute([$id]);
$edicao = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$edicao) {
    die("Edição não encontrada.");
}

$msg = "";

// Processar Atualização
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $numero = $_POST['numero'];
    $data = $_POST['data'];

    // Verifica se enviou novo arquivo
    if (!empty($_FILES['pdf_file']['name'])) {
        $ext = pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION);
        if (strtolower($ext) === 'pdf') {
            // 1. Gera o nome único
            $novoNome = uniqid() . ".pdf";

            // 2. Define a estrutura de pastas: uploads/ANO/MES
            $ano = date('Y');
            $mes = date('m');
            $pastaRelativa = "$ano/$mes/";
            $pastaAbsoluta = "../uploads/" . $pastaRelativa;

            // 3. Cria a pasta se não existir (recursivo)
            if (!is_dir($pastaAbsoluta)) {
                mkdir($pastaAbsoluta, 0755, true);

                // Copia o .htaccess para a nova subpasta para garantir segurança extra
                // (Opcional, pois o .htaccess da raiz uploads já protege, mas é boa prática)
                if (file_exists('../uploads/.htaccess')) {
                    copy('../uploads/.htaccess', $pastaAbsoluta . '.htaccess');
                }
            }

            // 4. Define o caminho final
            $destino = $pastaAbsoluta . $novoNome;

            // 5. Salva no banco o caminho RELATIVO (ex: 2023/10/arquivo.pdf)
            $caminhoParaBanco = $pastaRelativa . $novoNome;

            if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $destino)) {
                // SQL precisa salvar $caminhoParaBanco
                $sql = "INSERT INTO edicoes (numero_edicao, data_publicacao, arquivo_path) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$numero, $data, $caminhoParaBanco]); // <--- Mudou aqui

                $msg = "<div class='alert alert-success'>Publicado com sucesso!</div>";
            }
        } else {
            $msg = "<div class='alert alert-danger'>Erro: O arquivo deve ser PDF.</div>";
        }
    } else {
        // Atualiza APENAS dados (Mantém arquivo antigo)
        $stmtUpdate = $pdo->prepare("UPDATE edicoes SET numero_edicao=?, data_publicacao=? WHERE id=?");
        $stmtUpdate->execute([$numero, $data, $id]);
        registrarLog($pdo, 'Edição', "Edição $numero", "Dados atualizados via formulário");
        $msg = "<div class='alert alert-success'>Dados atualizados com sucesso!</div>";
        header( "refresh:5; url=index.php" );
    }

    // Atualiza variaveis locais
    $edicao['numero_edicao'] = $numero;
    $edicao['data_publicacao'] = $data;
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Editar Edição - DOECA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-warning">
                        <strong>Editando Edição: <?php echo $edicao['numero_edicao']; ?></strong>
                    </div>
                    <div class="card-body">
                        <?php echo $msg; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Número da Edição</label>
                                <input type="text" name="numero" class="form-control"
                                    value="<?php echo $edicao['numero_edicao']; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Data da Publicação</label>
                                <input type="date" name="data" class="form-control"
                                    value="<?php echo $edicao['data_publicacao']; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Substituir Arquivo PDF (Opcional)</label>
                                <input type="file" name="pdf_file" class="form-control" accept=".pdf">
                                <div class="form-text">Arquivo atual: <a
                                        href="../arquivo.php?id=<?php echo $id; ?>"
                                        target="_blank">Visualizar PDF Atual</a></div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary">Voltar</a>
                                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                            </div>
                        </form>
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
</body>

</html>