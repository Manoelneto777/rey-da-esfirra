<?php
/**
 * backend/Models/ChatMessage.php
 * Model da tabela chat_messages.
 *
 * ┌─────────────────────────────────────────────────────────────┐
 * │ O QUE MUDOU EM RELAÇÃO À VERSÃO ANTERIOR                     │
 * │ Antes o Model gravava as colunas message/sender/sessao_id,  │
 * │ que NÃO existem na tabela. O schema real (estrutura.sql) é:  │
 * │   id, conversation_id, usuario_id, mensagem, tipo, created_at│
 * │ Agora os nomes batem 100% com o banco — sem "Unknown column".│
 * └─────────────────────────────────────────────────────────────┘
 */

namespace Models;

use Core\Model;
use PDO;

class ChatMessage extends Model
{
    /** Nome da tabela (usado pelos métodos genéricos do Model base). */
    protected string $table = 'chat_messages';

    /**
     * Salva UMA mensagem da conversa.
     *
     * Repare que NÃO passamos created_at: a coluna tem
     * DEFAULT CURRENT_TIMESTAMP no banco, então o MySQL preenche sozinho.
     *
     * @param  int      $conversationId  FK para chat_conversations.id
     * @param  string   $mensagem        Texto da mensagem
     * @param  string   $tipo            'user' | 'bot' | 'ai'
     * @param  int|null $usuarioId       ID do usuário logado, ou null (visitante)
     * @return int                       ID da mensagem inserida
     */
    public function salvar(int $conversationId, string $mensagem, string $tipo, ?int $usuarioId = null): int
    {
        // O Model::save() monta o INSERT a partir das CHAVES deste array.
        // Por isso cada chave precisa ser exatamente o nome da coluna.
        return $this->save([
            'conversation_id' => $conversationId,
            'usuario_id'      => $usuarioId,   // pode ser null — o PDO grava NULL
            'mensagem'        => $mensagem,
            'tipo'            => $tipo,
        ]);
    }

    /**
     * Retorna o histórico completo de uma conversa, em ordem cronológica.
     * Útil para reabrir o chat ou enviar contexto para a IA.
     *
     * Obs.: usamos bindValue com PARAM_INT no LIMIT porque o MySQL
     * não aceita LIMIT como string vinda de placeholder.
     *
     * @param  int $conversationId
     * @param  int $limite          Máximo de mensagens retornadas
     * @return array
     */
    public function historicoDaConversa(int $conversationId, int $limite = 50): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, conversation_id, usuario_id, mensagem, tipo, created_at
               FROM {$this->table}
              WHERE conversation_id = :cid
              ORDER BY created_at ASC, id ASC
              LIMIT :limite"
        );

        $stmt->bindValue(':cid',    $conversationId, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite,         PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Conta quantas mensagens existem por tipo de remetente.
     * (Bom para um futuro painel de estatísticas.)
     *
     * @return array ['user' => N, 'bot' => N, 'ai' => N]
     */
    public function estatisticas(): array
    {
        $stmt = $this->db->query(
            "SELECT tipo, COUNT(*) AS total
               FROM {$this->table}
              GROUP BY tipo"
        );

        // Inicia zerado para nunca retornar índice indefinido.
        $stats = ['user' => 0, 'bot' => 0, 'ai' => 0];

        foreach ($stmt->fetchAll() as $row) {
            $stats[$row['tipo']] = (int) $row['total'];
        }

        return $stats;
    }
}
