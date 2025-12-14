<?php
session_start();

require '../db_connect.php';
require '../includes/constants.php';
require '../includes/security.php';
require '../includes/notification_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== ROLE_ADMIN) {
    header('Location: ../index.php');
    exit;
}

$err = '';
$success = '';

// ============================================================================
// AJAX: SEND EMAIL NOTIFICATION
// ============================================================================

if (isset($_POST['ajax_send_email'])) {
    header('Content-Type: application/json');

    $order_id = intval($_POST['order_id'] ?? 0);

    if ($order_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT 
            o.*,
            COALESCE(o.customer_email, u.email) AS email,
            COALESCE(o.customer_name, u.name) AS name
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }

    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    $order = $result->fetch_assoc();
    $stmt->close();

    $emailBody = buildEmailTemplate([
        'greeting' => $order['name'],
        'subject' => 'Order Ready for Pickup',
        'body' => "
            <p>Your laundry order is ready for pickup!</p>
            <div style='background: #f0f9ff; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3 style='margin: 0; color: #1e40af;'>Order Details:</h3>
                <ul style='margin: 10px 0 0 0;'>
                    <li><strong>Order #:</strong> {$order_id}</li>
                    <li><strong>Services:</strong> " . htmlspecialchars($order['options']) . "</li>
                    <li><strong>Total Amount:</strong> ₱" . number_format($order['total_cost'], 2) . "</li>
                    <li><strong>Status:</strong> " . htmlspecialchars($order['status']) . "</li>
                </ul>
            </div>
            <p>Please collect your order at your earliest convenience.</p>
        "
    ]);

    $emailSent = sendEmailNotification($order['email'], "Order #{$order_id} Ready", $emailBody);

    echo json_encode([
        'success' => $emailSent,
        'message' => $emailSent ? 'Email sent successfully' : 'Failed to send email'
    ]);
    exit;
}

// ============================================================================
// DELETE ORDER
// ============================================================================

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    if ($id > 0) {
        $delStmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
        
        if (!$delStmt) {
            $err = "Database error: " . $conn->error;
        } else {
            $delStmt->bind_param('i', $id);

            if ($delStmt->execute()) {
                $success = "✅ Order #{$id} deleted successfully.";
                error_log("Order #{$id} deleted by admin {$_SESSION['name']}");
            } else {
                $err = "❌ Failed to delete order: " . $delStmt->error;
            }

            $delStmt->close();
        }
    } else {
        $err = "❌ Invalid order ID.";
    }
}

// ============================================================================
// UPDATE ORDER STATUS
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    verifyCsrfToken();

    $id = intval($_POST['order_id'] ?? 0);
    $status = validateOrderStatus($_POST['status'] ?? '');

    if (!$status) {
        $err = "❌ Invalid status selected.";
    } elseif ($id <= 0) {
        $err = "❌ Invalid order ID.";
    } else {
        $stmt = $conn->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");

        if (!$stmt) {
            $err = "❌ Database error: " . $conn->error;
        } else {
            $stmt->bind_param('si', $status, $id);

            if ($stmt->execute()) {
                $success = "✅ Order #{$id} status updated to {$status}.";
                error_log("Order #{$id} status changed to {$status} by admin {$_SESSION['name']}");

                // Send notification to customer
                $orderStmt = $conn->prepare("SELECT user_id FROM orders WHERE id = ?");
                $orderStmt->bind_param('i', $id);
                $orderStmt->execute();
                $orderResult = $orderStmt->get_result();

                if ($orderResult->num_rows > 0) {
                    $orderData = $orderResult->fetch_assoc();
                    if ($orderData['user_id']) {
                        notifyCustomerStatusChange($conn, $id, $orderData['user_id'], $status);
                    }
                }

                $orderStmt->close();
            } else {
                $err = "❌ Failed to update status: " . $stmt->error;
            }

            $stmt->close();
        }
    }
}

// ============================================================================
// UPDATE PAYMENT STATUS
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    verifyCsrfToken();

    $id = intval($_POST['order_id'] ?? 0);
    $payment_status = validatePaymentStatus($_POST['payment_status'] ?? '');

    if (!$payment_status) {
        $err = "❌ Invalid payment status selected.";
    } elseif ($id <= 0) {
        $err = "❌ Invalid order ID.";
    } else {
        $stmt = $conn->prepare("UPDATE orders SET payment_status = ?, updated_at = NOW() WHERE id = ?");

        if (!$stmt) {
            $err = "❌ Database error: " . $conn->error;
        } else {
            $stmt->bind_param('si', $payment_status, $id);

            if ($stmt->execute()) {
                $success = "✅ Order #{$id} payment updated to {$payment_status}.";
                error_log("Order #{$id} payment changed to {$payment_status} by admin {$_SESSION['name']}");

                if ($payment_status === PAYMENT_STATUS_PAID) {
                    $orderStmt = $conn->prepare("SELECT user_id, total_cost FROM orders WHERE id = ?");
                    $orderStmt->bind_param('i', $id);
                    $orderStmt->execute();
                    $orderResult = $orderStmt->get_result();

                    if ($orderResult->num_rows > 0) {
                        $orderData = $orderResult->fetch_assoc();
                        if ($orderData['user_id']) {
                            notifyCustomerPaymentReceived($conn, $id, $orderData['user_id'], $orderData['total_cost']);
                        }
                    }

                    $orderStmt->close();
                }
            } else {
                $err = "❌ Failed to update payment status: " . $stmt->error;
            }

            $stmt->close();
        }
    }
}

// ============================================================================
// SEARCH & FILTER ORDERS (SECURE)
// ============================================================================

$searchTerm = sanitizeText($_GET['search'] ?? '', 100);
$statusFilter = validateOrderStatus($_GET['status_filter'] ?? '');
$paymentFilter = validatePaymentStatus($_GET['payment_filter'] ?? '');

// Build WHERE clause with prepared statement placeholders
$whereClauses = ["1=1"];
$params = [];
$types = '';

if (!empty($searchTerm)) {
    $whereClauses[] = "(COALESCE(o.customer_name, u.name) LIKE ? OR o.id LIKE ?)";
    $searchPattern = "%{$searchTerm}%";
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $types .= 'ss';
}

if ($statusFilter) {
    $whereClauses[] = "o.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if ($paymentFilter) {
    $whereClauses[] = "o.payment_status = ?";
    $params[] = $paymentFilter;
    $types .= 's';
}

$whereSQL = implode(' AND ', $whereClauses);

$query = "
    SELECT 
        o.*,
        COALESCE(o.customer_name, u.name) AS customer,
        COALESCE(o.customer_email, u.email) AS email
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE {$whereSQL}
    ORDER BY o.created_at DESC
";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $orders = $stmt->get_result();
        $stmt->close();
    } else {
        $err = "❌ Database error: " . $conn->error;
        $orders = null;
    }
} else {
    $orders = $conn->query($query);
}

if (!$orders) {
    $err = "❌ Database error: " . $conn->error;
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Manage Orders</title>
    <link rel="stylesheet" href="../css/admin-theme.css">
    <style>
        .filter-bar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .filter-bar form {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }
        .filter-bar input, .filter-bar select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
        }
        .filter-bar button {
            background: #2563eb;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #1f2937; color: white; padding: 12px; text-align: left; font-size: 13px; }
        td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; }
        tr:hover { background: #f9fafb; }
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-inprogress { background: #dbeafe; color: #1e40af; }
        .status-ready { background: #d1fae5; color: #065f46; }
        .status-pickup { background: #e5e7eb; color: #374151; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-unpaid { background: #fee2e2; color: #991b1b; }
        .status-partial { background: #fef3c7; color: #92400e; }
        .action-btn {
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            margin: 2px;
        }
        .btn-edit { background: #3b82f6; color: white; }
        .btn-delete { background: #ef4444; color: white; }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
        }
        .modal-content h3 {
            margin-bottom: 20px;
            color: #1f2937;
        }
        .modal-content label {
            display: block;
            margin: 15px 0 5px;
            font-weight: 600;
            color: #374151;
        }
        .modal-content select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
        }
        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .modal-buttons button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-save { background: #10b981; color: white; }
        .btn-cancel { background: #6b7280; color: white; }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="card">
    <h2>📦 Manage Orders</h2>
    
    <?php if ($success): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="error"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="get">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Search</label>
                <input type="text" name="search" placeholder="Order ID or Customer name..." 
                       value="<?= esc($searchTerm) ?>">
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Status</label>
                <select name="status_filter">
                    <option value="">All Statuses</option>
                    <?php foreach (VALID_ORDER_STATUSES as $s): ?>
                        <option value="<?= esc($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>>
                            <?= esc($s) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Payment</label>
                <select name="payment_filter">
                    <option value="">All Payments</option>
                    <?php foreach (VALID_PAYMENT_STATUSES as $p): ?>
                        <option value="<?= esc($p) ?>" <?= $paymentFilter === $p ? 'selected' : '' ?>>
                            <?= esc($p) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit">🔍 Filter</button>
        </form>
    </div>

    <!-- Orders Table -->
    <div style="background: white; border-radius: 12px; overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Services</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($orders && $orders->num_rows > 0): ?>
                    <?php while ($o = $orders->fetch_assoc()): ?>
                    <tr>
                        <td><strong>#<?= $o['id'] ?></strong></td>
                        <td>
                            <?= esc($o['customer']) ?><br>
                            <small style="color: #6b7280;"><?= esc($o['email'] ?? '') ?></small>
                        </td>
                        <td><?= esc($o['options']) ?></td>
                        <td><strong>₱<?= number_format($o['total_cost'], 2) ?></strong></td>
                        <td>
                            <span class="status-badge status-<?= strtolower(str_replace(' ', '', $o['status'])) ?>">
                                <?= esc($o['status']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge status-<?= strtolower($o['payment_status']) ?>">
                                <?= esc($o['payment_status']) ?>
                            </span>
                        </td>
                        <td><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
                        <td>
                            <a href="#" class="action-btn btn-edit" 
                               onclick="editOrder(<?= $o['id'] ?>, '<?= esc($o['status']) ?>', '<?= esc($o['payment_status']) ?>')">
                                ✏️ Edit
                            </a>
                            <a href="?delete=<?= $o['id'] ?>" class="action-btn btn-delete" 
                               onclick="return confirm('Delete order #<?= $o['id'] ?>?')">
                                🗑️ Delete
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">
                            No orders found
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <h3>Edit Order #<span id="modalOrderId"></span></h3>
        <form method="post" id="editForm">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
            <input type="hidden" name="order_id" id="editOrderId">
            
            <label>Order Status</label>
            <select name="status" id="editStatus">
                <?php foreach (VALID_ORDER_STATUSES as $s): ?>
                    <option value="<?= esc($s) ?>"><?= esc($s) ?></option>
                <?php endforeach; ?>
            </select>
            
            <label>Payment Status</label>
            <select name="payment_status" id="editPayment">
                <?php foreach (VALID_PAYMENT_STATUSES as $p): ?>
                    <option value="<?= esc($p) ?>"><?= esc($p) ?></option>
                <?php endforeach; ?>
            </select>
            
            <div class="modal-buttons">
                <button type="submit" name="update_status" class="btn-save">💾 Update Status</button>
                <button type="submit" name="update_payment" class="btn-save">💳 Update Payment</button>
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function editOrder(id, status, payment) {
    document.getElementById('modalOrderId').textContent = id;
    document.getElementById('editOrderId').value = id;
    document.getElementById('editStatus').value = status;
    document.getElementById('editPayment').value = payment;
    document.getElementById('editModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>

</body>
</html>