<?php
// Start output buffering FIRST to catch any output
ob_start();

// Set JSON header early
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Handle fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Server error: ' . htmlspecialchars($error['message']) . ' in ' . basename($error['file']) . ' on line ' . $error['line']
        ]);
        exit;
    }
});

try {
    session_start();

    // Handle database connection manually to avoid die() output
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db = "bookshelf_db";

    $conn = new mysqli($host, $user, $pass, $db);

    if($conn->connect_error){
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
        exit;
    }

    if (!isset($_SESSION['user_id'])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Please login first.']);
        exit;
    }

    $user_id = $_SESSION['user_id'];

    // Billing details
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
        exit;
    }

    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $address = $data['address'] ?? '';
    $payment = $data['payment'] ?? '';

    // Calculate total
    $stmt = $conn->prepare("SELECT b.price FROM cart c JOIN books b ON c.book_id = b.id WHERE c.user_id = ?");
    if (!$stmt) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database query error: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error fetching cart: ' . $stmt->error]);
        $stmt->close();
        exit;
    }

    $result = $stmt->get_result();

    $total = 0.0;
    while ($row = $result->fetch_assoc()) {
        $price_str = trim($row['price']);
        $price_num = floatval(preg_replace('/[^0-9.]/', '', $price_str));
        $total += $price_num;
    }
    $stmt->close();

    if ($total <= 0) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Cart is empty.']);
        exit;
    }

    // Check if orders table exists, if not create it, or fix it if columns are missing
    $table_check = $conn->query("SHOW TABLES LIKE 'orders'");
    if (!$table_check) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database error checking tables: ' . $conn->error]);
        exit;
    }
    if ($table_check->num_rows == 0) {
        // Table doesn't exist, create it
        $create_orders = $conn->query("CREATE TABLE IF NOT EXISTS `orders` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `total_price` decimal(10,2) NOT NULL,
          `name` varchar(255) DEFAULT NULL,
          `email` varchar(255) DEFAULT NULL,
          `address` text DEFAULT NULL,
          `payment_method` varchar(50) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        if (!$create_orders) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Error creating orders table: ' . $conn->error]);
            exit;
        }
    } else {
        // Table exists, check if it has the required columns and add missing ones
        $columns_result = $conn->query("SHOW COLUMNS FROM `orders`");
        $existing_columns = [];
        if ($columns_result) {
            while ($row = $columns_result->fetch_assoc()) {
                $existing_columns[] = $row['Field'];
            }
        }
        
        // Add missing columns
        if (!in_array('total_price', $existing_columns)) {
            $alter = $conn->query("ALTER TABLE `orders` ADD COLUMN `total_price` decimal(10,2) NOT NULL DEFAULT 0.00");
            if (!$alter) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Error adding total_price column: ' . $conn->error]);
                exit;
            }
        }
        if (!in_array('name', $existing_columns)) {
            $conn->query("ALTER TABLE `orders` ADD COLUMN `name` varchar(255) DEFAULT NULL");
        }
        if (!in_array('email', $existing_columns)) {
            $conn->query("ALTER TABLE `orders` ADD COLUMN `email` varchar(255) DEFAULT NULL");
        }
        if (!in_array('address', $existing_columns)) {
            $conn->query("ALTER TABLE `orders` ADD COLUMN `address` text DEFAULT NULL");
        }
        if (!in_array('payment_method', $existing_columns)) {
            $conn->query("ALTER TABLE `orders` ADD COLUMN `payment_method` varchar(50) DEFAULT NULL");
        }
        if (!in_array('created_at', $existing_columns)) {
            $conn->query("ALTER TABLE `orders` ADD COLUMN `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP");
        }
    }

    // Check if order_items table exists, if not create it
    $table_check = $conn->query("SHOW TABLES LIKE 'order_items'");
    if (!$table_check) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database error checking tables: ' . $conn->error]);
        exit;
    }
    if ($table_check->num_rows == 0) {
        $create_order_items = $conn->query("CREATE TABLE IF NOT EXISTS `order_items` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `order_id` int(11) NOT NULL,
          `book_id` int(11) NOT NULL,
          `quantity` int(11) NOT NULL DEFAULT 1,
          `price` varchar(50) NOT NULL,
          PRIMARY KEY (`id`),
          KEY `order_id` (`order_id`),
          KEY `book_id` (`book_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        if (!$create_order_items) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Error creating order_items table: ' . $conn->error]);
            exit;
        }
    }

    // Check which total column exists (total or total_price) - prefer 'total' if it exists
    $columns_result = $conn->query("SHOW COLUMNS FROM `orders`");
    $has_total = false;
    $has_total_price = false;
    if ($columns_result) {
        while ($row = $columns_result->fetch_assoc()) {
            if ($row['Field'] == 'total') $has_total = true;
            if ($row['Field'] == 'total_price') $has_total_price = true;
        }
    }
    
    // Use 'total' if it exists (since that's what has real values), otherwise use 'total_price'
    // Note: We can't use variables in prepared statement column names, so we need to build the query
    $total_column = $has_total ? 'total' : 'total_price';
    
    // Sanitize column name to prevent SQL injection (whitelist approach)
    $allowed_columns = ['total', 'total_price'];
    if (!in_array($total_column, $allowed_columns)) {
        $total_column = 'total_price'; // fallback
    }
    
    // Debug: Log the values being inserted
    error_log("Inserting order - user_id: $user_id, total: $total, name: '$name', email: '$email', address: '$address', payment: '$payment'");
    
    // Create order - build query with proper column name (prepared statements don't allow column names as params)
    $sql = "INSERT INTO orders (user_id, `$total_column`, name, email, address, payment_method) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database error preparing statement: ' . $conn->error . ' SQL: ' . $sql]);
        exit;
    }

    // Bind parameters - make sure types match: i=integer, d=double, s=string
    // Also ensure values are not null
    $name = $name ?: '';
    $email = $email ?: '';
    $address = $address ?: '';
    $payment = $payment ?: '';
    
    $stmt->bind_param("idssss", $user_id, $total, $name, $email, $address, $payment);
    
    // Execute and check for errors
    if (!$stmt->execute()) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Order create error: ' . $stmt->error . ' | SQL: ' . $sql]);
        $stmt->close();
        exit;
    }
    
    // Verify the insert worked
    if ($stmt->affected_rows <= 0) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Order was not created. No rows affected.']);
        $stmt->close();
        exit;
    }
    $order_id = $conn->insert_id;
    $stmt->close();

    // Save order items
    $stmt = $conn->prepare("INSERT INTO order_items (order_id, book_id, quantity, price) SELECT ?, c.book_id, 1, b.price FROM cart c JOIN books b ON c.book_id = b.id WHERE c.user_id = ?");
    if (!$stmt) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param("ii", $order_id, $user_id);
    if (!$stmt->execute()) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Order items error: ' . $stmt->error]);
        $stmt->close();
        exit;
    }
    $stmt->close();

    // Clear cart
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    if (!$stmt) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Cart clear error: ' . $stmt->error]);
        $stmt->close();
        exit;
    }
    $stmt->close();

    // Clean output and send JSON
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully!',
        'total' => '$' . number_format($total, 2)
    ]);

    $conn->close();
    exit;

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine()
    ]);
    exit;
} catch (Error $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine()
    ]);
    exit;
}
