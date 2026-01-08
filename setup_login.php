<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Login System</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            color: #1d3557;
            border-bottom: 2px solid #1d3557;
            padding-bottom: 10px;
        }
        .step {
            background: #f1faee;
            border-left: 4px solid #457b9d;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 0 5px 5px 0;
        }
        .step h2 {
            margin-top: 0;
            color: #1d3557;
        }
        code {
            background: #e9ecef;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: monospace;
        }
        .button {
            display: inline-block;
            background: #457b9d;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 10px;
        }
        .button:hover {
            background: #1d3557;
        }
        .credentials {
            background: #e63946;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <h1>Roulette POS Login System Setup</h1>

    <div class="step">
        <h2>Step 1: Create Database and User Table</h2>
        <p>Click the button below to create the database and user table with default credentials:</p>
        <a href="create_user.php" class="button">Create Database &amp; User</a>
    </div>

    <div class="step">
        <h2>Troubleshooting Tools</h2>
        <p>If you're having trouble logging in, try these tools:</p>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="test_login.php" class="button">Test Login System</a>
            <a href="reset_password.php" class="button">Reset Default Password</a>
            <a href="direct_login.php" class="button">Direct Login (No AJAX)</a>
            <a href="fix_database.php" class="button">Fix Database</a>
            <a href="login_troubleshooting.html" class="button">Troubleshooting Guide</a>
        </div>
    </div>

    <div class="step">
        <h2>Step 2: Default Login Credentials</h2>
        <p>Use these credentials to log in to the system:</p>
        <div class="credentials">
            <p><strong>Username:</strong> 123456789012</p>
            <p><strong>Password:</strong> 123456</p>
        </div>
    </div>

    <div class="step">
        <h2>Step 3: Access the Login Page</h2>
        <p>Once the setup is complete, you can access the login page:</p>
        <a href="login.php" class="button">Go to Login Page</a>
    </div>

    <div class="step">
        <h2>Troubleshooting</h2>
        <p>If you encounter any issues:</p>
        <ul>
            <li>Make sure XAMPP is running with MySQL service active</li>
            <li>Check that the database connection parameters in the PHP files are correct</li>
            <li>Look for error messages in the logs directory</li>
        </ul>
    </div>
</body>
</html>
