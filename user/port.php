<?php
require_once '../config/database.php';
requireLogin();

$conn = getConnection();
$userId = $_SESSION['user_id'];

// Update matured shares
$stmt = $conn->prepare("UPDATE transactions SET status = 'matured' 
    WHERE user_id = ? AND status = 'confirmed' AND maturity_date <= NOW()");
$stmt->execute([$userId]);

// Fetch transactions
$stmt = $conn->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by status
$grouped = [
    'pending' => [],
    'confirmed' => [],
    'matured' => [],
    'listed' => [],
    'sold' => [],
    'released' => []
];
foreach ($transactions as $t) {
    if (isset($grouped[$t['status']])) {
        $grouped[$t['status']][] = $t;
    }
}

// Stats
$totalInvested = $activeInvestments = $matureShares = $totalProfit = 0;
foreach ($transactions as $t) {
    if (in_array($t['status'], ['confirmed','matured','listed','sold','released'])) {
        $totalInvested += $t['amount'];
    }
    if (in_array($t['status'], ['confirmed','matured'])) {
        $activeInvestments += $t['amount'];
    }
    if ($t['status']==='matured' || $t['status']==='listed') {
        $matureShares += $t['amount'] + $t['profit_amount'];
        $totalProfit += $t['profit_amount'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Portfolio - P2P Shares</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .sidebar { background: linear-gradient(180deg,#667eea 0%,#764ba2 100%); min-height: 100vh; }
    .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,.1); }
    .stat-card { background: linear-gradient(45deg,#667eea,#764ba2); color:white; }
    .stat-card.h-100 { display:flex; align-items:center; justify-content:center; }
    .stat-card .card-body { text-align:center; }
    .status-pending{color:#ffc107;font-weight:bold;}
    .status-confirmed{color:#198754;font-weight:bold;}
    .status-matured{color:#0d6efd;font-weight:bold;}
    .status-listed{color:#6f42c1;font-weight:bold;}
    .status-sold{color:#fd7e14;font-weight:bold;}
    .status-released{color:#20c997;font-weight:bold;}
    @media(max-width:576px){
      .stat-card .card-body{padding:.9rem;}
      .stat-card h5{font-size:.95rem;margin-bottom:.25rem;}
      .stat-card h3{font-size:1.25rem;margin:0;}
    }
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
        <li class="nav-item mb-2"><a class="nav-link text-white active" href="portfolio.php">My Portfolio</a></li>
        <li class="nav-item mb-2"><a class="nav-link text-white" href="marketplace.php">Marketplace</a></li>
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
            <li class="nav-item"><a class="nav-link active" href="portfolio.php">My Portfolio</a></li>
            <li class="nav-item"><a class="nav-link" href="marketplace.php">Marketplace</a></li>
            <li class="nav-item"><a class="nav-link" href="../logout.php">Logout</a></li>
          </ul>
        </div>
      </div>
    </nav>

    <!-- Main -->
    <div class="col-md-9 p-4">
      <h2 class="mb-4">My Portfolio</h2>

      <!-- Stats -->
      <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 g-3 mb-4">
        <div class="col"><div class="card stat-card h-100"><div class="card-body"><h5>Total Invested</h5><h3><?php echo formatCurrency($totalInvested); ?></h3></div></div></div>
        <div class="col"><div class="card stat-card h-100"><div class="card-body"><h5>Active Investments</h5><h3><?php echo formatCurrency($activeInvestments); ?></h3></div></div></div>
        <div class="col"><div class="card stat-card h-100"><div class="card-body"><h5>Mature Shares Value</h5><h3><?php echo formatCurrency($matureShares); ?></h3></div></div></div>
        <div class="col"><div class="card stat-card h-100"><div class="card-body"><h5>Total Profit</h5><h3><?php echo formatCurrency($totalProfit); ?></h3></div></div></div>
      </div>

      <!-- Transactions -->
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">All Transactions</h5>
          <?php if (empty($transactions)): ?>
            <p class="text-muted">No transactions yet</p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table">
                <thead><tr><th>Amount</th><th>Status</th><th>Purchase Date</th><th>Maturity Date</th><th>Value</th><th>Profit</th></tr></thead>
                <tbody>
                  <?php foreach($transactions as $t): ?>
                  <tr>
                    <td><?php echo formatCurrency($t['amount']); ?></td>
                    <td><span class="status-<?php echo $t['status']; ?>"><?php echo ucfirst($t['status']); ?></span></td>
                    <td><?php echo date('M j, Y', strtotime($t['buy_date'])); ?></td>
                    <td><?php echo $t['maturity_date'] ? date('M j, Y', strtotime($t['maturity_date'])) : '-'; ?></td>
                    <td><?php echo in_array($t['status'],['matured','listed','released']) ? formatCurrency($t['amount']+$t['profit_amount']) : formatCurrency($t['amount']); ?></td>
                    <td><?php echo $t['profit_amount']>0 ? '<span class="text-success">+'.formatCurrency($t['profit_amount']).'</span>' : '-'; ?></td>
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
