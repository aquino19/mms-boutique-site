<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pdo = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ceps = $_POST['cep'] ?? [];
    $stmt = $pdo->prepare('UPDATE store_locations SET cep = :cep WHERE id = :id');
    foreach ($ceps as $locationId => $cep) {
        $cep = trim($cep);
        $stmt->execute(['cep' => $cep !== '' ? $cep : null, 'id' => (int)$locationId]);
    }
    header('Location: locations.php?saved=1');
    exit;
}

$locations = $pdo->query('SELECT * FROM store_locations ORDER BY id')->fetchAll();

$pageTitle = 'Unidades';
require __DIR__ . '/includes/layout_start.php';
?>

<h1>Unidades físicas</h1>

<?php if (isset($_GET['saved'])): ?>
  <div class="success">CEPs salvos com sucesso.</div>
<?php endif; ?>

<p style="margin-bottom:16px; font-size:13px; color:#777;">
  O CEP de cada unidade é usado como origem na cotação de frete (Melhor Envio). Sem ele, não dá pra calcular entrega dos itens que saem dessa loja.
</p>

<form class="card" method="post" action="locations.php" style="max-width:500px;">
  <?php foreach ($locations as $loc): ?>
    <label for="cep_<?= (int)$loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></label>
    <input type="text" id="cep_<?= (int)$loc['id'] ?>" name="cep[<?= (int)$loc['id'] ?>]"
           placeholder="00000-000" value="<?= htmlspecialchars($loc['cep'] ?? '') ?>">
  <?php endforeach; ?>

  <div style="margin-top:20px;">
    <button type="submit" class="btn">Salvar</button>
  </div>
</form>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
