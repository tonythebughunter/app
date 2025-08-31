<?php
require_once '../config/database.php';
requireLogin();

$conn = getConnection();
$userId = $_SESSION['user_id'];

// Handle purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_from_marketplace'])) {
    $transactionId = intval($_POST['transaction_id']);
    try {
        $stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'listed'");
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($transaction && $transaction['user_id'] != $userId) {
            $stmt = $conn->prepare("UPDATE transactions SET status = 'sold', buyer_id = ? WHERE id = ?");
            $stmt->execute([$userId, $transactionId]);

            logAction('Purchased shares from marketplace', $userId, "Transaction ID: $transactionId");
            setFlashMessage('success', 'Purchase successful! Pay ' . formatCurrency($transaction['amount'] + $transaction['profit_amount']) . ' to M-Pesa number: ' . $transaction['mpesa_number'] . ' and wait for seller confirmation.');
        } else {
            setFlashMessage('error', 'Cannot purchase your own shares or shares no longer available');
        }
    } catch(Exception $e) {
        setFlashMessage('error', 'Failed to purchase shares');
    }
    header('Location: marketplace.php');
    exit();
}

// Listings
$stmt = $conn->prepare("
    SELECT t.*, u.name as seller_name, u.phone as seller_phone 
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.status = 'listed' AND t.user_id != ? 
    ORDER BY t.updated_at DESC
");
$stmt->execute([$userId]);
$marketplaceListings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Purchases
$stmt = $conn->prepare("
    SELECT t.*, u1.name as seller_name, u1.phone as seller_phone 
    FROM transactions t 
    JOIN users u1 ON t.user_id = u1.id 
    WHERE t.buyer_id = ? AND t.status IN ('sold', 'released') 
    ORDER BY t.updated_at DESC
");
$stmt->execute([$userId]);
$myPurchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// My listings
$stmt = $conn->prepare("
    SELECT t.*, u.name as buyer_name 
    FROM transactions t 
    LEFT JOIN users u ON t.buyer_id = u.id 
    WHERE t.user_id = ? AND t.status IN ('listed', 'sold') 
    ORDER BY t.updated_at DESC
");
$stmt->execute([$userId]);
$myListings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Marketplace - P2P Shares</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .sidebar { background: linear-gradient(180deg,#667eea 0%,#764ba2 100%); min-height:100vh; }
    .card { border:none; border-radius:15px; box-shadow:0 5px 15px rgba(0,0,0,.1); }
    .listing-card { border:1px solid #dee2e6; border-radius:12px; transition:transform .2s; }
    .listing-card:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(0,0,0,.15); }
    .price-tag { background:linear-gradient(45deg,#667eea,#764ba2); color:white; font-weight:bold; font-size:1.1rem; }
    .seller-info { background:#f8f9fa; border-radius:8px; padding:10px; }
    @media(max-width:576px){ .price-tag{font-size:1rem; padding:.3rem .6rem;} }
  </style>
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <!-- Sidebar -->
    <div class="col-md-3 sidebar text-white p-4 d-none d-md-flex flex-column">
      <h4 class="mb-4">P2P Shares</h4>
      <ul class="nav flex-column mb-auto">
        <li class="nav-item mb-2"><a class="nav-link text-white" href="dashboard.php">Dashboard</a></li>
        <li class="nav-item mb-2"><a class="nav-link text-white" href="portfolio.php">My Portfolio</a></li>
        <li class="nav-item mb-2"><a class="nav-link text-white active" href="marketplace.php">Marketplace</a></li>
      </ul>
      <hr>
      <div>
        <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong><br>
        <small>Investor</small><br>
        <a href="../logout.php" class="btn btn-outline-light btn-sm mt-2">Logout</a>
      </div>
    </div>

    <!-- Mobile Nav -->
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
            <li class="nav-item"><a class="nav-link active" href="marketplace.php">Marketplace</a></li>
            <li class="nav-item"><a class="nav-link" href="../logout.php">Logout</a></li>
          </ul>
        </div>
      </div>
    </nav>

    <!-- Main -->
    <div class="col-md-9 p-4">
      <?php if ($error = getFlashMessage('error')): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <?php if ($success = getFlashMessage('success')): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <h2 class="mb-4">Shares Marketplace</h2>

      <!-- Tabs -->
      <ul class="nav nav-tabs mb-4">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#available">Available Shares (<?php echo count($marketplaceListings); ?>)</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#purchases">My Purchases (<?php echo count($myPurchases); ?>)</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#listings">My Listings (<?php echo count($myListings); ?>)</button></li>
      </ul>

      <div class="tab-content">
        <!-- Available -->
        <div class="tab-pane fade show active" id="available">
          <?php if (empty($marketplaceListings)): ?>
            <div class="text-center py-5 text-muted">No shares available</div>
          <?php else: ?>
            <div class="row">
              <?php foreach($marketplaceListings as $listing): ?>
              <div class="col-md-6 mb-4">
                <div class="card listing-card h-100">
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                      <div>
                        <h6><?php echo htmlspecialchars($listing['seller_name']); ?></h6>
                        <small class="text-muted">Listed <?php echo date('M j, Y g:i A', strtotime($listing['updated_at'])); ?></small>
                      </div>
                      <span class="badge price-tag px-2 py-1"><?php echo formatCurrency($listing['amount']+$listing['profit_amount']); ?></span>
                    </div>
                    <div class="seller-info mb-3">
                      <small class="text-muted">Original: </small><?php echo formatCurrency($listing['amount']); ?><br>
                      <small class="text-muted">Profit: </small><span class="text-success">+<?php echo formatCurrency($listing['profit_amount']); ?></span><br>
                      <small class="text-muted">M-Pesa: </small><strong><?php echo htmlspecialchars($listing['mpesa_number']); ?></strong>
                    </div>
                    <form method="POST">
                      <input type="hidden" name="transaction_id" value="<?php echo $listing['id']; ?>">
                      <button type="submit" name="buy_from_marketplace" class="btn btn-primary w-100"
                        onclick="return confirm('Pay <?php echo formatCurrency($listing['amount']+$listing['profit_amount']); ?> to <?php echo htmlspecialchars($listing['mpesa_number']); ?>. Proceed?')">
                        Buy Now
                      </button>
                    </form>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Purchases -->
        <div class="tab-pane fade" id="purchases">
          <?php if (empty($myPurchases)): ?>
            <p class="text-center text-muted py-5">No purchases yet</p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table">
                <thead><tr><th>Seller</th><th>Amount</th><th>M-Pesa</th><th>Date</th><th>Status</th></tr></thead>
                <tbody>
                  <?php foreach($myPurchases as $p): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($p['seller_name']); ?></td>
                    <td><?php echo formatCurrency($p['amount']+$p['profit_amount']); ?></td>
                    <td><?php echo htmlspecialchars($p['mpesa_number']); ?></td>
                    <td><?php echo date('M j, Y g:i A', strtotime($p['updated_at'])); ?></td>
                    <td>
                      <?php echo $p['status']==='sold' ? '<span class="badge bg-warning">Awaiting Seller</span>' : '<span class="badge bg-success">Complete</span>'; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <!-- My Listings -->
        <div class="tab-pane fade" id="listings">
          <?php if (empty($myListings)): ?>
            <p class="text-center text-muted py-5">No active listings. <a href="dashboard.php">Go to Dashboard</a></p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table">
                <thead><tr><th>Amount</th><th>M-Pesa</th><th>Listed</th><th>Status</th><th>Buyer</th></tr></thead>
                <tbody>
                  <?php foreach($myListings as $l): ?>
                  <tr>
                    <td><?php echo formatCurrency($l['amount']+$l['profit_amount']); ?></td>
                    <td><?php echo htmlspecialchars($l['mpesa_number']); ?></td>
                    <td><?php echo date('M j, Y g:i A', strtotime($l['updated_at'])); ?></td>
                    <td><?php echo $l['status']==='listed' ? '<span class="badge bg-info">Available</span>' : '<span class="badge bg-warning">Awaiting Confirmation</span>'; ?></td>
                    <td><?php echo $l['buyer_name'] ? htmlspecialchars($l['buyer_name']) : '-'; ?></td>
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
