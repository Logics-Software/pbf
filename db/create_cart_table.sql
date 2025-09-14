-- Create table for shopping cart
-- File: db/create_cart_table.sql

CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_code VARCHAR(50) NOT NULL,
    kodebarang VARCHAR(50) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_code) REFERENCES mastercustomer(kodecustomer) ON DELETE CASCADE,
    FOREIGN KEY (kodebarang) REFERENCES masterbarang(kodebarang) ON DELETE CASCADE,
    UNIQUE KEY unique_customer_product (customer_code, kodebarang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add index for better performance
CREATE INDEX idx_cart_customer ON cart(customer_code);
CREATE INDEX idx_cart_product ON cart(kodebarang);
CREATE INDEX idx_cart_created ON cart(created_at);
