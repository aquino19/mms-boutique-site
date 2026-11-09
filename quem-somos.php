<?php
$infoPage = [
    'slug' => 'quem-somos',
    'title' => 'Quem somos',
    'eyebrow' => 'Nossa história',
    'intro' => 'Moda para toda a família, escolhida peça por peça em Natal, RN.',
];
require __DIR__ . '/includes/info_page.php';
?>

<p class="info-lead">A MM'S Boutique nasceu em 2022 com uma ideia simples: fazer cada cliente sair do provador se sentindo na sua melhor versão.</p>

<p><mark class="todo">[OPCIONAL: 2 ou 3 frases da cliente sobre como a loja começou, quem está por trás e o que a inspira. Ex.: “Começamos com uma arara e um sonho…”]</mark></p>

<p>Hoje trabalhamos com moda feminina, plus size, infantil, masculina e acessórios: dos vestidos para festas e formaturas às peças do dia a dia. Cada coleção é escolhida com cuidado para valorizar todos os corpos, do P ao 54, da adolescente à mulher madura.</p>

<h2>Use sua melhor versão</h2>
<p>É o que está escrito no nosso provador, e é o que guia o atendimento. Ajudamos você a encontrar o tamanho certo, a combinar as peças e a montar o look para a ocasião, seja na loja, seja pelo WhatsApp.</p>

<h2>Nossas lojas</h2>
<div class="stores info-stores">
  <div class="store-card">
    <span class="eyebrow">Loja 01</span>
    <h3>Igapó</h3>
    <p>R. Santa Luzia, 1550, Igapó, Natal/RN</p>
    <p class="muted">Seg a Sáb · 09h às 18h</p>
    <a href="https://api.whatsapp.com/send?phone=5584988013059" target="_blank" rel="noopener">Chamar no WhatsApp →</a>
  </div>
  <div class="store-card">
    <span class="eyebrow">Loja 02 · Site e retirada</span>
    <h3>SuperFácil Atacado</h3>
    <p>Av. Dr. Ruy Pereira dos Santos, 258, Olho D'Água, São Gonçalo do Amarante/RN</p>
    <p class="muted">Seg a Sáb · 09h às 20h</p>
    <a href="<?= mmsH($co['whats_link']) ?>" target="_blank" rel="noopener">Chamar no WhatsApp →</a>
  </div>
</div>

<h2>Compre do seu jeito</h2>
<ul>
  <li><b>Pelo site:</b> com entrega em Natal e região ou retirada na Loja 02.</li>
  <li><b>Pelo WhatsApp:</b> mande a foto da peça que você viu e a gente separa.</li>
  <li><b>No Instagram:</b> acompanhe as novidades e os bastidores em <a href="https://www.instagram.com/usemmsboutique/" target="_blank" rel="noopener">@usemmsboutique</a>.</li>
</ul>

<p class="info-cta-row">
  <a class="btn solid" href="categoria.php?cat=<?= urlencode('Coleção Nova') ?>">Ver coleção nova</a>
  <a class="btn" href="<?= mmsH($co['whats_link']) ?>" target="_blank" rel="noopener">Falar com a gente</a>
</p>

<?php require __DIR__ . '/includes/info_page_end.php'; ?>
