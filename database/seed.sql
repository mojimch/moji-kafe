USE moji_kafe;

-- Development seed only. Set APP_ENV=development locally; the production default blocks this password.
INSERT INTO admins (id, name, username, password)
VALUES (
    1,
    'Administrator',
    'admin',
    '$2y$12$zWIo/5bE4FV7To6hK5ICSeJ3XaVrLnoWrUY2mT9evvJB5SHbJb6Cq'
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    username = VALUES(username),
    password = VALUES(password);

INSERT INTO categories (id, name)
VALUES
    (1, 'Makanan'),
    (2, 'Minuman'),
    (3, 'Snack')
ON DUPLICATE KEY UPDATE
    name = VALUES(name);

INSERT INTO products (id, category_id, name, description, price, is_available)
VALUES
    (1, 1, 'Nasi Goreng Moji', 'Nasi goreng khas Moji Kafe.', 28000.00, TRUE),
    (2, 1, 'Chicken Katsu Rice', 'Ayam katsu renyah dengan nasi hangat.', 32000.00, TRUE),
    (3, 2, 'Es Kopi Susu', 'Espresso dan susu dengan rasa yang seimbang.', 18000.00, TRUE),
    (4, 2, 'Matcha Latte', 'Minuman matcha lembut dengan susu.', 22000.00, TRUE),
    (5, 3, 'French Fries', 'Kentang goreng renyah.', 15000.00, TRUE)
ON DUPLICATE KEY UPDATE
    category_id = VALUES(category_id),
    name = VALUES(name),
    description = VALUES(description),
    price = VALUES(price),
    is_available = VALUES(is_available);

INSERT INTO product_variants (id, product_id, name, price, is_available)
VALUES
    (1, 1, 'Regular', 28000.00, TRUE),
    (2, 1, 'Extra Ayam', 35000.00, TRUE),
    (3, 2, 'Regular', 32000.00, TRUE),
    (4, 2, 'Extra Nasi', 36000.00, TRUE),
    (5, 3, 'Regular', 18000.00, TRUE),
    (6, 3, 'Large', 23000.00, TRUE),
    (7, 4, 'Regular', 22000.00, TRUE),
    (8, 4, 'Large', 27000.00, TRUE),
    (9, 5, 'Regular', 15000.00, TRUE),
    (10, 5, 'Large', 22000.00, TRUE)
ON DUPLICATE KEY UPDATE
    product_id = VALUES(product_id),
    name = VALUES(name),
    price = VALUES(price),
    is_available = VALUES(is_available);

INSERT INTO `tables` (id, table_number, capacity, is_active)
VALUES
    (1, 'Meja 01', 2, TRUE),
    (2, 'Meja 02', 4, TRUE),
    (3, 'Meja 03', 4, TRUE),
    (4, 'Meja 04', 6, TRUE)
ON DUPLICATE KEY UPDATE
    table_number = VALUES(table_number),
    capacity = VALUES(capacity),
    is_active = VALUES(is_active);
