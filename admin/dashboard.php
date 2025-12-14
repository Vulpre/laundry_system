<?php
session_start();

require '../db_connect.php';
require '../includes/constants.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_ADMIN) {
    header('Location: ../index.php');
    exit;
}

$today = date('Y-m-d');
$thisMonth = date('Y-m');
$lastMonth = date('Y-m', strtotime('-1 month'));

// ============================================================================
// TODAY'S METRICS - Using Prepared Statements
// ============================================================================

$stmt = $conn->prepare("
    SELECT COUNT(*) AS count 
    FROM orders 
    WHERE DATE(created_at) = ?
");
$stmt->bind_param('s', $today);
$stmt->execute();
$todayOrders = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $conn->prepare("
    SELECT IFNULL(SUM(total_cost), 0) AS total 
    FROM orders 
    WHERE DATE(created_at) = ? 
      AND payment_status = 'Paid'
");
$stmt->bind_param('s', $today);
$stmt->execute();
$todayRevenue = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// ============================================================================
// THIS MONTH'S METRICS
// ============================================================================

$stmt = $conn->prepare("
    SELECT COUNT(*) AS count 
    FROM orders 
    WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
");
$stmt->bind_param('s', $thisMonth);
$stmt->execute();
$monthOrders = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $conn->prepare("
    SELECT IFNULL(SUM(total_cost), 0) AS total 
    FROM orders 
    WHERE DATE_FORMAT(created_at, '%Y-%m') = ? 
      AND payment_status = 'Paid'
");
$stmt->bind_param('s', $thisMonth);
$stmt->execute();
$monthRevenue = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("
    SELECT IFNULL(SUM(total_cost), 0) AS total 
    FROM orders 
    WHERE DATE_FORMAT(created_at, '%Y-%m') = ? 
      AND payment_status = 'Paid'
");
$stmt->bind_param('s', $lastMonth);
$stmt->execute();
$lastMonthRevenue = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$revenueGrowth = $lastMonthRevenue > 0
    ? (($monthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100
    : 0;

// ============================================================================
// OVERALL METRICS
// ============================================================================

$totalOrders = $conn->query("SELECT COUNT(*) AS count FROM orders")->fetch_assoc()['count'];

$activeOrders = $conn->query("
    SELECT COUNT(*) AS count 
    FROM orders 
    WHERE status NOT IN ('Pickup')
")->fetch_assoc()['count'];

$totalRevenue = $conn->query("
    SELECT IFNULL(SUM(total_cost), 0) AS total 
    FROM orders 
    WHERE payment_status = 'Paid'
")->fetch_assoc()['total'];

$pendingPayments = $conn->query("
    SELECT IFNULL(SUM(total_cost), 0) AS total 
    FROM orders 
    WHERE payment_status != 'Paid'
")->fetch_assoc()['total'];

$avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

// ============================================================================
// ORDER STATUS COUNTS
// ============================================================================

$pendingOrders = $conn->query("
    SELECT COUNT(*) AS count 
    FROM orders 
    WHERE status = 'Pending'
")->fetch_assoc()['count'];

$inProgressOrders = $conn->query("
    SELECT COUNT(*) AS count 
    FROM orders 
    WHERE status = 'In Progress'
")->fetch_assoc()['count'];

$readyOrders = $conn->query("
    SELECT COUNT(*) AS count 
    FROM orders 
    WHERE status = 'Ready'
")->fetch_assoc()['count'];

$completedOrders = $conn->query("
    SELECT COUNT(*) AS count 
    FROM orders 
    WHERE status = 'Pickup'
")->fetch_assoc()['count'];

// ============================================================================
// CUSTOMER METRICS
// ============================================================================

$totalCustomers = $conn->query("
    SELECT COUNT(*) AS count 
    FROM users 
    WHERE role = 'user'
")->fetch_assoc()['count'];

$stmt = $conn->prepare("
    SELECT COUNT(*) AS count 
    FROM users 
    WHERE role = 'user' 
      AND DATE_FORMAT(created_at, '%Y-%m') = ?
");
$stmt->bind_param('s', $thisMonth);
$stmt->execute();
$newCustomersMonth = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// ============================================================================
// RECENT ORDERS
// ============================================================================

$recentOrders = $conn->query("
    SELECT 
        o.id,
        COALESCE(o.customer_name, u.name) AS customer,
        o.total_cost,
        o.status,
        o.payment_status,
        o.created_at
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
    LIMIT 10
");
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../css/admin-theme.css">
    <style>
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .kpi-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }
        .kpi-card:hover {
            transform: translateY(-5px);
        }
        .kpi-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        .kpi-label {
            font-size: 13px;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .kpi-value {
            font-size: 32px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 8px;
        }
        .kpi-change {
            font-size: 13px;
            font-weight: 600;
        }
        .kpi-change.positive {
            color: #10b981;
        }
        .kpi-change.negative {
            color: #ef4444;
        }
        .dashboard-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin: 20px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .action-btn {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            text-decoration: none;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.3);
        }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: #1f2937; color: white; padding: 12px; text-align: left; }
        td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; }
        tr:hover { background: #f9fafb; }
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-progress { background: #dbeafe; color: #1e40af; }
        .status-ready { background: #d1fae5; color: #065f46; }
        .status-pickup { background: #e5e7eb; color: #374151; }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="card">
    <h2>📊 Admin Dashboard</h2>
    <p style="color: #6b7280; margin-bottom: 10px;">Welcome back, <?= htmlspecialchars($_SESSION['name']) ?>!</p>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="create_order.php" class="action-btn">➕ Create Order</a>
        <a href="manage_orders.php" class="action-btn">📦 Manage Orders</a>
        <a href="reports.php" class="action-btn">📈 View Reports</a>
        <a href="price_list.php" class="action-btn">💰 Update Prices</a>
    </div>

    <!-- KPI Grid -->
    <div class="kpi-grid">
        <!-- Today's Orders -->
        <div class="kpi-card" style="border-left: 4px solid #2563eb;">
            <div class="kpi-icon">📅</div>
            <div class="kpi-label">Today's Orders</div>
            <div class="kpi-value"><?= number_format($todayOrders) ?></div>
            <div class="kpi-change">Revenue: ₱<?= number_format($todayRevenue, 2) ?></div>
        </div>

        <!-- This Month -->
        <div class="kpi-card" style="border-left: 4px solid #10b981;">
            <div class="kpi-icon">📊</div>
            <div class="kpi-label">This Month</div>
            <div class="kpi-value"><?= number_format($monthOrders) ?></div>
            <div class="kpi-change <?= $revenueGrowth >= 0 ? 'positive' : 'negative' ?>">
                <?= $revenueGrowth >= 0 ? '↑' : '↓' ?> <?= abs(number_format($revenueGrowth, 1)) ?>%
            </div>
        </div>

        <!-- Total Revenue -->
        <div class="kpi-card" style="border-left: 4px solid #f59e0b;">
            <div class="kpi-icon">💰</div>
            <div class="kpi-label">Total Revenue</div>
            <div class="kpi-value">₱<?= number_format($totalRevenue, 0) ?></div>
            <div class="kpi-change">Avg: ₱<?= number_format($avgOrderValue, 2) ?></div>
        </div>

        <!-- Pending Payments -->
        <div class="kpi-card" style="border-left: 4px solid #ef4444;">
            <div class="kpi-icon">⏳</div>
            <div class="kpi-label">Pending Payments</div>
            <div class="kpi-value">₱<?= number_format($pendingPayments, 0) ?></div>
            <div class="kpi-change">Active: <?= $activeOrders ?> orders</div>
        </div>

        <!-- Order Status -->
        <div class="kpi-card" style="border-left: 4px solid #8b5cf6;">
            <div class="kpi-icon">📦</div>
            <div class="kpi-label">Order Status</div>
            <div class="kpi-value"><?= number_format($pendingOrders) ?></div>
            <div class="kpi-change">Pending</div>
        </div>

        <!-- In Progress -->
        <div class="kpi-card" style="border-left: 4px solid #06b6d4;">
            <div class="kpi-icon">🔄</div>
            <div class="kpi-label">In Progress</div>
            <div class="kpi-value"><?= number_format($inProgressOrders) ?></div>
            <div class="kpi-change">Processing</div>
        </div>

        <!-- Ready for Pickup -->
        <div class="kpi-card" style="border-left: 4px solid #10b981;">
            <div class="kpi-icon">✅</div>
            <div class="kpi-label">Ready</div>
            <div class="kpi-value"><?= number_format($readyOrders) ?></div>
            <div class="kpi-change">For Pickup</div>
        </div>

        <!-- Completed -->
        <div class="kpi-card" style="border-left: 4px solid #6b7280;">
            <div class="kpi-icon">🎉</div>
            <div class="kpi-label">Completed</div>
            <div class="kpi-value"><?= number_format($completedOrders) ?></div>
            <div class="kpi-change">Total</div>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="dashboard-section">
        <h3 style="color: #1f2937; margin-bottom: 15px;">📋 Recent Orders</h3>
        <table>
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recentOrders->num_rows > 0): ?>
                    <?php while ($order = $recentOrders->fetch_assoc()): ?>
                    <tr>
                        <td><strong>#<?= $order['id'] ?></strong></td>
                        <td><?= htmlspecialchars($order['customer']) ?></td>
                        <td><strong>₱<?= number_format($order['total_cost'], 2) ?></strong></td>
                        <td>
                            <span class="status-badge status-<?= strtolower(str_replace(' ', '', $order['status'])) ?>">
                                <?= htmlspecialchars($order['status']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($order['payment_status']) ?></td>
                        <td><?= date('M d, h:i A', strtotime($order['created_at'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px;">
                            No orders yet
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div style="text-align: center; margin-top: 20px;">
            <a href="manage_orders.php" style="color: #2563eb; font-weight: 600; text-decoration: none;">
                View All Orders →
            </a>
        </div>
    </div>
</div>

<script>
    // Auto-refresh every 5 minutes
    setTimeout(() => location.reload(), 300000);
</script>
</body>
</html>