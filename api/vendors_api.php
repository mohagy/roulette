<?php
// Vendors API - Connected to Roulette Database
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

    $action = $_GET['action'] ?? 'get_vendors';
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($action) {
        case 'get_vendors':
            echo json_encode(getVendors($conn));
            break;
        case 'get_vendor_details':
            echo json_encode(getVendorDetails($conn));
            break;
        case 'add_vendor':
            if ($method === 'POST') {
                echo json_encode(addVendor($conn));
            }
            break;
        case 'update_vendor':
            if ($method === 'POST') {
                echo json_encode(updateVendor($conn));
            }
            break;
        case 'deactivate_vendor':
            if ($method === 'POST') {
                echo json_encode(deactivateVendor($conn));
            }
            break;
        case 'get_vendor_performance':
            echo json_encode(getVendorPerformance($conn));
            break;
        case 'get_vendor_orders':
            echo json_encode(getVendorOrders($conn));
            break;
        case 'update_vendor_rating':
            if ($method === 'POST') {
                echo json_encode(updateVendorRating($conn));
            }
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

function getVendors($conn) {
    // First, ensure vendor tables exist
    createVendorTablesIfNotExist($conn);
    
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            COUNT(po.po_id) as total_orders,
            COALESCE(SUM(po.total_amount), 0) as total_value,
            MAX(po.order_date) as last_order_date,
            COALESCE(AVG(vr.rating), 0) as rating,
            CASE 
                WHEN COUNT(po.po_id) = 0 THEN 0
                WHEN COUNT(CASE WHEN po.status = 'completed' THEN 1 END) = 0 THEN 25
                ELSE ROUND((COUNT(CASE WHEN po.status = 'completed' THEN 1 END) * 100.0 / COUNT(po.po_id)), 0)
            END as performance_score
        FROM suppliers s
        LEFT JOIN purchase_orders po ON s.supplier_id = po.supplier_id
        LEFT JOIN vendor_ratings vr ON s.supplier_id = vr.supplier_id
        GROUP BY s.supplier_id
        ORDER BY s.supplier_name
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    $vendors = [];
    
    while ($row = $result->fetch_assoc()) {
        $vendors[] = $row;
    }
    
    return [
        'status' => 'success',
        'data' => $vendors,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

function getVendorDetails($conn) {
    $vendor_id = $_GET['vendor_id'] ?? 0;
    
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            COUNT(po.po_id) as total_orders,
            COALESCE(SUM(po.total_amount), 0) as total_value,
            MAX(po.order_date) as last_order_date,
            COALESCE(AVG(vr.rating), 0) as rating
        FROM suppliers s
        LEFT JOIN purchase_orders po ON s.supplier_id = po.supplier_id
        LEFT JOIN vendor_ratings vr ON s.supplier_id = vr.supplier_id
        WHERE s.supplier_id = ?
        GROUP BY s.supplier_id
    ");
    
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Vendor not found");
    }
    
    $vendor = $result->fetch_assoc();
    
    // Get recent orders
    $orders_stmt = $conn->prepare("
        SELECT po_number, order_date, status, total_amount
        FROM purchase_orders 
        WHERE supplier_id = ? 
        ORDER BY order_date DESC 
        LIMIT 10
    ");
    $orders_stmt->bind_param("i", $vendor_id);
    $orders_stmt->execute();
    $orders_result = $orders_stmt->get_result();
    
    $recent_orders = [];
    while ($row = $orders_result->fetch_assoc()) {
        $recent_orders[] = $row;
    }
    
    $vendor['recent_orders'] = $recent_orders;
    
    return [
        'status' => 'success',
        'data' => $vendor
    ];
}

function addVendor($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $conn->prepare("
        INSERT INTO suppliers 
        (supplier_name, contact_person, email, phone, address, 
         category, credit_limit, payment_terms, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
    ");
    
    $stmt->bind_param("ssssssds", 
        $input['supplier_name'],
        $input['contact_person'],
        $input['email'],
        $input['phone'],
        $input['address'] ?? '',
        $input['category'] ?? 'general',
        $input['credit_limit'] ?? 0,
        $input['payment_terms'] ?? 'Net 30'
    );
    
    if ($stmt->execute()) {
        $vendor_id = $conn->insert_id;
        
        return [
            'status' => 'success',
            'message' => 'Vendor added successfully',
            'vendor_id' => $vendor_id
        ];
    } else {
        throw new Exception("Failed to add vendor: " . $stmt->error);
    }
}

function updateVendor($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $conn->prepare("
        UPDATE suppliers 
        SET supplier_name = ?, contact_person = ?, email = ?, phone = ?, 
            address = ?, category = ?, credit_limit = ?, payment_terms = ?, 
            status = ?, updated_at = NOW()
        WHERE supplier_id = ?
    ");
    
    $stmt->bind_param("ssssssdssi", 
        $input['supplier_name'],
        $input['contact_person'],
        $input['email'],
        $input['phone'],
        $input['address'],
        $input['category'],
        $input['credit_limit'],
        $input['payment_terms'],
        $input['status'],
        $input['supplier_id']
    );
    
    if ($stmt->execute()) {
        return [
            'status' => 'success',
            'message' => 'Vendor updated successfully'
        ];
    } else {
        throw new Exception("Failed to update vendor: " . $stmt->error);
    }
}

function deactivateVendor($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    $vendor_id = $input['vendor_id'];
    
    $stmt = $conn->prepare("
        UPDATE suppliers 
        SET status = 'inactive', updated_at = NOW()
        WHERE supplier_id = ?
    ");
    
    $stmt->bind_param("i", $vendor_id);
    
    if ($stmt->execute()) {
        return [
            'status' => 'success',
            'message' => 'Vendor deactivated successfully'
        ];
    } else {
        throw new Exception("Failed to deactivate vendor: " . $stmt->error);
    }
}

function getVendorPerformance($conn) {
    $stmt = $conn->prepare("
        SELECT 
            s.supplier_name,
            COUNT(po.po_id) as total_orders,
            COALESCE(SUM(po.total_amount), 0) as total_value,
            COALESCE(AVG(vr.rating), 0) as avg_rating,
            CASE 
                WHEN COUNT(po.po_id) = 0 THEN 0
                ELSE ROUND((COUNT(CASE WHEN po.status = 'completed' THEN 1 END) * 100.0 / COUNT(po.po_id)), 0)
            END as completion_rate,
            CASE 
                WHEN COUNT(po.po_id) = 0 THEN 0
                ELSE ROUND((COUNT(CASE WHEN po.actual_delivery_date <= po.expected_delivery_date THEN 1 END) * 100.0 / COUNT(po.po_id)), 0)
            END as on_time_delivery_rate
        FROM suppliers s
        LEFT JOIN purchase_orders po ON s.supplier_id = po.supplier_id
        LEFT JOIN vendor_ratings vr ON s.supplier_id = vr.supplier_id
        WHERE s.status = 'active'
        GROUP BY s.supplier_id
        ORDER BY completion_rate DESC, on_time_delivery_rate DESC
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    $performance = [];
    
    while ($row = $result->fetch_assoc()) {
        $performance[] = $row;
    }
    
    return [
        'status' => 'success',
        'data' => $performance
    ];
}

function getVendorOrders($conn) {
    $vendor_id = $_GET['vendor_id'] ?? 0;
    
    $stmt = $conn->prepare("
        SELECT 
            po_number,
            order_date,
            expected_delivery_date,
            actual_delivery_date,
            status,
            total_amount,
            DATEDIFF(COALESCE(actual_delivery_date, CURDATE()), expected_delivery_date) as delivery_delay
        FROM purchase_orders 
        WHERE supplier_id = ? 
        ORDER BY order_date DESC
    ");
    
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];
    
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    
    return [
        'status' => 'success',
        'data' => $orders
    ];
}

function updateVendorRating($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $conn->prepare("
        INSERT INTO vendor_ratings (supplier_id, rating, review, created_by, created_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
        rating = VALUES(rating), 
        review = VALUES(review), 
        updated_at = NOW()
    ");
    
    $created_by = $_SESSION['user_id'] ?? 1;
    
    $stmt->bind_param("idsi", 
        $input['supplier_id'],
        $input['rating'],
        $input['review'] ?? '',
        $created_by
    );
    
    if ($stmt->execute()) {
        return [
            'status' => 'success',
            'message' => 'Vendor rating updated successfully'
        ];
    } else {
        throw new Exception("Failed to update vendor rating: " . $stmt->error);
    }
}

function createVendorTablesIfNotExist($conn) {
    // Ensure suppliers table has all necessary columns
    $alter_suppliers = [
        "ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS category VARCHAR(50) DEFAULT 'general'",
        "ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS credit_limit DECIMAL(12,2) DEFAULT 0.00",
        "ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS payment_terms VARCHAR(100) DEFAULT 'Net 30'",
        "ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
        "ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
    ];
    
    foreach ($alter_suppliers as $sql) {
        $conn->query($sql);
    }
    
    // Create vendor ratings table
    $vendor_ratings_table = "
        CREATE TABLE IF NOT EXISTS vendor_ratings (
            rating_id INT AUTO_INCREMENT PRIMARY KEY,
            supplier_id INT NOT NULL,
            rating DECIMAL(2,1) NOT NULL CHECK (rating >= 1 AND rating <= 5),
            review TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE CASCADE,
            UNIQUE KEY unique_supplier_rating (supplier_id),
            INDEX idx_rating (rating),
            INDEX idx_created_by (created_by)
        )
    ";
    
    $conn->query($vendor_ratings_table);
    
    // Create vendor performance tracking table
    $vendor_performance_table = "
        CREATE TABLE IF NOT EXISTS vendor_performance (
            performance_id INT AUTO_INCREMENT PRIMARY KEY,
            supplier_id INT NOT NULL,
            month_year DATE NOT NULL,
            total_orders INT DEFAULT 0,
            completed_orders INT DEFAULT 0,
            on_time_deliveries INT DEFAULT 0,
            total_value DECIMAL(12,2) DEFAULT 0.00,
            avg_delivery_time DECIMAL(5,2) DEFAULT 0.00,
            performance_score DECIMAL(5,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE CASCADE,
            UNIQUE KEY unique_supplier_month (supplier_id, month_year),
            INDEX idx_month_year (month_year),
            INDEX idx_performance_score (performance_score)
        )
    ";
    
    $conn->query($vendor_performance_table);
}
?>
