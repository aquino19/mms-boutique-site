<?php
/**
 * Clientes: agrupa os pedidos pelo e-mail (o site não tem cadastro com senha).
 * Lista com nome, contato, número de pedidos e total gasto; ao clicar,
 * mostra o histórico de compras daquele e-mail.
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pdo = getDbConnection();
$detail = isset($_GET['email']) ? mb_strtolower(trim((string)$_GET['email'])) : null;
$q = trim((string)($_GET['q'] ?? ''));

$statusLabels = ['pending' => 'Pendente', 'paid' => 'Pago', 'failed' => 'Recusado', 'refunded' => 'Estornado'];
$fulfillmentLabels = ['novo' => 'Aguardando separação', 'separado' => 'Separado', 'enviado' => 'Enviado', 'entregue' => 'Concluído'];

function brl($v): string { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }

if ($detail === null) {
    $where = '';
    $params = [];
    if ($q !== '') {
        $where = 'WHERE customer_name LIKE :q1 OR customer_email LIKE :q2 OR customer_phone LIKE :q3';
        $params = ['q1' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%"];
    }
    $stmt = $pdo->prepare(
        "SELECT LOWER(customer_email) AS email,
                SUBSTRING_INDEX(GROUP_CONCAT(customer_name ORDER BY created_at DESC SEPARATOR '\\n'), '\\n', 1) AS name,
                SUBSTRING_INDEX(GROUP_CONCAT(NULLIF(customer_phone, '') ORDER BY created_at DESC SEPARATOR '\\n'), '\\n', 1) AS phone,
                COUNT(*) AS orders_total,
                SUM(status = 'paid') AS orders_paid,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount END), 0) AS spent,
                MAX(created_at) AS last_order
         FROM orders $where
         GROUP BY LOWER(customer_email)
         ORDER BY last_order DESC"
    );
    $stmt->execute($params);
    $customers = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE LOWER(customer_email) = :e ORDER BY created_at DESC');
    $stmt->execute(['e' => $detail]);
    $orders = $stmt->fetchAll();
    $itemsStmt = $pdo->prepare(
        'SELECT oi.quantity, oi.unit_price, oi.variant_color, oi.variant_size, p.name
         FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = :id'
    );
    $paidTotal = 0.0;
    $paidCount = 0;
    foreach ($orders as &$o) {
        $itemsStmt->execute(['id' => $o['id']]);
        $o['items'] = $itemsStmt->fetchAll();
        if ($o['status'] === 'paid') {
            $paidTotal += (float)$o['total_amount'];
            $paidCount++;
        }
    }
    unset($o);
}

$pageTitle = 'Clientes';
require __DIR__ . '/includes/layout_start.php';
?>
<style>
  .cust-table{ width:100%; border-collapse:collapse; font-size:14px; background:#fff; }
  .cust-table th, .cust-table td{ text-align:left; padding:10px 12px; border-bottom:1px solid #eee; vertical-align:top; }
  .cust-table th{ font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#777; }
  .cust-table td.num{ text-align:right; white-space:nowrap; }
  .cust-stats{ display:flex; gap:28px; flex-wrap:wrap; margin:6px 0 18px; font-size:14px; }
  .cust-stats b{ display:block; font-size:20px; }
  .pill{ display:inline-block; font-size:11px; font-weight:700; padding:2px 8px; background:#eee; }
  .pill.paid{ background:#e6f2ea; color:#0E5C33; } .pill.failed, .pill.refunded{ background:#fbe9e5; color:#8C5443; }
</style>

<?php if ($detail === null): ?>
  <h1>Clientes (<?= count($customers) ?>)</h1>
  <form method="get" style="margin-bottom:14px; display:flex; gap:8px;">
    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Buscar por nome, e-mail ou telefone" style="flex:1;">
    <button class="btn" type="submit">Buscar</button>
    <?php if ($q !== ''): ?><a class="btn secondary" href="customers.php">Limpar</a><?php endif; ?>
  </form>
  <p style="font-size:13px; color:#777;">Cada cliente é identificada pelo e-mail usado na compra. O total gasto considera só pedidos pagos.</p>
  <table class="cust-table">
    <thead><tr><th>Cliente</th><th>Contato</th><th class="num">Pedidos</th><th class="num">Total gasto</th><th>Último pedido</th></tr></thead>
    <tbody>
      <?php foreach ($customers as $c): ?>
        <tr>
          <td><a href="customers.php?email=<?= urlencode($c['email']) ?>"><strong><?= htmlspecialchars((string)$c['name']) ?></strong></a></td>
          <td><?= htmlspecialchars($c['email']) ?><?= $c['phone'] ? '<br><small>' . htmlspecialchars($c['phone']) . '</small>' : '' ?></td>
          <td class="num"><?= (int)$c['orders_paid'] ?> pago(s)<?= (int)$c['orders_total'] > (int)$c['orders_paid'] ? '<br><small style="color:#999;">' . ((int)$c['orders_total'] - (int)$c['orders_paid']) . ' não pago(s)</small>' : '' ?></td>
          <td class="num"><?= brl($c['spent']) ?></td>
          <td><?= date('d/m/Y', strtotime($c['last_order'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$customers): ?><tr><td colspan="5">Nenhuma cliente encontrada.</td></tr><?php endif; ?>
    </tbody>
  </table>
<?php else: ?>
  <p><a href="customers.php">← Todas as clientes</a></p>
  <?php if (!$orders): ?>
    <h1>Cliente não encontrada</h1>
  <?php else: ?>
    <h1><?= htmlspecialchars((string)$orders[0]['customer_name']) ?></h1>
    <p><?= htmlspecialchars($detail) ?><?= $orders[0]['customer_phone'] ? ' · ' . htmlspecialchars((string)$orders[0]['customer_phone']) : '' ?>
      <?php
        $phoneDigits = preg_replace('/\D/', '', (string)$orders[0]['customer_phone']);
        if (strlen($phoneDigits) >= 10):
            $wa = (strlen($phoneDigits) <= 11 ? '55' : '') . $phoneDigits; ?>
        · <a href="https://wa.me/<?= $wa ?>" target="_blank" rel="noopener">WhatsApp</a>
      <?php endif; ?>
    </p>
    <div class="cust-stats">
      <div><b><?= $paidCount ?></b>pedido(s) pago(s)</div>
      <div><b><?= brl($paidTotal) ?></b>total gasto</div>
      <div><b><?= $paidCount ? brl($paidTotal / $paidCount) : '—' ?></b>ticket médio</div>
      <div><b><?= date('d/m/Y', strtotime(end($orders)['created_at'])) ?></b>cliente desde</div>
    </div>
    <table class="cust-table">
      <thead><tr><th>Pedido</th><th>Data</th><th>Peças</th><th>Situação</th><th class="num">Total</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td><a href="orders.php#pedido-<?= (int)$o['id'] ?>">#<?= (int)$o['id'] ?></a><br><small><?= $o['fulfillment_method'] === 'pickup' ? 'Retirada' : 'Entrega' ?></small></td>
            <td><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></td>
            <td><?php foreach ($o['items'] as $it): ?><?= (int)$it['quantity'] ?>x <?= htmlspecialchars($it['name']) ?> <small style="color:#888;">(<?= htmlspecialchars(trim($it['variant_color'] . ' ' . $it['variant_size'])) ?>)</small><br><?php endforeach; ?></td>
            <td><span class="pill <?= htmlspecialchars($o['status']) ?>"><?= $statusLabels[$o['status']] ?? htmlspecialchars($o['status']) ?></span>
              <?php if ($o['status'] === 'paid'): ?><br><small><?= $fulfillmentLabels[$o['fulfillment_status'] ?? 'novo'] ?? '' ?></small><?php endif; ?></td>
            <td class="num"><?= brl($o['total_amount']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/layout_end.php'; ?>
