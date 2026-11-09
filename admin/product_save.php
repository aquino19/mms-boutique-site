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
$category = trim($_POST['category'] ?? '');
$price = $_POST['price'] ?? '';
$imageUrl = trim($_POST['image_url'] ?? '');
$weightKg = $_POST['weight_kg'] ?? '';
$heightCm = $_POST['height_cm'] ?? '';
$widthCm = $_POST['width_cm'] ?? '';
$lengthCm = $_POST['length_cm'] ?? '';
$active = isset($_POST['active']) ? 1 : 0;

if ($name === '' || !is_numeric($price) || (float)$price < 0) {
    die('Dados inválidos. Volte e confira nome e preço.');
}
foreach (['weight_kg' => $weightKg, 'height_cm' => $heightCm, 'width_cm' => $widthCm, 'length_cm' => $lengthCm] as $label => $val) {
    if (!is_numeric($val) || (float)$val <= 0) {
        die("Valor inválido para $label. Peso e dimensões são obrigatórios pra calcular frete.");
    }
}

if ($id !== '') {
    $productId = (int)$id;
    $stmt = $pdo->prepare(
        'UPDATE products
         SET sku = :sku, name = :name, description = :description, category = :category, price = :price,
             image_url = :image_url, weight_kg = :weight_kg, height_cm = :height_cm,
             width_cm = :width_cm, length_cm = :length_cm, active = :active
         WHERE id = :id'
    );
    $stmt->execute([
        'sku' => $sku !== '' ? $sku : null,
        'name' => $name,
        'description' => $description,
        'category' => $category !== '' ? $category : null,
        'price' => $price,
        'image_url' => $imageUrl,
        'weight_kg' => $weightKg,
        'height_cm' => $heightCm,
        'width_cm' => $widthCm,
        'length_cm' => $lengthCm,
        'active' => $active,
        'id' => $productId,
    ]);
} else {
    $stmt = $pdo->prepare(
        'INSERT INTO products (sku, name, description, category, price, image_url, weight_kg, height_cm, width_cm, length_cm, active)
         VALUES (:sku, :name, :description, :category, :price, :image_url, :weight_kg, :height_cm, :width_cm, :length_cm, :active)'
    );
    $stmt->execute([
        'sku' => $sku !== '' ? $sku : null,
        'name' => $name,
        'description' => $description,
        'category' => $category !== '' ? $category : null,
        'price' => $price,
        'image_url' => $imageUrl,
        'weight_kg' => $weightKg,
        'height_cm' => $heightCm,
        'width_cm' => $widthCm,
        'length_cm' => $lengthCm,
        'active' => $active,
    ]);
    $productId = (int)$pdo->lastInsertId();
}

// Produto novo já direciona pra tela de variações, já que sem
// pelo menos 1 variação o produto não pode ser vendido.
header('Location: product_variants.php?product_id=' . $productId . '&saved=1');
exit;
