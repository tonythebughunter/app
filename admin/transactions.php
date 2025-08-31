<?php
require_once '../config/database.php';
requireAdmin();

$conn = getConnection();

// Get all transactions with user details
$stmt = $conn->query("
    SELECT t.*, u.name as user_name, u.email as user_email,
           u2.name as buyer_name, u3.name as seller_name
    FROM transactions t 
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN users u2 ON t.buyer_id = u2.id  
    LEFT JOIN users u3 ON t.seller_id = u3.id
    ORDER BY t.created_at DESC
");
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by status for statistics
$statusCounts = [
    'pending' => 0,
    'confirmed' => 0,
    'matured' => 0,
    'listed' => 0,
    'sold' => 0,
    'released' => 0
];

$totalVolume = 0;
foreach($transactions as $transaction) {
    if (isset($statusCounts[$transaction['status']])) {
        $statusCounts[$transaction['status']]++;
    }
    $totalVolume += $transaction['amount'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Transactions - P2P Shares</title>
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
        .status-pending { color: #ffc107; font-weight: bold; }
        .status-confirmed { color: #198754; font-weight: bold; }
        .status-matured { color: #0d6efd; font-weight: bold; }
        .status-listed { color: #6f42c1; font-weight: bold; }
        .status-sold { color: #fd7e14; font-weight: bold; }
        .status-released { color: #20c997; font-weight: bold; }
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
                        <a class="nav-link text-white" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="users.php">Manage Users</a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white active" href="transactions.php">All Transactions</a>
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
                <h2 class="mb-4">All Transactions</h2>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h5>Total</h5>
                                <h3><?php echo count($transactions); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h5>Pending</h5>
                                <h3><?php echo $statusCounts['pending']; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h5>Active</h5>
                                <h3><?php echo $statusCounts['confirmed']; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h5>Matured</h5>
                                <h3><?php echo $statusCounts['matured']; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h5>Listed</h5>
                                <h3><?php echo $statusCounts['listed']; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h5>Volume</h5>
                                <h4><?php echo formatCurrency($totalVolume); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transactions Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table" id="transactionsTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Purchase Date</th>
                                        <th>Maturity Date</th>
                                        <th>Current Value</th>
                                        <th>Marketplace Info</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($transactions as $transaction): ?>
                                    <tr>
                                        <td><?php echo $transaction['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($transaction['user_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($transaction['user_email']); ?></small>
                                        </td>
                                        <td><?php echo formatCurrency($transaction['amount']); ?></td>
                                        <td>
                                            <span class="status-<?php echo $transaction['status']; ?>">
                                                <?php echo ucfirst($transaction['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($transaction['buy_date'])); ?></td>
                                        <td>
                                            <?php if ($transaction['maturity_date']): ?>
                                                <?php echo date('M j, Y g:i A', strtotime($transaction['maturity_date'])); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (in_array($transaction['status'], ['matured', 'listed', 'sold', 'released'])): ?>
                                                <?php echo formatCurrency($transaction['amount'] + $transaction['profit_amount']); ?>
                                                <br><small class="text-success">+<?php echo formatCurrency($transaction['profit_amount']); ?></small>
                                            <?php else: ?>
                                                <?php echo formatCurrency($transaction['amount']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($transaction['status'] === 'listed'): ?>
                                                <small>M-Pesa: <?php echo htmlspecialchars($transaction['mpesa_number']); ?></small>
                                            <?php elseif ($transaction['status'] === 'sold'): ?>
                                                <small>
                                                    Buyer: <?php echo htmlspecialchars($transaction['buyer_name']); ?><br>
                                                    M-Pesa: <?php echo htmlspecialchars($transaction['mpesa_number']); ?>
                                                </small>
                                            <?php elseif ($transaction['status'] === 'released'): ?>
                                                <small class="text-success">
                                                    Sold to: <?php echo htmlspecialchars($transaction['buyer_name']); ?>
                                                </small>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple search functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Add search input
            const cardBody = document.querySelector('.card-body');
            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.className = 'form-control mb-3';
            searchInput.placeholder = 'Search transactions...';
            cardBody.insertBefore(searchInput, cardBody.firstChild);
            
            // Search functionality
            searchInput.addEventListener('keyup', function() {
                const filter = this.value.toUpperCase();
                const table = document.getElementById('transactionsTable');
                const rows = table.getElementsByTagName('tr');
                
                for (let i = 1; i < rows.length; i++) {
                    const row = rows[i];
                    const cells = row.getElementsByTagName('td');
                    let found = false;
                    
                    for (let j = 0; j < cells.length; j++) {
                        if (cells[j].textContent.toUpperCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                    
                    row.style.display = found ? '' : 'none';
                }
            });
        });
    </script>
</body>
</html>