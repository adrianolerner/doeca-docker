<?php
require 'auth.php';
verificarAdmin(); // Proteção: Apenas admin vê essa tela

// 1. Configurações de Versão
function getCurrentVersion() {
    return '0.5.1';
}

// 2. Verifica a última versão no GitHub
function getLatestVersion() {
    $repo = 'adrianolerner/doeca';
    $url = "https://api.github.com/repos/$repo/releases/latest";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'DOECA-System');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Timeout curto
    
    // GitHub exige Header User-Agent
    $headers = [
        'User-Agent: PHP-Script',
        'Accept: application/vnd.github.v3+json'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        $data = json_decode($response, true);
        return isset($data['tag_name']) ? $data['tag_name'] : 'v0.0.0';
    }
    return 'v' . getCurrentVersion(); // Se falhar (sem internet ou limite API), assume atual
}

// 3. Compara versões
function isUpdateAvailable() {
    $current = ltrim(getCurrentVersion(), 'v');
    $latest = ltrim(getLatestVersion(), 'v');
    return version_compare($latest, $current, '>');
}

$versaoAtual = getCurrentVersion();
$temAtualizacao = isUpdateAvailable();
$ultimaVersao = getLatestVersion();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Sobre - DOECA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .author-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid #fff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        .about-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }
        .header-bg {
            background: linear-gradient(135deg, #212529 0%, #343a40 100%);
            color: white;
            padding-top: 3rem;
            padding-bottom: 3rem;
        }
        .feature-icon {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #0d6efd;
        }
    </style>
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-0 px-3">
        <a class="navbar-brand fw-bold" href="#"><i class="fas fa-book-open"></i> DOECA</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="index.php">Publicações</a></li>
                <li class="nav-item"><a class="nav-link" href="usuarios.php">Usuários</a></li>
                <li class="nav-item"><a class="nav-link" href="historico.php">Auditoria</a></li>
                <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="ferramentas.php">Ferramentas</a></li>
            </ul>
            <span class="navbar-text me-3 text-white"><a href="perfil.php" class="text-white text-decoration-none"><i class="fas fa-user-circle"></i> Olá, <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></a></span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">Sair</a>
        </div>
    </nav>

    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                
                <div class="card shadow about-card">
                    <div class="card-header header-bg text-center position-relative">
                        <img src="https://avatars.githubusercontent.com/u/11412428?v=4" alt="Adriano Lerner Biesek" class="author-avatar mb-3">
                        <h2 class="fw-bold mb-0">DOECA</h2>
                        <p class="opacity-75 mb-3">Diário Oficial Eletrônico de Código Aberto</p>
                        
                        <div class="d-flex justify-content-center gap-2">
                            <span class="badge bg-light text-dark border px-3 py-2">
                                <i class="fas fa-code-branch text-primary"></i> Versão <?php echo $versaoAtual; ?>
                            </span>
                            
                            <?php if (!$temAtualizacao): ?>
                                <span class="badge bg-success border border-light px-3 py-2">
                                    <i class="fas fa-check-circle"></i> Sistema Atualizado
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark border border-light px-3 py-2">
                                    <i class="fas fa-exclamation-triangle"></i> Desatualizado
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-body p-4 p-md-5">

                        <?php if ($temAtualizacao): ?>
                            <div class="alert alert-warning shadow-sm d-flex align-items-center mb-4" role="alert">
                                <i class="fas fa-rocket fa-2x me-3"></i>
                                <div>
                                    <h5 class="alert-heading fw-bold mb-1">Nova versão disponível!</h5>
                                    <p class="mb-0 small">A versão <strong><?php echo $ultimaVersao; ?></strong> já foi lançada. Recomendamos atualizar para obter novos recursos e correções de segurança.</p>
                                    <a href="https://github.com/adrianolerner/doeca/releases/latest" target="_blank" class="btn btn-dark btn-sm mt-2">Baixar Atualização</a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="text-center mb-5">
                            <h5 class="fw-bold text-secondary text-uppercase small ls-1">Sobre o Autor</h5>
                            <h3 class="fw-bold text-dark">Adriano Lerner Biesek</h3>
                            <p class="text-muted">Desenvolvedor e Servidor Público</p>
                            <div class="d-flex justify-content-center gap-3">
                                <a href="https://github.com/adrianolerner" target="_blank" class="text-dark fs-4"><i class="fab fa-github"></i></a>
                                <a href="#" class="text-primary fs-4"><i class="fab fa-linkedin"></i></a>
                            </div>
                        </div>

                        <hr class="opacity-25 my-4">

                        <h4 class="fw-bold mb-4 text-center"><i class="fas fa-info-circle text-primary me-2"></i> Detalhes Técnicos</h4>
                        
                        <div class="row g-4 text-center">
                            <div class="col-md-6">
                                <div class="p-3 border rounded h-100 bg-light">
                                    <div class="feature-icon"><i class="fas fa-university"></i></div>
                                    <h6 class="fw-bold">Propósito</h6>
                                    <p class="small text-muted mb-0">Ferramenta gratuita para transparência de atos oficiais em prefeituras e câmaras municipais.</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded h-100 bg-light">
                                    <div class="feature-icon"><i class="fas fa-layer-group"></i></div>
                                    <h6 class="fw-bold">Stack Tecnológica</h6>
                                    <p class="small text-muted mb-0">PHP 8, MariaDB, Bootstrap 5, PDFParser. Leve e sem frameworks pesados.</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded h-100 bg-light">
                                    <div class="feature-icon"><i class="fas fa-balance-scale"></i></div>
                                    <h6 class="fw-bold">Licença MIT</h6>
                                    <p class="small text-muted mb-0">Software livre. Use, modifique e distribua sem custos de licença.</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded h-100 bg-light">
                                    <div class="feature-icon"><i class="fab fa-github"></i></div>
                                    <h6 class="fw-bold">Código Aberto</h6>
                                    <p class="small text-muted mb-0">Acesse o código fonte, contribua e reporte erros diretamente no GitHub.</p>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-center gap-2 mt-5">
                            <a href="index.php" class="btn btn-outline-secondary px-4">
                                <i class="fas fa-arrow-left me-2"></i> Voltar
                            </a>
                            <a href="https://github.com/adrianolerner/doeca" target="_blank" class="btn btn-dark px-4">
                                <i class="fab fa-github me-2"></i> Repositório Oficial
                            </a>
                            <a href="https://github.com/adrianolerner/doeca/issues" target="_blank" class="btn btn-outline-danger px-4">
                                <i class="fas fa-bug me-2"></i> Reportar Bug
                            </a>
                        </div>

                    </div>
                    
                    <div class="card-footer bg-white text-center py-3">
                        <p class="small text-muted mb-0">
                            © <?php echo date('Y'); ?> Adriano Lerner Biesek | Prefeitura Municipal de Castro (PR)<br>
                            Feito com <i class="fas fa-heart text-danger"></i> para o serviço público.
                        </p>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>