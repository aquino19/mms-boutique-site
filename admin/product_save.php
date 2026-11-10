<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/image_upload.php';
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

// Produto vindo do SG Master: preço e descrição são do sistema da loja,
// e a visibilidade é calculada (foto + categoria + tamanho ativo).
$erpProduct = null;
if ($id !== '') {
    $chk = $pdo->prepare('SELECT price, description, erp_managed FROM products WHERE id = :id');
    $chk->execute(['id' => (int)$id]);
    $erpProduct = $chk->fetch();
    if ($erpProduct && empty($erpProduct['erp_managed'])) {
        $erpProduct = null;
    }
}
if ($erpProduct) {
    $price = $erpProduct['price'];
    $description = (string)$erpProduct['description'];
}

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

// Foto enviada pelo formulário
$uploadError = null;
if (!empty($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    try {
        $newUrl = mmsSaveProductPhoto($_FILES['photo'], $productId);
        $pdo->prepare('UPDATE products SET image_url = :u WHERE id = :id')->execute(['u' => $newUrl, 'id' => $productId]);
    } catch (RuntimeException $e) {
        $uploadError = $e->getMessage();
    }
}

if ($erpProduct) {
    $pdo->prepare(
        "UPDATE products p SET p.active = (
            p.image_url IS NOT NULL AND p.image_url <> '' AND p.category IS NOT NULL AND p.category <> ''
            AND EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.active = 1))
         WHERE p.id = :id"
    )->execute(['id' => $productId]);
}

if ($uploadError !== null) {
    header('Location: product_form.php?id=' . $productId . '&upload_error=' . urlencode($uploadError));
    exit;
}
if ($erpProduct) {
    header('Location: products.php?saved=1');
    exit;
}

// Produto novo já direciona pra tela de variações, já que sem
// pelo menos 1 variação o produto não pode ser vendido.
header('Location: product_variants.php?product_id=' . $productId . '&saved=1');
exit;
