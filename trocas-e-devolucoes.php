<?php
$infoPage = [
    'slug' => 'trocas',
    'title' => 'Trocas e devoluções',
    'eyebrow' => 'Ajuda',
    'intro' => 'Quer trocar o tamanho, devolver uma peça ou recebeu algo com defeito? Aqui está tudo o que você precisa saber.',
    'updated' => '09/11/2026',
];
require __DIR__ . '/includes/info_page.php';
?>

<div class="info-callout">
  <strong>Resumo rápido</strong>
  <ul>
    <li>Comprou pelo site e desistiu? Você tem <b>7 dias corridos</b> a partir do recebimento para devolver, com reembolso total, inclusive do frete.</li>
    <li>Peça com defeito? Você tem <b>90 dias</b> para nos avisar.</li>
    <li>Quer outro tamanho ou cor? Fazemos a troca em até <mark class="todo">[A DEFINIR: 30 dias]</mark>.</li>
  </ul>
</div>

<h2>1. Direito de arrependimento (compras pelo site)</h2>
<p>Nas compras feitas pelo site, você pode desistir em até <b>7 dias corridos</b>, contados a partir do dia em que recebeu o pedido (ou retirou na loja), sem precisar explicar o motivo, conforme o artigo 49 do Código de Defesa do Consumidor.</p>
<p>Nesse caso, devolvemos <b>todo o valor pago</b>, incluindo o frete, e o envio de volta da peça não tem custo para você.</p>

<h2>2. Troca por tamanho, cor ou modelo</h2>
<p>Se a peça não serviu ou você preferiu outra cor, fazemos a troca em até <mark class="todo">[A DEFINIR: 30 dias]</mark> após o recebimento, desde que a peça:</p>
<ul>
  <li>não tenha sido usada nem lavada;</li>
  <li>esteja com a etiqueta original presa;</li>
  <li>não tenha cheiro de perfume, desodorante ou cigarro, nem manchas ou marcas de maquiagem.</li>
</ul>
<p>A troca é feita por outra peça disponível em estoque. Se a nova peça custar mais, você paga a diferença; se custar menos, <mark class="todo">[A DEFINIR: a diferença vira crédito na loja / é devolvida]</mark>.</p>
<p>Você pode trocar pessoalmente na <b>Loja 02 · SuperFácil Atacado</b> (Av. Dr. Ruy Pereira dos Santos, 258, Olho D'Água, São Gonçalo do Amarante), levando o número do pedido, ou pelo envio. <mark class="todo">[A DEFINIR: quem paga o frete da troca por tamanho/cor feita pelo envio]</mark></p>
<p class="muted">Esta troca é uma cortesia da loja e não substitui o direito de arrependimento do item 1.</p>

<h2>3. Peça com defeito</h2>
<p>Se a peça chegar com defeito de fabricação (costura aberta, furo, mancha, zíper quebrado etc.), avise em até <b>90 dias</b> após o recebimento. Defeitos visíveis devem ser avisados de preferência logo que a peça chegar, com fotos.</p>
<p>Depois de analisar a peça, que deve ocorrer em até 30 dias, você escolhe entre:</p>
<ul>
  <li>trocar por outra peça igual, em perfeito estado;</li>
  <li>receber o valor de volta;</li>
  <li>ficar com a peça e receber um abatimento proporcional no preço.</li>
</ul>
<p>Nas devoluções por defeito, o frete de envio e de reenvio fica por nossa conta.</p>

<h2>4. Como solicitar</h2>
<ol>
  <li>Fale com a gente pelo WhatsApp <a href="<?= mmsH($co['whats_link']) ?>" target="_blank" rel="noopener"><?= mmsH($co['whats']) ?></a> ou pelo e-mail <a href="mailto:<?= mmsH($co['email']) ?>"><?= mmsH($co['email']) ?></a>, informando o <b>número do pedido</b> e o motivo.</li>
  <li>Nós enviamos as instruções de devolução (código de postagem ou combinação de retirada na loja).</li>
  <li>Embale a peça de forma que ela chegue protegida, de preferência na embalagem original.</li>
  <li>Assim que recebermos e conferirmos a peça, fazemos a troca ou o reembolso.</li>
</ol>

<h2>5. Reembolso</h2>
<p>O reembolso é feito pelo mesmo meio de pagamento usado na compra:</p>
<ul>
  <li><b>Cartão de crédito:</b> pedimos o estorno ao Mercado Pago, e ele aparece na sua fatura em uma ou duas faturas seguintes, conforme o banco emissor.</li>
  <li><b>PIX ou boleto:</b> devolvemos o valor por PIX, na conta do titular da compra, em até <mark class="todo">[A DEFINIR: 7 dias úteis]</mark> após recebermos a peça.</li>
</ul>

<h2>6. Peças que não podem ser trocadas</h2>
<p>Por questões de higiene, <mark class="todo">[A DEFINIR: por exemplo, brincos e acessórios de uso íntimo]</mark> não são trocados por tamanho ou gosto. Essa regra <b>não</b> se aplica a defeitos nem ao direito de arrependimento das compras pelo site, que valem para qualquer produto.</p>

<?php require __DIR__ . '/includes/info_page_end.php'; ?>
