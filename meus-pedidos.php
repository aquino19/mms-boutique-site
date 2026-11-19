<?php
/**
 * "Meus pedidos" sem senha: a cliente informa o e-mail usado na compra e
 * recebe um link de acesso (válido por 24 horas) que lista os pedidos
 * daquele e-mail. Só o hash do token fica no banco.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/ui.php';
require_once __DIR__ . '/includes/mailer.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const MMS_LINK_HOURS = 24;

$pdo = getDbConnection();
$message = null;
$error = null;
$email = null;
$orders = [];

// ---- Acesso pelo link ----
$token = (string)($_GET['t'] ?? '');
if ($token !== '') {
    if (preg_match('/^[a-f0-9]{64}$/', $token)) {
        $stmt = $pdo->prepare('SELECT email FROM customer_access_tokens WHERE token_hash = :h AND expires_at > NOW()');
        $stmt->execute(['h' => hash('sha256', $token)]);
        $email = $stmt->fetchColumn() ?: null;
    }
    if (!$email) {
        $error = 'Este link expirou ou não é válido. Peça um novo abaixo.';
    } else {
        $_SESSION['mms_orders_email'] = $email;
        $_SESSION['mms_orders_until'] = time() + 2 * 3600;
        header('Location: meus-pedidos.php'); // tira o token da barra de endereço
        exit;
    }
}
if (!$email && !empty($_SESSION['mms_orders_email']) && ($_SESSION['mms_orders_until'] ?? 0) > time()) {
    $email = $_SESSION['mms_orders_email'];
}
if (isset($_GET['sair'])) {
    unset($_SESSION['mms_orders_email'], $_SESSION['mms_orders_until']);
    header('Location: meus-pedidos.php');
    exit;
}

// ---- Pedido do link ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asked = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    if (!filter_var($asked, FILTER_VALIDATE_EMAIL)) {
        $error = 'Digite um e-mail válido.';
    } else {
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        // limites: 1 link a cada 2 min por e-mail e 10 por hora por IP
        $recentEmail = $pdo->prepare('SELECT COUNT(*) FROM customer_access_tokens WHERE email = :e AND created_at > NOW() - INTERVAL 2 MINUTE');
        $recentEmail->execute(['e' => $asked]);
        $recentIp = $pdo->prepare('SELECT COUNT(*) FROM customer_access_tokens WHERE ip = :ip AND created_at > NOW() - INTERVAL 1 HOUR');
        $recentIp->execute(['ip' => $ip]);

        if ((int)$recentEmail->fetchColumn() === 0 && (int)$recentIp->fetchColumn() < 10) {
            $has = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE LOWER(customer_email) = :e');
            $has->execute(['e' => $asked]);
            if ((int)$has->fetchColumn() > 0) {
                $raw = bin2hex(random_bytes(32));
                $pdo->prepare(
                    'INSERT INTO customer_access_tokens (email, token_hash, created_at, expires_at, ip)
                     VALUES (:e, :h, NOW(), NOW() + INTERVAL ' . (int)MMS_LINK_HOURS . ' HOUR, :ip)'
                )->execute(['e' => $asked, 'h' => hash('sha256', $raw), 'ip' => $ip]);
                $link = mmsSiteUrl() . '/meus-pedidos.php?t=' . $raw;
                mmsSendMail($pdo, $asked, "MM'S Boutique — acesse seus pedidos", mmsMailLayout('Seus pedidos na MM\'S',
                    '<p style="font-size:15px;line-height:1.6;">Clique no botão abaixo para ver os seus pedidos. O link vale por ' . MMS_LINK_HOURS . ' horas.</p>'
                    . '<p style="margin:22px 0;"><a href="' . htmlspecialchars($link) . '" style="background:#0E5C33;color:#F7F2E6;padding:12px 22px;text-decoration:none;font-weight:bold;font-size:13px;">VER MEUS PEDIDOS</a></p>'
                    . '<p style="font-size:12px;color:#888;">Se não foi você que pediu, pode ignorar este e-mail.</p>'));
            }
            // limpeza de links vencidos
            $pdo->exec('DELETE FROM customer_access_tokens WHERE expires_at < NOW() - INTERVAL 7 DAY');
        }
        // mesma resposta sempre, para não revelar quem comprou
        $message = 'Se houver pedidos com o e-mail ' . $asked . ', enviamos um link de acesso para ele. Confira a caixa de entrada e o spam.';
    }
}

// ---- Lista de pedidos ----
if ($email) {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE LOWER(customer_email) = :e ORDER BY created_at DESC LIMIT 50');
    $stmt->execute(['e' => mb_strtolower($email)]);
    $orders = $stmt->fetchAll();
    $itemsStmt = $pdo->prepare(
        'SELECT oi.quantity, oi.unit_price, oi.variant_color, oi.variant_size, p.name, p.image_url
         FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = :id'
    );
    foreach ($orders as &$o) {
        $itemsStmt->execute(['id' => $o['id']]);
        $o['items'] = $itemsStmt->fetchAll();
    }
    unset($o);
}

/** Situação para a cliente, juntando pagamento e andamento. */
function mmsCustomerStatus(array $o): array
{
    if ($o['status'] === 'pending') return ['Aguardando pagamento', 'wait'];
    if ($o['status'] === 'failed') return ['Pagamento não aprovado', 'bad'];
    if ($o['status'] === 'refunded') return ['Estornado', 'bad'];
    $pickup = $o['fulfillment_method'] === 'pickup';
    switch ($o['fulfillment_status'] ?? 'novo') {
        case 'separado': return [$pickup ? 'Pronto para retirada' : 'Separado', 'ok'];
        case 'enviado': return ['Enviado', 'ok'];
        case 'entregue': return [$pickup ? 'Retirado' : 'Entregue', 'done'];
        default: return ['Pago · em separação', 'ok'];
    }
}

$pageTitle = 'Meus pedidos';
require __DIR__ . '/includes/site_head.php';
?>
<style>
  .mp-wrap{ max-width:760px; margin:0 auto; padding:0 0 80px; }
  .mp-card{ background:#fff; border-top:3px solid var(--gold); padding:28px; margin-bottom:22px; }
  .mp-card label{ display:block; font-size:12px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; margin-bottom:8px; }
  .mp-card input[type=email]{ width:100%; padding:12px; border:1px solid var(--line); background:var(--cream); font-size:14px; font-family:var(--sans); }
  .mp-note{ padding:12px 16px; margin-bottom:18px; font-size:14px; background:var(--cream-2); border-left:3px solid var(--emerald-deep); }
  .mp-note.err{ background:#f5dcd4; border-left-color:var(--rose-deep); }
  .mp-order{ background:#fff; padding:20px 22px; margin-bottom:16px; border:1px solid var(--line); }
  .mp-head{ display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:baseline; margin-bottom:12px; }
  .mp-head h3{ font-family:var(--serif); font-size:20px; margin:0; }
  .mp-badge{ font-size:11px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; padding:5px 10px; }
  .mp-badge.wait{ background:#efe6d1; color:#7a5c00; } .mp-badge.ok{ background:#e6f2ea; color:var(--emerald-deep); }
  .mp-badge.done{ background:var(--emerald-deep); color:var(--cream); } .mp-badge.bad{ background:#f5dcd4; color:var(--rose-deep); }
  .mp-item{ display:flex; gap:12px; align-items:center; padding:8px 0; border-top:1px solid var(--line); font-size:14px; }
  .mp-item img{ width:44px; height:58px; object-fit:cover; background:var(--cream-2); }
  .mp-item .grow{ flex:1; } .mp-item small{ color:rgba(46,31,24,.55); }
  .mp-foot{ display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; font-size:13px; color:rgba(46,31,24,.65); margin-top:10px; }
  .mp-foot strong{ color:var(--choc); font-size:15px; }
</style>

<div class="crumbs" style="padding:20px 0 0;"><a href="index.php">Início</a><span>›</span><b>Meus pedidos</b></div>
<div class="page-head">
  <span class="eyebrow">Sua conta</span>
  <h1>Meus pedidos</h1>
  <p><?= $email ? 'Pedidos feitos com ' . mmsH($email) . '.' : 'Acompanhe suas compras sem precisar de senha: enviamos um link de acesso para o seu e-mail.' ?></p>
</div>
<hr>

<div class="mp-wrap">
  <?php if ($message): ?><div class="mp-note"><?= mmsH($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="mp-note err"><?= mmsH($error) ?></div><?php endif; ?>

  <?php if (!$email): ?>
    <form class="mp-card" method="post" action="meus-pedidos.php">
      <label for="email">E-mail usado na compra</label>
      <input type="email" id="email" name="email" required placeholder="seu@email.com" autocomplete="email">
      <button type="submit" class="btn solid" style="margin-top:16px;">Receber link de acesso</button>
      <p style="font-size:12px; color:rgba(46,31,24,.6); margin-top:14px;">Dúvidas? Chame no <a href="https://wa.me/5584999001179" target="_blank" rel="noopener" style="text-decoration:underline;">WhatsApp (84) 99900-1179</a>.</p>
    </form>
  <?php else: ?>
    <?php if (!$orders): ?>
      <div class="mp-card">Nenhum pedido encontrado com este e-mail.</div>
    <?php endif; ?>
    <?php foreach ($orders as $o): [$label, $kind] = mmsCustomerStatus($o); ?>
      <div class="mp-order">
        <div class="mp-head">
          <h3>Pedido #<?= (int)$o['id'] ?></h3>
          <span class="mp-badge <?= $kind ?>"><?= mmsH($label) ?></span>
        </div>
        <?php foreach ($o['items'] as $it): ?>
          <div class="mp-item">
            <?php if (!empty($it['image_url'])): ?><img src="<?= mmsH($it['image_url']) ?>" alt=""><?php endif; ?>
            <div class="grow"><?= (int)$it['quantity'] ?>x <?= mmsH($it['name']) ?><br><small><?= mmsH(trim($it['variant_color'] . ' · ' . $it['variant_size'], ' ·')) ?></small></div>
            <div><?= mmsMailMoney($it['unit_price'] * $it['quantity']) ?></div>
          </div>
        <?php endforeach; ?>
        <div class="mp-foot">
          <span><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?> ·
            <?= $o['fulfillment_method'] === 'pickup' ? 'Retirada na Loja 02 · SuperFácil' : 'Entrega' . ($o['shipping_service'] ? ' (' . mmsH($o['shipping_service']) . ')' : '') ?></span>
          <strong>Total <?= mmsMailMoney($o['total_amount']) ?></strong>
        </div>
      </div>
    <?php endforeach; ?>
    <p style="font-size:13px;"><a href="meus-pedidos.php?sair=1" style="text-decoration:underline;">Sair</a> ·
      Precisa de ajuda com um pedido? <a href="https://wa.me/5584999001179" target="_blank" rel="noopener" style="text-decoration:underline;">WhatsApp</a></p>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/site_foot.php'; ?>
