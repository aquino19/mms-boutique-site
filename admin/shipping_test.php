<?php
/**
 * Diagnóstico do Melhor Envio: confere o token (conta, ambiente) e faz uma
 * cotação de exemplo da Loja 02 para um CEP de Natal. Não compra nada.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/shipping/melhorenvio.php';
requireLogin();

$pdo = getDbConnection();
$settings = $pdo->query('SELECT melhor_envio_token FROM store_settings WHERE id = 1')->fetch() ?: [];
$token = trim((string)($settings['melhor_envio_token'] ?? ''));
$dest = preg_replace('/\D/', '', $_GET['cep'] ?? '59020000') ?: '59020000';
$checks = [];
function addCheck(array &$c, bool $ok, string $t, string $d = ''): void { $c[] = ['ok' => $ok, 't' => $t, 'd' => $d]; }

addCheck($checks, $token !== '', 'Token do Melhor Envio preenchido', $token === '' ? 'Cole o token em Configurações.' : strlen($token) . ' caracteres');

$locations = $pdo->query('SELECT name, code, cep, active FROM store_locations ORDER BY id')->fetchAll();
$origin = null;
foreach ($locations as $l) {
    $ok = !$l['active'] || strlen(preg_replace('/\D/', '', (string)$l['cep'])) === 8;
    addCheck($checks, $ok, 'Unidade ' . $l['name'] . ($l['active'] ? '' : ' (inativa)'), 'CEP: ' . ($l['cep'] ?: 'não cadastrado'));
    if ($l['code'] === 'sao_goncalo' && $l['cep']) $origin = $l['cep'];
}
if (!$origin) addCheck($checks, false, 'CEP de origem (sao_goncalo)', 'Cadastre o CEP da Loja 02 em Unidades.');

if ($token !== '' && function_exists('curl_init')) {
    // 1) Quem é o dono do token
    $ch = curl_init('https://api.melhorenvio.com.br/api/v2/me');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => [
        'Accept: application/json', 'Authorization: Bearer ' . $token, 'User-Agent: MMS Boutique (contato@mmsboutique.com.br)']]);
    $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($err) {
        addCheck($checks, false, 'Conexão com o Melhor Envio', $err);
    } elseif ($code === 200) {
        $me = json_decode($body, true) ?: [];
        addCheck($checks, true, 'Token aceito (ambiente de PRODUÇÃO)', 'Conta: ' . trim(($me['firstname'] ?? '') . ' ' . ($me['lastname'] ?? '')) . ' · ' . ($me['email'] ?? ''));
    } else {
        $hint = $code === 401 ? ' — token inválido, expirado ou gerado no SANDBOX (sandbox.melhorenvio.com.br não funciona aqui).' : '';
        addCheck($checks, false, 'Token aceito pelo Melhor Envio', 'HTTP ' . $code . $hint . ' Resposta: ' . mb_substr((string)$body, 0, 400));
    }
    // 2) Cotação de exemplo
    if ($origin) {
        try {
            $opts = me_calculate_shipping($origin, $dest, [[
                'product_id' => 1, 'width_cm' => 25, 'height_cm' => 4, 'length_cm' => 30, 'weight_kg' => 0.4, 'unit_price' => 89.9, 'quantity' => 1,
            ]], $token);
            $list = implode(' · ', array_map(fn($o) => $o['service_name'] . ' ' . ($o['company_name'] ?? '') . ' R$ ' . number_format((float)$o['price'], 2, ',', '.'), $opts));
            addCheck($checks, !empty($opts), 'Cotação de ' . $origin . ' para ' . $dest, $opts ? $list : 'Nenhuma transportadora atende esse trecho (confira os serviços habilitados na sua conta do Melhor Envio).');
        } catch (Throwable $e) {
            $m = $e->getMessage();
            $hint = (stripos($m, '403') !== false || stripos($m, 'scope') !== false || stripos($m, 'permission') !== false)
                ? ' — o token não tem a permissão de cálculo de frete (shipping-calculate). Gere um token novo marcando essa permissão.' : '';
            addCheck($checks, false, 'Cotação de ' . $origin . ' para ' . $dest, mb_substr($m, 0, 600) . $hint);
        }
    }
}

$pageTitle = 'Testar Melhor Envio';
require __DIR__ . '/includes/layout_start.php';
?>
<h1>Testar frete (Melhor Envio)</h1>
<div class="card">
  <?php foreach ($checks as $c): ?>
    <div style="padding:10px 0; border-bottom:1px solid #eee;">
      <strong style="color:<?= $c['ok'] ? '#0E5C33' : '#8C5443' ?>;"><?= $c['ok'] ? '✓' : '✗' ?> <?= htmlspecialchars($c['t']) ?></strong>
      <?php if ($c['d'] !== ''): ?><div style="font-size:13px; color:#555; margin-top:4px; word-break:break-all;"><?= htmlspecialchars($c['d']) ?></div><?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<form method="get" style="margin:12px 0;">CEP de destino: <input name="cep" value="<?= htmlspecialchars($dest) ?>" style="width:120px;"> <button class="btn" type="submit">Testar</button></form>
<p><a class="btn secondary" href="settings.php">← Voltar às Configurações</a></p>
<?php require __DIR__ . '/includes/layout_end.php'; ?>
