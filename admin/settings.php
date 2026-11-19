<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pdo = getDbConnection();

$errors = [];
$saved = isset($_GET['saved']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $storeName = trim($_POST['store_name'] ?? '');
    $contactEmail = trim($_POST['contact_email'] ?? '');
    $gatewayProvider = $_POST['gateway_provider'] ?? '';
    $gatewayAccessToken = trim($_POST['gateway_access_token'] ?? '');
    $gatewayPublicKey = trim($_POST['gateway_public_key'] ?? '');
    $melhorEnvioToken = trim($_POST['melhor_envio_token'] ?? '');
    $smtpUser = trim($_POST['smtp_user'] ?? '');
    $smtpPassNew = str_replace(' ', '', trim($_POST['smtp_pass'] ?? ''));

    if ($storeName === '') {
        $errors[] = 'O nome da loja é obrigatório.';
    }
    if ($gatewayProvider !== '' && !in_array($gatewayProvider, ['mercadopago', 'pagseguro'], true)) {
        $errors[] = 'Gateway de pagamento inválido.';
    }

    if (empty($errors)) {
        // NOTA: em produção, gatewayAccessToken deve ser criptografado antes de
        // gravar no banco (ex: com sodium_crypto_secretbox ou openssl_encrypt),
        // nunca guardado em texto puro como está aqui na versão de teste.
        $stmt = $pdo->prepare(
            'UPDATE store_settings
             SET store_name = :store_name,
                 contact_email = :contact_email,
                 gateway_provider = :gateway_provider,
                 gateway_access_token = :gateway_access_token,
                 gateway_public_key = :gateway_public_key,
                 melhor_envio_token = :melhor_envio_token
             WHERE id = 1'
        );
        $stmt->execute([
            'store_name' => $storeName,
            'contact_email' => $contactEmail,
            'gateway_provider' => $gatewayProvider !== '' ? $gatewayProvider : null,
            'gateway_access_token' => $gatewayAccessToken !== '' ? $gatewayAccessToken : null,
            'gateway_public_key' => $gatewayPublicKey !== '' ? $gatewayPublicKey : null,
            'melhor_envio_token' => $melhorEnvioToken !== '' ? $melhorEnvioToken : null,
        ]);

        // Conta de envio de e-mails (colunas da migração de e-mails; ignora se ainda não existir)
        try {
            if ($smtpPassNew !== '') {
                $pdo->prepare('UPDATE store_settings SET smtp_user = :u, smtp_pass = :p WHERE id = 1')
                    ->execute(['u' => $smtpUser ?: null, 'p' => $smtpPassNew]);
            } else {
                $pdo->prepare('UPDATE store_settings SET smtp_user = :u WHERE id = 1')->execute(['u' => $smtpUser ?: null]);
            }
        } catch (PDOException $e) {
        }

        header('Location: settings.php?saved=1');
        exit;
    }
}

$settings = $pdo->query('SELECT * FROM store_settings WHERE id = 1')->fetch();

$pageTitle = 'Configurações';
require __DIR__ . '/includes/layout_start.php';
?>

<h1>Configurações da loja</h1>

<?php if ($saved): ?>
  <div class="success">Configurações salvas com sucesso.</div>
<?php endif; ?>

<?php foreach ($errors as $error): ?>
  <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endforeach; ?>

<form class="card" method="post" action="settings.php">
  <label for="store_name">Nome da loja</label>
  <input type="text" id="store_name" name="store_name" required
         value="<?= htmlspecialchars($settings['store_name'] ?? '') ?>">

  <label for="contact_email">E-mail da loja (recebe os avisos de pedido e as respostas das clientes)</label>
  <input type="email" id="contact_email" name="contact_email"
         value="<?= htmlspecialchars($settings['contact_email'] ?? '') ?>">

  <fieldset style="border:1px solid #ddd; padding:12px 16px; margin:18px 0;">
    <legend style="font-weight:bold; padding:0 6px;">Conta que envia os e-mails (Gmail)</legend>
    <small style="display:block; color:#777; margin-bottom:6px;">Usada para confirmação de pedido, avisos de andamento e o link de "Meus pedidos".
      Use uma <b>senha de app</b> do Google (16 letras), não a senha normal do Gmail.</small>
    <label for="smtp_user">Gmail</label>
    <input type="email" id="smtp_user" name="smtp_user" placeholder="lojamms20@gmail.com" autocomplete="off"
           value="<?= htmlspecialchars($settings['smtp_user'] ?? '') ?>">
    <label for="smtp_pass">Senha de app <?= !empty($settings['smtp_pass']) ? '<span style="color:#0E5C33;">(salva — deixe em branco para manter)</span>' : '' ?></label>
    <input type="password" id="smtp_pass" name="smtp_pass" autocomplete="new-password" placeholder="xxxx xxxx xxxx xxxx">
  </fieldset>

  <label for="gateway_provider">Gateway de pagamento</label>
  <select id="gateway_provider" name="gateway_provider">
    <option value="">— Selecione —</option>
    <option value="mercadopago" <?= ($settings['gateway_provider'] ?? '') === 'mercadopago' ? 'selected' : '' ?>>
      Mercado Pago
    </option>
    <option value="pagseguro" <?= ($settings['gateway_provider'] ?? '') === 'pagseguro' ? 'selected' : '' ?>>
      PagSeguro
    </option>
  </select>

  <label for="gateway_access_token">Access Token do gateway</label>
  <small style="display:block; color:#777; margin-bottom:6px;">Mercado Pago: começa com <code>APP_USR-</code> e fica em Suas integrações → aplicação → Credenciais de produção → Access Token.</small>
  <input type="text" id="gateway_access_token" name="gateway_access_token"
         placeholder="Cole aqui o token de acesso da conta do gateway"
         value="<?= htmlspecialchars($settings['gateway_access_token'] ?? '') ?>">

  <label for="gateway_public_key">Public Key (opcional, só Mercado Pago)</label>
  <input type="text" id="gateway_public_key" name="gateway_public_key"
         value="<?= htmlspecialchars($settings['gateway_public_key'] ?? '') ?>">

  <label for="melhor_envio_token">Token de API do Melhor Envio (frete)</label>
  <input type="text" id="melhor_envio_token" name="melhor_envio_token"
         placeholder="Cole aqui o token de acesso do Melhor Envio"
         value="<?= htmlspecialchars($settings['melhor_envio_token'] ?? '') ?>">

  <div style="margin-top:20px;">
    <button type="submit" class="btn">Salvar configurações</button>
  </div>
</form>

<div class="card" style="padding:14px 18px;">
  <strong>Algo não funciona?</strong>
  <a class="btn secondary" href="gateway_test.php" style="margin-left:10px;">Testar conexão com o Mercado Pago</a>
  <a class="btn secondary" href="shipping_test.php" style="margin-left:6px;">Testar frete (Melhor Envio)</a>
  <a class="btn secondary" href="mail_test.php" style="margin-left:6px;">Testar e-mail</a>
</div>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
