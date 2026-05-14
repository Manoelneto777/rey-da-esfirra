<?php
/**
 * backend/Models/Produto.php
 * Model da tabela produtos.
 */

namespace Models;

use Core\Model;
use PDO;

class Produto extends Model
{
    protected string $table = 'produtos';

    /**
     * Retorna produtos disponiveis, com filtro opcional de categoria.
     *
     * @param  string $categoria '' para todos
     * @return array
     */
    public function listarDisponiveis(string $categoria = ''): array
    {
        if ($categoria !== '' && $categoria !== 'Todos') {
            $stmt = $this->db->prepare(
                "SELECT * FROM {$this->table}
                  WHERE disponivel = 1
                    AND categoria  = :cat
                  ORDER BY nome ASC"
            );
            $stmt->execute([':cat' => $categoria]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT * FROM {$this->table}
                  WHERE disponivel = 1
                  ORDER BY categoria, nome ASC"
            );
            $stmt->execute();
        }

        return $stmt->fetchAll();
    }
}
