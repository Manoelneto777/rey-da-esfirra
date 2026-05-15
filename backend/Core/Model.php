<?php
/**
 * backend/Core/Model.php
 * Classe base para todos os Models.
 * Fornece metodos genericos de CRUD usando PDO.
 */

namespace Core;

use PDO;

abstract class Model
{
    /** @var PDO Conexao PDO compartilhada */
    protected PDO $db;

    /** @var string Nome da tabela (definido em cada Model filho) */
    protected string $table;

    public function __construct()
    {
        $this->db = Connect::getInstance();
    }

    /**
     * Busca um registro pelo ID.
     *
     * @param  int        $id
     * @return array|null Linha encontrada ou null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Retorna todos os registros da tabela.
     *
     * @param  string $orderBy Coluna e direcao (ex: 'nome ASC')
     * @return array
     */
    public function findAll(string $orderBy = 'id ASC'): array
    {
        $stmt = $this->db->query("SELECT * FROM {$this->table} ORDER BY {$orderBy}");
        return $stmt->fetchAll();
    }

    /**
     * Insere ou atualiza um registro.
     * Se $data contiver 'id', faz UPDATE; caso contrario, INSERT.
     *
     * @param  array $data Dados a salvar (colunas => valores)
     * @return int         ID inserido ou numero de linhas afetadas
     */
    public function save(array $data): int
    {
        if (isset($data['id']) && $data['id'] > 0) {
            return $this->update($data['id'], $data);
        }

        return $this->insert($data);
    }

    /**
     * Insere um novo registro.
     *
     * @param  array $data
     * @return int   ID do registro inserido
     */
    protected function insert(array $data): int
    {
        unset($data['id']); // garante que id nao e enviado

        $columns      = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})"
        );

        $params = [];
        foreach ($data as $col => $val) {
            $params[':' . $col] = $val;
        }

        $stmt->execute($params);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualiza um registro existente.
     *
     * @param  int   $id
     * @param  array $data
     * @return int   Numero de linhas afetadas
     */
    protected function update(int $id, array $data): int
    {
        unset($data['id']);

        $setParts = [];
        foreach (array_keys($data) as $col) {
            $setParts[] = "{$col} = :{$col}";
        }
        $setStr = implode(', ', $setParts);

        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET {$setStr} WHERE id = :id"
        );

        $params = [':id' => $id];
        foreach ($data as $col => $val) {
            $params[':' . $col] = $val;
        }

        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Remove um registro pelo ID.
     *
     * @param  int $id
     * @return int Numero de linhas afetadas
     */
    public function delete(int $id): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table} WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount();
    }
}
