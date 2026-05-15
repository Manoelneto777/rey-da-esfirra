<?php
/**
 * backend/Models/Pedido.php
 * Model da tabela pedidos + pedido_itens.
 */

namespace Models;

use Core\Model;
use Core\Connect;
use PDO;

class Pedido extends Model
{
    protected string $table = 'pedidos';

    /**
     * Salva pedido + itens em transacao atomica.
     *
     * @param  array $dados  [nome_cliente, telefone, endereco, observacoes, total]
     * @param  array $itens  [[produto_id, quantidade, preco], ...]
     * @return int   ID do pedido criado
     * @throws \RuntimeException em caso de falha
     */
    public function salvarComItens(array $dados, array $itens): int
    {
        $this->db->beginTransaction();

        try {
            // Insere o pedido
            $pedidoId = $this->save($dados);

            // Insere cada item
            $stmt = $this->db->prepare(
                "INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco)
                 VALUES (:pedido_id, :produto_id, :quantidade, :preco)"
            );

            foreach ($itens as $item) {
                $stmt->execute([
                    ':pedido_id'  => $pedidoId,
                    ':produto_id' => (int)   $item['produto_id'],
                    ':quantidade' => (int)   $item['quantidade'],
                    ':preco'      => (float) $item['preco'],
                ]);
            }

            $this->db->commit();
            return $pedidoId;

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw new \RuntimeException('Erro ao salvar pedido: ' . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Atualiza o status de um pedido.
     *
     * @param  int    $id
     * @param  string $status
     * @return int    Linhas afetadas
     */
    public function atualizarStatus(int $id, string $status): int
    {
        return $this->update($id, ['status' => $status]);
    }
}
