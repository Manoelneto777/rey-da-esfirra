<?php
/**
 * backend/chatbot.php
 * POST /backend/chatbot.php
 * { "mensagem": "oi", "sessao_id": "abc123" }
 */

require_once __DIR__ . '/db.php';

cabecalhosApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['sucesso' => false, 'erro' => 'Método não permitido.'], 405);
}

$data     = lerJson();
$mensagem = mb_strtolower(trim($data['mensagem'] ?? ''));
$sessao   = trim($data['sessao_id'] ?? 'anon');

if ($mensagem === '') {
    responder(['sucesso' => false, 'erro' => 'Mensagem vazia.'], 422);
}

$resposta = gerarResposta($mensagem);

// Log no banco
$conn = db();
$stmt = $conn->prepare(
    'INSERT INTO chatbot_logs (sessao_id, mensagem_usuario, resposta_bot) VALUES (?, ?, ?)'
);
$log = $resposta['texto'];
$stmt->bind_param('sss', $sessao, $mensagem, $log);
$stmt->execute();
$stmt->close();
$conn->close();

responder($resposta);

// ─── Motor de respostas ──────────────────────────
function tem(string $msg, array $palavras): bool
{
    foreach ($palavras as $p) {
        if (str_contains($msg, $p)) return true;
    }
    return false;
}

function gerarResposta(string $msg): array
{
    if (tem($msg, ['oi','olá','ola','bom dia','boa tarde','boa noite','hey','hello'])) {
        return [
            'tipo'   => 'botoes',
            'texto'  => "👋 Olá! Bem-vindo ao *Rei da Esfirra*! 🥙\nComo posso te ajudar?",
            'botoes' => ['📋 Cardápio', '⏰ Horários', '📍 Localização', '💰 Preços', '🛵 Delivery'],
        ];
    }
    if (tem($msg, ['cardápio','cardapio','menu','esfirra','produto','comer','opção'])) {
        return [
            'tipo'  => 'texto',
            'texto' => "📋 *Nosso Cardápio:*\n\n🥙 *Tradicionais* — a partir de R\$ 6,50\n• Carne · Frango · Queijo · Calabresa\n\n🌿 *Especiais*\n• Palmito · Atum\n\n👑 *Premium*\n• Camarão — R\$ 12,00\n\n🍫 *Doces* — Banana · Chocolate\n\n📦 *Combos com desconto*\n• 10 un. — R\$ 65 · 20 Mix — R\$ 120\n\n🥤 *Bebidas* a partir de R\$ 5,00",
        ];
    }
    if (tem($msg, ['horário','horario','abre','fecha','funciona','quando'])) {
        return [
            'tipo'  => 'texto',
            'texto' => "⏰ *Horários:*\n\nSeg–Qui: 11h às 22h\nSex–Sáb: 11h às 23h\nDom: 10h às 21h\n\nAbertos todos os dias! 🎉",
        ];
    }
    if (tem($msg, ['endereço','endereco','onde','fica','local','mapa'])) {
        return [
            'tipo'  => 'texto',
            'texto' => "📍 *Endereço:*\n\nRua das Esfirras, 42 — Centro\nFeira de Santana - BA\n\n📞 (75) 99999-0000\n🛵 Delivery para toda a região!",
        ];
    }
    if (tem($msg, ['preço','preco','valor','custa','quanto'])) {
        return [
            'tipo'   => 'botoes',
            'texto'  => "💰 Tradicionais a partir de R\$ 6,50\nEspeciais a partir de R\$ 8,50\nPremium a partir de R\$ 12,00",
            'botoes' => ['📋 Ver Cardápio', '🛵 Delivery'],
        ];
    }
    if (tem($msg, ['delivery','entrega','motoboy','frete'])) {
        return [
            'tipo'  => 'texto',
            'texto' => "🛵 *Delivery:*\n\n⏱ 30 a 50 minutos\n💰 Taxa a partir de R\$ 5,00\n🎉 Frete grátis acima de R\$ 60\n📦 Pedido mínimo: R\$ 25,00",
        ];
    }
    if (tem($msg, ['pagamento','pagar','pix','cartão','cartao','dinheiro'])) {
        return [
            'tipo'  => 'texto',
            'texto' => "💳 *Pagamentos aceitos:*\n\n✅ PIX\n✅ Dinheiro\n✅ Débito e Crédito (todas bandeiras)",
        ];
    }
    if (tem($msg, ['tchau','bye','obrigado','obrigada','valeu','até'])) {
        return [
            'tipo'  => 'texto',
            'texto' => "😊 Obrigado! Volte sempre ao *Rei da Esfirra*! 👑",
        ];
    }
    return [
        'tipo'   => 'botoes',
        'texto'  => "Não entendi 😅 Posso ajudar com:",
        'botoes' => ['📋 Cardápio', '⏰ Horários', '📍 Localização', '💰 Preços', '🛵 Delivery'],
    ];
}
