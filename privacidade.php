<?php
$infoPage = [
    'slug' => 'privacidade',
    'title' => 'Política de privacidade',
    'eyebrow' => 'Seus dados',
    'intro' => 'Como coletamos, usamos e protegemos os seus dados pessoais, de acordo com a Lei Geral de Proteção de Dados (Lei nº 13.709/2018).',
    'updated' => '09/11/2026',
];
require __DIR__ . '/includes/info_page.php';
?>

<h2>1. Quem é responsável pelos seus dados</h2>
<p>O controlador dos dados pessoais tratados neste site é <b><?= mmsH($co['razao']) ?></b>, nome fantasia <?= mmsH($co['fantasia']) ?>, CNPJ <?= mmsH($co['cnpj']) ?>, com endereço em <?= mmsH($co['endereco']) ?>.</p>
<p>Para qualquer assunto sobre privacidade, fale com a gente pelo e-mail <a href="mailto:<?= mmsH($co['email']) ?>"><?= mmsH($co['email']) ?></a> ou pelo WhatsApp <a href="<?= mmsH($co['whats_link']) ?>" target="_blank" rel="noopener"><?= mmsH($co['whats']) ?></a>.</p>

<h2>2. Quais dados coletamos</h2>
<ul>
  <li><b>Dados do pedido:</b> nome, e-mail, telefone e, quando há entrega, endereço completo e CEP.</li>
  <li><b>Dados da compra:</b> produtos, valores, cupom usado, forma de entrega e situação do pagamento.</li>
  <li><b>Dados de navegação:</b> um cookie de sessão que guarda o seu carrinho enquanto você navega, além de informações técnicas registradas pelo servidor, como endereço IP, data e hora de acesso.</li>
  <li><b>Mensagens:</b> o que você nos envia pelo WhatsApp ou por e-mail.</li>
</ul>
<p><b>Não guardamos dados de cartão.</b> O pagamento é feito na página segura do Mercado Pago, e os dados do cartão vão direto para ele, sem passar pelos nossos servidores.</p>

<h2>3. Para que usamos os dados</h2>
<table class="info-table">
  <thead><tr><th>Finalidade</th><th>Base legal (LGPD, art. 7º)</th></tr></thead>
  <tbody>
    <tr><td>Processar o pedido, cobrar, entregar ou separar para retirada</td><td>Execução de contrato</td></tr>
    <tr><td>Falar com você sobre o pedido, trocas e devoluções</td><td>Execução de contrato</td></tr>
    <tr><td>Emitir nota fiscal e cumprir obrigações fiscais e contábeis</td><td>Cumprimento de obrigação legal</td></tr>
    <tr><td>Prevenir fraudes e manter o site seguro</td><td>Legítimo interesse</td></tr>
    <tr><td>Enviar novidades e promoções, somente se você autorizar</td><td>Consentimento</td></tr>
  </tbody>
</table>

<h2>4. Com quem compartilhamos</h2>
<p>Compartilhamos apenas o necessário para a sua compra acontecer:</p>
<ul>
  <li><b>Mercado Pago:</b> para processar o pagamento.</li>
  <li><b>Melhor Envio e transportadoras:</b> nome, endereço e telefone, para calcular o frete e entregar o pedido.</li>
  <li><b>Hostinger:</b> empresa que hospeda o site e o banco de dados.</li>
  <li><b>Contabilidade e órgãos públicos:</b> quando exigido por lei, como na emissão de nota fiscal.</li>
  <li><b>Google Fonts:</b> o site carrega fontes do Google, que recebe o seu endereço IP ao exibir a página.</li>
</ul>
<p>Não vendemos nem alugamos os seus dados.</p>

<h2>5. Cookies</h2>
<p>Usamos apenas o cookie de sessão necessário para o carrinho e o checkout funcionarem. Ele é apagado quando você fecha o navegador ou depois de um período sem uso. Não usamos cookies de publicidade nem de rastreamento.</p>

<h2>6. Por quanto tempo guardamos</h2>
<ul>
  <li><b>Dados de pedidos:</b> pelo prazo exigido pela legislação fiscal e de defesa do consumidor, em geral 5 anos após a compra.</li>
  <li><b>Mensagens de atendimento:</b> enquanto forem necessárias para resolver o seu atendimento.</li>
  <li><b>Lista de novidades:</b> até você pedir para sair.</li>
</ul>
<p>Depois desses prazos, os dados são apagados ou anonimizados.</p>

<h2>7. Seus direitos</h2>
<p>A qualquer momento, você pode pedir:</p>
<ul>
  <li>confirmação de que tratamos os seus dados e acesso a eles;</li>
  <li>correção de dados incompletos, errados ou desatualizados;</li>
  <li>anonimização, bloqueio ou eliminação de dados desnecessários;</li>
  <li>portabilidade dos dados para outro fornecedor;</li>
  <li>eliminação dos dados tratados com o seu consentimento, e a revogação desse consentimento;</li>
  <li>informação sobre com quem compartilhamos os seus dados.</li>
</ul>
<p>Para exercer esses direitos, use os contatos do item 1. Respondemos em até 15 dias. Você também pode reclamar à Autoridade Nacional de Proteção de Dados (ANPD).</p>

<h2>8. Segurança</h2>
<p>O site usa conexão criptografada (HTTPS), o acesso ao painel da loja é protegido por senha e os dados de pagamento ficam com o Mercado Pago. Nenhum sistema é totalmente imune a falhas; se acontecer um incidente que possa causar risco a você, vamos avisar.</p>

<h2>9. Alterações</h2>
<p>Esta política pode ser atualizada. A data da última versão fica no fim desta página.</p>

<?php require __DIR__ . '/includes/info_page_end.php'; ?>
