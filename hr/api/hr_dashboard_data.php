<?php
// HR Dashboard API
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "roulette";

date_default_timezone_set('America/Guyana');

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Get dashboard statistics
    $stats = [
        'total_employees' => 0,
        'active_employees' => 0,
        'new_hires_month' => 0,
        'pending_leave_requests' => 0,
        'monthly_payroll' => 0,
        'pending_applications' => 0
    ];

    // Total employees
    $result = $conn->query("SELECT COUNT(*) as count FROM employees");
    if ($result && $result->num_rows > 0) {
        $stats['total_employees'] = $result->fetch_assoc()['count'];
    }

    // Active employees
    $result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE employment_status = 'active'");
    if ($result && $result->num_rows > 0) {
        $stats['active_employees'] = $result->fetch_assoc()['count'];
    }

    // New hires this month
    $result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE MONTH(hire_date) = MONTH(CURDATE()) AND YEAR(hire_date) = YEAR(CURDATE())");
    if ($result && $result->num_rows > 0) {
        $stats['new_hires_month'] = $result->fetch_assoc()['count'];
    }

    // Pending leave requests
    $result = $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'");
    if ($result && $result->num_rows > 0) {
        $stats['pending_leave_requests'] = $result->fetch_assoc()['count'];
    }

    // Monthly payroll (current month)
    $result = $conn->query("
        SELECT SUM(net_pay) as total 
        FROM payroll_records 
        WHERE MONTH(pay_period_start) = MONTH(CURDATE()) 
        AND YEAR(pay_period_start) = YEAR(CURDATE())
        AND status = 'paid'
    ");
    if ($result && $result->num_rows > 0) {
        $stats['monthly_payroll'] = $result->fetch_assoc()['total'] ?? 0;
    }

    // Pending job applications
    $result = $conn->query("SELECT COUNT(*) as count FROM job_applications WHERE status IN ('applied', 'screening', 'interview')");
    if ($result && $result->num_rows > 0) {
        $stats['pending_applications'] = $result->fetch_assoc()['count'];
    }

    // Get recent activities
    $recent_activities = [];
    $result = $conn->query("
        (SELECT 'hire' as activity_type, CONCAT(first_name, ' ', last_name, ' hired as ', job_title) as description, hire_date as activity_time 
         FROM employees 
         WHERE hire_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY))
        UNION ALL
        (SELECT 'leave' as activity_type, CONCAT('Leave request: ', leave_type) as description, requested_at as activity_time 
         FROM leave_requests 
         WHERE requested_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))
        UNION ALL
        (SELECT 'training' as activity_type, CONCAT('Training: ', training_name) as description, training_date as activity_time 
         FROM training_records 
         WHERE training_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY))
        ORDER BY activity_time DESC 
        LIMIT 15
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $recent_activities[] = $row;
        }
    }

    // Get attendance summary for today
    $attendance_today = [];
    $result = $conn->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM attendance_records 
        WHERE date = CURDATE()
        GROUP BY status
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $attendance_today[] = $row;
        }
    }

    // Get pending leave requests details
    $pending_leave_details = [];
    $result = $conn->query("
        SELECT 
            lr.leave_type,
            lr.start_date,
            lr.end_date,
            lr.days_requested,
            lr.reason,
            lr.requested_at,
            CONCAT(e.first_name, ' ', e.last_name) as employee_name,
            e.job_title
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.employee_id
        WHERE lr.status = 'pending'
        ORDER BY lr.requested_at DESC
        LIMIT 10
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $pending_leave_details[] = $row;
        }
    }

    // Get upcoming performance reviews
    $upcoming_reviews = [];
    $result = $conn->query("
        SELECT 
            CONCAT(e.first_name, ' ', e.last_name) as employee_name,
            e.job_title,
            e.hire_date,
            DATEDIFF(DATE_ADD(e.hire_date, INTERVAL 1 YEAR), CURDATE()) as days_until_review
        FROM employees e
        LEFT JOIN performance_reviews pr ON e.employee_id = pr.employee_id 
            AND pr.review_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
        WHERE e.employment_status = 'active'
        AND pr.review_id IS NULL
        AND DATEDIFF(CURDATE(), e.hire_date) >= 330
        ORDER BY days_until_review ASC
        LIMIT 10
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $upcoming_reviews[] = $row;
        }
    }

    // Get training completion rates
    $training_stats = [];
    $result = $conn->query("
        SELECT 
            training_type,
            COUNT(*) as total_trainings,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_trainings,
            ROUND((SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as completion_rate
        FROM training_records
        WHERE training_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY training_type
        ORDER BY completion_rate DESC
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $training_stats[] = $row;
        }
    }

    // Get payroll summary for last 6 months
    $payroll_trend = [];
    $result = $conn->query("
        SELECT 
            DATE_FORMAT(pay_period_start, '%Y-%m') as month,
            SUM(gross_pay) as total_gross,
            SUM(total_deductions) as total_deductions,
            SUM(net_pay) as total_net,
            COUNT(DISTINCT employee_id) as employee_count
        FROM payroll_records
        WHERE pay_period_start >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        AND status = 'paid'
        GROUP BY DATE_FORMAT(pay_period_start, '%Y-%m')
        ORDER BY month
    ");

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $payroll_trend[] = $row;
        }
    }

    // Get employee turnover data
    $turnover_data = [
        'hires_this_year' => 0,
        'terminations_this_year' => 0,
        'turnover_rate' => 0
    ];

    $result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE YEAR(hire_date) = YEAR(CURDATE())");
    if ($result && $result->num_rows > 0) {
        $turnover_data['hires_this_year'] = $result->fetch_assoc()['count'];
    }

    $result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE YEAR(termination_date) = YEAR(CURDATE())");
    if ($result && $result->num_rows > 0) {
        $turnover_data['terminations_this_year'] = $result->fetch_assoc()['count'];
    }

    if ($stats['active_employees'] > 0) {
        $turnover_data['turnover_rate'] = round(($turnover_data['terminations_this_year'] / $stats['active_employees']) * 100, 1);
    }

    // Return data
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'recent_activities' => $recent_activities,
        'attendance_today' => $attendance_today,
        'pending_leave_details' => $pending_leave_details,
        'upcoming_reviews' => $upcoming_reviews,
        'training_stats' => $training_stats,
        'payroll_trend' => $payroll_trend,
        'turnover_data' => $turnover_data,
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
