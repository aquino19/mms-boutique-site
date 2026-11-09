<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pdo = getDbConnection();
$coupons = $pdo->query('SELECT * FROM coupons ORDER BY created_at DESC')->fetchAll();

$pageTitle = 'Cupons';
require __DIR__ . '/includes/layout_start.php';
?>

<div class="toolbar">
  <h1 style="margin-bottom:0;">Cupons (<?= count($coupons) ?>)</h1>
  <a href="coupon_form.php" class="btn">+ Novo cupom</a>
</div>

<?php if (isset($_GET['saved'])): ?>
  <div class="success">Cupom salvo com sucesso.</div>
<?php endif; ?>

<?php if (empty($coupons)): ?>
  <p>Nenhum cupom cadastrado ainda.</p>
<?php else: ?>
  <table>
    <thead>
      <tr>
        <th>Código</th>
        <th>Desconto</th>
        <th>Válido para</th>
        <th>Período</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($coupons as $c): ?>
        <tr>
          <td><strong><?= htmlspecialchars($c['code']) ?></strong></td>
          <td><?= number_format((float)$c['discount_percent'], 0) ?>%</td>
          <td><?= $c['applies_to'] === 'all' ? 'Todos os produtos' : 'Produtos específicos' ?></td>
          <td>
            <?= $c['valid_from'] ? date('d/m/Y', strtotime($c['valid_from'])) : '—' ?>
            até
            <?= $c['valid_until'] ? date('d/m/Y', strtotime($c['valid_until'])) : 'sem prazo' ?>
          </td>
          <td>
            <span class="badge <?= $c['active'] ? 'active' : 'inactive' ?>">
              <?= $c['active'] ? 'Ativo' : 'Inativo' ?>
            </span>
          </td>
          <td><a class="btn secondary btn-sm" href="coupon_form.php?id=<?= (int)$c['id'] ?>">Editar</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
