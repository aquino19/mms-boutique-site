<?php
$infoPage = [
    'slug' => 'faq',
    'title' => 'Perguntas frequentes',
    'eyebrow' => 'Ajuda',
    'intro' => 'As dúvidas mais comuns sobre compras, pagamento, entrega e trocas. Não achou a sua? Chame a gente no WhatsApp.',
];
require __DIR__ . '/includes/info_page.php';

$faq = [
    'Compras' => [
        ['Como faço para comprar pelo site?',
         'Escolha a peça, clique em <b>Comprar</b>, selecione a cor e o tamanho e vá para o carrinho. No checkout, informe os seus dados, escolha entre entrega ou retirada na loja e finalize. Você será levada para a página segura do Mercado Pago para pagar.'],
        ['Como sei qual é o meu tamanho?',
         'Cada peça tem um caimento diferente. Na dúvida, chame a gente no WhatsApp com as suas medidas de busto, cintura e quadril que indicamos o tamanho ideal.'],
        ['A peça está esgotada. Vai voltar?',
         'Muitas peças são repostas. Chame no WhatsApp que avisamos quando chegar ou indicamos uma peça parecida.'],
    ],
    'Pagamento' => [
        ['Quais formas de pagamento vocês aceitam?',
         'O pagamento é feito pelo Mercado Pago, com as formas que ele oferecer no momento, como PIX, cartão de crédito e boleto.'],
        ['Posso parcelar?',
         'Sim, em até 10x sem juros no cartão de crédito. As opções de parcelamento aparecem na página de pagamento do Mercado Pago.'],
        ['É seguro pagar no site?',
         'Sim. O site usa conexão criptografada e o pagamento acontece na página do Mercado Pago. Os dados do seu cartão não passam pelo nosso servidor.'],
        ['Como uso um cupom de desconto?',
         'No carrinho, digite o código no campo <b>Cupom de desconto</b> e clique em OK. O desconto aparece no resumo antes de finalizar. É permitido um cupom por pedido.'],
    ],
    'Entrega e retirada' => [
        ['Vocês entregam onde?',
         'Entregamos em Natal e região. No checkout, informe o CEP para ver as opções, o valor e o prazo do frete.'],
        ['Quanto tempo demora para chegar?',
         'O prazo aparece no checkout junto com o valor do frete e começa a contar depois que o pagamento é aprovado. O site funciona 24 horas, e os pedidos são separados e enviados de segunda a sábado, das 09h às 20h.'],
        ['Posso retirar na loja?',
         'Sim, sem custo. Escolha <b>Retirar na loja</b> no checkout e aguarde o nosso aviso de que o pedido está separado. A retirada é na Loja 02 · SuperFácil Atacado (Av. Dr. Ruy Pereira dos Santos, 258, Olho D\'Água, São Gonçalo do Amarante), de segunda a sábado, das 09h às 20h. Leve um documento com foto e o número do pedido.'],
        ['Como acompanho o meu pedido?',
         'Chame a gente no WhatsApp com o número do pedido que informamos a situação e, se houver, o código de rastreio.'],
    ],
    'Trocas e devoluções' => [
        ['Posso trocar se não servir?',
         'Sim. Peças sem uso e com etiqueta podem ser trocadas em até 30 dias. Veja todas as regras em <a href="trocas-e-devolucoes.php">Trocas e devoluções</a>.'],
        ['Comprei pelo site e me arrependi. E agora?',
         'Você tem 7 dias a partir do recebimento para desistir da compra, com devolução total do valor, inclusive do frete.'],
        ['A peça veio com defeito. O que faço?',
         'Mande fotos pelo WhatsApp em até 90 dias. Resolvemos com troca, reembolso ou abatimento, e o frete fica por nossa conta.'],
    ],
];
?>

<?php foreach ($faq as $group => $items): ?>
  <h2><?= mmsH($group) ?></h2>
  <div class="faq-list">
    <?php foreach ($items as [$q, $a]): ?>
      <details class="faq-item">
        <summary><?= mmsH($q) ?></summary>
        <div class="faq-answer"><?= $a /* HTML fixo deste arquivo */ ?></div>
      </details>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>

<div class="info-callout" style="margin-top:40px;">
  <strong>Ainda com dúvida?</strong>
  <p>Fale com a gente pelo WhatsApp <a href="<?= mmsH($co['whats_link']) ?>" target="_blank" rel="noopener"><?= mmsH($co['whats']) ?></a>, de segunda a sábado, das 09h às 20h.</p>
</div>

<?php require __DIR__ . '/includes/info_page_end.php'; ?>
