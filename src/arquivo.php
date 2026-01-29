<?php
require 'config.php';

// Agora recebemos o ID, não o nome do arquivo
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    http_response_code(400);
    die("Requisição inválida.");
}

// Busca o caminho real no banco de dados
$stmt = $pdo->prepare("SELECT arquivo_path FROM edicoes WHERE id = ?");
$stmt->execute([$id]);
$edicao = $stmt->fetch(PDO::FETCH_ASSOC);

if ($edicao) {
    $pdo->prepare("UPDATE edicoes SET visualizacoes = visualizacoes + 1 WHERE id = ?")->execute([$id]);
    $caminhoRelativo = $edicao['arquivo_path']; // Ex: 2023/10/arquivo.pdf
    $caminhoCompleto = 'uploads/' . $caminhoRelativo;

    // Verifica se o arquivo existe no disco e previne 'directory traversal' checando se inicia com uploads/
    $realPath = realpath($caminhoCompleto);

    if ($realPath && file_exists($realPath) && str_starts_with($realPath, realpath('uploads'))) {

        // Pega o tipo de arquivo (garantir que é PDF)
        $ext = pathinfo($realPath, PATHINFO_EXTENSION);
        if (strtolower($ext) !== 'pdf')
            die("Arquivo inválido.");


        header('Content-Type: application/pdf');
        header('Content-Length: ' . filesize($realPath));
        // Sugere o nome do arquivo para download (pode usar o nº da edição se quiser)
        header('Content-Disposition: inline; filename="' . basename($caminhoRelativo) . '"');

        ob_clean();
        flush();
        readfile($realPath);
        exit;
    }
}

http_response_code(404);
echo "Arquivo não encontrado.";
?>