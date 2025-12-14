<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header('Location: ../index.php');
  exit;
}

$thisMonth = date('Y-m');
$lastMonth = date('Y-m', strtotime('-1 month'));

// ============================================================================
// MONTHLY COMPARISON (SECURE)
// ============================================================================

$stmt = $conn->prepare("
  SELECT 
    COUNT(*) as orders,
    SUM(CASE WHEN payment_status = 'Paid' THEN total_cost ELSE 0 END) as revenue
  FROM orders 
  WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
");
$stmt->bind_param('s', $thisMonth);
$stmt->execute();
$thisMonthData = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("
  SELECT 
    COUNT(*) as orders,
    SUM(CASE WHEN payment_status = 'Paid' THEN total_cost ELSE 0 END) as revenue
  FROM orders 
  WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
");
$stmt->bind_param('s', $lastMonth);
$stmt->execute();
$lastMonthData = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ============================================================================
// LAST 7 DAYS DATA (SECURE)
// ============================================================================

$last7Days = [];
for ($i = 6; $i >= 0; $i--) {
  $date = date('Y-m-d', strtotime("-$i days"));
  
  $stmt = $conn->prepare("
    SELECT 
      COUNT(*) as orders,
      SUM(CASE WHEN payment_status = 'Paid' THEN total_cost ELSE 0 END) as revenue
    FROM orders 
    WHERE DATE(created_at) = ?
  ");
  $stmt->bind_param('s', $date);
  $stmt->execute();
  $dayData = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  
  $last7Days[] = [
    'date' => date('D', strtotime($date)),
    'orders' => $dayData['orders'],
    'revenue' => $dayData['revenue'] ?? 0
  ];
}

// ============================================================================
// PEAK HOURS ANALYSIS (SECURE)
// ============================================================================

$peakHours = $conn->query("
  SELECT 
    HOUR(created_at) as hour,
    COUNT(*) as order_count
  FROM orders
  WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  GROUP BY HOUR(created_at)
  ORDER BY order_count DESC
  LIMIT 5
");

// ============================================================================
// CUSTOMER RETENTION
// ============================================================================

$totalCustomers = $conn->query("
  SELECT COUNT(DISTINCT COALESCE(user_id, customer_email)) as count 
  FROM orders 
  WHERE user_id IS NOT NULL OR customer_email IS NOT NULL
")->fetch_assoc()['count'];

$repeatCustomers = $conn->query("
  SELECT COUNT(*) as count FROM (
    SELECT COALESCE(user_id, customer_email) as identifier
    FROM orders 
    WHERE user_id IS NOT NULL OR customer_email IS NOT NULL
    GROUP BY identifier
    HAVING COUNT(*) > 1
  ) as repeat_customers
")->fetch_assoc()['count'];

$retentionRate = $totalCustomers > 0 ? ($repeatCustomers / $totalCustomers) * 100 : 0;

// ============================================================================
// AVERAGE PROCESSING TIME
// ============================================================================

$avgProcessingTime = $conn->query("
  SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_hours
  FROM orders
  WHERE status = 'Pickup' AND updated_at IS NOT NULL
")->fetch_assoc()['avg_hours'] ?? 0;
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Business Analytics</title>
<link rel="stylesheet" href="../css/admin-theme.css">
<style>
.analytics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 25px 0; }
.analytics-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
.analytics-card h3 { color: #1f2937; margin-bottom: 20px; font-size: 18px; }
.comparison-box { display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #f9fafb; border-radius: 8px; margin: 10px 0; }
.comparison-label { font-size: 13px; color: #6b7280; font-weight: 600; }
.comparison-value { font-size: 24px; font-weight: 700; color: #1f2937; }
.trend-indicator { font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 5px; }
.trend-up { color: #10b981; }
.trend-down { color: #ef4444; }
.chart-container { margin: 20px 0; }
.bar-chart { display: flex; align-items: flex-end; gap: 10px; height: 200px; }
.bar { flex: 1; background: linear-gradient(to top, #2563eb, #3b82f6); border-radius: 4px 4px 0 0; position: relative; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; transition: all 0.3s; }
.bar:hover { background: linear-gradient(to top, #1e40af, #2563eb); transform: translateY(-5px); }
.bar-label { font-size: 11px; color: #6b7280; margin-top: 8px; text-align: center; }
.bar-value { font-size: 12px; color: white; font-weight: 600; padding: 5px; }
.metric-box { background: #f9fafb; padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #2563eb; }
.metric-value { font-size: 36px; font-weight: 700; color: #1f2937; }
.metric-label { font-size: 13px; color: #6b7280; margin-top: 8px; }
.peak-hours-list { list-style: none; padding: 0; }
.peak-hours-list li { padding: 12px; background: #f9fafb; border-radius: 8px; margin: 8px 0; display: flex; justify-content: space-between; align-items: center; }
.hour-badge { background: #2563eb; color: white; padding: 5px 12px; border-radius: 20px; font-weight: 600; font-size: 12px; }
</style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="card">
  <h2>📊 Business Analytics</h2>
  <p style="color: #6b7280; margin-bottom: 25px;">Deep insights into your laundry business performance and trends.</p>

  <!-- Monthly Comparison -->
  <div class="analytics-grid">
    <div class="analytics-card">
      <h3>📈 Monthly Performance</h3>
      
      <div class="comparison-box">
        <div>
          <div class="comparison-label">This Month</div>
          <div class="comparison-value">₱<?= number_format($thisMonthData['revenue'], 2) ?></div>
          <div style="font-size: 13px; color: #6b7280; margin-top: 5px;"><?= $thisMonthData['orders'] ?> orders</div>
        </div>
        <?php 
        $revenueChange = $lastMonthData['revenue'] > 0 ? 
          (($thisMonthData['revenue'] - $lastMonthData['revenue']) / $lastMonthData['revenue']) * 100 : 0;
        $isPositive = $revenueChange >= 0;
        ?>
        <div class="trend-indicator <?= $isPositive ? 'trend-up' : 'trend-down' ?>">
          <?= $isPositive ? '↑' : '↓' ?> <?= abs(number_format($revenueChange, 1)) ?>%
        </div>
      </div>

      <div class="comparison-box">
        <div>
          <div class="comparison-label">Last Month</div>
          <div class="comparison-value">₱<?= number_format($lastMonthData['revenue'], 2) ?></div>
          <div style="font-size: 13px; color: #6b7280; margin-top: 5px;"><?= $lastMonthData['orders'] ?> orders</div>
        </div>
      </div>
    </div>

    <div class="analytics-card">
      <h3>🔁 Customer Retention</h3>
      <div class="metric-box">
        <div class="metric-value"><?= number_format($retentionRate, 1) ?>%</div>
        <div class="metric-label">Repeat Customer Rate</div>
      </div>
      <div style="margin-top: 15px; padding: 15px; background: #f0f9ff; border-radius: 8px;">
        <div style="display: flex; justify-content: space-between; margin: 5px 0;">
          <span style="color: #6b7280; font-size: 13px;">Total Customers:</span>
          <strong><?= $totalCustomers ?></strong>
        </div>
        <div style="display: flex; justify-content: space-between; margin: 5px 0;">
          <span style="color: #6b7280; font-size: 13px;">Repeat Customers:</span>
          <strong style="color: #10b981;"><?= $repeatCustomers ?></strong>
        </div>
      </div>
    </div>

    <div class="analytics-card">
      <h3>⏱️ Processing Time</h3>
      <div class="metric-box">
        <div class="metric-value"><?= number_format($avgProcessingTime, 1) ?>h</div>
        <div class="metric-label">Average Processing Time</div>
      </div>
      <div style="margin-top: 15px; padding: 15px; background: #fef3c7; border-radius: 8px; text-align: center;">
        <p style="color: #92400e; font-size: 13px; margin: 0;">
          <?php if ($avgProcessingTime < 24): ?>
            ✅ Excellent! Orders processed within 24 hours
          <?php elseif ($avgProcessingTime < 48): ?>
            ⚠️ Good! Most orders processed within 2 days
          <?php else: ?>
            ⏳ Consider optimizing workflow
          <?php endif; ?>
        </p>
      </div>
    </div>
  </div>

  <!-- Last 7 Days Chart -->
  <div class="analytics-card" style="margin: 20px 0;">
    <h3>📅 Last 7 Days Performance</h3>
    <div class="chart-container">
      <div class="bar-chart">
        <?php 
        $maxRevenue = max(array_column($last7Days, 'revenue'));
        foreach ($last7Days as $day): 
          $height = $maxRevenue > 0 ? ($day['revenue'] / $maxRevenue) * 100 : 0;
        ?>
        <div style="flex: 1; display: flex; flex-direction: column; align-items: center;">
          <div class="bar" style="height: <?= $height ?>%; min-height: 20px;" title="₱<?= number_format($day['revenue'], 2) ?>">
            <div class="bar-value"><?= $day['orders'] ?></div>
          </div>
          <div class="bar-label"><?= htmlspecialchars($day['date']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="text-align: center; margin-top: 15px; color: #6b7280; font-size: 13px;">
        Orders per day (Bar height represents revenue)
      </div>
    </div>
  </div>

  <!-- Peak Hours -->
  <div class="analytics-card">
    <h3>🕐 Peak Business Hours</h3>
    <p style="color: #6b7280; font-size: 14px; margin-bottom: 15px;">Top 5 busiest hours based on last 30 days</p>
    <ul class="peak-hours-list">
      <?php while ($hour = $peakHours->fetch_assoc()): ?>
      <li>
        <span class="hour-badge">
          <?php
          $h = $hour['hour'];
          if ($h == 0) echo '12 AM';
          elseif ($h < 12) echo $h . ' AM';
          elseif ($h == 12) echo '12 PM';
          else echo ($h - 12) . ' PM';
          ?>
        </span>
        <span style="font-weight: 600; color: #1f2937;"><?= $hour['order_count'] ?> orders</span>
      </li>
      <?php endwhile; ?>
    </ul>
  </div>
</div>

</body>
</html>