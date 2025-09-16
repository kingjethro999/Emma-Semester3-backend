<?php
// Simple PHP API for Next.js frontend at http://localhost/emmaapi/v1/api.php
// Supports endpoints via query param: ?endpoint=products, orders, categories, auth, etc.

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function json_response($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function db_connect() {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $name = 'grocery_ecommerce';

    $mysqli = @new mysqli($host, $user, $pass, $name);
    if ($mysqli->connect_errno) {
        json_response(['message' => 'Database connection failed', 'error' => $mysqli->connect_error], 500);
    }
    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}

function get_endpoint_parts(): array {
    $endpoint = isset($_GET['endpoint']) ? trim($_GET['endpoint']) : '';
    $endpoint = trim($endpoint, '/');
    if ($endpoint === '') return [];
    return array_values(array_filter(explode('/', $endpoint), fn($p) => $p !== ''));
}

// Auth helpers
function get_auth_header(): string {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization'] ?? ($headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    return is_string($auth) ? $auth : '';
}

function current_user(mysqli $db): ?array {
    $auth = get_auth_header();
    if (!$auth) return null;
    if (!preg_match('/Bearer\s+(.*)/i', $auth, $m)) return null;
    $token = trim($m[1]);
    $decoded = base64_decode($token, true);
    if ($decoded === false) return null;
    $parts = explode('|', $decoded, 2);
    if (count($parts) < 1) return null;
    $email = $parts[0];
    $stmt = $db->prepare('SELECT id, name, email, role FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    return $user ?: null;
}

function require_user(mysqli $db): array {
    $u = current_user($db);
    if (!$u) json_response(['message' => 'Unauthorized'], 401);
    return $u;
}

function require_admin(mysqli $db): array {
    $u = require_user($db);
    if (($u['role'] ?? 'customer') !== 'admin') json_response(['message' => 'Forbidden'], 403);
    return $u;
}

function get_or_create_active_cart(mysqli $db, int $userId): int {
    $status = 'active';
    $stmt = $db->prepare('SELECT id FROM carts WHERE user_id = ? AND status = ? LIMIT 1');
    $stmt->bind_param('is', $userId, $status);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) return (int)$row['id'];
    $stmt2 = $db->prepare('INSERT INTO carts (user_id, status) VALUES (?, ?)');
    $stmt2->bind_param('is', $userId, $status);
    $stmt2->execute();
    return (int)$db->insert_id;
}

function build_cart_payload(mysqli $db, int $cartId): array {
    $stmt = $db->prepare('SELECT ci.product_id AS productId, p.name, p.image, ci.quantity, ci.unit_price AS unitPrice FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.cart_id = ?');
    $stmt->bind_param('i', $cartId);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $total = 0.0;
    foreach ($items as &$it) {
        $qty = (int)$it['quantity'];
        $price = (float)$it['unitPrice'];
        $it['subtotal'] = round($qty * $price, 2);
        $total += $it['subtotal'];
    }
    return ['cartId' => $cartId, 'items' => $items, 'total' => round($total, 2)];
}

$method = $_SERVER['REQUEST_METHOD'];
$parts = get_endpoint_parts();
$resource = $parts[0] ?? '';
$resourceId = $parts[1] ?? ($_GET['id'] ?? null);
$input = json_decode(file_get_contents('php://input') ?: 'null', true);

$db = db_connect();

try {
    switch ($resource) {
        case 'products':
            // ... existing code ...
            if ($method === 'GET') {
                if ($resourceId) {
                    $stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
                    $stmt->bind_param('i', $resourceId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    if (!$row) json_response(['message' => 'Product not found'], 404);
                    json_response($row);
                }
                $category = isset($_GET['category']) ? $_GET['category'] : null;
                if ($category && $category !== 'all') {
                    $stmt = $db->prepare('SELECT * FROM products WHERE category = ?');
                    $stmt->bind_param('s', $category);
                    $stmt->execute();
                    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    json_response($rows);
                }
                $rows = $db->query('SELECT * FROM products ORDER BY id DESC')->fetch_all(MYSQLI_ASSOC);
                json_response($rows);
            }

            if ($method === 'POST') {
                if (!isset($input['name'], $input['price'], $input['category'])) {
                    json_response(['message' => 'Missing required fields: name, price, category'], 400);
                }
                $name = $input['name'];
                $price = (float)$input['price'];
                $image = $input['image'] ?? null;
                $category = $input['category'];
                $description = $input['description'] ?? null;
                $stock = isset($input['stock']) ? (int)$input['stock'] : 0;
                $discount = isset($input['discount']) ? (float)$input['discount'] : null;

                $stmt = $db->prepare('INSERT INTO products (name, price, image, category, description, stock, discount) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('sdsssid', $name, $price, $image, $category, $description, $stock, $discount);
                // Adjust types: discount can be null, use i for ints, d for decimals; using s for null fallback
                $stmt->execute();
                $newId = $db->insert_id;
                json_response(['id' => $newId, 'name' => $name, 'price' => $price, 'image' => $image, 'category' => $category, 'description' => $description, 'stock' => $stock, 'discount' => $discount], 201);
            }

            if ($method === 'PUT') {
                if (!$resourceId) json_response(['message' => 'Product id is required'], 400);
                $fields = ['name','price','image','category','description','stock','discount','is_new'];
                $updates = [];
                $params = [];
                $types = '';
                foreach ($fields as $f) {
                    if (array_key_exists($f, $input)) {
                        $updates[] = "$f = ?";
                        $val = $input[$f];
                        if (in_array($f, ['price','discount'])) { $types .= 'd'; $params[] = (float)$val; }
                        elseif (in_array($f, ['stock'])) { $types .= 'i'; $params[] = (int)$val; }
                        elseif ($f === 'is_new') { $types .= 'i'; $params[] = $val ? 1 : 0; }
                        else { $types .= 's'; $params[] = $val; }
                    }
                }
                if (empty($updates)) json_response(['message' => 'No fields to update'], 400);
                $types .= 'i';
                $params[] = (int)$resourceId;
                $sql = 'UPDATE products SET ' . implode(', ', $updates) . ' WHERE id = ?';
                $stmt = $db->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                json_response(['message' => 'Product updated']);
            }

            if ($method === 'DELETE') {
                if (!$resourceId) json_response(['message' => 'Product id is required'], 400);
                $stmt = $db->prepare('DELETE FROM products WHERE id = ?');
                $stmt->bind_param('i', $resourceId);
                $stmt->execute();
                json_response(['message' => 'Product deleted']);
            }
            break;

        case 'categories':
            if ($method === 'GET') {
                if ($resourceId) {
                    $stmt = $db->prepare('SELECT * FROM categories WHERE id = ?');
                    $stmt->bind_param('s', $resourceId);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    if (!$row) json_response(['message' => 'Category not found'], 404);
                    json_response($row);
                }
                $rows = $db->query('SELECT * FROM categories ORDER BY name ASC')->fetch_all(MYSQLI_ASSOC);
                json_response($rows);
            }
            json_response(['message' => 'Method not allowed'], 405);
            break;

        case 'orders':
            if ($method === 'GET') {
                if ($resourceId) {
                    $stmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
                    $stmt->bind_param('s', $resourceId);
                    $stmt->execute();
                    $order = $stmt->get_result()->fetch_assoc();
                    if (!$order) json_response(['message' => 'Order not found'], 404);
                    $stmt2 = $db->prepare('SELECT product_id AS productId, quantity, price FROM order_items WHERE order_id = ?');
                    $stmt2->bind_param('s', $resourceId);
                    $stmt2->execute();
                    $items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
                    $order['items'] = $items;
                    json_response($order);
                }
                $rows = $db->query('SELECT * FROM orders ORDER BY date DESC')->fetch_all(MYSQLI_ASSOC);
                json_response($rows);
            }

            if ($method === 'POST') {
                if (!isset($input['customer'], $input['email'], $input['items']) || !is_array($input['items']) || count($input['items']) === 0) {
                    json_response(['message' => 'Missing required fields: customer, email, items'], 400);
                }
                $customer = $input['customer'];
                $email = $input['email'];
                $items = $input['items'];
                $total = 0.0;
                foreach ($items as $it) {
                    $qty = isset($it['quantity']) ? (int)$it['quantity'] : 0;
                    $price = isset($it['price']) ? (float)$it['price'] : 0.0;
                    $total += $qty * $price;
                }
                $total = round($total, 2);
                $orderId = 'ORD-' . strtoupper(substr(uniqid('', true), -6));
                $today = date('Y-m-d');

                $stmt = $db->prepare('INSERT INTO orders (id, customer, email, date, status, total) VALUES (?, ?, ?, ?, ?, ?)');
                $status = 'Processing';
                $stmt->bind_param('sssssd', $orderId, $customer, $email, $today, $status, $total);
                $stmt->execute();

                $stmtItem = $db->prepare('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');
                foreach ($items as $it) {
                    $pid = (int)($it['productId'] ?? $it['product_id'] ?? 0);
                    $qty = (int)($it['quantity'] ?? 0);
                    $price = (float)($it['price'] ?? 0.0);
                    $stmtItem->bind_param('siid', $orderId, $pid, $qty, $price);
                    $stmtItem->execute();
                }

                json_response([
                    'id' => $orderId,
                    'customer' => $customer,
                    'email' => $email,
                    'date' => $today,
                    'status' => 'Processing',
                    'total' => $total,
                    'items' => $items
                ], 201);
            }

            if ($method === 'PUT') {
                if (!$resourceId) json_response(['message' => 'Order id is required'], 400);
                $status = $input['status'] ?? null;
                if (!$status) json_response(['message' => 'Status is required'], 400);
                $stmt = $db->prepare('UPDATE orders SET status = ? WHERE id = ?');
                $stmt->bind_param('ss', $status, $resourceId);
                $stmt->execute();
                json_response(['message' => 'Order status updated']);
            }
            json_response(['message' => 'Method not allowed'], 405);
            break;

        case 'reviews':
            if ($method === 'GET') {
                $productId = isset($_GET['productId']) ? (int)$_GET['productId'] : null;
                if ($resourceId) {
                    $stmt = $db->prepare('SELECT * FROM reviews WHERE id = ?');
                    $stmt->bind_param('i', $resourceId);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    if (!$row) json_response(['message' => 'Review not found'], 404);
                    json_response($row);
                }
                if ($productId) {
                    $stmt = $db->prepare('SELECT id, product_id AS productId, user_name AS userName, rating, comment, date, helpful, verified FROM reviews WHERE product_id = ? ORDER BY date DESC');
                    $stmt->bind_param('i', $productId);
                    $stmt->execute();
                    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    json_response($rows);
                }
                $rows = $db->query('SELECT id, product_id AS productId, user_name AS userName, rating, comment, date, helpful, verified FROM reviews ORDER BY date DESC')->fetch_all(MYSQLI_ASSOC);
                json_response($rows);
            }

            if ($method === 'POST') {
                $required = ['productId','userName','rating','comment'];
                foreach ($required as $r) { if (!isset($input[$r])) json_response(['message' => 'Missing field: '.$r], 400); }
                $pid = (int)$input['productId'];
                $user = (string)$input['userName'];
                $rating = (int)$input['rating'];
                $comment = (string)$input['comment'];
                $date = date('Y-m-d');
                $verified = isset($input['verified']) ? (int)!!$input['verified'] : 0;
                $helpful = 0;
                $stmt = $db->prepare('INSERT INTO reviews (product_id, user_name, rating, comment, date, helpful, verified) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('isissii', $pid, $user, $rating, $comment, $date, $helpful, $verified);
                $stmt->execute();
                $newId = $db->insert_id;
                json_response(['id' => $newId, 'productId' => $pid, 'userName' => $user, 'rating' => $rating, 'comment' => $comment, 'date' => $date, 'helpful' => $helpful, 'verified' => (bool)$verified], 201);
            }

            if ($method === 'PUT') {
                if (!$resourceId) json_response(['message' => 'Review id is required'], 400);
                $action = $input['action'] ?? '';
                if ($action === 'helpful') {
                    $stmt = $db->prepare('UPDATE reviews SET helpful = helpful + 1 WHERE id = ?');
                    $stmt->bind_param('i', $resourceId);
                    $stmt->execute();
                    json_response(['message' => 'Marked helpful']);
                }
                json_response(['message' => 'Unsupported action'], 400);
            }
            json_response(['message' => 'Method not allowed'], 405);
            break;

        case 'auth':
            if ($method === 'GET' && ($parts[1] ?? '') === 'me') {
                $user = current_user($db);
                if (!$user) json_response(['message' => 'Unauthorized'], 401);
                json_response(['user' => $user]);
            }

            if ($method === 'POST' && ($parts[1] ?? '') !== 'register') { // login
                $email = $input['email'] ?? '';
                $password = $input['password'] ?? '';
                if (!$email || !$password) json_response(['message' => 'Email and password are required'], 400);
                $stmt = $db->prepare('SELECT id, name, email, password, role FROM users WHERE email = ?');
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                if (!$user || !password_verify($password, $user['password'])) {
                    json_response(['message' => 'Invalid email or password'], 401);
                }
                $token = base64_encode($user['email'] . '|' . time());
                unset($user['password']);
                json_response(['user' => $user, 'token' => $token]);
            }

            if ($method === 'PUT' && ($parts[1] ?? '') === 'register') {
                // Allow POST to auth/register, but support PUT fallback if client mis-sends
            }

            if ($method === 'POST' && ($parts[1] ?? '') === 'register') {
                $name = $input['name'] ?? '';
                $email = $input['email'] ?? '';
                $password = $input['password'] ?? '';
                if (!$name || !$email || !$password) json_response(['message' => 'Missing fields: name, email, password'], 400);
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
                $role = 'customer';
                $stmt->bind_param('ssss', $name, $email, $hash, $role);
                if (!$stmt->execute()) {
                    json_response(['message' => 'Registration failed', 'error' => $db->error], 400);
                }
                json_response(['message' => 'Registered successfully']);
            }
            json_response(['message' => 'Method not allowed'], 405);
            break;

        case 'users':
            // Admin-only users listing and management
            if ($method === 'GET') {
                require_admin($db);
                $rows = $db->query('SELECT id, name, email, role, created_at FROM users ORDER BY id DESC')->fetch_all(MYSQLI_ASSOC);
                json_response($rows);
            }
            if ($method === 'PUT' && $resourceId) {
                require_admin($db);
                $role = $input['role'] ?? null;
                if (!in_array($role, ['customer','admin'])) json_response(['message' => 'Invalid role'], 400);
                $stmt = $db->prepare('UPDATE users SET role = ? WHERE id = ?');
                $uid = (int)$resourceId;
                $stmt->bind_param('si', $role, $uid);
                $stmt->execute();
                json_response(['message' => 'User updated']);
            }
            json_response(['message' => 'Method not allowed'], 405);
            break;

        case 'cart':
            $user = require_user($db);
            $userId = (int)$user['id'];
            $cartId = get_or_create_active_cart($db, $userId);
            if ($method === 'GET') {
                $payload = build_cart_payload($db, $cartId);
                json_response($payload);
            }
            if ($method === 'POST') {
                $pid = isset($input['productId']) ? (int)$input['productId'] : (int)($_POST['productId'] ?? 0);
                $qty = isset($input['quantity']) ? (int)$input['quantity'] : (int)($_POST['quantity'] ?? 1);
                if ($pid <= 0 || $qty <= 0) json_response(['message' => 'productId and positive quantity are required'], 400);
                $stmtP = $db->prepare('SELECT price FROM products WHERE id = ?');
                $stmtP->bind_param('i', $pid);
                $stmtP->execute();
                $prod = $stmtP->get_result()->fetch_assoc();
                if (!$prod) json_response(['message' => 'Product not found'], 404);
                $price = (float)$prod['price'];
                $stmt = $db->prepare('INSERT INTO cart_items (cart_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), unit_price = VALUES(unit_price)');
                $stmt->bind_param('iiid', $cartId, $pid, $qty, $price);
                $stmt->execute();
                json_response(build_cart_payload($db, $cartId), 201);
            }
            if ($method === 'PUT') {
                $pid = (int)($input['productId'] ?? 0);
                $qty = (int)($input['quantity'] ?? -1);
                if ($pid <= 0 || $qty < 0) json_response(['message' => 'productId and non-negative quantity are required'], 400);
                if ($qty === 0) {
                    $stmt = $db->prepare('DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?');
                    $stmt->bind_param('ii', $cartId, $pid);
                    $stmt->execute();
                } else {
                    $stmt = $db->prepare('UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND product_id = ?');
                    $stmt->bind_param('iii', $qty, $cartId, $pid);
                    $stmt->execute();
                }
                json_response(build_cart_payload($db, $cartId));
            }
            if ($method === 'DELETE') {
                $pid = isset($input['productId']) ? (int)$input['productId'] : (int)($_GET['productId'] ?? 0);
                if ($pid > 0) {
                    $stmt = $db->prepare('DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?');
                    $stmt->bind_param('ii', $cartId, $pid);
                    $stmt->execute();
                } else {
                    $stmt = $db->prepare('DELETE FROM cart_items WHERE cart_id = ?');
                    $stmt->bind_param('i', $cartId);
                    $stmt->execute();
                }
                json_response(build_cart_payload($db, $cartId));
            }
            json_response(['message' => 'Method not allowed'], 405);
            break;

        case 'checkout':
            $user = require_user($db);
            $userId = (int)$user['id'];
            $name = (string)$user['name'];
            $email = (string)$user['email'];
            if ($method !== 'POST') json_response(['message' => 'Method not allowed'], 405);
            $cartId = get_or_create_active_cart($db, $userId);
            $stmt = $db->prepare('SELECT product_id, quantity, unit_price FROM cart_items WHERE cart_id = ?');
            $stmt->bind_param('i', $cartId);
            $stmt->execute();
            $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            if (!$items) json_response(['message' => 'Cart is empty'], 400);
            $total = 0.0;
            foreach ($items as $it) { $total += (int)$it['quantity'] * (float)$it['unit_price']; }
            $total = round($total, 2);
            $orderId = 'ORD-' . strtoupper(substr(uniqid('', true), -6));
            $today = date('Y-m-d');
            $status = 'Processing';
            $payStatus = 'pending';
            $stmtO = $db->prepare('INSERT INTO orders (id, customer, email, date, status, total, user_id, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmtO->bind_param('sssssdis', $orderId, $name, $email, $today, $status, $total, $userId, $payStatus);
            $stmtO->execute();
            $stmtItem = $db->prepare('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');
            foreach ($items as $it) {
                $pid = (int)$it['product_id'];
                $qty = (int)$it['quantity'];
                $price = (float)$it['unit_price'];
                $stmtItem->bind_param('siid', $orderId, $pid, $qty, $price);
                $stmtItem->execute();
            }
            $checked = 'checked_out';
            $stmtC = $db->prepare('UPDATE carts SET status = ? WHERE id = ?');
            $stmtC->bind_param('si', $checked, $cartId);
            $stmtC->execute();
            json_response(['id' => $orderId, 'customer' => $name, 'email' => $email, 'date' => $today, 'status' => $status, 'total' => $total]);
            break;

        case 'receipts':
            if ($method === 'GET') {
                $user = require_user($db);
                $isAdmin = (($user['role'] ?? 'customer') === 'admin');
                $orderId = isset($_GET['orderId']) ? $_GET['orderId'] : null;
                if ($resourceId) {
                    if ($isAdmin) {
                        $stmt = $db->prepare('SELECT * FROM payment_receipts WHERE id = ?');
                        $stmt->bind_param('i', $resourceId);
                        $stmt->execute();
                        $row = $stmt->get_result()->fetch_assoc();
                        if (!$row) json_response(['message' => 'Receipt not found'], 404);
                        json_response($row);
                    } else {
                        $stmt = $db->prepare('SELECT * FROM payment_receipts WHERE id = ? AND user_id = ?');
                        $uid = (int)$user['id'];
                        $stmt->bind_param('ii', $resourceId, $uid);
                        $stmt->execute();
                        $row = $stmt->get_result()->fetch_assoc();
                        if (!$row) json_response(['message' => 'Receipt not found'], 404);
                        json_response($row);
                    }
                }
                if ($isAdmin) {
                    if ($orderId) {
                        $stmt = $db->prepare('SELECT * FROM payment_receipts WHERE order_id = ? ORDER BY uploaded_at DESC');
                        $stmt->bind_param('s', $orderId);
                        $stmt->execute();
                        json_response($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
                    }
                    $rows = $db->query('SELECT * FROM payment_receipts ORDER BY uploaded_at DESC')->fetch_all(MYSQLI_ASSOC);
                    json_response($rows);
                } else {
                    $uid = (int)$user['id'];
                    if ($orderId) {
                        $stmt = $db->prepare('SELECT * FROM payment_receipts WHERE order_id = ? AND user_id = ? ORDER BY uploaded_at DESC');
                        $stmt->bind_param('si', $orderId, $uid);
                        $stmt->execute();
                        json_response($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
                    }
                    $stmt = $db->prepare('SELECT * FROM payment_receipts WHERE user_id = ? ORDER BY uploaded_at DESC');
                    $stmt->bind_param('i', $uid);
                    $stmt->execute();
                    json_response($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
                }
            }

            if ($method === 'POST') {
                $user = require_user($db);
                $userId = (int)$user['id'];
                $orderId = $_POST['orderId'] ?? '';
                if (!$orderId) json_response(['message' => 'orderId is required'], 400);
                if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                    json_response(['message' => 'file upload is required'], 400);
                }
                $isAdmin = (($user['role'] ?? 'customer') === 'admin');
                if ($isAdmin) {
                    $stmt = $db->prepare('SELECT id FROM orders WHERE id = ?');
                    $stmt->bind_param('s', $orderId);
                } else {
                    $stmt = $db->prepare('SELECT id FROM orders WHERE id = ? AND user_id = ?');
                    $stmt->bind_param('si', $orderId, $userId);
                }
                $stmt->execute();
                $order = $stmt->get_result()->fetch_assoc();
                if (!$order) json_response(['message' => 'Order not found or not allowed'], 404);

                $file = $_FILES['file'];
                $orig = $file['name'];
                $mime = $file['type'];
                $size = (int)$file['size'];
                $ext = pathinfo($orig, PATHINFO_EXTENSION);
                $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($orig, PATHINFO_FILENAME));
                $filename = $safeBase . '_' . time() . '_' . bin2hex(random_bytes(3)) . ($ext ? ('.' . $ext) : '');
                $baseDir = dirname(__DIR__);
                $uploadDir = $baseDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'receipts';
                if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
                $destPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
                if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                    json_response(['message' => 'Failed to save file'], 500);
                }
                $publicPath = '/emmaapi/uploads/receipts/' . $filename;
                $status = 'pending';
                $stmtIns = $db->prepare('INSERT INTO payment_receipts (order_id, user_id, file_path, mime_type, file_size, status) VALUES (?, ?, ?, ?, ?, ?)');
                $stmtIns->bind_param('sissis', $orderId, $userId, $publicPath, $mime, $size, $status);
                $stmtIns->execute();
                $newId = $db->insert_id;
                json_response(['id' => $newId, 'order_id' => $orderId, 'file_path' => $publicPath, 'status' => $status], 201);
            }

            if ($method === 'PUT') {
                if (!$resourceId) json_response(['message' => 'Receipt id is required'], 400);
                $admin = require_admin($db);
                $status = $input['status'] ?? '';
                $notes = $input['notes'] ?? null;
                if (!in_array($status, ['approved','rejected'])) json_response(['message' => 'Invalid status'], 400);
                $stmt = $db->prepare('SELECT order_id FROM payment_receipts WHERE id = ?');
                $stmt->bind_param('i', $resourceId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                if (!$row) json_response(['message' => 'Receipt not found'], 404);
                $orderId = $row['order_id'];
                $now = date('Y-m-d H:i:s');
                $adminId = (int)$admin['id'];
                $stmtU = $db->prepare('UPDATE payment_receipts SET status = ?, notes = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ?');
                $stmtU->bind_param('ssisi', $status, $notes, $adminId, $now, $resourceId);
                $stmtU->execute();
                if ($status === 'approved') {
                    $st = $db->prepare('UPDATE orders SET payment_status = ?, paid_at = NOW() WHERE id = ?');
                    $ps = 'approved';
                    $st->bind_param('ss', $ps, $orderId);
                    $st->execute();
                } else {
                    $st = $db->prepare('UPDATE orders SET payment_status = ?, paid_at = NULL WHERE id = ?');
                    $ps = 'rejected';
                    $st->bind_param('ss', $ps, $orderId);
                    $st->execute();
                }
                json_response(['message' => 'Receipt updated', 'status' => $status]);
            }
            json_response(['message' => 'Method not allowed'], 405);
            break;

        default:
            json_response(['message' => 'Not found'], 404);
    }
} catch (Throwable $e) {
    json_response(['message' => 'Server error', 'error' => $e->getMessage()], 500);
} finally {
    if ($db) { $db->close(); }
}



// End of api.php