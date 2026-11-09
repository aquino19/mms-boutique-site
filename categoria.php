<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/ui.php';

$pdo = getDbConnection();
$category = trim($_GET['cat'] ?? '');
$colorFilter = trim($_GET['cor'] ?? '') ?: null;
$sort = trim($_GET['ordenar'] ?? '') ?: 'recent';
$catInfo = mmsCategories()[$category] ?? ['label' => $category, 'accent' => 'var(--emerald-deep)', 'subtitle' => ''];

if ($category === '') {
    header('Location: catalog.php');
    exit;
}

$products = getCategoryProducts($pdo, $category, $colorFilter, $sort);
$availableColors = getCategoryColors($pdo, $category);

$pageTitle = $category;
$fullBleed = true;
require __DIR__ . '/includes/site_head.php';
$base = '?cat=' . urlencode($category);
?>

<div class="crumbs"><a href="index.php">Início</a><span>›</span><b><?= mmsH($category) ?></b></div>

<div class="cat-hero" style="background:<?= $catInfo['accent'] ?>;">
  <div class="inner">
    <span class="eyebrow">MM'S BOUTIQUE</span>
    <h1><?= mmsH($category) ?></h1>
    <?php if ($catInfo['subtitle']): ?><p><?= mmsH($catInfo['subtitle']) ?></p><?php endif; ?>
  </div>
</div>

<div class="cat-toolbar-wrap">
  <div class="cat-toolbar">
    <div class="filter-colors">
      <span class="lbl">Filtrar:</span>
      <a class="chip <?= $colorFilter ? '' : 'on' ?>" href="<?= $base ?>&ordenar=<?= urlencode($sort) ?>">Todos</a>
      <?php foreach (array_slice($availableColors, 0, 8) as $c): ?>
        <a class="dot <?= $colorFilter === $c['color_name'] ? 'on' : '' ?>"
           href="<?= $base ?>&cor=<?= urlencode($c['color_name']) ?>&ordenar=<?= urlencode($sort) ?>"
           style="background:<?= mmsH($c['color_hex'] ?: '#ccc') ?>" title="<?= mmsH($c['color_name']) ?>"></a>
      <?php endforeach; ?>
    </div>
    <div class="sort-box">
      <span class="count"><?= count($products) ?> <?= count($products) === 1 ? 'peça' : 'peças' ?></span>
      <select id="sortSelect" onchange="location.href='<?= $base ?>&cor=<?= urlencode($colorFilter ?? '') ?>&ordenar='+this.value">
        <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Em destaque</option>
        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Menor preço</option>
        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Maior preço</option>
        <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Nome A-Z</option>
      </select>
    </div>
  </div>
</div>

<?php if (empty($products)): ?>
  <div class="empty-state">
    Nenhuma peça encontrada
    <a href="<?= $base ?>">Limpar filtros</a>
  </div>
<?php else: ?>
  <div class="grid-catalog">
    <?php foreach ($products as $p) renderProductCard($p); ?>
  </div>
<?php endif; ?>

<?php renderProductModal($products); ?>
<?php require __DIR__ . '/includes/site_foot.php'; ?>
