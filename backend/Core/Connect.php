<?php
/**
 * backend/Core/Connect.php
 * Conexao com MySQL via PDO — padrao Singleton.
 * Uma unica instancia de conexao por requisicao.
 */

namespace Core;

use PDO;
use PDOException;

class Connect
{
    /** @var PDO|null Instancia unica da conexao */
    private static ?PDO $instance = null;

    /**
     * Retorna a conexao PDO (cria se nao existir).
     *
     * @return PDO
     * @throws \RuntimeException se a conexao falhar
     */
    public static function getInstance(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            Config::DB_HOST,
            Config::DB_PORT,
            Config::DB_NAME,
            Config::DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            self::$instance = new PDO($dsn, Config::DB_USER, Config::DB_PASS, $options);
        } catch (PDOException $e) {
            // Em producao, nunca exponha detalhes da excecao
            $msg = Config::APP_DEBUG
                ? 'Erro de conexao: ' . $e->getMessage()
                : 'Servico temporariamente indisponivel.';

            throw new \RuntimeException($msg, 500, $e);
        }

        return self::$instance;
    }

    /** Impede instanciacao direta e clonagem (padrao Singleton) */
    private function __construct() {}
    private function __clone()     {}
}
