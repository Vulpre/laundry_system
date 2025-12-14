<?php

if (!defined('LAUNDRY_CONSTANTS_LOADED')) {
    define('LAUNDRY_CONSTANTS_LOADED', true);
    
    define('SESSION_TIMEOUT', 3600);
    define('CSRF_TOKEN_LENGTH', 32);
    
    define('ROLE_ADMIN', 'admin');
    define('ROLE_USER', 'user');
    
    define('ORDER_STATUS_PENDING', 'Pending');
    define('ORDER_STATUS_IN_PROGRESS', 'In Progress');
    define('ORDER_STATUS_READY', 'Ready');
    define('ORDER_STATUS_PICKUP', 'Pickup');
    define('ORDER_STATUS_ARCHIVED', 'Archived');
    
    define('VALID_ORDER_STATUSES', [
        ORDER_STATUS_PENDING,
        ORDER_STATUS_IN_PROGRESS,
        ORDER_STATUS_READY,
        ORDER_STATUS_PICKUP,
        ORDER_STATUS_ARCHIVED
    ]);
    
    define('PAYMENT_STATUS_PAID', 'Paid');
    define('PAYMENT_STATUS_UNPAID', 'Unpaid');
    define('PAYMENT_STATUS_PARTIAL', 'Partial');
    
    define('VALID_PAYMENT_STATUSES', [
        PAYMENT_STATUS_PAID,
        PAYMENT_STATUS_UNPAID,
        PAYMENT_STATUS_PARTIAL
    ]);
    
    define('SERVICE_MODE_REGULAR', 'Regular');
    define('SERVICE_MODE_EXPRESS', 'Express');
    
    define('VALID_SERVICE_MODES', [
        SERVICE_MODE_REGULAR,
        SERVICE_MODE_EXPRESS
    ]);
    
    define('NOTIF_TYPE_ORDER', 'order');
    define('NOTIF_TYPE_PAYMENT', 'payment');
    define('NOTIF_TYPE_SYSTEM', 'system');
    define('NOTIF_TYPE_ALERT', 'alert');
    define('NOTIF_TYPE_SUCCESS', 'success');
    define('NOTIF_TYPE_INFO', 'info');
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = '/laundry_system';
    
    if (!defined('BASE_URL')) {
        define('BASE_URL', $protocol . $host . $basePath);
    }
    
    define('MIN_PASSWORD_LENGTH', 8);
    define('MAX_ORDER_AMOUNT', 100000);
    define('MAX_QUANTITY', 1000);
    define('MAX_NAME_LENGTH', 255);
    define('MAX_EMAIL_LENGTH', 254);
    define('MAX_NOTES_LENGTH', 1000);
    
    define('RATE_LIMIT_LOGIN', 5);
    define('RATE_LIMIT_REGISTER', 3);
    define('RATE_LIMIT_ORDER', 10);
    define('RATE_LIMIT_WINDOW', 60);
}

function esc($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

?>