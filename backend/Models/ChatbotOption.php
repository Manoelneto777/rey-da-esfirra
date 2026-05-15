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
     * Busca uma resposta pela palavra-chave exata.
     *
     * @param  string      $keyword
     * @return array|null  Registro encontrado ou null
     */
    public function buscarPorKeyword(string $keyword): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
              WHERE keyword = :keyword
                AND ativo   = 1
              LIMIT 1"
        );
        $stmt->execute([':keyword' => mb_strtolower(trim($keyword))]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Retorna todas as opcoes ativas (para varredura de keywords).
     *
     * @return array
     */
    public function listarAtivas(): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
              WHERE ativo = 1
              ORDER BY keyword ASC"
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Adiciona ou atualiza uma opcao de resposta.
     *
     * @param  string $keyword
     * @param  string $response
     * @return int    ID
     */
    public function salvarOpcao(string $keyword, string $response): int
    {
        return $this->save([
            'keyword'  => mb_strtolower(trim($keyword)),
            'response' => $response,
            'ativo'    => 1,
        ]);
    }
}
