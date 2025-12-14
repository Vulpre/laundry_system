<?php
session_start();
require '../db_connect.php';
require '../includes/notification_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header('Location: ../index.php');
  exit;
}

$result = '';

if (isset($_POST['test'])) {
  try {
    if (sendNotification($conn, $_SESSION['user_id'], 'info', '🧪 Test Notification', 'This is a test notification. If you see this, notifications are working!', 'notifications.php')) {
      $result = "✅ Test notification sent successfully! Check your notifications page.";
    } else {
      $result = "❌ Failed to send notification. Check error logs.";
    }
  } catch (Exception $e) {
    $result = "❌ Error: " . $e->getMessage();
  }
}

if (isset($_POST['test_order'])) {
  try {
    notifyNewOrder($conn, 9999, "Test Customer", 500);
    $result = "✅ Test order notification sent! Check notifications and email.";
  } catch (Exception $e) {
    $result = "❌ Error: " . $e->getMessage();
  }
}

if (isset($_POST['test_email'])) {
  $test_email = $_POST['email'] ?? '';
  
  $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
  $stmt->bind_param('i', $_SESSION['user_id']);
  $stmt->execute();
  $user_name = $stmt->get_result()->fetch_assoc()['name'];
  $stmt->close();
  
  try {
    if (testEmailSending($test_email, $user_name)) {
      $result = "✅ Test email sent to $test_email! Check your inbox.";
    } else {
      $result = "❌ Failed to send email. Check SMTP configuration.";
    }
  } catch (Exception $e) {
    $result = "❌ Email Error: " . $e->getMessage();
  }
}

try {
  $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ?");
  $stmt->bind_param('i', $_SESSION['user_id']);
  $stmt->execute();
  $notifCount = $stmt->get_result()->fetch_assoc()['count'];
  $stmt->close();
} catch (Exception $e) {
  $notifCount = 0;
  $result = "⚠️ Notifications table may not exist. Error: " . $e->getMessage();
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Test Notifications & Email</title>
<link rel="stylesheet" href="../css/admin-theme.css">
<style>
.test-section {
  background: white;
  padding: 25px;
  border-radius: 12px;
  margin: 20px 0;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.test-section h3 {
  color: #1f2937;
  margin-bottom: 15px;
  padding-bottom: 10px;
  border-bottom: 2px solid #e5e7eb;
}
.info-box {
  background: #f9fafb;
  padding: 15px;
  border-radius: 8px;
  margin: 15px 0;
}
.warning-box {
  background: #fef3c7;
  border-left: 4px solid #f59e0b;
  padding: 15px;
  border-radius: 8px;
  margin: 15px 0;
  color: #92400e;
}
</style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="card">
  <h2>🧪 Test Notification & Email System</h2>
  <p style="color: #6b7280; margin-bottom: 20px;">Test your notification and email configuration</p>
  
  <?php if ($result): ?>
    <div class="<?= strpos($result, '✅') !== false ? 'success' : 'error' ?>">
      <?= $result ?>
    </div>
  <?php endif; ?>

  <div class="info-box">
    <p><strong>👤 Your User ID:</strong> <?= $_SESSION['user_id'] ?></p>
    <p><strong>📧 Your Email:</strong> <?php
    $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $email = $stmt->get_result()->fetch_assoc()['email'];
    $stmt->close();
    echo $email ?? 'Not set';
    ?></p>
    <p><strong>🔔 Your Notifications:</strong> <?= $notifCount ?> total</p>
  </div>

  <div class="test-section">
    <h3>🔔 Test System Notifications</h3>
    <p style="color: #6b7280; margin-bottom: 15px;">These notifications appear in the notifications page</p>
    
    <form method="post" style="display: inline-block; margin: 10px;">
      <button type="submit" name="test" style="background: #2563eb; color: white; padding: 15px 30px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
        🔔 Send Test Notification
      </button>
    </form>

    <form method="post" style="display: inline-block; margin: 10px;">
      <button type="submit" name="test_order" style="background: #10b981; color: white; padding: 15px 30px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
        📦 Test Order Notification
      </button>
    </form>
  </div>

  <div class="test-section">
    <h3>📧 Test Email Sending</h3>
    <p style="color: #6b7280; margin-bottom: 15px;">Send a test email to verify SMTP configuration</p>
    
    <form method="post" style="max-width: 500px;">
      <div style="margin-bottom: 15px;">
        <label style="display: block; font-weight: 600; margin-bottom: 8px;">Email Address</label>
        <input type="email" name="email" placeholder="your-email@gmail.com" required
          style="width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px;">
      </div>
      <button type="submit" name="test_email" style="background: #f59e0b; color: white; padding: 15px 30px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
        📧 Send Test Email
      </button>
    </form>

    <div class="warning-box">
      <p style="margin: 0;"><strong>⚠️ Important:</strong> Make sure you've configured email settings in <code>notification_helper.php</code>!</p>
      <ul style="margin: 10px 0 0 20px;">
        <li>Update SMTP settings for Gmail</li>
        <li>Or use PHP's built-in mail() function</li>
        <li>Test with a real email address</li>
      </ul>
    </div>
  </div>

  <div style="margin-top: 20px; text-align: center;">
    <a href="notifications.php" style="color: #2563eb; text-decoration: underline; font-weight: 600; font-size: 16px;">
      → Go to Notifications Page
    </a>
  </div>

  <hr style="margin: 30px 0;">

  <h3>📋 Recent Notifications</h3>
  <?php
  try {
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $recentNotifs = $stmt->get_result();
    $stmt->close();
    
    if ($recentNotifs && $recentNotifs->num_rows > 0):
    ?>
      <table class="table">
        <tr>
          <th>ID</th>
          <th>Type</th>
          <th>Title</th>
          <th>Message</th>
          <th>Read</th>
          <th>Created</th>
        </tr>
        <?php while ($n = $recentNotifs->fetch_assoc()): ?>
        <tr>
          <td><?= $n['id'] ?></td>
          <td><span style="background: #dbeafe; padding: 4px 8px; border-radius: 4px; font-size: 12px;"><?= htmlspecialchars($n['type']) ?></span></td>
          <td><strong><?= htmlspecialchars($n['title']) ?></strong></td>
          <td><?= htmlspecialchars(substr($n['message'], 0, 50)) ?>...</td>
          <td style="text-align: center;"><?= $n['is_read'] ? '✅' : '❌' ?></td>
          <td><?= date('M d, Y h:i A', strtotime($n['created_at'])) ?></td>
        </tr>
        <?php endwhile; ?>
      </table>
    <?php else: ?>
      <p style="text-align: center; padding: 40px; color: #6b7280;">
        <span style="font-size: 48px; display: block; margin-bottom: 10px;">🔭</span>
        No notifications yet. Click "Send Test Notification" above to create one.
      </p>
    <?php endif; ?>
  <?php
  } catch (Exception $e) {
    echo "<div class='error'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
  }
  ?>
</div>

</body>
</html>