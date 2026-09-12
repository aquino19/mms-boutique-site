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
                 gateway_public_key = :gateway_public_key
             WHERE id = 1'
        );
        $stmt->execute([
            'store_name' => $storeName,
            'contact_email' => $contactEmail,
            'gateway_provider' => $gatewayProvider !== '' ? $gatewayProvider : null,
            'gateway_access_token' => $gatewayAccessToken !== '' ? $gatewayAccessToken : null,
            'gateway_public_key' => $gatewayPublicKey !== '' ? $gatewayPublicKey : null,
        ]);

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

  <label for="contact_email">E-mail de contato</label>
  <input type="email" id="contact_email" name="contact_email"
         value="<?= htmlspecialchars($settings['contact_email'] ?? '') ?>">

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
  <input type="text" id="gateway_access_token" name="gateway_access_token"
         placeholder="Cole aqui o token de acesso da conta do gateway"
         value="<?= htmlspecialchars($settings['gateway_access_token'] ?? '') ?>">

  <label for="gateway_public_key">Public Key (opcional, só Mercado Pago)</label>
  <input type="text" id="gateway_public_key" name="gateway_public_key"
         value="<?= htmlspecialchars($settings['gateway_public_key'] ?? '') ?>">

  <div style="margin-top:20px;">
    <button type="submit" class="btn">Salvar configurações</button>
  </div>
</form>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
