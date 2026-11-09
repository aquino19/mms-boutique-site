# Arquitetura completa — Loja virtual por cliente

Documento de referência do modelo de loja virtual própria, com a implementação real da primeira loja: **MM'S Boutique** (Natal/RN).
Atualizado em 09/11/2026 a partir do código em produção (`public_html` da Hostinger, versão de 09/10/2026) e do repositório `aquino19/mms-boutique-site`.

> A versão anterior deste documento descrevia o desenho inicial: site estático, uma tabela de estoque simples (`products.stock`) e uma loja só. O que está no ar é diferente: site em PHP, variações por cor e tamanho, estoque por unidade física, cupons e frete pelo Melhor Envio. Este documento descreve o que existe hoje.

---

## 1. Visão geral do modelo

Cada cliente recebe:
- Um site de e-commerce próprio: home, categorias, carrinho, checkout e páginas institucionais e legais.
- Um painel de admin próprio, com login isolado e banco de dados isolado.
- Um gateway de pagamento configurável (Mercado Pago ou PagSeguro), recebendo direto na conta do cliente.
- Frete calculado pelo Melhor Envio, na conta do cliente.
- Estoque por unidade física, sincronizável com o sistema que o cliente já usa (ERP, banco local, planilha).

A infraestrutura compartilhada (hospedagem, VPS) é do integrador. Os dados, o dinheiro e as contas de cada loja (domínio, gateway, Melhor Envio) ficam no CNPJ do cliente.

---

## 2. Hospedagem dos sites

**Plano: Hostinger Unlimited.** Sites ilimitados, 150 bancos MySQL de até 3 GB, 3 GB de RAM, 60 PHP workers, backup diário, e-mail por domínio (grátis no primeiro ano). Próximo degrau, se passar de 150 bancos: Cloud Startup (300 bancos).

**Domínio:** registrado no CNPJ do próprio cliente, para evitar dependência ou disputa se o contrato terminar. MM'S: `mmsboutique.com.br`, ativo, com SSL.

---

## 3. VPS — automação e atendimento

**Uso:** n8n (integrações e agentes) + EasyPanel (gerenciador) + Chatwoot (atendimento, ainda não instalado).

**Plano em uso: KVM 2** (2 vCPU, 8 GB de RAM). Subir para KVM 4 (4 vCPU, 16 GB) quando o Chatwoot entrar ou houver várias lojas.

A VPS é separada da hospedagem: os sites não rodam nela, só a camada de automação.
MM'S: n8n em `mms-n8n-n8n.acm0m5.easypanel.host`, com o workflow de estoque importado e ainda não ativado.

---

## 4. Tecnologia do site

- **Site público e admin:** PHP 8 + MySQL, sem framework e sem plataforma pronta (Nuvemshop e Shopify foram descartadas). Páginas renderizadas no servidor; JavaScript só para o modal de produto, o menu mobile, a cotação de frete e o chat.
- **Visual:** CSS próprio (`assets/site.css`), direção editorial, paleta da marca (verde, verde-limão, dourado, rose gold e creme) e fontes Fraunces e Manrope. O design do Figma Make foi aplicado como re-skin do PHP, sem migrar para React, para preservar banco, admin e integrações.
- **Checkout:** coleta dados e entrega e redireciona para a **página hospedada do gateway**. Nenhum dado de cartão passa pelo servidor.
- **Carrinho:** guardado na sessão PHP (cookie de sessão), sem conta de cliente.

### Estrutura de arquivos (`public_html`)

```
index.php                  home (hero, categorias, destaques, filosofia, bastidores, lojas)
categoria.php              página de categoria (?cat=), filtro por cor e ordenação
catalog.php                catálogo geral / busca
cart.php, cart_*.php       carrinho, cupom
checkout.php               dados, entrega/retirada, frete, criação do pedido e redirecionamento
checkout_success.php       retorno do gateway
shipping_quote.php         cotação de frete (AJAX → Melhor Envio)
chat_proxy.php             proxy do chat de IA (a chave fica só no servidor)
quem-somos.php, faq.php, trocas-e-devolucoes.php, privacidade.php, termos-de-uso.php
includes/
  config.php               conexão e funções de dados (NÃO versionado)
  ui.php                   categorias, cards de produto, modal de variação
  site_head.php / site_foot.php / chat_widget.php
  info_page.php / info_page_end.php   estrutura das páginas institucionais
  gateway.php + gateways/  adaptadores Mercado Pago e PagSeguro
  shipping/melhorenvio.php adaptador de frete
admin/                     painel (/admin/login.php); admin/includes/config.php NÃO versionado
api/stock_sync.php         endpoint chamado pelo n8n
webhooks/                  retorno de pagamento (mercadopago.php, pagseguro.php)
assets/                    CSS, logo (logo-mms.png), reels, fotos de produtos (assets/produtos/)
```

---

## 5. Banco de dados — 1 por loja

Um banco MySQL isolado por loja (MM'S: prefixo `u340554535_`). Tabelas usadas pelo código em produção:

| Tabela | Papel | Campos principais |
|---|---|---|
| `admin_users` | Login do lojista | email, password_hash |
| `store_settings` | Configuração (linha única, id = 1) | store_name, gateway_provider, gateway_access_token, melhor_envio_token |
| `products` | Produto "pai" | sku, name, description, category, price, image_url, weight_kg, height_cm, width_cm, length_cm, active, stock (soma calculada) |
| `product_variants` | Cor + tamanho de um produto | product_id, color_name, color_hex, size, **sku** (chave da sincronização), active |
| `store_locations` | Unidades físicas | name, **code** (ex.: `igapo`, `sao_goncalo`), cep, active |
| `stock_by_store` | Estoque por variação e unidade | variant_id, location_id, quantity (único por variant_id + location_id) |
| `coupons` / `coupon_products` | Cupons de desconto | code, discount_percent, applies_to, valid_from, valid_until, active / produtos participantes |
| `orders` | Pedido | dados do cliente, total_amount, coupon_code, discount_amount, shipping_cost, shipping_service, status, fulfillment_method (pickup / delivery), endereço |
| `order_items` | Itens do pedido | product_id, variant_id, variant_color, variant_size, quantity, unit_price (cópia do preço na venda), fulfillment_location_id |

**Regras importantes**
- `products.stock` é sempre a **soma** de `stock_by_store` das variações do produto. Nunca editar direto: usar `recalcProductStock()`.
- Um produto só é vendável com pelo menos uma variação ativa com estoque.
- Peso e dimensões são obrigatórios, porque o frete depende deles.
- O pedido grava em qual unidade cada item será separado (`fulfillment_location_id`), escolhida pelo estoque disponível (`allocateFulfillment()`).

> Os arquivos `database/*.sql` do repositório estão atrás do banco real (por exemplo, `stock_by_store` ainda aparece com `product_id`). Antes de criar a próxima loja, exportar a estrutura atual pelo phpMyAdmin (só estrutura, sem dados) e substituir o schema do repositório.

---

## 6. Pagamento — adaptadores configuráveis

O gateway é configuração da loja (`store_settings.gateway_provider`), não decisão fixa no código. Checkout e webhooks usam a mesma interface (`includes/gateway.php`):

```
gatewayCreateCharge(order, storeConfig)      -> { charge_id, checkout_url, status }
gatewayGetChargeStatus(chargeId, storeConfig) -> { status }
gatewayHandleWebhook(provider, body, storeConfig) -> { order_id, status }
```

- **Mercado Pago:** valores em decimal, Checkout Pro (`checkout/preferences`), status `approved / pending / rejected / refunded`.
- **PagSeguro:** valores em centavos, endpoint `orders`, status `PAID / WAITING / DECLINED / CANCELED`.
- O webhook nunca é confiado cegamente: o adaptador reconfirma o status com `getChargeStatus` antes de marcar o pedido como pago.
- Webhooks: `https://<domínio>/webhooks/mercadopago.php` e `.../pagseguro.php`.

Detalhes: `spec-adaptadores-gateway-pagamento.md`.
MM'S: Mercado Pago provável, aguardando confirmação da cliente. Conta no CNPJ, token e webhook pendentes.

---

## 7. Frete — Melhor Envio

- Token pessoal da conta de produção do cliente, colado em Configurações no admin (`store_settings.melhor_envio_token`). Permissão mínima: cálculo de frete.
- Origem: CEP da unidade que despacha (`store_locations.cep`). MM'S: São Gonçalo, 59290-000.
- O checkout chama `shipping_quote.php`, que cota com peso e medidas dos produtos do carrinho.
- Alternativa sem custo: retirada na loja (`fulfillment_method = pickup`).

---

## 8. Integração de estoque

**Mecanismo:** o n8n lê o sistema do cliente e chama `POST /api/stock_sync.php` com:

```json
{ "location_code": "sao_goncalo", "items": [ { "sku": "1045-PRE-M", "quantity": 3 } ] }
```

- Autenticação pelo cabeçalho `X-Sync-Secret`, que precisa ser igual à constante `STOCK_SYNC_SECRET` do servidor. No repositório, o valor é `TROCAR_AQUI`; o real fica só no servidor e no nó do n8n.
- A chave de casamento é o **SKU da variação**. Os SKUs do site precisam ser iguais aos códigos do sistema da loja.
- O endpoint grava em `stock_by_store` da unidade informada e recalcula `products.stock`. SKUs desconhecidos são devolvidos na resposta.
- **Direção:** o sistema da loja é a fonte da verdade, e o n8n puxa os dados para o site.

| Sistema do cliente | Abordagem no n8n |
|---|---|
| ERP com API (Bling, Tiny, Omie) | Workflow agendado consultando a API |
| Banco local (ex.: Firebird) | Script de leitura num PC da loja enviando ao n8n, ou acesso somente leitura ao banco |
| Planilha | Workflow agendado lendo a planilha |

**MM'S:** o sistema da loja é o Sgbr (Firebird, tabela `T_STOCK`). Só a unidade **São Gonçalo** vende online; a Igapó é só física. Pendências: usuário somente leitura e colunas do `T_STOCK` (com o Sgbr), confirmar se o código do produto é igual ao SKU, e definir quem dá baixa no sistema da loja das vendas feitas pelo site (senão o sync "devolve" estoque já vendido).

---

## 9. Chat de IA

`includes/chat_widget.php` (botão flutuante) → `chat_proxy.php` → API da Anthropic. A chave fica só em `chat_proxy.php` no servidor. Hoje está com placeholder, ou seja, desligado na prática. Falta decidir se fica e como se relaciona com o Chatwoot. Se for ativado, a política de privacidade precisa manter o trecho sobre o processamento das mensagens nos EUA.

---

## 10. Páginas institucionais e legais

`quem-somos.php`, `faq.php`, `trocas-e-devolucoes.php`, `privacidade.php` e `termos-de-uso.php` compartilham a estrutura de `includes/info_page.php`. Os dados da empresa (razão social, CNPJ, endereço, contato) ficam em `mmsCompany()`, exigidos pelo Decreto 7.962/2013.
Trechos destacados em amarelo (`<mark class="todo">`) são decisões pendentes da loja e precisam ser resolvidos, e o texto revisado por advogado, antes de publicar.

---

## 11. Regras de deploy e segurança

- **Nunca** versionar `includes/config.php` nem `admin/includes/config.php` (estão no `.gitignore`), nem segredos reais.
- Ao subir arquivos pelo Gerenciador de Arquivos, **enviar arquivo por arquivo ou mesclar pastas**. Substituir `includes/` ou `admin/includes/` apaga o `config.php` e derruba o site com erro 500.
- O gerenciador da Hostinger renomeia pastas que já existem (`admin.6079`, `includes.5691` etc.). Essas cópias ficam públicas e devem ser apagadas.
- O admin fica em `/admin/login.php`; `/admin/` sem arquivo dá 403.
- O repositório deve ficar **privado**.
- Mudanças de CSS: aumentar o `?v=` em `site_head.php` para evitar cache.

---

## 12. Checklist de lançamento (status MM'S em 09/11/2026)

| # | Item | Status |
|---|---|---|
| 1 | Hospedagem, banco, SSL e domínio | ✅ |
| 2 | Código da loja (site, admin, checkout) | ✅ no ar |
| 3 | Produtos reais com fotos, SKUs, peso e medidas | 🟡 6 produtos de vitrine prontos para importar, com dados fictícios |
| 4 | Unidades: Igapó só física, CEP de São Gonçalo | 🟡 |
| 5 | Token do Melhor Envio | ⏳ |
| 6 | Gateway: conta no CNPJ, token e webhook | ⏳ aguardando cliente |
| 7 | Compra de teste de ponta a ponta | ⏳ depende de 5 e 6 |
| 8 | Páginas legais e institucionais | 🟡 rascunho pronto; faltam decisões da loja e revisão jurídica |
| 9 | E-mail do domínio | ⏳ (rodapé usa contato@mmsboutique.com) |
| 10 | VPS com n8n | ✅ no ar; workflow a ativar |
| 11 | Integração de estoque | ⏳ depende do Sgbr |
| 12 | Teste em celular real e performance | ⏳ |

---

## 13. Documentos relacionados

- `spec-adaptadores-gateway-pagamento.md` — adaptadores Mercado Pago e PagSeguro
- `schema-banco-loja.md` — schema detalhado (desatualizado: ver nota da seção 5)
- `README.md` do repositório — estrutura e regras rápidas
