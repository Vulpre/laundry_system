<?php
// This is a widget that can be included in dashboard or other pages
session_start();
require '../db_connect.php';

$today = date('Y-m-d');

// Today's stats
$todayStats = $conn->query("
  SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN payment_status = 'Paid' THEN total_cost ELSE 0 END) as revenue,
    COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending,
    COUNT(CASE WHEN status = 'Ready' THEN 1 END) as ready
  FROM orders 
  WHERE DATE(created_at) = '$today'
")->fetch_assoc();

// Pending payments
$pendingPayments = $conn->query("
  SELECT COUNT(*) as count, SUM(total_cost) as amount 
  FROM orders 
  WHERE payment_status != 'Paid'
")->fetch_assoc();

// Recent activity
$recentActivity = $conn->query("
  SELECT o.id, u.name, o.status, o.created_at
  FROM orders o
  JOIN users u ON o.user_id = u.id
  ORDER BY o.created_at DESC
  LIMIT 5
");
?>

<div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin: 20px 0;">
  <h3 style="color: #1f2937; margin-bottom: 15px; font-size: 18px;">⚡ Quick Stats</h3>
  
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
    <div style="background: #f0f9ff; padding: 15px; border-radius: 8px; border-left: 4px solid #2563eb;">
      <div style="font-size: 24px; font-weight: 700; color: #2563eb;"><?= $todayStats['total'] ?></div>
      <div style="font-size: 12px; color: #6b7280; margin-top: 5px;">Today's Orders</div>
    </div>
    
    <div style="background: #f0fdf4; padding: 15px; border-radius: 8px; border-left: 4px solid #10b981;">
      <div style="font-size: 24px; font-weight: 700; color: #10b981;">₱<?= number_format($todayStats['revenue'], 0) ?></div>
      <div style="font-size: 12px; color: #6b7280; margin-top: 5px;">Today's Revenue</div>
    </div>
    
    <div style="background: #fef3c7; padding: 15px; border-radius: 8px; border-left: 4px solid #f59e0b;">
      <div style="font-size: 24px; font-weight: 700; color: #f59e0b;"><?= $todayStats['pending'] ?></div>
      <div style="font-size: 12px; color: #6b7280; margin-top: 5px;">Pending Orders</div>
    </div>
    
    <div style="background: #fef2f2; padding: 15px; border-radius: 8px; border-left: 4px solid #ef4444;">
      <div style="font-size: 24px; font-weight: 700; color: #ef4444;">₱<?= number_format($pendingPayments['amount'], 0) ?></div>
      <div style="font-size: 12px; color: #6b7280; margin-top: 5px;">Unpaid (<?= $pendingPayments['count'] ?>)</div>
    </div>
  </div>

  <h4 style="color: #374151; font-size: 14px; margin: 15px 0 10px 0;">Recent Activity</h4>
  <div style="max-height: 200px; overflow-y: auto;">
    <?php while ($activity = $recentActivity->fetch_assoc()): ?>
    <div style="padding: 10px; background: #f9fafb; border-radius: 6px; margin: 5px 0; display: flex; justify-content: space-between; align-items: center;">
      <div>
        <strong style="color: #1f2937;">#<?= $activity['id'] ?></strong> - <?= htmlspecialchars($activity['name']) ?>
        <span style="font-size: 11px; color: #6b7280; margin-left: 10px;"><?= date('g:i A', strtotime($activity['created_at'])) ?></span>
      </div>
      <span style="font-size: 11px; padding: 4px 8px; background: #dbeafe; color: #1e40af; border-radius: 12px; font-weight: 600;">
        <?= $activity['status'] ?>
      </span>
    </div>
    <?php endwhile; ?>
  </div>
</div>