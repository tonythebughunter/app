<?php
/**
 * Database Setup and Installation Script
 * P2P Shares Marketplace
 * 
 * This script will:
 * 1. Check database connection
 * 2. Create database if it doesn't exist
 * 3. Create tables if they don't exist
 * 4. Insert default data (admin user, shares pool)
 * 5. Verify installation
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'admin');
define('DB_PASSWORD', '1justgotrooted');
define('DB_NAME','pyramid');

// Start output buffering for clean display
ob_start();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>P2P Shares Marketplace - Database Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .step {
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            border-left: 4px solid #dee2e6;
        }
        .step.success {
            background-color: #d4edda;
            border-left-color: #28a745;
        }
        .step.error {
            background-color: #f8d7da;
            border-left-color: #dc3545;
        }
        .step.warning {
            background-color: #fff3cd;
            border-left-color: #ffc107;
        }
        .step.info {
            background-color: #d1ecf1;
            border-left-color: #17a2b8;
        }
        .console-output {
            background: #2d3748;
            color: #e2e8f0;
            font-family: 'Courier New', monospace;
            padding: 20px;
            border-radius: 8px;
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold text-primary">P2P Shares Marketplace</h2>
                            <p class="text-muted">Database Setup and Installation</p>
                        </div>

                        <div id="installation-steps">
                            <?php
                            $steps = [];
                            $hasErrors = false;

                            // Step 1: Test database connection (without specific database)
                            try {
                                $conn = new PDO("mysql:host=" . DB_HOST, DB_USERNAME, DB_PASSWORD);
                                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                $steps[] = [
                                    'title' => 'Database Connection Test',
                                    'message' => 'Successfully connected to MySQL server',
                                    'status' => 'success'
                                ];
                            } catch(PDOException $e) {
                                $steps[] = [
                                    'title' => 'Database Connection Test',
                                    'message' => 'Failed to connect to MySQL: ' . $e->getMessage(),
                                    'status' => 'error'
                                ];
                                $hasErrors = true;
                            }

                            if (!$hasErrors) {
                                // Step 2: Create database if it doesn't exist
                                try {
                                    $stmt = $conn->prepare("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                                    $stmt->execute();
                                    
                                    $steps[] = [
                                        'title' => 'Database Creation',
                                        'message' => 'Database "' . DB_NAME . '" created or already exists',
                                        'status' => 'success'
                                    ];
                                } catch(PDOException $e) {
                                    $steps[] = [
                                        'title' => 'Database Creation',
                                        'message' => 'Failed to create database: ' . $e->getMessage(),
                                        'status' => 'error'
                                    ];
                                    $hasErrors = true;
                                }

                                // Step 3: Connect to the specific database
                                if (!$hasErrors) {
                                    try {
                                        $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
                                        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                        
                                        $steps[] = [
                                            'title' => 'Database Selection',
                                            'message' => 'Successfully connected to database "' . DB_NAME . '"',
                                            'status' => 'success'
                                        ];
                                    } catch(PDOException $e) {
                                        $steps[] = [
                                            'title' => 'Database Selection',
                                            'message' => 'Failed to connect to database: ' . $e->getMessage(),
                                            'status' => 'error'
                                        ];
                                        $hasErrors = true;
                                    }
                                }

                                // Step 4: Create tables
                                if (!$hasErrors) {
                                    $tables = [
                                        'users' => "CREATE TABLE IF NOT EXISTS users (
                                            id INT AUTO_INCREMENT PRIMARY KEY,
                                            name VARCHAR(100) NOT NULL,
                                            email VARCHAR(100) UNIQUE NOT NULL,
                                            phone VARCHAR(20) NOT NULL,
                                            password VARCHAR(255) NOT NULL,
                                            role ENUM('admin', 'user') DEFAULT 'user',
                                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                                        )",
                                        
                                        'shares_pool' => "CREATE TABLE IF NOT EXISTS shares_pool (
                                            id INT AUTO_INCREMENT PRIMARY KEY,
                                            total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                                            available_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                                        )",
                                        
                                        'transactions' => "CREATE TABLE IF NOT EXISTS transactions (
                                            id INT AUTO_INCREMENT PRIMARY KEY,
                                            user_id INT NOT NULL,
                                            amount DECIMAL(15,2) NOT NULL,
                                            status ENUM('pending', 'confirmed', 'matured', 'listed', 'sold', 'released') DEFAULT 'pending',
                                            buy_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                            maturity_date TIMESTAMP NULL,
                                            mpesa_number VARCHAR(20) NULL,
                                            buyer_id INT NULL,
                                            seller_id INT NULL,
                                            profit_amount DECIMAL(15,2) DEFAULT 0,
                                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                                            FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE SET NULL,
                                            FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE SET NULL
                                        )",
                                        
                                        'logs' => "CREATE TABLE IF NOT EXISTS logs (
                                            id INT AUTO_INCREMENT PRIMARY KEY,
                                            action VARCHAR(255) NOT NULL,
                                            user_id INT NOT NULL,
                                            details TEXT NULL,
                                            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                                        )"
                                    ];

                                    foreach ($tables as $tableName => $sql) {
                                        try {
                                            $stmt = $conn->prepare($sql);
                                            $stmt->execute();
                                            
                                            $steps[] = [
                                                'title' => 'Table Creation: ' . $tableName,
                                                'message' => 'Table "' . $tableName . '" created or already exists',
                                                'status' => 'success'
                                            ];
                                        } catch(PDOException $e) {
                                            $steps[] = [
                                                'title' => 'Table Creation: ' . $tableName,
                                                'message' => 'Failed to create table: ' . $e->getMessage(),
                                                'status' => 'error'
                                            ];
                                            $hasErrors = true;
                                        }
                                    }
                                }

                                // Step 5: Insert default data
                                if (!$hasErrors) {
                                    try {
                                        // Check if admin user exists
                                        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                                        $stmt->execute(['admin@sharesmarket.com']);
                                        $adminExists = $stmt->fetchColumn() > 0;

                                        if (!$adminExists) {
                                            // Insert default admin user (password: 1justgotroot)
                                            $hashedPassword = password_hash('1justgotroot', PASSWORD_DEFAULT);
                                            $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
                                            $stmt->execute(['Admin User', 'admin@sharesmarket.com', '254700000000', $hashedPassword, 'admin']);
                                            
                                            $steps[] = [
                                                'title' => 'Default Admin User',
                                                'message' => 'Admin user created (email: admin@sharesmarket.com, password: 1justgotroot)',
                                                'status' => 'success'
                                            ];
                                        } else {
                                            $steps[] = [
                                                'title' => 'Default Admin User',
                                                'message' => 'Admin user already exists',
                                                'status' => 'info'
                                            ];
                                        }

                                        // Check if shares pool exists
                                        $stmt = $conn->prepare("SELECT COUNT(*) FROM shares_pool");
                                        $stmt->execute();
                                        $poolExists = $stmt->fetchColumn() > 0;

                                        if (!$poolExists) {
                                            // Initialize shares pool
                                            $stmt = $conn->prepare("INSERT INTO shares_pool (total_amount, available_amount) VALUES (0, 0)");
                                            $stmt->execute();
                                            
                                            $steps[] = [
                                                'title' => 'Shares Pool Initialization',
                                                'message' => 'Shares pool initialized with 0 amount',
                                                'status' => 'success'
                                            ];
                                        } else {
                                            $steps[] = [
                                                'title' => 'Shares Pool Initialization',
                                                'message' => 'Shares pool already exists',
                                                'status' => 'info'
                                            ];
                                        }

                                        // Insert sample users for testing (optional)
                                        $testUsers = [
                                            ['John Doe', 'john@example.com', '254712345678'],
                                            ['Jane Smith', 'jane@example.com', '254723456789']
                                        ];

                                        foreach ($testUsers as $user) {
                                            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                                            $stmt->execute([$user[1]]);
                                            
                                            if ($stmt->fetchColumn() == 0) {
                                                $hashedPassword = password_hash('password', PASSWORD_DEFAULT);
                                                $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'user')");
                                                $stmt->execute([$user[0], $user[1], $user[2], $hashedPassword]);
                                            }
                                        }

                                        $steps[] = [
                                            'title' => 'Sample Test Users',
                                            'message' => 'Test users created (john@example.com, jane@example.com) with password: password',
                                            'status' => 'success'
                                        ];

                                    } catch(PDOException $e) {
                                        $steps[] = [
                                            'title' => 'Default Data Insertion',
                                            'message' => 'Error inserting default data: ' . $e->getMessage(),
                                            'status' => 'warning'
                                        ];
                                    }
                                }

                                // Step 6: Verify installation
                                if (!$hasErrors) {
                                    try {
                                        $stmt = $conn->prepare("SELECT COUNT(*) as user_count FROM users");
                                        $stmt->execute();
                                        $userCount = $stmt->fetch(PDO::FETCH_ASSOC)['user_count'];

                                        $stmt = $conn->prepare("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = ?");
                                        $stmt->execute([DB_NAME]);
                                        $tableCount = $stmt->fetch(PDO::FETCH_ASSOC)['table_count'];

                                        $steps[] = [
                                            'title' => 'Installation Verification',
                                            'message' => "Installation successful! Database has {$tableCount} tables and {$userCount} users.",
                                            'status' => 'success'
                                        ];

                                    } catch(PDOException $e) {
                                        $steps[] = [
                                            'title' => 'Installation Verification',
                                            'message' => 'Verification failed: ' . $e->getMessage(),
                                            'status' => 'warning'
                                        ];
                                    }
                                }
                            }

                            // Display all steps
                            foreach ($steps as $step) {
                                echo '<div class="step ' . $step['status'] . '">';
                                echo '<h6 class="mb-2">' . htmlspecialchars($step['title']) . '</h6>';
                                echo '<p class="mb-0">' . htmlspecialchars($step['message']) . '</p>';
                                echo '</div>';
                            }
                            ?>
                        </div>

                        <?php if (!$hasErrors): ?>
                            <div class="alert alert-success mt-4">
                                <h5 class="alert-heading">🎉 Installation Complete!</h5>
                                <p class="mb-3">Your P2P Shares Marketplace is now ready to use.</p>
                                <hr>
                                <h6>Default Login Credentials:</h6>
                                <p class="mb-1"><strong>Admin:</strong> admin@sharesmarket.com / 1justgotroot</p>
                                <p class="mb-3"><strong>Test User:</strong> john@example.com / password</p>
                                <a href="../index.php" class="btn btn-success">Go to Application</a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger mt-4">
                                <h5 class="alert-heading">❌ Installation Failed</h5>
                                <p>Please fix the errors above and try again.</p>
                                <button onclick="location.reload()" class="btn btn-danger">Retry Installation</button>
                            </div>
                        <?php endif; ?>

                        <!-- Configuration Help -->
                        <div class="card mt-4">
                            <div class="card-body">
                                <h6 class="card-title">Configuration Help</h6>
                                <p class="card-text text-muted">
                                    If you're getting connection errors, please update the database configuration at the top of this file:
                                </p>
                                <div class="console-output">
define('DB_HOST', '<?php echo DB_HOST; ?>');
define('DB_USERNAME', '<?php echo DB_USERNAME; ?>');
define('DB_PASSWORD', '<?php echo DB_PASSWORD ? '***hidden***' : '(empty)'; ?>');
define('DB_NAME', '<?php echo DB_NAME; ?>');
                                </div>
                                <small class="text-muted mt-2 d-block">
                                    Make sure MySQL is running and the credentials are correct.
                                </small>
                            </div>
                        </div>

                        <!-- System Requirements -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title">System Requirements</h6>
                                        <ul class="list-unstyled mb-0">
                                            <li>✅ PHP <?php echo phpversion(); ?> (7.4+ required)</li>
                                            <li>✅ MySQL Server</li>
                                            <li>✅ PDO MySQL Extension</li>
                                            <li>✅ Web Server (Apache/Nginx)</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title">Next Steps</h6>
                                        <ol class="mb-0">
                                            <li>Update database config in <code>config/database.php</code></li>
                                            <li>Set appropriate file permissions</li>
                                            <li>Configure web server virtual host</li>
                                            <li>Test the application</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// Clean output buffer and send
ob_end_flush();
?>