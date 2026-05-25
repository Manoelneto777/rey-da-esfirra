<?php
/**
 * backend/ChatService.php
 *
 * O "cérebro" do chatbot. É a ÚNICA classe que conhece o fluxo completo:
 *
 *   1. garante uma conversa em chat_conversations
 *   2. salva a mensagem do usuário em chat_messages
 *   3. decide a resposta (MANUAL via banco → fallback embutido → IA)
 *   4. salva a resposta do bot em chat_messages
 *   5. devolve tudo pronto para o endpoint só ecoar como JSON
 *
 * ┌─────────────────────────────────────────────────────────────┐
 * │ POR QUE NÃO TEM `namespace`?                                 │
 * │ Para o endpoint poder fazer um require_once direto e simples.│
 * │ Por isso referenciamos as classes com a barra inicial:       │
 * │   \Core\Connect, \Models\ChatMessage, etc. (caminho absoluto)│
 * │ As funções (chamarOpenRouter, detectarIntencao...) vêm do    │
 * │ chat_helpers.php, que o endpoint carrega ANTES deste arquivo.│
 * └─────────────────────────────────────────────────────────────┘
 */

class ChatService
{
    /** @var \PDO Conexão única (Singleton) usada para o controle da conversa. */
    private \PDO $pdo;

    /** @var \Models\ChatMessage Model responsável por gravar/ler mensagens. */
    private \Models\ChatMessage $mensagens;

    /** @var \Models\ChatbotOption Base de conhecimento do fluxo manual. */
    private \Models\ChatbotOption $opcoes;

    public function __construct()
    {
        // O autoload registrado no chat_helpers.php resolve estas classes.
        $this->pdo       = \Core\Connect::getInstance();
        $this->mensagens = new \Models\ChatMessage();
        $this->opcoes    = new \Models\ChatbotOption();
    }

    /**
     * Processa uma mensagem do usuário e devolve a resposta do bot.
     *
     * @param  string   $mensagem        Texto digitado pelo usuário
     * @param  int|null $conversationId  Conversa atual (null na 1ª mensagem)
     * @param  int|null $usuarioId       ID do usuário logado, se houver
     * @return array {
     *     sucesso, conversation_id, texto, tipo,
     *     mensagem_usuario_id, mensagem_bot_id
     * }
     * @throws \InvalidArgumentException se a mensagem for inválida
     */
    public function handleMessage(string $mensagem, ?int $conversationId = null, ?int $usuarioId = null): array
    {
        // ── Validação de entrada ──────────────────────────────
        $mensagem = trim($mensagem);
        if ($mensagem === '') {
            throw new \InvalidArgumentException('Mensagem vazia.');
        }
        if (mb_strlen($mensagem) > 1000) {
            throw new \InvalidArgumentException('Mensagem muito longa (máx. 1000 caracteres).');
        }

        // ── 1. Garante a conversa ─────────────────────────────
        // Se não veio id, ou o id veio mas não existe no banco, cria uma nova.
        if ($conversationId === null || !$this->conversaExiste($conversationId)) {
            $conversationId = $this->criarConversa($usuarioId);
        }

        // ── 2. Salva a mensagem do usuário ────────────────────
        $userMsgId = $this->mensagens->salvar($conversationId, $mensagem, 'user', $usuarioId);

        // ── 3. Gera a resposta ────────────────────────────────
        [$respostaTexto, $tipo] = $this->gerarResposta($mensagem);

        // ── 4. Salva a resposta do bot ────────────────────────
        $botMsgId = $this->mensagens->salvar($conversationId, $respostaTexto, $tipo, null);

        // ── 5. Devolve o pacote pronto ────────────────────────
        return [
            'sucesso'             => true,
            'conversation_id'     => $conversationId, // o front guarda e reenvia
            'texto'               => $respostaTexto,
            'tipo'                => $tipo,           // 'bot' (manual) ou 'ai'
            'mensagem_usuario_id' => $userMsgId,
            'mensagem_bot_id'     => $botMsgId,
        ];
    }

    // ─────────────────────────────────────────────────────────
    // LÓGICA DA RESPOSTA
    // Ordem de prioridade: MANUAL (banco) → MANUAL (fallback) → IA → padrão.
    // Retorna sempre uma dupla [texto, tipo].
    // ─────────────────────────────────────────────────────────
    private function gerarResposta(string $mensagem): array
    {
        $mensagemNorm = mb_strtolower($mensagem, 'UTF-8');
        $intencao     = detectarIntencao($mensagemNorm);

        // 3a. Tem intenção reconhecida → tenta o fluxo manual.
        if ($intencao !== null) {
            // Primeiro no banco (chatbot_options); se faltar, usa o embutido.
            $texto = $this->opcoes->buscarPorIntencao($intencao)
                  ?? respostaManualFallback($intencao);

            if ($texto !== null) {
                return [$texto, 'bot'];
            }
        }

        // 3b. Sem resposta manual → tenta a IA (se ligada na Config).
        if (\Core\Config::CHATBOT_USE_AI) {
            $textoIA = chamarOpenRouter($mensagem);
            if ($textoIA !== null && trim($textoIA) !== '') {
                return [$textoIA, 'ai'];
            }
        }

        // 3c. Nada funcionou → resposta padrão amigável.
        return [respostaPadraoTexto(), 'bot'];
    }

    // ─────────────────────────────────────────────────────────
    // CONTROLE DA CONVERSA (tabela chat_conversations)
    // Mantido aqui com PDO direto e prepared statements — simples e seguro.
    // ─────────────────────────────────────────────────────────

    /** Verifica se a conversa informada realmente existe. */
    private function conversaExiste(int $conversationId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM chat_conversations WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $conversationId]);

        return (bool) $stmt->fetchColumn();
    }

    /** Cria uma nova conversa e devolve o ID gerado. */
    private function criarConversa(?int $usuarioId): int
    {
        // criado_em tem DEFAULT no banco — não precisa enviar.
        $stmt = $this->pdo->prepare(
            "INSERT INTO chat_conversations (usuario_id, status, origem)
             VALUES (:usuario_id, 'ativa', 'web')"
        );
        $stmt->execute([':usuario_id' => $usuarioId]);

        return (int) $this->pdo->lastInsertId();
    }
}
