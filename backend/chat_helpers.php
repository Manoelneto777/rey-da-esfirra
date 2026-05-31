<?php
/**
 * backend/chat_helpers.php
 *
 * Funções auxiliares do chatbot (sem estado, fáceis de testar).
 * Responsabilidades:
 *   - autoload simples das classes (Core\* e Models\*)
 *   - detectarIntencao()        : transforma a frase do usuário numa "intenção"
 *   - respostaManualFallback()  : textos manuais embutidos (rede de segurança)
 *   - formatarResposta()/Erro() : padronizam o JSON devolvido ao front
 *   - respostaPadraoTexto()     : fallback quando nada é entendido
 *   - chamarOpenRouter()        : integra com a IA via cURL (corrigido)
 */

use Core\Config;
// ─────────────────────────────────────────────────────────────
// AUTOLOAD SIMPLES (sem Composer)
// Converte "Core\Config" em "backend/Core/Config.php" e carrega.
// Fica aqui porque chat_helpers.php é sempre o primeiro require do endpoint.
// ─────────────────────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// ─────────────────────────────────────────────────────────────
// 1. DETECTAR INTENÇÃO
// Recebe a mensagem JÁ normalizada (minúscula) e devolve uma
// "intenção" canônica, ou null se não reconhecer nada.
//
// Mantemos os dois jeitos de escrever (com e sem acento) porque o
// usuário digita de tudo. A intenção retornada é sempre SEM acento,
// para casar com a coluna `intencao` do banco.
// ─────────────────────────────────────────────────────────────
function detectarIntencao(string $mensagemNorm): ?string
{
    // mapa: intenção canônica => lista de termos que a disparam
    $mapa = [
        'cardapio'    => ['cardapio', 'cardápio', 'menu', 'sabores'],
        'horarios'    => ['horario', 'horário', 'aberto', 'funciona', 'fecha'],
        'localizacao' => ['local', 'endereço', 'endereco', 'onde fica', 'localiza'],
        'precos'      => ['preco', 'preço', 'valor', 'quanto custa'],
        'delivery'    => ['delivery', 'entrega', 'taxa', 'frete'],
    ];

    foreach ($mapa as $intencao => $termos) {
        foreach ($termos as $termo) {
            if (str_contains($mensagemNorm, $termo)) {
                return $intencao; // primeira intenção encontrada vence
            }
        }
    }

    return null; // nada reconhecido → vai pra IA ou pro fallback padrão
}

// ─────────────────────────────────────────────────────────────
// 2. FALLBACK MANUAL (rede de segurança)
// O ChatService busca a resposta PRIMEIRO no banco (chatbot_options).
// Se a linha não existir (ex.: seed não rodou), caímos aqui.
// Assim o chat funciona em qualquer máquina, mesmo sem popular a tabela.
// ─────────────────────────────────────────────────────────────
function respostaManualFallback(string $intencao): ?string
{
    switch ($intencao) {
     case 'cardapio':
            return "👑 *Nosso Cardápio*\n\n" .
                   "🍕 *Tradicionais e Especiais:* a partir de R$ 5,49 (Carne, Frango, Queijo, Bacon, etc)\n" .
                   "🍤 *Premium:* R$ 12,00 (Camarão)\n" .
                   "🍫 *Doces e Açaí:* a partir de R$ 5,99 (Chocolate, Banana, Açaí 300ml)\n" .
                   "🍟 *Combos:* 10 un. R$ 65,00 · 20 un. Mix R$ 120,00\n" .
                   "🥤 *Bebidas:* Sucos, Refrigerantes e Água (a partir de R$ 4,00)\n\n" .
                   "Navegue pela tela para ver as fotos deliciosas ou me peça uma sugestão! Qual vai ser o pedido de hoje?";

        case 'horarios':
            return "⏰ *Horários*\n\n" .
                   "Seg a Qui: 13h às 22:30h\n" .
                   "Sex e Sáb: 13h às 23h\n" .
                   "Domingo: 13h às 22h";

        case 'localizacao':
            return "📍 *Onde estamos*\n\n" .
                   "Avenida Principal, 580 — Centro\n" .
                   "Capoeiruçu — Bahia";

        case 'precos':
            return "💰 *Preços base*\n\n" .
                   "Esfirras a partir de R$ 6,50.\n" .
                   "Refrigerantes a partir de R$ 5,00.\n" .
                   "Toque em *Cardápio* para ver tudo!";

        case 'delivery':
            return "🛵 *Delivery*\n\n" .
                   "Tempo médio: 30 a 50 min.\n" .
                   "Taxa: R$ 5,00 (grátis acima de R$ 60).\n" .
                   "Pedido mínimo: R$ 25,00.";

        default:
            return null;
    }
}

// ─────────────────────────────────────────────────────────────
// 3. FORMATADORES DE RESPOSTA (padronização do JSON)
// ─────────────────────────────────────────────────────────────

/**
 * Monta o payload de sucesso devolvido ao frontend.
 *
 * @param  string $texto   Texto da resposta
 * @param  string $tipo    'bot' (manual) | 'ai' (inteligência artificial)
 * @param  array  $botoes  Botões de ação rápida (opcional)
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
 * Monta o payload de erro. O motivo técnico vai em 'erro' (pro dev ver no
 * F12/log); o cliente final só lê a mensagem amigável de 'texto'.
 *
 * @param  string $motivoTecnico
 * @return array
 */
function formatarErro(string $motivoTecnico): array
{
    return [
        'sucesso' => false,
        'erro'    => $motivoTecnico,
        'texto'   => 'Desculpe, tivemos um probleminha aqui. Tente novamente. 🙏',
        'tipo'    => 'bot',
    ];
}

/**
 * Texto padrão quando o bot não entende e a IA não respondeu.
 * @return string
 */
function respostaPadraoTexto(): string
{
    return 'Não entendi muito bem 🤔. Posso te ajudar com Cardápio, Horários, ' .
           'Localização, Preços ou Delivery — é só tocar num botão abaixo!';
}

// ─────────────────────────────────────────────────────────────
// 4. CHAMAR OPENROUTER (IA) — VERSÃO CORRIGIDA
//
// A versão antiga estava quebrada: nunca executava curl_exec() e
// usava variáveis ($response, $error, $httpCode) que não existiam,
// misturando estilo Guzzle com cURL. Aqui o fluxo está completo:
//   init → setopt → exec → lê código HTTP e erro → close.
// Em qualquer falha retornamos null para o ChatService cair no manual.
// ─────────────────────────────────────────────────────────────
function chamarOpenRouter(string $mensagem): ?string
{
    $apiKey = Config::OPENROUTER_API_KEY;

    // Sem chave configurada → não tenta (deixa o manual assumir).
    if (empty($apiKey) || str_contains($apiKey, 'COLE_SUA_CHAVE_AQUI')) {
        error_log('[OpenRouter] Chave de API ausente.');
        return null;
    }

    $payload = json_encode([
        'model'      => Config::OPENROUTER_MODEL,
        'max_tokens' => Config::CHATBOT_MAX_TOKENS,
        'messages'   => [
            ['role' => 'system', 'content' => Config::CHATBOT_SYSTEM_PROMPT],
            ['role' => 'user',   'content' => $mensagem],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init(Config::OPENROUTER_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 30,
        // Em produção o ideal é manter a verificação TLS LIGADA
        // (CURLOPT_SSL_VERIFYPEER => true) e apontar um CA bundle.
        // Deixe false só se o ambiente local não tiver certificados.
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'HTTP-Referer: http://localhost',
            'X-Title: Rei da Esfirra Bot',
        ],
    ]);

    $response = curl_exec($ch);                              // <- ESTAVA FALTANDO
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erroCurl = curl_error($ch);
    curl_close($ch);

    // Falha de rede ou status diferente de 200 → loga e devolve null.
    if ($response === false || $erroCurl !== '' || $httpCode !== 200) {
        error_log("[OpenRouter] Falha — HTTP {$httpCode} | cURL: {$erroCurl} | corpo: {$response}");
        return null;
    }

    $data = json_decode($response, true);

    // Estrutura esperada da OpenRouter: choices[0].message.content
    return $data['choices'][0]['message']['content'] ?? null;
}
