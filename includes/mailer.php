<?php
/**
 * Envio de e-mails da loja, sem bibliotecas externas.
 *
 * Usa a conta Gmail configurada no admin (Configurações > E-mails da loja)
 * com uma SENHA DE APP do Google (não a senha normal da conta).
 * Sem conta configurada, tenta a função mail() do servidor como reserva.
 *
 * Todo e-mail é "melhor esforço": uma falha nunca interrompe o pedido;
 * o motivo fica em store_settings.mail_last_error e aparece no admin.
 */

const MMS_SMTP_HOST = 'smtp.gmail.com';
const MMS_SMTP_PORT = 465;          // SSL direto
const MMS_SMTP_TIMEOUT = 15;

function mmsMailSettings(PDO $pdo): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = $pdo->query('SELECT * FROM store_settings WHERE id = 1')->fetch() ?: [];
    }
    return $cache;
}

/** Endereço público do site (https), para links nos e-mails. */
function mmsSiteUrl(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    $host = $_SERVER['HTTP_HOST'] ?? 'mmsboutique.com.br';
    return ($https ? 'https' : 'http') . '://' . $host;
}

/**
 * Envia um e-mail HTML. Retorna true se foi aceito pelo servidor de envio.
 * $smtpOverride só é usado nos testes (host/porta/segurança).
 */
function mmsSendMail(PDO $pdo, string $to, string $subject, string $html, ?string $replyTo = null, array $smtpOverride = []): bool
{
    $s = mmsMailSettings($pdo);
    $user = trim((string)($s['smtp_user'] ?? ''));
    $pass = str_replace(' ', '', (string)($s['smtp_pass'] ?? '')); // senha de app vem em blocos com espaço
    $fromName = (string)($s['store_name'] ?? "MM'S Boutique") ?: "MM'S Boutique";

    try {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Destinatário inválido: ' . $to);
        }
        if ($user !== '' && $pass !== '') {
            mmsSmtpSend(
                $smtpOverride['host'] ?? MMS_SMTP_HOST,
                (int)($smtpOverride['port'] ?? MMS_SMTP_PORT),
                $smtpOverride['secure'] ?? 'ssl',
                $user, $pass, $user, $fromName, $to, $subject, $html, $replyTo
            );
        } else {
            $from = 'noreply@' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'mmsboutique.com.br');
            $headers = mmsMailHeaders($from, $fromName, $to, $subject, $replyTo ?: ($s['contact_email'] ?? null), false);
            if (!@mail($to, mmsEncodeHeader($subject), mmsQuotedPrintable($html), $headers)) {
                throw new RuntimeException('mail() do servidor recusou o envio. Configure a conta de e-mail em Configurações.');
            }
        }
        return true;
    } catch (Throwable $e) {
        error_log('E-mail para ' . $to . ' falhou: ' . $e->getMessage());
        try {
            $pdo->prepare('UPDATE store_settings SET mail_last_error = :e WHERE id = 1')
                ->execute(['e' => date('d/m/Y H:i') . ' — para ' . $to . ': ' . mb_substr($e->getMessage(), 0, 900)]);
        } catch (Throwable $ignored) {
        }
        return false;
    }
}

function mmsEncodeHeader(string $text): string
{
    return preg_match('/[^\x20-\x7E]/', $text) ? '=?UTF-8?B?' . base64_encode($text) . '?=' : $text;
}

function mmsQuotedPrintable(string $html): string
{
    return quoted_printable_encode(str_replace(["\r\n", "\r"], "\n", $html));
}

function mmsMailHeaders(string $from, string $fromName, string $to, string $subject, ?string $replyTo, bool $full): string
{
    $h = [];
    if ($full) {
        $h[] = 'Date: ' . date('r');
        $h[] = 'To: <' . $to . '>';
        $h[] = 'Subject: ' . mmsEncodeHeader($subject);
        $h[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . substr(strrchr($from, '@'), 1) . '>';
    }
    $h[] = 'From: ' . mmsEncodeHeader($fromName) . ' <' . $from . '>';
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $h[] = 'Reply-To: <' . $replyTo . '>';
    }
    $h[] = 'MIME-Version: 1.0';
    $h[] = 'Content-Type: text/html; charset=UTF-8';
    $h[] = 'Content-Transfer-Encoding: quoted-printable';
    return implode("\r\n", $h);
}

/** Conversa SMTP mínima (AUTH LOGIN). $secure: 'ssl' (465), 'tls' (587 STARTTLS) ou 'none' (testes). */
function mmsSmtpSend(string $host, int $port, string $secure, string $user, string $pass, string $from, string $fromName,
                     string $to, string $subject, string $html, ?string $replyTo): void
{
    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, MMS_SMTP_TIMEOUT, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        throw new RuntimeException("Não conectou em $host:$port ($errstr)");
    }
    stream_set_timeout($fp, MMS_SMTP_TIMEOUT);

    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = function (string $command, array $okCodes, string $label = '') use ($fp, $read): string {
        if ($command !== '') {
            fwrite($fp, $command . "\r\n");
        }
        $resp = $read();
        $code = (int)substr($resp, 0, 3);
        if (!in_array($code, $okCodes, true)) {
            $shown = $label !== '' ? $label : $command;
            throw new RuntimeException('Servidor de e-mail recusou ' . $shown . ': ' . trim($resp));
        }
        return $resp;
    };

    try {
        $cmd('', [220], 'a conexão');
        $ehlo = 'EHLO ' . (gethostname() ?: 'localhost');
        $cmd($ehlo, [250]);
        if ($secure === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Falha ao iniciar TLS');
            }
            $cmd($ehlo, [250]);
        }
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($user), [334], 'o usuário');
        $cmd(base64_encode($pass), [235], 'a senha (use uma SENHA DE APP do Google)');
        $cmd('MAIL FROM:<' . $from . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);
        $body = mmsMailHeaders($from, $fromName, $to, $subject, $replyTo, true) . "\r\n\r\n"
            . str_replace("\n", "\r\n", mmsQuotedPrintable($html));
        // linhas começando com ponto precisam ser duplicadas (RFC 5321)
        $body = preg_replace('/^\./m', '..', $body);
        $cmd($body . "\r\n.", [250], 'a mensagem');
        @fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}

/* ===================== Modelos de e-mail ===================== */

function mmsMailLayout(string $title, string $bodyHtml): string
{
    $site = mmsSiteUrl();
    return '<!doctype html><html><body style="margin:0;background:#F7F2E6;font-family:Arial,Helvetica,sans-serif;color:#2E1F18;">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#F7F2E6;padding:24px 0;"><tr><td align="center">'
        . '<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-top:4px solid #0E5C33;">'
        . '<tr><td style="padding:22px 28px;background:#0E5C33;color:#F7F2E6;font-family:Georgia,serif;font-size:22px;font-weight:bold;">MM\'S <span style="font-size:12px;letter-spacing:3px;font-family:Arial;">BOUTIQUE</span></td></tr>'
        . '<tr><td style="padding:28px;">'
        . '<h1 style="font-family:Georgia,serif;font-size:22px;margin:0 0 16px;color:#2E1F18;">' . htmlspecialchars($title) . '</h1>'
        . $bodyHtml
        . '</td></tr>'
        . '<tr><td style="padding:16px 28px;background:#EFE6D1;font-size:12px;color:#6b5a50;">'
        . 'MM\'S Boutique · Av. Dr. Ruy Pereira dos Santos, 258 · São Gonçalo do Amarante/RN<br>'
        . 'WhatsApp (84) 99900-1179 · <a href="' . $site . '" style="color:#0E5C33;">mmsboutique.com.br</a>'
        . '</td></tr></table></td></tr></table></body></html>';
}

function mmsMailMoney($v): string
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

function mmsOrderItemsHtml(array $items): string
{
    $rows = '';
    foreach ($items as $it) {
        $variant = trim(($it['variant_color'] ?? '') . ' ' . ($it['variant_size'] ?? ''));
        $rows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #eee;">' . (int)$it['quantity'] . 'x '
            . htmlspecialchars((string)$it['product_name']) . ($variant !== '' ? ' <span style="color:#888;">(' . htmlspecialchars($variant) . ')</span>' : '')
            . '</td><td align="right" style="padding:8px 0;border-bottom:1px solid #eee;white-space:nowrap;">'
            . mmsMailMoney($it['unit_price'] * $it['quantity']) . '</td></tr>';
    }
    return '<table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;">' . $rows . '</table>';
}

function mmsLoadOrder(PDO $pdo, int $orderId): ?array
{
    $o = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
    $o->execute(['id' => $orderId]);
    $order = $o->fetch();
    if (!$order) {
        return null;
    }
    $i = $pdo->prepare(
        'SELECT oi.*, p.name AS product_name, sl.name AS location_name
         FROM order_items oi JOIN products p ON p.id = oi.product_id
         LEFT JOIN store_locations sl ON sl.id = oi.fulfillment_location_id
         WHERE oi.order_id = :id'
    );
    $i->execute(['id' => $orderId]);
    $order['items'] = $i->fetchAll();
    return $order;
}

/**
 * Chamado sempre que um pedido passa a "paid". Manda os e-mails uma única vez
 * (a coluna paid_notified_at garante isso mesmo com webhook + retorno ao site).
 */
function mmsNotifyOrderPaid(PDO $pdo, int $orderId): void
{
    try {
        $claim = $pdo->prepare("UPDATE orders SET paid_notified_at = NOW() WHERE id = :id AND status = 'paid' AND paid_notified_at IS NULL");
        $claim->execute(['id' => $orderId]);
        if ($claim->rowCount() !== 1) {
            return;
        }
        $order = mmsLoadOrder($pdo, $orderId);
        if (!$order) {
            return;
        }
        $s = mmsMailSettings($pdo);
        $site = mmsSiteUrl();
        $shopEmail = trim((string)($s['contact_email'] ?? ''));

        $totals = '<p style="font-size:14px;margin:12px 0 0;">'
            . ((float)$order['discount_amount'] > 0 ? 'Desconto (' . htmlspecialchars((string)$order['coupon_code']) . '): - ' . mmsMailMoney($order['discount_amount']) . '<br>' : '')
            . ((float)$order['shipping_cost'] > 0 ? 'Frete (' . htmlspecialchars((string)$order['shipping_service']) . '): ' . mmsMailMoney($order['shipping_cost']) . '<br>' : '')
            . '<strong style="font-size:16px;">Total: ' . mmsMailMoney($order['total_amount']) . '</strong></p>';

        if ($order['fulfillment_method'] === 'pickup') {
            $next = '<p style="font-size:14px;line-height:1.6;">Vamos separar as peças e avisar quando estiver <b>pronto para retirada</b> na '
                . '<b>Loja 02 · SuperFácil Atacado</b> (Av. Dr. Ruy Pereira dos Santos, 258, Olho D\'Água, São Gonçalo do Amarante), '
                . 'de segunda a sábado, das 09h às 20h. Leve um documento com foto e o número do pedido.</p>';
        } else {
            $addr = htmlspecialchars(trim($order['address_street'] . ', ' . $order['address_number']
                . ($order['address_complement'] ? ' - ' . $order['address_complement'] : '')
                . ' · ' . $order['address_neighborhood'] . ' · ' . $order['address_city'] . '/' . $order['address_state'] . ' · CEP ' . $order['address_zip']));
            $next = '<p style="font-size:14px;line-height:1.6;">Entrega em: ' . $addr . '<br>Avisamos por e-mail quando o pedido for enviado.</p>';
        }

        $customerHtml = mmsMailLayout('Pedido #' . $order['id'] . ' confirmado!',
            '<p style="font-size:15px;line-height:1.6;">Oi, ' . htmlspecialchars(strtok((string)$order['customer_name'], ' ')) . '! Recebemos o pagamento do seu pedido. Obrigada por comprar na MM\'S. 💚</p>'
            . mmsOrderItemsHtml($order['items']) . $totals . $next
            . '<p style="margin:24px 0 0;"><a href="' . $site . '/meus-pedidos.php" style="background:#0E5C33;color:#F7F2E6;padding:12px 22px;text-decoration:none;font-weight:bold;font-size:13px;">ACOMPANHAR MEUS PEDIDOS</a></p>'
            . '<p style="font-size:12px;color:#888;margin-top:24px;">Dúvidas? Responda este e-mail ou chame no WhatsApp (84) 99900-1179.</p>');
        mmsSendMail($pdo, (string)$order['customer_email'], "MM'S Boutique — pedido #" . $order['id'] . ' confirmado', $customerHtml, $shopEmail ?: null);

        if ($shopEmail !== '') {
            $shopHtml = mmsMailLayout('Novo pedido pago: #' . $order['id'],
                '<p style="font-size:14px;line-height:1.6;"><b>' . htmlspecialchars((string)$order['customer_name']) . '</b> · '
                . htmlspecialchars((string)$order['customer_email']) . ($order['customer_phone'] ? ' · ' . htmlspecialchars((string)$order['customer_phone']) : '') . '<br>'
                . ($order['fulfillment_method'] === 'pickup' ? '<b>Retirada na loja</b>' : '<b>Entrega</b> (' . htmlspecialchars((string)$order['shipping_service']) . ')') . '</p>'
                . mmsOrderItemsHtml($order['items']) . $totals
                . '<p style="font-size:14px;line-height:1.6;background:#fff4d6;padding:10px 12px;border-left:3px solid #D4AF37;">Lance a venda no sistema da loja e dê baixa no estoque antes de separar.</p>'
                . '<p><a href="' . $site . '/admin/orders.php#pedido-' . (int)$order['id'] . '" style="color:#0E5C33;font-weight:bold;">Abrir no admin →</a></p>');
            mmsSendMail($pdo, $shopEmail, 'Novo pedido pago #' . $order['id'] . ' — ' . mmsMailMoney($order['total_amount']), $shopHtml);
        }
    } catch (Throwable $e) {
        error_log('mmsNotifyOrderPaid #' . $orderId . ': ' . $e->getMessage());
    }
}

/** Avisa a cliente quando o pedido fica pronto para retirada, é enviado ou entregue (uma vez por etapa). */
function mmsNotifyFulfillment(PDO $pdo, int $orderId, string $status): void
{
    try {
        $claim = $pdo->prepare('UPDATE orders SET fulfillment_notified = :s WHERE id = :id AND (fulfillment_notified IS NULL OR fulfillment_notified <> :s2)');
        $claim->execute(['s' => $status, 's2' => $status, 'id' => $orderId]);
        if ($claim->rowCount() !== 1) {
            return;
        }
        $order = mmsLoadOrder($pdo, $orderId);
        if (!$order) {
            return;
        }
        $pickup = $order['fulfillment_method'] === 'pickup';
        $texts = [
            'separado' => $pickup
                ? ['Seu pedido está pronto para retirada!', 'Já separamos as suas peças. Pode retirar na <b>Loja 02 · SuperFácil Atacado</b> (Av. Dr. Ruy Pereira dos Santos, 258, Olho D\'Água, São Gonçalo do Amarante), de segunda a sábado, das 09h às 20h. Leve um documento com foto e o número do pedido.']
                : ['Seu pedido foi separado', 'Já separamos as suas peças e em breve elas seguem para entrega.'],
            'enviado' => ['Seu pedido foi enviado!', 'O pedido saiu para entrega com ' . htmlspecialchars((string)$order['shipping_service']) . '. Qualquer dúvida sobre o rastreio, chame a gente no WhatsApp.'],
            'entregue' => $pickup
                ? ['Pedido retirado. Obrigada!', 'Esperamos que você ame as peças! Se precisar trocar algo, veja as regras em mmsboutique.com.br/trocas-e-devolucoes.php.']
                : ['Pedido entregue. Obrigada!', 'Esperamos que você ame as peças! Se precisar trocar algo, veja as regras em mmsboutique.com.br/trocas-e-devolucoes.php.'],
        ];
        if (!isset($texts[$status])) {
            return;
        }
        [$title, $msg] = $texts[$status];
        $s = mmsMailSettings($pdo);
        $html = mmsMailLayout($title,
            '<p style="font-size:15px;line-height:1.6;">Oi, ' . htmlspecialchars(strtok((string)$order['customer_name'], ' ')) . '! ' . $msg . '</p>'
            . '<p style="font-size:13px;color:#888;">Pedido #' . (int)$order['id'] . '</p>'
            . mmsOrderItemsHtml($order['items'])
            . '<p style="margin:24px 0 0;"><a href="' . mmsSiteUrl() . '/meus-pedidos.php" style="background:#0E5C33;color:#F7F2E6;padding:12px 22px;text-decoration:none;font-weight:bold;font-size:13px;">VER MEUS PEDIDOS</a></p>');
        mmsSendMail($pdo, (string)$order['customer_email'], "MM'S Boutique — " . $title, $html, trim((string)($s['contact_email'] ?? '')) ?: null);
    } catch (Throwable $e) {
        error_log('mmsNotifyFulfillment #' . $orderId . ': ' . $e->getMessage());
    }
}
