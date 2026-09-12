# Especificação: Adaptadores de Gateway de Pagamento

Documento de referência para implementar o suporte a **Mercado Pago** e **PagSeguro** de forma configurável por loja. Cada loja escolhe seu gateway e credencial no admin; o checkout chama sempre a mesma interface interna, que delega para o adaptador correto.

---

## 1. Interface comum (contrato interno)

Todo adaptador deve implementar as mesmas três funções, independente do provedor por trás:

```
createCharge(order, storeConfig) -> { charge_id, checkout_url, status }
getChargeStatus(charge_id, storeConfig) -> { status }
handleWebhook(requestBody, storeConfig) -> { order_id, status }
```

- `order`: objeto interno da loja (itens, valor total, dados do comprador, ID do pedido)
- `storeConfig`: `{ gateway_provider, gateway_access_token }` vindo do banco daquela loja
- `status`: sempre normalizado internamente para um destes valores: `pending`, `paid`, `failed`, `refunded`

Isso garante que a página de checkout e a lógica de pedidos nunca precisem saber qual gateway está por trás — só conversam com essa interface.

---

## 2. Configuração salva por loja

| Campo | Tipo | Exemplo |
|---|---|---|
| `gateway_provider` | enum | `"mercadopago"` \| `"pagseguro"` |
| `gateway_access_token` | string (criptografado no banco) | Access Token / Token de API do cliente |
| `gateway_public_key` | string (opcional, só Mercado Pago) | Public Key, usada se for renderizar formulário de cartão no próprio site |

**Importante:** essas credenciais pertencem à conta do **cliente dono da loja**, não à sua. O dinheiro cai direto na conta dele.

---

## 3. Adaptador — Mercado Pago

**Autenticação:** Bearer token (`gateway_access_token`) no header `Authorization`.

### Criar cobrança
- Endpoint: `POST https://api.mercadopago.com/checkout/preferences`
- Campos principais enviados:
```json
{
  "items": [
    { "title": "Nome do produto", "quantity": 1, "unit_price": 99.90 }
  ],
  "payer": { "email": "cliente@email.com" },
  "external_reference": "ID_DO_PEDIDO_INTERNO",
  "back_urls": {
    "success": "https://loja.com/pedido/sucesso",
    "failure": "https://loja.com/pedido/falha"
  },
  "notification_url": "https://loja.com/webhooks/mercadopago"
}
```
- Resposta relevante: `id` (charge_id) e `init_point` (checkout_url — para onde redirecionar o comprador)

### Checar status
- Endpoint: `GET https://api.mercadopago.com/v1/payments/{payment_id}`
- Campo relevante: `status` → mapear:
  - `approved` → `paid`
  - `pending`, `in_process` → `pending`
  - `rejected` → `failed`
  - `refunded` → `refunded`

### Webhook
- Mercado Pago envia um POST simples avisando que *algo* mudou, sem o valor:
```json
{ "type": "payment", "data": { "id": "PAYMENT_ID" } }
```
- Ao receber, o adaptador deve chamar `getChargeStatus` com esse `id` para confirmar o status real antes de atualizar o pedido (nunca confiar cegamente no webhook).
- `external_reference` enviado na criação retorna dentro dos dados do pagamento — é como o adaptador sabe a qual pedido interno aquilo pertence.

---

## 4. Adaptador — PagSeguro (PagBank)

**Autenticação:** Bearer token (`gateway_access_token`) no header `Authorization`.

### Criar cobrança
- Endpoint: `POST https://api.pagseguro.com/orders`
- Campos principais enviados:
```json
{
  "reference_id": "ID_DO_PEDIDO_INTERNO",
  "customer": { "email": "cliente@email.com" },
  "items": [
    { "name": "Nome do produto", "quantity": 1, "unit_amount": 9990 }
  ],
  "notification_urls": ["https://loja.com/webhooks/pagseguro"]
}
```
- **Atenção:** valores no PagSeguro são em **centavos** (inteiro), diferente do Mercado Pago que usa decimal.
- Resposta relevante: `id` (charge_id) e link de checkout dentro de `links` (rel: `PAY`)

### Checar status
- Endpoint: `GET https://api.pagseguro.com/orders/{order_id}`
- Campo relevante: `charges[0].status` → mapear:
  - `PAID` → `paid`
  - `WAITING`, `AUTHORIZED` → `pending`
  - `DECLINED` → `failed`
  - `CANCELED` → `refunded`

### Webhook
- PagSeguro envia o objeto do pedido/charge já atualizado direto no corpo do POST (mais completo que o do Mercado Pago), mas ainda assim recomenda-se confirmar com `getChargeStatus` antes de gravar, por segurança contra requests forjados.
- `reference_id` retorna no payload — é o identificador do pedido interno.

---

## 5. Diferenças a tratar no código (checklist)

- [ ] Conversão de valores: Mercado Pago = decimal (`99.90`), PagSeguro = centavos inteiros (`9990`)
- [ ] Duas rotas de webhook distintas: `/webhooks/mercadopago` e `/webhooks/pagseguro`, ambas convergindo para a mesma função interna `handleWebhook`
- [ ] Nunca confiar no corpo do webhook sozinho — sempre reconfirmar com `getChargeStatus` antes de marcar pedido como pago
- [ ] Armazenar `gateway_access_token` criptografado no banco, nunca em texto puro
- [ ] Validar que o token configurado pertence de fato ao provedor selecionado (evitar erro de configuração do lojista)

---

## 6. Observação sobre atualização das APIs

Endpoints, nomes de campos e formatos de webhook de gateways de pagamento mudam com alguma frequência. Antes de implementar em produção, confirme os detalhes exatos na documentação oficial de cada provedor (Mercado Pago Developers e PagBank/PagSeguro Developers), pois este documento é uma referência de arquitetura, não a fonte definitiva de campos de API.
