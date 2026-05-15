<?php
/**
 * backend/pedidos.php
 * POST /backend/pedidos.php
 * Salva pedido + itens no banco.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'erro' => 'Metodo nao permitido.']);
    exit;
}

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) require_once $file;
});

use Models\Pedido;

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'JSON invalido.']);
    exit;
}

$nome       = trim($body['nome_cliente'] ?? '');
$telefone   = trim($body['telefone']     ?? '');
$endereco   = trim($body['endereco']     ?? '');
$obs        = trim($body['observacoes']  ?? '');
$total      = (float) ($body['total']    ?? 0);
$itens      = $body['itens'] ?? [];

$erros = [];
if (!$nome)          $erros[] = 'Nome obrigatorio.';
if (!$telefone)      $erros[] = 'Telefone obrigatorio.';
if (!$endereco)      $erros[] = 'Endereco obrigatorio.';
if ($total <= 0)     $erros[] = 'Total invalido.';
if (empty($itens))   $erros[] = 'Pedido sem itens.';

if ($erros) {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'erros' => $erros]);
    exit;
}

try {
    $model    = new Pedido();
    $pedidoId = $model->salvarComItens(
        ['nome_cliente' => $nome, 'telefone' => $telefone,
         'endereco' => $endereco, 'observacoes' => $obs, 'total' => $total],
        $itens
    );

    http_response_code(201);
    echo json_encode([
        'sucesso'   => true,
        'pedido_id' => $pedidoId,
        'mensagem'  => "Pedido #{$pedidoId} recebido com sucesso!",
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao salvar pedido.']);
}
