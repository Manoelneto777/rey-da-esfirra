<?php
/**
 * backend/db.php
 * Conexão com MySQL via mysqli — singleton simples.
 * Edite as constantes abaixo conforme seu XAMPP.
 */

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');   // padrão XAMPP
define('DB_PASS', '');       // padrão XAMPP (sem senha)
define('DB_NAME', 'reydaesfirra_chatbot');
define('DB_PORT', 3307);

/**
 * Retorna uma conexão mysqli reutilizável.
 * Encerra com JSON de erro em caso de falha.
 */
function db(): mysqli
{
    static $conn = null;

    if ($conn !== null) {
        return $conn;
    }

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode([
            'sucesso' => false,
            'erro'    => 'Falha na conexão com o banco de dados.',
        ]);
        exit;
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

/**
 * Headers padrão para todas as respostas da API.
 * Chame no topo de cada endpoint.
 */
function cabecalhosApi(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    // Responde preflight CORS e encerra
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/**
 * Lê e decodifica o corpo JSON da requisição.
 * Retorna array ou encerra com erro 400.
 */
function lerJson(): array
{
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'erro' => 'JSON inválido ou ausente.']);
        exit;
    }

    return $data;
}

/**
 * Atalho para encerrar com resposta JSON.
 */
function responder(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
