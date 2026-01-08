<?php
// Purchase Orders API - Connected to Roulette Database
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

    $action = $_GET['action'] ?? 'get_purchase_orders';
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($action) {
        case 'get_purchase_orders':
            echo json_encode(getPurchaseOrders($conn));
            break;
        case 'get_overview':
            echo json_encode(getOverviewMetrics($conn));
            break;
        case 'create_po':
            if ($method === 'POST') {
                echo json_encode(createPurchaseOrder($conn));
            }
            break;
        case 'update_po':
            if ($method === 'POST') {
                echo json_encode(updatePurchaseOrder($conn));
            }
            break;
        case 'approve_po':
            if ($method === 'POST') {
                echo json_encode(approvePurchaseOrder($conn));
            }
            break;
        case 'cancel_po':
            if ($method === 'POST') {
                echo json_encode(cancelPurchaseOrder($conn));
            }
            break;
        case 'get_po_details':
            echo json_encode(getPODetails($conn));
            break;
        case 'get_suppliers':
            echo json_encode(getSuppliers($conn));
            break;
        case 'get_inventory_items':
            echo json_encode(getInventoryItems($conn));
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

function getPurchaseOrders($conn) {
    // First, ensure purchase order tables exist
    createPurchaseOrderTablesIfNotExist($conn);
    
    $stmt = $conn->prepare("
        SELECT 
            po.po_id,
            po.po_number,
            po.order_date,
            po.expected_delivery_date,
            po.actual_delivery_date,
            po.status,
            po.subtotal,
            po.tax_amount,
            po.total_amount,
            po.notes,
            po.created_at,
            po.updated_at,
            s.supplier_name,
            s.contact_person,
            s.email,
            s.phone,
            COUNT(poi.po_item_id) as items_count,
            CASE 
                WHEN po.status = 'completed' THEN 100
                WHEN po.status = 'partial' THEN 50
                WHEN po.status = 'confirmed' THEN 75
                WHEN po.status = 'sent' THEN 25
                ELSE 0
            END as progress
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
        LEFT JOIN purchase_order_items poi ON po.po_id = poi.po_id
        GROUP BY po.po_id
        ORDER BY po.created_at DESC
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];
    
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    
    return [
        'status' => 'success',
        'data' => $orders,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

function getOverviewMetrics($conn) {
    $metrics = [];
    
    // Total POs
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM purchase_orders");
    $stmt->execute();
    $metrics['total_pos'] = $stmt->get_result()->fetch_assoc()['total'];
    
    // Pending POs
    $stmt = $conn->prepare("
        SELECT COUNT(*) as pending 
        FROM purchase_orders 
        WHERE status IN ('draft', 'sent')
    ");
    $stmt->execute();
    $metrics['pending_pos'] = $stmt->get_result()->fetch_assoc()['pending'];
    
    // Overdue POs
    $stmt = $conn->prepare("
        SELECT COUNT(*) as overdue 
        FROM purchase_orders 
        WHERE expected_delivery_date < CURDATE() 
        AND status NOT IN ('completed', 'cancelled')
    ");
    $stmt->execute();
    $metrics['overdue_pos'] = $stmt->get_result()->fetch_assoc()['overdue'];
    
    // Total value
    $stmt = $conn->prepare("
        SELECT SUM(total_amount) as total_value 
        FROM purchase_orders 
        WHERE status != 'cancelled'
    ");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $metrics['total_value'] = $result['total_value'] ?? 0;
    
    return [
        'status' => 'success',
        'data' => $metrics
    ];
}

function createPurchaseOrder($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Generate PO number if not provided
    if (empty($input['po_number'])) {
        $input['po_number'] = generatePONumber($conn);
    }
    
    $conn->begin_transaction();
    
    try {
        // Insert purchase order
        $stmt = $conn->prepare("
            INSERT INTO purchase_orders 
            (po_number, supplier_id, order_date, expected_delivery_date, 
             status, subtotal, tax_amount, total_amount, notes, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $created_by = $_SESSION['user_id'] ?? 1;
        
        $stmt->bind_param("sisssdddsi", 
            $input['po_number'],
            $input['supplier_id'],
            $input['order_date'],
            $input['expected_delivery_date'],
            $input['status'] ?? 'draft',
            $input['subtotal'],
            $input['tax_amount'] ?? 0,
            $input['total_amount'],
            $input['notes'] ?? '',
            $created_by
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create purchase order: " . $stmt->error);
        }
        
        $po_id = $conn->insert_id;
        
        // Insert PO items
        if (!empty($input['items'])) {
            $item_stmt = $conn->prepare("
                INSERT INTO purchase_order_items 
                (po_id, item_id, quantity_ordered, unit_price, total_price, notes) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($input['items'] as $item) {
                $item_stmt->bind_param("iiidds", 
                    $po_id,
                    $item['item_id'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total_price'],
                    $item['notes'] ?? ''
                );
                
                if (!$item_stmt->execute()) {
                    throw new Exception("Failed to add PO item: " . $item_stmt->error);
                }
            }
        }
        
        $conn->commit();
        
        return [
            'status' => 'success',
            'message' => 'Purchase order created successfully',
            'po_id' => $po_id,
            'po_number' => $input['po_number']
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function updatePurchaseOrder($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $conn->prepare("
        UPDATE purchase_orders 
        SET supplier_id = ?, order_date = ?, expected_delivery_date = ?, 
            status = ?, subtotal = ?, tax_amount = ?, total_amount = ?, 
            notes = ?, updated_by = ?
        WHERE po_id = ?
    ");
    
    $updated_by = $_SESSION['user_id'] ?? 1;
    
    $stmt->bind_param("isssdddsii", 
        $input['supplier_id'],
        $input['order_date'],
        $input['expected_delivery_date'],
        $input['status'],
        $input['subtotal'],
        $input['tax_amount'],
        $input['total_amount'],
        $input['notes'],
        $updated_by,
        $input['po_id']
    );
    
    if ($stmt->execute()) {
        return [
            'status' => 'success',
            'message' => 'Purchase order updated successfully'
        ];
    } else {
        throw new Exception("Failed to update purchase order: " . $stmt->error);
    }
}

function approvePurchaseOrder($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    $po_id = $input['po_id'];
    
    $stmt = $conn->prepare("
        UPDATE purchase_orders 
        SET status = 'confirmed', updated_by = ?
        WHERE po_id = ?
    ");
    
    $updated_by = $_SESSION['user_id'] ?? 1;
    $stmt->bind_param("ii", $updated_by, $po_id);
    
    if ($stmt->execute()) {
        return [
            'status' => 'success',
            'message' => 'Purchase order approved successfully'
        ];
    } else {
        throw new Exception("Failed to approve purchase order: " . $stmt->error);
    }
}

function cancelPurchaseOrder($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    $po_id = $input['po_id'];
    
    $stmt = $conn->prepare("
        UPDATE purchase_orders 
        SET status = 'cancelled', updated_by = ?
        WHERE po_id = ?
    ");
    
    $updated_by = $_SESSION['user_id'] ?? 1;
    $stmt->bind_param("ii", $updated_by, $po_id);
    
    if ($stmt->execute()) {
        return [
            'status' => 'success',
            'message' => 'Purchase order cancelled successfully'
        ];
    } else {
        throw new Exception("Failed to cancel purchase order: " . $stmt->error);
    }
}

function getPODetails($conn) {
    $po_id = $_GET['po_id'] ?? 0;
    
    // Get PO header
    $stmt = $conn->prepare("
        SELECT 
            po.*,
            s.supplier_name,
            s.contact_person,
            s.email,
            s.phone,
            s.address
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
        WHERE po.po_id = ?
    ");
    
    $stmt->bind_param("i", $po_id);
    $stmt->execute();
    $po_result = $stmt->get_result();
    
    if ($po_result->num_rows === 0) {
        throw new Exception("Purchase order not found");
    }
    
    $po_data = $po_result->fetch_assoc();
    
    // Get PO items
    $items_stmt = $conn->prepare("
        SELECT 
            poi.*,
            i.item_name,
            i.item_code,
            i.description
        FROM purchase_order_items poi
        LEFT JOIN inventory_items i ON poi.item_id = i.item_id
        WHERE poi.po_id = ?
    ");
    
    $items_stmt->bind_param("i", $po_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    $items = [];
    while ($row = $items_result->fetch_assoc()) {
        $items[] = $row;
    }
    
    $po_data['items'] = $items;
    
    return [
        'status' => 'success',
        'data' => $po_data
    ];
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

function getInventoryItems($conn) {
    $stmt = $conn->prepare("
        SELECT item_id, item_code, item_name, unit_price, current_stock 
        FROM inventory_items 
        WHERE status = 'active' 
        ORDER BY item_name
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    return [
        'status' => 'success',
        'data' => $items
    ];
}

function generatePONumber($conn) {
    $today = new Date();
    $year = $today->format('Y');
    $month = $today->format('m');
    $day = $today->format('d');
    
    // Get next sequence number for today
    $stmt = $conn->prepare("
        SELECT COUNT(*) + 1 as next_seq 
        FROM purchase_orders 
        WHERE DATE(created_at) = CURDATE()
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $seq = $result->fetch_assoc()['next_seq'];
    
    return sprintf("PO-%s%s%s-%03d", $year, $month, $day, $seq);
}

function createPurchaseOrderTablesIfNotExist($conn) {
    // Create purchase order tables if they don't exist
    $tables = [
        "CREATE TABLE IF NOT EXISTS purchase_orders (
            po_id INT AUTO_INCREMENT PRIMARY KEY,
            po_number VARCHAR(100) UNIQUE NOT NULL,
            supplier_id INT NOT NULL,
            order_date DATE NOT NULL,
            expected_delivery_date DATE,
            actual_delivery_date DATE,
            status ENUM('draft', 'sent', 'confirmed', 'partial', 'completed', 'cancelled') DEFAULT 'draft',
            subtotal DECIMAL(12,2) DEFAULT 0.00,
            tax_amount DECIMAL(12,2) DEFAULT 0.00,
            total_amount DECIMAL(12,2) DEFAULT 0.00,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by INT,
            updated_by INT,
            FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE RESTRICT,
            INDEX idx_po_number (po_number),
            INDEX idx_supplier_po (supplier_id),
            INDEX idx_order_date (order_date),
            INDEX idx_status (status)
        )",
        
        "CREATE TABLE IF NOT EXISTS purchase_order_items (
            po_item_id INT AUTO_INCREMENT PRIMARY KEY,
            po_id INT NOT NULL,
            item_id INT NOT NULL,
            quantity_ordered INT NOT NULL,
            quantity_received INT DEFAULT 0,
            unit_price DECIMAL(10,2) NOT NULL,
            total_price DECIMAL(12,2) NOT NULL,
            notes TEXT,
            FOREIGN KEY (po_id) REFERENCES purchase_orders(po_id) ON DELETE CASCADE,
            FOREIGN KEY (item_id) REFERENCES inventory_items(item_id) ON DELETE RESTRICT,
            INDEX idx_po_items (po_id),
            INDEX idx_item_po (item_id)
        )"
    ];
    
    foreach ($tables as $sql) {
        $conn->query($sql);
    }
}
?>
