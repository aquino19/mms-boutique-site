<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php');
    exit;
}

$pdo = getDbConnection();

$id = trim($_POST['id'] ?? '');
$sku = trim($_POST['sku'] ?? '');
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$price = $_POST['price'] ?? '';
$imageUrl = trim($_POST['image_url'] ?? '');
$active = isset($_POST['active']) ? 1 : 0;
$stockByLocation = $_POST['stock_location'] ?? []; // [location_id => qty]

// Validação básica no servidor (nunca confiar só no HTML5 required)
if ($name === '' || !is_numeric($price) || (float)$price < 0) {
    die('Dados inválidos. Volte e confira nome e preço.');
}
foreach ($stockByLocation as $qty) {
    if (!ctype_digit((string)$qty)) {
        die('Estoque inválido. Use apenas números inteiros.');
    }
}

$pdo->beginTransaction();

try {
    if ($id !== '') {
        $productId = (int)$id;
        $stmt = $pdo->prepare(
            'UPDATE products
             SET sku = :sku, name = :name, description = :description, price = :price,
                 image_url = :image_url, active = :active
             WHERE id = :id'
        );
        $stmt->execute([
            'sku' => $sku !== '' ? $sku : null,
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'image_url' => $imageUrl,
            'active' => $active,
            'id' => $productId,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO products (sku, name, description, price, image_url, active)
             VALUES (:sku, :name, :description, :price, :image_url, :active)'
        );
        $stmt->execute([
            'sku' => $sku !== '' ? $sku : null,
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'image_url' => $imageUrl,
            'active' => $active,
        ]);
        $productId = (int)$pdo->lastInsertId();
    }

    // Grava o estoque de cada unidade (upsert)
    $upsert = $pdo->prepare(
        'INSERT INTO stock_by_store (product_id, location_id, quantity)
         VALUES (:product_id, :location_id, :quantity)
         ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)'
    );
    foreach ($stockByLocation as $locationId => $qty) {
        $upsert->execute([
            'product_id' => $productId,
            'location_id' => (int)$locationId,
            'quantity' => (int)$qty,
        ]);
    }

    // products.stock é sempre recalculado, nunca gravado direto
    recalcProductStock($pdo, $productId);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Erro ao salvar produto: ' . $e->getMessage());
    die('Não foi possível salvar o produto. Tente novamente.');
}

header('Location: products.php?saved=1');
exit;
