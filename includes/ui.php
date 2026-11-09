<?php
/**
 * Helpers de interface compartilhados (cabeçalho, cards de produto, modal).
 * Não mexe em banco de dados nem em regras de negócio.
 */

/** Máximo de parcelas sem juros exibidas nos cards. Coloque 0 para esconder. */
const MMS_MAX_INSTALLMENTS = 10; // confirmar no Mercado Pago quando a conta existir

/** Chat de IA no canto da tela. Desligado até haver decisão de uso (chave em chat_proxy.php). */
const MMS_CHAT_ENABLED = false;

/** Categorias da loja: nome no banco => rótulo, cor de destaque e texto da faixa da página. */
function mmsCategories(): array
{
    return [
        'Vestidos'       => ['label' => 'Vestidos',      'accent' => 'var(--rose-deep)',    'subtitle' => 'Do casual ao elegante — peças que abraçam cada corpo e cada ocasião.'],
        'Conjuntos'      => ['label' => 'Conjuntos',     'accent' => 'var(--emerald-deep)', 'subtitle' => 'Looks completos, coordenados com cuidado para você brilhar sem esforço.'],
        'Coleção Nova'   => ['label' => 'Coleção Nova',  'accent' => 'var(--choc)',         'subtitle' => 'Os lançamentos mais recentes da MM\'S — peças exclusivas recém chegadas.'],
        'Infantil'       => ['label' => 'Infantil',      'accent' => '#B8735E',             'subtitle' => 'Moda para as pequenas fashionistas. Conforto, qualidade e muito estilo.'],
        'Moda Masculina' => ['label' => 'Moda Masc.',    'accent' => '#2E1F18',             'subtitle' => 'Peças masculinas com estilo e qualidade para o homem moderno.'],
        'Plus Size'      => ['label' => 'Plus Size',     'accent' => 'var(--emerald-deep)', 'subtitle' => 'Moda que celebra todos os corpos. Do 44 ao 54 com elegância e conforto.'],
        'Acessórios'     => ['label' => 'Acessórios',    'accent' => 'var(--gold)',         'subtitle' => 'Os detalhes que completam o look. Bolsas, cintos, joias e muito mais.'],
    ];
}

function mmsMoney(float $v): string
{
    return 'R$ ' . number_format($v, 2, ',', '.');
}

function mmsInstallmentText(float $price): string
{
    $n = MMS_MAX_INSTALLMENTS;
    if ($n < 2 || $price < 40) return '';
    return 'ou ' . $n . 'x de ' . mmsMoney($price / $n) . ' sem juros';
}

function mmsProductImage(array $p): string
{
    return $p['image_url'] ?: 'https://placehold.co/450x600/EFE6D1/2E1F18?text=MM%27S';
}

/** Selo automático: "Esgotado" sem estoque; "Novo" para peças cadastradas nos últimos 30 dias. */
function mmsProductTag(array $p, int $totalStock): string
{
    if ($totalStock <= 0) return 'Esgotado';
    $created = isset($p['created_at']) ? strtotime((string)$p['created_at']) : false;
    if ($created && $created > time() - 30 * 86400) return 'Novo';
    return '';
}

function mmsH(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Card de produto. $small = versão menor (grade de 4 colunas na home). */
function renderProductCard(array $p, bool $small = false): void
{
    $totalStock = array_sum(array_column($p['variants'], 'total_stock'));
    $tag = mmsProductTag($p, (int)$totalStock);
    $inst = mmsInstallmentText((float)$p['price']);
    $disabled = $totalStock <= 0 ? 'disabled' : '';
    ?>
    <div class="look-card<?= $small ? ' small' : '' ?>">
      <div class="look-photo">
        <img src="<?= mmsH(mmsProductImage($p)) ?>" alt="<?= mmsH($p['name']) ?>" loading="lazy">
        <?php if ($tag): ?><span class="tag"><?= mmsH($tag) ?></span><?php endif; ?>
      </div>
      <h3><?= mmsH($p['name']) ?></h3>
      <div class="price"><?= mmsMoney((float)$p['price']) ?></div>
      <?php if ($inst): ?><div class="installment"><?= mmsH($inst) ?></div><?php endif; ?>
      <div class="color-dots">
        <?php $seen = []; foreach ($p['variants'] as $v):
          if (empty($v['color_hex']) || isset($seen[$v['color_name']])) continue;
          $seen[$v['color_name']] = true; ?>
          <span style="background:<?= mmsH($v['color_hex']) ?>" title="<?= mmsH($v['color_name']) ?>"></span>
        <?php endforeach; ?>
      </div>
      <div class="card-actions">
        <button type="button" class="buy-btn" <?= $disabled ?> onclick="openVariantModal(<?= (int)$p['id'] ?>,'buy')"><?= $totalStock <= 0 ? 'Indisponível' : 'Comprar' ?></button>
        <button type="button" class="bag-btn" <?= $disabled ?> aria-label="Adicionar ao carrinho" onclick="openVariantModal(<?= (int)$p['id'] ?>,'cart')">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        </button>
      </div>
    </div>
    <?php
}

/** Modal de cor/tamanho + JS. Chamar UMA vez por página, passando todos os produtos exibidos nela. */
function renderProductModal(array $products): void
{
    $data = array_map(function ($p) {
        $stock = array_sum(array_column($p['variants'], 'total_stock'));
        return [
            'id' => (int)$p['id'],
            'name' => $p['name'],
            'price' => (float)$p['price'],
            'priceFmt' => mmsMoney((float)$p['price']),
            'installment' => mmsInstallmentText((float)$p['price']),
            'description' => (string)($p['description'] ?? ''),
            'category' => (string)($p['category'] ?? ''),
            'image' => mmsProductImage($p),
            'tag' => mmsProductTag($p, (int)$stock),
            'variants' => array_map(fn($v) => [
                'id' => (int)$v['id'], 'color' => $v['color_name'], 'hex' => $v['color_hex'],
                'size' => $v['size'], 'stock' => (int)$v['total_stock'],
            ], $p['variants']),
        ];
    }, $products);
    $return = $_SERVER['REQUEST_URI'] ?? 'catalog.php';
    ?>
    <div class="variant-modal-overlay" id="variantModalOverlay" onclick="if(event.target===this) closeVariantModal()">
      <div class="variant-modal" role="dialog" aria-modal="true">
        <div class="vm-image"><img id="vm-img" src="" alt=""><span class="tag" id="vm-tag" style="display:none"></span></div>
        <div class="vm-body">
          <button type="button" class="close-btn" onclick="closeVariantModal()" aria-label="Fechar">×</button>
          <span class="eyebrow" id="vm-cat"></span>
          <h3 id="vm-name"></h3>
          <div>
            <div class="vm-price" id="vm-price"></div>
            <div class="vm-inst" id="vm-inst"></div>
          </div>
          <p class="vm-desc" id="vm-desc"></p>
          <hr>
          <div id="vm-color-block">
            <label>Cor: <span id="vm-color-selected"></span></label>
            <div class="swatch-row" id="vm-colors"></div>
          </div>
          <div id="vm-size-block">
            <label>Tamanho</label>
            <div class="size-row" id="vm-sizes"></div>
          </div>
          <form method="post" action="cart_add.php" id="vm-form">
            <input type="hidden" name="variant_id" id="vm-variant-id">
            <input type="hidden" name="return" id="vm-return" value="cart.php">
            <button type="submit" class="add-btn" id="vm-add-btn" disabled>Selecione cor e tamanho</button>
          </form>
          <p class="variant-hint" id="vm-hint" style="display:none;">Essa combinação está fora de estoque.</p>
        </div>
      </div>
    </div>
    <script>
      const PRODUCTS = <?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
      const CURRENT_URL = <?= json_encode($return, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
      let currentProduct = null, selectedColor = null, selectedSize = null, modalMode = 'buy';

      function openVariantModal(productId, mode) {
        currentProduct = PRODUCTS.find(p => p.id === productId);
        if (!currentProduct) return;
        modalMode = mode || 'buy';
        selectedColor = null; selectedSize = null;
        document.getElementById('vm-return').value = modalMode === 'buy' ? 'cart.php' : CURRENT_URL;
        document.getElementById('vm-img').src = currentProduct.image;
        document.getElementById('vm-img').alt = currentProduct.name;
        const tag = document.getElementById('vm-tag');
        tag.textContent = currentProduct.tag; tag.style.display = currentProduct.tag ? 'block' : 'none';
        document.getElementById('vm-cat').textContent = currentProduct.category;
        document.getElementById('vm-name').textContent = currentProduct.name;
        document.getElementById('vm-price').textContent = currentProduct.priceFmt;
        document.getElementById('vm-inst').textContent = currentProduct.installment;
        const desc = document.getElementById('vm-desc');
        desc.textContent = currentProduct.description; desc.style.display = currentProduct.description ? 'block' : 'none';

        const colors = [...new Map(currentProduct.variants.filter(v => v.hex).map(v => [v.color, v])).values()];
        const colorBlock = document.getElementById('vm-color-block');
        const colorsEl = document.getElementById('vm-colors');
        colorsEl.innerHTML = '';
        if (colors.length === 0) { colorBlock.style.display = 'none'; }
        else {
          colorBlock.style.display = 'block';
          colors.forEach(c => {
            const btn = document.createElement('button');
            btn.type = 'button'; btn.className = 'swatch'; btn.style.background = c.hex; btn.title = c.color;
            btn.onclick = () => { selectedColor = c.color; renderSizes(); updateAddButton(); highlightColors(); };
            colorsEl.appendChild(btn);
          });
        }
        renderSizes(); updateAddButton();
        document.getElementById('vm-color-selected').textContent = '';
        document.getElementById('variantModalOverlay').classList.add('open');
      }
      function highlightColors() {
        document.querySelectorAll('#vm-colors .swatch').forEach(el => el.classList.toggle('selected', el.title === selectedColor));
        document.getElementById('vm-color-selected').textContent = selectedColor || '';
      }
      function renderSizes() {
        const sizesEl = document.getElementById('vm-sizes');
        sizesEl.innerHTML = '';
        const sizes = [...new Set(currentProduct.variants.filter(v => !selectedColor || v.color === selectedColor).map(v => v.size))];
        sizes.forEach(s => {
          const btn = document.createElement('button');
          btn.type = 'button'; btn.className = 'size-btn'; btn.textContent = s;
          btn.onclick = () => { selectedSize = s; updateAddButton(); renderSizes(); };
          if (s === selectedSize) btn.classList.add('selected');
          sizesEl.appendChild(btn);
        });
      }
      function findVariant() {
        if (!currentProduct) return null;
        return currentProduct.variants.find(v => (!v.color || v.color === selectedColor) && v.size === selectedSize) || null;
      }
      function updateAddButton() {
        const btn = document.getElementById('vm-add-btn');
        const hint = document.getElementById('vm-hint');
        const hasColors = currentProduct.variants.some(v => v.hex);
        if ((hasColors && !selectedColor) || !selectedSize) {
          btn.disabled = true; btn.textContent = 'Selecione cor e tamanho'; hint.style.display = 'none'; return;
        }
        const variant = findVariant();
        if (!variant || variant.stock <= 0) {
          btn.disabled = true; btn.textContent = 'Fora de estoque'; hint.style.display = 'block'; return;
        }
        document.getElementById('vm-variant-id').value = variant.id;
        btn.disabled = false; btn.textContent = modalMode === 'buy' ? 'Comprar agora' : 'Adicionar ao carrinho'; hint.style.display = 'none';
      }
      function closeVariantModal() { document.getElementById('variantModalOverlay').classList.remove('open'); }
      document.addEventListener('keydown', e => { if (e.key === 'Escape') closeVariantModal(); });
    </script>
    <?php
}
