<?php
// admin/logger.php

function registrarLog($pdo, $acao, $alvo, $detalhes = null) {
    try {
        $usuario = $_SESSION['usuario_nome'] ?? 'Sistema';
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // Se estiver usando proxy (Cloudflare, etc), tenta pegar o IP real
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        $sql = "INSERT INTO logs (usuario_nome, acao, alvo, detalhes, ip) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario, $acao, $alvo, $detalhes, $ip]);
    } catch (Exception $e) {
        // Silencia erro de log para não parar o sistema principal
        // error_log($e->getMessage()); 
    }
}
?>