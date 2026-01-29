<?php
require 'auth.php';
verificarAdmin(); // Proteção: Apenas admin acessa
require '../config.php';

// Contagem rápida para estatística no card de exportação
$totalEdicoes = $pdo->query("SELECT COUNT(*) FROM edicoes")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Ferramentas - DOECA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .tool-card {
            transition: transform 0.2s;
            height: 100%;
            border: 1px solid #e0e0e0;
        }

        .tool-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            border-color: #0d6efd;
        }

        .icon-box {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
    </style>
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
                <li class="nav-item"><a class="nav-link" href="usuarios.php">Gerenciar Usuários</a></li>
                <li class="nav-item"><a class="nav-link" href="historico.php">Auditoria</a></li>
                <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link active fw-bold text-warning"
                        href="ferramentas.php">Ferramentas</a></li>
            </ul>
            <span class="navbar-text me-3 text-white"><a href="perfil.php" class="text-white text-decoration-none"><i
                        class="fas fa-user-circle"></i> Olá,
                    <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></a></span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">Sair</a>
        </div>
    </nav>

    <div class="container mb-5">

        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
            <h2 class="text-dark mb-0"><i class="fas fa-tools text-secondary"></i> Ferramentas do Sistema</h2>
        </div>

        <div class="card shadow-sm mb-5 border-success border-start border-4">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-success"><i class="fas fa-file-export"></i> Backup e Exportação</h5>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="card-title">Gerar Backup Completo (ZIP)</h5>
                        <p class="card-text text-muted">
                            Gera um arquivo comprimido contendo todas as <strong><?php echo $totalEdicoes; ?>
                                edições</strong> cadastradas.
                            Os arquivos são renomeados automaticamente para o padrão <code>AAAA-MM-DD__EDICAO.pdf</code>
                            e inclui uma planilha CSV para fácil reimportação.
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="exportar.php" class="btn btn-success btn-lg w-100 py-3">
                            <i class="fas fa-download fa-lg me-2"></i> Exportar Acervo
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <h4 class="mb-3 text-dark border-bottom pb-2"><i class="fas fa-file-import text-primary"></i> Ferramentas de
            Importação (Carga de Acervo Legado)</h4>

        <div class="row g-4">

            <div class="col-md-4">
                <div class="card tool-card p-3 text-center">
                    <div class="card-body">
                        <div class="icon-box text-primary"><i class="fas fa-font"></i></div>
                        <h5 class="card-title">Importar por Nome</h5>
                        <p class="card-text small text-muted">
                            Ideal para arquivos já renomeados e padronizados.
                            <br>Ex: <code>2026-01-20__3315.pdf</code>
                        </p>
                        <a href="importar.php" class="btn btn-outline-primary w-100 stretched-link">Acessar</a>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card tool-card p-3 text-center">
                    <div class="card-body">
                        <div class="icon-box text-info"><i class="fas fa-file-csv"></i></div>
                        <h5 class="card-title">Importar via CSV</h5>
                        <p class="card-text small text-muted">
                            Ideal para listas organizadas em planilha.
                            <br>Lê: <code>Edicao;Data;Arquivo.pdf</code>
                        </p>
                        <a href="importar_csv.php" class="btn btn-outline-info w-100 stretched-link">Acessar</a>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card tool-card p-3 text-center">
                    <div class="card-body">
                        <div class="icon-box text-warning"><i class="fas fa-robot"></i></div>
                        <h5 class="card-title">Importação Inteligente</h5>
                        <p class="card-text small text-muted">
                            Ideal para arquivos com nomes aleatórios.
                            <br>Lê o cabeçalho do PDF para achar Data/Edição.
                        </p>
                        <a href="importar_inteligente.php"
                            class="btn btn-outline-warning text-dark w-100 stretched-link">Acessar</a>
                    </div>
                </div>
            </div>

        </div>

        <div class="alert alert-warning mt-5">
            <i class="fas fa-exclamation-triangle"></i> <strong>Atenção:</strong> As ferramentas de importação movem
            arquivos e alteram o banco de dados em massa. Certifique-se de ter um backup antes de utilizá-las em grandes
            volumes.
        </div>
        <footer class="text-center mt-5 py-4 text-muted">
            <small>©
                <?php echo date('Y'); ?> Adriano Lerner Biesek | Prefeitura Municipal de Castro (PR)<br>Feito com <i
                    class="fa fa-heart text-danger"></i> para o serviço público.
            </small>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>