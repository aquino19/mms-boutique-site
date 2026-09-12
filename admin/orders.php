<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pdo = getDbConnection();
$orders = $pdo->query('SELECT * FROM orders ORDER BY created_at DESC')->fetchAll();

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

$pageTitle = 'Pedidos';
require __DIR__ . '/includes/layout_start.php';
?>

<h1>Pedidos (<?= count($orders) ?>)</h1>

<?php if (empty($orders)): ?>
  <p>Nenhum pedido ainda. Assim que o checkout estiver ativo, as compras aparecem aqui automaticamente.</p>
<?php else: ?>
  <table>
    <thead>
      <tr>
        <th>Pedido</th>
        <th>Cliente</th>
        <th>Total</th>
        <th>Status</th>
        <th>Data</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td>#<?= (int)$o['id'] ?></td>
          <td>
            <?= htmlspecialchars($o['customer_name'] ?: '—') ?><br>
            <span style="color:#777; font-size:12px;"><?= htmlspecialchars($o['customer_email'] ?: '') ?></span>
          </td>
          <td>R$ <?= number_format((float)$o['total_amount'], 2, ',', '.') ?></td>
          <td>
            <span class="badge <?= $statusClass[$o['status']] ?? 'inactive' ?>">
              <?= $statusLabels[$o['status']] ?? htmlspecialchars($o['status']) ?>
            </span>
          </td>
          <td><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
