<?php
require 'auth.php';
verificarAdmin(); // Apenas admin vê isso
require '../config.php';

// Lógica de Limpeza de Logs (dentro do mesmo arquivo para simplificar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['limpar_logs'])) {
    $pdo->query("TRUNCATE TABLE logs"); // Apaga tudo e zera o ID
    $msg = "<div class='alert alert-success'>Histórico limpo com sucesso!</div>";
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Histórico de Alterações - DOECA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <style>
        /* Estilo Timeline na Tabela */
        .timeline-table {
            border-left: 3px solid #dee2e6;
            margin-left: 10px;
        }

        .timeline-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 10px;
            position: relative;
            left: -18px;
            border: 2px solid #fff;
        }

        .dot-success {
            background-color: #198754;
            box-shadow: 0 0 5px #198754;
        }

        .dot-danger {
            background-color: #dc3545;
            box-shadow: 0 0 5px #dc3545;
        }

        .dot-primary {
            background-color: #0d6efd;
            box-shadow: 0 0 5px #0d6efd;
        }

        .dot-warning {
            background-color: #ffc107;
            box-shadow: 0 0 5px #ffc107;
        }

        .dot-secondary {
            background-color: #6c757d;
        }

        table.dataTable td {
            vertical-align: top;
            padding-top: 15px;
            padding-bottom: 15px;
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
                <li class="nav-item"><a class="nav-link active fw-bold text-warning" href="historico.php">Auditoria</a>
                <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="ferramentas.php">Ferramentas</a></li>
                </li>
            </ul>
            <span class="navbar-text me-3 text-white"><a href="perfil.php"
                    class="navbar-text me-3 text-white text-decoration-none" title="Alterar minha senha">
                    <i class="fas fa-user-circle"></i> Olá, <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?>
                </a></span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">Sair</a>
        </div>
    </nav>

    <div class="container mb-5">

        <?php if (isset($msg))
            echo $msg; ?>

        <div class="card shadow border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="fa fa-history fa-2x text-primary me-3"></i>
                    <div>
                        <h4 class="mb-0 fw-bold text-dark">Auditoria e Logs</h4>
                        <small class="text-muted">Rastreamento de atividades do sistema.</small>
                    </div>
                </div>

                <form method="POST"
                    onsubmit="return confirm('ATENÇÃO: Tem certeza que deseja apagar TODO o histórico? Esta ação é irreversível.');">
                    <button type="submit" name="limpar_logs" class="btn btn-outline-danger btn-sm">
                        <i class="fa fa-trash me-1"></i> Limpar Logs
                    </button>
                </form>
            </div>

            <div class="card-body p-4">
                <div class="timeline-table ps-2">
                    <table id="logTable" class="table table-hover w-100 border-0">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">#</th>
                                <th width="15%">Data/Hora</th>
                                <th width="15%">Usuário</th>
                                <th width="10%">Ação</th>
                                <th width="20%">Alvo</th>
                                <th width="25%">Detalhes</th>
                                <th width="10%">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "SELECT * FROM logs ORDER BY id DESC";
                            $stmt = $pdo->query($sql);

                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                // Definição de Cores
                                $dotClass = 'dot-secondary';
                                $badgeClass = 'bg-secondary';
                                $icon = 'fa-info';

                                if (stripos($row['acao'], 'Publicação') !== false || stripos($row['acao'], 'Cadastro') !== false) {
                                    $dotClass = 'dot-success';
                                    $badgeClass = 'bg-success';
                                    $icon = 'fa-plus';
                                } elseif (stripos($row['acao'], 'Exclusão') !== false) {
                                    $dotClass = 'dot-danger';
                                    $badgeClass = 'bg-danger';
                                    $icon = 'fa-trash';
                                } elseif (stripos($row['acao'], 'Edição') !== false || stripos($row['acao'], 'Alteração') !== false) {
                                    $dotClass = 'dot-warning';
                                    $badgeClass = 'bg-warning text-dark';
                                    $icon = 'fa-edit';
                                } elseif (stripos($row['acao'], 'Login') !== false) {
                                    $dotClass = 'dot-primary';
                                    $badgeClass = 'bg-primary';
                                    $icon = 'fa-sign-in-alt';
                                }

                                $dataObj = new DateTime($row['criado_em']);
                                $dataFmt = $dataObj->format('d/m/Y');
                                $horaFmt = $dataObj->format('H:i');

                                echo '<tr>';
                                // ID
                                echo '<td><span class="timeline-dot ' . $dotClass . '"></span><small class="text-muted">#' . $row['id'] . '</small></td>';

                                // Data
                                echo '<td><div class="fw-bold">' . $dataFmt . '</div><div class="small text-muted">' . $horaFmt . '</div></td>';

                                // Usuário
                                echo '<td><i class="fa fa-user text-secondary me-1"></i> ' . htmlspecialchars($row['usuario_nome']) . '</td>';

                                // Ação
                                echo '<td><span class="badge ' . $badgeClass . '"><i class="fa ' . $icon . ' me-1"></i>' . htmlspecialchars($row['acao']) . '</span></td>';

                                // Alvo
                                echo '<td class="fw-bold text-dark">' . htmlspecialchars($row['alvo']) . '</td>';

                                // Detalhes
                                echo '<td class="small text-muted text-break">' . htmlspecialchars($row['detalhes']) . '</td>';

                                // IP
                                echo '<td class="small font-monospace text-muted">' . htmlspecialchars($row['ip']) . '</td>';
                                echo '</tr>';
                            }
                            ?>
                        </tbody>
                    </table>
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
            $('#logTable').DataTable({
                "language": { url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json' },
                "order": [[0, "desc"]],
                "pageLength": 25
            });
        });
    </script>
</body>

</html>