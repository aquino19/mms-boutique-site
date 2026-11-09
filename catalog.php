<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/ui.php';

$pdo = getDbConnection();
$products = getCatalogProducts($pdo);

$pageTitle = 'Todas as peças';
$fullBleed = true;
require __DIR__ . '/includes/site_head.php';
?>

<div class="crumbs"><a href="index.php">Início</a><span>›</span><b>Todas as peças</b></div>

<div class="cat-hero" style="background:var(--emerald-deep);">
  <div class="inner">
    <span class="eyebrow">MM'S BOUTIQUE</span>
    <h1>Todas as peças</h1>
    <p>Tudo o que a MM'S tem para você — escolha uma categoria no menu ou navegue por aqui.</p>
  </div>
</div>

<?php if (empty($products)): ?>
  <div class="empty-state">Em breve, novos produtos por aqui.</div>
<?php else: ?>
  <div class="grid-catalog">
    <?php foreach ($products as $p) renderProductCard($p); ?>
  </div>
<?php endif; ?>

<?php renderProductModal($products); ?>
<?php require __DIR__ . '/includes/site_foot.php'; ?>
