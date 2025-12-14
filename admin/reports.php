<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header('Location: ../index.php');
  exit;
}

$startDate = sanitizeText($_GET['start_date'] ?? date('Y-m-01'), 20);
$endDate = sanitizeText($_GET['end_date'] ?? date('Y-m-d'), 20);

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $startDate = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = date('Y-m-d');
}

// ============================================================================
// SALES DATA (SECURE)
// ============================================================================

$stmt = $conn->prepare("
  SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN payment_status = 'Paid' THEN total_cost ELSE 0 END) as total_revenue,
    SUM(CASE WHEN payment_status = 'Paid' THEN 1 ELSE 0 END) as paid_orders,
    SUM(CASE WHEN payment_status = 'Unpaid' THEN total_cost ELSE 0 END) as unpaid_amount,
    AVG(total_cost) as avg_order_value
  FROM orders
  WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$salesData = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ============================================================================
// DAILY BREAKDOWN (SECURE)
// ============================================================================

$stmt = $conn->prepare("
  SELECT 
    DATE(created_at) as date,
    COUNT(*) as orders,
    SUM(CASE WHEN payment_status = 'Paid' THEN total_cost ELSE 0 END) as revenue,
    SUM(CASE WHEN payment_status = 'Paid' THEN 1 ELSE 0 END) as paid_orders
  FROM orders
  WHERE DATE(created_at) BETWEEN ? AND ?
  GROUP BY DATE(created_at)
  ORDER BY date DESC
");
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$dailyBreakdown = $stmt->get_result();
$stmt->close();

// ============================================================================
// SERVICE BREAKDOWN (SECURE)
// ============================================================================

$stmt = $conn->prepare("
  SELECT 
    options,
    COUNT(*) as order_count,
    SUM(total_cost) as total_revenue
  FROM orders
  WHERE DATE(created_at) BETWEEN ? AND ?
  GROUP BY options
  ORDER BY total_revenue DESC
");
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$serviceBreakdown = $stmt->get_result();
$stmt->close();

// ============================================================================
// TOP CUSTOMERS (SECURE)
// ============================================================================

$stmt = $conn->prepare("
  SELECT 
    COALESCE(o.customer_name, u.name) as name,
    COALESCE(o.customer_email, u.email) as email,
    COUNT(o.id) as order_count,
    SUM(o.total_cost) as total_spent
  FROM orders o
  LEFT JOIN users u ON o.user_id = u.id
  WHERE DATE(o.created_at) BETWEEN ? AND ?
  GROUP BY COALESCE(o.customer_name, u.name), COALESCE(o.customer_email, u.email)
  ORDER BY total_spent DESC
  LIMIT 10
");
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$topCustomers = $stmt->get_result();
$stmt->close();

// ============================================================================
// PAYMENT METHODS (SECURE)
// ============================================================================

$stmt = $conn->prepare("
  SELECT 
    payment_method,
    COUNT(*) as count,
    SUM(total_cost) as total
  FROM orders
  WHERE DATE(created_at) BETWEEN ? AND ?
  AND payment_status = 'Paid'
  GROUP BY payment_method
");
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$paymentMethods = $stmt->get_result();
$stmt->close();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Reports & Analytics</title>
<link rel="stylesheet" href="../css/admin-theme.css">
<style>
.report-header { background: linear-gradient(135deg, #2563eb, #1e40af); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; }
.filter-panel { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
.filter-panel form { display: grid; grid-template-columns: 1fr 1fr auto auto; gap: 15px; align-items: end; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 25px 0; }
.stat-box { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); text-align: center; }
.stat-value { font-size: 32px; font-weight: 700; color: #1f2937; margin: 10px 0; }
.stat-label { font-size: 13px; color: #6b7280; font-weight: 600; text-transform: uppercase; }
.report-section { background: white; border-radius: 12px; padding: 25px; margin: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
.action-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
.btn-export { background: #10b981; color: white; padding: 12px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
.btn-export:hover { background: #059669; transform: translateY(-2px); }
.btn-print { background: #f59e0b; color: white; padding: 12px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; border: none; cursor: pointer; }
.btn-print:hover { background: #d97706; transform: translateY(-2px); }
table { width: 100%; border-collapse: collapse; margin-top: 15px; }
th { background: #1f2937; color: white; padding: 12px; text-align: left; }
td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; }
tr:hover { background: #f9fafb; }
@media print { .nav, .filter-panel, .action-buttons { display: none; } }
</style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="card">
  <div class="report-header">
    <h2 style="color: white; margin: 0;">📊 Sales Reports & Analytics</h2>
    <p style="margin: 10px 0 0 0; opacity: 0.9;">Comprehensive business insights and performance metrics</p>
  </div>

  <div class="filter-panel">
    <form method="get">
      <div>
        <label>Start Date</label>
        <input type="date" name="start_date" value="<?= esc($startDate) ?>" required>
      </div>
      <div>
        <label>End Date</label>
        <input type="date" name="end_date" value="<?= esc($endDate) ?>" required>
      </div>
      <button type="submit" style="background: #2563eb;">🔍 Filter</button>
      <a href="reports.php" style="background: #6b7280; color: white; padding: 12px 20px; border-radius: 8px; text-decoration: none; font-weight: 600;">Reset</a>
    </form>
  </div>

  <div class="stats-grid">
    <div class="stat-box" style="border-left: 4px solid #2563eb;">
      <div class="stat-label">Total Orders</div>
      <div class="stat-value"><?= number_format($salesData['total_orders']) ?></div>
    </div>
    <div class="stat-box" style="border-left: 4px solid #10b981;">
      <div class="stat-label">Total Revenue</div>
      <div class="stat-value">₱<?= number_format($salesData['total_revenue'], 2) ?></div>
    </div>
    <div class="stat-box" style="border-left: 4px solid #f59e0b;">
      <div class="stat-label">Avg Order Value</div>
      <div class="stat-value">₱<?= number_format($salesData['avg_order_value'], 2) ?></div>
    </div>
    <div class="stat-box" style="border-left: 4px solid #ef4444;">
      <div class="stat-label">Unpaid Amount</div>
      <div class="stat-value">₱<?= number_format($salesData['unpaid_amount'], 2) ?></div>
    </div>
    <div class="stat-box" style="border-left: 4px solid #8b5cf6;">
      <div class="stat-label">Paid Orders</div>
      <div class="stat-value"><?= number_format($salesData['paid_orders']) ?></div>
    </div>
  </div>

  <div class="action-buttons" style="margin: 25px 0;">
    <a href="export_csv.php?start=<?= esc($startDate) ?>&end=<?= esc($endDate) ?>" class="btn-export">📥 Export CSV</a>
    <a href="export_excel.php?start=<?= esc($startDate) ?>&end=<?= esc($endDate) ?>" class="btn-export" style="background: #06b6d4;">📊 Export Excel</a>
    <button onclick="window.print()" class="btn-print">🖨️ Print Report</button>
  </div>

  <div class="report-section">
    <h3>📅 Daily Sales Breakdown</h3>
    <table>
      <tr><th>Date</th><th>Orders</th><th>Paid Orders</th><th>Revenue</th><th>Avg per Order</th></tr>
      <?php if ($dailyBreakdown->num_rows > 0): ?>
        <?php while ($day = $dailyBreakdown->fetch_assoc()): ?>
        <tr>
          <td><strong><?= date('M d, Y (D)', strtotime($day['date'])) ?></strong></td>
          <td><?= $day['orders'] ?></td>
          <td><?= $day['paid_orders'] ?></td>
          <td><strong>₱<?= number_format($day['revenue'], 2) ?></strong></td>
          <td>₱<?= $day['orders'] > 0 ? number_format($day['revenue'] / $day['orders'], 2) : '0.00' ?></td>
        </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="5" style="text-align: center; padding: 30px;">No data for selected period</td></tr>
      <?php endif; ?>
    </table>
  </div>

  <div class="report-section">
    <h3>🧺 Service Performance</h3>
    <table>
      <tr><th>Service</th><th>Orders</th><th>Total Revenue</th><th>Avg per Order</th><th>% of Total</th></tr>
      <?php if ($serviceBreakdown->num_rows > 0): ?>
        <?php while ($service = $serviceBreakdown->fetch_assoc()): 
          $percentage = $salesData['total_revenue'] > 0 ? ($service['total_revenue'] / $salesData['total_revenue']) * 100 : 0;
        ?>
        <tr>
          <td><strong><?= esc($service['options']) ?></strong></td>
          <td><?= $service['order_count'] ?></td>
          <td><strong>₱<?= number_format($service['total_revenue'], 2) ?></strong></td>
          <td>₱<?= number_format($service['total_revenue'] / $service['order_count'], 2) ?></td>
          <td>
            <div style="display: flex; align-items: center; gap: 10px;">
              <div style="flex: 1; background: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden;">
                <div style="width: <?= $percentage ?>%; background: #2563eb; height: 100%;"></div>
              </div>
              <span><?= number_format($percentage, 1) ?>%</span>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="5" style="text-align: center; padding: 30px;">No service data</td></tr>
      <?php endif; ?>
    </table>
  </div>

  <div class="report-section">
    <h3>👑 Top Customers</h3>
    <table>
      <tr><th>Rank</th><th>Customer Name</th><th>Email</th><th>Orders</th><th>Total Spent</th><th>Avg per Order</th></tr>
      <?php if ($topCustomers->num_rows > 0): ?>
        <?php $rank = 1; while ($customer = $topCustomers->fetch_assoc()): ?>
        <tr>
          <td style="text-align: center;">
            <strong style="color: <?= $rank <= 3 ? '#f59e0b' : '#6b7280' ?>;">
              <?= $rank == 1 ? '🥇' : ($rank == 2 ? '🥈' : ($rank == 3 ? '🥉' : $rank)) ?>
            </strong>
          </td>
          <td><strong><?= esc($customer['name']) ?></strong></td>
          <td><?= esc($customer['email']) ?></td>
          <td><?= $customer['order_count'] ?></td>
          <td><strong>₱<?= number_format($customer['total_spent'], 2) ?></strong></td>
          <td>₱<?= number_format($customer['total_spent'] / $customer['order_count'], 2) ?></td>
        </tr>
        <?php $rank++; endwhile; ?>
      <?php else: ?>
        <tr><td colspan="6" style="text-align: center; padding: 30px;">No customer data</td></tr>
      <?php endif; ?>
    </table>
  </div>

  <div class="report-section">
    <h3>💳 Payment Method Breakdown</h3>
    <table>
      <tr><th>Payment Method</th><th>Transactions</th><th>Total Amount</th><th>% of Total</th></tr>
      <?php if ($paymentMethods->num_rows > 0): ?>
        <?php while ($pm = $paymentMethods->fetch_assoc()): 
          $percentage = $salesData['total_revenue'] > 0 ? ($pm['total'] / $salesData['total_revenue']) * 100 : 0;
        ?>
        <tr>
          <td><strong><?= esc($pm['payment_method']) ?></strong></td>
          <td><?= $pm['count'] ?></td>
          <td><strong>₱<?= number_format($pm['total'], 2) ?></strong></td>
          <td><?= number_format($percentage, 1) ?>%</td>
        </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="4" style="text-align: center; padding: 30px;">No payment data</td></tr>
      <?php endif; ?>
    </table>
  </div>
</div>

</body>
</html>