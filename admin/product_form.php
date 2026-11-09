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

$pageTitle = $isEdit ? 'Editar produto' : 'Novo produto';
require __DIR__ . '/includes/layout_start.php';
?>

<h1><?= $isEdit ? 'Editar produto' : 'Novo produto' ?></h1>

<form class="card" method="post" action="product_save.php">
  <input type="hidden" name="id" value="<?= htmlspecialchars((string)$product['id']) ?>">

  <label for="name">Nome do produto</label>
  <input type="text" id="name" name="name" required value="<?= htmlspecialchars($product['name']) ?>">

  <label for="sku">SKU base (opcional por enquanto)</label>
  <input type="text" id="sku" name="sku" value="<?= htmlspecialchars($product['sku'] ?? '') ?>"
         placeholder="Código usado para casar com o sistema de estoque">

  <label for="description">Descrição</label>
  <textarea id="description" name="description"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>

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
  <input type="number" id="price" name="price" step="0.01" min="0" required value="<?= htmlspecialchars((string)$product['price']) ?>">

  <label for="image_url">URL da imagem</label>
  <input type="text" id="image_url" name="image_url" value="<?= htmlspecialchars($product['image_url'] ?? '') ?>" placeholder="https://...">

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

  <label>
    <input type="checkbox" name="active" value="1" <?= $product['active'] ? 'checked' : '' ?> style="width:auto; display:inline-block;">
    Produto ativo (visível na loja)
  </label>

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
