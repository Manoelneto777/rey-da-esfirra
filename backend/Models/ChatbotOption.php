<?php
/**
 * backend/Models/ChatbotOption.php
 * Model da tabela chatbot_options.
 * Base de conhecimento: palavras-chave -> respostas manuais.
 */


namespace Models;

use Core\Model;
use PDO;

class ChatbotOption extends Model
{
    protected string $table = 'chatbot_options';

    /**
     * Busca uma resposta por keyword usando correspondência parcial.
     *
     * Mudança:
     * - Antes buscava apenas keyword exata.
     * - Agora usa LIKE para encontrar a keyword dentro de frases maiores.
     */
    public function buscarPorKeyword(string $keyword): ?array
    {
        $keyword = mb_strtolower(trim($keyword), 'UTF-8');

        $stmt = $this->db->prepare(
            "SELECT *
               FROM {$this->table}
              WHERE LOWER(keyword) LIKE :keyword
                AND ativo = 1
              LIMIT 1"
        );

        $stmt->execute([
            ':keyword' => '%' . $keyword . '%',
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function listarAtivas(): array
    {
        $stmt = $this->db->prepare(
            "SELECT *
               FROM {$this->table}
              WHERE ativo = 1
              ORDER BY keyword ASC"
        );

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function salvarOpcao(string $keyword, string $response): int
    {
        return $this->save([
            'keyword'  => mb_strtolower(trim($keyword), 'UTF-8'),
            'response' => $response,
            'ativo'    => 1,
        ]);
    }
}