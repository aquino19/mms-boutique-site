<?php
require_once __DIR__ . '/includes/config.php';

$productId = (int)($_GET['product_id'] ?? 0);

if (isset($_SESSION['cart'][$productId])) {
    unset($_SESSION['cart'][$productId]);
}

header('Location: cart.php');
exit;
