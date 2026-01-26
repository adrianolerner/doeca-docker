<?php
require 'auth.php'; // Protege a página
require 'logger.php';
verificarAdmin();   // Garante que é ADMIN
require '../config.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: usuarios.php");
    exit;
}

// Busca usuário atual
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    die("Usuário não encontrado.");
}

$msg = "";

// Processar Atualização
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $nivel = $_POST['nivel'];
    $novaSenha = $_POST['senha']; // Pode vir vazio

    if (!empty($nome) && !empty($email)) {
        try {
            // Lógica para senha: Se preencheu, atualiza. Se não, mantém a antiga.
            if (!empty($novaSenha)) {
                $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
                $sql = "UPDATE usuarios SET nome=?, email=?, nivel=?, senha=? WHERE id=?";
                $params = [$nome, $email, $nivel, $senhaHash, $id];
            } else {
                $sql = "UPDATE usuarios SET nome=?, email=?, nivel=? WHERE id=?";
                $params = [$nome, $email, $nivel, $id];
            }

            $stmtUpdate = $pdo->prepare($sql);
            $stmtUpdate->execute($params);
            registrarLog($pdo, 'Editar Usuário', "Painel Admin", "Usuário alterado com sucesso!");

            $msg = "<div class='alert alert-success'>Usuário atualizado com sucesso!</div>";

            // Atualiza variaveis locais para refletir na tela
            $usuario['nome'] = $nome;
            $usuario['email'] = $email;
            $usuario['nivel'] = $nivel;

        } catch (PDOException $e) {
            $msg = "<div class='alert alert-danger'>Erro: E-mail possivelmente já em uso.</div>";
        }
    } else {
        $msg = "<div class='alert alert-warning'>Nome e E-mail são obrigatórios.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Editar Usuário - DOECA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow border-0">
                    <div class="card-header bg-warning text-dark">
                        <strong><i class="fas fa-user-edit"></i> Editando Usuário:
                            <?php echo htmlspecialchars($usuario['nome']); ?></strong>
                    </div>
                    <div class="card-body">
                        <?php echo $msg; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Nome</label>
                                <input type="text" name="nome" class="form-control"
                                    value="<?php echo htmlspecialchars($usuario['nome']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">E-mail</label>
                                <input type="email" name="email" class="form-control"
                                    value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Nível de Acesso</label>
                                <select name="nivel" class="form-select">
                                    <option value="editor" <?php echo ($usuario['nivel'] == 'editor') ? 'selected' : ''; ?>>Editor</option>
                                    <option value="admin" <?php echo ($usuario['nivel'] == 'admin') ? 'selected' : ''; ?>>
                                        Administrador</option>
                                </select>
                            </div>

                            <hr>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted">Nova Senha</label>
                                <input type="password" name="senha" class="form-control"
                                    placeholder="Deixe em branco para manter a senha atual">
                                <small class="text-muted">Preencha apenas se desejar alterar a senha deste
                                    usuário.</small>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <a href="usuarios.php" class="btn btn-secondary">Voltar</a>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>