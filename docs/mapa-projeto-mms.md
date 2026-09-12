# Mapa do projeto — Loja MM'S Boutique (loja-piloto do modelo replicável)

Documento de status consolidado. Cobre o que já está pronto, o que está em andamento, o que falta, e uma estimativa de conclusão por área.

**Estimativa geral de conclusão: ~45–50%**

Importante: isso é maior peso em decisões e arquitetura (que já estão fechadas) do que em execução final — várias frentes dependem de terceiros (cliente, Sgbr, você contratando hospedagem) antes de eu poder avançar mais.

---

## 1. Arquitetura e decisões técnicas — 100%

✅ Concluído
- Modelo de negócio definido: 1 site + 1 admin + 1 banco isolado por loja de cliente
- Hospedagem escolhida: Hostinger Unlimited (sites ilimitados, 150 bancos MySQL)
- VPS escolhida: KVM 2 ou 4, rodando n8n + EasyPanel + Chatwoot
- Stack definida: frontend estático/PHP, admin PHP+MySQL, checkout redirecionando pro gateway
- Estratégia de domínio definida (registrar na conta do cliente)

Nada pendente aqui — é a base que orientou todo o resto.

---

## 2. Banco de dados — 80%

✅ Concluído
- Schema completo desenhado e documentado (`schema-banco-loja.md`)
- SQL de criação testado e funcional (`mms-boutique-schema.sql`)
- Tabelas: `admin_users`, `store_settings`, `products`, `orders`, `order_items`

🔲 Falta
- Adicionar campo `sku` em `products` (necessário pra integração de estoque)
- Criar tabela `stock_by_store` (necessária pra somar o estoque das duas lojas físicas sem uma sobrescrever a outra)

---

## 3. Admin (painel do lojista) — 85%

✅ Concluído, testado de ponta a ponta
- Login com sessão e senha com hash
- CRUD de produtos (criar, editar, ativar/desativar)
- Listagem de pedidos
- Configuração de gateway de pagamento (Mercado Pago/PagSeguro)

🔲 Falta
- Criptografar `gateway_access_token` antes de salvar (hoje está em texto puro — anotado no código, mas não implementado)
- Script pronto pra criar o primeiro `admin_user` em produção (hoje foi feito manualmente nos testes)

---

## 4. Site público (loja) — funcionalidade — 75%

✅ Concluído, testado de ponta a ponta
- Catálogo dinâmico puxando do banco
- Carrinho (adicionar, remover, calcular total)
- Checkout (grava pedido + itens no banco corretamente)
- Página de confirmação do pedido

🔲 Falta
- Ligação real com o gateway de pagamento (hoje o pedido fica "pendente" e não redireciona de verdade — ver item 6)
- Produtos reais (hoje só há dados de teste — aguardando fotos/preços/estoque reais)

---

## 5. Design do site público — ~20%, em andamento em paralelo

✅ Concluído
- Homepage com identidade visual definida (recebida e revisada)
- Alinhamento de que o design de catálogo/carrinho/checkout deve seguir a mesma identidade

🔲 Falta
- Vocês estão desenhando 4 páginas em outro chat (catálogo, carrinho, checkout, confirmação) — combinei de receber os HTMLs prontos aqui pra só plugar a lógica, sem recriar visual

---

## 6. Pagamento (gateway real) — 15%

✅ Concluído
- Especificação técnica completa dos adaptadores Mercado Pago e PagSeguro (`spec-adaptadores-gateway-pagamento.md`)
- Tela de configuração no admin já salva qual gateway e token usar

🔲 Falta
- Implementar de fato o código dos adaptadores (`createCharge`, `getChargeStatus`, `handleWebhook`)
- Conectar o checkout a esse código (hoje ele só cria o pedido como pendente, sem redirecionar pro gateway de verdade)
- Credenciais reais do cliente (conta Mercado Pago ou PagSeguro configurada)

---

## 7. Integração de estoque — 20%

✅ Concluído
- Decisão de arquitetura: script local em cada loja física, empurrando dados via HTTPS pro n8n (evita abrir portas na rede do cliente)
- Decisão de negócio: estoque da loja virtual = soma das duas lojas físicas
- Sistema identificado: Firebird, tabela `T_STOCK`, banco local (`C:/sgbr/master/bd`)

🔲 Falta
- Usuário Firebird read-only (combinado que o Sgbr passa quando a estrutura estiver pronta)
- Nomes exatos das colunas de `T_STOCK` (qual é o código/SKU, qual é a quantidade)
- Confirmar se o SKU do Firebird bate com o que será usado no cadastro de produtos do site
- Escrever o script de envio (roda em cada um dos dois computadores das lojas)
- Montar o workflow no n8n que recebe, soma, e atualiza `products.stock`
- **Depende da VPS estar no ar** (item 8) pra ter onde o n8n rodar

---

## 8. Infraestrutura (hospedagem, VPS, domínio) — 0%

🔲 Falta tudo
- Contratar o plano Hostinger Unlimited
- Contratar e configurar a VPS (KVM)
- Registrar o domínio
- Subir n8n + EasyPanel + Chatwoot na VPS
- Criar o banco de dados real na hospedagem e importar o schema

Isso é puramente uma questão de você contratar — o código já está pronto pra ser hospedado assim que isso existir.

---

## 9. Homepage — 80%

✅ Concluído
- Layout, identidade visual, catálogo de vitrine, depoimentos, footer

🔲 Falta
- Corrigir o chat de IA (hoje chama a API da Anthropic direto do navegador — não funciona fora do ambiente Claude, precisa de proxy PHP)
- Menu mobile (hoje não existe alternativa ao menu desktop em telas pequenas)
- CNPJ real no rodapé (hoje está com placeholder)
- Trocar produtos de exemplo (hardcoded) pelo catálogo dinâmico

---

## 10. Páginas legais — 5%

✅ Concluído
- Links já existem no rodapé (Política de Troca, Política de Privacidade)

🔲 Falta
- Escrever o conteúdo real dessas páginas (política de privacidade, termos de uso, política de trocas) — exigência do CDC e da LGPD antes de operar de verdade

---

## Prioridades sugeridas para destravar o máximo de progresso

1. **Contratar a hospedagem** (item 8) — libera testar tudo em ambiente real, não só localmente
2. **Receber os HTMLs de design** do outro chat (item 5) — permite fechar o site público de vez
3. **Implementar os adaptadores de pagamento** (item 6) — maior bloqueador funcional pra loja processar venda de verdade
4. **Fechar as respostas do Sgbr** (item 7) — permite finalizar a integração de estoque em paralelo

Esses quatro itens, uma vez resolvidos, provavelmente levam o projeto de ~48% para acima de 80%.
