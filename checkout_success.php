<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/gateway.php';

$pdo = getDbConnection();
$orderId = (int)($_GET['order_id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
$stmt->execute(['id' => $orderId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: catalog.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT oi.*, p.name AS product_name, sl.name AS location_name
     FROM order_items oi
     JOIN products p ON p.id = oi.product_id
     LEFT JOIN store_locations sl ON sl.id = oi.fulfillment_location_id
     WHERE oi.order_id = :id'
);
$stmt->execute(['id' => $orderId]);
$items = $stmt->fetchAll();

$byLocation = [];
foreach ($items as $item) {
    $loc = $item['location_name'] ?? 'A definir';
    $byLocation[$loc][] = $item;
}

$settings = $pdo->query('SELECT * FROM store_settings WHERE id = 1')->fetch();
$gateway = $settings['gateway_provider'] ?? null;

// Voltando do Mercado Pago: confirma o pagamento direto na API, sem
// depender só do webhook. Só aceita se o pagamento for deste pedido.
$paymentId = $_GET['payment_id'] ?? ($_GET['collection_id'] ?? '');
if ($paymentId !== '' && $paymentId !== 'null' && $gateway === 'mercadopago'
    && !empty($settings['gateway_access_token']) && $order['status'] !== 'paid') {
    try {
        $payment = mp_get_payment((string)$paymentId, $settings['gateway_access_token']);
        if ((string)($payment['external_reference'] ?? '') === (string)$order['id']) {
            $newStatus = mp_map_status($payment['status'] ?? '');
            if ($newStatus !== $order['status']) {
                $pdo->prepare('UPDATE orders SET status = :s WHERE id = :id')->execute(['s' => $newStatus, 'id' => $order['id']]);
                $order['status'] = $newStatus;
            }
        }
    } catch (Throwable $e) {
        error_log('checkout_success: falha ao confirmar pagamento ' . $paymentId . ': ' . $e->getMessage());
    }
}
$returnedFromGateway = $paymentId !== '' || isset($_GET['collection_status']) || isset($_GET['status']);

function itemLabel(array $item): string
{
    $label = $item['quantity'] . 'x ' . $item['product_name'];
    $variant = trim(($item['variant_color'] ?? '') . ' ' . ($item['variant_size'] ?? ''));
    return $variant !== '' ? $label . ' (' . $variant . ')' : $label;
}

$pageTitle = 'Pedido confirmado';
require __DIR__ . '/includes/site_head.php';
?>

<style>
  .confirm-wrap{ max-width:560px; margin:40px auto 0; }
  .confirm-card{ background:white; padding:40px 32px; }
  .check-mark{
    width:56px; height:56px; border-radius:50%; background:var(--emerald-deep); color:var(--cream);
    display:flex; align-items:center; justify-content:center; font-size:28px; margin:0 auto 20px;
  }
  .confirm-card h1{ font-family:var(--serif); font-size:26px; margin-bottom:6px; text-align:center; }
  .confirm-card .total-line{ font-size:15px; margin-top:10px; text-align:center; }
  .confirm-card .status-line{ font-size:14px; margin-top:4px; color:rgba(46,31,24,.7); text-align:center; }

  .fulfillment-block{ margin-top:28px; }
  .item-line{ display:flex; justify-content:space-between; font-size:13px; padding:6px 0; border-bottom:1px solid var(--line); }
  .location-group{ margin-bottom:20px; background:var(--cream); padding:14px 16px; }
  .location-group h3{ font-family:var(--serif); font-size:16px; margin-bottom:10px; color:var(--emerald-deep); }

  .address-box{ margin-top:24px; background:var(--cream); padding:16px 18px; font-size:13px; line-height:1.6; }

  .note-box{
    text-align:left; font-size:13px; background:var(--cream-2); padding:16px 18px; margin-top:24px;
    color:rgba(46,31,24,.8); border-left:3px solid var(--gold);
  }
  .confirm-card .btn{ margin-top:26px; width:100%; justify-content:center; }
</style>

<div class="confirm-wrap">
  <div class="confirm-card">
    <div class="check-mark">✓</div>
    <h1>Pedido #<?= (int)$order['id'] ?> recebido!</h1>
    <p class="total-line">Total: <strong>R$ <?= number_format((float)$order['total_amount'], 2, ',', '.') ?></strong></p>
    <?php if ($order['discount_amount'] > 0): ?>
      <p class="status-line">Cupom <?= htmlspecialchars($order['coupon_code']) ?>: - R$ <?= number_format((float)$order['discount_amount'], 2, ',', '.') ?></p>
    <?php endif; ?>
    <?php if ($order['shipping_cost'] > 0): ?>
      <p class="status-line">Frete (<?= htmlspecialchars($order['shipping_service']) ?>): R$ <?= number_format((float)$order['shipping_cost'], 2, ',', '.') ?></p>
    <?php endif; ?>
    <p class="status-line">Status: <?= ['paid' => 'Pago', 'failed' => 'Pagamento não aprovado', 'refunded' => 'Estornado'][$order['status']] ?? 'Pendente de pagamento' ?></p>

    <?php if ($order['fulfillment_method'] === 'pickup'): ?>
      <div class="fulfillment-block">
        <?php if (count($byLocation) > 1): ?>
          <p style="font-size:13px; color:rgba(46,31,24,.7); margin-bottom:16px;">
            Seu pedido foi dividido entre duas unidades — retire cada parte no local indicado.
          </p>
        <?php endif; ?>

        <?php foreach ($byLocation as $locationName => $locItems): ?>
          <div class="location-group">
            <h3>Retirar em: <?= htmlspecialchars($locationName) ?></h3>
            <?php foreach ($locItems as $item): ?>
              <div class="item-line">
                <span><?= htmlspecialchars(itemLabel($item)) ?></span>
                <span>R$ <?= number_format($item['quantity'] * (float)$item['unit_price'], 2, ',', '.') ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="fulfillment-block">
        <?php foreach ($items as $item): ?>
          <div class="item-line">
            <span><?= htmlspecialchars(itemLabel($item)) ?></span>
            <span>R$ <?= number_format($item['quantity'] * (float)$item['unit_price'], 2, ',', '.') ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="address-box">
        <strong>Endereço de entrega</strong><br>
        <?= htmlspecialchars($order['address_street']) ?>, <?= htmlspecialchars($order['address_number']) ?>
        <?= $order['address_complement'] ? '— ' . htmlspecialchars($order['address_complement']) : '' ?><br>
        <?= htmlspecialchars($order['address_neighborhood']) ?> — <?= htmlspecialchars($order['address_city']) ?>/<?= htmlspecialchars($order['address_state']) ?><br>
        CEP <?= htmlspecialchars($order['address_zip']) ?>
      </div>
    <?php endif; ?>

    <?php if ($order['status'] === 'paid'): ?>
      <div class="note-box">
        <strong>Pagamento aprovado!</strong> Já recebemos o seu pedido e vamos separar as peças.
        Avisamos pelo WhatsApp quando estiver pronto.
      </div>
    <?php elseif ($order['status'] === 'failed'): ?>
      <div class="note-box">
        O pagamento não foi aprovado. Você pode tentar de novo com outra forma de pagamento,
        ou falar com a gente pelo <a href="https://wa.me/5584999001179" target="_blank" rel="noopener">WhatsApp</a> informando o pedido #<?= (int)$order['id'] ?>.
      </div>
    <?php elseif ($gateway && ($returnedFromGateway || !empty($order['gateway_charge_id']))): ?>
      <div class="note-box">
        Estamos aguardando a confirmação do pagamento pelo
        <strong><?= $gateway === 'mercadopago' ? 'Mercado Pago' : 'PagSeguro' ?></strong>.
        Se você pagou com PIX ou boleto, a confirmação pode levar alguns minutos.
      </div>
    <?php elseif ($gateway): ?>
      <div class="note-box">
        Não conseguimos abrir a página de pagamento agora. O seu pedido #<?= (int)$order['id'] ?> ficou registrado:
        fale com a gente pelo <a href="https://wa.me/5584999001179" target="_blank" rel="noopener">WhatsApp</a> para concluir a compra.
      </div>
    <?php else: ?>
      <div class="note-box">
        Nenhum gateway de pagamento foi configurado ainda nas Configurações do admin.
        O pedido ficou registrado como pendente até isso ser feito.
      </div>
    <?php endif; ?>

    <a href="catalog.php" class="btn solid">Voltar à loja</a>
  </div>
</div>

<?php require __DIR__ . '/includes/site_foot.php'; ?>
