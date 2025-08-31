<?php
require_once '../config/database.php';
requireLogin();

$conn = getConnection();
$userId = $_SESSION['user_id'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Buy shares
    if (isset($_POST['buy_shares'])) {
        $amount = floatval($_POST['amount']);

        if ($amount <= 0) {
            setFlashMessage('error', 'Invalid amount');
        } elseif ($amount > 50000) {
            setFlashMessage('error', 'Maximum purchase amount is Ksh. 50,000');
        } else {
            try {
                // Check available shares
                $stmt = $conn->prepare("SELECT available_amount FROM shares_pool WHERE id = 1");
                $stmt->execute();
                $pool = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($pool['available_amount'] < $amount) {
                    setFlashMessage('error', 'Insufficient shares available. Available: ' . formatCurrency($pool['available_amount']));
                } else {
                    // Create transaction
                    $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, status) VALUES (?, ?, 'pending')");
                    $stmt->execute([$userId, $amount]);

                    logAction('Requested share purchase', $userId, "Amount: " . formatCurrency($amount));
                    setFlashMessage('success', 'Request submitted. Please pay ' . formatCurrency($amount) . ' via M-Pesa to <strong>254712345678</strong> and wait for admin approval.');
                }
            } catch(Exception $e) {
                setFlashMessage('error', 'Failed to process request');
            }
        }
    }

    // List matured shares
    if (isset($_POST['list_for_sale'])) {
        $transactionId = intval($_POST['transaction_id']);
        $mpesaNumber = $_POST['mpesa_number'];

        try {
            $stmt = $conn->prepare("UPDATE transactions SET status = 'listed', mpesa_number = ? WHERE id = ? AND user_id = ? AND status = 'matured'");
            $stmt->execute([$mpesaNumber, $transactionId, $userId]);

            logAction('Listed shares for sale', $userId, "Transaction ID: $transactionId");
            setFlashMessage('success', 'Shares listed for sale successfully');
        } catch(Exception $e) {
            setFlashMessage('error', 'Failed to list shares');
        }
    }

    // Buy from marketplace
    if (isset($_POST['buy_from_marketplace'])) {
        $transactionId = intval($_POST['transaction_id']);
        try {
            $stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'listed'");
            $stmt->execute([$transactionId]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($transaction && $transaction['user_id'] != $userId) {
                $stmt = $conn->prepare("UPDATE transactions SET status = 'sold', buyer_id = ? WHERE id = ?");
                $stmt->execute([$userId, $transactionId]);

                logAction('Purchased shares from marketplace', $userId, "Transaction ID: $transactionId");
                setFlashMessage('success', 'Purchase successful! Pay via M-Pesa to <strong>' . $transaction['mpesa_number'] . '</strong> and wait for seller confirmation.');
            }
        } catch(Exception $e) {
            setFlashMessage('error', 'Failed to purchase shares');
        }
    }

    // Confirm payment
    if (isset($_POST['confirm_payment'])) {
        $transactionId = intval($_POST['transaction_id']);
        try {
            $stmt = $conn->prepare("UPDATE transactions SET status = 'released' WHERE id = ? AND seller_id = ? AND status = 'sold'");
            $stmt->execute([$transactionId, $userId]);

            logAction('Confirmed payment received', $userId, "Transaction ID: $transactionId");
            setFlashMessage('success', 'Payment confirmed. Shares released to buyer.');
        } catch(Exception $e) {
            setFlashMessage('error', 'Failed to confirm payment');
        }
    }

    header('Location: dashboard.php');
    exit();
}

// Update matured
$stmt = $conn->prepare("UPDATE transactions SET status = 'matured' WHERE user_id = ? AND status = 'confirmed' AND maturity_date <= NOW()");
$stmt->execute([$userId]);

// Fetch data
$stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$userTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT available_amount FROM shares_pool WHERE id = 1");
$stmt->execute();
$availableShares = $stmt->fetch(PDO::FETCH_ASSOC)['available_amount'];

$stmt = $conn->prepare("SELECT t.*, u.name as seller_name FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.status = 'listed' AND t.user_id != ? ORDER BY t.updated_at DESC");
$stmt->execute([$userId]);
$marketplaceListings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT t.*, u.name as buyer_name FROM transactions t JOIN users u ON t.buyer_id = u.id WHERE t.seller_id = ? AND t.status = 'sold' ORDER BY t.updated_at DESC");
$stmt->execute([$userId]);
$pendingSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalInvested = $totalMatured = $totalProfit = 0;
foreach($userTransactions as $transaction) {
    if (in_array($transaction['status'], ['confirmed','matured','listed','sold','released'])) {
        $totalInvested += $transaction['amount'];
        if ($transaction['status'] === 'matured' || $transaction['status'] === 'listed') {
            $totalMatured += $transaction['amount'] + $transaction['profit_amount'];
            $totalProfit += $transaction['profit_amount'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - P2P Shares</title>
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
        .status-pending { color: #ffc107; }
        .status-confirmed { color: #198754; }
        .status-matured { color: #0d6efd; }
        .status-listed { color: #6f42c1; }
        .status-sold { color: #fd7e14; }
        .status-released { color: #20c997; }
        /* keep cards equal height and centered */
	.stat-card.h-100 { display: flex; align-items: center; justify-content: center; }
	.stat-card .card-body { text-align: center; }

	/* mobile tweaks: reduce padding & font sizes so cards don't feel cramped */
	@media (max-width: 576px) {
	  .stat-card .card-body { padding: .9rem; }
	  .stat-card h5 { font-size: .95rem; margin-bottom: .25rem; }
	  .stat-card h3 { font-size: 1.25rem; margin: 0; }
	}

	/* slightly larger on small screens */
	@media (min-width: 577px) and (max-width: 991px) {
	  .stat-card .card-body { padding: 1rem; }
	  .stat-card h5 { font-size: 1rem; }
	  .stat-card h3 { font-size: 1.5rem; }
	}
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar (hidden on mobile) -->
            <div class="col-md-3 sidebar text-white p-4 d-none d-md-flex flex-column">
                <h4 class="mb-4">P2P Shares</h4>
                <ul class="nav flex-column mb-auto">
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white active" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="portfolio.php">My Portfolio</a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="marketplace.php">Marketplace</a>
                    </li>
                </ul>
                <hr>
                <div>
                    <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong><br>
                    <small>Investor</small><br>
                    <a href="../logout.php" class="btn btn-outline-light btn-sm mt-2">Logout</a>
                </div>
            </div>

            <!-- Mobile Navbar (hidden on desktop) -->
            <nav class="navbar navbar-expand-md navbar-dark bg-dark d-md-none">
                <div class="container-fluid">
                    <a class="navbar-brand" href="#">P2P Shares</a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mobileNav">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="mobileNav">
                        <ul class="navbar-nav ms-auto">
                            <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                            <li class="nav-item"><a class="nav-link" href="portfolio.php">My Portfolio</a></li>
                            <li class="nav-item"><a class="nav-link" href="marketplace.php">Marketplace</a></li>
                            <li class="nav-item"><a class="nav-link" href="../logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </nav>

            <!-- Main -->
            <div class="col-md-9 p-4">
                <?php if ($error = getFlashMessage('error')): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success = getFlashMessage('success')): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <h2 class="mb-4">Dashboard</h2>

                <!-- Stats -->
		        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-3 mb-4">
	  <div class="col">
	    <div class="card stat-card h-100">
	      <div class="card-body">
		<h5 class="mb-1">Total Invested</h5>
		<h3 class="mb-0"><?php echo formatCurrency($totalInvested); ?></h3>
	      </div>
	    </div>
	  </div>

	  <div class="col">
	    <div class="card stat-card h-100">
	      <div class="card-body">
		<h5 class="mb-1">Current Value</h5>
		<h3 class="mb-0"><?php echo formatCurrency($totalMatured); ?></h3>
	      </div>
	    </div>
	  </div>

	  <div class="col">
	    <div class="card stat-card h-100">
	      <div class="card-body">
		<h5 class="mb-1">Total Profit</h5>
		<h3 class="mb-0"><?php echo formatCurrency($totalProfit); ?></h3>
	      </div>
	    </div>
	  </div>

	  <div class="col">
	    <div class="card stat-card h-100">
	      <div class="card-body">
		<h5 class="mb-1">Available Shares</h5>
		<h3 class="mb-0"><?php echo formatCurrency($availableShares); ?></h3>
	      </div>
	    </div>
	  </div>
	</div>
                <!-- Buy -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Buy Shares</h5>
                        <p class="text-muted">Purchase up to Ksh. 50,000 per transaction. Shares mature in 3 days with 30% profit.</p>
                        <form method="POST" class="row g-3">
                            <div class="col-md-8">
                                <input type="number" class="form-control" name="amount" placeholder="Amount (Ksh)" min="1" max="50000" step="0.01" required>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" name="buy_shares" class="btn btn-primary">Buy Shares</button>
                            </div>
                        </form>
                        <div class="alert alert-info mt-3">
                            After submitting, please pay the entered amount via M-Pesa to <strong>254712345678</strong>. Your shares will be confirmed once payment is verified.
                        </div>
                    </div>
                </div>

                <!-- Transactions -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">My Recent Transactions</h5>
                        <?php if (empty($userTransactions)): ?>
                            <p class="text-muted">No transactions yet</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead><tr><th>Amount</th><th>Status</th><th>Purchase Date</th><th>Maturity Date</th><th>Value</th><th>Action</th></tr></thead>
                                    <tbody>
                                        <?php foreach(array_slice($userTransactions, 0, 5) as $transaction): ?>
                                        <tr>
                                            <td><?php echo formatCurrency($transaction['amount']); ?></td>
                                            <td><span class="status-<?php echo $transaction['status']; ?>"><?php echo ucfirst($transaction['status']); ?></span></td>
                                            <td><?php echo date('M j, Y', strtotime($transaction['buy_date'])); ?></td>
                                            <td><?php echo $transaction['maturity_date'] ? date('M j, Y', strtotime($transaction['maturity_date'])) : '-'; ?></td>
                                            <td>
                                                <?php if ($transaction['status']==='matured'||$transaction['status']==='listed'): ?>
                                                    <?php echo formatCurrency($transaction['amount']+$transaction['profit_amount']); ?>
                                                <?php else: ?>
                                                    <?php echo formatCurrency($transaction['amount']); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($transaction['status']==='matured'): ?>
                                                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#listModal<?php echo $transaction['id']; ?>">List for Sale</button>
                                                <?php elseif ($transaction['status']==='confirmed' && $transaction['maturity_date']): ?>
                                                    <?php $daysLeft=ceil((strtotime($transaction['maturity_date'])-time())/86400); ?>
                                                    <small class="text-muted"><?php echo $daysLeft; ?> days left</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <!-- Modal -->
                                        <?php if ($transaction['status']==='matured'): ?>
                                        <div class="modal fade" id="listModal<?php echo $transaction['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog"><div class="modal-content">
                                                <div class="modal-header"><h5 class="modal-title">List Shares</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                                <form method="POST">
                                                    <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                                                    <div class="modal-body">
                                                        <p>List <strong><?php echo formatCurrency($transaction['amount']+$transaction['profit_amount']); ?></strong> worth of shares.</p>
                                                        <div class="mb-3">
                                                            <label class="form-label">Your M-Pesa Number</label>
                                                            <input type="tel" class="form-control" name="mpesa_number" placeholder="254712345678" required>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="list_for_sale" class="btn btn-success">List</button>
                                                    </div>
                                                </form>
                                            </div></div>
                                        </div>
                                        <?php endif; ?>
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

