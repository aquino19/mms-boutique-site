# Arquitetura completa — Loja virtual por cliente

Documento de referência consolidando todas as decisões de infraestrutura, banco de dados, pagamento e automação para o modelo de lojas que serão criadas para diferentes clientes.

---

## 1. Visão geral do modelo

Cada cliente recebe:
- Um site de e-commerce próprio (landing page + catálogo + checkout)
- Um painel de admin próprio (login isolado, banco de dados isolado)
- Um gateway de pagamento configurável (Mercado Pago ou PagSeguro), recebendo direto na conta do cliente
- Estoque sincronizável com o sistema que o cliente já usa (ERP, planilha, etc.)

A infraestrutura compartilhada (hospedagem, VPS) é sua; os dados e o dinheiro de cada loja pertencem ao respectivo cliente.

---

## 2. Hospedagem de sites

**Plano escolhido: Hostinger Unlimited**

| Recurso | Capacidade |
|---|---|
| Sites | Ilimitados |
| Bancos de dados MySQL | 150 |
| Tamanho por banco | 3 GB |
| RAM | 3 GB |
| PHP Workers | 60 |
| Backup | Diário |
| E-mail por site | Ilimitado (grátis por 1 ano, depois renova) |
| Domínio grátis | 1 (demais domínios são pagos à parte) |

**Por que este plano:** o limite de 150 bancos de dados é o que garante espaço para crescer até dezenas de clientes, cada um com seu banco isolado, sem precisar migrar de plano no curto prazo. Se ultrapassar esse número no futuro, o próximo degrau é o plano Cloud Startup (300 bancos).

**Domínio:** recomenda-se registrar cada domínio na conta do próprio cliente (não centralizado na sua conta), para evitar dependência ou disputa caso o contrato termine.

---

## 3. VPS — automação e atendimento

**Uso:** n8n (agentes de IA) + EasyPanel (gerenciador) + Chatwoot (atendimento), rodando juntos na mesma máquina.

**Plano recomendado: KVM 4** (4 vCPU / 16 GB RAM / 200 GB NVMe) — dá folga para rodar as três aplicações simultaneamente sem gargalo de memória. KVM 2 (2 vCPU / 8 GB) é uma alternativa mais barata para começar, com upgrade posterior se necessário.

Essa VPS é separada da hospedagem de sites — sites dos clientes não rodam aqui, só a camada de automação/atendimento.

---

## 4. Tecnologia do site

- **Frontend das lojas:** HTML/CSS/JS estático — mais leve, mais rápido, sem necessidade de servidor de aplicação.
- **Checkout:** redireciona para checkout hospedado do gateway escolhido (Mercado Pago ou PagSeguro) — pagamento em si não é processado no seu servidor, reduzindo responsabilidade sobre dados de cartão.
- **Admin de produtos e pedidos:** PHP + MySQL, rodando no mesmo plano de hospedagem (recurso já incluso, sem custo extra).

---

## 5. Banco de dados — 1 por loja

Cada loja tem seu próprio banco MySQL isolado, com 5 tabelas:

### `admin_users`
Login do lojista.
| Campo | Tipo |
|---|---|
| id | INT, PK |
| email | VARCHAR(255), UNIQUE |
| password_hash | VARCHAR(255) |
| created_at | TIMESTAMP |
| last_login_at | TIMESTAMP, NULL |

### `store_settings`
Configuração geral, incluindo o gateway de pagamento escolhido.
| Campo | Tipo |
|---|---|
| id | INT, PK (sempre 1) |
| store_name | VARCHAR(255) |
| contact_email | VARCHAR(255) |
| gateway_provider | ENUM: mercadopago / pagseguro |
| gateway_access_token | TEXT (criptografado) |
| gateway_public_key | VARCHAR(255), NULL |
| updated_at | TIMESTAMP |

### `products`
| Campo | Tipo |
|---|---|
| id | INT, PK |
| name | VARCHAR(255) |
| description | TEXT, NULL |
| price | DECIMAL(10,2) |
| stock | INT — **campo de destino da integração com o sistema de estoque do cliente** |
| image_url | VARCHAR(500), NULL |
| active | BOOLEAN |
| created_at / updated_at | TIMESTAMP |

### `orders`
| Campo | Tipo |
|---|---|
| id | INT, PK — usado como referência externa no gateway |
| customer_name / email / phone | VARCHAR |
| total_amount | DECIMAL(10,2) |
| status | ENUM: pending / paid / failed / refunded |
| gateway_charge_id | VARCHAR(255) — indexado, usado no processamento de webhook |
| created_at / updated_at | TIMESTAMP |

### `order_items`
| Campo | Tipo |
|---|---|
| id | INT, PK |
| order_id | FK → orders.id |
| product_id | FK → products.id |
| quantity | INT |
| unit_price | DECIMAL(10,2) — cópia do preço no momento da venda, não referencia products.price diretamente |

**Relacionamentos:** `orders` 1→N `order_items` N→1 `products`. `admin_users` e `store_settings` não têm FK com as demais — são configuração/acesso.

---

## 6. Pagamento — adaptadores configuráveis

O gateway é uma configuração por loja (`store_settings.gateway_provider`), não uma decisão fixa no código. Checkout e lógica de pedidos conversam sempre com a mesma interface interna:

```
createCharge(order, storeConfig) -> { charge_id, checkout_url, status }
getChargeStatus(charge_id, storeConfig) -> { status }
handleWebhook(requestBody, storeConfig) -> { order_id, status }
```

**Mercado Pago:** valores em decimal, endpoint `checkout/preferences`, status mapeado de `approved/pending/rejected/refunded`.
**PagSeguro:** valores em **centavos inteiros**, endpoint `orders`, status mapeado de `PAID/WAITING/DECLINED/CANCELED`.

Em ambos os casos, o webhook nunca é confiado cegamente — o adaptador sempre reconfirma com `getChargeStatus` antes de marcar o pedido como pago.

Documento técnico detalhado dos dois adaptadores: ver `spec-adaptadores-gateway-pagamento.md`.

---

## 7. Integração de estoque

**Mecanismo:** n8n atua como a camada de integração entre o sistema de estoque do cliente e o banco da loja, escrevendo periodicamente em `products.stock`.

| Sistema do cliente | Abordagem no n8n |
|---|---|
| ERP com API (Bling, Tiny, Omie) | Workflow agendado consultando a API do ERP e atualizando `products.stock` |
| Planilha (Excel/Google Sheets) | Workflow agendado lendo a planilha e sincronizando os valores |
| Sistema próprio/customizado | Depende de o sistema ter API ou banco acessível — avaliar caso a caso |

**Direção do fluxo:** por padrão, assume-se que o sistema do cliente continua sendo a fonte da verdade (ele já opera por lá), e o n8n *puxa* os dados para o site — não o contrário.

**Status atual:** aguardando retorno do cliente sobre qual sistema ele usa hoje, para configurar o conector específico.

---

## 8. Checklist para lançamento de uma loja nova

1. Contratar/configurar hospedagem: domínio, banco MySQL, SSL
2. Gerar o código da loja (site, admin, checkout) a partir deste desenho
3. Popular banco com produtos reais e criar o primeiro admin_user
4. Obter credenciais de produção do gateway escolhido pelo cliente
5. Testar o fluxo de pagamento de ponta a ponta (compra real de teste)
6. Configurar e-mail do domínio
7. Resolver páginas legais obrigatórias (política de privacidade, termos, trocas — exigências do CDC e da LGPD)
8. Subir/configurar VPS (n8n + EasyPanel + Chatwoot), se a loja já for usar automação/atendimento
9. Configurar integração de estoque no n8n, assim que o sistema do cliente for identificado
10. Testar em dispositivo real (mobile) e revisar performance

---

## 9. Documentos relacionados

- `spec-adaptadores-gateway-pagamento.md` — especificação técnica completa dos adaptadores Mercado Pago e PagSeguro
- `schema-banco-loja.md` — detalhamento do schema de banco de dados por loja
