<?php
// Add New Betting Shop
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "roulette";

date_default_timezone_set('America/Guyana');

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = '';
$messageType = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_shop'])) {
    try {
        $shop_name = trim($_POST['shop_name']);
        $shop_code = trim($_POST['shop_code']);
        $address = trim($_POST['address']);
        $city = trim($_POST['city']);
        $state = trim($_POST['state']);
        $postal_code = trim($_POST['postal_code']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $manager_name = trim($_POST['manager_name']);
        $manager_phone = trim($_POST['manager_phone']);
        $manager_email = trim($_POST['manager_email']);
        $commission_rate = floatval($_POST['commission_rate']);
        $opening_time = $_POST['opening_time'];
        $closing_time = $_POST['closing_time'];
        $status = $_POST['status'];

        // Validate required fields
        if (empty($shop_name) || empty($shop_code)) {
            throw new Exception("Shop name and shop code are required.");
        }

        // Check if shop code already exists
        $stmt = $conn->prepare("SELECT shop_id FROM betting_shops WHERE shop_code = ?");
        $stmt->bind_param("s", $shop_code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            throw new Exception("Shop code already exists. Please choose a different code.");
        }

        // Insert new shop
        $stmt = $conn->prepare("INSERT INTO betting_shops (
            shop_name, shop_code, address, city, state, postal_code,
            phone, email, manager_name, manager_phone, manager_email,
            commission_rate, opening_time, closing_time, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param("sssssssssssdsss",
            $shop_name, $shop_code, $address, $city, $state, $postal_code,
            $phone, $email, $manager_name, $manager_phone, $manager_email,
            $commission_rate, $opening_time, $closing_time, $status
        );

        if ($stmt->execute()) {
            $shop_id = $conn->insert_id;

            // Create initial performance record for today
            $stmt = $conn->prepare("INSERT INTO shop_performance (shop_id, date) VALUES (?, CURDATE())");
            $stmt->bind_param("i", $shop_id);
            $stmt->execute();

            $message = "Betting shop '{$shop_name}' has been created successfully!";
            $messageType = "success";

            // Redirect to shops list after 2 seconds
            header("refresh:2;url=betting_shops.php");
        } else {
            throw new Exception("Error creating shop: " . $stmt->error);
        }

    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Betting Shop - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .form-section {
            background: #f8f9fc;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid #4e73df;
        }
        .form-section h5 {
            color: #4e73df;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        .form-control:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
        }
        .btn-secondary {
            background: #6c757d;
            border: none;
            padding: 0.75rem 2rem;
        }
        .required {
            color: #e74a3b;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <nav class="top-navbar">
            <div class="navbar-search">
                <input type="text" class="search-input" placeholder="Search...">
            </div>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a href="../logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="content-wrapper">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-plus-circle"></i> Add New Betting Shop
                </h1>
                <div class="breadcrumb">
                    <div class="breadcrumb-item"><a href="index.php">Admin</a></div>
                    <div class="breadcrumb-item"><a href="betting_shops.php">Betting Shops</a></div>
                    <div class="breadcrumb-item active">Add New</div>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-store"></i> Shop Information
                            </h6>
                        </div>
                        <div class="card-body">
                            <form method="post" action="" id="addShopForm">
                                <!-- Basic Information -->
                                <div class="form-section">
                                    <h5><i class="fas fa-info-circle"></i> Basic Information</h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label for="shop_name" class="form-label">
                                                    Shop Name <span class="required">*</span>
                                                </label>
                                                <input type="text" class="form-control" id="shop_name" name="shop_name" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label for="shop_code" class="form-label">
                                                    Shop Code <span class="required">*</span>
                                                </label>
                                                <input type="text" class="form-control" id="shop_code" name="shop_code"
                                                       placeholder="e.g., DBC001" required>
                                                <small class="form-text text-muted">Unique identifier for the shop</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group mb-3">
                                                <label for="status" class="form-label">Status</label>
                                                <select class="form-control" id="status" name="status">
                                                    <option value="active">Active</option>
                                                    <option value="inactive">Inactive</option>
                                                    <option value="suspended">Suspended</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group mb-3">
                                                <label for="commission_rate" class="form-label">Commission Rate (%)</label>
                                                <input type="number" class="form-control" id="commission_rate" name="commission_rate"
                                                       value="5.00" step="0.01" min="0" max="100">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Location Information -->
                                <div class="form-section">
                                    <h5><i class="fas fa-map-marker-alt"></i> Location Information</h5>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group mb-3">
                                                <label for="address" class="form-label">Address</label>
                                                <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group mb-3">
                                                <label for="city" class="form-label">City</label>
                                                <input type="text" class="form-control" id="city" name="city" value="Georgetown">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group mb-3">
                                                <label for="state" class="form-label">State/Region</label>
                                                <input type="text" class="form-control" id="state" name="state" value="Demerara-Mahaica">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group mb-3">
                                                <label for="postal_code" class="form-label">Postal Code</label>
                                                <input type="text" class="form-control" id="postal_code" name="postal_code">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Contact Information -->
                                <div class="form-section">
                                    <h5><i class="fas fa-phone"></i> Contact Information</h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label for="phone" class="form-label">Shop Phone</label>
                                                <input type="tel" class="form-control" id="phone" name="phone"
                                                       placeholder="+592-xxx-xxxx">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label for="email" class="form-label">Shop Email</label>
                                                <input type="email" class="form-control" id="email" name="email">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Manager Information -->
                                <div class="form-section">
                                    <h5><i class="fas fa-user-tie"></i> Manager Information</h5>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group mb-3">
                                                <label for="manager_name" class="form-label">Manager Name</label>
                                                <input type="text" class="form-control" id="manager_name" name="manager_name">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group mb-3">
                                                <label for="manager_phone" class="form-label">Manager Phone</label>
                                                <input type="tel" class="form-control" id="manager_phone" name="manager_phone">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group mb-3">
                                                <label for="manager_email" class="form-label">Manager Email</label>
                                                <input type="email" class="form-control" id="manager_email" name="manager_email">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Operating Hours -->
                                <div class="form-section">
                                    <h5><i class="fas fa-clock"></i> Operating Hours</h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label for="opening_time" class="form-label">Opening Time</label>
                                                <input type="time" class="form-control" id="opening_time" name="opening_time" value="08:00">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label for="closing_time" class="form-label">Closing Time</label>
                                                <input type="time" class="form-control" id="closing_time" name="closing_time" value="22:00">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Form Actions -->
                                <div class="form-actions text-center mt-4">
                                    <button type="submit" name="add_shop" class="btn btn-primary me-3">
                                        <i class="fas fa-save"></i> Create Shop
                                    </button>
                                    <a href="betting_shops.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-generate shop code based on shop name
        document.getElementById('shop_name').addEventListener('input', function() {
            const shopName = this.value.trim();
            if (shopName) {
                const words = shopName.split(' ');
                let code = '';
                words.forEach(word => {
                    if (word.length > 0) {
                        code += word.charAt(0).toUpperCase();
                    }
                });
                // Add random number
                code += String(Math.floor(Math.random() * 900) + 100);
                document.getElementById('shop_code').value = code;
            }
        });

        // Form validation
        document.getElementById('addShopForm').addEventListener('submit', function(e) {
            const shopName = document.getElementById('shop_name').value.trim();
            const shopCode = document.getElementById('shop_code').value.trim();

            if (!shopName || !shopCode) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
        });
    </script>
</body>
</html>
