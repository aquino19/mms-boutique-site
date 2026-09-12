<?php
require_once __DIR__ . '/includes/config.php';

$pdo = getDbConnection();
$cart = getCart();

$items = [];
$total = 0.0;

if (!empty($cart)) {
    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $products = $stmt->fetchAll();

    foreach ($products as $p) {
        $qty = $cart[$p['id']];
        $subtotal = $qty * (float)$p['price'];
        $total += $subtotal;
        $items[] = ['product' => $p, 'qty' => $qty, 'subtotal' => $subtotal];
    }
}

$pageTitle = 'Carrinho';
require __DIR__ . '/includes/site_head.php';
?>

<style>
  .cart-table{ width:100%; border-collapse:collapse; margin-top:8px; }
  .cart-table th, .cart-table td{
    text-align:left; padding:16px 8px; border-bottom:1px solid var(--line); font-size:14px;
  }
  .cart-table th{ font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:rgba(46,31,24,.6); }
  .cart-table .prod-name{ font-family:var(--serif); font-size:17px; }
  .cart-table .total-row td{ font-weight:700; font-size:17px; border-bottom:none; font-family:var(--serif); }
  .remove-link{ color:var(--rose-deep); font-size:12px; text-decoration:underline; }
  .empty-state{ text-align:center; padding:80px 20px; color:rgba(46,31,24,.6); font-family:var(--serif); font-size:18px; }
  .empty-state a{ text-decoration:underline; color:var(--emerald-deep); }
  .cart-actions{ margin-top:28px; }
</style>

<h1 class="page-title">Seu carrinho</h1>

<?php if (empty($items)): ?>
  <p class="empty-state">Seu carrinho está vazio. <a href="catalog.php">Ver produtos</a></p>
<?php else: ?>
  <table class="cart-table">
    <thead>
      <tr><th>Produto</th><th>Qtd</th><th>Subtotal</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item): ?>
        <tr>
          <td class="prod-name"><?= htmlspecialchars($item['product']['name']) ?></td>
          <td><?= (int)$item['qty'] ?></td>
          <td>R$ <?= number_format($item['subtotal'], 2, ',', '.') ?></td>
          <td><a class="remove-link" href="cart_remove.php?product_id=<?= (int)$item['product']['id'] ?>">remover</a></td>
        </tr>
      <?php endforeach; ?>
      <tr class="total-row">
        <td colspan="2">Total</td>
        <td colspan="2">R$ <?= number_format($total, 2, ',', '.') ?></td>
      </tr>
    </tbody>
  </table>

  <div class="cart-actions">
    <a href="checkout.php" class="btn solid">Finalizar compra</a>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/site_foot.php'; ?>
