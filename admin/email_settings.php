<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header('Location: ../index.php');
  exit;
}

$success = '';
$error = '';

// Create email settings table if not exists
$conn->query("
  CREATE TABLE IF NOT EXISTS email_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  )
");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $settings = [
    'email_enabled' => $_POST['email_enabled'] ?? 'no',
    'smtp_host' => $_POST['smtp_host'] ?? '',
    'smtp_port' => $_POST['smtp_port'] ?? '',
    'smtp_username' => $_POST['smtp_username'] ?? '',
    'smtp_password' => $_POST['smtp_password'] ?? '',
    'from_email' => $_POST['from_email'] ?? '',
    'from_name' => $_POST['from_name'] ?? '',
    'notify_new_order' => $_POST['notify_new_order'] ?? 'no',
    'notify_status_change' => $_POST['notify_status_change'] ?? 'no',
    'notify_payment' => $_POST['notify_payment'] ?? 'no',
    'daily_summary' => $_POST['daily_summary'] ?? 'no'
  ];

  foreach ($settings as $key => $value) {
    $stmt = $conn->prepare("
      INSERT INTO email_settings (setting_key, setting_value) 
      VALUES (?, ?) 
      ON DUPLICATE KEY UPDATE setting_value = ?
    ");
    $stmt->bind_param('sss', $key, $value, $value);
    $stmt->execute();
  }

  $success = "Email settings saved successfully!";
}

// Fetch current settings
$currentSettings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM email_settings");
while ($row = $result->fetch_assoc()) {
  $currentSettings[$row['setting_key']] = $row['setting_value'];
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Email Settings</title>
<link rel="stylesheet" href="../css/admin-theme.css">
<style>
.settings-container { max-width: 800px; margin: 0 auto; }
.settings-section { background: white; padding: 25px; border-radius: 12px; margin: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
.settings-section h3 { color: #1f2937; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e5e7eb; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; font-weight: 600; color: #374151; margin-bottom: 8px; }
.form-group input[type="text"], .form-group input[type="email"], .form-group input[type="password"], .form-group input[type="number"] { width: 100%; padding: 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; }
.form-group input:focus { border-color: #2563eb; outline: none; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
.checkbox-group { display: flex; align-items: center; gap: 10px; }
.checkbox-group input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; }
.checkbox-group label { margin: 0; font-weight: 500; cursor: pointer; }
.btn-save { background: #10b981; color: white; padding: 14px 28px; border: none; border-radius: 8px; font-weight: 600; font-size: 16px; cursor: pointer; width: 100%; }
.btn-save:hover { background: #059669; }
.btn-test { background: #f59e0b; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 10px; }
.btn-test:hover { background: #d97706; }
.info-box { background: #f0f9ff; border-left: 4px solid #2563eb; padding: 15px; border-radius: 8px; margin: 15px 0; }
.info-box p { margin: 5px 0; color: #1e40af; font-size: 14px; }
</style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="card">
  <div class="settings-container">
    <h2>⚙️ Email & Notification Settings</h2>
    <p style="color: #6b7280; margin-bottom: 20px;">Configure email notifications and SMTP settings for automated communications.</p>

    <?php if ($success): ?>
      <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <!-- Email Enable/Disable -->
      <div class="settings-section">
        <h3>📧 Email Notifications</h3>
        <div class="checkbox-group">
          <input type="checkbox" id="email_enabled" name="email_enabled" value="yes" 
            <?= ($currentSettings['email_enabled'] ?? 'no') === 'yes' ? 'checked' : '' ?>>
          <label for="email_enabled">Enable Email Notifications</label>
        </div>
        <div class="info-box">
          <p>⚠️ Email notifications require proper SMTP configuration below.</p>
          <p>💡 For testing, leave disabled and check error logs.</p>
        </div>
      </div>

      <!-- SMTP Settings -->
      <div class="settings-section">
        <h3>🔧 SMTP Configuration</h3>
        
        <div class="form-group">
          <label>SMTP Host</label>
          <input type="text" name="smtp_host" value="<?= htmlspecialchars($currentSettings['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
        </div>

        <div class="form-group">
          <label>SMTP Port</label>
          <input type="number" name="smtp_port" value="<?= htmlspecialchars($currentSettings['smtp_port'] ?? '587') ?>" placeholder="587">
        </div>

        <div class="form-group">
          <label>SMTP Username</label>
          <input type="text" name="smtp_username" value="<?= htmlspecialchars($currentSettings['smtp_username'] ?? '') ?>" placeholder="your-email@gmail.com">
        </div>

        <div class="form-group">
          <label>SMTP Password</label>
          <input type="password" name="smtp_password" value="<?= htmlspecialchars($currentSettings['smtp_password'] ?? '') ?>" placeholder="Your SMTP password or app password">
        </div>

        <div class="form-group">
          <label>From Email Address</label>
          <input type="email" name="from_email" value="<?= htmlspecialchars($currentSettings['from_email'] ?? '') ?>" placeholder="noreply@laundrysystem.com">
        </div>

        <div class="form-group">
          <label>From Name</label>
          <input type="text" name="from_name" value="<?= htmlspecialchars($currentSettings['from_name'] ?? 'Laundry Management System') ?>" placeholder="Laundry Management System">
        </div>

        <button type="button" class="btn-test" onclick="testEmail()">📤 Send Test Email</button>
      </div>

      <!-- Notification Preferences -->
      <div class="settings-section">
        <h3>🔔 Notification Preferences</h3>
        
        <div class="checkbox-group" style="margin-bottom: 15px;">
          <input type="checkbox" id="notify_new_order" name="notify_new_order" value="yes"
            <?= ($currentSettings['notify_new_order'] ?? 'yes') === 'yes' ? 'checked' : '' ?>>
          <label for="notify_new_order">Notify admins when new orders are created</label>
        </div>

        <div class="checkbox-group" style="margin-bottom: 15px;">
          <input type="checkbox" id="notify_status_change" name="notify_status_change" value="yes"
            <?= ($currentSettings['notify_status_change'] ?? 'yes') === 'yes' ? 'checked' : '' ?>>
          <label for="notify_status_change">Notify customers when order status changes</label>
        </div>

        <div class="checkbox-group" style="margin-bottom: 15px;">
          <input type="checkbox" id="notify_payment" name="notify_payment" value="yes"
            <?= ($currentSettings['notify_payment'] ?? 'yes') === 'yes' ? 'checked' : '' ?>>
          <label for="notify_payment">Notify customers when payment is received</label>
        </div>

        <div class="checkbox-group">
          <input type="checkbox" id="daily_summary" name="daily_summary" value="yes"
            <?= ($currentSettings['daily_summary'] ?? 'no') === 'yes' ? 'checked' : '' ?>>
          <label for="daily_summary">Send daily summary report to admins</label>
        </div>
      </div>

      <button type="submit" class="btn-save">💾 Save Settings</button>
    </form>
  </div>
</div>

<script>
function testEmail() {
  if (confirm('Send a test email to your admin email address?')) {
    fetch('test_email.php')
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('✅ Test email sent successfully! Check your inbox.');
        } else {
          alert('❌ Failed to send test email: ' + data.message);
        }
      })
      .catch(error => {
        alert('❌ Error: ' + error.message);
      });
  }
}
</script>

</body>
</html>