-- =====================================================
-- MM'S Boutique — Schema do banco de dados da loja
-- Baseado na arquitetura definida em arquitetura-completa-loja.md
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------
-- Tabela: admin_users
-- Login do lojista (acesso ao painel de admin)
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- Tabela: store_settings
-- Configuração geral da loja (singleton: sempre 1 linha, id=1)
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS store_settings (
    id INT PRIMARY KEY DEFAULT 1,
    store_name VARCHAR(255) NOT NULL DEFAULT 'MM''S Boutique',
    contact_email VARCHAR(255),
    gateway_provider ENUM('mercadopago', 'pagseguro') DEFAULT NULL,
    gateway_access_token TEXT,       -- deve ser armazenado criptografado pela aplicação
    gateway_public_key VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_single_row CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Linha inicial de configuração (sem gateway definido ainda)
INSERT INTO store_settings (id, store_name, contact_email)
VALUES (1, 'MM''S Boutique', 'contato@mmsboutique.com')
ON DUPLICATE KEY UPDATE store_name = VALUES(store_name);

-- ---------------------------------------------------
-- Tabela: products
-- Catálogo de produtos — começa vazia, populada via admin
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(100) NULL,            -- código do produto, usado para casar com o sistema de estoque do cliente
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,     -- SEMPRE = soma de stock_by_store; nunca editar direto, ver função recalcProductStock()
    image_url VARCHAR(500),
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_products_active (active),
    INDEX idx_products_sku (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- Tabela: store_locations
-- Unidades físicas da loja (funciona pra 1, 2 ou mais unidades,
-- sem precisar mudar código para outros clientes no futuro)
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS store_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Unidades da MM'S Boutique (ajustar/renomear conforme necessário)
INSERT INTO store_locations (name) VALUES ('Loja Igapó'), ('Loja SuperFácil');

-- ---------------------------------------------------
-- Tabela: stock_by_store
-- Estoque de cada produto, separado por unidade física.
-- products.stock é sempre a soma desta tabela (nunca editado direto).
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS stock_by_store (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    location_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_product_location (product_id, location_id),
    CONSTRAINT fk_stock_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_stock_location
        FOREIGN KEY (location_id) REFERENCES store_locations(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- Tabela: orders
-- Um pedido = um checkout iniciado pelo cliente final
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255),
    customer_email VARCHAR(255),
    customer_phone VARCHAR(30),
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    gateway_charge_id VARCHAR(255),   -- id retornado pelo Mercado Pago/PagSeguro
    fulfillment_method ENUM('pickup', 'delivery') NULL,
    address_street VARCHAR(255) NULL,
    address_number VARCHAR(20) NULL,
    address_complement VARCHAR(255) NULL,
    address_neighborhood VARCHAR(255) NULL,
    address_city VARCHAR(255) NULL,
    address_state VARCHAR(2) NULL,
    address_zip VARCHAR(10) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_orders_gateway_charge_id (gateway_charge_id),
    INDEX idx_orders_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- Tabela: order_items
-- Itens de um pedido (um pedido pode ter vários produtos)
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,  -- preço no momento da venda (não referencia products.price)
    fulfillment_location_id INT NULL,   -- de qual unidade este item específico sai (null = ainda não alocado / entrega)
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_order_items_location
        FOREIGN KEY (fulfillment_location_id) REFERENCES store_locations(id)
        ON DELETE SET NULL,
    INDEX idx_order_items_order_id (order_id),
    INDEX idx_order_items_product_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
