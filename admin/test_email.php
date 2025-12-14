<?php
session_start();
require '../db_connect.php';
require '../includes/notification_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

$stmt = $conn->prepare("SELECT email, name FROM users WHERE id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$result = sendEmailNotification(
  $user['email'],
  'Test Email from Laundry System',
  "<p>Hello {$user['name']},</p>
   <p>This is a test email from your Laundry Management System.</p>
   <p>If you received this email, your email configuration is working correctly!</p>
   <p><strong>Test Details:</strong></p>
   <ul>
     <li>Sent at: " . date('F d, Y h:i A') . "</li>
     <li>Sent to: {$user['email']}</li>
     <li>System: Laundry Management System</li>
   </ul>"
);

if ($result) {
  echo json_encode(['success' => true, 'message' => 'Test email sent successfully']);
} else {
  echo json_encode(['success' => false, 'message' => 'Failed to send email. Check your SMTP settings.']);
}
?>