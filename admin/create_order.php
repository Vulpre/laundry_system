<?php
require '../includes/security.php';
require '../db_connect.php';
require '../includes/notification_helper.php';
require '../includes/constants.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_ADMIN) {
  header('Location: ../index.php');
  exit;
}

if (!checkRateLimit('create_order', 10, 60)) {
  die('Too many requests. Please wait a moment before creating another order.');
}

$success = '';
$error = '';

$services = [
  ['name' => 'Regular Clothes', 'price' => 60, 'unit' => 'kg', 'description' => 'Wash + Dry + Iron'],
  ['name' => 'Delicate Fabrics', 'price' => 80, 'unit' => 'kg', 'description' => 'Special care + Dry + Iron'],
  ['name' => 'Beddings (Queen)', 'price' => 200, 'unit' => 'set', 'description' => 'Sheets, pillowcases, covers'],
  ['name' => 'Beddings (King)', 'price' => 250, 'unit' => 'set', 'description' => 'Sheets, pillowcases, covers'],
  ['name' => 'Comforter (Single)', 'price' => 150, 'unit' => 'piece', 'description' => 'Wash + Dry'],
  ['name' => 'Comforter (Double)', 'price' => 200, 'unit' => 'piece', 'description' => 'Wash + Dry'],
  ['name' => 'Blanket', 'price' => 120, 'unit' => 'piece', 'description' => 'Wash + Dry'],
  ['name' => 'Curtains (per panel)', 'price' => 100, 'unit' => 'panel', 'description' => 'Wash + Dry + Iron'],
  ['name' => 'Table Cloth', 'price' => 80, 'unit' => 'piece', 'description' => 'Wash + Dry + Iron'],
  ['name' => 'Towels (Bath)', 'price' => 30, 'unit' => 'piece', 'description' => 'Wash + Dry + Fold']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $error = "Invalid security token. Please refresh and try again.";
  } else {
    
    $customer_name = trim(strip_tags($_POST['customer_name'] ?? ''));
    $customer_email = trim(strip_tags($_POST['customer_email'] ?? ''));
    $customer_phone = trim(strip_tags($_POST['customer_phone'] ?? ''));
    
    if (strlen($customer_name) < 2 || strlen($customer_name) > 255) {
      $error = "Customer name must be between 2 and 255 characters.";
    }
    
    if (!$error && $customer_email && !filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
      $error = "Invalid email format.";
    }
    
    $allowedServices = [
      'Regular Clothes' => ['price' => 60, 'unit' => 'kg'],
      'Delicate Fabrics' => ['price' => 80, 'unit' => 'kg'],
      'Beddings (Queen)' => ['price' => 200, 'unit' => 'set'],
      'Beddings (King)' => ['price' => 250, 'unit' => 'set'],
      'Comforter (Single)' => ['price' => 150, 'unit' => 'piece'],
      'Comforter (Double)' => ['price' => 200, 'unit' => 'piece'],
      'Blanket' => ['price' => 120, 'unit' => 'piece'],
      'Curtains (per panel)' => ['price' => 100, 'unit' => 'panel'],
      'Table Cloth' => ['price' => 80, 'unit' => 'piece'],
      'Towels (Bath)' => ['price' => 30, 'unit' => 'piece']
    ];
    
    $selected_services = $_POST['services'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $service_mode = $_POST['service_mode'] ?? 'Regular';
    $notes = trim(strip_tags($_POST['notes'] ?? ''));
    
    if (!in_array($service_mode, ['Regular', 'Express'])) {
      $error = "Invalid service mode selected.";
      $service_mode = 'Regular';
    }
    
    if (!$error && (empty($selected_services) || !$customer_name)) {
      $error = "Please enter customer name and select at least one service.";
    } else if (!$error) {
      $total = 0;
      $orderItems = [];
      
      foreach ($selected_services as $index => $service) {
        if (!isset($allowedServices[$service])) {
          $error = "Invalid service selected: " . htmlspecialchars($service);
          break;
        }
        
        $qty = floatval($quantities[$index] ?? 0);
        
        if ($qty < 0 || $qty > 1000) {
          $error = "Invalid quantity for service: " . htmlspecialchars($service);
          break;
        }
        
        if ($qty > 0) {
          $serviceData = $allowedServices[$service];
          $itemTotal = $serviceData['price'] * $qty;
          $total += $itemTotal;
          $orderItems[] = [
            'service' => $service,
            'quantity' => $qty,
            'unit' => $serviceData['unit'],
            'price' => $serviceData['price'],
            'total' => $itemTotal
          ];
        }
      }
      
      if ($total < 0 || $total > 100000) {
        $error = "Invalid order total amount.";
      }
      
      if (!$error && $service_mode === 'Express') {
        $total += 100;
      }
      
      if (strlen($notes) > 1000) {
        $notes = substr($notes, 0, 1000);
      }
      
      if (!$error && !empty($orderItems)) {
        $options = implode(', ', array_map(function($item) {
          return $item['service'] . ' (' . $item['quantity'] . ' ' . $item['unit'] . ')';
        }, $orderItems));
        
        $daysToAdd = $service_mode === 'Express' ? 1 : 3;
        $dueDate = date('Y-m-d', strtotime("+$daysToAdd days"));
        
        $payment_method = 'Pay on Pickup';
        $payment_status = 'Unpaid';
        $status = 'Pending';
        
        $db = getDatabase();
        $stmt = $db->prepare("
          INSERT INTO orders (customer_name, customer_email, customer_phone, options, service_mode, payment_method, payment_status, total_cost, status, notes, due_date, created_at) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        if ($stmt) {
          $stmt->bind_param('sssssssdsss', 
            $customer_name, 
            $customer_email, 
            $customer_phone, 
            $options, 
            $service_mode, 
            $payment_method, 
            $payment_status, 
            $total, 
            $status, 
            $notes, 
            $dueDate
          );
          if ($stmt->execute()) {
            $order_id = $db->insert_id;
            error_log("Order #$order_id created by admin {$_SESSION['name']} (ID: {$_SESSION['user_id']})");
            if ($customer_email && filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
              sendEmailNotification(
                $customer_email,
                'New Laundry Order #' . $order_id,
                "<p>Hello <strong>" . htmlspecialchars($customer_name) . "</strong>,</p>
                 <p>Thank you for choosing our laundry service! Your order has been received.</p>
                 <h3>Order Details:</h3>
                 <ul>
                   <li><strong>Order #:</strong> $order_id</li>
                   <li><strong>Services:</strong> " . htmlspecialchars($options) . "</li>
                   <li><strong>Service Mode:</strong> $service_mode</li>
                   <li><strong>Ready By:</strong> " . date('F d, Y', strtotime($dueDate)) . "</li>
                   <li><strong>Total Amount:</strong> ₱" . number_format($total, 2) . "</li>
                 </ul>
                 <p style='background: #fef3c7; padding: 15px; border-radius: 8px; color: #92400e;'>
                   <strong>💰 Payment:</strong> Please pay ₱" . number_format($total, 2) . " when you pick up your order.
                 </p>
                 <p>We will notify you when your order is ready for pickup.</p>"
              );
            }
            $admins = $db->query("SELECT id, email, name FROM users WHERE role = 'admin'");
            while ($admin = $admins->fetch_assoc()) {
              sendNotification(
                $db,
                $admin['id'],
                'order',
                'New Order Received',
                "Order #$order_id from $customer_name - Total: ₱" . number_format($total, 2),
                'manage_orders.php'
              );
            }
            $success = "✅ Order #$order_id created successfully! Total: ₱" . number_format($total, 2) . " - Customer will pay on pickup.";
            if ($customer_email) {
              $success .= " Email confirmation sent to $customer_email";
            }
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_POST = [];
          } else {
            $error = "Failed to create order: " . $stmt->error;
            error_log("Order creation failed: " . $stmt->error);
          }
          $stmt->close();
        } else {
          $error = "Database error. Please try again.";
          error_log("Failed to prepare statement: " . $db->error);
        }
      }
    }
  }
}

if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Create New Order</title>
<link rel="stylesheet" href="../css/admin-theme.css">
<style>
.order-form-container { max-width: 1200px; margin: 0 auto; }
.form-section { background: white; padding: 25px; border-radius: 12px; margin: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
.form-section h3 { color: #1f2937; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e5e7eb; display: flex; align-items: center; gap: 10px; }
.form-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; font-weight: 600; color: #374151; margin-bottom: 8px; font-size: 14px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #2563eb; outline: none; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
.services-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; }
.service-card { border: 2px solid #e5e7eb; border-radius: 10px; padding: 18px; transition: all 0.3s; cursor: pointer; background: white; }
.service-card:hover { border-color: #2563eb; box-shadow: 0 4px 12px rgba(37,99,235,0.15); transform: translateY(-2px); }
.service-card.selected { border-color: #2563eb; background: #f0f9ff; }
.service-card input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; accent-color: #2563eb; }
.service-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.service-name { font-weight: 700; color: #1f2937; font-size: 16px; flex: 1; }
.service-price { color: #2563eb; font-weight: 700; font-size: 20px; }
.service-description { color: #6b7280; font-size: 12px; margin-bottom: 10px; display: flex; align-items: center; gap: 5px; }
.service-unit { color: #6b7280; font-size: 13px; background: #f3f4f6; padding: 4px 10px; border-radius: 12px; display: inline-block; margin-top: 5px; }
.quantity-input { margin-top: 12px; display: none; }
.service-card.selected .quantity-input { display: block; }
.summary-box { background: linear-gradient(135deg, #2563eb, #1e40af); color: white; padding: 25px; border-radius: 12px; position: sticky; top: 20px; }
.summary-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.2); }
.summary-total { font-size: 28px; font-weight: 700; margin-top: 20px; padding-top: 20px; border-top: 2px solid rgba(255,255,255,0.3); text-align: center; }
.btn-create { background: #10b981; color: white; padding: 18px 32px; border: none; border-radius: 10px; font-weight: 700; font-size: 18px; cursor: pointer; width: 100%; margin-top: 20px; transition: all 0.3s; box-shadow: 0 4px 15px rgba(16,185,129,0.3); }
.btn-create:hover { background: #059669; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(16,185,129,0.4); }
.btn-create:disabled { background: #9ca3af; cursor: not-allowed; transform: none; box-shadow: none; }
.mode-selector { display: flex; gap: 15px; }
.mode-option { flex: 1; padding: 20px; border: 2px solid #e5e7eb; border-radius: 10px; text-align: center; cursor: pointer; transition: all 0.3s; background: white; }
.mode-option:hover { border-color: #2563eb; transform: translateY(-2px); }
.mode-option.selected { border-color: #2563eb; background: #f0f9ff; box-shadow: 0 4px 12px rgba(37,99,235,0.15); }
.mode-option input[type="radio"] { display: none; }
.mode-icon { font-size: 32px; margin-bottom: 10px; }
.mode-title { font-weight: 700; color: #1f2937; margin-bottom: 8px; font-size: 16px; }
.mode-desc { font-size: 13px; color: #6b7280; line-height: 1.4; }
.mode-price { font-size: 14px; color: #2563eb; font-weight: 600; margin-top: 8px; }
.info-banner { background: #f0f9ff; border-left: 4px solid #2563eb; padding: 15px; border-radius: 8px; margin: 20px 0; }
.info-banner p { color: #1e40af; margin: 5px 0; font-size: 14px; }
.customer-inputs { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.required-mark { color: #ef4444; }
</style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="card">
  <div class="order-form-container">
    <h2>🧺 Create New Laundry Order</h2>
    <p style="color: #6b7280; margin-bottom: 10px;">Quick order creation - No customer registration required!</p>

    <div class="info-banner">
      <p><strong>📌 Service Includes:</strong> All clothes are washed, dried, and ironed professionally</p>
      <p><strong>💰 Payment:</strong> Customer pays total amount when picking up the order</p>
    </div>

    <?php if ($success): ?>
      <div class="success">
        <?= $success ?>
        <div style="margin-top: 15px; display: flex; gap: 15px;">
          <a href="manage_orders.php" style="color: #065f46; text-decoration: underline; font-weight: 600;">📋 View All Orders</a>
          <a href="create_order.php" style="color: #065f46; text-decoration: underline; font-weight: 600;">➕ Create Another Order</a>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" id="orderForm">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      
      <div class="form-grid">
        <div>
          
          <div class="form-section">
            <h3>👤 Customer Information</h3>
            <div class="form-group">
              <label>Customer Name <span class="required-mark">*</span></label>
              <input type="text" name="customer_name" id="customer_name" required 
                placeholder="Enter customer name" onchange="updateSummary()" maxlength="255">
            </div>
            
            <div class="customer-inputs">
              <div class="form-group">
                <label>Email (Optional for notifications)</label>
                <input type="email" name="customer_email" id="customer_email" 
                  placeholder="customer@email.com" maxlength="255">
              </div>
              
              <div class="form-group">
                <label>Phone Number (Optional)</label>
                <input type="text" name="customer_phone" id="customer_phone" 
                  placeholder="09XX XXX XXXX" maxlength="50">
              </div>
            </div>
            
            <div style="background: #fef3c7; padding: 12px; border-radius: 6px; margin-top: 10px;">
              <p style="color: #92400e; font-size: 13px; margin: 0;">
                💡 Tip: Add email to send order confirmation and ready notifications to customer
              </p>
            </div>
          </div>

          <div class="form-section">
            <h3>🧺 Select Laundry Services</h3>
            <div class="services-grid">
              <?php foreach ($services as $index => $service): ?>
              <div class="service-card" onclick="toggleService(<?= $index ?>)">
                <div class="service-header">
                  <input type="checkbox" name="services[]" value="<?= htmlspecialchars($service['name']) ?>" id="service_<?= $index ?>" onchange="updateTotal()">
                  <span class="service-name"><?= htmlspecialchars($service['name']) ?></span>
                </div>
                <div class="service-description">
                  ✓ <?= htmlspecialchars($service['description']) ?>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                  <span class="service-price">₱<?= number_format($service['price'], 2) ?></span>
                  <span class="service-unit">per <?= $service['unit'] ?></span>
                </div>
                <div class="quantity-input">
                  <label style="font-size: 13px; color: #6b7280; margin-bottom: 5px;">Quantity (<?= $service['unit'] ?>)</label>
                  <input type="number" name="quantities[]" step="0.5" min="0" max="1000" value="1" 
                    data-price="<?= $service['price'] ?>" 
                    class="quantity-field" 
                    onchange="updateTotal()" 
                    style="padding: 10px; font-size: 16px; font-weight: 600;">
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="form-section">
            <h3>⚡ Delivery Time</h3>
            <div class="mode-selector">
              <label class="mode-option selected" onclick="selectMode('Regular')">
                <input type="radio" name="service_mode" value="Regular" checked onchange="updateTotal()">
                <div class="mode-icon">🕐</div>
                <div class="mode-title">Regular Service</div>
                <div class="mode-desc">Ready in 3 days<br>Standard processing</div>
                <div class="mode-price">No extra charge</div>
              </label>
              <label class="mode-option" onclick="selectMode('Express')">
                <input type="radio" name="service_mode" value="Express" onchange="updateTotal()">
                <div class="mode-icon">⚡</div>
                <div class="mode-title">Express Service</div>
                <div class="mode-desc">Ready in 24 hours<br>Priority processing</div>
                <div class="mode-price">+ ₱100.00</div>
              </label>
            </div>
          </div>

          <div class="form-section">
            <h3>📝 Special Instructions</h3>
            <div class="form-group">
              <label>Notes (Optional)</label>
              <textarea name="notes" rows="3" maxlength="1000" placeholder="e.g., Separate whites and colors, Extra fabric softener, Fold neatly..."></textarea>
            </div>
          </div>
        </div>

        <div>
          <div class="summary-box">
            <h3 style="color: white; margin: 0 0 20px 0; border: none; padding: 0; font-size: 20px;">📋 Order Summary</h3>
            
            <div id="selectedServices" style="min-height: 120px; max-height: 300px; overflow-y: auto;">
              <p style="color: rgba(255,255,255,0.7); font-size: 14px; text-align: center; padding: 40px 0;">
                Select services to begin
              </p>
            </div>

            <div class="summary-item" id="expressFee" style="display: none;">
              <span>⚡ Express Service</span>
              <span style="font-weight: 700;">₱100.00</span>
            </div>

            <div class="summary-total">
              <div style="color: rgba(255,255,255,0.8); font-size: 14px; margin-bottom: 5px;">Total Amount</div>
              <div id="totalAmount" style="font-size: 36px;">₱0.00</div>
              <div style="color: rgba(255,255,255,0.7); font-size: 13px; margin-top: 10px;">💰 Pay on pickup</div>
            </div>

            <div style="background: rgba(255,255,255,0.1); padding: 18px; border-radius: 10px; margin-top: 20px;">
              <div style="font-size: 13px; opacity: 0.9; margin-bottom: 10px;">
                <strong>📅 Ready by:</strong><br>
                <span id="dueDate" style="font-size: 16px; font-weight: 600;">--</span>
              </div>
              <div style="font-size: 13px; opacity: 0.9;">
                <strong>👤 Customer:</strong><br>
                <span id="customerName" style="font-size: 14px;">Not entered</span>
              </div>
            </div>

            <button type="submit" class="btn-create" id="submitBtn" disabled>
              🛒 Create Order
            </button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function toggleService(index) {
  const checkbox = document.getElementById('service_' + index);
  const card = checkbox.closest('.service-card');
  
  checkbox.checked = !checkbox.checked;
  
  if (checkbox.checked) {
    card.classList.add('selected');
  } else {
    card.classList.remove('selected');
  }
  
  updateTotal();
}

function selectMode(mode) {
  document.querySelectorAll('.mode-option').forEach(opt => opt.classList.remove('selected'));
  event.currentTarget.classList.add('selected');
  updateTotal();
}

function updateSummary() {
  const customerName = document.getElementById('customer_name').value.trim();
  document.getElementById('customerName').textContent = customerName || 'Not entered';
  updateTotal();
}

function updateTotal() {
  let total = 0;
  let servicesList = [];
  
  document.querySelectorAll('.service-card.selected').forEach(card => {
    const checkbox = card.querySelector('input[type="checkbox"]');
    const quantityInput = card.querySelector('.quantity-field');
    const price = parseFloat(quantityInput.dataset.price);
    const quantity = parseFloat(quantityInput.value) || 0;
    const serviceName = checkbox.value;
    
    if (quantity > 0 && quantity <= 1000) {
      const itemTotal = price * quantity;
      total += itemTotal;
      servicesList.push({
        name: serviceName,
        quantity: quantity,
        price: price,
        total: itemTotal
      });
    }
  });
  
  const expressMode = document.querySelector('input[name="service_mode"]:checked').value;
  if (expressMode === 'Express') {
    total += 100;
    document.getElementById('expressFee').style.display = 'flex';
  } else {
    document.getElementById('expressFee').style.display = 'none';
  }
  
  const servicesDiv = document.getElementById('selectedServices');
  if (servicesList.length > 0) {
    servicesDiv.innerHTML = servicesList.map(item => `
      <div class="summary-item">
        <div>
          <div style="font-weight: 600; font-size: 14px;">${item.name}</div>
          <div style="font-size: 12px; opacity: 0.8; margin-top: 3px;">${item.quantity} × ₱${item.price.toFixed(2)}</div>
        </div>
        <span style="font-weight: 700; font-size: 16px;">₱${item.total.toFixed(2)}</span>
      </div>
    `).join('');
  } else {
    servicesDiv.innerHTML = '<p style="color: rgba(255,255,255,0.7); font-size: 14px; text-align: center; padding: 40px 0;">Select services to begin</p>';
  }
  
  document.getElementById('totalAmount').textContent = '₱' + total.toFixed(2);
  
  const daysToAdd = expressMode === 'Express' ? 1 : 3;
  const dueDate = new Date();
  dueDate.setDate(dueDate.getDate() + daysToAdd);
  document.getElementById('dueDate').textContent = dueDate.toLocaleDateString('en-US', { 
    weekday: 'long',
    month: 'short', 
    day: 'numeric', 
    year: 'numeric' 
  });
  
  const customerName = document.getElementById('customer_name').value.trim();
  const servicesSelected = servicesList.length > 0;
  document.getElementById('submitBtn').disabled = !(customerName && servicesSelected);
}

document.getElementById('customer_name').addEventListener('input', updateSummary);

document.getElementById('orderForm').addEventListener('submit', function(e) {
  const customerName = document.getElementById('customer_name').value.trim();
  const selectedServices = document.querySelectorAll('.service-card.selected').length;
  
  if (!customerName || selectedServices === 0) {
    e.preventDefault();
    alert('Please enter customer name and select at least one service.');
    return false;
  }
  
  if (!confirm('Create this order?')) {
    e.preventDefault();
    return false;
  }
});

let formChanged = false;
document.getElementById('orderForm').addEventListener('change', function() {
  formChanged = true;
});

window.addEventListener('beforeunload', function(e) {
  if (formChanged) {
    e.preventDefault();
    e.returnValue = '';
    return '';
  }
});

document.getElementById('orderForm').addEventListener('submit', function() {
  formChanged = false;
});

document.addEventListener('DOMContentLoaded', function() {
  updateTotal();
});
</script>
</body>
</html>