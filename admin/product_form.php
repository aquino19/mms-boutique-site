<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pdo = getDbConnection();

$product = [
    'id' => null,
    'sku' => '',
    'name' => '',
    'description' => '',
    'price' => '',
    'image_url' => '',
    'active' => 1,
];

$isEdit = isset($_GET['id']);
$locations = getActiveLocations($pdo);
$stockByLocation = []; // location_id => quantity

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
    $stmt->execute(['id' => (int)$_GET['id']]);
    $found = $stmt->fetch();

    if (!$found) {
        header('Location: products.php');
        exit;
    }
    $product = $found;

    $stmt = $pdo->prepare('SELECT location_id, quantity FROM stock_by_store WHERE product_id = :id');
    $stmt->execute(['id' => (int)$_GET['id']]);
    foreach ($stmt->fetchAll() as $row) {
        $stockByLocation[(int)$row['location_id']] = (int)$row['quantity'];
    }
}

$pageTitle = $isEdit ? 'Editar produto' : 'Novo produto';
require __DIR__ . '/includes/layout_start.php';
?>

<h1><?= $isEdit ? 'Editar produto' : 'Novo produto' ?></h1>

<form class="card" method="post" action="product_save.php">
  <input type="hidden" name="id" value="<?= htmlspecialchars((string)$product['id']) ?>">

  <label for="name">Nome do produto</label>
  <input type="text" id="name" name="name" required value="<?= htmlspecialchars($product['name']) ?>">

  <label for="sku">SKU / código (opcional por enquanto)</label>
  <input type="text" id="sku" name="sku" value="<?= htmlspecialchars($product['sku'] ?? '') ?>"
         placeholder="Código usado para casar com o sistema de estoque">

  <label for="description">Descrição</label>
  <textarea id="description" name="description"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>

  <label for="price">Preço (R$)</label>
  <input type="number" id="price" name="price" step="0.01" min="0" required value="<?= htmlspecialchars((string)$product['price']) ?>">

  <label>Estoque por unidade</label>
  <?php foreach ($locations as $loc): ?>
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
      <span style="flex:1; font-size:13px;"><?= htmlspecialchars($loc['name']) ?></span>
      <input type="number" name="stock_location[<?= (int)$loc['id'] ?>]" step="1" min="0"
             value="<?= (int)($stockByLocation[$loc['id']] ?? 0) ?>" style="width:100px;">
    </div>
  <?php endforeach; ?>
  <p style="font-size:12px; color:#777; margin-top:-4px;">
    O estoque total exibido na loja é a soma de todas as unidades.
  </p>

  <label for="image_url">URL da imagem</label>
  <input type="text" id="image_url" name="image_url" value="<?= htmlspecialchars($product['image_url'] ?? '') ?>" placeholder="https://...">

  <label>
    <input type="checkbox" name="active" value="1" <?= $product['active'] ? 'checked' : '' ?> style="width:auto; display:inline-block;">
    Produto ativo (visível na loja)
  </label>

  <div style="margin-top:20px;">
    <button type="submit" class="btn">Salvar</button>
    <a href="products.php" class="btn secondary">Cancelar</a>
  </div>
</form>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
