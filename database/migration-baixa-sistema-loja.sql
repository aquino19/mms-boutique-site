-- =====================================================================
-- Migração: separação de pedidos + baixa obrigatória no sistema da loja
-- Rodar UMA vez no phpMyAdmin (banco u340554535_mms_boutique),
-- ANTES de subir os arquivos novos do admin e do api/stock_sync.php.
--
-- fulfillment_status: andamento do pedido depois de pago
--   novo      -> pago, aguardando separação
--   separado  -> separado; pronto para retirada ou envio
--   enviado   -> postado / saiu para entrega (só pedidos com entrega)
--   entregue  -> entregue ou retirado pela cliente (concluído)
--
-- erp_registered_at: quando a venda foi lançada no sistema da loja (Sgbr).
--   Enquanto estiver vazio, a sincronização de estoque desconta as peças
--   deste pedido, para o site não "devolver" estoque já vendido.
-- =====================================================================

ALTER TABLE orders
  ADD COLUMN fulfillment_status ENUM('novo','separado','enviado','entregue') NOT NULL DEFAULT 'novo',
  ADD COLUMN fulfillment_updated_at DATETIME NULL,
  ADD COLUMN erp_registered_at DATETIME NULL,
  ADD COLUMN erp_registered_by INT NULL;

CREATE INDEX idx_orders_erp_pending ON orders (erp_registered_at, status);
