<?php
// admin/auth.php
session_start();

// Verifica se a sessão do usuário existe
if (!isset($_SESSION['usuario_id'])) {
    // Se não existir, redireciona para o login
    header("Location: login.php");
    exit;
}

// Função auxiliar para verificar se é admin (para proteger páginas exclusivas)
function verificarAdmin() {
    if ($_SESSION['usuario_nivel'] !== 'admin') {
        die('<div class="container mt-5"><div class="alert alert-danger">Acesso Negado: Apenas administradores podem acessar esta página. <a href="index.php">Voltar</a></div></div>');
    }
}
?>