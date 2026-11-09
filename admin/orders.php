<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pdo = getDbConnection();
$orders = $pdo->query('SELECT * FROM orders ORDER BY created_at DESC')->fetchAll();

$itemsStmt = $pdo->prepare(
    'SELECT oi.*, p.name AS product_name, sl.name AS location_name
     FROM order_items oi
     JOIN products p ON p.id = oi.product_id
     LEFT JOIN store_locations sl ON sl.id = oi.fulfillment_location_id
     WHERE oi.order_id = :id'
);

$statusLabels = [
    'pending'  => 'Pendente',
    'paid'     => 'Pago',
    'failed'   => 'Falhou',
    'refunded' => 'Reembolsado',
];
$statusClass = [
    'pending'  => 'inactive',
    'paid'     => 'active',
    'failed'   => 'inactive',
    'refunded' => 'inactive',
];
$methodLabels = [
    'pickup'   => 'Retirada',
    'delivery' => 'Entrega',
];

$pageTitle = 'Pedidos';
require __DIR__ . '/includes/layout_start.php';
?>

<style>
  .order-block{ background:white; margin-bottom:16px; }
  .order-head{
    display:flex; justify-content:space-between; align-items:center;
    padding:14px 16px; border-bottom:1px solid var(--line); font-size:14px;
  }
  .order-items-table{ width:100%; border-collapse:collapse; }
  .order-items-table td{ padding:8px 16px; font-size:13px; border-bottom:1px solid var(--line); }
  .order-items-table tr:last-child td{ border-bottom:none; }
  .loc-tag{
    display:inline-block; font-size:11px; font-weight:700; padding:2px 8px;
    background:var(--cream-2); border-radius:10px; margin-left:8px;
  }
  .variant-tag{ color:rgba(46,31,24,.6); font-size:12px; }
</style>

<h1>Pedidos (<?= count($orders) ?>)</h1>

<?php if (empty($orders)): ?>
  <p>Nenhum pedido ainda. Assim que o checkout estiver ativo, as compras aparecem aqui automaticamente.</p>
<?php else: ?>
  <?php foreach ($orders as $o):
      $itemsStmt->execute(['id' => $o['id']]);
      $items = $itemsStmt->fetchAll();
  ?>
    <div class="order-block">
      <div class="order-head">
        <div>
          <strong>#<?= (int)$o['id'] ?></strong> —
          <?= htmlspecialchars($o['customer_name'] ?: '—') ?>
          <span style="color:#777;">(<?= htmlspecialchars($o['customer_email'] ?: '') ?>)</span>
          <?php if ($o['fulfillment_method']): ?>
            <span class="loc-tag"><?= $methodLabels[$o['fulfillment_method']] ?? htmlspecialchars($o['fulfillment_method']) ?></span>
          <?php endif; ?>
          <?php if ($o['coupon_code']): ?>
            <span class="loc-tag">Cupom: <?= htmlspecialchars($o['coupon_code']) ?> (-R$<?= number_format((float)$o['discount_amount'], 2, ',', '.') ?>)</span>
          <?php endif; ?>
          <?php if ($o['shipping_cost'] > 0): ?>
            <span class="loc-tag">Frete: <?= htmlspecialchars($o['shipping_service']) ?> (R$<?= number_format((float)$o['shipping_cost'], 2, ',', '.') ?>)</span>
          <?php endif; ?>
        </div>
        <div>
          <span class="badge <?= $statusClass[$o['status']] ?? 'inactive' ?>">
            <?= $statusLabels[$o['status']] ?? htmlspecialchars($o['status']) ?>
          </span>
          <strong style="margin-left:12px;">R$ <?= number_format((float)$o['total_amount'], 2, ',', '.') ?></strong>
          <span style="color:#777; margin-left:12px; font-size:12px;"><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></span>
        </div>
      </div>
      <table class="order-items-table">
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td style="width:36px;"><?= (int)$item['quantity'] ?>x</td>
              <td>
                <?= htmlspecialchars($item['product_name'] ?? ('Produto #' . $item['product_id'])) ?>
                <?php if ($item['variant_color'] || $item['variant_size']): ?>
                  <span class="variant-tag">
                    (<?= htmlspecialchars(trim(($item['variant_color'] ?? '') . ' ' . ($item['variant_size'] ?? ''))) ?>)
                  </span>
                <?php endif; ?>
              </td>
              <td style="text-align:right; color:#777;">
                <?= $item['location_name'] ? 'Sai de: ' . htmlspecialchars($item['location_name']) : ($o['fulfillment_method'] === 'delivery' ? 'Entrega' : '—') ?>
              </td>
              <td style="text-align:right; width:100px;">
                R$ <?= number_format($item['quantity'] * (float)$item['unit_price'], 2, ',', '.') ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if ($o['fulfillment_method'] === 'delivery' && $o['address_street']): ?>
            <tr>
              <td colspan="4" style="color:#777; font-size:12px;">
                📍 <?= htmlspecialchars($o['address_street']) ?>, <?= htmlspecialchars($o['address_number']) ?>
                <?= $o['address_complement'] ? '— ' . htmlspecialchars($o['address_complement']) : '' ?>
                — <?= htmlspecialchars($o['address_neighborhood']) ?>, <?= htmlspecialchars($o['address_city']) ?>/<?= htmlspecialchars($o['address_state']) ?>
                — CEP <?= htmlspecialchars($o['address_zip']) ?>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
