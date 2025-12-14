<?php
/**
 * Login Page & Authentication Handler
 * 
 * Handles user authentication with security measures:
 * - Prepared statements for SQL injection prevention
 * - Timing-safe password comparison
 * - Input validation and sanitization
 * - Security logging
 * 
 * @package LaundryManagementSystem
 */

session_start();
require 'db_connect.php';

// ============================================================================
// CHECK IF USER ALREADY LOGGED IN
// ============================================================================

if (isset($_SESSION['user_id'])) {
  // Redirect based on role
  if ($_SESSION['role'] === 'admin') {
    header('Location: admin/dashboard.php', true, 302);
  } else {
    header('Location: user/home.php', true, 302);
  }
  exit;
}

// ============================================================================
// INITIALIZE VARIABLES
// ============================================================================

$err = '';
$success = '';
$email = '';

// ============================================================================
// CHECK FOR MESSAGES
// ============================================================================

// Check for logout message
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
  $success = '✅ You have been logged out successfully. See you next time!';
}

// Check for session expired message
if (isset($_GET['session']) && $_GET['session'] === 'expired') {
  $err = '⏱️ Your session has expired due to inactivity. Please log in again.';
}

// Check for security error
if (isset($_GET['error']) && $_GET['error'] === 'security') {
  $err = '🔒 Security check failed. Please log in again.';
}

// ============================================================================
// HANDLE LOGIN FORM SUBMISSION
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Sanitize and validate inputs
  $email = sanitizeText($_POST['email'] ?? '', 254);
  $password = $_POST['password'] ?? '';
  
  // ========================================================================
  // INPUT VALIDATION
  // ========================================================================
  
  // Check if fields are empty
  if (empty($email) || empty($password)) {
    $err = '❌ Please enter both email and password.';
  } 
  // Validate email format
  elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err = '❌ Invalid email format. Please check and try again.';
  } 
  // Validate password is not too long (prevent DoS)
  elseif (strlen($password) > 255) {
    $err = '❌ Invalid password format.';
    error_log("Suspicious login attempt - Password too long, IP: {$_SERVER['REMOTE_ADDR']}");
  } 
  else {
    // ====================================================================
    // AUTHENTICATE USER WITH PREPARED STATEMENT
    // ====================================================================
    
    try {
      // Prepare statement to prevent SQL injection
      $stmt = $conn->prepare('SELECT id, name, password, role FROM users WHERE email = ?');
      
      if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
      }
      
      // Bind email parameter
      $stmt->bind_param('s', $email);
      
      // Execute query
      if (!$stmt->execute()) {
        throw new Exception("Query failed: " . $stmt->error);
      }
      
      // Get result
      $result = $stmt->get_result();
      
      if ($result->num_rows === 1) {
        // User found - fetch data
        $row = $result->fetch_assoc();
        
        // ================================================================
        // VERIFY PASSWORD (TIMING-SAFE COMPARISON)
        // ================================================================
        
        $passwordHash = hash('sha256', $password);
        
        if (hash_equals($row['password'], $passwordHash)) {
          // ============================================================
          // LOGIN SUCCESSFUL - SET SESSION VARIABLES
          // ============================================================
          
          $_SESSION['user_id'] = (int) $row['id'];
          $_SESSION['name'] = $row['name'];
          $_SESSION['role'] = $row['role'];
          $_SESSION['login_time'] = time();
          $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
          
          // Log successful login
          error_log("✅ User login successful - ID: {$row['id']}, Name: {$row['name']}, Role: {$row['role']}, IP: {$_SERVER['REMOTE_ADDR']}");
          
          // ============================================================
          // REDIRECT BASED ON ROLE
          // ============================================================
          
          if ($row['role'] === 'admin') {
            header('Location: admin/dashboard.php', true, 302);
          } else {
            header('Location: user/home.php', true, 302);
          }
          exit;
        } else {
          // Password does not match
          $err = '❌ Invalid email or password.';
          error_log("⚠️  Failed login attempt - Wrong password for email: {$email}, IP: {$_SERVER['REMOTE_ADDR']}");
        }
      } else {
        // User not found
        $err = '❌ Invalid email or password.';
        error_log("⚠️  Failed login attempt - Email not found: {$email}, IP: {$_SERVER['REMOTE_ADDR']}");
      }
      
      // Close statement
      $stmt->close();
      
    } catch (Exception $e) {
      error_log("❌ Login error: " . $e->getMessage());
      $err = '❌ An error occurred. Please try again later.';
    }
  }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Login to your laundry management system account">
  <meta name="theme-color" content="#2563eb">
  <title>Login - Laundry Management System</title>
  <link rel="stylesheet" href="css/auth.css">
</head>
<body>
  <div class="card">
    <h2>Welcome Back 👋</h2>
    <p class="sub">Login to manage or track your laundry orders</p>

    <!-- Error Message -->
    <?php if (!empty($err)): ?>
      <div class="error"><?= $err ?></div>
    <?php endif; ?>

    <!-- Success Message -->
    <?php if (!empty($success)): ?>
      <div class="success"><?= $success ?></div>
    <?php endif; ?>

    <!-- Login Form -->
    <form method="post" autocomplete="off">
      <!-- Email Input -->
      <label for="email">Email Address</label>
      <input 
        type="email" 
        id="email"
        name="email" 
        placeholder="your@email.com" 
        value="<?= esc($email) ?>"
        required 
        autocomplete="email"
        maxlength="254"
        autofocus
      >

      <!-- Password Input -->
      <label for="password">Password</label>
      <input 
        type="password" 
        id="password"
        name="password" 
        placeholder="Enter your password" 
        required 
        autocomplete="current-password"
      >

      <!-- Submit Button -->
      <button type="submit">Login</button>
    </form>

    <!-- Register Link -->
    <p>Don't have an account? <a href="register.php">Create one</a></p>
  </div>

  <script>
    // Add basic client-side validation for UX
    document.querySelector('form').addEventListener('submit', function(e) {
      const email = document.getElementById('email').value.trim();
      const password = document.getElementById('password').value;

      if (!email || !password) {
        e.preventDefault();
        alert('Please fill in all fields');
        return false;
      }

      if (password.length < 1) {
        e.preventDefault();
        alert('Password cannot be empty');
        return false;
      }
    });
  </script>
</body>
</html>