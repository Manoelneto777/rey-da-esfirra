<?php
/**
 * backend/produtos.php
 * GET /backend/produtos.php
 * GET /backend/produtos.php?categoria=Tradicionais
 *
 * Retorna lista de produtos disponíveis em JSON.
 */

require_once __DIR__ . '/db.php';

cabecalhosApi();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responder(['sucesso' => false, 'erro' => 'Método não permitido.'], 405);
}

$conn      = db();
$categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';

// Filtra por categoria se informada e válida
if ($categoria !== '' && $categoria !== 'Todos') {
    $stmt = $conn->prepare(
        'SELECT id, nome, descricao, preco, categoria, imagem
           FROM produtos
          WHERE disponivel = 1
            AND categoria  = ?
          ORDER BY nome'
    );
    $stmt->bind_param('s', $categoria);
} else {
    $stmt = $conn->prepare(
        'SELECT id, nome, descricao, preco, categoria, imagem
           FROM produtos
          WHERE disponivel = 1
          ORDER BY categoria, nome'
    );
}

$stmt->execute();
$result = $stmt->get_result();

$produtos = [];
while ($row = $result->fetch_assoc()) {
    // Garante que preco seja float no JSON
    $row['preco'] = (float) $row['preco'];
    $produtos[]   = $row;
}

$stmt->close();
$conn->close();

responder(['sucesso' => true, 'data' => $produtos]);
