-- =====================================================================
-- Migração: catálogo vindo do SG Master (sistema da loja)
-- Rodar UMA vez no phpMyAdmin (banco u340554535_mms_boutique),
-- DEPOIS de migration-baixa-sistema-loja.sql e ANTES de subir
-- api/catalog_sync.php e os arquivos novos do admin.
--
-- products.erp_ref      chave do grupo no SG Master: a Referência do produto
--                       (todos os tamanhos do mesmo modelo têm a mesma) ou,
--                       sem Referência, "COD:<código>" de um produto só.
-- products.erp_managed  1 = produto criado/atualizado pela sincronização.
--                       Preço, descrição, tamanhos, estoque e visibilidade
--                       vêm do SG Master; foto, cor e categoria vêm do admin.
-- product_variants.sku  = Código do produto no SG Master (um por tamanho).
-- erp_sync_log          histórico das sincronizações (mostrado no admin).
-- =====================================================================

ALTER TABLE products
  ADD COLUMN erp_ref VARCHAR(100) NULL,
  ADD COLUMN erp_managed TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN erp_synced_at DATETIME NULL,
  ADD UNIQUE KEY uq_products_erp_ref (erp_ref);

CREATE INDEX idx_variants_sku ON product_variants (sku);

CREATE TABLE erp_sync_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  received_at DATETIME NOT NULL,
  source VARCHAR(30) NOT NULL,
  items_received INT NOT NULL DEFAULT 0,
  products_created INT NOT NULL DEFAULT 0,
  variants_created INT NOT NULL DEFAULT 0,
  variants_updated INT NOT NULL DEFAULT 0,
  variants_hidden INT NOT NULL DEFAULT 0,
  waiting_photo INT NOT NULL DEFAULT 0,
  notes TEXT NULL
) DEFAULT CHARSET=utf8mb4;
