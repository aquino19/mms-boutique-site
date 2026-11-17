<?php
/**
 * Diagnóstico do Mercado Pago: confere o token e tenta criar uma
 * preferência de R$ 1,00 (não cobra nada; só gera um link de pagamento).
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/gateway.php';
requireLogin();

$pdo = getDbConnection();
$settings = $pdo->query('SELECT * FROM store_settings WHERE id = 1')->fetch() ?: [];
$token = trim((string)($settings['gateway_access_token'] ?? ''));
$checks = [];

function addCheck(array &$checks, bool $ok, string $title, string $detail = ''): void
{
    $checks[] = ['ok' => $ok, 'title' => $title, 'detail' => $detail];
}

addCheck($checks, ($settings['gateway_provider'] ?? '') === 'mercadopago', 'Gateway escolhido: Mercado Pago', 'Atual: ' . (($settings['gateway_provider'] ?? '') ?: 'nenhum'));
addCheck($checks, $token !== '', 'Access Token preenchido',
    $token === '' ? 'Cole o token em Configurações.' : 'Começa com ' . substr($token, 0, 8) . '… e tem ' . strlen($token) . ' caracteres');
if ($token !== '' && $token !== ($settings['gateway_access_token'] ?? '')) {
    addCheck($checks, false, 'Token sem espaços', 'O token salvo tem espaço ou quebra de linha no começo/fim. Cole de novo e salve.');
}
addCheck($checks, function_exists('curl_init'), 'Servidor consegue fazer chamadas (cURL)');
addCheck($checks, true, 'Endereço do site enviado ao Mercado Pago', gatewaySiteBaseUrl());

if ($token !== '' && function_exists('curl_init')) {
    try {
        $me = mp_api_request('GET', 'https://api.mercadopago.com/users/me', $token);
        $isTestUser = stripos((string)($me['nickname'] ?? ''), 'TEST') === 0 || !empty($me['test_user']) || in_array('test_user', (array)($me['tags'] ?? []), true);
        addCheck($checks, true, 'Token aceito pelo Mercado Pago',
            'Conta: ' . ($me['nickname'] ?? '?') . ' (id ' . ($me['id'] ?? '?') . ', ' . ($me['site_id'] ?? '?') . ')'
            . ($isTestUser ? ' — conta de TESTE (pagamentos não são reais)' : ' — conta REAL (pagamentos são cobrados de verdade)'));
    } catch (Throwable $e) {
        addCheck($checks, false, 'Token aceito pelo Mercado Pago', $e->getMessage());
    }
    try {
        $pref = mp_create_charge([
            'id' => 'teste-' . time(),
            'customer_email' => 'teste@example.com',
            'base_url' => gatewaySiteBaseUrl(),
            'items' => [['name' => 'Teste de conexão', 'quantity' => 1, 'unit_price' => 1.00]],
        ], $token);
        addCheck($checks, !empty($pref['checkout_url']), 'Criar link de pagamento (R$ 1,00 de teste, nada é cobrado)',
            !empty($pref['checkout_url']) ? 'OK: ' . $pref['checkout_url'] : 'Mercado Pago não devolveu o link.');
    } catch (Throwable $e) {
        addCheck($checks, false, 'Criar link de pagamento', $e->getMessage());
    }
}

$pageTitle = 'Testar Mercado Pago';
require __DIR__ . '/includes/layout_start.php';
?>
<h1>Testar conexão com o Mercado Pago</h1>
<div class="card">
  <?php foreach ($checks as $c): ?>
    <div style="padding:10px 0; border-bottom:1px solid #eee;">
      <strong style="color:<?= $c['ok'] ? '#0E5C33' : '#8C5443' ?>;"><?= $c['ok'] ? '✓' : '✗' ?> <?= htmlspecialchars($c['title']) ?></strong>
      <?php if ($c['detail'] !== ''): ?><div style="font-size:13px; color:#555; margin-top:4px; word-break:break-all;"><?= htmlspecialchars($c['detail']) ?></div><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!empty($settings['gateway_last_error'])): ?>
    <div style="padding:10px 0;">
      <strong>Último erro ao abrir o pagamento de um pedido:</strong>
      <div style="font-size:13px; color:#555; margin-top:4px; word-break:break-all;"><?= htmlspecialchars($settings['gateway_last_error']) ?></div>
    </div>
  <?php endif; ?>
</div>
<p><a class="btn secondary" href="settings.php">← Voltar às Configurações</a> <a class="btn" href="gateway_test.php">Testar de novo</a></p>
<?php require __DIR__ . '/includes/layout_end.php'; ?>
