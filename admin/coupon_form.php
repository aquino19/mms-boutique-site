<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pdo = getDbConnection();

$coupon = [
    'id' => null,
    'code' => '',
    'discount_percent' => '',
    'applies_to' => 'all',
    'valid_from' => '',
    'valid_until' => '',
    'active' => 1,
];

$isEdit = isset($_GET['id']);
$selectedProductIds = [];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM coupons WHERE id = :id');
    $stmt->execute(['id' => (int)$_GET['id']]);
    $found = $stmt->fetch();
    if (!$found) {
        header('Location: coupons.php');
        exit;
    }
    $coupon = $found;

    $stmt = $pdo->prepare('SELECT product_id FROM coupon_products WHERE coupon_id = :id');
    $stmt->execute(['id' => (int)$_GET['id']]);
    $selectedProductIds = array_column($stmt->fetchAll(), 'product_id');
}

$products = $pdo->query('SELECT id, name FROM products ORDER BY name')->fetchAll();

$pageTitle = ($isEdit ? 'Editar' : 'Novo') . ' cupom';
require __DIR__ . '/includes/layout_start.php';
?>

<h1><?= $isEdit ? 'Editar cupom' : 'Novo cupom' ?></h1>

<form class="card" method="post" action="coupon_save.php" style="max-width:520px;">
  <input type="hidden" name="id" value="<?= htmlspecialchars((string)$coupon['id']) ?>">

  <label for="code">Código do cupom</label>
  <input type="text" id="code" name="code" required style="text-transform:uppercase;"
         value="<?= htmlspecialchars($coupon['code']) ?>" placeholder="Ex: BLACKFRIDAY20">

  <label for="discount_percent">Desconto (%)</label>
  <input type="number" id="discount_percent" name="discount_percent" step="1" min="1" max="100" required
         value="<?= htmlspecialchars((string)$coupon['discount_percent']) ?>">

  <label>Válido para</label>
  <select id="applies_to" name="applies_to" onchange="document.getElementById('productList').style.display = this.value === 'specific' ? 'block' : 'none';">
    <option value="all" <?= $coupon['applies_to'] === 'all' ? 'selected' : '' ?>>Todos os produtos</option>
    <option value="specific" <?= $coupon['applies_to'] === 'specific' ? 'selected' : '' ?>>Produtos específicos</option>
  </select>

  <div id="productList" style="display:<?= $coupon['applies_to'] === 'specific' ? 'block' : 'none' ?>; margin-top:12px; max-height:220px; overflow-y:auto; border:1px solid var(--line); padding:10px;">
    <?php foreach ($products as $p): ?>
      <label style="font-weight:400; display:flex; align-items:center; gap:8px; margin:4px 0;">
        <input type="checkbox" name="product_ids[]" value="<?= (int)$p['id'] ?>" style="width:auto;"
               <?= in_array($p['id'], $selectedProductIds) ? 'checked' : '' ?>>
        <?= htmlspecialchars($p['name']) ?>
      </label>
    <?php endforeach; ?>
    <?php if (empty($products)): ?>
      <p style="font-size:12px; color:#777;">Nenhum produto cadastrado ainda.</p>
    <?php endif; ?>
  </div>

  <label for="valid_from">Válido a partir de (opcional)</label>
  <input type="date" id="valid_from" name="valid_from" value="<?= htmlspecialchars($coupon['valid_from'] ?? '') ?>">

  <label for="valid_until">Válido até (opcional)</label>
  <input type="date" id="valid_until" name="valid_until" value="<?= htmlspecialchars($coupon['valid_until'] ?? '') ?>">

  <label>
    <input type="checkbox" name="active" value="1" <?= $coupon['active'] ? 'checked' : '' ?> style="width:auto; display:inline-block;">
    Cupom ativo
  </label>

  <div style="margin-top:20px;">
    <button type="submit" class="btn">Salvar</button>
    <a href="coupons.php" class="btn secondary">Cancelar</a>
  </div>
</form>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
