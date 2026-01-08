<?php
// Equipment Issues API - Connected to Roulette Database
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

    $action = $_GET['action'] ?? 'get_issues';
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($action) {
        case 'get_issues':
            echo json_encode(getEquipmentIssues($conn));
            break;
        case 'get_issue_details':
            echo json_encode(getIssueDetails($conn));
            break;
        case 'create_issue':
            if ($method === 'POST') {
                echo json_encode(createIssue($conn));
            }
            break;
        case 'update_issue':
            if ($method === 'POST') {
                echo json_encode(updateIssue($conn));
            }
            break;
        case 'resolve_issue':
            if ($method === 'POST') {
                echo json_encode(resolveIssue($conn));
            }
            break;
        case 'delete_issue':
            if ($method === 'POST') {
                echo json_encode(deleteIssue($conn));
            }
            break;
        case 'get_issue_history':
            echo json_encode(getIssueHistory($conn));
            break;
        case 'add_issue_comment':
            if ($method === 'POST') {
                echo json_encode(addIssueComment($conn));
            }
            break;
        case 'get_equipment_list':
            echo json_encode(getEquipmentList($conn));
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

function getEquipmentIssues($conn) {
    // First, ensure equipment issues tables exist
    createEquipmentIssuesTablesIfNotExist($conn);
    
    $stmt = $conn->prepare("
        SELECT 
            ei.*,
            e.equipment_name,
            e.serial_number,
            COUNT(eih.history_id) as update_count,
            MAX(eih.created_at) as last_update_date
        FROM equipment_issues ei
        LEFT JOIN equipment e ON ei.equipment_id = e.equipment_id
        LEFT JOIN equipment_issue_history eih ON ei.issue_id = eih.issue_id
        GROUP BY ei.issue_id
        ORDER BY 
            CASE ei.priority 
                WHEN 'critical' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
            END,
            ei.created_date DESC
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    $issues = [];
    
    while ($row = $result->fetch_assoc()) {
        $issues[] = $row;
    }
    
    return [
        'status' => 'success',
        'data' => $issues,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

function getIssueDetails($conn) {
    $issue_id = $_GET['issue_id'] ?? 0;
    
    $stmt = $conn->prepare("
        SELECT 
            ei.*,
            e.equipment_name,
            e.serial_number,
            e.location as equipment_location,
            e.purchase_date,
            e.warranty_expiry
        FROM equipment_issues ei
        LEFT JOIN equipment e ON ei.equipment_id = e.equipment_id
        WHERE ei.issue_id = ?
    ");
    
    $stmt->bind_param("i", $issue_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Issue not found");
    }
    
    $issue = $result->fetch_assoc();
    
    // Get issue history
    $history_stmt = $conn->prepare("
        SELECT * FROM equipment_issue_history 
        WHERE issue_id = ? 
        ORDER BY created_at DESC
    ");
    $history_stmt->bind_param("i", $issue_id);
    $history_stmt->execute();
    $history_result = $history_stmt->get_result();
    
    $history = [];
    while ($row = $history_result->fetch_assoc()) {
        $history[] = $row;
    }
    
    $issue['history'] = $history;
    
    return [
        'status' => 'success',
        'data' => $issue
    ];
}

function createIssue($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $conn->begin_transaction();
    
    try {
        // Generate issue ID
        $issue_number = generateIssueNumber($conn);
        
        // Insert equipment issue
        $stmt = $conn->prepare("
            INSERT INTO equipment_issues 
            (issue_number, equipment_type, equipment_id, title, description, 
             priority, status, location, steps_to_reproduce, assigned_to, 
             expected_resolution, created_by, created_date) 
            VALUES (?, ?, ?, ?, ?, ?, 'open', ?, ?, ?, ?, ?, NOW())
        ");
        
        $created_by = $_SESSION['user_id'] ?? 1;
        
        $stmt->bind_param("sssssssssi", 
            $issue_number,
            $input['equipment_type'],
            $input['equipment_id'],
            $input['title'],
            $input['description'],
            $input['priority'],
            $input['location'],
            $input['steps_to_reproduce'] ?? '',
            $input['assigned_to'] ?? null,
            $input['expected_resolution'] ?? null,
            $created_by
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create issue: " . $stmt->error);
        }
        
        $issue_id = $conn->insert_id;
        
        // Add initial history entry
        $history_stmt = $conn->prepare("
            INSERT INTO equipment_issue_history 
            (issue_id, action_type, description, created_by, created_at) 
            VALUES (?, 'created', 'Issue reported', ?, NOW())
        ");
        
        $history_stmt->bind_param("ii", $issue_id, $created_by);
        $history_stmt->execute();
        
        $conn->commit();
        
        return [
            'status' => 'success',
            'message' => 'Issue created successfully',
            'issue_id' => $issue_id,
            'issue_number' => $issue_number
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function updateIssue($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $conn->begin_transaction();
    
    try {
        // Update issue
        $stmt = $conn->prepare("
            UPDATE equipment_issues 
            SET status = ?, priority = ?, assigned_to = ?, 
                expected_resolution = ?, updated_date = NOW()
            WHERE issue_id = ?
        ");
        
        $stmt->bind_param("ssssi", 
            $input['status'],
            $input['priority'],
            $input['assigned_to'],
            $input['expected_resolution'],
            $input['issue_id']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update issue: " . $stmt->error);
        }
        
        // Add history entry
        $history_stmt = $conn->prepare("
            INSERT INTO equipment_issue_history 
            (issue_id, action_type, description, created_by, created_at) 
            VALUES (?, 'updated', ?, ?, NOW())
        ");
        
        $created_by = $_SESSION['user_id'] ?? 1;
        $description = $input['notes'] ?? 'Issue updated';
        
        $history_stmt->bind_param("isi", $input['issue_id'], $description, $created_by);
        $history_stmt->execute();
        
        $conn->commit();
        
        return [
            'status' => 'success',
            'message' => 'Issue updated successfully'
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function resolveIssue($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    $issue_id = $input['issue_id'];
    
    $conn->begin_transaction();
    
    try {
        // Update issue status to resolved
        $stmt = $conn->prepare("
            UPDATE equipment_issues 
            SET status = 'resolved', resolved_date = NOW(), updated_date = NOW()
            WHERE issue_id = ?
        ");
        
        $stmt->bind_param("i", $issue_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to resolve issue: " . $stmt->error);
        }
        
        // Add history entry
        $history_stmt = $conn->prepare("
            INSERT INTO equipment_issue_history 
            (issue_id, action_type, description, created_by, created_at) 
            VALUES (?, 'resolved', ?, ?, NOW())
        ");
        
        $created_by = $_SESSION['user_id'] ?? 1;
        $description = $input['resolution_notes'] ?? 'Issue resolved';
        
        $history_stmt->bind_param("isi", $issue_id, $description, $created_by);
        $history_stmt->execute();
        
        $conn->commit();
        
        return [
            'status' => 'success',
            'message' => 'Issue resolved successfully'
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function deleteIssue($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    $issue_id = $input['issue_id'];
    
    $stmt = $conn->prepare("DELETE FROM equipment_issues WHERE issue_id = ?");
    $stmt->bind_param("i", $issue_id);
    
    if ($stmt->execute()) {
        return [
            'status' => 'success',
            'message' => 'Issue deleted successfully'
        ];
    } else {
        throw new Exception("Failed to delete issue: " . $stmt->error);
    }
}

function getIssueHistory($conn) {
    $issue_id = $_GET['issue_id'] ?? 0;
    
    $stmt = $conn->prepare("
        SELECT 
            eih.*,
            u.username
        FROM equipment_issue_history eih
        LEFT JOIN users u ON eih.created_by = u.user_id
        WHERE eih.issue_id = ?
        ORDER BY eih.created_at DESC
    ");
    
    $stmt->bind_param("i", $issue_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $history = [];
    
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    
    return [
        'status' => 'success',
        'data' => $history
    ];
}

function addIssueComment($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $conn->prepare("
        INSERT INTO equipment_issue_history 
        (issue_id, action_type, description, created_by, created_at) 
        VALUES (?, 'comment', ?, ?, NOW())
    ");
    
    $created_by = $_SESSION['user_id'] ?? 1;
    
    $stmt->bind_param("isi", 
        $input['issue_id'],
        $input['comment'],
        $created_by
    );
    
    if ($stmt->execute()) {
        return [
            'status' => 'success',
            'message' => 'Comment added successfully'
        ];
    } else {
        throw new Exception("Failed to add comment: " . $stmt->error);
    }
}

function getEquipmentList($conn) {
    $stmt = $conn->prepare("
        SELECT equipment_id, equipment_name, serial_number, equipment_type, location
        FROM equipment 
        WHERE status = 'active' 
        ORDER BY equipment_name
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $equipment = [];
    
    while ($row = $result->fetch_assoc()) {
        $equipment[] = $row;
    }
    
    return [
        'status' => 'success',
        'data' => $equipment
    ];
}

function generateIssueNumber($conn) {
    $today = new DateTime();
    $year = $today->format('Y');
    $month = $today->format('m');
    $day = $today->format('d');
    
    // Get next sequence number for today
    $stmt = $conn->prepare("
        SELECT COUNT(*) + 1 as next_seq 
        FROM equipment_issues 
        WHERE DATE(created_date) = CURDATE()
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $seq = $result->fetch_assoc()['next_seq'];
    
    return sprintf("EQ-%s%s%s-%03d", $year, $month, $day, $seq);
}

function createEquipmentIssuesTablesIfNotExist($conn) {
    // Create equipment table if it doesn't exist
    $equipment_table = "
        CREATE TABLE IF NOT EXISTS equipment (
            equipment_id INT AUTO_INCREMENT PRIMARY KEY,
            equipment_name VARCHAR(255) NOT NULL,
            equipment_type VARCHAR(100) NOT NULL,
            serial_number VARCHAR(100) UNIQUE,
            location VARCHAR(255),
            purchase_date DATE,
            warranty_expiry DATE,
            status ENUM('active', 'inactive', 'maintenance', 'retired') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_equipment_type (equipment_type),
            INDEX idx_location (location),
            INDEX idx_status (status)
        )
    ";
    
    // Create equipment issues table
    $issues_table = "
        CREATE TABLE IF NOT EXISTS equipment_issues (
            issue_id INT AUTO_INCREMENT PRIMARY KEY,
            issue_number VARCHAR(50) UNIQUE NOT NULL,
            equipment_id INT,
            equipment_type VARCHAR(100) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            priority ENUM('critical', 'high', 'medium', 'low') DEFAULT 'medium',
            status ENUM('open', 'in-progress', 'pending-parts', 'resolved', 'closed') DEFAULT 'open',
            location VARCHAR(255),
            steps_to_reproduce TEXT,
            assigned_to VARCHAR(100),
            expected_resolution DATETIME,
            resolved_date DATETIME,
            created_by INT,
            created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (equipment_id) REFERENCES equipment(equipment_id) ON DELETE SET NULL,
            INDEX idx_issue_number (issue_number),
            INDEX idx_equipment_type (equipment_type),
            INDEX idx_priority (priority),
            INDEX idx_status (status),
            INDEX idx_assigned_to (assigned_to),
            INDEX idx_created_date (created_date)
        )
    ";
    
    // Create equipment issue history table
    $history_table = "
        CREATE TABLE IF NOT EXISTS equipment_issue_history (
            history_id INT AUTO_INCREMENT PRIMARY KEY,
            issue_id INT NOT NULL,
            action_type ENUM('created', 'updated', 'assigned', 'resolved', 'closed', 'comment') NOT NULL,
            description TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (issue_id) REFERENCES equipment_issues(issue_id) ON DELETE CASCADE,
            INDEX idx_issue_history (issue_id),
            INDEX idx_action_type (action_type),
            INDEX idx_created_at (created_at)
        )
    ";
    
    $tables = [$equipment_table, $issues_table, $history_table];
    
    foreach ($tables as $sql) {
        $conn->query($sql);
    }
}
?>
