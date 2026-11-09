<?php
require_once __DIR__ . '/includes/config.php';

$variantId = (int)($_GET['variant_id'] ?? 0);

if (isset($_SESSION['cart'][$variantId])) {
    unset($_SESSION['cart'][$variantId]);
}

header('Location: cart.php');
exit;
