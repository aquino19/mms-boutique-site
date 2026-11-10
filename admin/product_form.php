<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pdo = getDbConnection();

$product = [
    'id' => null,
    'sku' => '',
    'name' => '',
    'description' => '',
    'price' => '',
    'category' => '',
    'image_url' => '',
    'weight_kg' => '0.300',
    'height_cm' => '15.00',
    'width_cm' => '20.00',
    'length_cm' => '5.00',
    'active' => 1,
    'erp_managed' => 0,
    'erp_ref' => null,
];

$categories = ['Vestidos', 'Conjuntos', 'Coleção Nova', 'Infantil', 'Moda Masculina', 'Plus Size', 'Acessórios'];

$isEdit = isset($_GET['id']);

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
    $stmt->execute(['id' => (int)$_GET['id']]);
    $found = $stmt->fetch();

    if (!$found) {
        header('Location: products.php');
        exit;
    }
    $product = $found;
}

$fromErp = !empty($product['erp_managed']);
$erpVariants = [];
if ($fromErp) {
    $vs = $pdo->prepare('SELECT size, sku, active FROM product_variants WHERE product_id = :id ORDER BY sku');
    $vs->execute(['id' => (int)$product['id']]);
    $erpVariants = $vs->fetchAll();
}

$pageTitle = $isEdit ? 'Editar produto' : 'Novo produto';
require __DIR__ . '/includes/layout_start.php';
?>

<h1><?= $isEdit ? 'Editar produto' : 'Novo produto' ?></h1>

<?php if (isset($_GET['upload_error'])): ?>
  <div class="error" style="background:#fbe9e5; border-left:3px solid #8C5443; padding:10px 14px; margin-bottom:14px;">
    A foto não foi salva: <?= htmlspecialchars($_GET['upload_error']) ?>
  </div>
<?php endif; ?>

<?php if ($fromErp): ?>
  <div class="card" style="background:#f3f8f4; border-left:3px solid #0E5C33; padding:14px 18px;">
    <strong>Produto do SG Master</strong> · Referência: <code><?= htmlspecialchars((string)$product['erp_ref']) ?></code><br>
    <span style="font-size:13px;">Preço, descrição, tamanhos, peso e estoque vêm do sistema da loja e são atualizados sozinhos.
    Aqui você define <b>foto</b>, <b>categoria</b> e o <b>nome</b> que aparece no site. A cor de cada tamanho fica em "Gerenciar cores, tamanhos e estoque".
    A peça aparece no site assim que tiver foto e categoria, e enquanto estiver marcada "Disponibilizar online" no SG Master.</span>
    <div style="margin-top:8px; font-size:13px;">Tamanhos:
      <?php foreach ($erpVariants as $v): ?>
        <span style="display:inline-block; border:1px solid #ccc; padding:2px 8px; margin:2px; <?= $v['active'] ? '' : 'opacity:.45; text-decoration:line-through;' ?>"><?= htmlspecialchars($v['size']) ?> <small>(<?= htmlspecialchars($v['sku']) ?>)</small></span>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<form class="card" method="post" action="product_save.php" enctype="multipart/form-data">
  <input type="hidden" name="id" value="<?= htmlspecialchars((string)$product['id']) ?>">

  <label for="name">Nome do produto</label>
  <input type="text" id="name" name="name" required value="<?= htmlspecialchars($product['name']) ?>">

  <label for="sku">SKU base (opcional por enquanto)</label>
  <input type="text" id="sku" name="sku" value="<?= htmlspecialchars($product['sku'] ?? '') ?>"
         placeholder="Código usado para casar com o sistema de estoque">

  <label for="description">Descrição</label>
  <textarea id="description" name="description"<?= $fromErp ? ' readonly style="background:#f4f4f4;"' : '' ?>><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
  <?php if ($fromErp): ?><small style="color:#777;">Para mudar, edite a "Descrição adicional web" no SG Master.</small><?php endif; ?>

  <label for="category">Categoria</label>
  <select id="category" name="category">
    <option value="">— Sem categoria —</option>
    <?php foreach ($categories as $cat): ?>
      <option value="<?= htmlspecialchars($cat) ?>" <?= ($product['category'] ?? '') === $cat ? 'selected' : '' ?>>
        <?= htmlspecialchars($cat) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label for="price">Preço (R$)</label>
  <input type="number" id="price" name="price" step="0.01" min="0" required value="<?= htmlspecialchars((string)$product['price']) ?>"<?= $fromErp ? ' readonly style="background:#f4f4f4;"' : '' ?>>
  <?php if ($fromErp): ?><small style="color:#777;">O preço vem do SG Master.</small><?php endif; ?>

  <label for="photo">Foto do produto</label>
  <?php if (!empty($product['image_url'])): ?>
    <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="" style="width:120px; aspect-ratio:3/4; object-fit:cover; display:block; margin-bottom:8px; border:1px solid #ddd;">
  <?php endif; ?>
  <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
  <small style="color:#777;">Foto vertical, de preferência da peça inteira. Pode ser direto do celular: o site ajusta o tamanho sozinho.</small>
  <input type="hidden" name="image_url" value="<?= htmlspecialchars($product['image_url'] ?? '') ?>">

  <label>Peso e dimensões (necessário para calcular frete)</label>
  <div style="display:flex; gap:10px;">
    <div style="flex:1;">
      <label for="weight_kg" style="font-weight:400; font-size:11px;">Peso (kg)</label>
      <input type="number" id="weight_kg" name="weight_kg" step="0.001" min="0.001" required value="<?= htmlspecialchars((string)$product['weight_kg']) ?>">
    </div>
    <div style="flex:1;">
      <label for="height_cm" style="font-weight:400; font-size:11px;">Altura (cm)</label>
      <input type="number" id="height_cm" name="height_cm" step="0.1" min="1" required value="<?= htmlspecialchars((string)$product['height_cm']) ?>">
    </div>
    <div style="flex:1;">
      <label for="width_cm" style="font-weight:400; font-size:11px;">Largura (cm)</label>
      <input type="number" id="width_cm" name="width_cm" step="0.1" min="1" required value="<?= htmlspecialchars((string)$product['width_cm']) ?>">
    </div>
    <div style="flex:1;">
      <label for="length_cm" style="font-weight:400; font-size:11px;">Comprimento (cm)</label>
      <input type="number" id="length_cm" name="length_cm" step="0.1" min="1" required value="<?= htmlspecialchars((string)$product['length_cm']) ?>">
    </div>
  </div>

  <?php if ($fromErp): ?>
    <p style="font-size:13px; margin-top:14px;">Situação no site: <strong><?= $product['active'] ? 'visível' : 'oculto' ?></strong>
      <?php if (!$product['active']): ?>(aparece quando tiver foto e categoria e estiver "Disponibilizar online" no SG Master)<?php endif; ?></p>
  <?php else: ?>
  <label>
    <input type="checkbox" name="active" value="1" <?= $product['active'] ? 'checked' : '' ?> style="width:auto; display:inline-block;">
    Produto ativo (visível na loja)
  </label>
  <?php endif; ?>

  <div style="margin-top:20px;">
    <button type="submit" class="btn">Salvar</button>
    <a href="products.php" class="btn secondary">Cancelar</a>
  </div>
</form>

<?php if ($isEdit): ?>
  <div style="margin-top:8px;">
    <a href="product_variants.php?product_id=<?= (int)$product['id'] ?>" class="btn secondary">
      Gerenciar cores, tamanhos e estoque →
    </a>
  </div>
<?php else: ?>
  <p style="font-size:13px; color:#777;">Salve o produto primeiro para depois cadastrar cores, tamanhos e estoque.</p>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
