<?php
/**
 * User Registration Page
 * 
 * Handles new user account creation with:
 * - Comprehensive input validation
 * - Password strength requirements
 * - Duplicate email prevention
 * - Prepared statements for security
 * - Security logging
 * 
 * @package LaundryManagementSystem
 */

session_start();
require 'db_connect.php';

// ============================================================================
// INITIALIZE VARIABLES
// ============================================================================

$err = '';
$name = '';
$email = '';

// ============================================================================
// HANDLE REGISTRATION FORM SUBMISSION
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Sanitize inputs
  $name = sanitizeText($_POST['name'] ?? '', 100);
  $email = sanitizeText($_POST['email'] ?? '', 254);
  $password = $_POST['password'] ?? '';
  
  // ========================================================================
  // VALIDATE INPUTS
  // ========================================================================
  
  // Check if fields are empty
  if (empty($name) || empty($email) || empty($password)) {
    $err = '❌ Please fill in all fields.';
  }
  // Validate name length
  elseif (strlen($name) < 2) {
    $err = '❌ Name must be at least 2 characters long.';
  }
  elseif (strlen($name) > 100) {
    $err = '❌ Name cannot exceed 100 characters.';
  }
  // Validate email format
  elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err = '❌ Please enter a valid email address.';
  }
  // Validate password length
  elseif (strlen($password) < 8) {
    $err = '❌ Password must be at least 8 characters long.';
  }
  // Validate password has uppercase letter
  elseif (!preg_match('/[A-Z]/', $password)) {
    $err = '❌ Password must contain at least one uppercase letter (A-Z).';
  }
  // Validate password has lowercase letter
  elseif (!preg_match('/[a-z]/', $password)) {
    $err = '❌ Password must contain at least one lowercase letter (a-z).';
  }
  // Validate password has number
  elseif (!preg_match('/[0-9]/', $password)) {
    $err = '❌ Password must contain at least one number (0-9).';
  }
  // Validate password is not too long (prevent DoS)
  elseif (strlen($password) > 255) {
    $err = '❌ Password is too long.';
  }
  else {
    // ====================================================================
    // HASH PASSWORD
    // ====================================================================
    
    $passwordHash = hash('sha256', $password);
    
    // ====================================================================
    // CHECK FOR DUPLICATE EMAIL
    // ====================================================================
    
    try {
      // Prepare statement to check existing email
      $checkStmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
      
      if (!$checkStmt) {
        throw new Exception("Database error: " . $conn->error);
      }
      
      // Bind email parameter
      $checkStmt->bind_param('s', $email);
      
      // Execute query
      if (!$checkStmt->execute()) {
        throw new Exception("Query failed: " . $checkStmt->error);
      }
      
      // Check results
      $checkStmt->store_result();
      
      if ($checkStmt->num_rows > 0) {
        // Email already registered
        $err = '❌ This email is already registered. Please <a href="index.php" style="color: #ef4444; text-decoration: underline;">login</a> instead.';
        error_log("⚠️  Registration attempt with existing email: {$email}, IP: {$_SERVER['REMOTE_ADDR']}");
      } else {
        // ================================================================
        // EMAIL IS AVAILABLE - INSERT NEW USER
        // ================================================================
        
        $checkStmt->close();
        
        // Prepare statement to insert user
        $insertStmt = $conn->prepare('
          INSERT INTO users (name, email, password, role, created_at) 
          VALUES (?, ?, ?, ?, NOW())
        ');
        
        if (!$insertStmt) {
          throw new Exception("Database error: " . $conn->error);
        }
        
        // Set default role for new registrations
        $role = 'user';
        
        // Bind parameters
        $insertStmt->bind_param('ssss', $name, $email, $passwordHash, $role);
        
        // Execute insert
        if (!$insertStmt->execute()) {
          throw new Exception("Insert failed: " . $insertStmt->error);
        }
        
        // Get new user ID
        $newUserId = $insertStmt->insert_id;
        
        // Log successful registration
        error_log("✅ New user registered - ID: {$newUserId}, Name: {$name}, Email: {$email}, IP: {$_SERVER['REMOTE_ADDR']}");
        
        // Set success message
        $_SESSION['success'] = '✅ Registration successful! Please log in with your email and password.';
        
        // Close statement
        $insertStmt->close();
        
        // Redirect to login
        header('Location: index.php', true, 302);
        exit;
      }
      
      $checkStmt->close();
      
    } catch (Exception $e) {
      error_log("❌ Registration error: " . $e->getMessage());
      $err = '❌ An error occurred during registration. Please try again later.';
    }
  }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Create a new laundry management system account">
  <meta name="theme-color" content="#2563eb">
  <title>Register - Laundry Management System</title>
  <link rel="stylesheet" href="css/auth.css">
</head>
<body>
  <div class="card">
    <h2>Create Account ✨</h2>
    <p class="sub">Join our laundry service to track your orders easily</p>

    <!-- Error Message -->
    <?php if (!empty($err)): ?>
      <div class="error"><?= $err ?></div>
    <?php endif; ?>

    <!-- Registration Form -->
    <form method="post" autocomplete="off" id="registerForm">
      <!-- Name Input -->
      <label for="name">Full Name</label>
      <input 
        type="text" 
        id="name"
        name="name" 
        placeholder="John Doe" 
        value="<?= esc($name) ?>"
        required 
        autocomplete="name"
        minlength="2"
        maxlength="100"
        autofocus
      >

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
      >

      <!-- Password Input -->
      <label for="password">Password</label>
      <input 
        type="password" 
        id="password"
        name="password" 
        placeholder="Create a strong password" 
        required 
        autocomplete="new-password"
        minlength="8"
      >

      <!-- Password Requirements -->
      <small style="color: #666; display: block; margin-top: 8px;">
        🔐 Requirements: At least 8 characters, with uppercase (A-Z), lowercase (a-z), and numbers (0-9)
      </small>

      <!-- Password Strength Indicator -->
      <div id="passwordStrength" style="margin-top: 10px; display: none;">
        <div style="font-size: 12px; font-weight: 600; margin-bottom: 5px;">Password Strength:</div>
        <div style="background: #e5e7eb; height: 4px; border-radius: 2px; overflow: hidden;">
          <div id="strengthBar" style="height: 100%; width: 0%; background: #ef4444; transition: width 0.3s, background 0.3s;"></div>
        </div>
      </div>

      <!-- Submit Button -->
      <button type="submit">Create Account</button>
    </form>

    <!-- Login Link -->
    <p>Already have an account? <a href="index.php">Log In</a></p>
  </div>

  <script>
    // Password strength indicator
    const passwordInput = document.getElementById('password');
    const strengthBar = document.getElementById('strengthBar');
    const passwordStrength = document.getElementById('passwordStrength');

    passwordInput.addEventListener('input', function() {
      const password = this.value;
      let strength = 0;

      // Check length
      if (password.length >= 8) strength += 20;
      if (password.length >= 12) strength += 10;
      if (password.length >= 16) strength += 10;

      // Check for uppercase
      if (/[A-Z]/.test(password)) strength += 20;

      // Check for lowercase
      if (/[a-z]/.test(password)) strength += 20;

      // Check for numbers
      if (/[0-9]/.test(password)) strength += 20;

      // Show/hide strength indicator
      if (password.length > 0) {
        passwordStrength.style.display = 'block';
        strengthBar.style.width = strength + '%';

        if (strength < 40) {
          strengthBar.style.background = '#ef4444';
        } else if (strength < 70) {
          strengthBar.style.background = '#f59e0b';
        } else {
          strengthBar.style.background = '#10b981';
        }
      } else {
        passwordStrength.style.display = 'none';
      }
    });

    // Form validation
    document.getElementById('registerForm').addEventListener('submit', function(e) {
      const name = document.getElementById('name').value.trim();
      const email = document.getElementById('email').value.trim();
      const password = document.getElementById('password').value;

      if (!name || !email || !password) {
        e.preventDefault();
        alert('❌ Please fill in all fields');
        return false;
      }

      if (name.length < 2) {
        e.preventDefault();
        alert('❌ Name must be at least 2 characters');
        return false;
      }

      if (password.length < 8) {
        e.preventDefault();
        alert('❌ Password must be at least 8 characters');
        return false;
      }

      if (!/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password)) {
        e.preventDefault();
        alert('❌ Password must contain uppercase, lowercase, and numbers');
        return false;
      }
    });
  </script>
</body>
</html>