<?php
// admin/reindexar.php
// Rode este script apenas uma vez via navegador para atualizar o passado.

require 'auth.php';
verificarAdmin();
require '../config.php';
require '../vendor/autoload.php';
use Smalot\PdfParser\Parser;

// Aumenta tempo de execução (processar muitos PDFs demora)
set_time_limit(300); 

echo "<h1>Iniciando Reindexação...</h1>";

$parser = new Parser();
$stmt = $pdo->query("SELECT id, arquivo_path FROM edicoes WHERE conteudo_indexado IS NULL OR conteudo_indexado = ''");

$count = 0;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $caminhoFisico = "../uploads/" . $row['arquivo_path'];
    
    if (file_exists($caminhoFisico)) {
        try {
            $pdf = $parser->parseFile($caminhoFisico);
            $texto = $pdf->getText();
            
            $update = $pdo->prepare("UPDATE edicoes SET conteudo_indexado = ? WHERE id = ?");
            $update->execute([$texto, $row['id']]);
            
            echo "ID {$row['id']} processado.<br>";
            $count++;
        } catch (Exception $e) {
            echo "Erro no ID {$row['id']}: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "Arquivo não encontrado para ID {$row['id']}<br>";
    }
}

echo "<h2>Concluído! $count edições indexadas.</h2>";
echo "<a href='index.php'>Voltar</a>";
?>