<?php
/**
 * Sincronização de catálogo e estoque com o SG Master (sistema da loja).
 *
 * O programa no PC da loja (mms-sync.ps1) lê os produtos marcados como
 * "Disponibilizar online" no SG Master e envia, a cada execução, a lista
 * COMPLETA desses produtos para api/catalog_sync.php, que chama
 * erpSyncCatalog().
 *
 * Regras:
 * - Cada produto do SG Master é um tamanho. Produtos com a mesma
 *   Referência viram UMA peça no site, com um tamanho por produto.
 *   Sem Referência, o produto vira uma peça com um tamanho só.
 * - SKU da variação = Código do SG Master.
 * - Vêm do SG Master: preço, descrição (Descrição adicional web),
 *   tamanhos, peso, estoque e se aparece ou não no site.
 * - Vêm do admin do site: foto, categoria, cor e nome (o nome é sugerido
 *   na criação e não é sobrescrito depois).
 * - A peça só aparece no site quando tem foto e categoria.
 * - Vendas do site ainda não lançadas no SG Master são descontadas do
 *   estoque recebido (reserva), igual ao api/stock_sync.php.
 */

const ERP_RESERVE_PENDING_DAYS = 3;
const ERP_DEFAULT_WEIGHT_KG = 0.4;
const ERP_DEFAULT_DIMS_CM = [4, 25, 30]; // altura, largura, comprimento

/** Tamanhos reconhecidos no nome do produto (além de números). */
const ERP_SIZE_TOKENS = ['PP', 'P', 'M', 'G', 'GG', 'XG', 'XGG', 'EG', 'EGG', 'G1', 'G2', 'G3', 'G4', 'G5', 'U', 'UN', 'UNICO', 'ÚNICO'];

/** Normaliza o texto de tamanho ("02" -> "2", "unico" -> "Único"). */
function erpNormalizeSize(string $size): string
{
    $s = mb_strtoupper(trim($size), 'UTF-8');
    if ($s === '' ) {
        return 'Único';
    }
    if (in_array($s, ['U', 'UN', 'UNICO', 'ÚNICO', 'UNI'], true)) {
        return 'Único';
    }
    if (ctype_digit($s)) {
        return (string)(int)$s;
    }
    return $s;
}

/**
 * Descobre o tamanho: campo Tamanho do SG Master; senão "TAM X" no nome;
 * senão a última palavra do nome que pareça tamanho. Nomes com faixa
 * ("38 AO 46", "40/42/44") são tamanho único.
 * Retorna [tamanho, palavra encontrada no nome ou null].
 */
function erpParseSize(string $name, string $sizeField = ''): array
{
    if (trim($sizeField) !== '') {
        return [erpNormalizeSize($sizeField), null];
    }
    $upper = mb_strtoupper($name, 'UTF-8');
    if (preg_match('/\b\d{1,2}\s*(AO|A)\s*\d{1,2}\b|\d{2}\s*\/\s*\d{2}/u', $upper)) {
        return ['Único', null];
    }
    if (preg_match('/\bTAM\.?\s*([A-Z]{1,5}\d?|\d{1,2})\b/u', $upper, $m)) {
        return [erpNormalizeSize($m[1]), $m[0]];
    }
    $words = preg_split('/\s+/u', trim($upper));
    $found = null;
    foreach ($words as $i => $w) {
        if ($i === 0) {
            continue; // a primeira palavra é o tipo da peça (VESTIDO, BERMUDA...)
        }
        if (in_array($w, ERP_SIZE_TOKENS, true) || preg_match('/^\d{1,2}$/', $w)) {
            $found = $w;
        }
    }
    return $found !== null ? [erpNormalizeSize($found), $found] : ['Único', null];
}

/** Nome da peça sem o tamanho, em formato de título ("BERMUDA CELIA G FLORITA" -> "Bermuda Celia Florita"). */
function erpBaseName(string $name, ?string $sizeWord): string
{
    $n = mb_strtoupper(trim($name), 'UTF-8');
    if ($sizeWord !== null) {
        $n = preg_replace('/(^|\s)' . preg_quote($sizeWord, '/') . '(?=\s|$)/u', ' ', $n, 1);
    }
    $n = trim(preg_replace('/\s+/u', ' ', $n));
    $title = mb_convert_case(mb_strtolower($n, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    // preposições em minúsculo, exceto no início
    $title = preg_replace_callback('/(?<=\s)(De|Da|Do|Das|Dos|E|Com|C\/|Em|Para|Ao|A)(?=\s)/u', fn($m) => mb_strtolower($m[1], 'UTF-8'), $title);
    return $title;
}

/** "Descrição adicional web" do SG Master (HTML) para texto simples. */
function erpPlainDescription(string $html): string
{
    $t = preg_replace('/<\s*(br|\/p|\/div|\/li)\s*\/?>/i', "\n", $html);
    $t = preg_replace('/<\s*li[^>]*>/i', '• ', $t);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace("/[ \t]+/u", ' ', $t);
    $t = preg_replace("/\n\s*\n+/u", "\n\n", $t);
    return trim($t);
}

/** Peças de pedidos do site ainda não lançadas no SG Master (reserva). */
function erpReservedQty(PDO $pdo, int $variantId, int $locationId): int
{
    static $stmt = null;
    if ($stmt === null) {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(oi.quantity), 0)
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE oi.variant_id = :variant_id
               AND oi.fulfillment_location_id = :location_id
               AND o.erp_registered_at IS NULL
               AND (o.status = 'paid'
                    OR (o.status = 'pending' AND o.created_at >= NOW() - INTERVAL " . (int)ERP_RESERVE_PENDING_DAYS . " DAY))"
        );
    }
    $stmt->execute(['variant_id' => $variantId, 'location_id' => $locationId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Aplica a lista completa de produtos "online" do SG Master.
 *
 * $items: [['code','name','reference','size','price','quantity','weight_kg','web_description'], ...]
 * Retorna estatísticas e avisos.
 */
function erpSyncCatalog(PDO $pdo, int $locationId, array $items): array
{
    $stats = [
        'items_received' => count($items), 'products_created' => 0, 'variants_created' => 0,
        'variants_updated' => 0, 'variants_hidden' => 0, 'waiting_photo' => [], 'reserved' => [], 'warnings' => [],
    ];

    // 1) Agrupa por Referência (ou pelo próprio código, sem Referência)
    $groups = [];
    foreach ($items as $it) {
        $code = trim((string)($it['code'] ?? ''));
        $name = trim((string)($it['name'] ?? ''));
        if ($code === '' || $name === '') {
            $stats['warnings'][] = 'Item ignorado sem código ou descrição.';
            continue;
        }
        $ref = mb_strtoupper(trim((string)($it['reference'] ?? '')), 'UTF-8');
        $key = $ref !== '' ? $ref : 'COD:' . $code;
        [$size, $sizeWord] = erpParseSize($name, (string)($it['size'] ?? ''));
        $groups[$key][] = [
            'code' => $code,
            'name' => $name,
            'size' => $size,
            'size_word' => $sizeWord,
            'price' => (float)str_replace(',', '.', (string)($it['price'] ?? 0)),
            'quantity' => (int)floor((float)str_replace(',', '.', (string)($it['quantity'] ?? 0))),
            'weight_kg' => (float)str_replace(',', '.', (string)($it['weight_kg'] ?? 0)),
            'web_description' => (string)($it['web_description'] ?? ''),
        ];
    }

    $findProduct = $pdo->prepare('SELECT * FROM products WHERE erp_ref = :ref');
    $createProduct = $pdo->prepare(
        'INSERT INTO products (sku, name, description, category, price, stock, image_url, weight_kg, height_cm, width_cm, length_cm, active, erp_ref, erp_managed, erp_synced_at)
         VALUES (:sku, :name, :description, NULL, :price, 0, \'\', :weight, :h, :w, :l, 0, :ref, 1, NOW())'
    );
    $updateProduct = $pdo->prepare(
        'UPDATE products SET price = :price, description = :description, weight_kg = :weight, erp_managed = 1, erp_synced_at = NOW() WHERE id = :id'
    );
    $findVariant = $pdo->prepare('SELECT id, product_id FROM product_variants WHERE sku = :sku LIMIT 1');
    $createVariant = $pdo->prepare(
        'INSERT INTO product_variants (product_id, color_name, color_hex, size, sku, active) VALUES (:pid, \'\', NULL, :size, :sku, 1)'
    );
    $activateVariant = $pdo->prepare('UPDATE product_variants SET active = 1 WHERE id = :id');
    $upsertStock = $pdo->prepare(
        'INSERT INTO stock_by_store (variant_id, location_id, quantity) VALUES (:v, :l, :q)
         ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)'
    );

    $seenProductIds = [];
    $seenVariantIds = [];

    foreach ($groups as $ref => $rows) {
        usort($rows, fn($a, $b) => strcmp($a['code'], $b['code']));
        $first = $rows[0];
        $prices = array_unique(array_map(fn($r) => number_format($r['price'], 2, '.', ''), $rows));
        $price = max(array_map(fn($r) => $r['price'], $rows));
        if (count($prices) > 1) {
            $stats['warnings'][] = "Referência $ref tem preços diferentes por tamanho (" . implode(', ', $prices) . '); o site usa o maior.';
        }
        $weights = array_filter(array_map(fn($r) => $r['weight_kg'], $rows), fn($w) => $w > 0);
        $descRow = array_values(array_filter($rows, fn($r) => trim($r['web_description']) !== ''));
        $description = $descRow ? erpPlainDescription($descRow[0]['web_description']) : '';

        $findProduct->execute(['ref' => $ref]);
        $product = $findProduct->fetch();

        if (!$product) {
            $createProduct->execute([
                'sku' => count($rows) === 1 ? $first['code'] : null,
                'name' => erpBaseName($first['name'], $first['size_word']),
                'description' => $description,
                'price' => $price,
                'weight' => $weights ? max($weights) : ERP_DEFAULT_WEIGHT_KG,
                'h' => ERP_DEFAULT_DIMS_CM[0], 'w' => ERP_DEFAULT_DIMS_CM[1], 'l' => ERP_DEFAULT_DIMS_CM[2],
                'ref' => $ref,
            ]);
            $productId = (int)$pdo->lastInsertId();
            $stats['products_created']++;
            $product = ['id' => $productId, 'description' => $description, 'weight_kg' => $weights ? max($weights) : ERP_DEFAULT_WEIGHT_KG];
        } else {
            $productId = (int)$product['id'];
            $updateProduct->execute([
                'price' => $price,
                // descrição do SG Master manda; se estiver vazia lá, mantém a do site
                'description' => $description !== '' ? $description : (string)$product['description'],
                'weight' => $weights ? max($weights) : (float)$product['weight_kg'],
                'id' => $productId,
            ]);
        }
        $seenProductIds[$productId] = true;

        $usedSizes = [];
        foreach ($rows as $r) {
            if (isset($usedSizes[$r['size']])) {
                $stats['warnings'][] = "Referência $ref: tamanho {$r['size']} repetido (código {$r['code']}). Confira o campo Tamanho no SG Master.";
                $r['size'] = $r['size'] . ' (' . $r['code'] . ')';
            }
            $usedSizes[$r['size']] = true;

            $findVariant->execute(['sku' => $r['code']]);
            $variant = $findVariant->fetch();
            if (!$variant) {
                $createVariant->execute(['pid' => $productId, 'size' => $r['size'], 'sku' => $r['code']]);
                $variantId = (int)$pdo->lastInsertId();
                $stats['variants_created']++;
            } else {
                $variantId = (int)$variant['id'];
                $activateVariant->execute(['id' => $variantId]);
                $stats['variants_updated']++;
                if ((int)$variant['product_id'] !== $productId) {
                    $seenProductIds[(int)$variant['product_id']] = true; // reagrupado à mão no admin: respeita
                }
            }
            $seenVariantIds[$variantId] = true;

            $reserved = erpReservedQty($pdo, $variantId, $locationId);
            if ($reserved > 0) {
                $stats['reserved'][$r['code']] = $reserved;
            }
            $upsertStock->execute(['v' => $variantId, 'l' => $locationId, 'q' => max(0, $r['quantity'] - $reserved)]);
        }
    }

    // 2) Itens que saíram do "Disponibilizar online": esconde a variação e zera o estoque online
    $managedVariants = $pdo->query(
        'SELECT v.id FROM product_variants v JOIN products p ON p.id = v.product_id
         WHERE p.erp_managed = 1 AND v.active = 1'
    )->fetchAll(PDO::FETCH_COLUMN);
    $hide = $pdo->prepare('UPDATE product_variants SET active = 0 WHERE id = :id');
    $zero = $pdo->prepare('UPDATE stock_by_store SET quantity = 0 WHERE variant_id = :id AND location_id = :l');
    foreach ($managedVariants as $vid) {
        if (!isset($seenVariantIds[(int)$vid])) {
            $hide->execute(['id' => $vid]);
            $zero->execute(['id' => $vid, 'l' => $locationId]);
            $stats['variants_hidden']++;
        }
    }

    // 3) Totais e visibilidade: aparece no site só com foto, categoria e tamanho ativo
    $managed = $pdo->query('SELECT id, name, image_url, category FROM products WHERE erp_managed = 1')->fetchAll();
    $setActive = $pdo->prepare(
        'UPDATE products p
         SET p.stock = (SELECT COALESCE(SUM(s.quantity), 0) FROM product_variants v JOIN stock_by_store s ON s.variant_id = v.id
                        WHERE v.product_id = p.id AND v.active = 1),
             p.active = :active
         WHERE p.id = :id'
    );
    foreach ($managed as $p) {
        $online = isset($seenProductIds[(int)$p['id']]);
        $ready = trim((string)$p['image_url']) !== '' && trim((string)$p['category']) !== '';
        if ($online && !$ready) {
            $stats['waiting_photo'][] = $p['name'];
        }
        $setActive->execute(['active' => ($online && $ready) ? 1 : 0, 'id' => $p['id']]);
    }

    return $stats;
}

/** Grava a sincronização no histórico (erp_sync_log). */
function erpLogSync(PDO $pdo, string $source, array $stats): void
{
    $notes = $stats['warnings'];
    if ($stats['reserved']) {
        $notes[] = 'Reservado por pedidos do site ainda não lançados: ' . json_encode($stats['reserved']);
    }
    $pdo->prepare(
        'INSERT INTO erp_sync_log (received_at, source, items_received, products_created, variants_created, variants_updated, variants_hidden, waiting_photo, notes)
         VALUES (NOW(), :src, :ir, :pc, :vc, :vu, :vh, :wp, :n)'
    )->execute([
        'src' => $source,
        'ir' => $stats['items_received'],
        'pc' => $stats['products_created'],
        'vc' => $stats['variants_created'],
        'vu' => $stats['variants_updated'],
        'vh' => $stats['variants_hidden'],
        'wp' => count($stats['waiting_photo']),
        'n' => $notes ? implode("\n", $notes) : null,
    ]);
    // mantém só os últimos 500 registros
    $pdo->exec('DELETE FROM erp_sync_log WHERE id < (SELECT id FROM (SELECT id FROM erp_sync_log ORDER BY id DESC LIMIT 1 OFFSET 499) t)');
}
