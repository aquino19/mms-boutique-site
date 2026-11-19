<?php
/** Envia um e-mail de teste para o e-mail da loja e mostra o resultado. */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
requireLogin();

$pdo = getDbConnection();
$settings = $pdo->query('SELECT * FROM store_settings WHERE id = 1')->fetch() ?: [];
$to = trim((string)($_POST['to'] ?? ($settings['contact_email'] ?? '')));
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = mmsSendMail($pdo, $to, "MM'S Boutique — e-mail de teste",
        mmsMailLayout('Tudo certo com os e-mails!', '<p style="font-size:15px;">Se você recebeu esta mensagem, a loja já consegue enviar confirmações de pedido.</p>'));
    $err = $pdo->query('SELECT mail_last_error FROM store_settings WHERE id = 1')->fetchColumn();
    $result = $ok ? ['ok' => true, 'msg' => 'Enviado para ' . $to . '. Confira a caixa de entrada (e o spam).'] : ['ok' => false, 'msg' => (string)$err];
}

$pageTitle = 'Testar e-mail';
require __DIR__ . '/includes/layout_start.php';
?>
<h1>Testar envio de e-mail</h1>
<div class="card">
  <p>Conta que envia: <strong><?= htmlspecialchars(($settings['smtp_user'] ?? '') ?: 'não configurada (vai tentar o servidor da Hostinger)') ?></strong>
    · Senha de app: <strong><?= !empty($settings['smtp_pass']) ? 'salva' : 'não preenchida' ?></strong></p>
  <?php if ($result): ?>
    <div style="padding:12px 14px; margin:10px 0; background:<?= $result['ok'] ? '#e6f2ea' : '#fbe9e5' ?>; border-left:3px solid <?= $result['ok'] ? '#0E5C33' : '#8C5443' ?>; word-break:break-word;">
      <?= $result['ok'] ? '✓ ' : '✗ ' ?><?= htmlspecialchars($result['msg']) ?>
      <?php if (!$result['ok'] && stripos($result['msg'], 'senha') !== false): ?>
        <br><small>Confira se a verificação em duas etapas está ativa no Gmail e se a senha colada é uma <b>senha de app</b>.</small>
      <?php endif; ?>
    </div>
  <?php elseif (!empty($settings['mail_last_error'])): ?>
    <p style="font-size:13px; color:#8C5443;">Último erro de envio: <?= htmlspecialchars($settings['mail_last_error']) ?></p>
  <?php endif; ?>
  <form method="post">
    <label for="to">Enviar teste para</label>
    <input type="email" id="to" name="to" required value="<?= htmlspecialchars($to) ?>">
    <button class="btn" type="submit" style="margin-top:10px;">Enviar e-mail de teste</button>
  </form>
</div>
<p><a class="btn secondary" href="settings.php">← Voltar às Configurações</a></p>
<?php require __DIR__ . '/includes/layout_end.php'; ?>
