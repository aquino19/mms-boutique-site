# Schema do banco de dados — 1 por loja

Cada loja tem seu próprio banco MySQL isolado (conforme decidido: login e dados separados por cliente). Este é o schema completo, cobrindo produtos, pedidos, admin e configuração de pagamento.

---

## Tabela: admin_users

Login do lojista para acessar o painel de admin daquela loja.

| Campo | Tipo | Observação |
|---|---|---|
| id | INT, PK, AUTO_INCREMENT | |
| email | VARCHAR(255), UNIQUE | login |
| password_hash | VARCHAR(255) | nunca texto puro — usar bcrypt/argon2 |
| created_at | TIMESTAMP | |
| last_login_at | TIMESTAMP, NULL | útil para detectar contas abandonadas |

---

## Tabela: store_settings

Configuração geral da loja — 1 linha só, funciona como um "singleton" de config.

| Campo | Tipo | Observação |
|---|---|---|
| id | INT, PK | sempre 1 |
| store_name | VARCHAR(255) | nome exibido no site |
| contact_email | VARCHAR(255) | e-mail de contato/suporte da loja |
| gateway_provider | ENUM: mercadopago / pagseguro | qual adaptador usar |
| gateway_access_token | TEXT | criptografado antes de salvar |
| gateway_public_key | VARCHAR(255), NULL | só usado se Mercado Pago com form de cartão próprio |
| updated_at | TIMESTAMP | |

---

## Tabela: products

| Campo | Tipo | Observação |
|---|---|---|
| id | INT, PK, AUTO_INCREMENT | |
| name | VARCHAR(255) | |
| description | TEXT, NULL | |
| price | DECIMAL(10,2) | sempre em reais, decimal — conversão para centavos acontece só no adaptador PagSeguro |
| stock | INT | quantidade disponível |
| image_url | VARCHAR(500), NULL | caminho da imagem enviada |
| active | BOOLEAN, DEFAULT true | permite "pausar" produto sem apagar |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

---

## Tabela: orders

Um pedido = um checkout iniciado pelo cliente final.

| Campo | Tipo | Observação |
|---|---|---|
| id | INT, PK, AUTO_INCREMENT | usado como external_reference / reference_id no gateway |
| customer_name | VARCHAR(255) | |
| customer_email | VARCHAR(255) | |
| customer_phone | VARCHAR(30), NULL | |
| total_amount | DECIMAL(10,2) | soma dos itens |
| status | ENUM: pending / paid / failed / refunded | valor normalizado pelo adaptador |
| gateway_charge_id | VARCHAR(255), NULL | ID retornado pelo gateway |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | atualizado a cada mudança de status |

Índice recomendado: gateway_charge_id (busca rápida ao processar webhook).

---

## Tabela: order_items

Itens de um pedido — necessário porque um pedido pode ter vários produtos.

| Campo | Tipo | Observação |
|---|---|---|
| id | INT, PK, AUTO_INCREMENT | |
| order_id | INT, FK para orders.id | |
| product_id | INT, FK para products.id | |
| quantity | INT | |
| unit_price | DECIMAL(10,2) | preço no momento da compra — não referenciar products.price diretamente, pois o preço pode mudar depois |

---

## Relacionamentos

- orders 1 → N order_items (um pedido tem vários itens)
- order_items N → 1 products (cada item aponta para um produto)
- store_settings e admin_users não se relacionam com as outras tabelas via FK — são configuração/acesso, não dados transacionais

---

## Por que unit_price fica duplicado em order_items

Ponto importante de integridade de dados: se o lojista mudar o preço de um produto depois, os pedidos antigos não podem "mudar de valor" retroativamente. Por isso order_items.unit_price grava uma cópia do preço no momento da venda, independente do que products.price disser depois.

---

## Extensões possíveis (não necessárias agora, mas o schema já comporta)

- categories — se algum cliente quiser organizar produtos por categoria
- coupons — cupons de desconto
- webhook_logs — registro bruto de cada webhook recebido, útil para debug quando um pagamento "sumir"

Nenhuma dessas é necessária para o MVP — foram deixadas de fora para manter o schema inicial simples, mas encaixam sem redesenhar o que já existe.
