<?php
/**
 * Security Module
 * 
 * Comprehensive security handling including:
 * - Session management & timeout
 * - CSRF token generation & validation
 * - Input validation & sanitization
 * - Rate limiting
 * - Security headers
 * 
 * @package LaundryManagementSystem
 */

// Prevent direct access to this file
if (php_sapi_name() !== 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? '')) {
  header('HTTP/1.0 403 Forbidden');
  exit('Direct access not allowed');
}

// Load constants if not already loaded
if (!defined('SESSION_TIMEOUT')) {
  require_once __DIR__ . '/constants.php';
}

// ============================================================================
// SECURITY HEADERS
// ============================================================================

/**
 * Set security headers to prevent common attacks
 */
if (php_sapi_name() !== 'cli') {
  // Prevent clickjacking attacks
  header("X-Frame-Options: SAMEORIGIN");
  
  // Prevent MIME type sniffing
  header("X-Content-Type-Options: nosniff");
  
  // Enable XSS protection
  header("X-XSS-Protection: 1; mode=block");
  
  // Referrer policy
  header("Referrer-Policy: strict-origin-when-cross-origin");
  
  // Content Security Policy
  header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:;");
  
  // Permissions policy
  header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}

// ============================================================================
// SESSION CONFIGURATION
// ============================================================================

/**
 * Initialize secure session
 */
if (session_status() === PHP_SESSION_NONE) {
  // Secure session cookie settings
  ini_set('session.cookie_httponly', 1);
  ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
  ini_set('session.use_strict_mode', 1);
  ini_set('session.cookie_samesite', 'Strict');
  ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
  ini_set('session.use_only_cookies', 1);
  ini_set('session.name', 'LAUNDRY_SESSION');
  
  session_start();
}

// ============================================================================
// SESSION TIMEOUT & HIJACKING PREVENTION
// ============================================================================

/**
 * Check and enforce session timeout
 * Prevent session fixation and hijacking
 */
if (isset($_SESSION['user_id'])) {
  // ======================================================================
  // SESSION TIMEOUT CHECK
  // ======================================================================
  
  if (isset($_SESSION['last_activity'])) {
    $inactivitySeconds = time() - $_SESSION['last_activity'];
    
    if ($inactivitySeconds > SESSION_TIMEOUT) {
      // Session has expired
      error_log("⏱️  Session timeout - User ID: {$_SESSION['user_id']}, Inactive for: {$inactivitySeconds}s");
      
      // Destroy session
      session_unset();
      session_destroy();
      
      // Redirect to login with timeout message
      header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/laundry_system') . '/index.php?session=expired', true, 302);
      exit;
    }
  }
  
  // Update last activity timestamp
  $_SESSION['last_activity'] = time();
  
  // ======================================================================
  // SESSION HIJACKING PREVENTION
  // ======================================================================
  
  // Check user agent (hash it for comparison)
  $userAgentHash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
  
  if (!isset($_SESSION['user_agent_hash'])) {
    $_SESSION['user_agent_hash'] = $userAgentHash;
  } elseif ($_SESSION['user_agent_hash'] !== $userAgentHash) {
    // User agent changed - potential session hijacking
    error_log("⚠️  SECURITY ALERT - Session hijacking detected!");
    error_log("User ID: {$_SESSION['user_id']}, Expected UA: {$_SESSION['user_agent_hash']}, Got: {$userAgentHash}");
    
    // Destroy session
    session_unset();
    session_destroy();
    
    // Redirect to login with error
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/laundry_system') . '/index.php?error=security', true, 302);
    exit;
  }
  
  // ======================================================================
  // IP ADDRESS CHECK (Optional but recommended)
  // ======================================================================
  
  $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
  
  if (!isset($_SESSION['client_ip'])) {
    $_SESSION['client_ip'] = $clientIp;
  } elseif ($_SESSION['client_ip'] !== $clientIp && !empty($clientIp)) {
    // IP changed - might be suspicious (but can happen with proxies)
    // Only log warning, don't destroy session automatically
    error_log("⚠️  Client IP changed - User ID: {$_SESSION['user_id']}, Old IP: {$_SESSION['client_ip']}, New IP: {$clientIp}");
  }
}

// ============================================================================
// CSRF TOKEN MANAGEMENT
// ============================================================================

/**
 * Initialize CSRF token for the session
 * Should be called once per session
 */
function initializeCsrfToken() {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    return $_SESSION['csrf_token'];
  }
  return $_SESSION['csrf_token'];
}

/**
 * Get CSRF token for use in forms
 * 
 * @return string CSRF token
 */
function getCsrfToken() {
  if (empty($_SESSION['csrf_token'])) {
    return initializeCsrfToken();
  }
  return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token from POST request
 * Uses timing-safe comparison to prevent timing attacks
 * 
 * @param string|null $token Token to validate (if null, gets from POST)
 * @return bool True if valid, false otherwise
 */
function validateCsrfToken($token = null) {
  // Get token from parameter or POST data
  $token = $token ?? ($_POST['csrf_token'] ?? '');
  
  // Check if both tokens exist
  if (empty($token) || empty($_SESSION['csrf_token'])) {
    error_log("⚠️  CSRF validation failed - Missing token");
    return false;
  }
  
  // Use timing-safe comparison to prevent timing attacks
  if (!hash_equals($_SESSION['csrf_token'], $token)) {
    error_log("⚠️  CSRF validation failed - Token mismatch, IP: {$_SERVER['REMOTE_ADDR']}");
    return false;
  }
  
  return true;
}

/**
 * Verify CSRF token or exit with error
 * Convenience function for simple validation
 * 
 * @param string|null $token Token to validate
 */
function verifyCsrfToken($token = null) {
  if (!validateCsrfToken($token)) {
    http_response_code(403);
    error_log("❌ CSRF token verification failed, IP: {$_SERVER['REMOTE_ADDR']}");
    die('Security token validation failed. Please refresh and try again.');
  }
}

/**
 * Regenerate CSRF token
 * Should be called after sensitive operations (login, logout, password change)
 * 
 * @return string New CSRF token
 */
function regenerateCsrfToken() {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
  return $_SESSION['csrf_token'];
}

// ============================================================================
// INPUT VALIDATION & SANITIZATION
// ============================================================================

/**
 * Sanitize text input
 * 
 * @param string $text Text to sanitize
 * @param int $maxLength Maximum allowed length
 * @return string Sanitized text
 */
function sanitizeText($text, $maxLength = 255) {
  if (!is_string($text)) {
    return '';
  }
  
  $text = trim($text);
  $text = substr($text, 0, $maxLength);
  return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Sanitize email address
 * 
 * @param string $email Email to sanitize
 * @return string|null Valid email or null if invalid
 */
function sanitizeEmail($email) {
  $email = trim($email);
  $email = strtolower($email);
  
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return null;
  }
  
  if (strlen($email) > 254) {
    return null;
  }
  
  return $email;
}

/**
 * Validate and sanitize price
 * 
 * @param mixed $price Price to validate
 * @param float $min Minimum allowed price
 * @param float $max Maximum allowed price
 * @return float|null Valid price or null if invalid
 */
function sanitizePrice($price, $min = 0, $max = 100000) {
  $price = floatval($price);
  
  if ($price < $min || $price > $max) {
    return null;
  }
  
  return round($price, 2);
}

/**
 * Validate order status
 * 
 * @param string $status Status to validate
 * @return string|null Valid status or null if invalid
 */
function validateOrderStatus($status) {
  if (!defined('VALID_ORDER_STATUSES')) {
    return null;
  }
  
  return in_array($status, VALID_ORDER_STATUSES, true) ? $status : null;
}

/**
 * Validate payment status
 * 
 * @param string $status Status to validate
 * @return string|null Valid status or null if invalid
 */
function validatePaymentStatus($status) {
  if (!defined('VALID_PAYMENT_STATUSES')) {
    return null;
  }
  
  return in_array($status, VALID_PAYMENT_STATUSES, true) ? $status : null;
}

/**
 * Validate service mode
 * 
 * @param string $mode Mode to validate
 * @return string|null Valid mode or null if invalid
 */
function validateServiceMode($mode) {
  if (!defined('VALID_SERVICE_MODES')) {
    return null;
  }
  
  return in_array($mode, VALID_SERVICE_MODES, true) ? $mode : null;
}

/**
 * Validate integer ID
 * 
 * @param mixed $id ID to validate
 * @return int|null Valid ID or null if invalid
 */
function validateId($id) {
  $id = intval($id);
  
  if ($id <= 0) {
    return null;
  }
  
  return $id;
}

// ============================================================================
// RATE LIMITING
// ============================================================================

/**
 * Check if action exceeds rate limit
 * Prevents brute force and spam attacks
 * 
 * @param string $action Unique action identifier
 * @param int $maxAttempts Maximum attempts allowed
 * @param int $timeWindow Time window in seconds
 * @return bool True if within limits, false if exceeded
 */
function checkRateLimit($action, $maxAttempts = 5, $timeWindow = 60) {
  if (!isset($_SESSION['rate_limit'])) {
    $_SESSION['rate_limit'] = [];
  }
  
  $clientId = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  $key = hash('sha256', $action . $clientId);
  
  // First request - initialize
  if (!isset($_SESSION['rate_limit'][$key])) {
    $_SESSION['rate_limit'][$key] = [
      'count' => 1,
      'first_time' => time(),
      'last_time' => time()
    ];
    return true;
  }
  
  $data = $_SESSION['rate_limit'][$key];
  $elapsed = time() - $data['first_time'];
  
  // Reset if time window has passed
  if ($elapsed > $timeWindow) {
    $_SESSION['rate_limit'][$key] = [
      'count' => 1,
      'first_time' => time(),
      'last_time' => time()
    ];
    return true;
  }
  
  // Check if limit exceeded
  if ($data['count'] >= $maxAttempts) {
    error_log("⚠️  Rate limit exceeded - Action: {$action}, Client: {$clientId}, Attempts: {$data['count']}");
    return false;
  }
  
  // Increment counter
  $_SESSION['rate_limit'][$key]['count']++;
  $_SESSION['rate_limit'][$key]['last_time'] = time();
  
  return true;
}

/**
 * Clear rate limit for an action
 * Call after successful operation
 * 
 * @param string $action Action to clear
 */
function clearRateLimit($action) {
  $clientId = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  $key = hash('sha256', $action . $clientId);
  
  if (isset($_SESSION['rate_limit'][$key])) {
    unset($_SESSION['rate_limit'][$key]);
  }
}

/**
 * Get remaining attempts for rate-limited action
 * 
 * @param string $action Action to check
 * @param int $maxAttempts Maximum allowed attempts
 * @return int Remaining attempts (0 if exceeded)
 */
function getRateLimitRemaining($action, $maxAttempts = 5) {
  if (!isset($_SESSION['rate_limit'])) {
    return $maxAttempts;
  }
  
  $clientId = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  $key = hash('sha256', $action . $clientId);
  
  if (!isset($_SESSION['rate_limit'][$key])) {
    return $maxAttempts;
  }
  
  $attempts = $_SESSION['rate_limit'][$key]['count'] ?? 0;
  $remaining = max(0, $maxAttempts - $attempts);
  
  return $remaining;
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Check if user is admin
 * 
 * @return bool True if logged in as admin
 */
function isAdmin() {
  return isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin';
}

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in
 */
function isLoggedIn() {
  return isset($_SESSION['user_id']);
}

/**
 * Get current user ID
 * 
 * @return int|null Current user ID or null if not logged in
 */
function getCurrentUserId() {
  return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/**
 * Get current user role
 * 
 * @return string|null Current user role or null if not logged in
 */
function getCurrentUserRole() {
  return $_SESSION['role'] ?? null;
}

// Initialize CSRF token on page load
if (php_sapi_name() !== 'cli') {
  initializeCsrfToken();
}

?>