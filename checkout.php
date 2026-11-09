<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/gateway.php';

$pdo = getDbConnection();
$cart = getCart();

if (empty($cart)) {
    header('Location: cart.php');
    exit;
}

$locations = getActiveLocations($pdo);
$errors = [];

// Busca os itens do carrinho pra exibir a revisão (sempre recalculado do banco,
// nunca confiar em preço/nome vindo do POST)
$ids = array_keys($cart);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare(
    "SELECT pv.id AS variant_id, pv.color_name, pv.size, p.id AS product_id, p.name, p.price, p.image_url
     FROM product_variants pv
     JOIN products p ON p.id = pv.product_id
     WHERE pv.id IN ($placeholders)"
);
$stmt->execute($ids);
$cartVariants = $stmt->fetchAll();

$reviewItems = [];
$displayTotal = 0.0;
foreach ($cartVariants as $v) {
    $qty = $cart[$v['variant_id']] ?? 0;
    $subtotal = $qty * (float)$v['price'];
    $displayTotal += $subtotal;
    $reviewItems[] = ['variant' => $v, 'qty' => $qty, 'subtotal' => $subtotal];
}

// Reaplica o cupom da sessão (se houver) pra mostrar e cobrar o valor certo
$appliedCoupon = $_SESSION['applied_coupon'] ?? null;
$discountAmount = 0.0;
if ($appliedCoupon) {
    $couponResult = validateCoupon($pdo, $appliedCoupon['code'], $cartVariants, $cart);
    if ($couponResult['valid']) {
        $discountAmount = $couponResult['discount_amount'];
    } else {
        unset($_SESSION['applied_coupon']);
        $appliedCoupon = null;
    }
}
$displayTotalWithDiscount = $displayTotal - $discountAmount;

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

    if ($name === '') $errors[] = 'Informe seu nome.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Informe um e-mail válido.';
    if (!in_array($fulfillmentMethod, ['pickup', 'delivery'], true)) $errors[] = 'Escolha retirar na loja ou receber em casa.';

    $shippingCost = 0.0;
    $shippingService = null;
    if ($fulfillmentMethod === 'delivery') {
        if ($address['street'] === '' || $address['number'] === '' || $address['city'] === '' || $address['zip'] === '') {
            $errors[] = 'Preencha o endereço completo para entrega.';
        }
        // Vem preenchido pelo JS depois que o cliente escolhe uma opção de frete cotada
        $shippingCost = (float)($_POST['shipping_cost'] ?? 0);
        $shippingService = trim($_POST['shipping_service'] ?? '') ?: null;
        if ($shippingCost <= 0 || !$shippingService) {
            $errors[] = 'Escolha uma opção de frete antes de confirmar.';
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
        $subtotal = 0.0;
        foreach ($allocations as $a) $subtotal += $a['quantity'] * $a['unit_price'];
        $total = $subtotal - $discountAmount + $shippingCost;

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO orders (
                    customer_name, customer_email, customer_phone, total_amount,
                    coupon_code, discount_amount, shipping_cost, shipping_service, status,
                    fulfillment_method, address_street, address_number, address_complement,
                    address_neighborhood, address_city, address_state, address_zip
                ) VALUES (
                    :name, :email, :phone, :total,
                    :coupon_code, :discount_amount, :shipping_cost, :shipping_service, :status,
                    :method, :street, :number, :complement,
                    :neighborhood, :city, :state, :zip
                )'
            );
            $stmt->execute([
                'name' => $name, 'email' => $email, 'phone' => $phone,
                'total' => $total,
                'coupon_code' => $appliedCoupon['code'] ?? null,
                'discount_amount' => $discountAmount,
                'shipping_cost' => $shippingCost,
                'shipping_service' => $shippingService,
                'status' => 'pending',
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
                'INSERT INTO order_items (order_id, product_id, quantity, unit_price, variant_id, variant_color, variant_size, fulfillment_location_id)
                 VALUES (:order_id, :product_id, :quantity, :unit_price, :variant_id, :variant_color, :variant_size, :location_id)'
            );
            $stockStmt = $pdo->prepare(
                'UPDATE stock_by_store SET quantity = quantity - :qty
                 WHERE variant_id = :vid AND location_id = :lid'
            );

            $touchedProducts = [];
            foreach ($allocations as $a) {
                $itemStmt->execute([
                    'order_id' => $orderId,
                    'product_id' => $a['product_id'],
                    'quantity' => $a['quantity'],
                    'unit_price' => $a['unit_price'],
                    'variant_id' => $a['variant_id'],
                    'variant_color' => $a['color'],
                    'variant_size' => $a['size'],
                    'location_id' => $a['location_id'],
                ]);
                $stockStmt->execute([
                    'qty' => $a['quantity'],
                    'vid' => $a['variant_id'],
                    'lid' => $a['location_id'],
                ]);
                $touchedProducts[$a['product_id']] = true;
            }

            foreach (array_keys($touchedProducts) as $pid) {
                recalcProductStock($pdo, (int)$pid);
            }

            $pdo->commit();
            unset($_SESSION['cart']);
            unset($_SESSION['applied_coupon']);

            // Tenta redirecionar pro checkout de verdade do gateway configurado.
            // Se não houver gateway configurado, ou a chamada falhar (rede, credencial
            // inválida, etc.), cai de volta pra tela de confirmação com o pedido
            // registrado como pendente — nunca deixa o cliente sem retorno nenhum.
            $settings = $pdo->query('SELECT * FROM store_settings WHERE id = 1')->fetch();
            if ($settings && $settings['gateway_provider'] && $settings['gateway_access_token']) {
                try {
                    $chargeItems = array_map(fn($a) => [
                        'name' => $a['name'],
                        'quantity' => $a['quantity'],
                        'unit_price' => $a['unit_price'],
                    ], $allocations);

                    // Desconto e frete entram como linhas próprias, pra o total
                    // cobrado no gateway bater exatamente com o total_amount salvo.
                    if ($discountAmount > 0) {
                        $chargeItems[] = ['name' => 'Desconto (' . $appliedCoupon['code'] . ')', 'quantity' => 1, 'unit_price' => -$discountAmount];
                    }
                    if ($shippingCost > 0) {
                        $chargeItems[] = ['name' => 'Frete (' . $shippingService . ')', 'quantity' => 1, 'unit_price' => $shippingCost];
                    }

                    $chargeOrder = [
                        'id' => $orderId,
                        'customer_email' => $email,
                        'base_url' => (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'],
                        'items' => $chargeItems,
                    ];
                    $charge = gatewayCreateCharge($chargeOrder, $settings);

                    if (!empty($charge['checkout_url'])) {
                        $stmt = $pdo->prepare('UPDATE orders SET gateway_charge_id = :cid WHERE id = :id');
                        $stmt->execute(['cid' => $charge['charge_id'], 'id' => $orderId]);
                        header('Location: ' . $charge['checkout_url']);
                        exit;
                    }
                } catch (Throwable $e) {
                    error_log('Falha ao criar cobrança no gateway: ' . $e->getMessage());
                    // segue pro fallback abaixo — o cliente nunca fica sem retorno
                }
            }

            header('Location: checkout_success.php?order_id=' . $orderId);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Erro ao criar pedido: ' . $e->getMessage());
            $errors[] = 'Não foi possível concluir seu pedido. Tente novamente.';
        }
    }
}

$pageTitle = 'Finalizar compra';
require __DIR__ . '/includes/site_head.php';
?>

<style>
  .checkout-grid{ display:grid; grid-template-columns:1fr 380px; gap:48px; align-items:start; }
  @media (max-width:860px){ .checkout-grid{ grid-template-columns:1fr; } }

  .checkout-card label{ display:block; font-family:var(--sans); font-size:12px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; margin:18px 0 8px; }
  .checkout-card label:first-child{ margin-top:0; }
  .checkout-card input[type=text], .checkout-card input[type=email]{
    width:100%; padding:12px; border:1px solid var(--line); background:var(--cream); font-family:var(--sans); font-size:14px;
  }
  .checkout-card input:focus{ outline:1px solid var(--emerald-deep); }
  .error-box{ background:#f5dcd4; color:#6b2f1f; padding:12px 16px; margin-bottom:18px; font-size:14px; }
  .checkout-card{ background:white; padding:32px; border-top:3px solid var(--gold); }
  .checkout-submit{ margin-top:24px; width:100%; justify-content:center; }

  .method-options{ display:flex; gap:12px; margin-top:6px; }
  .method-option{
    flex:1; border:1px solid var(--line); padding:14px; cursor:pointer; text-align:center;
    font-family:var(--sans); font-size:13px; font-weight:700;
  }
  .checkout-card .method-option{ margin:0; }
  .method-option input{ display:none; }
  .address-fields{ margin-top:8px; }
  .method-option.selected{ border-color:var(--emerald-deep); background:var(--cream-2); }

  .address-fields{ display:none; }
  .address-fields.show{ display:block; }
  .address-row{ display:flex; gap:12px; }
  .address-row > div{ flex:1; }

  .shipping-group{ margin-bottom:14px; }
  .shipping-group h4{ font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; margin-bottom:8px; color:rgba(46,31,24,.6); }
  .shipping-option{
    display:flex; justify-content:space-between; align-items:center; gap:10px;
    border:1px solid var(--line); padding:10px 12px; margin-bottom:6px; cursor:pointer; font-size:13px;
  }
  .shipping-option.selected{ border-color:var(--emerald-deep); background:var(--cream-2); }
  .shipping-option input{ width:auto; margin-right:8px; }
  .shipping-loading{ font-size:12px; color:rgba(46,31,24,.5); }
  .shipping-error{ font-size:12px; color:var(--rose-deep); }

  /* Resumo do pedido */
  .summary-box{ background:var(--cream-2); padding:28px; position:sticky; top:100px; }
  .summary-box h2{ font-family:var(--serif); font-size:22px; font-weight:600; margin-bottom:20px; }
  .summary-item{ display:flex; gap:12px; align-items:center; margin-bottom:14px; }
  .summary-item img{ width:54px; height:68px; object-fit:cover; background:var(--cream); flex-shrink:0; }
  .summary-item .info{ flex:1; }
  .summary-item .name{ font-size:13px; font-weight:600; line-height:1.3; }
  .summary-item .variant{ font-size:11.5px; color:rgba(46,31,24,.5); margin-top:3px; }
  .summary-item .price{ font-size:13px; font-weight:700; }
  .summary-total{ display:flex; justify-content:space-between; font-size:18px; font-weight:700; margin-top:16px; padding-top:16px; border-top:1px solid var(--line); }
  .summary-total span:last-child{ color:var(--rose-deep); }
</style>

<div class="crumbs" style="padding:20px 0 0;"><a href="index.php">Início</a><span>›</span><a href="cart.php">Carrinho</a><span>›</span><b>Finalizar compra</b></div>
<div class="page-head">
  <span class="eyebrow">MM'S Boutique</span>
  <h1>Finalizar compra</h1>
  <p>Confira seus dados. O pagamento é feito no ambiente seguro do Mercado Pago/PagSeguro.</p>
</div>
<hr>

<?php foreach ($errors as $error): ?>
  <div class="error-box"><?= htmlspecialchars($error) ?></div>
<?php endforeach; ?>

<div class="checkout-grid">
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
      <input type="text" id="address_zip" name="address_zip" value="<?= htmlspecialchars($_POST['address_zip'] ?? '') ?>" placeholder="00000-000">

      <div id="shippingOptionsBox" style="margin-top:14px;"></div>
      <input type="hidden" id="shipping_cost" name="shipping_cost" value="">
      <input type="hidden" id="shipping_service" name="shipping_service" value="">
    </div>

    <button type="submit" class="cta checkout-submit">Confirmar pedido</button>
    <p style="font-size:11.5px; color:rgba(46,31,24,.55); text-align:center; margin-top:10px;">
      Ao confirmar, você será direcionado para o pagamento seguro do Mercado Pago/PagSeguro.
    </p>
  </form>

  <div class="summary-box">
    <h2>Resumo do pedido</h2>
    <?php foreach ($reviewItems as $item): $v = $item['variant']; ?>
      <div class="summary-item">
        <img src="<?= htmlspecialchars($v['image_url'] ?: 'https://placehold.co/100x120/EFE6D1/2E1F18?text=MM%27S') ?>" alt="">
        <div class="info">
          <div class="name"><?= (int)$item['qty'] ?>x <?= htmlspecialchars($v['name']) ?></div>
          <?php if ($v['color_name'] || $v['size']): ?>
            <div class="variant"><?= htmlspecialchars($v['color_name'] ?: '') ?><?= ($v['color_name'] && $v['size']) ? ' · ' : '' ?><?= htmlspecialchars($v['size'] ?: '') ?></div>
          <?php endif; ?>
        </div>
        <div class="price">R$ <?= number_format($item['subtotal'], 2, ',', '.') ?></div>
      </div>
    <?php endforeach; ?>
    <?php if ($discountAmount > 0): ?>
      <div class="summary-item" style="color:var(--emerald-deep);">
        <div class="info"><div class="name">Cupom <?= htmlspecialchars($appliedCoupon['code']) ?></div></div>
        <div class="price">- R$ <?= number_format($discountAmount, 2, ',', '.') ?></div>
      </div>
    <?php endif; ?>
    <div class="summary-item" id="shippingSummaryRow" style="display:none;">
      <div class="info"><div class="name" id="shippingSummaryLabel">Frete</div></div>
      <div class="price" id="shippingSummaryPrice">R$ 0,00</div>
    </div>
    <div class="summary-total"><span>Total</span><span id="summaryTotal">R$ <?= number_format($displayTotalWithDiscount, 2, ',', '.') ?></span></div>
  </div>
</div>

<script>
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

  // ---- Cotação de frete ----
  const zipInput = document.getElementById('address_zip');
  const shippingBox = document.getElementById('shippingOptionsBox');
  const shippingCostInput = document.getElementById('shipping_cost');
  const shippingServiceInput = document.getElementById('shipping_service');
  const summaryTotal = document.getElementById('summaryTotal');
  const shippingSummaryRow = document.getElementById('shippingSummaryRow');
  const shippingSummaryLabel = document.getElementById('shippingSummaryLabel');
  const shippingSummaryPrice = document.getElementById('shippingSummaryPrice');

  const baseTotal = <?= json_encode($displayTotalWithDiscount) ?>; // já com desconto aplicado, sem frete
  const selections = {}; // { groupIndex: { price, serviceLabel } }

  function formatBRL(value) {
    return 'R$ ' + value.toFixed(2).replace('.', ',');
  }

  function recomputeShipping() {
    const groups = Object.values(selections);
    if (groups.length === 0) {
      shippingCostInput.value = '';
      shippingServiceInput.value = '';
      shippingSummaryRow.style.display = 'none';
      summaryTotal.textContent = formatBRL(baseTotal);
      return;
    }
    const totalShipping = groups.reduce((sum, g) => sum + g.price, 0);
    const serviceLabel = groups.map(g => g.serviceLabel).join(' + ');
    shippingCostInput.value = totalShipping.toFixed(2);
    shippingServiceInput.value = serviceLabel;
    shippingSummaryRow.style.display = 'flex';
    shippingSummaryLabel.textContent = 'Frete (' + serviceLabel + ')';
    shippingSummaryPrice.textContent = formatBRL(totalShipping);
    summaryTotal.textContent = formatBRL(baseTotal + totalShipping);
  }

  function renderShippingOptions(data) {
    shippingBox.innerHTML = '';
    for (const key in selections) delete selections[key];

    if (data.error) {
      shippingBox.innerHTML = '<p class="shipping-error">' + data.error + '</p>';
      recomputeShipping();
      return;
    }

    data.groups.forEach((group, idx) => {
      const wrap = document.createElement('div');
      wrap.className = 'shipping-group';

      const title = document.createElement('h4');
      title.textContent = data.groups.length > 1 ? 'Saindo de: ' + group.location_name : 'Opções de entrega';
      wrap.appendChild(title);

      if (group.error) {
        const err = document.createElement('p');
        err.className = 'shipping-error';
        err.textContent = group.error;
        wrap.appendChild(err);
      } else {
        group.options.forEach((opt, optIdx) => {
          const label = document.createElement('label');
          label.className = 'shipping-option';
          const inputId = 'ship_' + idx + '_' + optIdx;
          label.innerHTML = '<span><input type="radio" name="shipping_group_' + idx + '" id="' + inputId + '"> ' +
            opt.service_name + (opt.company_name ? ' — ' + opt.company_name : '') +
            (opt.delivery_time ? ' (' + opt.delivery_time + ' dias úteis)' : '') + '</span>' +
            '<strong>' + formatBRL(opt.price) + '</strong>';

          label.querySelector('input').addEventListener('change', () => {
            wrap.querySelectorAll('.shipping-option').forEach(el => el.classList.remove('selected'));
            label.classList.add('selected');
            const groupLabel = data.groups.length > 1 ? opt.service_name + ' (' + group.location_name + ')' : opt.service_name;
            selections[idx] = { price: opt.price, serviceLabel: groupLabel };
            recomputeShipping();
          });

          wrap.appendChild(label);
        });
      }

      shippingBox.appendChild(wrap);
    });

    recomputeShipping();
  }

  function fetchShippingOptions() {
    const cep = zipInput.value.replace(/\D/g, '');
    const selectedMethod = document.querySelector('input[name="fulfillment_method"]:checked');
    if (!selectedMethod || selectedMethod.value !== 'delivery' || cep.length !== 8) return;

    shippingBox.innerHTML = '<p class="shipping-loading">Calculando frete...</p>';
    fetch('shipping_quote.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'cep=' + encodeURIComponent(cep),
    })
      .then(r => r.json())
      .then(renderShippingOptions)
      .catch(() => {
        shippingBox.innerHTML = '<p class="shipping-error">Não foi possível calcular o frete agora. Tente novamente.</p>';
      });
  }

  zipInput.addEventListener('blur', fetchShippingOptions);
</script>

<?php require __DIR__ . '/includes/site_foot.php'; ?>
