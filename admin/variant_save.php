<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php');
    exit;
}

$pdo = getDbConnection();

$id = trim($_POST['id'] ?? '');
$productId = (int)($_POST['product_id'] ?? 0);
$colorName = trim($_POST['color_name'] ?? '');
$colorHex = trim($_POST['color_hex'] ?? '');
$size = trim($_POST['size'] ?? '');
$sku = trim($_POST['sku'] ?? '');
$active = isset($_POST['active']) ? 1 : 0;
$stockByLocation = $_POST['stock_location'] ?? [];

$stmt = $pdo->prepare('SELECT id FROM products WHERE id = :id');
$stmt->execute(['id' => $productId]);
if (!$stmt->fetch()) {
    die('Produto inválido.');
}

foreach ($stockByLocation as $qty) {
    if (!ctype_digit((string)$qty)) {
        die('Estoque inválido. Use apenas números inteiros.');
    }
}
if ($colorHex !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $colorHex)) {
    die('Cor em formato inválido. Use algo como #8C5443.');
}

$pdo->beginTransaction();

try {
    if ($id !== '') {
        $variantId = (int)$id;
        $stmt = $pdo->prepare(
            'UPDATE product_variants
             SET color_name = :color_name, color_hex = :color_hex, size = :size, sku = :sku, active = :active
             WHERE id = :id AND product_id = :product_id'
        );
        $stmt->execute([
            'color_name' => $colorName !== '' ? $colorName : null,
            'color_hex' => $colorHex !== '' ? $colorHex : null,
            'size' => $size !== '' ? $size : null,
            'sku' => $sku !== '' ? $sku : null,
            'active' => $active,
            'id' => $variantId,
            'product_id' => $productId,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO product_variants (product_id, color_name, color_hex, size, sku, active)
             VALUES (:product_id, :color_name, :color_hex, :size, :sku, :active)'
        );
        $stmt->execute([
            'product_id' => $productId,
            'color_name' => $colorName !== '' ? $colorName : null,
            'color_hex' => $colorHex !== '' ? $colorHex : null,
            'size' => $size !== '' ? $size : null,
            'sku' => $sku !== '' ? $sku : null,
            'active' => $active,
        ]);
        $variantId = (int)$pdo->lastInsertId();
    }

    $upsert = $pdo->prepare(
        'INSERT INTO stock_by_store (variant_id, location_id, quantity)
         VALUES (:variant_id, :location_id, :quantity)
         ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)'
    );
    foreach ($stockByLocation as $locationId => $qty) {
        $upsert->execute([
            'variant_id' => $variantId,
            'location_id' => (int)$locationId,
            'quantity' => (int)$qty,
        ]);
    }

    recalcProductStock($pdo, $productId);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Erro ao salvar variação: ' . $e->getMessage());
    die('Não foi possível salvar a variação. Verifique se essa combinação de cor+tamanho já existe pra esse produto.');
}

header('Location: product_variants.php?product_id=' . $productId . '&saved=1');
exit;
