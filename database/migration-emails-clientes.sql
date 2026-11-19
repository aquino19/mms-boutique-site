-- =====================================================================
-- Migração: e-mails da loja + "Meus pedidos"
-- Rodar UMA vez no phpMyAdmin (banco u340554535_mms_boutique),
-- com o banco selecionado na coluna da esquerda.
-- =====================================================================

-- Conta de envio (Gmail com senha de app)
ALTER TABLE store_settings
  ADD COLUMN smtp_user VARCHAR(255) NULL,
  ADD COLUMN smtp_pass VARCHAR(255) NULL,
  ADD COLUMN mail_last_error TEXT NULL;

-- Evita mandar o mesmo e-mail duas vezes
ALTER TABLE orders
  ADD COLUMN paid_notified_at DATETIME NULL,
  ADD COLUMN fulfillment_notified VARCHAR(20) NULL;

CREATE INDEX idx_orders_email ON orders (customer_email);

-- Links de acesso a "Meus pedidos" (guardamos só o hash do token)
CREATE TABLE customer_access_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  ip VARCHAR(45) NULL,
  UNIQUE KEY uq_token (token_hash),
  KEY idx_email_created (email, created_at)
) DEFAULT CHARSET=utf8mb4;

-- E-mail da loja (contato e avisos de pedido)
UPDATE store_settings SET contact_email = 'lojamms20@gmail.com' WHERE id = 1;
