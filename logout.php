<?php
/**
 * Logout & Session Destruction
 * 
 * Securely logs out user, destroys session, and clears cookies
 * 
 * @package LaundryManagementSystem
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// ============================================================================
// LOG LOGOUT ACTIVITY
// ============================================================================

// Log the logout action with user info
if (isset($_SESSION['user_id'])) {
  $userId = $_SESSION['user_id'];
  $userName = $_SESSION['name'] ?? 'Unknown';
  $userRole = $_SESSION['role'] ?? 'unknown';
  $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP';
  
  error_log("User logged out - ID: $userId, Name: $userName, Role: $userRole, IP: $ipAddress");
}

// ============================================================================
// CLEAR SESSION DATA
// ============================================================================

// Unset all session variables
$_SESSION = [];

// ============================================================================
// DESTROY SESSION COOKIE
// ============================================================================

// Delete the session cookie
if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  
  setcookie(
    session_name(),           // Cookie name
    '',                       // Empty value
    time() - 42000,           // Expire in the past
    $params["path"],          // Path
    $params["domain"],        // Domain
    $params["secure"],        // Secure flag
    $params["httponly"]       // HTTPOnly flag
  );
}

// ============================================================================
// DESTROY SESSION
// ============================================================================

session_destroy();

error_log("✅ Session destroyed successfully");

// ============================================================================
// REDIRECT TO LOGIN
// ============================================================================

// Redirect to login page with success message
header("Location: index.php?logout=success", true, 302);
exit;

?>