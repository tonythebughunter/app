<?php
require_once '../config/database.php';
requireAdmin();

$conn = getConnection();

// Get activity logs with user details
$stmt = $conn->query("
    SELECT l.*, u.name as user_name, u.email as user_email 
    FROM logs l 
    LEFT JOIN users u ON l.user_id = u.id 
    ORDER BY l.timestamp DESC 
    LIMIT 500
");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get log statistics
$stmt = $conn->query("SELECT COUNT(*) as total_logs FROM logs");
$totalLogs = $stmt->fetch(PDO::FETCH_ASSOC)['total_logs'];

$stmt = $conn->query("SELECT COUNT(*) as today_logs FROM logs WHERE DATE(timestamp) = CURDATE()");
$todayLogs = $stmt->fetch(PDO::FETCH_ASSOC)['today_logs'];

$stmt = $conn->query("SELECT COUNT(*) as week_logs FROM logs WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$weekLogs = $stmt->fetch(PDO::FETCH_ASSOC)['week_logs'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - P2P Shares</title>
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
        .log-row {
            border-left: 3px solid #dee2e6;
            transition: all 0.2s;
        }
        .log-row:hover {
            border-left-color: #667eea;
            background-color: #f8f9fa;
        }
        .action-login { border-left-color: #28a745; }
        .action-logout { border-left-color: #dc3545; }
        .action-purchase { border-left-color: #007bff; }
        .action-confirm { border-left-color: #17a2b8; }
        .action-admin { border-left-color: #ffc107; }
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
                        <a class="nav-link text-white" href="transactions.php">All Transactions</a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white active" href="logs.php">Activity Logs</a>
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
                <h2 class="mb-4">Activity Logs</h2>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h5>Total Logs</h5>
                                <h3><?php echo number_format($totalLogs); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h5>Today's Activity</h5>
                                <h3><?php echo number_format($todayLogs); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <h5>This Week</h5>
                                <h3><?php echo number_format($weekLogs); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity Logs -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Recent Activity</h5>
                        <input type="text" class="form-control mb-3" id="logSearch" placeholder="Search logs...">
                        
                        <?php if (empty($logs)): ?>
                            <p class="text-muted text-center py-4">No activity logs found</p>
                        <?php else: ?>
                            <div class="activity-timeline" id="logsContainer">
                                <?php foreach($logs as $log): ?>
                                <div class="log-row p-3 mb-2 rounded <?php 
                                    if (strpos($log['action'], 'logged in') !== false) echo 'action-login';
                                    elseif (strpos($log['action'], 'logged out') !== false) echo 'action-logout';
                                    elseif (strpos($log['action'], 'purchase') !== false) echo 'action-purchase';
                                    elseif (strpos($log['action'], 'Confirmed') !== false) echo 'action-confirm';
                                    elseif (strpos($log['action'], 'Added') !== false || strpos($log['action'], 'Created') !== false || strpos($log['action'], 'Updated') !== false || strpos($log['action'], 'Deleted') !== false) echo 'action-admin';
                                ?>">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($log['user_name'] ?: 'Unknown User'); ?></strong>
                                                    <small class="text-muted">(<?php echo htmlspecialchars($log['user_email'] ?: 'N/A'); ?>)</small>
                                                </div>
                                            </div>
                                            <div class="mt-1">
                                                <span class="text-dark"><?php echo htmlspecialchars($log['action']); ?></span>
                                                <?php if ($log['details']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($log['details']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($log['timestamp'])); ?><br>
                                                <?php echo date('g:i:s A', strtotime($log['timestamp'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Load More Button -->
                            <div class="text-center mt-3">
                                <button class="btn btn-outline-primary" onclick="loadMoreLogs()">Load More Logs</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Legend -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h6 class="card-title">Activity Types</h6>
                        <div class="row">
                            <div class="col-md-2">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="me-2" style="width: 20px; height: 3px; background-color: #28a745;"></div>
                                    <small>Login</small>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="me-2" style="width: 20px; height: 3px; background-color: #dc3545;"></div>
                                    <small>Logout</small>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="me-2" style="width: 20px; height: 3px; background-color: #007bff;"></div>
                                    <small>Purchase</small>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="me-2" style="width: 20px; height: 3px; background-color: #17a2b8;"></div>
                                    <small>Confirmation</small>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="me-2" style="width: 20px; height: 3px; background-color: #ffc107;"></div>
                                    <small>Admin Action</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Search functionality
        document.getElementById('logSearch').addEventListener('keyup', function() {
            const filter = this.value.toUpperCase();
            const logs = document.querySelectorAll('.log-row');
            
            logs.forEach(function(log) {
                const text = log.textContent || log.innerText;
                if (text.toUpperCase().indexOf(filter) > -1) {
                    log.style.display = '';
                } else {
                    log.style.display = 'none';
                }
            });
        });

        // Load more logs functionality (placeholder)
        function loadMoreLogs() {
            alert('Load more functionality would be implemented here with AJAX');
        }

        // Real-time updates (placeholder)
        function refreshLogs() {
            // This would fetch new logs via AJAX
            console.log('Refreshing logs...');
        }

        // Auto-refresh every 30 seconds
        setInterval(refreshLogs, 30000);
    </script>
</body>
</html>