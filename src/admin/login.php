<?php
// admin/login.php - Versão 0.6
session_start();
require '../config.php'; // As chaves CLOUDFLARE_... vêm do ambiente via config.php
require 'logger.php';

// Configurações de Rate Limit
$max_tentativas = 5; 
$tempo_bloqueio = 15; // Minutos

// --- FUNÇÃO DE BYPASS PARA DEV ---
function isLocalhost() {
    $whitelist = ['127.0.0.1', '::1'];
    return in_array($_SERVER['REMOTE_ADDR'], $whitelist) || $_SERVER['SERVER_NAME'] === 'localhost';
}

// Verifica se as chaves estão configuradas corretamente
function captchaConfigurado() {
    return defined('CLOUDFLARE_SITE_KEY') && !empty(CLOUDFLARE_SITE_KEY) && 
           defined('CLOUDFLARE_SECRET_KEY') && !empty(CLOUDFLARE_SECRET_KEY);
}

if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$erro = "";

function verificarTurnstile($token) {
    // 1. Bypass Localhost
    if (isLocalhost()) {
        return true; 
    }

    // 2. Bypass Setup (Docker sem chaves)
    // Se as chaves não foram definidas no ambiente, permite login (protegido apenas pelo Rate Limit)
    if (!captchaConfigurado()) {
        return true; 
    }

    // 3. Verificação Real com a Cloudflare
    $url = "https://challenges.cloudflare.com/turnstile/v0/siteverify";
    $data = [
        'secret' => CLOUDFLARE_SECRET_KEY,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    $response = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($response, true);
    return isset($json['success']) && $json['success'] === true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $ip = $_SERVER['REMOTE_ADDR'];

    // 1. VERIFICA RATE LIMIT
    $stmt = $pdo->prepare("DELETE FROM login_tentativas WHERE tentativa_em < (NOW() - INTERVAL ? MINUTE)");
    $stmt->execute([$tempo_bloqueio]);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_tentativas WHERE ip_address = ?");
    $stmt->execute([$ip]);
    $tentativas_atuais = $stmt->fetchColumn();

    // Se excedeu tentativas e NÃO é localhost, bloqueia (Rate Limit funciona mesmo sem Captcha)
    if ($tentativas_atuais >= $max_tentativas && !isLocalhost()) { 
        $erro = "Muitas tentativas falhas. Bloqueado por $tempo_bloqueio minutos.";
        registrarLog($pdo, 'Segurança', 'Bloqueio de IP', "IP $ip excedeu tentativas.");
    } else {
        
        // 2. VERIFICA CAPTCHA
        $tokenCaptcha = $_POST['cf-turnstile-response'] ?? '';
        
        if (!verificarTurnstile($tokenCaptcha)) {
            $erro = "Falha na verificação de segurança (Captcha). Tente novamente.";
        } else {
            // 3. VERIFICA CREDENCIAIS
            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $senha = $_POST['senha'];

            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                // SUCESSO
                $stmt = $pdo->prepare("DELETE FROM login_tentativas WHERE ip_address = ?");
                $stmt->execute([$ip]);

                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['usuario_nivel'] = $usuario['nivel'];
                
                // Define origem do login para o log
                if (isLocalhost()) $origem = "Localhost";
                elseif (!captchaConfigurado()) $origem = "Web (Sem Captcha)";
                else $origem = "Web (Seguro)";

                registrarLog($pdo, 'Login', "Painel Admin", "Sucesso via $origem");

                header("Location: index.php");
                exit;
            } else {
                // FALHA
                $stmt = $pdo->prepare("INSERT INTO login_tentativas (ip_address) VALUES (?)");
                $stmt->execute([$ip]);
                
                $erro = "E-mail ou senha incorretos.";
                registrarLog($pdo, 'Login Falho', "Tentativa Inválida", "User: $email | IP: $ip");
            }
        }
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
    
    <?php if (!isLocalhost() && captchaConfigurado()): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>

    <style>
        body { background-color: #f0f2f5; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .card-login { width: 100%; max-width: 400px; }
        .cf-turnstile { margin-bottom: 1rem; display: flex; justify-content: center; }
    </style>
</head>

<body>
    <div class="card card-login shadow border-0">
        <div class="card-body p-5">
            <h3 class="text-center text-primary mb-4 fw-bold">DOECA</h3>
            <h5 class="text-center text-secondary mb-4 fw-bold">Diário Oficial Eletrônico <br /> de Código Aberto</h5>
            <p class="text-center text-muted mb-4">Acesso Administrativo (v0.6)</p>

            <?php if ($erro): ?>
                <div class="alert alert-danger py-2 text-center small"><?php echo $erro; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-control" required autofocus placeholder="seu@email.gov.br">
                </div>
                <div class="mb-4">
                    <label class="form-label">Senha</label>
                    <input type="password" name="senha" class="form-control" required placeholder="••••••••">
                </div>
                
                <?php if (isLocalhost()): ?>
                    <div class="alert alert-warning text-center py-2 mb-3 small border-warning text-warning-emphasis">
                        <i class="fas fa-bug"></i> Modo Local: <b>Captcha Ignorado</b>
                    </div>
                <?php elseif (!captchaConfigurado()): ?>
                    <div class="alert alert-info text-center py-2 mb-3 small border-info text-info-emphasis">
                        <i class="fas fa-info-circle"></i> Modo Setup: <b>Captcha não configurado</b>
                    </div>
                <?php else: ?>
                    <div class="cf-turnstile" data-sitekey="<?php echo CLOUDFLARE_SITE_KEY; ?>" data-language="pt-br"></div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary w-100 py-2">
                    <i class="fas fa-sign-in-alt me-2"></i> Entrar
                </button>
            </form>
            
            <div class="text-center mt-3">
                <a href="../index.php" class="btn btn-link text-decoration-none text-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar ao site
                </a>
            </div>
        </div>
        <footer class="text-center mt-3 pb-4 text-muted">
            <small>©
                <?php echo date('Y'); ?> Adriano Lerner Biesek | Prefeitura Municipal de Castro (PR)<br>Feito com <i
                    class="fa fa-heart text-danger"></i> para o serviço público.
            </small>
        </footer>
    </div>
</body>
</html>