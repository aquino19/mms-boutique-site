# MM'S Boutique — site + admin

Loja virtual da MM'S Boutique (Natal/RN): PHP + MySQL na Hostinger.
Este repositório espelha o conteúdo de `public_html` em produção.

## Estrutura

```
index.php, categoria.php, catalog.php   → páginas públicas
cart*.php, checkout.php, checkout_success.php, shipping_quote.php
chat_proxy.php                          → proxy do chat de IA (chave fica no servidor)
includes/                               → cabeçalho, rodapé, cards (ui.php), gateways, frete
  config.php                            → NÃO versionado (dados do banco)
admin/                                  → painel do lojista (/admin/login.php)
  includes/config.php                   → NÃO versionado
api/stock_sync.php                      → endpoint chamado pelo n8n (segredo: TROCAR_AQUI no repo)
webhooks/                               → retorno do Mercado Pago e PagSeguro
assets/                                 → CSS, logo e imagens
database/                               → schema e migrations
docs/                                   → arquitetura e especificações
```

## Regras

- Nunca versionar `config.php` nem segredos reais. No servidor, os valores reais ficam só nos arquivos de lá.
- Ao subir para a Hostinger, **mesclar** pastas, nunca substituir `includes/` ou `admin/includes/` (apaga o `config.php`).
- Pagamento sempre pela página hospedada do gateway; nenhum dado de cartão passa pelo servidor.
