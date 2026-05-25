<?php
/**
 * backend/pedidos.php
 * POST /backend/pedidos.php — salva pedido + itens no banco.
 *
 * ┌─────────────────────────────────────────────────────────────┐
 * │ MUDANÇA DE SEGURANÇA                                         │
 * │ Antes confiávamos no preço e no total enviados pelo cliente. │
 * │ Isso permite fraude (mandar total = R$ 0,01).                │
 * │ Agora o servidor IGNORA o preço do cliente e RECALCULA tudo  │
 * │ buscando o preço real de cada produto no banco.              │
 * └─────────────────────────────────────────────────────────────┘
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Autoload simples para as classes Models\*.
spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) require_once $file;
});

use Models\Pedido;
use Models\Produto;

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'JSON inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Dados do cliente ──────────────────────────────────────────
$nome     = trim($body['nome_cliente'] ?? '');
$telefone = trim($body['telefone']     ?? '');
$endereco = trim($body['endereco']     ?? '');
$obs      = trim($body['observacoes']  ?? '');
$itens    = $body['itens'] ?? [];   // [{ produto_id, quantidade }, ...]

// ── Validação dos campos ──────────────────────────────────────
$erros = [];
if ($nome === '')        $erros[] = 'Nome obrigatório.';
if ($telefone === '')    $erros[] = 'Telefone obrigatório.';
if ($endereco === '')    $erros[] = 'Endereço obrigatório.';
if (empty($itens))       $erros[] = 'Pedido sem itens.';

if ($erros) {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'erros' => $erros], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $produtoModel = new Produto();

    // ── Recalcula preço e total no SERVIDOR ───────────────────
    // Para cada item, buscamos o produto real no banco e usamos o
    // preço DELE — nunca o preço que veio do navegador.
    $itensValidados = [];
    $total          = 0.0;

    foreach ($itens as $item) {
        $produtoId  = (int) ($item['produto_id'] ?? 0);
        $quantidade = max(1, (int) ($item['quantidade'] ?? 1)); // nunca menor que 1

        $produto = $produtoModel->findById($produtoId);

        // Produto inexistente ou indisponível → recusa o pedido inteiro.
        if (!$produto || (int) $produto['disponivel'] !== 1) {
            http_response_code(422);
            echo json_encode([
                'sucesso' => false,
                'erro'    => "Produto #{$produtoId} indisponível.",
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $precoReal = (float) $produto['preco'];
        $total    += $precoReal * $quantidade;

        $itensValidados[] = [
            'produto_id' => $produtoId,
            'quantidade' => $quantidade,
            'preco'      => $precoReal, // preço de verdade, vindo do banco
        ];
    }

    // ── Persiste pedido + itens em transação atômica ──────────
    $pedidoModel = new Pedido();
    $pedidoId = $pedidoModel->salvarComItens(
        [
            'nome_cliente' => $nome,
            'telefone'     => $telefone,
            'endereco'     => $endereco,
            'observacoes'  => $obs,
            'total'        => $total, // total calculado pelo servidor
        ],
        $itensValidados
    );

    http_response_code(201);
    echo json_encode([
        'sucesso'   => true,
        'pedido_id' => $pedidoId,
        'total'     => $total,
        'mensagem'  => "Pedido #{$pedidoId} recebido com sucesso!",
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    http_response_code(500);
    error_log('[pedidos] ' . $e->getMessage());
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao salvar pedido.'], JSON_UNESCAPED_UNICODE);
}
