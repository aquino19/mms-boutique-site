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

// Andamento depois do pagamento (ver order_update.php)
$fulfillmentFlow = [
    'pickup'   => ['novo', 'separado', 'entregue'],
    'delivery' => ['novo', 'separado', 'enviado', 'entregue'],
];
$fulfillmentLabels = [
    'novo'     => 'Aguardando separação',
    'separado' => 'Separado',
    'enviado'  => 'Enviado',
    'entregue' => 'Concluído',
];
$fulfillmentNextLabels = [
    'pickup'   => ['separado' => 'Separado · pronto para retirada', 'entregue' => 'Retirado pela cliente'],
    'delivery' => ['separado' => 'Separado · pronto para envio', 'enviado' => 'Enviado', 'entregue' => 'Entregue'],
];

if (empty($_SESSION['orders_csrf'])) {
    $_SESSION['orders_csrf'] = bin2hex(random_bytes(16));
}
$flash = $_SESSION['orders_flash'] ?? null;
unset($_SESSION['orders_flash']);

$pendingErp = array_filter($orders, fn($o) => $o['status'] === 'paid' && $o['erp_registered_at'] === null);

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
  .flash{ padding:12px 16px; margin-bottom:16px; font-size:14px; background:#e6f2ea; border-left:3px solid #0E5C33; }
  .flash.error{ background:#fbe9e5; border-left-color:#8C5443; }
  .erp-alert{ padding:12px 16px; margin-bottom:16px; font-size:14px; background:#fff4d6; border-left:3px solid #D4AF37; }
  .order-flow{ display:flex; flex-wrap:wrap; gap:12px 20px; align-items:center; padding:12px 16px; background:#faf7f0; border-top:1px solid var(--line); font-size:13px; }
  .order-flow .erp-ok{ color:#0E5C33; font-weight:700; }
  .order-flow .erp-missing{ color:#8C5443; font-weight:700; }
  .order-flow form{ display:flex; flex-wrap:wrap; gap:10px 14px; align-items:center; margin-left:auto; }
  .order-flow select{ padding:6px 8px; font-size:13px; }
  .order-flow label.erp-check{ display:flex; gap:6px; align-items:center; font-weight:600; cursor:pointer; }
  .order-flow button{ padding:7px 14px; font-size:12px; font-weight:700; cursor:pointer; background:#0E5C33; color:#fff; border:none; }
</style>

<h1>Pedidos (<?= count($orders) ?>)</h1>

<?php if ($flash): ?>
  <div class="flash<?= $flash['error'] ? ' error' : '' ?>"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>

<?php if ($pendingErp): ?>
  <div class="erp-alert">
    ⚠ <?= count($pendingErp) ?> pedido(s) pago(s) ainda <strong>não lançado(s) no sistema da loja</strong>:
    <?= implode(', ', array_map(fn($o) => '#' . (int)$o['id'], $pendingErp)) ?>.
    Lance a venda no sistema e dê baixa no estoque antes de separar.
  </div>
<?php endif; ?>

<?php if (empty($orders)): ?>
  <p>Nenhum pedido ainda. Assim que o checkout estiver ativo, as compras aparecem aqui automaticamente.</p>
<?php else: ?>
  <?php foreach ($orders as $o):
      $itemsStmt->execute(['id' => $o['id']]);
      $items = $itemsStmt->fetchAll();
  ?>
    <div class="order-block" id="pedido-<?= (int)$o['id'] ?>">
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
      <?php if ($o['status'] === 'paid'):
          $flow = $fulfillmentFlow[$o['fulfillment_method']] ?? $fulfillmentFlow['delivery'];
          $pos = array_search($o['fulfillment_status'], $flow, true);
          $nextOptions = $pos === false ? [] : array_slice($flow, $pos + 1);
      ?>
        <div class="order-flow">
          <span>Andamento: <strong><?= $fulfillmentLabels[$o['fulfillment_status']] ?? htmlspecialchars($o['fulfillment_status']) ?></strong></span>
          <?php if ($o['erp_registered_at']): ?>
            <span class="erp-ok">✓ Lançado no sistema da loja em <?= date('d/m/Y H:i', strtotime($o['erp_registered_at'])) ?></span>
          <?php else: ?>
            <span class="erp-missing">✗ Ainda não lançado no sistema da loja</span>
          <?php endif; ?>
          <?php if ($nextOptions): ?>
            <form method="post" action="order_update.php">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['orders_csrf']) ?>">
              <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
              <select name="fulfillment_status" required>
                <?php foreach ($nextOptions as $opt): ?>
                  <option value="<?= $opt ?>"><?= htmlspecialchars($fulfillmentNextLabels[$o['fulfillment_method']][$opt] ?? $fulfillmentLabels[$opt]) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (!$o['erp_registered_at']): ?>
                <label class="erp-check">
                  <input type="checkbox" name="erp_confirm" value="1" required>
                  Lancei a venda no sistema da loja e dei baixa no estoque
                </label>
              <?php endif; ?>
              <button type="submit">Atualizar</button>
            </form>
          <?php endif; ?>
        </div>
      <?php elseif ($o['status'] === 'pending'): ?>
        <div class="order-flow"><span style="color:#777;">Aguardando pagamento. A separação é liberada quando o pagamento for aprovado.</span></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
