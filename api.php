<?php
// Stock Management App Backend API with Database
header('Content-Type: application/json');
require_once 'config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? null;

function response($success, $data = [], $message = '') {
    echo json_encode(array_filter([
        'success' => $success,
        'message' => $message,
        ...$data
    ]));
    exit;
}

switch ($action) {
    case 'login':
        $email = $_REQUEST['email'] ?? '';
        $password = $_REQUEST['password'] ?? '';
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            response(false, [], 'User not found');
        }
        if (!password_verify($password, $user['password'])) {
            response(false, [], 'Password salah');
        }
        unset($user['password']);
        response(true, ['user' => $user]);
        break;

    case 'register':
        $name = $_REQUEST['name'] ?? '';
        $email = $_REQUEST['email'] ?? '';
        $password = $_REQUEST['password'] ?? '';
        $role = $_REQUEST['role'] ?? 'staff';
        $outletId = $_REQUEST['outletId'] ?? null;
        $id = (string)(time() . rand(10, 99));
        
        $stmt = $db->prepare('INSERT INTO users (id, name, email, password, role, outletId) VALUES (?, ?, ?, ?, ?, ?)');
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt->execute([$id, $name, $email, $hashedPassword, $role, $outletId ?: null]);
            response(true, ['user' => ['id' => $id, 'name' => $name, 'email' => $email, 'role' => $role, 'outletId' => $outletId]]);
        } catch (Exception $e) {
            response(false, [], 'Registration failed: ' . $e->getMessage());
        }
        break;

    case 'getOutlets':
        $stmt = $db->query('SELECT id, name, address FROM outlets');
        $outlets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($outlets as &$outlet) {
            $stmtStock = $db->prepare('SELECT product_id, quantity FROM outlet_stock WHERE outlet_id = ?');
            $stmtStock->execute([$outlet['id']]);
            $stock = [];
            foreach ($stmtStock->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $stock[$s['product_id']] = $s['quantity'];
            }
            $outlet['stock'] = $stock;
        }
        response(true, ['outlets' => $outlets]);
        break;

    case 'addOutlet':
        $outlet = json_decode($_REQUEST['outlet'] ?? '{}', true);
        $stmt = $db->prepare('INSERT INTO outlets (id, name, address) VALUES (?, ?, ?)');
        try {
            $stmt->execute([$outlet['id'], $outlet['name'], $outlet['address'] ?? '']);
            response(true);
        } catch (Exception $e) {
            response(false, [], 'Add outlet failed: ' . $e->getMessage());
        }
        break;

    case 'updateOutlet':
        $outlet = json_decode($_REQUEST['outlet'] ?? '{}', true);
        $stmt = $db->prepare('UPDATE outlets SET name = ?, address = ? WHERE id = ?');
        try {
            $stmt->execute([$outlet['name'], $outlet['address'] ?? '', $outlet['id']]);
            if (isset($outlet['stock'])) {
                $db->prepare('DELETE FROM outlet_stock WHERE outlet_id = ?')->execute([$outlet['id']]);
                foreach ($outlet['stock'] as $productId => $qty) {
                    $db->prepare('INSERT INTO outlet_stock (outlet_id, product_id, quantity) VALUES (?, ?, ?)')
                        ->execute([$outlet['id'], $productId, $qty]);
                }
            }
            response(true);
        } catch (Exception $e) {
            response(false, [], 'Update outlet failed: ' . $e->getMessage());
        }
        break;

    case 'deleteOutlet':
        $id = $_REQUEST['outletId'] ?? '';
        try {
            $db->prepare('DELETE FROM outlets WHERE id = ?')->execute([$id]);
            response(true);
        } catch (Exception $e) {
            response(false, [], 'Delete outlet failed: ' . $e->getMessage());
        }
        break;

    case 'getProducts':
        $stmt = $db->query('SELECT id, name, category, stockPusat, price FROM products');
        response(true, ['products' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'addProduct':
        $prod = json_decode($_REQUEST['product'] ?? '{}', true);
        $stmt = $db->prepare('INSERT INTO products (id, name, category, stockPusat, price) VALUES (?, ?, ?, ?, ?)');
        try {
            $stmt->execute([$prod['id'], $prod['name'], $prod['category'] ?? '', $prod['stockPusat'] ?? 0, $prod['price'] ?? 0]);
            response(true);
        } catch (Exception $e) {
            response(false, [], 'Add product failed: ' . $e->getMessage());
        }
        break;

    case 'updateProduct':
        $prod = json_decode($_REQUEST['product'] ?? '{}', true);
        $stmt = $db->prepare('UPDATE products SET name = ?, category = ?, stockPusat = ?, price = ? WHERE id = ?');
        try {
            $stmt->execute([$prod['name'], $prod['category'] ?? '', $prod['stockPusat'] ?? 0, $prod['price'] ?? 0, $prod['id']]);
            response(true);
        } catch (Exception $e) {
            response(false, [], 'Update product failed: ' . $e->getMessage());
        }
        break;

    case 'deleteProduct':
        $id = $_REQUEST['productId'] ?? '';
        try {
            $db->prepare('DELETE FROM outlet_stock WHERE product_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
            response(true);
        } catch (Exception $e) {
            response(false, [], 'Delete product failed: ' . $e->getMessage());
        }
        break;

    case 'getInvoices':
        $stmt = $db->query('SELECT id, outlet_id as outletId, total_amount as totalAmount, is_paid as isPaid, created_at as date FROM invoices');
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($invoices as &$inv) {
            $inv['isPaid'] = (bool)$inv['isPaid'];
            $itemStmt = $db->prepare('SELECT product_id as productId, product_name as productName, quantity, price FROM invoice_items WHERE invoice_id = ?');
            $itemStmt->execute([$inv['id']]);
            $inv['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        response(true, ['invoices' => $invoices]);
        break;

    case 'addPayment':
        $pay = json_decode($_REQUEST['payment'] ?? '{}', true);
        $proofImage = $pay['proofImage'] ?? null;
        $fileName = null;

        if ($proofImage && $pay['paymentMethod'] === 'Transfer') {
            // Ensure uploads directory exists
            if (!is_dir('uploads')) {
                mkdir('uploads', 0777, true);
            }

            // Create filename: buktitf_tanggal_jam.jpg
            $dateStr = date('Ymd_His');
            $fileName = 'buktitf_' . $dateStr . '.jpg';
            $filePath = 'uploads/' . $fileName;

            // Save base64 to file
            $data = base64_decode($proofImage);
            file_put_contents($filePath, $data);
        }

        $stmt = $db->prepare('INSERT INTO payments (id, invoice_id, amount, payment_method, proof_image) VALUES (?, ?, ?, ?, ?)');
        try {
            $stmt->execute([
                $pay['id'] ?? (string)time(), 
                $pay['invoiceId'] ?? '', 
                $pay['amount'] ?? 0,
                $pay['paymentMethod'] ?? 'Tunai',
                $fileName // Store filename instead of base64
            ]);
            response(true);
        } catch (Exception $e) {
            response(false, [], 'Add payment failed: ' . $e->getMessage());
        }
        break;

    case 'markInvoicePaid':
        $id = $_REQUEST['invoiceId'] ?? '';
        try {
            $db->prepare('UPDATE invoices SET is_paid = 1 WHERE id = ?')->execute([$id]);
            response(true);
        } catch (Exception $e) {
            response(false, [], 'Mark invoice failed: ' . $e->getMessage());
        }
        break;

    case 'sendStock':
        $outletId = $_REQUEST['outletId'] ?? '';
        $productId = $_REQUEST['productId'] ?? '';
        $qty = intval($_REQUEST['quantity'] ?? 0);
        try {
            $db->prepare('UPDATE products SET stockPusat = GREATEST(0, stockPusat - ?) WHERE id = ?')->execute([$qty, $productId]);
            $stmt = $db->prepare('INSERT INTO outlet_stock (outlet_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?');
            $stmt->execute([$outletId, $productId, $qty, $qty]);
            $consId = (string)time();
            $db->prepare('INSERT INTO consignments (id, outlet_id, product_id, quantity, status) VALUES (?, ?, ?, ?, ?)')
                ->execute([$consId, $outletId, $productId, $qty, 'sent']);
            response(true);
        } catch (Exception $e) {
            response(false, [], 'Send stock failed: ' . $e->getMessage());
        }
        break;

    case 'receiveStock':
        $consignmentId = $_REQUEST['consignmentId'] ?? '';
        $proofImage = $_REQUEST['proofImage'] ?? null;
        $lat = $_REQUEST['latitude'] ?? null;
        $lng = $_REQUEST['longitude'] ?? null;
        $fileName = null;

        if ($proofImage) {
            if (!is_dir('uploads')) {
                mkdir('uploads', 0777, true);
            }
            $dateStr = date('Ymd_His');
            $fileName = 'buktircv_' . $dateStr . '.jpg';
            $filePath = 'uploads/' . $fileName;
            $data = base64_decode($proofImage);
            file_put_contents($filePath, $data);
        }

        try {
            $stmt = $db->prepare('UPDATE consignments SET status = "received", proof_image = ?, latitude = ?, longitude = ?, received_at = NOW() WHERE id = ?');
            $stmt->execute([$fileName, $lat, $lng, $consignmentId]);
            response(true);
        } catch (Exception $e) {
            response(false, [], 'Receive stock failed: ' . $e->getMessage());
        }
        break;

    case 'recordSale':
        $outletId = $_REQUEST['outletId'] ?? '';
        $productId = $_REQUEST['productId'] ?? '';
        $qty = intval($_REQUEST['quantity'] ?? 0);
        try {
            $stmt = $db->prepare('UPDATE outlet_stock SET quantity = GREATEST(0, quantity - ?) WHERE outlet_id = ? AND product_id = ?');
            $stmt->execute([$qty, $outletId, $productId]);
            
            $prodStmt = $db->prepare('SELECT name, price FROM products WHERE id = ?');
            $prodStmt->execute([$productId]);
            $prod = $prodStmt->fetch(PDO::FETCH_ASSOC);
            
            $invId = 'INV-' . (string)time();
            $totalAmount = ($prod['price'] ?? 0) * $qty;
            $db->prepare('INSERT INTO invoices (id, outlet_id, total_amount, is_paid) VALUES (?, ?, ?, 0)')
                ->execute([$invId, $outletId, $totalAmount]);
            
            $db->prepare('INSERT INTO invoice_items (invoice_id, product_id, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)')
                ->execute([$invId, $productId, $prod['name'] ?? '', $qty, $prod['price'] ?? 0]);
            
            response(true, ['invoice' => ['id' => $invId, 'outletId' => $outletId, 'totalAmount' => $totalAmount, 'items' => [['productId' => $productId, 'productName' => $prod['name'] ?? '', 'quantity' => $qty, 'price' => $prod['price'] ?? 0]]]]);
        } catch (Exception $e) {
            response(false, [], 'Record sale failed: ' . $e->getMessage());
        }
        break;

    case 'getConsignments':
        $stmt = $db->query('SELECT id, outlet_id as outletId, product_id as productId, quantity, status, created_at as date FROM consignments');
        response(true, ['consignments' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'getPayments':
        $stmt = $db->query('SELECT id, invoice_id as invoiceId, amount, payment_method as paymentMethod, proof_image as proofImage, created_at as date FROM payments');
        response(true, ['payments' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'getExpenses':
        $stmt = $db->query('SELECT id, outlet_id as outletId, amount, category, description, date FROM expenses');
        response(true, ['expenses' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'addExpense':
        $ex = json_decode($_REQUEST['expense'] ?? '{}', true);
        $stmt = $db->prepare('INSERT INTO expenses (id, outlet_id, amount, category, description, date) VALUES (?, ?, ?, ?, ?, ?)');
        try {
            $stmt->execute([
                $ex['id'] ?? (string)time(),
                $ex['outletId'] ?? null,
                $ex['amount'] ?? 0,
                $ex['category'] ?? 'Lainnya',
                $ex['description'] ?? '',
                substr($ex['date'], 0, 10) // Only take YYYY-MM-DD
            ]);
            response(true);
        } catch (Exception $e) {
            response(false, [], 'Add expense failed: ' . $e->getMessage());
        }
        break;

    case 'deleteExpense':
        $id = $_REQUEST['expenseId'] ?? '';
        try {
            $db->prepare('DELETE FROM expenses WHERE id = ?')->execute([$id]);
            response(true);
        } catch (Exception $e) {
            response(false, [], 'Delete expense failed: ' . $e->getMessage());
        }
        break;

    default:
        response(false, [], 'Unknown action');
}
