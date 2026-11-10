<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pdo = getDbConnection();
$filter = $_GET['f'] ?? '';
$sql = 'SELECT p.*, (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id AND v.active = 1) AS sizes_active FROM products p';
if ($filter === 'pendentes') {
    $sql .= " WHERE p.erp_managed = 1 AND (p.image_url IS NULL OR p.image_url = '' OR p.category IS NULL OR p.category = '')
              AND EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.active = 1)";
}
$products = $pdo->query($sql . ' ORDER BY p.created_at DESC')->fetchAll();

$lastSync = null;
$pendingCount = 0;
try {
    $lastSync = $pdo->query('SELECT * FROM erp_sync_log ORDER BY id DESC LIMIT 1')->fetch() ?: null;
    $pendingCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM products p WHERE p.erp_managed = 1 AND (p.image_url IS NULL OR p.image_url = '' OR p.category IS NULL OR p.category = '')
         AND EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.active = 1)"
    )->fetchColumn();
} catch (PDOException $e) {
    // migração do SG Master ainda não aplicada
}

$pageTitle = 'Produtos';
require __DIR__ . '/includes/layout_start.php';
?>

<div class="toolbar">
  <h1 style="margin-bottom:0;">Produtos (<?= count($products) ?>)</h1>
  <a href="product_form.php" class="btn">+ Novo produto</a>
</div>

<?php if ($lastSync): ?>
  <?php $minutes = (int)floor((time() - strtotime($lastSync['received_at'])) / 60); ?>
  <div class="card" style="padding:12px 16px; font-size:13px; <?= $minutes > 30 ? 'border-left:3px solid #8C5443; background:#fbe9e5;' : 'border-left:3px solid #0E5C33; background:#f3f8f4;' ?>">
    <strong>Sincronização com o SG Master:</strong>
    última em <?= date('d/m/Y H:i', strtotime($lastSync['received_at'])) ?>
    (<?= $minutes < 1 ? 'agora há pouco' : "há $minutes min" ?>) ·
    <?= (int)$lastSync['items_received'] ?> produtos online no sistema da loja.
    <?php if ($minutes > 30): ?><br><b>Atenção:</b> faz mais de 30 minutos sem sincronizar. Confira se o PC da loja está ligado e com internet.<?php endif; ?>
    <?php if (!empty($lastSync['notes'])): ?>
      <details style="margin-top:6px;"><summary>Avisos da última sincronização</summary><pre style="white-space:pre-wrap; font-size:12px;"><?= htmlspecialchars($lastSync['notes']) ?></pre></details>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($pendingCount > 0 || $filter === 'pendentes'): ?>
  <div class="card" style="padding:12px 16px; font-size:14px; background:#fff4d6; border-left:3px solid #D4AF37;">
    <?php if ($filter === 'pendentes'): ?>
      Mostrando só as peças que <b>aguardam foto ou categoria</b>. <a href="products.php">Ver todos os produtos</a>
    <?php else: ?>
      📷 <b><?= $pendingCount ?></b> peça(s) do SG Master aguardando foto ou categoria para aparecer no site.
      <a href="products.php?f=pendentes">Ver só essas</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if (isset($_GET['saved'])): ?>
  <div class="success">Produto salvo com sucesso.</div>
<?php endif; ?>

<?php if (empty($products)): ?>
  <p>Nenhum produto cadastrado ainda. Clique em "Novo produto" para começar.</p>
<?php else: ?>
  <table>
    <thead>
      <tr>
        <th>Nome</th>
        <th>Preço</th>
        <th>Estoque</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($products as $p): ?>
        <tr>
          <td>
            <?php if (!empty($p['image_url'])): ?><img src="<?= htmlspecialchars($p['image_url']) ?>" alt="" style="width:34px; height:45px; object-fit:cover; vertical-align:middle; margin-right:8px;"><?php endif; ?>
            <?= htmlspecialchars($p['name']) ?>
            <?php if (!empty($p['erp_managed'])): ?>
              <span class="badge" style="background:#e6f2ea; color:#0E5C33;">SG Master</span>
              <?php if (empty($p['image_url'])): ?><span class="badge" style="background:#fff4d6; color:#7a5c00;">falta foto</span><?php endif; ?>
              <?php if (empty($p['category'])): ?><span class="badge" style="background:#fff4d6; color:#7a5c00;">falta categoria</span><?php endif; ?>
              <?php if ((int)$p['sizes_active'] === 0): ?><span class="badge inactive">fora do online</span><?php endif; ?>
            <?php endif; ?>
          </td>
          <td>R$ <?= number_format((float)$p['price'], 2, ',', '.') ?></td>
          <td><?= (int)$p['stock'] ?></td>
          <td>
            <span class="badge <?= $p['active'] ? 'active' : 'inactive' ?>">
              <?= $p['active'] ? 'Ativo' : 'Inativo' ?>
            </span>
          </td>
          <td>
            <a class="btn secondary btn-sm" href="product_form.php?id=<?= (int)$p['id'] ?>"><?= (!empty($p['erp_managed']) && empty($p['image_url'])) ? 'Adicionar foto' : 'Editar' ?></a>
            <?php if (empty($p['erp_managed'])): ?>
            <a class="btn secondary btn-sm" href="product_toggle.php?id=<?= (int)$p['id'] ?>">
              <?= $p['active'] ? 'Desativar' : 'Ativar' ?>
            </a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
