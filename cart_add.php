<?php
require_once __DIR__ . '/includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: catalog.php');
    exit;
}

$variantId = (int)($_POST['variant_id'] ?? 0);

if ($variantId > 0) {
    // Confirma que a variação existe, está ativa, e pertence a um produto ativo
    $pdo = getDbConnection();
    $stmt = $pdo->prepare(
        'SELECT pv.id FROM product_variants pv
         JOIN products p ON p.id = pv.product_id
         WHERE pv.id = :id AND pv.active = 1 AND p.active = 1'
    );
    $stmt->execute(['id' => $variantId]);

    if ($stmt->fetch()) {
        $cart = $_SESSION['cart'] ?? [];
        $cart[$variantId] = ($cart[$variantId] ?? 0) + 1;
        $_SESSION['cart'] = $cart;
        $_SESSION['flash_added'] = true;
    }
}

// Para onde voltar: só caminhos relativos do próprio site (evita redirecionamento externo)
$return = (string)($_POST['return'] ?? 'cart.php');
if ($return === '' || preg_match('#^[a-z][a-z0-9+.-]*:|^//|[\r\n\\]#i', $return)) {
    $return = 'cart.php';
}
if ($return !== 'cart.php') {
    $return = ltrim($return, '/');
    // O toast "adicionado" só faz sentido nas páginas de loja; no carrinho não precisa
}
if ($return === 'cart.php') {
    unset($_SESSION['flash_added']);
}
header('Location: ' . $return);
exit;
