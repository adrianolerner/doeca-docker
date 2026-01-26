<?php
require 'auth.php'; // Protege a página
require '../config.php';

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senhaAtual = $_POST['senha_atual'];
    $novaSenha = $_POST['nova_senha'];
    $confirmaSenha = $_POST['confirma_senha'];
    $idUsuario = $_SESSION['usuario_id'];

    if (!empty($senhaAtual) && !empty($novaSenha) && !empty($confirmaSenha)) {

        // 1. Busca a senha atual do banco para verificar
        $stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE id = ?");
        $stmt->execute([$idUsuario]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($senhaAtual, $usuario['senha'])) {

            // 2. Verifica se a nova senha bate com a confirmação
            if ($novaSenha === $confirmaSenha) {

                // 3. Atualiza a senha
                $novoHash = password_hash($novaSenha, PASSWORD_DEFAULT);
                $stmtUpdate = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");

                if ($stmtUpdate->execute([$novoHash, $idUsuario])) {
                    $msg = "<div class='alert alert-success alert-dismissible fade show'>Senha alterada com sucesso! <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
                } else {
                    $msg = "<div class='alert alert-danger'>Erro ao atualizar no banco de dados.</div>";
                }

            } else {
                $msg = "<div class='alert alert-warning'>A nova senha e a confirmação não conferem.</div>";
            }

        } else {
            $msg = "<div class='alert alert-danger'>A senha atual está incorreta.</div>";
        }
    } else {
        $msg = "<div class='alert alert-warning'>Preencha todos os campos.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Meu Perfil - DOECA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 px-3">
        <a class="navbar-brand fw-bold" href="index.php"><i class="fas fa-book-open"></i> DOECA</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="index.php">Publicações</a></li>
                <?php if ($_SESSION['usuario_nivel'] === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="usuarios.php">Gerenciar Usuários</a></li>
                    <li class="nav-item"><a class="nav-link" href="historico.php">Auditoria</a></li>
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="ferramentas.php">Ferramentas</a></li>
                <?php endif; ?>
            </ul>

            <span class="navbar-text me-3 text-white"><a href="perfil.php"
                    class="navbar-text me-3 text-warning text-decoration-none fw-bold" title="Alterar minha senha">
                    <i class="fas fa-user-circle"></i> Olá, <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?>
                </a></span>

            <a href="logout.php" class="btn btn-outline-danger btn-sm">Sair</a>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">

                <div class="card shadow border-0 mt-4">
                    <div class="card-header bg-primary text-white text-center py-3">
                        <h5 class="mb-0"><i class="fas fa-key"></i> Alterar Minha Senha</h5>
                    </div>
                    <div class="card-body p-4">
                        <?php echo $msg; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted">Senha Atual</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="senha_atual" class="form-control" required
                                        placeholder="Digite sua senha antiga">
                                </div>
                            </div>

                            <hr class="my-4">

                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted">Nova Senha</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-key"></i></span>
                                    <input type="password" name="nova_senha" class="form-control" required
                                        placeholder="Nova senha">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold text-muted">Confirmar Nova Senha</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
                                    <input type="password" name="confirma_senha" class="form-control" required
                                        placeholder="Repita a nova senha">
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg">
                                    Salvar Nova Senha
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    Voltar
                                </a>
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