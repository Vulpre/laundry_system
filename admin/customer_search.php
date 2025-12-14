<?php
session_start();
require '../db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

$search = $_GET['q'] ?? '';
$search = $conn->real_escape_string($search);

$customers = $conn->query("
  SELECT id, name, email, created_at,
  (SELECT COUNT(*) FROM orders WHERE user_id = users.id) as order_count
  FROM users 
  WHERE role = 'user' 
  AND (name LIKE '%$search%' OR email LIKE '%$search%')
  ORDER BY name ASC
  LIMIT 10
");

$results = [];
while ($customer = $customers->fetch_assoc()) {
  $results[] = $customer;
}

echo json_encode($results);
?>