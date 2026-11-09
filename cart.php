<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/ui.php';

$pdo = getDbConnection();
$cart = getCart();

$couponError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['apply_coupon'])) {
        $code = trim($_POST['coupon_code'] ?? '');
        if ($code === '') {
            $couponError = 'Digite um código de cupom.';
        } else {
            // Validação real acontece depois de montar $variants (precisa dos itens do carrinho)
            $_SESSION['pending_coupon_check'] = $code;
        }
    } elseif (isset($_POST['remove_coupon'])) {
        unset($_SESSION['applied_coupon']);
    }
}

$items = [];
$total = 0.0;

if (!empty($cart)) {
    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT pv.id AS variant_id, pv.color_name, pv.color_hex, pv.size, p.id AS product_id, p.name, p.price, p.image_url
         FROM product_variants pv
         JOIN products p ON p.id = pv.product_id
         WHERE pv.id IN ($placeholders)"
    );
    $stmt->execute($ids);
    $variants = $stmt->fetchAll();

    foreach ($variants as $v) {
        $qty = $cart[$v['variant_id']];
        $subtotal = $qty * (float)$v['price'];
        $total += $subtotal;
        $items[] = ['variant' => $v, 'qty' => $qty, 'subtotal' => $subtotal];
    }
}

// Processa o cupom pendente (agora que já temos $variants montado)
if (isset($_SESSION['pending_coupon_check'])) {
    $code = $_SESSION['pending_coupon_check'];
    unset($_SESSION['pending_coupon_check']);
    $result = validateCoupon($pdo, $code, $variants ?? [], $cart);
    if ($result['valid']) {
        $_SESSION['applied_coupon'] = [
            'code' => $result['coupon_code'],
            'discount_percent' => $result['discount_percent'],
        ];
    } else {
        $couponError = $result['error'];
    }
}

$discountAmount = 0.0;
$appliedCoupon = $_SESSION['applied_coupon'] ?? null;
if ($appliedCoupon && !empty($cart)) {
    $result = validateCoupon($pdo, $appliedCoupon['code'], $variants ?? [], $cart);
    if ($result['valid']) {
        $discountAmount = $result['discount_amount'];
    } else {
        // Cupom deixou de valer (ex: item removido do carrinho, ou expirou) — remove silenciosamente
        unset($_SESSION['applied_coupon']);
        $appliedCoupon = null;
    }
}
$totalWithDiscount = $total - $discountAmount;

$pageTitle = 'Carrinho';
require __DIR__ . '/includes/site_head.php';
$itemCount = array_sum(array_column($items, 'qty'));
?>

<div class="crumbs" style="padding:20px 0 0;"><a href="index.php">Início</a><span>›</span><b>Carrinho</b></div>

<div class="page-head">
  <span class="eyebrow">MM'S Boutique</span>
  <h1>Seu carrinho</h1>
  <p><?= $itemCount > 0 ? $itemCount . ($itemCount === 1 ? ' item selecionado' : ' itens selecionados') : 'Revise suas escolhas antes de continuar para a finalização.' ?></p>
</div>
<hr>

<?php if (empty($items)): ?>
  <div class="empty-cart">
    <div class="ico"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></div>
    <h2>Seu carrinho está vazio</h2>
    <p>Escolha suas peças favoritas e elas aparecerão aqui.</p>
    <a href="catalog.php" class="cta" style="display:inline-block;width:auto;padding:14px 32px;margin:0;">Continuar comprando</a>
  </div>
<?php else: ?>
  <div class="cart-layout">
    <div>
      <div class="cart-head"><span></span><span>Produto</span><span style="text-align:center">Qtd</span><span style="text-align:right">Preço</span><span></span></div>

      <?php foreach ($items as $item): $v = $item['variant']; ?>
        <div class="cart-row">
          <div class="thumb"><img src="<?= mmsH(mmsProductImage($v)) ?>" alt=""></div>
          <div>
            <h3><?= mmsH($v['name']) ?></h3>
            <div class="meta">
              <?php if ($v['color_name']): ?><span>Cor: <strong><?= mmsH($v['color_name']) ?></strong></span><?php endif; ?>
              <?php if ($v['size']): ?><span>Tam: <strong><?= mmsH($v['size']) ?></strong></span><?php endif; ?>
            </div>
            <div class="unit"><?= mmsMoney((float)$v['price']) ?></div>
          </div>
          <form method="post" action="cart_update.php" class="qty">
            <input type="hidden" name="variant_id" value="<?= (int)$v['variant_id'] ?>">
            <button type="submit" name="delta" value="-1" aria-label="Diminuir">−</button>
            <span><?= (int)$item['qty'] ?></span>
            <button type="submit" name="delta" value="1" aria-label="Aumentar">+</button>
          </form>
          <span class="line-total"><?= mmsMoney($item['subtotal']) ?></span>
          <a class="rm" href="cart_remove.php?variant_id=<?= (int)$v['variant_id'] ?>" aria-label="Remover">×</a>
        </div>
      <?php endforeach; ?>

      <a href="catalog.php" class="back-link">← Continuar comprando</a>
    </div>

    <aside class="summary">
      <h2>Resumo do pedido</h2>
      <div class="sum-lines">
        <div class="sum-line"><span class="k">Subtotal (<?= $itemCount ?> <?= $itemCount === 1 ? 'item' : 'itens' ?>)</span><span><?= mmsMoney($total) ?></span></div>
        <?php if ($discountAmount > 0): ?>
          <div class="sum-line disc"><span>Cupom <?= mmsH($appliedCoupon['code']) ?> (-<?= (int)$appliedCoupon['discount_percent'] ?>%)</span><span>- <?= mmsMoney($discountAmount) ?></span></div>
        <?php endif; ?>
        <div class="sum-line"><span class="k">Frete</span><span style="color:rgba(46,31,24,.6)">calculado na finalização</span></div>
        <div class="sum-line total"><span>Total</span><span class="v"><?= mmsMoney($totalWithDiscount) ?></span></div>
        <?php $inst = mmsInstallmentText($totalWithDiscount); if ($inst): ?><div class="sum-note"><?= mmsH($inst) ?></div><?php endif; ?>
      </div>

      <div class="coupon-box">
        <label>Cupom de desconto</label>
        <?php if ($appliedCoupon): ?>
          <form method="post" action="cart.php" class="coupon-on">
            <span>Cupom aplicado: <strong><?= mmsH($appliedCoupon['code']) ?></strong></span>
            <button type="submit" name="remove_coupon" value="1">Remover</button>
          </form>
        <?php else: ?>
          <form method="post" action="cart.php" class="coupon-form">
            <input type="text" name="coupon_code" placeholder="Insira seu cupom">
            <button type="submit" name="apply_coupon" value="1">OK</button>
          </form>
        <?php endif; ?>
        <?php if ($couponError): ?><p class="err"><?= mmsH($couponError) ?></p><?php endif; ?>
      </div>

      <a href="checkout.php" class="cta">Finalizar compra →</a>
      <div class="trust"><span>🔒 Pagamento seguro</span><span>🚚 Entrega em Natal e região</span><span>🏬 Retirada na loja</span></div>
    </aside>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/site_foot.php'; ?>
