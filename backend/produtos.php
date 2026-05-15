<?php
/**
 * backend/produtos.php
 * GET /backend/produtos.php?categoria=Tradicionais
 * Retorna lista de produtos em JSON.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) require_once $file;
});

use Models\Produto;

try {
    $model     = new Produto();
    $categoria = trim($_GET['categoria'] ?? '');
    $produtos  = $model->listarDisponiveis($categoria);

    foreach ($produtos as &$p) {
        $p['preco'] = (float) $p['preco'];
    }

    echo json_encode(['sucesso' => true, 'data' => $produtos], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao buscar produtos.']);
}
