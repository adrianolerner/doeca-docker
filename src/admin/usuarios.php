<?php
require 'auth.php'; // Protege a página
require 'logger.php';
verificarAdmin();   // Garante que é ADMIN
require '../config.php';

$msg = "";

// --- LÓGICA DE EXCLUSÃO ---
if (isset($_GET['excluir'])) {
    $idExcluir = (int) $_GET['excluir'];

    // Proteção: Não permitir excluir o próprio usuário logado
    if ($idExcluir == $_SESSION['usuario_id']) {
        $msg = "<div class='alert alert-warning alert-dismissible fade show'>Você não pode excluir seu próprio usuário! <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } else {
        $stmtDelete = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        if ($stmtDelete->execute([$idExcluir])) {
            registrarLog($pdo, 'Apagar Usuário', "Painel Admin", "Usuário apagado com sucesso!");
            $msg = "<div class='alert alert-success alert-dismissible fade show'>Usuário excluído com sucesso! <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } else {
            $msg = "<div class='alert alert-danger'>Erro ao excluir usuário.</div>";
        }
    }
}

// --- LÓGICA DE CADASTRO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cadastrar'])) {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $nivel = $_POST['nivel'];
    $senha = $_POST['senha'];

    if (!empty($nome) && !empty($email) && !empty($senha)) {
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, nivel) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nome, $email, $senhaHash, $nivel]);
            registrarLog($pdo, 'Criar Usuário', "Painel Admin", "Usuário criado com sucesso!");
            $msg = "<div class='alert alert-success alert-dismissible fade show'>Usuário cadastrado com sucesso! <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } catch (PDOException $e) {
            $msg = "<div class='alert alert-danger'>Erro: E-mail já cadastrado ou dados inválidos.</div>";
        }
    } else {
        $msg = "<div class='alert alert-warning'>Preencha todos os campos obrigatórios.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Gerenciar Usuários - DOECA</title>
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
                <li class="nav-item"><a class="nav-link" href="index.php">Publicações</a></li>
                <li class="nav-item"><a class="nav-link active fw-bold text-warning" href="usuarios.php">Gerenciar Usuários</a></li>
                <li class="nav-item"><a class="nav-link" href="historico.php">Auditoria</a></li>
                <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="ferramentas.php">Ferramentas</a></li>
            </ul>
            <span class="navbar-text me-3 text-white">
                <a href="perfil.php" class="navbar-text me-3 text-white text-decoration-none"
                    title="Alterar minha senha">
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
                        <i class="fas fa-user-plus"></i> Novo Usuário
                    </div>
                    <div class="card-body">
                        <?php echo $msg; ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Nome</label>
                                <input type="text" name="nome" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">E-mail</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Senha</label>
                                <input type="password" name="senha" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Nível de Acesso</label>
                                <select name="nivel" class="form-select">
                                    <option value="editor">Editor</option>
                                    <option value="admin">Administrador</option>
                                </select>
                            </div>
                            <button type="submit" name="cadastrar" class="btn btn-success w-100">
                                <i class="fas fa-save"></i> Cadastrar
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-9">
                <div class="card shadow border-0">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 text-secondary"><i class="fas fa-users"></i> Usuários Ativos</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="tabelaUsuarios" class="table table-striped table-hover" style="width:100%">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nome</th>
                                        <th>E-mail</th>
                                        <th>Nível</th>
                                        <th class="text-center" width="150">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $pdo->query("SELECT id, nome, email, nivel FROM usuarios ORDER BY nome ASC");
                                    while ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $badge = ($u['nivel'] == 'admin') ? 'bg-danger' : 'bg-info';

                                        // Ações
                                        $botoes = "";

                                        // Botão Editar
                                        $botoes .= "<a href='editar_usuario.php?id={$u['id']}' class='btn btn-sm btn-warning me-1' title='Editar'><i class='fas fa-edit'></i></a>";

                                        // Botão Excluir (Desativado se for o próprio usuário)
                                        if ($u['id'] == $_SESSION['usuario_id']) {
                                            $botoes .= "<button class='btn btn-sm btn-secondary' disabled title='Você não pode se excluir'><i class='fas fa-trash'></i></button>";
                                        } else {
                                            $botoes .= "<a href='usuarios.php?excluir={$u['id']}' class='btn btn-sm btn-danger' title='Excluir' onclick=\"return confirm('Tem certeza que deseja excluir o usuário {$u['nome']}?');\"><i class='fas fa-trash'></i></a>";
                                        }

                                        echo "<tr>
                                                <td>" . htmlspecialchars($u['nome']) . "</td>
                                                <td>" . htmlspecialchars($u['email']) . "</td>
                                                <td><span class='badge {$badge}'>{$u['nivel']}</span></td>
                                                <td class='text-center'>{$botoes}</td>
                                              </tr>";
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
            $('#tabelaUsuarios').DataTable({
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json' }
            });
        });
    </script>
</body>

</html>