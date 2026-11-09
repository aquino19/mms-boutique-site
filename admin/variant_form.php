<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pdo = getDbConnection();
$productId = (int)($_GET['product_id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
$stmt->execute(['id' => $productId]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: products.php');
    exit;
}

$variant = [
    'id' => null,
    'color_name' => '',
    'color_hex' => '#8C5443',
    'size' => '',
    'sku' => '',
    'active' => 1,
];

$isEdit = isset($_GET['id']);
$locations = getActiveLocations($pdo);
$stockByLocation = [];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM product_variants WHERE id = :id AND product_id = :pid');
    $stmt->execute(['id' => (int)$_GET['id'], 'pid' => $productId]);
    $found = $stmt->fetch();

    if (!$found) {
        header('Location: product_variants.php?product_id=' . $productId);
        exit;
    }
    $variant = $found;

    $stmt = $pdo->prepare('SELECT location_id, quantity FROM stock_by_store WHERE variant_id = :id');
    $stmt->execute(['id' => (int)$_GET['id']]);
    foreach ($stmt->fetchAll() as $row) {
        $stockByLocation[(int)$row['location_id']] = (int)$row['quantity'];
    }
}

$pageTitle = ($isEdit ? 'Editar' : 'Nova') . ' variação';
require __DIR__ . '/includes/layout_start.php';
?>

<h1><?= $isEdit ? 'Editar variação' : 'Nova variação' ?> — <?= htmlspecialchars($product['name']) ?></h1>

<form class="card" method="post" action="variant_save.php">
  <input type="hidden" name="id" value="<?= htmlspecialchars((string)$variant['id']) ?>">
  <input type="hidden" name="product_id" value="<?= $productId ?>">

  <label for="color_name">Cor (nome)</label>
  <input type="text" id="color_name" name="color_name" value="<?= htmlspecialchars($variant['color_name'] ?? '') ?>" placeholder="Ex: Verde Sálvia">

  <label for="color_hex">Cor (código hexadecimal, pra mostrar a bolinha)</label>
  <input type="text" id="color_hex" name="color_hex" value="<?= htmlspecialchars($variant['color_hex'] ?? '#8C5443') ?>" placeholder="#8C5443">

  <label for="size">Tamanho</label>
  <input type="text" id="size" name="size" value="<?= htmlspecialchars($variant['size'] ?? '') ?>" placeholder="Ex: P, M, G ou Único">

  <label for="sku">SKU da variação (opcional)</label>
  <input type="text" id="sku" name="sku" value="<?= htmlspecialchars($variant['sku'] ?? '') ?>">

  <label>Estoque por unidade</label>
  <?php foreach ($locations as $loc): ?>
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
      <span style="flex:1; font-size:13px;"><?= htmlspecialchars($loc['name']) ?></span>
      <input type="number" name="stock_location[<?= (int)$loc['id'] ?>]" step="1" min="0"
             value="<?= (int)($stockByLocation[$loc['id']] ?? 0) ?>" style="width:100px;">
    </div>
  <?php endforeach; ?>

  <label>
    <input type="checkbox" name="active" value="1" <?= $variant['active'] ? 'checked' : '' ?> style="width:auto; display:inline-block;">
    Variação ativa
  </label>

  <div style="margin-top:20px;">
    <button type="submit" class="btn">Salvar</button>
    <a href="product_variants.php?product_id=<?= $productId ?>" class="btn secondary">Cancelar</a>
  </div>
</form>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
