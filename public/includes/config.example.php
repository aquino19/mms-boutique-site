<?php
// Mesma base de dados usada pelo admin. Ao subir para a Hostinger,
// troque pelos dados reais do banco criado no hPanel.

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
            die('Loja indisponível no momento. Tente novamente em instantes.');
        }
    }

    return $pdo;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getCart(): array
{
    return $_SESSION['cart'] ?? [];
}

function cartItemCount(): int
{
    return array_sum(getCart());
}

/** Lista as unidades físicas ativas. */
function getActiveLocations(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM store_locations WHERE active = 1 ORDER BY id')->fetchAll();
}

/** Recalcula products.stock como soma de stock_by_store (mesma lógica do admin). */
function recalcProductStock(PDO $pdo, int $productId): void
{
    $stmt = $pdo->prepare(
        'UPDATE products
         SET stock = (SELECT COALESCE(SUM(quantity), 0) FROM stock_by_store WHERE product_id = :pid)
         WHERE id = :pid2'
    );
    $stmt->execute(['pid' => $productId, 'pid2' => $productId]);
}

/**
 * Decide de qual(is) unidade(s) cada item do carrinho vai sair, com base no
 * estoque disponível em stock_by_store. Pode dividir um mesmo produto entre
 * duas unidades se nenhuma sozinha tiver a quantidade toda.
 *
 * Retorna ['success' => bool, 'allocations' => [...], 'error' => string|null]
 * Cada allocation: ['product_id','name','unit_price','location_id','location_name','quantity']
 */
function allocateFulfillment(PDO $pdo, array $cart): array
{
    $allocations = [];

    foreach ($cart as $productId => $qtyNeeded) {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id AND active = 1');
        $stmt->execute(['id' => $productId]);
        $product = $stmt->fetch();

        if (!$product) {
            continue; // produto removido/inativo — ignorado silenciosamente no carrinho
        }

        $stmt = $pdo->prepare(
            'SELECT sbs.location_id, sbs.quantity, sl.name AS location_name
             FROM stock_by_store sbs
             JOIN store_locations sl ON sl.id = sbs.location_id AND sl.active = 1
             WHERE sbs.product_id = :pid AND sbs.quantity > 0
             ORDER BY sbs.quantity DESC'
        );
        $stmt->execute(['pid' => $productId]);
        $stockRows = $stmt->fetchAll();

        $remaining = (int)$qtyNeeded;
        foreach ($stockRows as $row) {
            if ($remaining <= 0) break;
            $take = min($remaining, (int)$row['quantity']);
            if ($take <= 0) continue;

            $allocations[] = [
                'product_id' => (int)$product['id'],
                'name' => $product['name'],
                'unit_price' => (float)$product['price'],
                'location_id' => (int)$row['location_id'],
                'location_name' => $row['location_name'],
                'quantity' => $take,
            ];
            $remaining -= $take;
        }

        if ($remaining > 0) {
            return [
                'success' => false,
                'allocations' => [],
                'error' => 'Estoque insuficiente para "' . $product['name'] . '" (faltam ' . $remaining . ' unidade(s)).',
            ];
        }
    }

    return ['success' => true, 'allocations' => $allocations, 'error' => null];
}
