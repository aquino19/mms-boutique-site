<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/ui.php';

$pdo = getDbConnection();
$catalog = getCatalogProducts($pdo);
$featuredProducts = array_slice($catalog, 0, 4);
$dressProducts = array_slice(getCategoryProducts($pdo, 'Vestidos'), 0, 3);

// Depoimentos: coloque true quando tiver relatos reais de clientes e edite os textos abaixo.
$showTestimonials = false;
$testimonials = [
    ['"Texto do depoimento real da cliente."', 'Nome, Cidade/RN'],
];

$catCircles = [
    'Vestidos'       => 'linear-gradient(160deg,var(--rose),var(--rose-deep))',
    'Conjuntos'      => 'linear-gradient(160deg,var(--emerald),var(--emerald-deep))',
    'Coleção Nova'   => 'linear-gradient(160deg,var(--lime),var(--lime-deep))',
    'Infantil'       => 'linear-gradient(160deg,var(--gold-soft),var(--gold))',
    'Moda Masculina' => 'linear-gradient(160deg,#8a97a8,#4d5866)',
    'Plus Size'      => 'linear-gradient(160deg,var(--rose-deep),#4A2E24)',
    'Acessórios'     => 'linear-gradient(160deg,var(--choc),#5a4132)',
];

$pageTitle = '';
$fullBleed = true;
require __DIR__ . '/includes/site_head.php';
?>

<header class="hero">
  <div class="hero-copy">
    <span class="eyebrow">Coleção Fim de Ano</span>
    <h1>Elegância que<br><i>se veste</i> de você.</h1>
    <p>Peças fluidas, cores terrosas e caimento pensado pra quem quer se sentir bonita todos os dias — do provador direto pra sua tela.</p>
    <div class="hero-actions">
      <a class="btn solid" style="border-color:var(--cream)" href="categoria.php?cat=<?= urlencode('Coleção Nova') ?>">Ver coleção</a>
      <a class="btn ghost-light" href="#lojas">Nossas lojas</a>
    </div>
  </div>
  <div class="hero-curtain">
    <div class="drape"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
    <div class="center-mark"><div class="ring"><img src="assets/logo-mms.png" alt="MM'S Boutique"></div></div>
  </div>
</header>

<div class="value-strip">
  <div>Entrega toda Natal</div>
  <div>2 lojas físicas</div>
  <div>Retirada no local</div>
  <div>Atendimento via WhatsApp</div>
</div>

<section class="wrap" style="padding:72px 32px 48px;">
  <div style="margin-bottom:40px;">
    <span class="eyebrow">Navegue por</span>
    <h2 class="h2-lg" style="margin-top:8px;">Explore o universo MM'S</h2>
  </div>
  <div class="cat-icons" style="padding:0 0 8px;">
    <?php foreach (mmsCategories() as $name => $c): ?>
      <a href="categoria.php?cat=<?= urlencode($name) ?>"><span class="cat-circle" style="background:<?= $catCircles[$name] ?? 'var(--choc)' ?>;"></span><em><?= mmsH($c['label']) ?></em></a>
    <?php endforeach; ?>
  </div>
</section>

<section class="wrap" style="padding:72px 32px;">
  <div class="section-head">
    <h2>Mais aguardados</h2>
    <a href="catalog.php">Ver tudo</a>
  </div>
  <?php if ($featuredProducts): ?>
    <div class="grid-4">
      <?php foreach ($featuredProducts as $p) renderProductCard($p, true); ?>
    </div>
  <?php else: ?>
    <p style="color:rgba(46,31,24,.6);">Em breve, novos produtos por aqui.</p>
  <?php endif; ?>
</section>

<section class="editorial" id="filosofia" style="padding:0;">
  <div class="editorial-copy">
    <span class="eyebrow">Nossa filosofia</span>
    <h2>Roupa que abraça quem você <i style="color:var(--rose)">é</i>.</h2>
    <p>Na MM'S, cada peça é escolhida com cuidado para que toda mulher se sinta confiante. Do P ao 54, da adolescente à mulher madura — moda que inclui, que valoriza, que dura.</p>
    <a class="btn solid" style="align-self:flex-start;" href="catalog.php">Conhecer a loja</a>
  </div>
  <div class="editorial-visual"><img src="assets/filosofia.jpg" alt="Cliente no provador da MM'S com o lema Use Sua Melhor Versão" loading="lazy"></div>
</section>

<?php if ($showTestimonials): ?>
<section class="wrap" style="padding:90px 32px;">
  <div style="margin-bottom:40px;">
    <span class="eyebrow">Depoimentos</span>
    <h2 class="h2-lg" style="margin-top:8px;">Elas amam a MM'S</h2>
  </div>
  <div class="testi-grid">
    <?php foreach ($testimonials as $t): ?>
      <div class="testi-card"><p><?= mmsH($t[0]) ?></p><span>— <?= mmsH($t[1]) ?></span></div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($dressProducts): ?>
<section class="block alt">
  <div class="wrap">
    <div class="section-head">
      <h2>Vestidos em destaque</h2>
      <a href="categoria.php?cat=Vestidos">Ver todos</a>
    </div>
    <div class="grid-3">
      <?php foreach ($dressProducts as $p) renderProductCard($p); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="reels">
  <div class="wrap">
    <div class="section-head">
      <h2>Bastidores</h2>
      <a href="https://www.instagram.com/usemmsboutique/" target="_blank" rel="noopener">Seguir no Instagram</a>
    </div>
    <div class="reel-scroll">
      <a class="reel-card has-photo" href="https://www.instagram.com/usemmsboutique/" target="_blank" rel="noopener"><img src="assets/reel1.jpg" alt="" loading="lazy"><div class="play">▶</div></a>
      <a class="reel-card has-photo" href="https://www.instagram.com/usemmsboutique/" target="_blank" rel="noopener"><img src="assets/reel2.jpg" alt="" loading="lazy"><div class="play">▶</div></a>
      <a class="reel-card has-photo" href="https://www.instagram.com/usemmsboutique/" target="_blank" rel="noopener"><img src="assets/reel3.jpg" alt="" loading="lazy"><div class="play">▶</div></a>
      <a class="reel-card has-photo" href="https://www.instagram.com/usemmsboutique/" target="_blank" rel="noopener"><img src="assets/reel4.jpg" alt="" loading="lazy"><div class="play">▶</div><p>Reposição mais aguardada ✨</p></a>
      <a class="reel-card" href="https://www.instagram.com/usemmsboutique/" target="_blank" rel="noopener"><div class="play">▶</div><p>Provando os looks da coleção nova</p></a>
    </div>
  </div>
</section>

<section class="block" id="lojas">
  <div class="wrap">
    <div class="section-head"><h2>Nossas lojas</h2></div>
    <div class="stores">
      <div class="store-card">
        <span class="eyebrow">Loja 01</span>
        <h3>Igapó</h3>
        <p>R. Santa Luzia, 1550, Igapó — Natal/RN</p>
        <p class="muted">Seg a Sáb · 09h às 18h</p>
        <a href="https://api.whatsapp.com/send?phone=5584988013059" target="_blank" rel="noopener">Chamar no WhatsApp →</a>
      </div>
      <div class="store-card">
        <span class="eyebrow">Loja 02</span>
        <h3>SuperFácil Atacado</h3>
        <p>São Gonçalo do Amarante/RN</p>
        <p class="muted">Seg a Sáb · 09h às 20h</p>
        <a href="https://wa.me/5584999001179" target="_blank" rel="noopener">Chamar no WhatsApp →</a>
      </div>
    </div>
  </div>
</section>

<?php renderProductModal(array_merge($featuredProducts, $dressProducts)); ?>
<?php require __DIR__ . '/includes/site_foot.php'; ?>
