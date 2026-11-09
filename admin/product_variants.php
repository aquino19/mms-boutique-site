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

$variants = getProductVariants($pdo, $productId);

$pageTitle = 'Variações — ' . $product['name'];
require __DIR__ . '/includes/layout_start.php';
?>

<div class="toolbar">
  <h1 style="margin-bottom:0;">Variações — <?= htmlspecialchars($product['name']) ?></h1>
  <a href="variant_form.php?product_id=<?= $productId ?>" class="btn">+ Nova variação</a>
</div>

<p style="margin-bottom:20px;"><a href="product_form.php?id=<?= $productId ?>">← Voltar pro produto</a></p>

<?php if (isset($_GET['saved'])): ?>
  <div class="success">Produto salvo. Agora cadastre pelo menos uma cor/tamanho com estoque.</div>
<?php endif; ?>

<?php if (empty($variants)): ?>
  <p>Nenhuma variação cadastrada ainda. Sem isso, o produto não aparece disponível na loja.</p>
<?php else: ?>
  <table>
    <thead>
      <tr>
        <th>Cor</th>
        <th>Tamanho</th>
        <th>Estoque total</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($variants as $v): ?>
        <tr>
          <td>
            <?php if ($v['color_hex']): ?>
              <span style="display:inline-block; width:14px; height:14px; border-radius:50%; background:<?= htmlspecialchars($v['color_hex']) ?>; border:1px solid rgba(0,0,0,.15); vertical-align:middle; margin-right:6px;"></span>
            <?php endif; ?>
            <?= htmlspecialchars($v['color_name'] ?: '—') ?>
          </td>
          <td><?= htmlspecialchars($v['size'] ?: '—') ?></td>
          <td><?= (int)$v['total_stock'] ?></td>
          <td>
            <span class="badge <?= $v['active'] ? 'active' : 'inactive' ?>">
              <?= $v['active'] ? 'Ativa' : 'Inativa' ?>
            </span>
          </td>
          <td>
            <a class="btn secondary btn-sm" href="variant_form.php?product_id=<?= $productId ?>&id=<?= (int)$v['id'] ?>">Editar</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
