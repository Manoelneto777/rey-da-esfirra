<?php
/**
 * backend/chatbot_response.php
 *
 * Endpoint HTTP do chatbot. Recebe POST JSON do front (chatbot.js) e
 * devolve JSON. Aqui só cuidamos de HTTP — toda a regra está no ChatService.
 *
 * ┌─────────────────────────────────────────────────────────────┐
 * │ ANTES: o endpoint reimplementava o fluxo na mão, com colunas │
 * │ erradas e SEM usar chat_conversations.                       │
 * │ AGORA: ele é "fino" e delega tudo para o ChatService, que    │
 * │ cumpre o fluxo exigido: conversa → user → resposta → bot.    │
 * └─────────────────────────────────────────────────────────────┘
 */

declare(strict_types=1);

// Em desenvolvimento, ver erros ajuda. Em produção, troque para 0
// e confie no error_log (os erros ficam no log do servidor, não na tela).
ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // em produção, restrinja ao seu domínio
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 1) Carrega as funções auxiliares (que também registram o autoload).
require_once __DIR__ . '/chat_helpers.php';
// 2) Carrega o serviço (não é namespaced → require direto).
require_once __DIR__ . '/ChatService.php';

// Pré-flight do CORS (navegador manda OPTIONS antes do POST).
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Só aceitamos POST aqui.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(formatarErro('Método não permitido.'), JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Lê e valida o corpo JSON ──────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(formatarErro('JSON inválido.'), JSON_UNESCAPED_UNICODE);
    exit;
}

$mensagem = trim((string) ($body['mensagem'] ?? ''));

// conversation_id pode vir null (1ª mensagem) — convertemos para int|null.
$conversationId = isset($body['conversation_id']) && $body['conversation_id'] !== null
    ? (int) $body['conversation_id']
    : null;

if ($mensagem === '') {
    http_response_code(422);
    echo json_encode(formatarErro('Mensagem vazia.'), JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Processa pelo serviço ─────────────────────────────────────
try {
    $service   = new ChatService();
    $resultado = $service->handleMessage($mensagem, $conversationId, null);

    // Botões de ação rápida que o front renderiza embaixo da resposta.
    $resultado['botoes'] = ['Cardápio', 'Horários', 'Localização', 'Preços', 'Delivery'];

    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);

} catch (\InvalidArgumentException $e) {
    // Erro "do usuário" (mensagem vazia/longa) → 422, sem alarde.
    http_response_code(422);
    echo json_encode(formatarErro($e->getMessage()), JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    // Erro inesperado (banco, IA, etc.) → loga detalhe e devolve genérico.
    http_response_code(500);
    error_log('[chatbot_response] ' . $e->getMessage()
        . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
    echo json_encode(formatarErro('Erro interno no servidor.'), JSON_UNESCAPED_UNICODE);
}
