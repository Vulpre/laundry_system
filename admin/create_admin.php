<?php
session_start();
require '../db_connect.php';

// Restrict access to admins only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$err = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';

    // Validation
    if (!$name || !$email || !$pass) {
        $err = 'Please fill all fields.';
    } elseif (strlen($name) < 2 || strlen($name) > 100) {
        $err = 'Name must be between 2 and 100 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Invalid email address.';
    } elseif (strlen($pass) < 8) {
        $err = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $pass)) {
        $err = 'Password must include at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $pass)) {
        $err = 'Password must include at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $pass)) {
        $err = 'Password must include at least one number.';
    } else {
        $hash = hash('sha256', $pass);

        // Check for existing email
        $check = $conn->prepare('SELECT id FROM users WHERE email=?');
        $check->bind_param('s', $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $err = 'That email is already registered.';
        } else {
            $stmt = $conn->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, "admin")');
            $stmt->bind_param('sss', $name, $email, $hash);

            if ($stmt->execute()) {
                $success = "✅ Admin account created successfully!";
            } else {
                $err = "Failed to create admin: " . $stmt->error;
            }

            $stmt->close();
        }

        $check->close();
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Create Admin Account</title>
<link rel="stylesheet" href="../css/admin-theme.css">
<style>
.card {
  max-width: 600px;
  margin: 40px auto;
}

button {
  background: var(--primary);
}

button:hover {
  background: var(--primary-dark);
}

.success, .error {
  margin-top: 15px;
}
</style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="card">
  <h2>👤 Create New Admin Account</h2>
  <p>Add another administrator to manage the laundry system.</p>

  <?php if($err): ?><div class="error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <?php if($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <form method="post">
    <label>Full Name</label>
    <input type="text" name="name" placeholder="Enter full name" required autocomplete="off">

    <label>Email Address</label>
    <input type="email" name="email" placeholder="Enter admin email" required autocomplete="off">

    <label>Password</label>
    <input type="password" name="password" placeholder="Create a strong password" required autocomplete="new-password">
    <small class="small">Password must contain at least 8 characters with uppercase, lowercase, and numbers.</small>

    <button type="submit">💾 Create Admin</button>
  </form>
</div>
</body>
</html>
