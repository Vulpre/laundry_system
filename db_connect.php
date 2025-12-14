<?php
/**
 * Database Connection & Utility Functions
 * InfinityFree - PRODUCTION READY
 * 
 * @package LaundryManagementSystem
 */

// Start session if not already started (suppress errors for deployment)
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================

// Use environment variables if set, otherwise fallback to defaults
define('DB_HOST', getenv('DB_HOST') ?: 'sql308.infinityfree.com');
define('DB_USER', getenv('DB_USER') ?: 'if0_40678472');
define('DB_PASS', getenv('DB_PASS') ?: 'r1Kv94jqhMe1W');
define('DB_NAME', getenv('DB_NAME') ?: 'if0_40678472_laundry_db');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// Disable debug mode for production by default
define('DB_DEBUG', getenv('DB_DEBUG') === 'true');

// ============================================================================
// DATABASE CONNECTION
// ============================================================================

try {
    // Suppress errors for deployment, handle via exceptions
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception($conn->connect_error);
    }

    // Set charset
    if (!$conn->set_charset(DB_CHARSET)) {
        throw new Exception($conn->error);
    }

    // Ensure connection is accessible globally for legacy code that expects $conn
    $GLOBALS['conn'] = $conn;

} catch (Exception $e) {
    if (DB_DEBUG) {
        die("❌ Database Connection Error: " . $e->getMessage());
    } else {
        // Generic error for production
        error_log("Database Connection Error: " . $e->getMessage());
        die("❌ Unable to connect to database. Please try again later.");
    }
}

// ============================================================================
// SECURITY & UTILITY FUNCTIONS
// ============================================================================

/**
 * Sanitize text input
 * Removes dangerous characters and limits length
 * 
 * @param string $input The input to sanitize
 * @param int $maxLength Maximum allowed length
 * @return string Sanitized text
 */
function sanitizeText($input, $maxLength = 255) {
    // Trim whitespace
    $input = trim($input);
    
    // Remove null bytes
    $input = str_replace("\0", '', $input);
    
    // Limit length
    if (strlen($input) > $maxLength) {
        $input = substr($input, 0, $maxLength);
    }
    
    return $input;
}

/**
 * Escape HTML output
 * Prevents XSS attacks
 * 
 * @param string $str The string to escape
 * @return string HTML-safe string
 */
function esc($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Get database connection
 * 
 * @return mysqli Database connection object
 */
function getDatabase() {
    global $conn;
    return $conn;
}

/**
 * Execute prepared statement with error handling
 * 
 * @param mysqli $conn Database connection
 * @param string $query SQL query with placeholders
 * @param string $types Parameter types (e.g., "ssi" for string, string, int)
 * @param array $params Parameters to bind
 * @return mysqli_stmt|false Prepared statement or false on error
 */
function executeQuery($conn, $query, $types = '', $params = []) {
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        error_log("Query preparation failed: " . $conn->error);
        return false;
    }
    
    if ($types && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        error_log("Query execution failed: " . $stmt->error);
        return false;
    }
    
    return $stmt;
}

/**
 * Verify user session is valid
 * Checks for session hijacking and timeout
 * 
 * @param int $timeout Session timeout in seconds (default: 30 minutes)
 * @return bool True if session is valid
 */
function verifySession($timeout = 1800) {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Check session timeout
    if (isset($_SESSION['login_time'])) {
        $elapsed = time() - $_SESSION['login_time'];
        if ($elapsed > $timeout) {
            session_destroy();
            return false;
        }
        // Update last activity time
        $_SESSION['login_time'] = time();
    }
    
    // Check IP address (basic session hijacking prevention)
    if (isset($_SESSION['login_ip'])) {
        $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($_SESSION['login_ip'] !== $currentIp) {
            error_log("⚠️ Session hijacking attempt detected - User ID: {$_SESSION['user_id']}, Original IP: {$_SESSION['login_ip']}, Current IP: {$currentIp}");
            session_destroy();
            return false;
        }
    }
    
    return true;
}

/**
 * Require authentication
 * Redirects to login if user is not authenticated
 * 
 * @param string $role Required role (optional)
 */
function requireAuth($role = null) {
    if (!verifySession()) {
        header('Location: ../index.php?session=expired');
        exit;
    }
    
    if ($role && $_SESSION['role'] !== $role) {
        header('Location: ../index.php?error=security');
        exit;
    }
}

/**
 * Generate CSRF token
 * 
 * @return string CSRF token
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * 
 * @param string $token Token to verify
 * @return bool True if valid
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ============================================================================
// CLOSE DATABASE ON SHUTDOWN
// ============================================================================

register_shutdown_function(function () {
    global $conn;
    if ($conn && $conn instanceof mysqli) {
        $conn->close();
    }
});
?>