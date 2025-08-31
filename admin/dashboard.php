<?php
require_once '../config/database.php';
requireAdmin();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getConnection();
    
    // Add shares to pool
    if (isset($_POST['add_shares'])) {
        $amount = floatval($_POST['amount']);
        if ($amount > 0) {
            try {
                $stmt = $conn->prepare("UPDATE shares_pool SET total_amount = total_amount + ?, available_amount = available_amount + ? WHERE id = 1");
                $stmt->execute([$amount, $amount]);
                
                logAction('Added shares to pool', $_SESSION['user_id'], "Amount: " . formatCurrency($amount));
                setFlashMessage('success', 'Successfully added ' . formatCurrency($amount) . ' to shares pool');
            } catch(Exception $e) {
                setFlashMessage('error', 'Failed to add shares');
            }
        }
    }
    
    // Confirm pending transaction
    if (isset($_POST['confirm_transaction'])) {
        $transactionId = intval($_POST['transaction_id']);
        try {
            // Get transaction details
            $stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'pending'");
            $stmt->execute([$transactionId]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($transaction) {
                $maturityDate = calculateMaturityDate($transaction['buy_date']);
                $profitAmount = calculateProfit($transaction['amount']);
                
                // Update transaction
                $stmt = $conn->prepare("UPDATE transactions SET status = 'confirmed', maturity_date = ?, profit_amount = ? WHERE id = ?");
                $stmt->execute([$maturityDate, $profitAmount, $transactionId]);
                
                // Reduce available shares
                $stmt = $conn->prepare("UPDATE shares_pool SET available_amount = available_amount - ? WHERE id = 1");
                $stmt->execute([$transaction['amount']]);
                
                logAction('Confirmed transaction', $_SESSION['user_id'], "Transaction ID: $transactionId");
                setFlashMessage('success', 'Transaction confirmed successfully');
            }
        } catch(Exception $e) {
            setFlashMessage('error', 'Failed to confirm transaction');
        }
    }
    
    // Force release shares
    if (isset($_POST['force_release'])) {
        $transactionId = intval($_POST['transaction_id']);
        try {
            $stmt = $conn->prepare("UPDATE transactions SET status = 'released' WHERE id = ? AND status = 'sold'");
            $stmt->execute([$transactionId]);
            
            logAction('Force released shares', $_SESSION['user_id'], "Transaction ID: $transactionId");
            setFlashMessage('success', 'Shares force released successfully');
        } catch(Exception $e) {
            setFlashMessage('error', 'Failed to force release shares');
        }
    }
    
    header('Location: dashboard.php');
    exit();
}

// Get statistics
$conn = getConnection();

// Shares pool info
$stmt = $conn->query("SELECT * FROM shares_pool WHERE id = 1");
$sharesPool = $stmt->fetch(PDO::FETCH_ASSOC);

// Pending transactions
$stmt = $conn->query("SELECT t.*, u.name, u.email FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.status = 'pending' ORDER BY t.created_at DESC");
$pendingTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Transactions awaiting seller confirmation
$stmt = $conn->query("SELECT t.*, u1.name as seller_name, u2.name as buyer_name FROM transactions t JOIN users u1 ON t.seller_id = u1.id JOIN users u2 ON t.buyer_id = u2.id WHERE t.status = 'sold' ORDER BY t.updated_at DESC");
$soldTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Users count
$stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
$usersCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total transactions
$stmt = $conn->query("SELECT COUNT(*) as count FROM transactions");
$totalTransactions = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - P2P Shares</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sidebar {
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .stat-card {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 sidebar text-white p-4">
                <h4 class="mb-4">Admin Panel</h4>
                <ul class="nav flex-column">
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white active" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="users.php">Manage Users</a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="transactions.php">All Transactions</a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="logs.php">Activity Logs</a>
                    </li>
                </ul>
                
                <div class="mt-auto">
                    <hr>
                    <div class="d-flex align-items-center">
                        <div>
                            <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong><br>
                            <small>Administrator</small>
                        </div>
                    </div>
                    <a href="../logout.php" class="btn btn-outline-light btn-sm mt-2">Logout</a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 p-4">
                <?php if ($error = getFlashMessage('error')): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if ($success = getFlashMessage('success')): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <h2 class="mb-4">Admin Dashboard</h2>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5>Total Shares</h5>
                                <h3><?php echo formatCurrency($sharesPool['total_amount']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5>Available Shares</h5>
                                <h3><?php echo formatCurrency($sharesPool['available_amount']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5>Total Users</h5>
                                <h3><?php echo $usersCount; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5>Total Transactions</h5>
                                <h3><?php echo $totalTransactions; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Add Shares Form -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Add Shares to Pool</h5>
                        <form method="POST" class="row g-3">
                            <div class="col-md-8">
                                <input type="number" class="form-control" name="amount" placeholder="Amount (Ksh)" min="1" step="0.01" required>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" name="add_shares" class="btn btn-primary">Add Shares</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Pending Transactions -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Pending Transactions</h5>
                        <?php if (empty($pendingTransactions)): ?>
                            <p class="text-muted">No pending transactions</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Amount</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($pendingTransactions as $transaction): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($transaction['name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($transaction['email']); ?></small>
                                            </td>
                                            <td><?php echo formatCurrency($transaction['amount']); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($transaction['buy_date'])); ?></td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                                                    <button type="submit" name="confirm_transaction" class="btn btn-success btn-sm">Confirm</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Awaiting Seller Confirmation -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Awaiting Seller Confirmation</h5>
                        <?php if (empty($soldTransactions)): ?>
                            <p class="text-muted">No transactions awaiting seller confirmation</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Seller</th>
                                            <th>Buyer</th>
                                            <th>Amount</th>
                                            <th>Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($soldTransactions as $transaction): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($transaction['seller_name']); ?></td>
                                            <td><?php echo htmlspecialchars($transaction['buyer_name']); ?></td>
                                            <td><?php echo formatCurrency($transaction['amount'] + $transaction['profit_amount']); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($transaction['updated_at'])); ?></td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                                                    <button type="submit" name="force_release" class="btn btn-warning btn-sm">Force Release</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>