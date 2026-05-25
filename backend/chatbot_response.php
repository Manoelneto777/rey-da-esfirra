<?php
/**
 * backend/chatbot_response.php
 *
 * Endpoint principal do chatbot.
 * Recebe POST JSON do frontend (chatbot.js) e retorna JSON.
 */

declare(strict_types=1);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 1. OBRIGATÓRIO: Carregar as funções auxiliares primeiro
require_once __DIR__ . '/chat_helpers.php';

// 2. AUTOLOAD À PROVA DE FALHAS (Garante a importação do banco de dados)
spl_autoload_register(function (string $class) {
    $classPath = str_replace('\\', '/', $class);
    // Usa __DIR__ para partir da pasta backend/ exata onde estamos
    $file = __DIR__ . '/' . $classPath . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// 3. Importação das classes
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
    // Instancia os modelos de banco de dados
    $chatMessageModel   = new ChatMessage();
    $chatbotOptionModel = new ChatbotOption();

    /**
     * Salva mensagem do usuário antes de buscar histórico,
     * permitindo que a sessão fique completa no banco.
     */
    $chatMessageModel->salvar($mensagem, 'user', $sessaoId);

    // 2. Detecta intencao manual
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
    $tipo = 'bot';

    if ($keyword !== null) {
        // 3a. Encontrou keyword — busca resposta manual 
        $respostaTexto = buscarRespostaManual($keyword);
    }

    if ($respostaTexto === null && Config::CHATBOT_USE_AI) {
        // 4. Nenhuma resposta manual — tenta IA (OpenRouter)
        $respostaTextoIA = chamarOpenRouter($mensagem);
        
        // Se a API retornar o alerta de debug, registramos o erro no log e anulamos a resposta
        if ($respostaTextoIA !== null && str_contains($respostaTextoIA, '🚨')) {
            error_log("Falha na API da OpenRouter: " . $respostaTextoIA);
            $respostaTexto = null; 
        } else {
            $respostaTexto = $respostaTextoIA;
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

    // 7. Retorna JSON ao frontend
    $resultadoFinal = formatarResposta($respostaTexto, $tipo);

    // Injeta os botões de ação rápida
    $resultadoFinal['botoes'] = ['Cardápio', 'Horários', 'Localização', 'Preços', 'Delivery'];

    echo json_encode($resultadoFinal);

} catch (\Throwable $e) {
    // Em produção, gravamos o erro no log do servidor, mas não na tela do cliente
    http_response_code(500);
    error_log('[chatbot_response] ERRO FATAL: ' . $e->getMessage() . ' no arquivo ' . basename($e->getFile()) . ' linha ' . $e->getLine());
    echo json_encode(formatarErro('Erro interno no servidor. Tente novamente mais tarde.'));
}