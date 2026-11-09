<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pdo = getDbConnection();
$products = $pdo->query('SELECT * FROM products ORDER BY created_at DESC')->fetchAll();

$pageTitle = 'Produtos';
require __DIR__ . '/includes/layout_start.php';
?>

<div class="toolbar">
  <h1 style="margin-bottom:0;">Produtos (<?= count($products) ?>)</h1>
  <a href="product_form.php" class="btn">+ Novo produto</a>
</div>

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
          <td><?= htmlspecialchars($p['name']) ?></td>
          <td>R$ <?= number_format((float)$p['price'], 2, ',', '.') ?></td>
          <td><?= (int)$p['stock'] ?></td>
          <td>
            <span class="badge <?= $p['active'] ? 'active' : 'inactive' ?>">
              <?= $p['active'] ? 'Ativo' : 'Inativo' ?>
            </span>
          </td>
          <td>
            <a class="btn secondary btn-sm" href="product_form.php?id=<?= (int)$p['id'] ?>">Editar</a>
            <a class="btn secondary btn-sm" href="product_toggle.php?id=<?= (int)$p['id'] ?>">
              <?= $p['active'] ? 'Desativar' : 'Ativar' ?>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
