<?php
/**
 * backend/Models/ChatbotOption.php
 * Model da tabela chatbot_options.
 *
 * ┌─────────────────────────────────────────────────────────────┐
 * │ O QUE MUDOU                                                  │
 * │ Antes o Model usava as colunas keyword/response, mas o       │
 * │ schema real é intencao/resposta/ativo. Corrigido aqui.       │
 * │                                                              │
 * │ Esta tabela é a "base de conhecimento" do fluxo MANUAL:      │
 * │   intencao = palavra-chave normalizada (ex: 'cardapio')      │
 * │   resposta = texto que o bot devolve                         │
 * └─────────────────────────────────────────────────────────────┘
 */

namespace Models;

use Core\Model;
use PDO;

class ChatbotOption extends Model
{
    protected string $table = 'chatbot_options';

    /**
     * Busca a resposta manual a partir da intenção já detectada.
     *
     * Usamos igualdade exata (= :intencao) porque a intenção já chega
     * "limpa" (ex: 'cardapio'), vinda de detectarIntencao(). Isso é mais
     * previsível e mais rápido que um LIKE com %.
     *
     * @param  string $intencao  ex: 'cardapio', 'horarios'
     * @return string|null       O texto da resposta, ou null se não houver
     */
    public function buscarPorIntencao(string $intencao): ?string
    {
        $intencao = mb_strtolower(trim($intencao), 'UTF-8');

        $stmt = $this->db->prepare(
            "SELECT resposta
               FROM {$this->table}
              WHERE LOWER(intencao) = :intencao
                AND ativo = 1
              LIMIT 1"
        );
        $stmt->execute([':intencao' => $intencao]);

        $resposta = $stmt->fetchColumn();

        // fetchColumn() devolve false quando não acha nada → normalizamos para null.
        return $resposta !== false ? (string) $resposta : null;
    }

    /**
     * Lista todas as intenções ativas (útil para um futuro admin
     * ou para alimentar a detecção dinamicamente).
     *
     * @return array
     */
    public function listarAtivas(): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, intencao, resposta, ativo
               FROM {$this->table}
              WHERE ativo = 1
              ORDER BY intencao ASC"
        );
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cadastra ou atualiza uma resposta manual.
     *
     * @param  string $intencao
     * @param  string $resposta
     * @return int                ID inserido / linhas afetadas
     */
    public function salvarOpcao(string $intencao, string $resposta): int
    {
        return $this->save([
            'intencao' => mb_strtolower(trim($intencao), 'UTF-8'),
            'resposta' => $resposta,
            'ativo'    => 1,
        ]);
    }
}
