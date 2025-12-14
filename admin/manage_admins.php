<?php
session_start();
require '../db_connect.php';

// ✅ Only admins can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$current_admin = $_SESSION['user_id'];
$err = '';
$success = '';

// ✅ Handle deletion
if (isset($_POST['delete_admin'])) {
    $aid = intval($_POST['admin_id']);
    if ($aid !== $current_admin) { // prevent self-delete
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'");
        $stmt->bind_param('i', $aid);
        if ($stmt->execute()) $success = "✅ Admin deleted successfully.";
        else $err = "⚠️ Failed to delete admin.";
    } else {
        $err = "You cannot delete your own account.";
    }
}

// ✅ Handle new admin creation
if (isset($_POST['create_admin'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if ($name && $email && $password) {
        $hash = hash('sha256', $password);
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
        $stmt->bind_param('sss', $name, $email, $hash);
        if ($stmt->execute()) $success = "✅ New admin created successfully.";
        else $err = "⚠️ Failed to create admin (email may already exist).";
    } else {
        $err = "Please fill in all fields.";
    }
}

// ✅ Fetch all admin accounts (excluding current admin)
$admins = $conn->query("
    SELECT id, name, email, created_at 
    FROM users 
    WHERE role = 'admin' AND id != $current_admin 
    ORDER BY created_at DESC
");
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Manage Admins</title>
<link rel="stylesheet" href="../css/admin-theme.css">
<style>
.card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 8px 20px rgba(0,0,0,0.1);
  padding: 25px;
  max-width: 1000px;
  margin: 40px auto;
}

button {
  border: none;
  border-radius: 6px;
  padding: 8px 14px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  transition: 0.3s;
}

button:hover { transform: translateY(-2px); }

button.delete {
  background: #ef4444;
  color: white;
}

button.delete:hover {
  background: #b91c1c;
}

button.add {
  background: #2563eb;
  color: white;
  float: right;
  margin-bottom: 15px;
}

button.add:hover {
  background: #1e40af;
}

.table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 20px;
  background: white;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

.table th {
  background: linear-gradient(135deg, #2563eb, #1e40af);
  color: white;
  padding: 14px;
  text-align: center;
}

.table td {
  text-align: center;
  padding: 12px;
  border-bottom: 1px solid #e5e7eb;
  color: #374151;
}

/* ✅ Modal */
.modal {
  display: none;
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(0,0,0,0.5);
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 12px;
  padding: 25px;
  width: 400px;
  box-shadow: 0 10px 20px rgba(0,0,0,0.3);
  animation: fadeIn 0.3s ease;
}

.modal-content h3 {
  margin-bottom: 20px;
  text-align: center;
  color: #1e40af;
}

.modal-content input {
  width: 100%;
  padding: 10px;
  border-radius: 8px;
  border: 2px solid #e5e7eb;
  margin-bottom: 12px;
}

.modal-content input:focus {
  border-color: #2563eb;
  outline: none;
  box-shadow: 0 0 0 2px rgba(37,99,235,0.2);
}

.modal-content button {
  width: 100%;
  background: #2563eb;
  color: white;
  padding: 10px;
}

.modal-content button:hover {
  background: #1e40af;
}

.success {
  background: #d1fae5;
  color: #065f46;
  border-left: 4px solid #10b981;
  padding: 10px;
  border-radius: 6px;
  margin-bottom: 10px;
}

.error {
  background: #fee2e2;
  color: #991b1b;
  border-left: 4px solid #ef4444;
  padding: 10px;
  border-radius: 6px;
  margin-bottom: 10px;
}

@keyframes fadeIn {
  from {opacity:0;transform:scale(0.95);}
  to {opacity:1;transform:scale(1);}
}
</style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<div class="card">
  <h2>🧑‍💼 Manage Admin Accounts</h2>

  <?php if($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if($err): ?><div class="error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <button class="add" id="openModal">➕ Create New Admin</button>

  <table class="table">
    <tr>
      <th>ID</th>
      <th>Name</th>
      <th>Email</th>
      <th>Created At</th>
      <th>Action</th>
    </tr>
    <?php if ($admins->num_rows > 0): ?>
      <?php while ($a = $admins->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($a['id']) ?></td>
          <td><?= htmlspecialchars($a['name']) ?></td>
          <td><?= htmlspecialchars($a['email']) ?></td>
          <td><?= htmlspecialchars($a['created_at']) ?></td>
          <td>
            <form method="post" onsubmit="return confirm('Delete this admin?');">
              <input type="hidden" name="admin_id" value="<?= $a['id'] ?>">
              <button type="submit" name="delete_admin" class="delete">🗑️ Delete</button>
            </form>
          </td>
        </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr><td colspan="5">No other admin accounts found.</td></tr>
    <?php endif; ?>
  </table>
</div>

<!-- ✅ Modal for creating admin -->
<div class="modal" id="adminModal">
  <div class="modal-content">
    <h3>➕ Create New Admin</h3>
    <form method="post">
      <input type="text" name="name" placeholder="Full Name" required>
      <input type="email" name="email" placeholder="Email Address" required>
      <input type="password" name="password" placeholder="Password" required>
      <button type="submit" name="create_admin">Create Admin</button>
    </form>
  </div>
</div>

<script>
const modal = document.getElementById('adminModal');
document.getElementById('openModal').addEventListener('click', ()=> modal.style.display = 'flex');
modal.addEventListener('click', e => { if(e.target === modal) modal.style.display = 'none'; });
</script>
</body>
</html>
