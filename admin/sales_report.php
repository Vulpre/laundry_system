<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$err = '';
$success = '';

// ============================================================================
// CLEAR SALES DATA
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_sales'])) {
  $delete = $conn->query("DELETE FROM orders");
  if ($delete) {
    $success = "✅ All sales data has been cleared successfully!";
    error_log("All sales data cleared by admin {$_SESSION['name']} (ID: {$_SESSION['user_id']})");
  } else {
    $err = "❌ Failed to clear sales report: " . $conn->error;
  }
}

// ============================================================================
// DATE FILTERS (SECURE)
// ============================================================================

$from = sanitizeText($_GET['from'] ?? date('Y-m-d', strtotime('-7 days')), 20);
$to = sanitizeText($_GET['to'] ?? date('Y-m-d'), 20);

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-d', strtotime('-7 days'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-d');
}

// ============================================================================
// FETCH SALES DATA (SECURE)
// ============================================================================

$stmt = $conn->prepare('
  SELECT 
    DATE(created_at) as d, 
    COUNT(*) as orders, 
    IFNULL(SUM(total_cost),0) as revenue
  FROM orders
  WHERE DATE(created_at) BETWEEN ? AND ?
  GROUP BY DATE(created_at)
  ORDER BY DATE(created_at) ASC
');

$stmt->bind_param('ss', $from, $to);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Sales Report</title>
<link rel="stylesheet" href="../css/admin-theme.css">
<style>
.card {
  background: white;
  border-radius: 12px;
  padding: 25px 30px;
  box-shadow: 0 5px 20px rgba(0,0,0,0.15);
  max-width: 800px;
  margin: 40px auto;
}
button {
  background: #2563eb;
  color: white;
  border: none;
  padding: 10px 18px;
  border-radius: 6px;
  cursor: pointer;
  transition: 0.3s;
  font-weight: 600;
}
button:hover {
  background: #1e40af;
}
.clear-btn {
  background: #ef4444;
  margin-top: 15px;
}
.clear-btn:hover {
  background: #b91c1c;
}
.success, .error {
  margin: 15px 0;
  padding: 10px 15px;
  border-radius: 6px;
  font-weight: 600;
}
.success {
  background: #d1fae5;
  color: #065f46;
}
.error {
  background: #fee2e2;
  color: #991b1b;
}
.table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 20px;
}
.table th, .table td {
  border: 1px solid #ddd;
  padding: 10px;
  text-align: center;
}
.table th {
  background: #2563eb;
  color: white;
}
form[method="get"] {
  display: grid;
  grid-template-columns: 1fr 1fr auto;
  gap: 15px;
  align-items: end;
  margin-bottom: 20px;
  padding: 20px;
  background: #f9fafb;
  border-radius: 8px;
}
form[method="get"] label {
  display: block;
  font-weight: 600;
  margin-bottom: 6px;
  color: #374151;
}
form[method="get"] input {
  width: 100%;
  padding: 10px;
  border: 2px solid #e5e7eb;
  border-radius: 8px;
}
</style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="card">
  <h2>📊 Sales Report</h2>

  <?php if($err): ?><div class="error"><?= esc($err) ?></div><?php endif; ?>
  <?php if($success): ?><div class="success"><?= esc($success) ?></div><?php endif; ?>

  <form method="get">
    <div>
      <label>From</label>
      <input type="date" name="from" value="<?= esc($from) ?>" required>
    </div>
    <div>
      <label>To</label>
      <input type="date" name="to" value="<?= esc($to) ?>" required>
    </div>
    <button type="submit">🔍 Filter</button>
  </form>

  <form method="post" onsubmit="return confirm('⚠️ Are you sure you want to clear all sales data? This action cannot be undone.');">
    <button type="submit" name="clear_sales" class="clear-btn">🗑️ Clear Sales Report</button>
  </form>

  <table class="table">
    <tr>
      <th>Date</th>
      <th>Orders</th>
      <th>Revenue</th>
    </tr>
    <?php if (count($data) > 0): ?>
      <?php foreach($data as $r): ?>
        <tr>
          <td><?= esc($r['d']) ?></td>
          <td><?= esc($r['orders']) ?></td>
          <td>₱<?= number_format($r['revenue'],2) ?></td>
        </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr><td colspan="3">No sales data for the selected period.</td></tr>
    <?php endif; ?>
  </table>
</div>

</body>
</html>