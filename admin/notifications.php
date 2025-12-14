<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header('Location: ../index.php');
  exit;
}

if (isset($_GET['mark_read'])) {
  $notif_id = intval($_GET['mark_read']);
  $conn->query("UPDATE notifications SET is_read = 1 WHERE id = $notif_id");
  header('Location: notifications.php');
  exit;
}

if (isset($_GET['mark_all_read'])) {
  $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = {$_SESSION['user_id']}");
  header('Location: notifications.php');
  exit;
}

if (isset($_GET['delete'])) {
  $notif_id = intval($_GET['delete']);
  $conn->query("DELETE FROM notifications WHERE id = $notif_id");
  header('Location: notifications.php');
  exit;
}

// Create table if not exists
$conn->query("
  CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) NULL,
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  )
");

$notifications = $conn->query("
  SELECT * FROM notifications 
  WHERE user_id = {$_SESSION['user_id']} OR user_id IS NULL
  ORDER BY created_at DESC
");

$unreadCount = $conn->query("
  SELECT COUNT(*) as count FROM notifications 
  WHERE (user_id = {$_SESSION['user_id']} OR user_id IS NULL) AND is_read = 0
")->fetch_assoc()['count'];

function getTimeAgo($timestamp) {
  $time = strtotime($timestamp);
  $diff = time() - $time;
  if ($diff < 60) return "Just now";
  if ($diff < 3600) return floor($diff / 60) . " mins ago";
  if ($diff < 86400) return floor($diff / 3600) . " hours ago";
  if ($diff < 604800) return floor($diff / 86400) . " days ago";
  return date('M d, Y', $time);
}

function getNotifIcon($type) {
  $icons = ['order' => '📦', 'payment' => '💰', 'system' => '⚙️', 'alert' => '⚠️', 'success' => '✅', 'info' => 'ℹ️'];
  return $icons[$type] ?? '🔔';
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Notifications</title>
<link rel="stylesheet" href="../css/admin-theme.css">
<style>
.notif-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
.notif-badge { background: #ef4444; color: white; border-radius: 20px; padding: 4px 12px; font-size: 12px; font-weight: 700; margin-left: 10px; }
.notif-list { display: flex; flex-direction: column; gap: 12px; }
.notif-item { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); display: flex; align-items: start; gap: 15px; transition: all 0.3s; border-left: 4px solid transparent; }
.notif-item:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.12); transform: translateX(5px); }
.notif-item.unread { background: #f0f9ff; border-left-color: #2563eb; }
.notif-icon { font-size: 32px; min-width: 40px; }
.notif-content { flex: 1; }
.notif-title { font-size: 16px; font-weight: 700; color: #1f2937; margin-bottom: 5px; }
.notif-message { font-size: 14px; color: #6b7280; line-height: 1.5; }
.notif-time { font-size: 12px; color: #9ca3af; margin-top: 8px; }
.notif-actions-item { display: flex; gap: 8px; flex-direction: column; }
.notif-actions-item a { padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 600; text-align: center; transition: all 0.3s; }
.btn-mark-read { background: #10b981; color: white; }
.btn-mark-read:hover { background: #059669; }
.btn-delete { background: #ef4444; color: white; }
.btn-delete:hover { background: #dc2626; }
.empty-state { text-align: center; padding: 60px 20px; color: #6b7280; }
.empty-state-icon { font-size: 64px; margin-bottom: 20px; }
</style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="card">
  <div class="notif-header">
    <div>
      <h2 style="margin: 0;">🔔 Notifications
        <?php if ($unreadCount > 0): ?>
          <span class="notif-badge"><?= $unreadCount ?></span>
        <?php endif; ?>
      </h2>
      <p style="color: #6b7280; margin: 5px 0 0 0;">Stay updated with your laundry business</p>
    </div>
    <div style="display: flex; gap: 10px;">
      <a href="?mark_all_read" style="background: #10b981; color: white; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-weight: 600;">✓ Mark All Read</a>
    </div>
  </div>

  <div class="notif-list">
    <?php if ($notifications->num_rows > 0): ?>
      <?php while ($notif = $notifications->fetch_assoc()): 
        $isUnread = $notif['is_read'] == 0;
        $timeAgo = getTimeAgo($notif['created_at']);
        $icon = getNotifIcon($notif['type']);
      ?>
      <div class="notif-item <?= $isUnread ? 'unread' : '' ?>">
        <div class="notif-icon"><?= $icon ?></div>
        <div class="notif-content">
          <div class="notif-title"><?= htmlspecialchars($notif['title']) ?></div>
          <div class="notif-message"><?= htmlspecialchars($notif['message']) ?></div>
          <div class="notif-time">⏱️ <?= $timeAgo ?></div>
        </div>
        <div class="notif-actions-item">
          <?php if ($isUnread): ?>
            <a href="?mark_read=<?= $notif['id'] ?>" class="btn-mark-read">Mark Read</a>
          <?php endif; ?>
          <?php if ($notif['link']): ?>
            <a href="<?= htmlspecialchars($notif['link']) ?>" style="background: #2563eb; color: white;">View</a>
          <?php endif; ?>
          <a href="?delete=<?= $notif['id'] ?>" class="btn-delete" onclick="return confirm('Delete?')">Delete</a>
        </div>
      </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="empty-state">
        <div class="empty-state-icon">📭</div>
        <h3 style="color: #374151; margin-bottom: 10px;">No Notifications</h3>
        <p>You're all caught up! New notifications will appear here.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

</body>
</html>