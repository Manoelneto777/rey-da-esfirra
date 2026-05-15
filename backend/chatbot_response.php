<?php
/**
 * backend/chatbot_response.php
 *
 * Endpoint principal do chatbot.
 * Recebe POST JSON do frontend (chatbot.js) e retorna JSON.
 *
 * Fluxo:
 *  1. Valida a requisicao
 *  2. Salva mensagem do usuario no banco
 *  3. Tenta resposta manual (banco de palavras-chave)
 *  4. Se nao encontrar e IA estiver ativa, chama OpenRouter
 *  5. Salva resposta no banco
 *  6. Retorna JSON ao frontend
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/chat_helpers.php';

spl_autoload_register(function (string $class): void {
    $classPath = str_replace('\\', '/', $class);
    $file = $_SERVER['DOCUMENT_ROOT'] . '/chatbot/' . $classPath . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use Core\Config;
use Models\ChatMessage;
use Models\ChatbotOption;

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(formatarErro('Metodo nao permitido.'), JSON_UNESCAPED_UNICODE);
    exit;
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(formatarErro('JSON invalido.'), JSON_UNESCAPED_UNICODE);
    exit;
}

$mensagem = trim((string) ($body['mensagem'] ?? ''));
$sessaoId = trim((string) ($body['sessao_id'] ?? 'anonimo'));

if ($mensagem === '') {
    http_response_code(422);
    echo json_encode(formatarErro('Mensagem vazia.'), JSON_UNESCAPED_UNICODE);
    exit;
}

$mensagemNorm = mb_strtolower($mensagem, 'UTF-8');

try {
    $chatMessageModel   = new ChatMessage();
    $chatbotOptionModel = new ChatbotOption();

    /**
     * Salva mensagem do usuário antes de buscar histórico,
     * permitindo que a sessão fique completa no banco.
     */
    $chatMessageModel->salvar($mensagem, 'user', $sessaoId);

    $opcoes = $chatbotOptionModel->listarAtivas();

    $keyword = detectarIntencao($mensagemNorm, $opcoes);

    $respostaTexto = null;
    $tipo = 'bot';

    if ($keyword !== null) {
        $respostaTexto = buscarRespostaManual($keyword);
    }

    if ($respostaTexto === null && Config::CHATBOT_USE_AI) {
        /**
         * Busca as últimas 6 mensagens da sessão.
         * Esse histórico será enviado para a IA como contexto.
         */
        $historico = $chatMessageModel->historicoDaSessao($sessaoId, 6);

        $respostaTexto = chamarOpenRouterGuzzle($mensagem, $historico);

        if ($respostaTexto !== null) {
            $tipo = 'ai';
        }
    }

    if ($respostaTexto === null) {
        $payload = respostaPadrao();

        $chatMessageModel->salvar($payload['texto'], 'bot', $sessaoId);

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $chatMessageModel->salvar($respostaTexto, $tipo, $sessaoId);

    echo json_encode(
        formatarResposta($respostaTexto, $tipo),
        JSON_UNESCAPED_UNICODE
    );
} catch (\RuntimeException $e) {
    http_response_code(500);

    $msg = Config::APP_DEBUG
        ? $e->getMessage()
        : 'Erro interno. Tente novamente.';

    echo json_encode(formatarErro($msg), JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);

    error_log('[chatbot_response] ' . $e->getMessage());

    echo json_encode(
        formatarErro('Erro inesperado. Tente novamente.'),
        JSON_UNESCAPED_UNICODE
    );
}