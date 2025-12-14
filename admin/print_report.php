<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header('Location: ../index.php');
  exit;
}

$reportType = $_GET['type'] ?? 'daily';
$date = $_GET['date'] ?? date('Y-m-d');

switch ($reportType) {
  case 'daily':
    $title = "Daily Sales Report - " . date('F d, Y', strtotime($date));
    $whereClause = "DATE(created_at) = '$date'";
    break;
  case 'weekly':
    $startOfWeek = date('Y-m-d', strtotime('monday this week', strtotime($date)));
    $endOfWeek = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
    $title = "Weekly Sales Report - " . date('F d', strtotime($startOfWeek)) . " to " . date('F d, Y', strtotime($endOfWeek));
    $whereClause = "DATE(created_at) BETWEEN '$startOfWeek' AND '$endOfWeek'";
    break;
  case 'monthly':
    $month = date('Y-m', strtotime($date));
    $title = "Monthly Sales Report - " . date('F Y', strtotime($date));
    $whereClause = "DATE_FORMAT(created_at, '%Y-%m') = '$month'";
    break;
  default:
    $title = "Sales Report";
    $whereClause = "1=1";
}

$summary = $conn->query("
  SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN payment_status = 'Paid' THEN total_cost ELSE 0 END) as total_revenue,
    SUM(CASE WHEN payment_status = 'Paid' THEN 1 ELSE 0 END) as paid_orders,
    SUM(CASE WHEN payment_status != 'Paid' THEN total_cost ELSE 0 END) as unpaid_amount,
    AVG(total_cost) as avg_order_value,
    COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_count,
    COUNT(CASE WHEN status = 'In Progress' THEN 1 END) as progress_count,
    COUNT(CASE WHEN status = 'Ready' THEN 1 END) as ready_count,
    COUNT(CASE WHEN status = 'Pickup' THEN 1 END) as completed_count
  FROM orders
  WHERE $whereClause
")->fetch_assoc();

$orders = $conn->query("
  SELECT o.*, u.name as customer, u.email
  FROM orders o
  JOIN users u ON o.user_id = u.id
  WHERE $whereClause
  ORDER BY o.created_at DESC
");

$services = $conn->query("
  SELECT options, COUNT(*) as count, SUM(total_cost) as revenue
  FROM orders
  WHERE $whereClause
  GROUP BY options
  ORDER BY revenue DESC
");

$paymentMethods = $conn->query("
  SELECT payment_method, COUNT(*) as count, SUM(total_cost) as total
  FROM orders
  WHERE $whereClause AND payment_status = 'Paid'
  GROUP BY payment_method
");
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?= $title ?></title>
<style>
@media print {
  .no-print { display: none !important; }
  body { margin: 0; padding: 20px; }
  .page-break { page-break-before: always; }
}

* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Segoe UI', Arial, sans-serif; padding: 40px; background: #f5f5f5; }
.report-container { max-width: 1000px; margin: 0 auto; background: white; padding: 40px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
.report-header { text-align: center; border-bottom: 3px solid #2563eb; padding-bottom: 20px; margin-bottom: 30px; }
.report-header h1 { font-size: 28px; color: #1f2937; margin-bottom: 10px; }
.report-header .subtitle { font-size: 18px; color: #6b7280; font-weight: 600; }
.report-header .meta { font-size: 12px; color: #9ca3af; margin-top: 10px; }
.summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0; }
.summary-box { background: #f9fafb; border-left: 4px solid #2563eb; padding: 20px; border-radius: 8px; }
.summary-box.revenue { border-left-color: #10b981; }
.summary-box.orders { border-left-color: #f59e0b; }
.summary-box.unpaid { border-left-color: #ef4444; }
.summary-label { font-size: 12px; color: #6b7280; text-transform: uppercase; font-weight: 600; margin-bottom: 8px; }
.summary-value { font-size: 28px; font-weight: 700; color: #1f2937; }
.report-section { margin: 30px 0; }
.section-title { font-size: 18px; font-weight: 700; color: #1f2937; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #e5e7eb; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; }
th { background: #1f2937; color: white; padding: 12px; text-align: left; font-size: 13px; text-transform: uppercase; }
td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; font-size: 13px; }
tr:hover { background: #f9fafb; }
.status-badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.status-pending { background: #fef3c7; color: #92400e; }
.status-progress { background: #dbeafe; color: #1e40af; }
.status-ready { background: #d1fae5; color: #065f46; }
.status-pickup { background: #e5e7eb; color: #374151; }
.report-footer { margin-top: 50px; padding-top: 20px; border-top: 2px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 12px; }
.print-controls { position: fixed; top: 20px; right: 20px; background: white; padding: 15px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); z-index: 1000; }
.print-btn { background: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; margin: 5px 0; display: block; width: 100%; }
.print-btn:hover { background: #1e40af; }
.print-btn.secondary { background: #6b7280; }
.chart-bar { height: 30px; background: #e5e7eb; border-radius: 4px; overflow: hidden; margin: 10px 0; }
.chart-fill { height: 100%; background: linear-gradient(90deg, #2563eb, #3b82f6); display: flex; align-items: center; padding: 0 10px; color: white; font-size: 12px; font-weight: 600; }
</style>
</head>
<body>

<div class="print-controls no-print">
  <button class="print-btn" onclick="window.print()">🖨️ Print Report</button>
  <button class="print-btn secondary" onclick="window.close()">✕ Close</button>
  <select onchange="location.href='print_report.php?type='+this.value" class="print-btn" style="cursor: pointer;">
    <option value="daily" <?= $reportType === 'daily' ? 'selected' : '' ?>>Daily Report</option>
    <option value="weekly" <?= $reportType === 'weekly' ? 'selected' : '' ?>>Weekly Report</option>
    <option value="monthly" <?= $reportType === 'monthly' ? 'selected' : '' ?>>Monthly Report</option>
  </select>
</div>

<div class="report-container">
  <div class="report-header">
    <h1>🧺 LAUNDRY MANAGEMENT SYSTEM</h1>
    <div class="subtitle"><?= $title ?></div>
    <div class="meta">
      Generated on <?= date('F d, Y h:i A') ?> by <?= htmlspecialchars($_SESSION['name']) ?>
    </div>
  </div>

  <div class="summary-grid">
    <div class="summary-box revenue">
      <div class="summary-label">Total Revenue</div>
      <div class="summary-value">₱<?= number_format($summary['total_revenue'], 2) ?></div>
    </div>
    <div class="summary-box orders">
      <div class="summary-label">Total Orders</div>
      <div class="summary-value"><?= $summary['total_orders'] ?></div>
    </div>
    <div class="summary-box">
      <div class="summary-label">Avg Order Value</div>
      <div class="summary-value">₱<?= number_format($summary['avg_order_value'], 2) ?></div>
    </div>
    <div class="summary-box unpaid">
      <div class="summary-label">Unpaid Amount</div>
      <div class="summary-value">₱<?= number_format($summary['unpaid_amount'], 2) ?></div>
    </div>
  </div>

  <div class="report-section">
    <h3 class="section-title">Order Status Distribution</h3>
    <table>
      <tr><th>Status</th><th>Count</th><th>Percentage</th></tr>
      <tr>
        <td><span class="status-badge status-pending">Pending</span></td>
        <td><?= $summary['pending_count'] ?></td>
        <td><?= $summary['total_orders'] > 0 ? number_format(($summary['pending_count'] / $summary['total_orders']) * 100, 1) : 0 ?>%</td>
      </tr>
      <tr>
        <td><span class="status-badge status-progress">In Progress</span></td>
        <td><?= $summary['progress_count'] ?></td>
        <td><?= $summary['total_orders'] > 0 ? number_format(($summary['progress_count'] / $summary['total_orders']) * 100, 1) : 0 ?>%</td>
      </tr>
      <tr>
        <td><span class="status-badge status-ready">Ready</span></td>
        <td><?= $summary['ready_count'] ?></td>
        <td><?= $summary['total_orders'] > 0 ? number_format(($summary['ready_count'] / $summary['total_orders']) * 100, 1) : 0 ?>%</td>
      </tr>
      <tr>
        <td><span class="status-badge status-pickup">Completed</span></td>
        <td><?= $summary['completed_count'] ?></td>
        <td><?= $summary['total_orders'] > 0 ? number_format(($summary['completed_count'] / $summary['total_orders']) * 100, 1) : 0 ?>%</td>
      </tr>
    </table>
  </div>

  <div class="report-section">
    <h3 class="section-title">Service Performance</h3>
    <?php while ($service = $services->fetch_assoc()): 
      $percentage = $summary['total_revenue'] > 0 ? ($service['revenue'] / $summary['total_revenue']) * 100 : 0;
    ?>
    <div style="margin: 15px 0;">
      <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
        <strong><?= htmlspecialchars($service['options']) ?></strong>
        <span>₱<?= number_format($service['revenue'], 2) ?> (<?= $service['count'] ?> orders)</span>
      </div>
      <div class="chart-bar">
        <div class="chart-fill" style="width: <?= $percentage ?>%;">
          <?= number_format($percentage, 1) ?>%
        </div>
      </div>
    </div>
    <?php endwhile; ?>
  </div>

  <?php if ($paymentMethods->num_rows > 0): ?>
  <div class="report-section">
    <h3 class="section-title">Payment Methods</h3>
    <table>
      <tr><th>Method</th><th>Transactions</th><th>Total Amount</th></tr>
      <?php while ($pm = $paymentMethods->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($pm['payment_method']) ?></td>
        <td><?= $pm['count'] ?></td>
        <td><strong>₱<?= number_format($pm['total'], 2) ?></strong></td>
      </tr>
      <?php endwhile; ?>
    </table>
  </div>
  <?php endif; ?>

  <div class="page-break"></div>

  <div class="report-section">
    <h3 class="section-title">Detailed Order List</h3>
    <table>
      <tr><th>Order #</th><th>Customer</th><th>Services</th><th>Amount</th><th>Status</th><th>Payment</th><th>Date</th></tr>
      <?php while ($order = $orders->fetch_assoc()): ?>
      <tr>
        <td><strong>#<?= $order['id'] ?></strong></td>
        <td><?= htmlspecialchars($order['customer']) ?></td>
        <td><?= htmlspecialchars($order['options']) ?></td>
        <td><strong>₱<?= number_format($order['total_cost'], 2) ?></strong></td>
        <td><span class="status-badge status-<?= strtolower(str_replace(' ', '', $order['status'])) ?>"><?= $order['status'] ?></span></td>
        <td><?= $order['payment_status'] ?></td>
        <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
      </tr>
      <?php endwhile; ?>
    </table>
  </div>

  <div class="report-footer">
    <p><strong>© <?= date('Y') ?> Laundry Management System</strong></p>
    <p>This is a computer-generated report. No signature required.</p>
    <p style="margin-top: 10px;">For inquiries: info@laundrysystem.com | Tel: (02) 1234-5678</p>
  </div>
</div>

<script>
if (window.location.href.indexOf('auto_print=1') > -1) {
  setTimeout(() => window.print(), 1000);
}
</script>

</body>
</html>