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
header('Access-Control-Allow-Origin: *'); // Permite que o JS fale com o PHP
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');


// 1. INCLUA O HELPERS PRIMEIRO (Para o PHP conhecer a função formatarErro)
// 1. OBRIGATÓRIO: Carregar as funções de erro primeiro
require_once __DIR__ . '/chat_helpers.php';

// 2. O AUTOLOAD (O "motor" que acha as pastas Core e Models sozinho)
spl_autoload_register(function (string $class) {
    $classPath = str_replace('\\', '/', $class);
    // Usamos o caminho real do Windows para não ter erro de localização
    $file = $_SERVER['DOCUMENT_ROOT'] . '/chatbot/' . $classPath . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});


// 3. AGORA você pode usar as classes sem precisar de require individual
use Core\Config;
use Core\Connect;
use Models\ChatMessage;
use Models\ChatbotOption;

// ── Valida metodo ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(formatarErro('Metodo nao permitido.'));
    exit;
}

// ── Le e valida payload JSON ──────────────────────────────────
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

$mensagem = trim($body['mensagem'] ?? '');
$sessaoId = trim($body['sessao_id'] ?? 'anonimo');

if ($mensagem === '') {
    http_response_code(422);
    echo json_encode(formatarErro('Mensagem vazia.'));
    exit;
}

// ── Normaliza mensagem para comparacao ────────────────────────
$mensagemNorm = mb_strtolower($mensagem, 'UTF-8');

try {
    $chatMessageModel   = new ChatMessage();
    $chatbotOptionModel = new ChatbotOption();

    // 1. Salva mensagem do usuario no banco
    $chatMessageModel->salvar($mensagem, 'user', $sessaoId);

    // 3. Detecta intencao ignorando o banco de dados temporariamente
    $keyword = null;
    if (str_contains($mensagemNorm, 'cardapio') || str_contains($mensagemNorm, 'cardápio')) {
        $keyword = 'cardapio';
    } elseif (str_contains($mensagemNorm, 'horario') || str_contains($mensagemNorm, 'horário')) {
        $keyword = 'horarios';
    } elseif (str_contains($mensagemNorm, 'local') || str_contains($mensagemNorm, 'endereço')) {
        $keyword = 'localizacao';
    } elseif (str_contains($mensagemNorm, 'preco') || str_contains($mensagemNorm, 'preço')) {
        $keyword = 'precos';
    } elseif (str_contains($mensagemNorm, 'delivery') || str_contains($mensagemNorm, 'entrega')) {
        $keyword = 'delivery';
    }

    $respostaTexto = null;
    $tipo          = 'bot';

    if ($keyword !== null) {
        // 3a. Encontrou keyword — busca resposta manual no banco
        $respostaTexto = buscarRespostaManual($keyword);
    }

    if ($respostaTexto === null && Config::CHATBOT_USE_AI) {
        // 4. Nenhuma resposta manual — tenta IA (OpenRouter)
        $respostaTexto = chamarOpenRouter($mensagem);
        if ($respostaTexto !== null) {
            $tipo = 'ai';
        }
    }

    if ($respostaTexto === null) {
        // 5. Fallback: resposta padrao com botoes de acao rapida
        $payload = respostaPadrao();
        $chatMessageModel->salvar($payload['texto'], 'bot', $sessaoId);
        echo json_encode($payload);
        exit;
    }

    // 6. Salva resposta no banco
    $chatMessageModel->salvar($respostaTexto, $tipo, $sessaoId);

// 7. Retorna JSON ao frontend
$resultadoFinal = formatarResposta($respostaTexto, $tipo);

// Injeta os botões de ação rápida no final de TODAS as respostas
$resultadoFinal['botoes'] = ['Cardápio', 'Horários', 'Localização', 'Preços', 'Delivery'];

echo json_encode($resultadoFinal);

} catch (\RuntimeException $e) {
    http_response_code(500);
    $msg = Config::APP_DEBUG ? $e->getMessage() : 'Erro interno. Tente novamente.';
    echo json_encode(formatarErro($msg));
} catch (\Throwable $e) {
    http_response_code(500);
    error_log('[chatbot_response] ' . $e->getMessage());
    echo json_encode(formatarErro('Erro inesperado. Tente novamente.'));
}
