-- =====================================================
-- Migração: multi-loja (retirada/entrega dividida por unidade)
-- Rodar em bancos que já foram criados com o schema anterior.
-- =====================================================

SET NAMES utf8mb4;

-- products: sku
ALTER TABLE products
    ADD COLUMN sku VARCHAR(100) NULL AFTER id,
    ADD INDEX idx_products_sku (sku);

-- store_locations (nova tabela)
CREATE TABLE IF NOT EXISTS store_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO store_locations (name) VALUES ('Loja Igapó'), ('Loja SuperFácil');

-- stock_by_store (nova tabela)
CREATE TABLE IF NOT EXISTS stock_by_store (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    location_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_product_location (product_id, location_id),
    CONSTRAINT fk_stock_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_stock_location FOREIGN KEY (location_id) REFERENCES store_locations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- orders: método de entrega + endereço
ALTER TABLE orders
    ADD COLUMN fulfillment_method ENUM('pickup', 'delivery') NULL,
    ADD COLUMN address_street VARCHAR(255) NULL,
    ADD COLUMN address_number VARCHAR(20) NULL,
    ADD COLUMN address_complement VARCHAR(255) NULL,
    ADD COLUMN address_neighborhood VARCHAR(255) NULL,
    ADD COLUMN address_city VARCHAR(255) NULL,
    ADD COLUMN address_state VARCHAR(2) NULL,
    ADD COLUMN address_zip VARCHAR(10) NULL;

-- order_items: de qual unidade o item sai
ALTER TABLE order_items
    ADD COLUMN fulfillment_location_id INT NULL,
    ADD CONSTRAINT fk_order_items_location
        FOREIGN KEY (fulfillment_location_id) REFERENCES store_locations(id)
        ON DELETE SET NULL;

-- Migra o estoque que já existia em products.stock para a primeira unidade
-- (ajuste manualmente depois, produto por produto, se o estoque real já
-- estiver dividido entre as duas lojas)
INSERT INTO stock_by_store (product_id, location_id, quantity)
SELECT p.id, (SELECT id FROM store_locations ORDER BY id LIMIT 1), p.stock
FROM products p
ON DUPLICATE KEY UPDATE quantity = VALUES(quantity);
