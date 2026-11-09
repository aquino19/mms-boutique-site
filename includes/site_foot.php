<?php
$fullBleed = $fullBleed ?? false;
$flashAdded = !empty($_SESSION['flash_added']);
unset($_SESSION['flash_added']);
if (!$fullBleed) echo "</main>\n";
?>

<footer>
  <div class="wrap">
    <div class="footer-grid">
      <div class="footer-brand">
        <div class="brand">
          <img src="assets/logo-mms.png" alt="" class="brand-img">
          <span class="logo">MM<em>'</em>S <span class="logo-sub">boutique</span></span>
        </div>
        <p>Moda infantil, adulto, masculina e plus size, feita pra quem gosta de se vestir bem em qualquer ocasião. Desde 2022 em Natal, RN.</p>
        <div class="newsletter-row">
          <input type="email" placeholder="Seu e-mail">
          <button type="button">Assinar →</button>
        </div>
      </div>
      <div>
        <h4>Loja</h4>
        <?php foreach (mmsCategories() as $name => $c): ?>
          <a href="categoria.php?cat=<?= urlencode($name) ?>"><?= mmsH($name) ?></a>
        <?php endforeach; ?>
      </div>
      <div>
        <h4>Sobre Nós</h4>
        <a href="quem-somos.php">Quem Somos</a>
        <a href="faq.php">Perguntas Frequentes</a>
        <a href="trocas-e-devolucoes.php">Trocas e Devoluções</a>
        <a href="cart.php">Meu carrinho</a>
      </div>
      <div>
        <h4>Suporte e Vendas</h4>
        <a href="https://api.whatsapp.com/send?phone=5584988013059" target="_blank" rel="noopener">WhatsApp Loja Igapó</a>
        <a href="https://wa.me/5584999001179" target="_blank" rel="noopener">WhatsApp Loja SuperFácil</a>
        <a href="mailto:contato@mmsboutique.com">contato@mmsboutique.com</a>
        <a href="https://www.instagram.com/usemmsboutique/" target="_blank" rel="noopener">@usemmsboutique</a>
      </div>
      <div>
        <h4>Visite</h4>
        <p>Loja 01 · R. Santa Luzia, 1550, Igapó</p>
        <p class="hours">Seg a Sáb · 09h às 18h</p>
        <p>Loja 02 · SuperFácil Atacado, SGA</p>
        <p class="hours">Seg a Sáb · 09h às 20h</p>
        <p>Natal, Rio Grande do Norte</p>
      </div>
    </div>
    <div class="bottom-row">
      <span>© <?= date('Y') ?> MM'S Boutique. Todos os direitos reservados. · <a href="privacidade.php">Privacidade</a> · <a href="termos-de-uso.php">Termos de uso</a></span>
      <span>CNPJ: 45.913.840/0001-83 · Entrega toda Natal e região · Atendimento via WhatsApp</span>
    </div>
  </div>
</footer>

<?php if ($flashAdded): ?>
  <div class="toast" id="toast">✓ Produto adicionado ao carrinho · <a href="cart.php">Ver carrinho</a></div>
  <script>setTimeout(function(){var t=document.getElementById('toast'); if(t) t.classList.add('hide');}, 3800);</script>
<?php endif; ?>

<script>
  (function(){
    var d = document.getElementById('mobileDrawer');
    document.getElementById('hamburgerBtn').addEventListener('click', function(){ d.classList.add('open'); });
    document.getElementById('closeDrawerBtn').addEventListener('click', function(){ d.classList.remove('open'); });
    d.addEventListener('click', function(e){ if (e.target === d) d.classList.remove('open'); });
  })();
</script>

<?php require __DIR__ . '/chat_widget.php'; ?>
</body>
</html>
