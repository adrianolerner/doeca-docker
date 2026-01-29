<?php
require 'config.php';
// Validação básica do ID
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM edicoes WHERE id = ?");
$stmt->execute([$id]);
$edicao = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$edicao) die("Edição não encontrada.");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leitura - Edição <?php echo $edicao['numero_edicao']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* Define a altura total e remove barras de rolagem da janela principal */
        body, html { 
            height: 100%; 
            margin: 0; 
            overflow: hidden; 
            display: flex;       /* Transforma o body em um container flexível */
            flex-direction: column; /* Organiza itens em coluna (Navbar em cima, PDF em baixo) */
            background-color: #f8f9fa;
        }

        /* A Navbar não encolhe */
        .navbar {
            flex-shrink: 0; 
            z-index: 1000;
        }

        /* O Iframe cresce para ocupar todo o espaço restante */
        .iframe-container { 
            flex-grow: 1; 
            width: 100%; 
            border: none; 
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark bg-dark px-3 shadow-sm">
        <div class="container-fluid">
            
            <div class="d-flex align-items-center">
                <a class="navbar-brand fw-bold me-3" href="index.php">
                    <i class="fas fa-book-open"></i> DOECA
                </a>
                
                <span class="text-white-50 border-start ps-3 d-none d-md-block">
                    Visualizando Edição nº <strong class="text-white"><?php echo $edicao['numero_edicao']; ?></strong> 
                    de <?php echo date('d/m/Y', strtotime($edicao['data_publicacao'])); ?>
                </span>
                
                <span class="text-white-50 border-start ps-2 d-md-none small">
                    Ed. <strong class="text-white"><?php echo $edicao['numero_edicao']; ?></strong>
                </span>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="arquivo.php?id=<?php echo $id; ?>" class="btn btn-light btn-sm" download title="Baixar arquivo PDF">
                    <i class="fas fa-download"></i> <span class="d-none d-sm-inline">Baixar</span>
                </a>

                <a href="index.php" class="btn btn-outline-light btn-sm" title="Voltar para a lista">
                    <i class="fas fa-arrow-left"></i> <span class="d-none d-sm-inline">Voltar</span>
                </a>
            </div>
            
        </div>
    </nav>
    
    <iframe src="arquivo.php?id=<?php echo $id; ?>" class="iframe-container" title="Visualizador de PDF"></iframe>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>