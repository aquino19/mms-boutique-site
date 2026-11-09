<?php
require_once __DIR__ . '/includes/config.php';

// Altera a quantidade de um item do carrinho (+1 / -1). Chegando a 0, o item sai.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cart.php');
    exit;
}

$variantId = (int)($_POST['variant_id'] ?? 0);
$delta = (int)($_POST['delta'] ?? 0);
$cart = $_SESSION['cart'] ?? [];

if ($variantId > 0 && isset($cart[$variantId]) && ($delta === 1 || $delta === -1)) {
    $newQty = $cart[$variantId] + $delta;

    if ($newQty <= 0) {
        unset($cart[$variantId]);
    } else {
        // Não deixa passar do estoque somado das lojas
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM stock_by_store WHERE variant_id = :id');
        $stmt->execute(['id' => $variantId]);
        $stock = (int)$stmt->fetchColumn();
        if ($delta < 0 || $newQty <= $stock) {
            $cart[$variantId] = $newQty;
        }
    }
    $_SESSION['cart'] = $cart;
}

header('Location: cart.php');
exit;
