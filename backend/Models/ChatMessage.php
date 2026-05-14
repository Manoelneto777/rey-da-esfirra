<?php
/**
 * backend/Models/ChatMessage.php
 * Model da tabela chat_messages.
 * Armazena o historico de mensagens do chatbot.
 */

namespace Models;

use Core\Model;
use PDO;

class ChatMessage extends Model
{
    protected string $table = 'chat_messages';

    /**
     * Salva uma mensagem no banco.
     *
     * @param  string $message   Texto da mensagem
     * @param  string $sender    'user' | 'bot' | 'ai'
     * @param  string $sessaoId  Identificador da sessao do usuario
     * @return int               ID da mensagem inserida
     */
    public function salvar(string $message, string $sender, string $sessaoId = ''): int
    {
        return $this->save([
            'message'   => $message,
            'sender'    => $sender,
            'sessao_id' => $sessaoId,
        ]);
    }

    /**
     * Retorna o historico de uma sessao.
     *
     * @param  string $sessaoId
     * @param  int    $limite   Numero maximo de mensagens
     * @return array
     */
    public function historicoDaSessao(string $sessaoId, int $limite = 50): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
              WHERE sessao_id = :sessao_id
              ORDER BY created_at ASC
              LIMIT :limite"
        );
        $stmt->bindValue(':sessao_id', $sessaoId, PDO::PARAM_STR);
        $stmt->bindValue(':limite',    $limite,   PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Total de mensagens por tipo de remetente.
     *
     * @return array ['user' => N, 'bot' => N, 'ai' => N]
     */
    public function estatisticas(): array
    {
        $stmt = $this->db->query(
            "SELECT sender, COUNT(*) as total FROM {$this->table} GROUP BY sender"
        );
        $rows = $stmt->fetchAll();

        $stats = ['user' => 0, 'bot' => 0, 'ai' => 0];
        foreach ($rows as $row) {
            $stats[$row['sender']] = (int) $row['total'];
        }

        return $stats;
    }
}
