<?php
$infoPage = [
    'slug' => 'termos',
    'title' => 'Termos de uso',
    'eyebrow' => 'Regras da loja',
    'intro' => 'As condições para comprar no site da MM\'S Boutique. Ao fazer um pedido, você concorda com estes termos.',
    'updated' => '09/11/2026',
];
require __DIR__ . '/includes/info_page.php';
?>

<h2>1. Quem somos</h2>
<p>Este site é operado por <b><?= mmsH($co['razao']) ?></b>, nome fantasia <?= mmsH($co['fantasia']) ?>, CNPJ <?= mmsH($co['cnpj']) ?>, com endereço em <?= mmsH($co['endereco']) ?>. Contato: <a href="mailto:<?= mmsH($co['email']) ?>"><?= mmsH($co['email']) ?></a> e WhatsApp <a href="<?= mmsH($co['whats_link']) ?>" target="_blank" rel="noopener"><?= mmsH($co['whats']) ?></a>.</p>

<h2>2. Produtos, fotos e preços</h2>
<ul>
  <li>As fotos são das nossas peças, mas as cores podem variar um pouco conforme a iluminação e a tela do seu aparelho.</li>
  <li>Todas as peças estão sujeitas à disponibilidade em estoque, que é compartilhado com as nossas lojas físicas. Se uma peça esgotar depois do seu pedido, avisamos e devolvemos o valor integral.</li>
  <li>Os preços podem mudar sem aviso. Vale o preço exibido no momento em que o pedido é finalizado.</li>
  <li>Se houver um erro evidente de preço ou de cadastro, podemos cancelar o pedido, com devolução integral do valor pago.</li>
</ul>

<h2>3. Pedido e pagamento</h2>
<ul>
  <li>O pagamento é feito na página segura do Mercado Pago, pelas formas que ele oferecer no momento (como PIX, cartão de crédito e boleto). O parcelamento disponível é o informado na página de pagamento.</li>
  <li>O pedido é confirmado depois que o pagamento é aprovado. A confirmação aparece na tela ao final da compra, e a loja entra em contato pelo WhatsApp ou e-mail informados.</li>
  <li>Pedidos não pagos em até 3 dias podem ser cancelados, e as peças voltam a ficar disponíveis.</li>
  <li>Confira os seus dados antes de finalizar.</li>
</ul>

<h2>4. Cupons de desconto</h2>
<ul>
  <li>É permitido um cupom por pedido.</li>
  <li>Cada cupom tem regras próprias de validade e de produtos participantes, mostradas ao aplicar.</li>
  <li>Cupons não são trocados por dinheiro.</li>
</ul>

<h2>5. Entrega e retirada</h2>
<ul>
  <li>Entregamos em Natal e região. O valor e o prazo do frete são calculados pelo CEP no checkout, antes do pagamento.</li>
  <li>O site recebe pedidos 24 horas por dia. A separação e o envio acontecem no horário da Loja 02: de segunda a sábado, das 09h às 20h. Pedidos aprovados fora desse horário são processados no próximo período de funcionamento.</li>
  <li>O prazo de entrega começa a contar a partir da aprovação do pagamento.</li>
  <li>Se preferir, você pode retirar o pedido sem custo na <b>Loja 02 · SuperFácil Atacado</b>, em São Gonçalo do Amarante, depois de receber o aviso de que está pronto. Leve um documento com foto e o número do pedido.</li>
  <li>Confira o endereço com atenção. Se a entrega não acontecer por endereço errado ou ausência de quem recebe, o reenvio pode ter custo.</li>
</ul>

<h2>6. Trocas, devoluções e arrependimento</h2>
<p>As regras completas estão na página <a href="trocas-e-devolucoes.php">Trocas e devoluções</a>. Em resumo: você pode desistir da compra feita pelo site em até 7 dias após o recebimento, com reembolso total.</p>

<h2>7. Uso do site</h2>
<ul>
  <li>As fotos, textos, logotipo e marca MM'S pertencem à loja e não podem ser copiados para uso comercial sem autorização.</li>
  <li>Não é permitido usar o site para fins ilegais, tentar acessar áreas restritas ou atrapalhar o seu funcionamento.</li>
</ul>

<h2>8. Privacidade</h2>
<p>O tratamento dos seus dados está descrito na <a href="privacidade.php">Política de privacidade</a>.</p>

<h2>9. Lei aplicável</h2>
<p>Estes termos seguem a legislação brasileira, em especial o Código de Defesa do Consumidor e o Decreto nº 7.962/2013 (comércio eletrônico). Fica eleito o foro do domicílio do consumidor.</p>

<?php require __DIR__ . '/includes/info_page_end.php'; ?>
