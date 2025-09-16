-- Create the database
CREATE DATABASE IF NOT EXISTS grocery_ecommerce;
USE grocery_ecommerce;

-- Create products table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    image VARCHAR(255),
    category VARCHAR(50) NOT NULL,
    description TEXT,
    stock INT DEFAULT 0,
    discount DECIMAL(5, 2) DEFAULT NULL,
    is_new BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create categories table
CREATE TABLE IF NOT EXISTS categories (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create orders table
CREATE TABLE IF NOT EXISTS orders (
    id VARCHAR(32) PRIMARY KEY,
    customer VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    date DATE NOT NULL,
    status VARCHAR(20) NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create order_items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(32) NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert sample categories
INSERT INTO categories (id, name) VALUES
('all', 'All Categories'),
('beverages', 'Beverages'),
('packaged-foods', 'Packaged Foods'),
('seasonings', 'Seasonings'),
('household-items', 'Household Items'),
('canned-foods', 'Canned Foods'),
('cooking-oils', 'Cooking Oils'),
('personal-care', 'Personal Care')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Insert sample products
INSERT INTO products (name, price, image, category, description, stock, is_new) VALUES
('Golden Penny Macaroni (500g)', 1200, '/images/golden-penny-macaroni.png', 'packaged-foods', 'High-quality Golden Penny pasta macaroni. Rich in protein and fiber. Perfect for making delicious pasta dishes.', 45, FALSE),
('Mai Kwabo Pasta Macaroni (500g)', 950, '/images/mai-kwabo-pasta.png', 'packaged-foods', 'Mai Kwabo pasta macaroni. Contains protein and provides energy. Great for family meals.', 30, TRUE),
('Maggi Star Seasoning (100 cubes)', 1500, '/images/maggi-seasoning.png', 'seasonings', 'Maggi Star seasoning cubes. Enhances the flavor of your meals. 100 cubes per pack.', 25, FALSE),
('Gino Max Beef Flavour (25g)', 350, '/images/maggi-seasoning.png', 'seasonings', 'Gino Max Beef Flavour seasoning. Adds rich beef flavor to your meals.', 40, FALSE),
('Tasty Tom Tomato Paste (50g)', 250, '/images/tasty-tom-paste.png', 'seasonings', 'Tasty Tom tomato paste in convenient sachets. Perfect for small servings.', 60, FALSE),
('Nestle Milo 3-in-1 (20 sachets)', 3500, '/images/milo-sachets.png', 'beverages', 'Nestle Milo 3-in-1 energy drink sachets. Contains milk, malt and cocoa. Source of calcium.', 15, FALSE),
('Bottled Water (Pack of 6)', 1800, '/placeholder.svg?height=300&width=300', 'beverages', 'A pack of 6 refreshing bottled water. Perfect for staying hydrated throughout the day.', 24, FALSE),
('Soft Drinks (Pack of 6)', 2200, '/placeholder.svg?height=300&width=300', 'beverages', 'Refreshing soft drinks in a variety of flavors. Perfect for any occasion.', 30, FALSE);

-- Insert sample users (password is bcrypt hash for the string 'password')
INSERT INTO users (name, email, password, role) VALUES
('Admin User', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Customer User', 'customer@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer')
ON DUPLICATE KEY UPDATE name = VALUES(name), role = VALUES(role);

-- Insert sample orders
INSERT INTO orders (id, customer, email, date, status, total) VALUES
('ORD-001', 'John Doe', 'john@example.com', '2023-05-12', 'Delivered', 24.97),
('ORD-002', 'Jane Smith', 'jane@example.com', '2023-05-11', 'Processing', 39.98),
('ORD-003', 'Robert Johnson', 'robert@example.com', '2023-05-10', 'Shipped', 15.96)
ON DUPLICATE KEY UPDATE customer = VALUES(customer), email = VALUES(email), status = VALUES(status), total = VALUES(total);

-- Insert sample order items
INSERT INTO order_items (order_id, product_id, quantity, price) VALUES
('ORD-001', 1, 2, 1200),
('ORD-001', 3, 3, 1500),
('ORD-002', 2, 4, 950),
('ORD-003', 4, 2, 350),
('ORD-003', 5, 1, 250),
('ORD-003', 3, 1, 1500);

-- Reviews table based on data/reviews.ts
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_name VARCHAR(100) NOT NULL,
    rating TINYINT NOT NULL,
    comment TEXT NOT NULL,
    date DATE NOT NULL,
    helpful INT DEFAULT 0,
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Seed reviews
INSERT INTO reviews (id, product_id, user_name, rating, comment, date, helpful, verified) VALUES
(1, 1, 'Chioma A.', 5, 'The Golden Penny Macaroni is excellent quality. Cooks perfectly every time and tastes great!', '2025-05-10', 12, TRUE),
(2, 1, 'Emmanuel O.', 4, 'Good product, but I wish the packaging was resealable. Otherwise, great taste and value.', '2025-05-05', 8, TRUE),
(3, 1, 'Blessing I.', 5, "My family loves this macaroni. It's now our regular brand for Sunday dinner.", '2025-04-28', 5, TRUE),
(4, 2, 'Tunde F.', 4, "Mai Kwabo pasta is good quality for the price. Cooks well and doesn't get too soft.", '2025-05-12', 3, TRUE),
(5, 2, 'Amina B.', 3, 'Average pasta. Nothing special but does the job. I prefer Golden Penny.', '2025-05-01', 1, FALSE),
(6, 6, 'Oluwaseun D.', 5, 'Milo is always a winner! These sachets are so convenient for my kids\' breakfast.', '2025-05-08', 15, TRUE),
(7, 6, 'Ngozi E.', 5, "Perfect size for travel. I always carry a few sachets when I'm on the go.", '2025-04-25', 7, TRUE),
(8, 3, 'Ibrahim M.', 4, 'Maggi Star seasoning adds great flavor to my soups. Good value for the quantity.', '2025-05-11', 9, TRUE)
ON DUPLICATE KEY UPDATE product_id = VALUES(product_id), user_name = VALUES(user_name), rating = VALUES(rating), comment = VALUES(comment), date = VALUES(date), helpful = VALUES(helpful), verified = VALUES(verified);

-- Upsert extended products from data/products.ts (ids 1..32)
INSERT INTO products (id, name, price, image, category, description, stock, discount, is_new) VALUES
(1, 'Golden Penny Macaroni (500g)', 1200, '/images/golden-penny-macaroni.png', 'packaged-foods', 'High-quality Golden Penny pasta macaroni. Rich in protein and fiber. Perfect for making delicious pasta dishes.', 45, NULL, FALSE),
(2, 'Mai Kwabo Pasta Macaroni (500g)', 950, '/images/mai-kwabo-pasta.png', 'packaged-foods', 'Mai Kwabo pasta macaroni. Contains protein and provides energy. Great for family meals.', 30, NULL, TRUE),
(3, 'Maggi Star Seasoning (100 cubes)', 1500, '/images/maggi-seasoning.png', 'seasonings', 'Maggi Star seasoning cubes. Enhances the flavor of your meals. 100 cubes per pack.', 25, NULL, FALSE),
(4, 'Gino Max Beef Flavour (25g)', 350, '/images/maggi-seasoning.png', 'seasonings', 'Gino Max Beef Flavour seasoning. Adds rich beef flavor to your meals.', 40, 10, FALSE),
(5, 'Tasty Tom Tomato Paste (50g)', 250, '/images/tasty-tom-paste.png', 'seasonings', 'Tasty Tom tomato paste in convenient sachets. Perfect for small servings.', 60, NULL, FALSE),
(6, 'Nestle Milo 3-in-1 (20 sachets)', 3500, '/images/milo-sachets.png', 'beverages', 'Nestle Milo 3-in-1 energy drink sachets. Contains milk, malt and cocoa. Source of calcium.', 15, NULL, FALSE),
(7, 'Bottled Water (Pack of 6)', 1800, '/placeholder.svg?height=300&width=300', 'beverages', 'A pack of 6 refreshing bottled water. Perfect for staying hydrated throughout the day.', 24, NULL, FALSE),
(8, 'Soft Drinks (Pack of 6)', 2200, '/placeholder.svg?height=300&width=300', 'beverages', 'Refreshing soft drinks in a variety of flavors. Perfect for any occasion.', 30, NULL, FALSE),
(9, 'La Casera Apple Drink (Pack of 6)', 2500, 'https://hebbkx1anhila5yf.public.blob.vercel-storage.com/WhatsApp%20Image%202025-05-17%20at%2011.37.57%20PM%20%281%29-JSUojsu9wSypZybZ5H4ANqcl9iDjsB.jpeg', 'beverages', 'Refreshing apple-flavored drink. Perfect for quenching your thirst with a sweet apple taste.', 40, NULL, TRUE),
(10, 'Smoov Carbonated Drink (Pack of 6)', 2400, 'https://hebbkx1anhila5yf.public.blob.vercel-storage.com/WhatsApp%20Image%202025-05-17%20at%2011.37.57%20PM-JaStU3MM1FsQ4UUxVx7JumFX1Qcbj2.jpeg', 'beverages', 'Smooth and refreshing carbonated soft drink. Great for parties and gatherings.', 35, NULL, TRUE),
(11, 'Teem Bitter Lemon (Pack of 6)', 2300, 'https://hebbkx1anhila5yf.public.blob.vercel-storage.com/WhatsApp%20Image%202025-05-17%20at%2011.37.56%20PM%20%281%29-G0P4hUh5l6M0BEZ13w5BhWEYkOcmPN.jpeg', 'beverages', 'Refreshing bitter lemon carbonated drink with a unique tangy flavor.', 30, NULL, TRUE),
(12, 'Sardines in Vegetable Oil (125g)', 850, 'https://hebbkx1anhila5yf.public.blob.vercel-storage.com/WhatsApp%20Image%202025-05-17%20at%2011.37.53%20PM-zJlcJ1ebuo5R70lVX6sZFbuazLvjO6.jpeg', 'canned-foods', 'Premium sardines in vegetable oil. Product of Morocco. Rich in protein and omega-3 fatty acids.', 50, NULL, TRUE),
(13, 'Haano Mackerel in Tomato Sauce (155g)', 950, 'https://hebbkx1anhila5yf.public.blob.vercel-storage.com/WhatsApp%20Image%202025-05-17%20at%2011.37.53%20PM%20%281%29-jWmxv6MGmIG6LT661IjRkN3WKdmUaQ.jpeg', 'canned-foods', 'Delicious mackerel in rich tomato sauce. Ready to eat and perfect for quick meals.', 45, NULL, TRUE),
(14, 'Top Tea (25 Tea Bags)', 850, 'https://hebbkx1anhila5yf.public.blob.vercel-storage.com/WhatsApp%20Image%202025-05-19%20at%202.30.04%20AM-XfHoFEwVUG1iKlHTSeWlpPSXpjwozV.jpeg', 'beverages', 'Premium quality tea bags with a grand bouquet of flavors. Contains 25 tea bags for a perfect cup of tea every time.', 40, NULL, TRUE),
(15, 'Closeup Toothpaste with Zinc+ (140g)', 650, 'https://hebbkx1anhila5yf.public.blob.vercel-storage.com/WhatsApp%20Image%202025-05-19%20at%202.30.05%20AM%20%281%29-38DGizfwWyyYb4dZU8NbHFmtM8aefI.jpeg', 'personal-care', 'Triple fresh formula toothpaste with antibacterial Zinc+ protection for long-lasting fresh breath and cavity protection.', 35, NULL, TRUE),
(16, 'Power Oil (75cl)', 1800, 'https://hebbkx1anhila5yf.public.blob.vercel-storage.com/WhatsApp%20Image%202025-05-19%20at%202.30.05%20AM-bQPVBrCeC8V6cj6teR5nivmhFFaZcT.jpeg', 'cooking-oils', 'Pure vegetable cooking oil for a healthy family. Cholesterol-free and fortified with essential vitamins.', 25, NULL, TRUE),
(17, 'Golden Penny Semovita (1kg)', 1200, 'https://hebbkx1anhila5yf.public.blob.vercel-storage.com/WhatsApp%20Image%202025-05-19%20at%202.30.06%20AM%20%281%29-eWnGZO9wjuHYyjiC2NwZDoRDcQ5TgD.jpeg', 'packaged-foods', 'Premium quality semolina flour, fortified with vitamins. Perfect for making smooth, delicious swallow meals.', 30, NULL, TRUE),
(18, 'Power Oil Mini Pack (Set of 12)', 1500, 'https://hebbkx1anhila5yf.public.blob.vercel-storage.com/WhatsApp%20Image%202025-05-19%20at%202.30.06%20AM-5faYknOFmTEWZxEJfe70TT8m5VAONl.jpeg', 'cooking-oils', 'Convenient mini packs of Power Oil vegetable cooking oil. Perfect for single-use or travel.', 20, NULL, TRUE),
(19, 'WAW Detergent (1kg)', 1100, 'https://hebbkx1anhila5yf.public.blob.vercel-storage.com/WhatsApp%20Image%202025-05-19%20at%202.30.07%20AM-1BRhT4jEWsbsiGV6FON2alHouGuKsY.jpeg', 'household-items', 'Multi-use detergent powder that washes a lot and saves a lot. Suitable for clothes, dishes, floors, and more.', 40, NULL, TRUE),
(20, 'Viva Plus Detergent Powder (170g)', 450, 'https://hebbkx1anhila5yf.public.blob.vercel-storage.com/WhatsApp%20Image%202025-05-19%20at%202.30.08%20AM-TI1zcpOnAAUbYKKuP9Y4C1PliObrD4.jpeg', 'household-items', 'Powerful stain removal detergent powder for all your laundry needs. Keeps clothes bright and fresh.', 50, NULL, TRUE),
(21, 'Sunlight Dish Washing Liquid (500ml)', 850, '/placeholder.svg?height=300&width=300&text=Sunlight&bg=3498db', 'household-items', 'Powerful grease-cutting dish washing liquid that leaves your dishes sparkling clean and fresh.', 45, NULL, TRUE),
(22, 'Hypo Bleach (500ml)', 750, '/placeholder.svg?height=300&width=300&text=Hypo&bg=f1c40f', 'household-items', 'Multi-purpose bleach for whitening clothes and disinfecting surfaces. Kills 99.9% of germs.', 38, NULL, TRUE),
(23, 'Dettol Antiseptic Liquid (250ml)', 1200, '/placeholder.svg?height=300&width=300&text=Dettol&bg=27ae60', 'household-items', 'Trusted protection against germs. Use for personal hygiene, cleaning cuts, and household disinfection.', 30, NULL, TRUE),
(24, 'Morning Fresh Dish Washing Liquid (400ml)', 950, '/placeholder.svg?height=300&width=300&text=Morning Fresh&bg=87cefa', 'household-items', 'Superior grease-cutting formula that\'s gentle on hands. Leaves dishes clean with a fresh lemon scent.', 42, NULL, TRUE),
(25, 'Harpic Power Plus Toilet Cleaner (500ml)', 1100, '/placeholder.svg?height=300&width=300&text=Harpic&bg=2c3e50', 'household-items', 'Powerful toilet cleaner that removes tough stains, kills germs, and leaves your toilet fresh and clean.', 35, NULL, TRUE),
(26, 'Omo Multi-Active Detergent (1kg)', 1300, '/placeholder.svg?height=300&width=300&text=Omo&bg=2980b9', 'household-items', 'Advanced stain removal detergent powder that delivers superior cleaning performance on all types of fabrics.', 40, NULL, TRUE),
(27, 'Ariel Washing Powder (900g)', 1250, '/placeholder.svg?height=300&width=300&text=Ariel&bg=3498db', 'household-items', 'Premium quality detergent with stain-lifting technology for brilliantly clean clothes every time.', 38, NULL, TRUE),
(28, 'Air Wick Air Freshener (250ml)', 950, '/placeholder.svg?height=300&width=300&text=Air Wick&bg=9b59b6', 'household-items', 'Long-lasting air freshener that eliminates odors and leaves your home smelling fresh and clean.', 50, NULL, TRUE),
(29, 'Glade Air Freshener Spray (300ml)', 850, '/placeholder.svg?height=300&width=300&text=Glade&bg=8e44ad', 'household-items', 'Instant freshness with a variety of pleasant scents to keep your home smelling great all day.', 45, NULL, TRUE),
(30, 'Scotch-Brite Scrubbing Sponge (3-pack)', 650, '/placeholder.svg?height=300&width=300&text=Scotch-Brite&bg=e67e22', 'household-items', 'Durable scrubbing sponges with non-scratch surface for effective cleaning of dishes and surfaces.', 60, NULL, TRUE),
(31, 'Mr. Muscle Glass Cleaner (500ml)', 850, '/placeholder.svg?height=300&width=300&text=Mr. Muscle&bg=34495e', 'household-items', 'Streak-free glass and surface cleaner that leaves windows, mirrors, and glass surfaces sparkling clean.', 40, NULL, TRUE),
(32, 'Vim Dishwashing Bar (250g)', 350, '/placeholder.svg?height=300&width=300&text=Vim&bg=16a085', 'household-items', 'Powerful dishwashing bar that cuts through tough grease and food residue for clean, shiny dishes.', 70, NULL, TRUE)
ON DUPLICATE KEY UPDATE name = VALUES(name), price = VALUES(price), image = VALUES(image), category = VALUES(category), description = VALUES(description), stock = VALUES(stock), discount = VALUES(discount), is_new = VALUES(is_new);

-- Carts and Cart Items for per-user shopping carts
CREATE TABLE IF NOT EXISTS carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    status ENUM('active', 'checked_out') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cart_product (cart_id, product_id),
    FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Payment receipts and approval flow
CREATE TABLE IF NOT EXISTS payment_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(32) NOT NULL,
    user_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    file_size INT DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    notes TEXT,
    reviewed_by INT DEFAULT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Extend orders for payment tracking and user relation
ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS user_id INT NULL,
    ADD COLUMN IF NOT EXISTS payment_status VARCHAR(20) NOT NULL DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL;

ALTER TABLE orders
    ADD CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
