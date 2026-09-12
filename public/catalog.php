<?php
require_once __DIR__ . '/includes/config.php';

$pdo = getDbConnection();
$products = $pdo->query('SELECT * FROM products WHERE active = 1 ORDER BY created_at DESC')->fetchAll();

$pageTitle = 'Catálogo';
require __DIR__ . '/includes/site_head.php';
?>

<style>
  .grid-catalog{ display:grid; grid-template-columns:repeat(3, 1fr); gap:26px; padding-top:8px; }
  @media (max-width:860px){ .grid-catalog{ grid-template-columns:1fr 1fr; } }
  @media (max-width:560px){ .grid-catalog{ grid-template-columns:1fr; } }

  .look-card{ position:relative; }
  .look-photo{
    aspect-ratio:3/4; position:relative; overflow:hidden; background:var(--cream-2);
  }
  .look-photo img{ position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
  .look-card h3{ font-family:var(--serif); font-size:19px; margin-top:14px; font-weight:600; }
  .look-card .price{ font-size:14px; color:var(--rose-deep); font-weight:700; margin-top:4px; }
  .look-card .stock{ font-size:12px; color:rgba(46,31,24,.55); margin-top:2px; }
  .look-card form{ margin-top:12px; }
  .look-card .buy-btn{
    width:100%; padding:12px; font-family:var(--sans); font-size:12px; font-weight:700;
    letter-spacing:.06em; text-transform:uppercase; border:1px solid var(--choc);
    background:var(--emerald-deep); color:var(--cream); cursor:pointer; transition:background .25s ease;
  }
  .look-card .buy-btn:hover{ background:var(--choc); }
  .look-card .buy-btn:disabled{ background:#ccc; border-color:#ccc; cursor:not-allowed; }
  .empty-state{ text-align:center; padding:80px 20px; color:rgba(46,31,24,.6); font-family:var(--serif); font-size:18px; }
</style>

<h1 class="page-title">Nossos produtos</h1>

<?php if (empty($products)): ?>
  <p class="empty-state">Ainda não há produtos disponíveis. Volte em breve!</p>
<?php else: ?>
  <div class="grid-catalog">
    <?php foreach ($products as $p): ?>
      <div class="look-card">
        <div class="look-photo">
          <img src="<?= htmlspecialchars($p['image_url'] ?: 'https://placehold.co/450x600/EFE6D1/2E1F18?text=MM%27S') ?>"
               alt="<?= htmlspecialchars($p['name']) ?>">
        </div>
        <h3><?= htmlspecialchars($p['name']) ?></h3>
        <div class="price">R$ <?= number_format((float)$p['price'], 2, ',', '.') ?></div>
        <div class="stock">
          <?= (int)$p['stock'] > 0 ? (int)$p['stock'] . ' em estoque' : 'Fora de estoque' ?>
        </div>
        <form method="post" action="cart_add.php">
          <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
          <button type="submit" class="buy-btn" <?= (int)$p['stock'] <= 0 ? 'disabled' : '' ?>>
            <?= (int)$p['stock'] <= 0 ? 'Indisponível' : 'Comprar' ?>
          </button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/site_foot.php'; ?>
