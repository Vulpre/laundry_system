<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header('Location: ../index.php');
  exit;
}

$order_id = $_GET['id'] ?? null;
if (!$order_id) {
  die('Order ID required');
}

$order = $conn->query("
  SELECT o.*, u.name as customer_name, u.email, u.id as customer_id
  FROM orders o
  JOIN users u ON o.user_id = u.id
  WHERE o.id = $order_id
")->fetch_assoc();

if (!$order) {
  die('Order not found');
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Receipt #<?= $order_id ?></title>
<style>
@media print {
  .no-print { display: none; }
  body { margin: 0; }
}

* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Courier New', monospace; padding: 20px; background: #f5f5f5; }
.receipt-container { max-width: 80mm; margin: 0 auto; background: white; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
.receipt-header { text-align: center; border-bottom: 2px dashed #333; padding-bottom: 15px; margin-bottom: 15px; }
.receipt-header h1 { font-size: 24px; margin-bottom: 5px; }
.receipt-header .subtitle { font-size: 12px; color: #666; }
.info-section { margin: 15px 0; font-size: 12px; }
.info-row { display: flex; justify-content: space-between; margin: 5px 0; }
.info-label { font-weight: bold; }
.items-section { margin: 20px 0; border-top: 2px dashed #333; border-bottom: 2px dashed #333; padding: 15px 0; }
.items-header { display: flex; justify-content: space-between; font-weight: bold; margin-bottom: 10px; font-size: 12px; }
.item-row { display: flex; justify-content: space-between; margin: 8px 0; font-size: 12px; }
.total-section { margin: 15px 0; font-size: 14px; }
.total-row { display: flex; justify-content: space-between; margin: 8px 0; }
.total-row.grand-total { font-size: 18px; font-weight: bold; border-top: 2px solid #333; padding-top: 10px; margin-top: 10px; }
.footer { text-align: center; margin-top: 20px; padding-top: 15px; border-top: 2px dashed #333; font-size: 11px; }
.status-badge { display: inline-block; padding: 5px 10px; border-radius: 3px; font-size: 11px; font-weight: bold; margin-top: 5px; }
.status-paid { background: #d1fae5; color: #065f46; }
.status-unpaid { background: #fee2e2; color: #991b1b; }
.status-partial { background: #fef3c7; color: #92400e; }
.barcode { text-align: center; margin: 15px 0; font-size: 24px; letter-spacing: 2px; font-family: 'Courier New', monospace; }
.print-btn { background: #2563eb; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px; margin: 20px auto; display: block; }
.print-btn:hover { background: #1e40af; }
</style>
</head>
<body>

<div class="receipt-container">
  <div class="receipt-header">
    <h1>🧺 LAUNDRY SHOP</h1>
    <div class="subtitle">Professional Laundry Services</div>
    <div class="subtitle">123 Main Street, City</div>
    <div class="subtitle">Tel: (02) 1234-5678</div>
    <div class="subtitle">Email: info@laundryshop.com</div>
  </div>

  <div class="info-section">
    <div class="info-row">
      <span class="info-label">Receipt No:</span>
      <span>#<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">Date:</span>
      <span><?= date('M d, Y h:i A', strtotime($order['created_at'])) ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">Customer:</span>
      <span><?= htmlspecialchars($order['customer_name']) ?></span>
    </div>
    <?php if ($order['email']): ?>
    <div class="info-row">
      <span class="info-label">Email:</span>
      <span><?= htmlspecialchars($order['email']) ?></span>
    </div>
    <?php endif; ?>
    <div class="info-row">
      <span class="info-label">Status:</span>
      <span><?= htmlspecialchars($order['status']) ?></span>
    </div>
  </div>

  <div class="barcode">|||| |||| ||||</div>

  <div class="items-section">
    <div class="items-header">
      <span>ITEM</span>
      <span>AMOUNT</span>
    </div>
    
    <div class="item-row">
      <span><?= htmlspecialchars($order['options']) ?></span>
      <span>₱<?= number_format($order['total_cost'], 2) ?></span>
    </div>
    
    <?php if ($order['service_mode']): ?>
    <div class="item-row" style="font-size: 10px; color: #666;">
      <span>Service Mode: <?= htmlspecialchars($order['service_mode']) ?></span>
      <span></span>
    </div>
    <?php endif; ?>
  </div>

  <div class="total-section">
    <div class="total-row">
      <span>Subtotal:</span>
      <span>₱<?= number_format($order['total_cost'], 2) ?></span>
    </div>
    <div class="total-row">
      <span>Tax (0%):</span>
      <span>₱0.00</span>
    </div>
    <div class="total-row grand-total">
      <span>TOTAL:</span>
      <span>₱<?= number_format($order['total_cost'], 2) ?></span>
    </div>
    
    <div style="margin-top: 10px; text-align: center;">
      <span class="status-badge status-<?= strtolower($order['payment_status']) ?>">
        <?= strtoupper($order['payment_status']) ?>
      </span>
    </div>
    
    <?php if ($order['payment_method']): ?>
    <div class="info-row" style="margin-top: 10px;">
      <span class="info-label">Payment Method:</span>
      <span><?= htmlspecialchars($order['payment_method']) ?></span>
    </div>
    <?php endif; ?>
  </div>

  <div class="footer">
    <p><strong>Thank you for your business!</strong></p>
    <p style="margin: 10px 0;">Please keep this receipt for pickup</p>
    <p>For inquiries: info@laundryshop.com</p>
    <p style="margin-top: 10px; font-size: 10px;">
      Processed by: <?= htmlspecialchars($_SESSION['name']) ?>
    </p>
    <p style="font-size: 10px;"><?= date('Y-m-d H:i:s') ?></p>
  </div>
</div>

<button class="print-btn no-print" onclick="window.print()">🖨️ Print Receipt</button>
<button class="print-btn no-print" onclick="window.close()" style="background: #6b7280;">Close</button>

<script>
window.onload = function() {
  if (window.location.href.indexOf('auto_print=1') > -1) {
    setTimeout(() => window.print(), 500);
  }
};
</script>

</body>
</html>