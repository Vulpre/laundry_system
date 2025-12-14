<?php
/**
 * Application Header & Navigation
 * 
 * Displays navigation menu with role-based links
 * Handles user session display and logout functionality
 * 
 * @package LaundryManagementSystem
 */

// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Load constants
if (!defined('ROLE_ADMIN')) {
  require_once __DIR__ . '/constants.php';
}

// ============================================================================
// GET USER DATA SAFELY
// ============================================================================

$userName = isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name'], ENT_QUOTES, 'UTF-8') : 'Guest';
$userRole = $_SESSION['role'] ?? null;
$isLoggedIn = isset($_SESSION['user_id']);

// ============================================================================
// DEFINE BASE URL
// ============================================================================

if (!defined('BASE_URL')) {
  $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $basePath = '/laundry_system'; // Adjust this if installed in different path
  define('BASE_URL', $protocol . $host . $basePath);
}

/**
 * Generate navigation link safely
 * Checks permissions and properly escapes output
 * 
 * @param string $url Relative URL
 * @param string $label Link label
 * @param string|null $icon Optional emoji/icon
 * @param string|null $requiredRole Required role for display (optional)
 */
function navLink($url, $label, $icon = null, $requiredRole = null) {
  global $userRole;
  
  // Check role permission
  if ($requiredRole !== null && $userRole !== $requiredRole) {
    return;
  }
  
  // Build full URL
  $fullUrl = htmlspecialchars(BASE_URL . '/' . ltrim($url, '/'), ENT_QUOTES, 'UTF-8');
  
  // Escape label
  $label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
  
  // Output link
  echo "<a href=\"{$fullUrl}\" class=\"nav-link\">{$label}</a>";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <style>
    /**
     * Navigation Styles
     */

    .nav {
      background: linear-gradient(135deg, #2563eb, #1e40af);
      padding: 14px 20px;
      border-radius: 0 0 12px 12px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
      font-family: 'Segoe UI', Arial, sans-serif;
      gap: 16px;
    }

    .nav-user {
      color: #fff;
      font-size: 14px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 6px;
      white-space: nowrap;
    }

    .nav-user::before {
      content: '👤';
      font-size: 16px;
    }

    .nav-links {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 8px;
      margin-left: auto;
    }

    .nav-link {
      color: white;
      text-decoration: none;
      padding: 8px 12px;
      font-weight: 500;
      transition: all 0.3s ease;
      border-radius: 6px;
      font-size: 13px;
      white-space: nowrap;
    }

    .nav-link:hover {
      background: rgba(255, 255, 255, 0.2);
      text-decoration: none;
      transform: translateY(-1px);
    }

    .nav-link:focus {
      outline: 2px solid rgba(255, 255, 255, 0.5);
      outline-offset: 2px;
    }

    .nav-link-logout {
      background: rgba(255, 255, 255, 0.2);
      border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .nav-link-logout:hover {
      background: rgba(255, 255, 255, 0.3);
    }

    .nav-divider {
      border: none;
      height: 2px;
      background: #2563eb;
      margin: 0;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .nav {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
      }

      .nav-user {
        width: 100%;
        margin-bottom: 8px;
      }

      .nav-links {
        margin-left: 0;
        width: 100%;
        flex-direction: column;
      }

      .nav-link {
        width: 100%;
        text-align: left;
      }

      .nav-link-logout {
        margin-top: 8px;
        border-top: 1px solid rgba(255, 255, 255, 0.2);
        padding-top: 10px;
      }
    }

    @media (max-width: 480px) {
      .nav {
        padding: 12px 16px;
        gap: 8px;
      }

      .nav-link {
        padding: 10px 12px;
        font-size: 12px;
      }

      .nav-user {
        font-size: 13px;
      }
    }

    /* Dark mode support */
    @media (prefers-color-scheme: dark) {
      .nav {
        background: linear-gradient(135deg, #1e3a8a, #1e1b4b);
      }
    }

    /* Reduce motion */
    @media (prefers-reduced-motion: reduce) {
      .nav-link {
        transition: none;
      }
    }
  </style>
</head>
<body>

<!-- ================================================================
     NAVIGATION HEADER
     ================================================================ -->

<nav class="nav" role="navigation" aria-label="Main navigation">
  
  <!-- User Display -->
  <div class="nav-user">
    <?php echo $userName; ?>
    <?php if ($isLoggedIn && $userRole): ?>
      <span style="font-size: 11px; opacity: 0.8;">
        (<?php echo ucfirst(htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8')); ?>)
      </span>
    <?php endif; ?>
  </div>

  <!-- Navigation Links -->
  <div class="nav-links">
    <?php if ($isLoggedIn): ?>
      
      <!-- Admin Navigation -->
      <?php if ($userRole === ROLE_ADMIN): ?>
        <?php navLink('admin/dashboard.php', '📊 Dashboard', null, ROLE_ADMIN); ?>
        <?php navLink('admin/manage_orders.php', '📋 Orders', null, ROLE_ADMIN); ?>
        <?php navLink('admin/create_order.php', '➕ Create Order', null, ROLE_ADMIN); ?>
        <?php navLink('admin/reports.php', '📈 Reports', null, ROLE_ADMIN); ?>
        <?php navLink('admin/price_list.php', '💰 Pricing', null, ROLE_ADMIN); ?>
        <?php navLink('admin/notifications.php', '🔔 Notifications', null, ROLE_ADMIN); ?>
        <?php navLink('admin/manage_users.php', '👥 Users', null, ROLE_ADMIN); ?>
        <?php navLink('admin/manage_admins.php', '👨‍💼 Admins', null, ROLE_ADMIN); ?>
      
      <!-- User Navigation -->
      <?php elseif ($userRole === ROLE_USER): ?>
        <?php navLink('user/home.php', '🏠 Home', null, ROLE_USER); ?>
        <?php navLink('user/create_order.php', '➕ New Order', null, ROLE_USER); ?>
        <?php navLink('user/order_status.php', '📍 Track Order', null, ROLE_USER); ?>
        <?php navLink('user/order_history.php', '📜 History', null, ROLE_USER); ?>
      
      <?php endif; ?>

      <!-- Logout Link -->
      <a href="<?php echo htmlspecialchars(BASE_URL . '/logout.php', ENT_QUOTES, 'UTF-8'); ?>" 
         class="nav-link nav-link-logout" 
         title="Logout from your account">
        🚪 Logout
      </a>

    <?php else: ?>
      
      <!-- Guest Navigation -->
      <a href="<?php echo htmlspecialchars(BASE_URL . '/index.php', ENT_QUOTES, 'UTF-8'); ?>" 
         class="nav-link" 
         title="Login to your account">
        🔐 Login
      </a>

      <a href="<?php echo htmlspecialchars(BASE_URL . '/register.php', ENT_QUOTES, 'UTF-8'); ?>" 
         class="nav-link" 
         title="Create a new account">
        ✨ Register
      </a>

    <?php endif; ?>
  </div>

</nav>

<!-- Navigation Divider -->
<hr class="nav-divider">

</body>
</html>