<?php
require_once __DIR__ . '/includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: catalog.php');
    exit;
}

$productId = (int)($_POST['product_id'] ?? 0);

if ($productId > 0) {
    // Confirma que o produto existe e está ativo antes de adicionar
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT id FROM products WHERE id = :id AND active = 1');
    $stmt->execute(['id' => $productId]);

    if ($stmt->fetch()) {
        $cart = $_SESSION['cart'] ?? [];
        $cart[$productId] = ($cart[$productId] ?? 0) + 1;
        $_SESSION['cart'] = $cart;
    }
}

header('Location: cart.php');
exit;
