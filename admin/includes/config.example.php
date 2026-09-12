<?php
// ============================================================
// EXEMPLO de configuração — copie este arquivo para "config.php"
// e preencha com os dados reais do banco (não versione o config.php real).
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'nome_do_banco_aqui');
define('DB_USER', 'usuario_do_banco_aqui');
define('DB_PASS', 'senha_do_banco_aqui');

function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Erro de conexão com o banco: ' . $e->getMessage());
            die('Não foi possível conectar ao banco de dados. Tente novamente mais tarde.');
        }
    }

    return $pdo;
}

/**
 * Recalcula products.stock como a soma de todas as unidades em stock_by_store.
 * Deve ser chamada sempre que stock_by_store for alterado.
 */
function recalcProductStock(PDO $pdo, int $productId): void
{
    $stmt = $pdo->prepare(
        'UPDATE products
         SET stock = (SELECT COALESCE(SUM(quantity), 0) FROM stock_by_store WHERE product_id = :pid)
         WHERE id = :pid2'
    );
    $stmt->execute(['pid' => $productId, 'pid2' => $productId]);
}

/** Lista as unidades físicas ativas, para montar os campos de estoque no admin. */
function getActiveLocations(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM store_locations WHERE active = 1 ORDER BY id')->fetchAll();
}
