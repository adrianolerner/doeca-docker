<?php
// config.php - Versão Blindada para Dumps do phpMyAdmin

$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'doeca_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

try {
    // Adicionamos as opções para garantir codificação correta e erros visíveis
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        PDO::ATTR_EMULATE_PREPARES => true, // Importante para dumps SQL longos
    ];

    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, $options);

    // --- AUTO-INSTALAÇÃO ---
    // Verifica se a tabela usuarios existe para decidir se instala
    try {
        $pdo->query("SELECT 1 FROM usuarios LIMIT 1");
        $tabelaExiste = true;
    } catch (PDOException $e) {
        $tabelaExiste = false;
    }

    if (!$tabelaExiste) {
        $sqlFile = __DIR__ . '/setup.sql'; 
        
        if (file_exists($sqlFile)) {
            // Lê o arquivo SQL
            $sql = file_get_contents($sqlFile);
            
            // Tenta executar o Dump inteiro
            try {
                $pdo->exec($sql);
                error_log("Sucesso: Tabelas importadas automaticamente.");
                
                // Remove o arquivo após o sucesso
                if (is_writable($sqlFile)) {
                    unlink($sqlFile);
                }
            } catch (PDOException $e) {
                // Se der erro no SQL (ex: sintaxe), mostra na tela para debug
                die("Erro fatal na importação do Banco de Dados: " . $e->getMessage());
            }
        } else {
            die("Erro crítico: Banco de dados vazio e arquivo 'setup.sql' não encontrado.");
        }
    }
    // -----------------------

} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Connection refused') !== false) {
        die("Inicializando banco de dados... Aguarde 10 segundos e recarregue.");
    }
    die("Erro de conexão: " . $e->getMessage());
}
?>