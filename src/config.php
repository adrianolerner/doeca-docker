<?php
// Tenta pegar do Docker, se não existir, usa o padrão local
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'doeca_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Dica de segurança: Em produção, evite mostrar o erro detalhado ($e->getMessage()) na tela do usuário
    die("Erro na conexão com o banco de dados.");
}
?>