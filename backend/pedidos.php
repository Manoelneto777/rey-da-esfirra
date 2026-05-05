<?php
/**
 * backend/pedidos.php
 * POST /backend/pedidos.php
 *
 * Recebe JSON:
 * {
 *   "nome_cliente": "João Silva",
 *   "telefone":     "(75) 99999-9999",
 *   "endereco":     "Rua A, 10, Bairro",
 *   "observacoes":  "sem cebola",
 *   "total":        35.50,
 *   "itens": [
 *     { "produto_id": 1, "quantidade": 2, "preco": 7.50 },
 *     { "produto_id": 3, "quantidade": 1, "preco": 6.50 }
 *   ]
 * }
 *
 * Retorna:
 * { "sucesso": true, "pedido_id": 42 }
 */

require_once __DIR__ . '/db.php';

cabecalhosApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['sucesso' => false, 'erro' => 'Método não permitido.'], 405);
}

// ── 1. Ler e validar payload ──────────────────────
$data = lerJson();

$nome_cliente = trim($data['nome_cliente'] ?? '');
$telefone     = trim($data['telefone']     ?? '');
$endereco     = trim($data['endereco']     ?? '');
$observacoes  = trim($data['observacoes']  ?? '');
$total        = isset($data['total']) ? (float) $data['total'] : 0.0;
$itens        = $data['itens'] ?? [];

// Validações obrigatórias
$erros = [];
if ($nome_cliente === '')        $erros[] = 'Nome do cliente é obrigatório.';
if ($telefone === '')            $erros[] = 'Telefone é obrigatório.';
if ($endereco === '')            $erros[] = 'Endereço é obrigatório.';
if ($total <= 0)                 $erros[] = 'Total deve ser maior que zero.';
if (!is_array($itens) || empty($itens)) $erros[] = 'O pedido deve ter pelo menos um item.';

if (!empty($erros)) {
    responder(['sucesso' => false, 'erros' => $erros], 422);
}

// Validar cada item
foreach ($itens as $i => $item) {
    $pid = isset($item['produto_id']) ? (int) $item['produto_id'] : 0;
    $qty = isset($item['quantidade']) ? (int) $item['quantidade'] : 0;
    $prc = isset($item['preco'])      ? (float) $item['preco']    : 0.0;

    if ($pid <= 0) $erros[] = "Item $i: produto_id inválido.";
    if ($qty <= 0) $erros[] = "Item $i: quantidade deve ser maior que zero.";
    if ($prc <= 0) $erros[] = "Item $i: preço deve ser maior que zero.";
}

if (!empty($erros)) {
    responder(['sucesso' => false, 'erros' => $erros], 422);
}

// ── 2. Persistir no banco (transação) ────────────
$conn = db();
$conn->begin_transaction();

try {
    // Inserir pedido
    $stmt = $conn->prepare(
        'INSERT INTO pedidos (nome_cliente, telefone, endereco, observacoes, total)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('ssssd', $nome_cliente, $telefone, $endereco, $observacoes, $total);
    $stmt->execute();
    $pedido_id = (int) $conn->insert_id;
    $stmt->close();

    // Inserir itens
    $stmtItem = $conn->prepare(
        'INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco)
         VALUES (?, ?, ?, ?)'
    );

    foreach ($itens as $item) {
        $pid = (int)   $item['produto_id'];
        $qty = (int)   $item['quantidade'];
        $prc = (float) $item['preco'];
        $stmtItem->bind_param('iiid', $pedido_id, $pid, $qty, $prc);
        $stmtItem->execute();
    }

    $stmtItem->close();
    $conn->commit();

    responder([
        'sucesso'   => true,
        'pedido_id' => $pedido_id,
        'mensagem'  => "Pedido #{$pedido_id} recebido com sucesso! 🎉",
    ], 201);

} catch (Throwable $e) {
    $conn->rollback();
    responder(['sucesso' => false, 'erro' => 'Erro ao salvar o pedido. Tente novamente.'], 500);
} finally {
    $conn->close();
}
