<?php
// Inventory Management API - Connected to Roulette Database
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection to existing roulette database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "roulette";

// Set Georgetown timezone
date_default_timezone_set('America/Guyana');

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $action = $_GET['action'] ?? 'get_inventory';
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($action) {
        case 'get_inventory':
            echo json_encode(getInventoryData($conn));
            break;
        case 'get_overview':
            echo json_encode(getOverviewMetrics($conn));
            break;
        case 'add_item':
            if ($method === 'POST') {
                echo json_encode(addInventoryItem($conn));
            }
            break;
        case 'update_item':
            if ($method === 'POST') {
                echo json_encode(updateInventoryItem($conn));
            }
            break;
        case 'delete_item':
            if ($method === 'POST') {
                echo json_encode(deleteInventoryItem($conn));
            }
            break;
        case 'adjust_stock':
            if ($method === 'POST') {
                echo json_encode(adjustStock($conn));
            }
            break;
        case 'get_suppliers':
            echo json_encode(getSuppliers($conn));
            break;
        case 'get_categories':
            echo json_encode(getCategories($conn));
            break;
        case 'get_low_stock':
            echo json_encode(getLowStockAlerts($conn));
            break;
        default:
            throw new Exception("Invalid action");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

function getInventoryData($conn) {
    // First, ensure inventory tables exist, if not create them
    createInventoryTablesIfNotExist($conn);
    
    $stmt = $conn->prepare("
        SELECT 
            i.item_id,
            i.item_code,
            i.item_name,
            i.description,
            i.category,
            i.current_stock,
            i.min_stock_level,
            i.unit_price,
            i.cost_price,
            (i.current_stock * i.unit_price) as total_value,
            s.supplier_name,
            i.location,
            i.status,
            i.updated_at,
            CASE 
                WHEN i.current_stock = 0 THEN 'out'
                WHEN i.current_stock <= i.min_stock_level THEN 'low'
                WHEN i.current_stock <= (i.min_stock_level * 2) THEN 'medium'
                ELSE 'high'
            END as stock_level
        FROM inventory_items i
        LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
        WHERE i.status = 'active'
        ORDER BY i.item_name
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    return [
        'status' => 'success',
        'data' => $items,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

function getOverviewMetrics($conn) {
    $metrics = [];
    
    // Total items
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM inventory_items WHERE status = 'active'");
    $stmt->execute();
    $metrics['total_items'] = $stmt->get_result()->fetch_assoc()['total'];
    
    // Low stock items
    $stmt = $conn->prepare("
        SELECT COUNT(*) as low_stock 
        FROM inventory_items 
        WHERE current_stock <= min_stock_level AND status = 'active'
    ");
    $stmt->execute();
    $metrics['low_stock_items'] = $stmt->get_result()->fetch_assoc()['low_stock'];
    
    // Out of stock items
    $stmt = $conn->prepare("
        SELECT COUNT(*) as out_of_stock 
        FROM inventory_items 
        WHERE current_stock = 0 AND status = 'active'
    ");
    $stmt->execute();
    $metrics['out_of_stock'] = $stmt->get_result()->fetch_assoc()['out_of_stock'];
    
    // Total inventory value
    $stmt = $conn->prepare("
        SELECT SUM(current_stock * unit_price) as total_value 
        FROM inventory_items 
        WHERE status = 'active'
    ");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $metrics['total_value'] = $result['total_value'] ?? 0;
    
    return [
        'status' => 'success',
        'data' => $metrics
    ];
}

function addInventoryItem($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $conn->prepare("
        INSERT INTO inventory_items 
        (item_code, item_name, description, category, supplier_id, current_stock, 
         min_stock_level, unit_price, cost_price, location, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $supplier_id = getSupplierIdByName($conn, $input['supplier']);
    $created_by = $_SESSION['user_id'] ?? 1;
    
    $stmt->bind_param("ssssiiiddsi", 
        $input['code'],
        $input['name'],
        $input['description'],
        $input['category'],
        $supplier_id,
        $input['currentStock'],
        $input['minStock'],
        $input['unitPrice'],
        $input['costPrice'] ?? $input['unitPrice'],
        $input['location'] ?? 'Main Warehouse',
        $created_by
    );
    
    if ($stmt->execute()) {
        $item_id = $conn->insert_id;
        
        // Log stock movement
        logStockMovement($conn, $item_id, 'in', $input['currentStock'], 
                        $input['unitPrice'], 'Initial stock', $created_by);
        
        return [
            'status' => 'success',
            'message' => 'Item added successfully',
            'item_id' => $item_id
        ];
    } else {
        throw new Exception("Failed to add item: " . $stmt->error);
    }
}

function updateInventoryItem($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $conn->prepare("
        UPDATE inventory_items 
        SET item_code = ?, item_name = ?, description = ?, category = ?, 
            supplier_id = ?, current_stock = ?, min_stock_level = ?, 
            unit_price = ?, cost_price = ?, location = ?, updated_by = ?
        WHERE item_id = ?
    ");
    
    $supplier_id = getSupplierIdByName($conn, $input['supplier']);
    $updated_by = $_SESSION['user_id'] ?? 1;
    
    $stmt->bind_param("ssssiiiddsii", 
        $input['code'],
        $input['name'],
        $input['description'],
        $input['category'],
        $supplier_id,
        $input['currentStock'],
        $input['minStock'],
        $input['unitPrice'],
        $input['costPrice'] ?? $input['unitPrice'],
        $input['location'] ?? 'Main Warehouse',
        $updated_by,
        $input['id']
    );
    
    if ($stmt->execute()) {
        return [
            'status' => 'success',
            'message' => 'Item updated successfully'
        ];
    } else {
        throw new Exception("Failed to update item: " . $stmt->error);
    }
}

function adjustStock($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $item_id = $input['item_id'];
    $adjustment_type = $input['adjustment_type'];
    $quantity = $input['quantity'];
    $reason = $input['reason'];
    $user_id = $_SESSION['user_id'] ?? 1;
    
    // Get current stock
    $stmt = $conn->prepare("SELECT current_stock, unit_price FROM inventory_items WHERE item_id = ?");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $current_stock = $result['current_stock'];
    $unit_price = $result['unit_price'];
    
    $new_stock = $current_stock;
    
    switch ($adjustment_type) {
        case 'in':
            $new_stock = $current_stock + $quantity;
            break;
        case 'out':
            $new_stock = max(0, $current_stock - $quantity);
            break;
        case 'adjustment':
            $new_stock = $quantity;
            break;
    }
    
    // Update stock
    $stmt = $conn->prepare("UPDATE inventory_items SET current_stock = ? WHERE item_id = ?");
    $stmt->bind_param("ii", $new_stock, $item_id);
    
    if ($stmt->execute()) {
        // Log the movement
        logStockMovement($conn, $item_id, $adjustment_type, $quantity, $unit_price, $reason, $user_id);
        
        return [
            'status' => 'success',
            'message' => 'Stock adjusted successfully',
            'new_stock' => $new_stock
        ];
    } else {
        throw new Exception("Failed to adjust stock: " . $stmt->error);
    }
}

function getSuppliers($conn) {
    $stmt = $conn->prepare("SELECT * FROM suppliers WHERE status = 'active' ORDER BY supplier_name");
    $stmt->execute();
    $result = $stmt->get_result();
    $suppliers = [];
    
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
    
    return [
        'status' => 'success',
        'data' => $suppliers
    ];
}

function getCategories($conn) {
    $categories = [
        ['id' => 'equipment', 'name' => 'Equipment'],
        ['id' => 'supplies', 'name' => 'Supplies'],
        ['id' => 'maintenance', 'name' => 'Maintenance'],
        ['id' => 'office', 'name' => 'Office']
    ];
    
    return [
        'status' => 'success',
        'data' => $categories
    ];
}

function getLowStockAlerts($conn) {
    $stmt = $conn->prepare("
        SELECT 
            i.item_id,
            i.item_code,
            i.item_name,
            i.current_stock,
            i.min_stock_level,
            i.category,
            s.supplier_name,
            CASE 
                WHEN i.current_stock = 0 THEN 'critical'
                WHEN i.current_stock <= i.min_stock_level THEN 'warning'
                ELSE 'normal'
            END as alert_level
        FROM inventory_items i
        LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
        WHERE i.current_stock <= i.min_stock_level 
        AND i.status = 'active'
        ORDER BY i.current_stock ASC, i.item_name
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    $alerts = [];
    
    while ($row = $result->fetch_assoc()) {
        $alerts[] = $row;
    }
    
    return [
        'status' => 'success',
        'data' => $alerts
    ];
}

function getSupplierIdByName($conn, $supplier_name) {
    $stmt = $conn->prepare("SELECT supplier_id FROM suppliers WHERE supplier_name = ?");
    $stmt->bind_param("s", $supplier_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['supplier_id'];
    }
    
    // If supplier doesn't exist, create it
    $stmt = $conn->prepare("INSERT INTO suppliers (supplier_name, status) VALUES (?, 'active')");
    $stmt->bind_param("s", $supplier_name);
    $stmt->execute();
    return $conn->insert_id;
}

function logStockMovement($conn, $item_id, $movement_type, $quantity, $unit_price, $reason, $user_id) {
    $stmt = $conn->prepare("
        INSERT INTO stock_movements 
        (item_id, movement_type, quantity, unit_price, total_value, reason, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $total_value = $quantity * $unit_price;
    
    $stmt->bind_param("isiddsi", 
        $item_id, 
        $movement_type, 
        $quantity, 
        $unit_price, 
        $total_value, 
        $reason, 
        $user_id
    );
    
    $stmt->execute();
}

function createInventoryTablesIfNotExist($conn) {
    // Create inventory tables if they don't exist
    $tables = [
        "CREATE TABLE IF NOT EXISTS inventory_items (
            item_id INT AUTO_INCREMENT PRIMARY KEY,
            item_code VARCHAR(50) UNIQUE NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            description TEXT,
            category ENUM('equipment', 'supplies', 'maintenance', 'office') NOT NULL,
            supplier_id INT,
            current_stock INT DEFAULT 0,
            min_stock_level INT DEFAULT 0,
            unit_price DECIMAL(10,2) DEFAULT 0.00,
            cost_price DECIMAL(10,2) DEFAULT 0.00,
            location VARCHAR(100) DEFAULT 'Main Warehouse',
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by INT,
            updated_by INT,
            INDEX idx_item_code (item_code),
            INDEX idx_category (category),
            INDEX idx_supplier (supplier_id)
        )",
        
        "CREATE TABLE IF NOT EXISTS suppliers (
            supplier_id INT AUTO_INCREMENT PRIMARY KEY,
            supplier_name VARCHAR(255) NOT NULL,
            contact_person VARCHAR(255),
            email VARCHAR(255),
            phone VARCHAR(50),
            address TEXT,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS stock_movements (
            movement_id INT AUTO_INCREMENT PRIMARY KEY,
            item_id INT NOT NULL,
            movement_type ENUM('in', 'out', 'adjustment') NOT NULL,
            quantity INT NOT NULL,
            unit_price DECIMAL(10,2) DEFAULT 0.00,
            total_value DECIMAL(12,2) DEFAULT 0.00,
            reason VARCHAR(255),
            movement_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by INT,
            FOREIGN KEY (item_id) REFERENCES inventory_items(item_id) ON DELETE CASCADE
        )"
    ];
    
    foreach ($tables as $sql) {
        $conn->query($sql);
    }
    
    // Insert default suppliers if table is empty
    $result = $conn->query("SELECT COUNT(*) as count FROM suppliers");
    if ($result->fetch_assoc()['count'] == 0) {
        $conn->query("
            INSERT INTO suppliers (supplier_name, contact_person, email, phone, status) VALUES
            ('Tech Solutions Ltd', 'John Smith', 'john@techsolutions.com', '+592-123-4567', 'active'),
            ('Office Depot', 'Sarah Johnson', 'sarah@officedepot.gy', '+592-234-5678', 'active'),
            ('Equipment Pro', 'Mike Wilson', 'mike@equipmentpro.com', '+592-345-6789', 'active')
        ");
    }
}
?>
