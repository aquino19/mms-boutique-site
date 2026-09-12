<?php
require_once __DIR__ . '/includes/config.php';

$pdo = getDbConnection();
$cart = getCart();

if (empty($cart)) {
    header('Location: cart.php');
    exit;
}

$locations = getActiveLocations($pdo);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['customer_name'] ?? '');
    $email = trim($_POST['customer_email'] ?? '');
    $phone = trim($_POST['customer_phone'] ?? '');
    $fulfillmentMethod = $_POST['fulfillment_method'] ?? '';

    $address = [
        'street' => trim($_POST['address_street'] ?? ''),
        'number' => trim($_POST['address_number'] ?? ''),
        'complement' => trim($_POST['address_complement'] ?? ''),
        'neighborhood' => trim($_POST['address_neighborhood'] ?? ''),
        'city' => trim($_POST['address_city'] ?? ''),
        'state' => trim($_POST['address_state'] ?? ''),
        'zip' => trim($_POST['address_zip'] ?? ''),
    ];

    if ($name === '') {
        $errors[] = 'Informe seu nome.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Informe um e-mail válido.';
    }
    if (!in_array($fulfillmentMethod, ['pickup', 'delivery'], true)) {
        $errors[] = 'Escolha retirar na loja ou receber em casa.';
    }
    if ($fulfillmentMethod === 'delivery') {
        if ($address['street'] === '' || $address['number'] === '' || $address['city'] === '' || $address['zip'] === '') {
            $errors[] = 'Preencha o endereço completo para entrega.';
        }
    }

    $allocationResult = null;
    if (empty($errors)) {
        $allocationResult = allocateFulfillment($pdo, $cart);
        if (!$allocationResult['success']) {
            $errors[] = $allocationResult['error'];
        }
    }

    if (empty($errors)) {
        $allocations = $allocationResult['allocations'];
        $total = 0.0;
        foreach ($allocations as $a) {
            $total += $a['quantity'] * $a['unit_price'];
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO orders (
                    customer_name, customer_email, customer_phone, total_amount, status,
                    fulfillment_method, address_street, address_number, address_complement,
                    address_neighborhood, address_city, address_state, address_zip
                ) VALUES (
                    :name, :email, :phone, :total, :status,
                    :method, :street, :number, :complement,
                    :neighborhood, :city, :state, :zip
                )'
            );
            $stmt->execute([
                'name' => $name, 'email' => $email, 'phone' => $phone,
                'total' => $total, 'status' => 'pending',
                'method' => $fulfillmentMethod,
                'street' => $fulfillmentMethod === 'delivery' ? $address['street'] : null,
                'number' => $fulfillmentMethod === 'delivery' ? $address['number'] : null,
                'complement' => $fulfillmentMethod === 'delivery' ? $address['complement'] : null,
                'neighborhood' => $fulfillmentMethod === 'delivery' ? $address['neighborhood'] : null,
                'city' => $fulfillmentMethod === 'delivery' ? $address['city'] : null,
                'state' => $fulfillmentMethod === 'delivery' ? $address['state'] : null,
                'zip' => $fulfillmentMethod === 'delivery' ? $address['zip'] : null,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, quantity, unit_price, fulfillment_location_id)
                 VALUES (:order_id, :product_id, :quantity, :unit_price, :location_id)'
            );
            $stockStmt = $pdo->prepare(
                'UPDATE stock_by_store SET quantity = quantity - :qty
                 WHERE product_id = :pid AND location_id = :lid'
            );

            $touchedProducts = [];
            foreach ($allocations as $a) {
                $itemStmt->execute([
                    'order_id' => $orderId,
                    'product_id' => $a['product_id'],
                    'quantity' => $a['quantity'],
                    'unit_price' => $a['unit_price'],
                    'location_id' => $a['location_id'],
                ]);
                $stockStmt->execute([
                    'qty' => $a['quantity'],
                    'pid' => $a['product_id'],
                    'lid' => $a['location_id'],
                ]);
                $touchedProducts[$a['product_id']] = true;
            }

            foreach (array_keys($touchedProducts) as $pid) {
                recalcProductStock($pdo, (int)$pid);
            }

            $pdo->commit();
            unset($_SESSION['cart']);

            header('Location: checkout_success.php?order_id=' . $orderId);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Erro ao criar pedido: ' . $e->getMessage());
            $errors[] = 'Não foi possível concluir seu pedido. Tente novamente.';
        }
    }
}

// Recalcula o total exibido (nunca confiar em valor vindo do POST)
$ids = array_keys($cart);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
$stmt->execute($ids);
$products = $stmt->fetchAll();
$displayTotal = 0.0;
foreach ($products as $p) {
    $displayTotal += ($cart[$p['id']] ?? 0) * (float)$p['price'];
}

$pageTitle = 'Finalizar compra';
require __DIR__ . '/includes/site_head.php';
?>

<style>
  .checkout-wrap{ max-width:520px; margin:0 auto; }
  .summary-line{ font-size:15px; margin-bottom:24px; color:rgba(46,31,24,.75); }
  .checkout-card{ background:white; padding:32px; }
  label{ display:block; font-family:var(--sans); font-size:12px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; margin:18px 0 8px; }
  label:first-child{ margin-top:0; }
  input[type=text], input[type=email]{
    width:100%; padding:12px; border:1px solid var(--line); background:var(--cream); font-family:var(--sans); font-size:14px;
  }
  input:focus{ outline:1px solid var(--emerald-deep); }
  .error-box{ background:#f5dcd4; color:#6b2f1f; padding:12px 16px; margin-bottom:18px; font-size:14px; }
  .checkout-submit{ margin-top:24px; width:100%; justify-content:center; }

  .method-options{ display:flex; gap:12px; margin-top:6px; }
  .method-option{
    flex:1; border:1px solid var(--line); padding:14px; cursor:pointer; text-align:center;
    font-family:var(--sans); font-size:13px; font-weight:700;
  }
  .method-option input{ display:none; }
  .method-option.selected{ border-color:var(--emerald-deep); background:var(--cream2, #EFE6D1); }

  .address-fields{ display:none; }
  .address-fields.show{ display:block; }
  .address-row{ display:flex; gap:12px; }
  .address-row > div{ flex:1; }
</style>

<div class="checkout-wrap">
  <h1 class="page-title">Finalizar compra</h1>

  <?php foreach ($errors as $error): ?>
    <div class="error-box"><?= htmlspecialchars($error) ?></div>
  <?php endforeach; ?>

  <p class="summary-line">Total do pedido: <strong>R$ <?= number_format($displayTotal, 2, ',', '.') ?></strong></p>

  <form class="checkout-card" method="post" action="checkout.php" id="checkoutForm">
    <label for="customer_name">Nome completo</label>
    <input type="text" id="customer_name" name="customer_name" required
           value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>">

    <label for="customer_email">E-mail</label>
    <input type="email" id="customer_email" name="customer_email" required
           value="<?= htmlspecialchars($_POST['customer_email'] ?? '') ?>">

    <label for="customer_phone">Telefone (opcional)</label>
    <input type="text" id="customer_phone" name="customer_phone"
           value="<?= htmlspecialchars($_POST['customer_phone'] ?? '') ?>">

    <label>Como você quer receber?</label>
    <div class="method-options">
      <label class="method-option" id="opt-pickup">
        <input type="radio" name="fulfillment_method" value="pickup" required> Retirar na loja
      </label>
      <label class="method-option" id="opt-delivery">
        <input type="radio" name="fulfillment_method" value="delivery" required> Entrega
      </label>
    </div>
    <p style="font-size:12px; color:rgba(46,31,24,.6); margin-top:8px;">
      Se escolher retirada, o sistema indica automaticamente em qual loja (ou lojas) buscar cada item, com base no estoque disponível.
    </p>

    <div class="address-fields" id="addressFields">
      <label for="address_street">Rua</label>
      <input type="text" id="address_street" name="address_street" value="<?= htmlspecialchars($_POST['address_street'] ?? '') ?>">

      <div class="address-row">
        <div>
          <label for="address_number">Número</label>
          <input type="text" id="address_number" name="address_number" value="<?= htmlspecialchars($_POST['address_number'] ?? '') ?>">
        </div>
        <div>
          <label for="address_complement">Complemento</label>
          <input type="text" id="address_complement" name="address_complement" value="<?= htmlspecialchars($_POST['address_complement'] ?? '') ?>">
        </div>
      </div>

      <label for="address_neighborhood">Bairro</label>
      <input type="text" id="address_neighborhood" name="address_neighborhood" value="<?= htmlspecialchars($_POST['address_neighborhood'] ?? '') ?>">

      <div class="address-row">
        <div>
          <label for="address_city">Cidade</label>
          <input type="text" id="address_city" name="address_city" value="<?= htmlspecialchars($_POST['address_city'] ?? '') ?>">
        </div>
        <div style="max-width:80px;">
          <label for="address_state">UF</label>
          <input type="text" id="address_state" name="address_state" maxlength="2" value="<?= htmlspecialchars($_POST['address_state'] ?? '') ?>">
        </div>
      </div>

      <label for="address_zip">CEP</label>
      <input type="text" id="address_zip" name="address_zip" value="<?= htmlspecialchars($_POST['address_zip'] ?? '') ?>">
    </div>

    <button type="submit" class="btn solid checkout-submit">Confirmar pedido</button>
  </form>
</div>

<script>
  // Alterna a exibição dos campos de endereço conforme o método escolhido.
  // Puramente visual — a validação de verdade acontece no servidor.
  const radios = document.querySelectorAll('input[name="fulfillment_method"]');
  const addressFields = document.getElementById('addressFields');
  const optPickup = document.getElementById('opt-pickup');
  const optDelivery = document.getElementById('opt-delivery');

  function updateMethodUI() {
    const selected = document.querySelector('input[name="fulfillment_method"]:checked');
    addressFields.classList.toggle('show', selected && selected.value === 'delivery');
    optPickup.classList.toggle('selected', selected && selected.value === 'pickup');
    optDelivery.classList.toggle('selected', selected && selected.value === 'delivery');
  }
  radios.forEach(r => r.addEventListener('change', updateMethodUI));
  updateMethodUI();
</script>

<?php require __DIR__ . '/includes/site_foot.php'; ?>
