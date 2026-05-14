<?php
/**
 * backend/chat_helpers.php
 *
 * Funcoes auxiliares do chatbot.
 * Responsabilidades:
 *  - detectarIntencao()   : identifica palavras-chave na mensagem
 *  - buscarRespostaManual(): consulta chatbot_options no banco
 *  - formatarResposta()   : padroniza a resposta final
 *  - chamarOpenRouter()   : integra com a API de IA
 */

use Core\Config;
use Models\ChatbotOption;

// ─── Autoload simples (sem Composer) ────────────────────────
spl_autoload_register(function (string $class): void {
    $base = __DIR__;
    $file = $base . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// ─────────────────────────────────────────────────────────────
// 1. DETECTAR INTENCAO
// Retorna a primeira keyword encontrada na mensagem do usuario
// ─────────────────────────────────────────────────────────────

/**
 * Varre o texto do usuario e retorna a primeira keyword
 * que aparecer na base de chatbot_options.
 *
 * @param  string $mensagem   Texto do usuario (ja em lowercase)
 * @param  array  $opcoes     Resultado de ChatbotOption::listarAtivas()
 * @return string|null        Keyword encontrada ou null
 */
function detectarIntencao(string $mensagem, array $opcoes): ?string
{
    foreach ($opcoes as $opcao) {
        $keyword = mb_strtolower(trim($opcao['keyword']));
        if ($keyword !== '' && str_contains($mensagem, $keyword)) {
            return $keyword;
        }
    }
    return null;
}

// ─────────────────────────────────────────────────────────────
// 2. BUSCAR RESPOSTA MANUAL
// Consulta o banco pela keyword detectada
// ─────────────────────────────────────────────────────────────

/**
 * Busca a resposta manual no banco a partir da keyword.
 *
 * @param  string $keyword
 * @return string|null  Resposta encontrada ou null
 */
function buscarRespostaManual(string $keyword): ?string
{
    $model  = new ChatbotOption();
    $opcao  = $model->buscarPorKeyword($keyword);
    return $opcao ? $opcao['response'] : null;
}

// ─────────────────────────────────────────────────────────────
// 3. FORMATAR RESPOSTA
// Padroniza o payload JSON de retorno ao frontend
// ─────────────────────────────────────────────────────────────

/**
 * Cria o array de resposta padronizado.
 *
 * @param  string $texto  Texto da resposta
 * @param  string $tipo   'bot' (manual) | 'ai' (inteligencia artificial)
 * @param  array  $botoes Botoes de acao rapida opcionais
 * @return array
 */
function formatarResposta(string $texto, string $tipo = 'bot', array $botoes = []): array
{
    return [
        'sucesso' => true,
        'tipo'    => $tipo,
        'texto'   => $texto,
        'botoes'  => $botoes,
    ];
}

/**
 * Cria o payload de erro padronizado.
 *
 * @param  string $mensagem
 * @return array
 */
function formatarErro(string $mensagem): array
{
    return [
        'sucesso' => false,
        'erro'    => $mensagem,
        'texto'   => 'Desculpe, ocorreu um erro. Tente novamente.',
        'tipo'    => 'bot',
    ];
}

// ─────────────────────────────────────────────────────────────
// 4. RESPOSTA PADRAO
// Quando nenhuma keyword e encontrada e IA esta desligada
// ─────────────────────────────────────────────────────────────

/**
 * Retorna a resposta padrao (fallback) com botoes de acao rapida.
 *
 * @return array
 */
function respostaPadrao(): array
{
    return formatarResposta(
        'Nao entendi muito bem. Posso te ajudar com:',
        'bot',
        ['Cardapio', 'Horarios', 'Localizacao', 'Preco', 'Delivery']
    );
}

// ─────────────────────────────────────────────────────────────
// 5. CHAMAR OPENROUTER (IA)
// Integra com a API via cURL (sem dependencia de Guzzle)
// ─────────────────────────────────────────────────────────────

/**
 * Envia a mensagem do usuario para a API OpenRouter e retorna
 * a resposta gerada pela IA.
 *
 * Usa cURL nativo do PHP para evitar dependencia do Composer.
 * Se a chave API estiver configurada via Composer/Guzzle,
 * veja a funcao chamarOpenRouterGuzzle() abaixo.
 *
 * @param  string $mensagem  Mensagem do usuario
 * @return string|null       Resposta da IA ou null em caso de falha
 */
function chamarOpenRouter(string $mensagem): ?string
{
    $apiKey = Config::OPENROUTER_API_KEY;

    if (empty($apiKey) || $apiKey === 'sk-or-v1-SUA_CHAVE_AQUI') {
        return null; // chave nao configurada
    }

    $payload = json_encode([
        'model'      => Config::OPENROUTER_MODEL,
        'max_tokens' => Config::CHATBOT_MAX_TOKENS,
        'messages'   => [
            [
                'role'    => 'system',
                'content' => Config::CHATBOT_SYSTEM_PROMPT,
            ],
            [
                'role'    => 'user',
                'content' => $mensagem,
            ],
        ],
    ]);

    $ch = curl_init(Config::OPENROUTER_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'HTTP-Referer: http://localhost',
            'X-Title: Rei da Esfirra Bot',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error || $httpCode !== 200) {
        error_log("[ChatBot-IA] cURL error: {$error} | HTTP {$httpCode}");
        return null;
    }

    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? null;
}

// ─────────────────────────────────────────────────────────────
// 5b. CHAMAR OPENROUTER COM GUZZLE (opcional / com Composer)
// Descomente e use se o Guzzle estiver instalado via Composer
// ─────────────────────────────────────────────────────────────

/**
 * Versao com Guzzle HTTP Client.
 * Requer: composer require guzzlehttp/guzzle
 *
 * @param  string $mensagem
 * @return string|null
 */
function chamarOpenRouterGuzzle(string $mensagem): ?string
{
    // Descomente as linhas abaixo apos instalar o Guzzle:
    //
    // $client = new \GuzzleHttp\Client(['timeout' => 15]);
    //
    // try {
    //     $response = $client->post(Config::OPENROUTER_API_URL, [
    //         'headers' => [
    //             'Authorization' => 'Bearer ' . Config::OPENROUTER_API_KEY,
    //             'Content-Type'  => 'application/json',
    //             'HTTP-Referer'  => 'http://localhost',
    //             'X-Title'       => 'Rei da Esfirra Bot',
    //         ],
    //         'json' => [
    //             'model'      => Config::OPENROUTER_MODEL,
    //             'max_tokens' => Config::CHATBOT_MAX_TOKENS,
    //             'messages'   => [
    //                 ['role' => 'system', 'content' => Config::CHATBOT_SYSTEM_PROMPT],
    //                 ['role' => 'user',   'content' => $mensagem],
    //             ],
    //         ],
    //     ]);
    //
    //     $data = json_decode($response->getBody()->getContents(), true);
    //     return $data['choices'][0]['message']['content'] ?? null;
    //
    // } catch (\GuzzleHttp\Exception\GuzzleException $e) {
    //     error_log('[ChatBot-IA] Guzzle error: ' . $e->getMessage());
    //     return null;
    // }

    return null; // remova esta linha ao descomentar o bloco acima
}
