<?php
require 'config.php';
// --- ESTATÍSTICAS: Contar Visita Diária ---
$hoje = date('Y-m-d');
$pdo->query("INSERT INTO visitas_diarias (data_visita, quantidade) VALUES ('$hoje', 1) ON DUPLICATE KEY UPDATE quantidade = quantidade + 1");

// --- ESTATÍSTICAS: Contar Termo Pesquisado ---
if (!empty($_GET['busca'])) {
    $termoBusca = trim($_GET['busca']);
    // Ignora buscas muito curtas para não sujar o banco
    if (strlen($termoBusca) > 2) {
        $stmtTermo = $pdo->prepare("INSERT INTO termos_pesquisados (termo, quantidade) VALUES (?, 1) ON DUPLICATE KEY UPDATE quantidade = quantidade + 1");
        $stmtTermo->execute([$termoBusca]);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DOECA - Diário Oficial Eletrônico</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        /* Container centralizado */
        .search-container {
            max-width: 700px;
            margin: 0 auto;
        }

        /* O grupo que une input e botão */
        .search-input-group {
            border-radius: 50px;
            /* Formato pílula */
            overflow: hidden;
            /* Garante que o botão não vaze as bordas */
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            /* Sombra suave */
            border: 1px solid #dfe1e5;
            transition: all 0.3s ease;
        }

        /* Efeito de foco em todo o grupo */
        .search-input-group:focus-within {
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.25);
            /* Sombra azulada */
            border-color: #0d6efd;
        }

        /* O campo de texto */
        .search-form-control {
            border: none;
            padding: 15px 25px;
            font-size: 1.1rem;
        }

        .search-form-control:focus {
            box-shadow: none;
            /* Remove o brilho padrão do bootstrap */
        }

        /* O botão de ação à direita */
        .btn-search-custom {
            background-color: #0d6efd;
            /* Azul Bootstrap */
            color: white;
            padding: 0 30px;
            font-weight: 600;
            font-size: 1rem;
            border: none;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            /* Espaço entre ícone e texto */
        }

        .btn-search-custom:hover {
            background-color: #0b5ed7;
            /* Azul mais escuro */
            color: white;
        }

        /* Ajuste do card da tabela */
        .card-custom {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
    </style>
</head>

<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 px-3">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#"><i class="fas fa-book-open"></i> DOECA</a>
            <div class="d-flex">
                <a href="admin/login.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-lock"></i> Área Administrativa
                </a>
            </div>
        </div>
    </nav>

    <div class="container">

        <div class="text-center mb-5 mt-4">
            <h2 class="text-primary mb-4 fw-bold">Consulta de Diários Oficiais</h2>
            <p class="text-muted mb-4">Pesquise por número da edição, ano, ou termos dentro dos documentos.</p>

            <form method="GET" action="index.php" class="search-container">
                <div class="input-group search-input-group">
                    <input type="text" name="busca" class="form-control search-form-control"
                        placeholder="Digite o que procura (ex: Lei, Decreto, Data)..."
                        value="<?php echo htmlspecialchars($_GET['busca'] ?? ''); ?>" required>

                    <button type="submit" class="btn btn-search-custom">
                        <i class="fas fa-search"></i> PESQUISAR
                    </button>
                </div>
            </form>

        </div>

        <div class="card card-custom bg-white">
            <div class="card-body p-4">

                <?php if (!empty($_GET['busca'])): ?>
                    <div class="alert alert-info d-flex align-items-center justify-content-between mb-3">
                        <span>
                            <i class="fas fa-filter me-2"></i> Exibindo resultados para:
                            <strong><?php echo htmlspecialchars($_GET['busca']); ?></strong>
                        </span>
                        <a href="index.php" class="btn btn-sm btn-outline-dark">Limpar Filtro</a>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table id="tabelaDiario" class="table table-striped table-hover" style="width:100%">
                        <thead class="table-dark">
                            <tr>
                                <th>Nº Edição</th>
                                <th>Data Publicação</th>
                                <th>Data Upload</th>
                                <th class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $termo = $_GET['busca'] ?? '';

                            if (!empty($termo)) {
                                // BUSCA AVANÇADA (FULLTEXT)
                                $sql = "SELECT *, MATCH(conteudo_indexado) AGAINST (:termo IN BOOLEAN MODE) as relevancia 
                                        FROM edicoes 
                                        WHERE numero_edicao LIKE :termoLike 
                                        OR MATCH(conteudo_indexado) AGAINST (:termo IN BOOLEAN MODE)
                                        ORDER BY data_publicacao DESC";

                                $stmt = $pdo->prepare($sql);
                                $stmt->execute([
                                    ':termo' => $termo,
                                    ':termoLike' => "%$termo%"
                                ]);
                            } else {
                                // LISTAGEM PADRÃO
                                $stmt = $pdo->query("SELECT * FROM edicoes ORDER BY data_publicacao DESC, id DESC LIMIT 100");
                            }

                            // Verifica se encontrou algo
                            $temResultados = false;

                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $temResultados = true;
                                $dataPub = date('d/m/Y', strtotime($row['data_publicacao']));
                                $dataUp = date('d/m/Y H:i', strtotime($row['criado_em']));

                                echo "<tr>
                                        <td><strong>{$row['numero_edicao']}</strong></td>
                                        <td>{$dataPub}</td>
                                        <td>{$dataUp}</td>
                                        <td class='text-center'>
                                            <a href='visualizar.php?id={$row['id']}' class='btn btn-sm btn-info text-white me-1' title='Visualizar'>
                                                <i class='fas fa-eye'></i> Visualizar
                                            </a>
                                            <a href='arquivo.php?id={$row['id']}' download class='btn btn-sm btn-secondary' title='Baixar PDF'>
                                                <i class='fas fa-download'></i> Baixar
                                            </a>
                                        </td>
                                      </tr>";
                            }
                            ?>
                        </tbody>
                    </table>

                    <?php if (!$temResultados && !empty($termo)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-file-excel fa-3x mb-3"></i>
                            <p>Nenhum documento encontrado para sua pesquisa.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center mt-5 py-4 text-muted">
        <small>© <?php echo date('Y'); ?> Adriano Lerner Biesek | Prefeitura Municipal de Castro (PR)<br>Feito com <i
                class="fa fa-heart text-danger"></i> para o serviço público.</small>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function () {
            $('#tabelaDiario').DataTable({
                // Configuração para esconder a busca padrão e paginação
                dom: '<"row mb-3"<"col-md-6"l>>rtip',
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
                },
                order: [
                    [1, "desc"]
                ]
            });
        });
    </script>
</body>

</html>