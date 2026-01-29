<?php
// config.php - Versão 0.6 (Blindada para Dumps + Cloudflare + Auto-Setup)

// 1. Configurações do Banco de Dados (Ambiente ou Padrão Local)
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'doeca_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

// 2. Configurações do Cloudflare Turnstile (Ambiente ou Vazio para Dev)
// Se estiver no Docker, pega do ENV. Se for XAMPP local, fica vazio (o login.php fará o bypass)
$cf_site_key   = getenv('CF_SITE_KEY')   ?: '';
$cf_secret_key = getenv('CF_SECRET_KEY') ?: '';

// Define as constantes globais para serem usadas no login.php
if (!defined('CLOUDFLARE_SITE_KEY'))   define('CLOUDFLARE_SITE_KEY', $cf_site_key);
if (!defined('CLOUDFLARE_SECRET_KEY')) define('CLOUDFLARE_SECRET_KEY', $cf_secret_key);

// 3. Configurações Gerais
date_default_timezone_set('America/Sao_Paulo'); // Garante logs com horário correto

try {
    // Adicionamos as opções para garantir codificação correta e erros visíveis
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        PDO::ATTR_EMULATE_PREPARES => true, // Importante para dumps SQL longos
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, $options);

    // --- AUTO-INSTALAÇÃO (Deploy Automático) ---
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
            // Aumenta tempo limite para importações pesadas
            set_time_limit(300); 

            // Lê o arquivo SQL
            $sql = file_get_contents($sqlFile);
            
            // Tenta executar o Dump inteiro
            try {
                $pdo->exec($sql);
                error_log("Sucesso: Tabelas do DOECA importadas automaticamente.");
                
                // Remove o arquivo setup.sql após o sucesso por segurança
                // (Comente a linha abaixo se quiser manter o arquivo em Dev)
                if (is_writable($sqlFile)) {
                    @unlink($sqlFile);
                }
            } catch (PDOException $e) {
                // Se der erro no SQL (ex: sintaxe), mostra na tela para debug
                die("Erro fatal na importação automática do Banco de Dados: <br>" . $e->getMessage());
            }
        } else {
            // Se não tem tabela e não tem setup.sql, o sistema não roda
            die("Erro crítico: Banco de dados vazio e arquivo 'setup.sql' não encontrado na raiz.");
        }
    }
    // -----------------------

} catch (PDOException $e) {
    // Tratamento para containers Docker que demoram a subir o MySQL
    if (strpos($e->getMessage(), 'Connection refused') !== false || strpos($e->getMessage(), 'server has gone away') !== false) {
        header("Refresh: 5"); // Tenta recarregar a cada 5 segundos
        die("Inicializando banco de dados... Aguarde, reconectando em 5 segundos.");
    }
    die("Erro de conexão com o banco: " . $e->getMessage());
}
?>