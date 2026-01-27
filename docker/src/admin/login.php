<?php
// admin/login.php
session_start();
require '../config.php';
require 'logger.php';

// Se já estiver logado, manda para o painel
if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$erro = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'];

    // Busca o usuário pelo email
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verifica se usuário existe E se a senha bate com o hash
    if ($usuario && password_verify($senha, $usuario['senha'])) {
        // Login Sucesso: Salva dados na sessão
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_nivel'] = $usuario['nivel']; // admin ou editor
        registrarLog($pdo, 'Login', "Painel Admin", "Acesso realizado com sucesso");

        header("Location: index.php");
        exit;
    } else {
        $erro = "E-mail ou senha incorretos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - DOECA Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }

        .card-login {
            width: 100%;
            max-width: 400px;
        }
    </style>
</head>

<body>
    <div class="card card-login shadow border-0">
        <div class="card-body p-5">
            <h3 class="text-center text-primary mb-4 fw-bold">DOECA</h3>
            <h5 class="text-center text-secondary mb-4 fw-bold">Diário Oficial Eletrônico <br /> de Código Aberto</h5>
            <p class="text-center text-muted mb-4">Acesso Administrativo</p>

            <?php if ($erro): ?>
                <div class="alert alert-danger py-2 text-center"><?php echo $erro; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-control" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label">Senha</label>
                    <input type="password" name="senha" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2">Entrar</button>
            </form>
            <div class="text-center mt-3">
                <a href="../index.php" class="btn btn-secondary w-100">Voltar ao site</a>
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