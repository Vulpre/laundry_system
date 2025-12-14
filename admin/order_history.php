<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header('Location: ../index.php');
  exit;
}

$success = '';
$error = '';

// Clear all completed orders history
if (isset($_POST['clear_history'])) {
  if ($conn->query("DELETE FROM orders WHERE status = 'Pickup'")) {
    $success = "✅ Order history cleared successfully!";
  } else {
    $error = "❌ Failed to clear history: " . $conn->error;
  }
}

// Archive order (move to history - optional, for now just mark as archived)
if (isset($_GET['archive'])) {
  $order_id = intval($_GET['archive']);
  if ($conn->query("UPDATE orders SET status = 'Archived' WHERE id = $order_id")) {
    $success = "Order #$order_id archived successfully!";
  }
}

// Date filter
$filterDate = $_GET['filter_date'] ?? '';
$filterMonth = $_GET['filter_month'] ?? '';

$whereClause = "status = 'Pickup'"; // Only show completed orders

if ($filterDate) {
  $whereClause .= " AND DATE(created_at) = '" . $conn->real_escape_string($filterDate) . "'";
} else if ($filterMonth) {
  $whereClause .= " AND DATE_FORMAT(created_at, '%Y-%m') = '" . $conn->real_escape_string($filterMonth) . "'";
}

// Get all completed orders with customer info (FIXED QUERY - removed updated_at reference)
$history = $conn->query("
  SELECT o.*, 
  COALESCE(o.customer_name, u.name) AS customer,
  COALESCE(o.customer_email, u.email) AS email
  FROM orders o
  LEFT JOIN users u ON o.user_id = u.id
  WHERE $whereClause
  ORDER BY o.created_at DESC
");

// Calculate statistics
$stats = $conn->query("
  SELECT 
    COUNT(*) as total_orders,
    SUM(total_cost) as total_revenue,
    AVG(total_cost) as avg_order_value,
    SUM(CASE WHEN payment_status = 'Paid' THEN 1 ELSE 0 END) as paid_count,
    SUM(CASE WHEN payment_status = 'Paid' THEN total_cost ELSE 0 END) as paid_revenue
  FROM orders
  WHERE $whereClause
")->fetch_assoc();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Order History - Completed Orders</title>
<link rel="stylesheet" href="../css/admin-theme.css">
<style>
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
  margin: 20px 0;
}
.stat-card {
  background: white;
  padding: 20px;
  border-radius: 10px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  text-align: center;
  border-left: 4px solid #2563eb;
}
.stat-card.green { border-left-color: #10b981; }
.stat-card.orange { border-left-color: #f59e0b; }
.stat-card.purple { border-left-color: #8b5cf6; }
.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #1f2937;
  margin: 10px 0;
}
.stat-label {
  font-size: 13px;
  color: #6b7280;
  font-weight: 600;
  text-transform: uppercase;
}
.filter-bar {
  background: white;
  padding: 20px;
  border-radius: 12px;
  margin-bottom: 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.filter-bar form {
  display: grid;
  grid-template-columns: 1fr 1fr auto auto auto;
  gap: 15px;
  align-items: end;
}
.filter-bar label {
  display: block;
  font-weight: 600;
  color: #374151;
  margin-bottom: 8px;
  font-size: 14px;
}
.filter-bar input[type="date"],
.filter-bar input[type="month"] {
  width: 100%;
  padding: 12px;
  border: 2px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #1f2937;
  background: #f9fafb;
  transition: all 0.3s;
}
.filter-bar input[type="date"]:focus,
.filter-bar input[type="month"]:focus {
  border-color: #2563eb;
  background: white;
  outline: none;
  box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}
.filter-bar button,
.filter-bar a {
  padding: 12px 20px;
  border-radius: 8px;
  border: none;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: all 0.3s;
  white-space: nowrap;
}
.action-buttons {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.btn {
  padding: 10px 20px;
  border-radius: 8px;
  border: none;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.3s;
}
.btn-danger {
  background: #ef4444;
  color: white;
}
.btn-danger:hover {
  background: #dc2626;
  transform: translateY(-2px);
}
.btn-export {
  background: #10b981;
  color: white;
}
.btn-export:hover {
  background: #059669;
  transform: translateY(-2px);
}
.btn-print {
  background: #f59e0b;
  color: white;
}
.btn-print:hover {
  background: #d97706;
  transform: translateY(-2px);
}

@media (max-width: 900px) {
  .filter-bar form {
    grid-template-columns: 1fr;
  }
  .stats-grid {
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  }
}
</style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="card">
  <h2>📜 Order History - Completed Orders</h2>
  <p style="color: #6b7280; margin-bottom: 20px;">View all completed and picked up orders</p>

  <?php if ($success): ?>
    <div class="success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Statistics -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-label">Total Completed</div>
      <div class="stat-value"><?= number_format($stats['total_orders']) ?></div>
    </div>
    <div class="stat-card green">
      <div class="stat-label">Total Revenue</div>
      <div class="stat-value">₱<?= number_format($stats['total_revenue'], 2) ?></div>
    </div>
    <div class="stat-card orange">
      <div class="stat-label">Avg Order Value</div>
      <div class="stat-value">₱<?= number_format($stats['avg_order_value'], 2) ?></div>
    </div>
    <div class="stat-card purple">
      <div class="stat-label">Paid Orders</div>
      <div class="stat-value"><?= $stats['paid_count'] ?> / <?= $stats['total_orders'] ?></div>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="filter-bar">
    <form method="get">
      <div>
        <label>Filter by Date</label>
        <input type="date" name="filter_date" value="<?= htmlspecialchars($filterDate) ?>">
      </div>
      <div>
        <label>Filter by Month</label>
        <input type="month" name="filter_month" value="<?= htmlspecialchars($filterMonth) ?>">
      </div>
      <button type="submit" style="background: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer;">🔍 Filter</button>
      <a href="order_history.php" style="background: #6b7280; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600;">Reset</a>
      <a href="export_csv.php?start=<?= $filterMonth ?: date('Y-m-01') ?>&end=<?= $filterDate ?: date('Y-m-d') ?>" class="btn btn-export">📥 Export CSV</a>
    </form>
  </div>

  <!-- Action Buttons -->
  <div class="action-buttons">
    <button onclick="window.print()" class="btn btn-print">🖨️ Print History</button>
    <form method="post" onsubmit="return confirm('⚠️ This will permanently delete all completed orders from history. Are you sure?');" style="display: inline;">
      <button type="submit" name="clear_history" class="btn btn-danger">🗑️ Clear All History</button>
    </form>
  </div>

  <!-- Order History Table -->
  <div style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
    <table class="table">
      <thead>
        <tr>
          <th>Order #</th>
          <th>Customer</th>
          <th>Services</th>
          <th>Mode</th>
          <th>Payment Method</th>
          <th>Total</th>
          <th>Payment Status</th>
          <th>Order Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($history->num_rows > 0): ?>
          <?php while ($o = $history->fetch_assoc()): ?>
          <tr>
            <td><strong>#<?= htmlspecialchars($o['id']) ?></strong></td>
            <td>
              <?= htmlspecialchars($o['customer']) ?><br>
              <small style="color: #6b7280;"><?= htmlspecialchars($o['email'] ?? '') ?></small>
            </td>
            <td><?= htmlspecialchars($o['options']) ?></td>
            <td><?= htmlspecialchars($o['service_mode']) ?></td>
            <td><?= htmlspecialchars($o['payment_method']) ?></td>
            <td><strong>₱<?= number_format($o['total_cost'], 2) ?></strong></td>
            <td>
              <span class="<?= $o['payment_status'] == 'Paid' ? 'status-paid' : 'status-unpaid' ?>">
                <?= htmlspecialchars($o['payment_status']) ?>
              </span>
            </td>
            <td><?= date('M d, Y h:i A', strtotime($o['created_at'])) ?></td>
            <td>
              <a href="print_receipt.php?id=<?= $o['id'] ?>" target="_blank" 
                style="color: #2563eb; text-decoration: none; font-weight: 600;" 
                title="Print Receipt">
                🖨️
              </a>
            </td>
          </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="9" style="text-align: center; padding: 40px; color: #6b7280;">
              <div style="font-size: 48px; margin-bottom: 15px;">📭</div>
              <strong>No completed orders yet</strong>
              <p style="margin: 10px 0 0 0; font-size: 14px;">Completed orders will appear here</p>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($history->num_rows > 0): ?>
  <div style="margin-top: 20px; padding: 15px; background: #f9fafb; border-radius: 8px; text-align: center;">
    <p style="color: #6b7280; margin: 0;">
      Showing <strong><?= $history->num_rows ?></strong> completed order(s)
      <?php if ($filterDate || $filterMonth): ?>
        | <a href="order_history.php" style="color: #2563eb; text-decoration: underline;">View all</a>
      <?php endif; ?>
    </p>
  </div>
  <?php endif; ?>
</div>

<script>
// Print functionality
window.onbeforeprint = function() {
  document.querySelector('.card h2').textContent = 'Order History Report - Generated: ' + new Date().toLocaleString();
}
</script>

</body>
</html>