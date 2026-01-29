<?php
require 'auth.php';
require '../config.php';

// 1. Dados dos Cards (Totais)
$totalEdicoes = $pdo->query("SELECT COUNT(*) FROM edicoes")->fetchColumn();
$totalDownloads = $pdo->query("SELECT SUM(visualizacoes) FROM edicoes")->fetchColumn() ?: 0;
$totalVisitas = $pdo->query("SELECT SUM(quantidade) FROM visitas_diarias")->fetchColumn() ?: 0;
$visitasHoje = $pdo->query("SELECT quantidade FROM visitas_diarias WHERE data_visita = CURDATE()")->fetchColumn() ?: 0;

// 2. Dados para Gráfico de Linha (Visitas últimos 30 dias)
$sqlVisitas = "SELECT DATE_FORMAT(data_visita, '%d/%m') as dia, quantidade FROM visitas_diarias ORDER BY data_visita DESC LIMIT 30";
$stmtVisitas = $pdo->query($sqlVisitas);
$dadosVisitas = array_reverse($stmtVisitas->fetchAll(PDO::FETCH_ASSOC));
$labelsVisitas = json_encode(array_column($dadosVisitas, 'dia'));
$valuesVisitas = json_encode(array_column($dadosVisitas, 'quantidade'));

// 3. Dados para Gráfico de Barras (Top 10 Edições)
$sqlTopEdicoes = "SELECT numero_edicao, visualizacoes FROM edicoes ORDER BY visualizacoes DESC LIMIT 10";
$stmtTop = $pdo->query($sqlTopEdicoes);
$dadosTop = $stmtTop->fetchAll(PDO::FETCH_ASSOC);
$labelsTop = json_encode(array_column($dadosTop, 'numero_edicao'));
$valuesTop = json_encode(array_column($dadosTop, 'visualizacoes'));

// 4. Dados para Termos Pesquisados
$sqlTermos = "SELECT termo, quantidade FROM termos_pesquisados ORDER BY quantidade DESC LIMIT 10";
$stmtTermos = $pdo->query($sqlTermos);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - DOECA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        /* CSS CORRETIVO PARA O PDF */
        
        /* 1. Garante que os elementos não quebrem no meio da página */
        .card { page-break-inside: avoid; }

        /* 2. Corrige o "estouro" lateral do Bootstrap */
        #conteudo-relatorio {
            /* Força uma largura fixa compatível com A4 Paisagem para renderizar o gráfico no tamanho certo */
            max-width: 1080px; 
            margin: 0 auto; /* Centraliza na tela */
            overflow: hidden; /* Corta qualquer excesso invisível */
        }

        /* 3. Remove as margens negativas das linhas que causam o corte lateral */
        #conteudo-relatorio .row {
            margin-left: 0 !important;
            margin-right: 0 !important;
        }

        /* 4. Garante que as colunas tenham espaçamento interno para não colar na borda */
        #conteudo-relatorio .col-md-4, 
        #conteudo-relatorio .col-lg-8, 
        #conteudo-relatorio .col-lg-4, 
        #conteudo-relatorio .col-12 {
            padding-left: 10px !important;
            padding-right: 10px !important;
        }
    </style>
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 px-3" data-html2canvas-ignore="true">
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
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link active fw-bold text-warning" href="dashboard.php">Dashboard</a></li>
                <?php if ($_SESSION['usuario_nivel'] === 'admin'): ?>
                <li class="nav-item"><a class="nav-link" href="ferramentas.php">Ferramentas</a></li>
                <?php endif; ?>
            </ul>
            <span class="navbar-text me-3 text-white"><a href="perfil.php" class="text-white text-decoration-none"><i class="fas fa-user-circle"></i> Olá, <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></a></span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">Sair</a>
        </div>
    </nav>

    <div class="container mb-5">
        
        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
            <h2 class="text-dark mb-0"><i class="fas fa-chart-line text-primary"></i> Visão Geral</h2>
            <button class="btn btn-danger" onclick="gerarPDF()">
                <i class="fas fa-file-pdf"></i> Baixar Relatório
            </button>
        </div>

        <div id="conteudo-relatorio" class="p-4 bg-white rounded shadow-sm">
            
            <div class="d-none d-print-block mb-4 text-center" id="titulo-pdf">
                <h3>Relatório Gerencial - DOECA</h3>
                <p class="text-muted">Gerado em: <?php echo date('d/m/Y H:i'); ?> por <?php echo $_SESSION['usuario_nome']; ?></p>
                <hr>
            </div>

            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card text-white bg-primary shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Visitas Hoje</h6>
                                    <h2 class="my-2 fw-bold"><?php echo $visitasHoje; ?></h2>
                                </div>
                                <i class="fas fa-user-clock fa-3x opacity-50"></i>
                            </div>
                            <small>Total Histórico: <?php echo $totalVisitas; ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card text-white bg-success shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Total Downloads</h6>
                                    <h2 class="my-2 fw-bold"><?php echo $totalDownloads; ?></h2>
                                </div>
                                <i class="fas fa-download fa-3x opacity-50"></i>
                            </div>
                            <small>Visualizações e Downloads</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card text-white bg-info shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Edições Publicadas</h6>
                                    <h2 class="my-2 fw-bold"><?php echo $totalEdicoes; ?></h2>
                                </div>
                                <i class="fas fa-book fa-3x opacity-50"></i>
                            </div>
                            <small>Documentos no sistema</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white">
                            <h5 class="mb-0 text-secondary">Acessos ao Site (Últimos 30 dias)</h5>
                        </div>
                        <div class="card-body">
                            <div style="height: 300px; position: relative;">
                                <canvas id="chartVisitas"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white">
                            <h5 class="mb-0 text-secondary">Termos Buscados</h5>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php while($termo = $stmtTermos->fetch(PDO::FETCH_ASSOC)): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?php echo htmlspecialchars($termo['termo']); ?>
                                        <span class="badge bg-secondary rounded-pill"><?php echo $termo['quantidade']; ?></span>
                                    </li>
                                <?php endwhile; ?>
                                <?php if($stmtTermos->rowCount() == 0): ?>
                                    <li class="list-group-item text-muted text-center py-4">Nenhuma busca registrada.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row" style="page-break-before: always;">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white">
                            <h5 class="mb-0 text-secondary">Top 10 Edições Mais Visualizadas</h5>
                        </div>
                        <div class="card-body">
                            <div style="height: 400px; position: relative;">
                                <canvas id="chartDownloads"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4 text-muted small d-none d-print-block" id="rodape-pdf">
                Relatório gerado automaticamente pelo sistema DOECA.
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // CONFIGURAÇÃO DOS GRÁFICOS
        const colorPrimary = '#0d6efd';
        const colorSuccess = '#198754';
        const colorBg = 'rgba(13, 110, 253, 0.1)';

        const ctxVisitas = document.getElementById('chartVisitas').getContext('2d');
        new Chart(ctxVisitas, {
            type: 'line',
            data: {
                labels: <?php echo $labelsVisitas; ?>,
                datasets: [{
                    label: 'Visitas Diárias',
                    data: <?php echo $valuesVisitas; ?>,
                    borderColor: colorPrimary,
                    backgroundColor: colorBg,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                animation: { duration: 0 }
            }
        });

        const ctxDownloads = document.getElementById('chartDownloads').getContext('2d');
        new Chart(ctxDownloads, {
            type: 'bar',
            data: {
                labels: <?php echo $labelsTop; ?>,
                datasets: [{
                    label: 'Visualizações/Downloads',
                    data: <?php echo $valuesTop; ?>,
                    backgroundColor: colorSuccess,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y', 
                scales: { 
                    x: { 
                        beginAtZero: true,
                        ticks: { stepSize: 1, precision: 0 } 
                    } 
                },
                animation: { duration: 0 }
            }
        });

        function gerarPDF() {
            const element = document.getElementById('conteudo-relatorio');
            
            document.getElementById('titulo-pdf').classList.remove('d-none');
            document.getElementById('rodape-pdf').classList.remove('d-none');

            const opt = {
                margin:       5, 
                filename:     'Relatorio-DOECA-' + new Date().toLocaleDateString('pt-BR').replace(/\//g, '-') + '.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                // Adicionado scrollX:0 para evitar cortes laterais
                html2canvas:  { scale: 2, scrollX: 0, scrollY: 0 }, 
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' },
                pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
            };

            html2pdf().set(opt).from(element).save().then(function() {
                document.getElementById('titulo-pdf').classList.add('d-none');
                document.getElementById('rodape-pdf').classList.add('d-none');
            });
        }
    </script>
</body>
</html>