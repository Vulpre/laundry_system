<?php
/**
 * Notification Helper Functions
 * 
 * Handles database notifications and email sending
 * 
 * @package LaundryManagementSystem
 */

// ============================================================================
// EMAIL SENDING
// ============================================================================

/**
 * Send email notification using PHP mail()
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @return bool Success status
 */
function sendEmailNotification($to, $subject, $body) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Laundry System <noreply@laundry.com>" . "\r\n";
    
    $result = mail($to, $subject, $body, $headers);
    
    if ($result) {
        error_log("✅ Email sent to $to: $subject");
    } else {
        error_log("❌ Failed to send email to $to: $subject");
    }
    
    return $result;
}

// ============================================================================
// DATABASE NOTIFICATIONS
// ============================================================================

/**
 * Send notification to database
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID to notify
 * @param string $type Notification type
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string|null $link Optional link
 * @return bool Success status
 */
function sendNotification($conn, $user_id, $type, $title, $message, $link = null) {
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) 
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ");
    
    if (!$stmt) {
        error_log("Failed to prepare notification: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param('issss', $user_id, $type, $title, $message, $link);
    $result = $stmt->execute();
    
    if ($result) {
        error_log("✅ Notification sent to user $user_id: $title");
    } else {
        error_log("❌ Failed to send notification: " . $stmt->error);
    }
    
    $stmt->close();
    
    return $result;
}

/**
 * Send notification to all admins
 * 
 * @param mysqli $conn Database connection
 * @param string $type Notification type
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string|null $link Optional link
 * @return bool Success status
 */
function notifyAllAdmins($conn, $type, $title, $message, $link = null) {
    $admins = $conn->query("SELECT id, email, name FROM users WHERE role = 'admin'");
    
    if (!$admins) {
        error_log("Failed to fetch admins: " . $conn->error);
        return false;
    }
    
    $success = true;
    while ($admin = $admins->fetch_assoc()) {
        if (!sendNotification($conn, $admin['id'], $type, $title, $message, $link)) {
            $success = false;
        }
    }
    
    return $success;
}

/**
 * Notify admins about new order
 * 
 * @param mysqli $conn Database connection
 * @param int $order_id Order ID
 * @param string $customer_name Customer name
 * @param float $total_cost Order total cost
 * @return bool Success status
 */
function notifyNewOrder($conn, $order_id, $customer_name, $total_cost) {
    $admins = $conn->query("SELECT id, email, name FROM users WHERE role = 'admin'");
    
    if (!$admins) {
        return false;
    }
    
    while ($admin = $admins->fetch_assoc()) {
        // Send database notification
        sendNotification(
            $conn,
            $admin['id'],
            'order',
            'New Order Received',
            "Order #$order_id from $customer_name - Total: ₱" . number_format($total_cost, 2),
            'manage_orders.php'
        );
        
        // Send email notification
        sendEmailNotification(
            $admin['email'],
            "New Order #$order_id",
            "<p>Hello {$admin['name']},</p>
             <p>New order received from <strong>$customer_name</strong></p>
             <p>Order #: $order_id</p>
             <p>Total: ₱" . number_format($total_cost, 2) . "</p>"
        );
    }
    
    return true;
}

/**
 * Notify customer of status change
 * 
 * @param mysqli $conn Database connection
 * @param int $order_id Order ID
 * @param int $user_id User ID
 * @param string $new_status New order status
 * @return bool Success status
 */
function notifyCustomerStatusChange($conn, $order_id, $user_id, $new_status) {
    $statusMessages = [
        'Pending' => 'Your order has been received and is awaiting processing.',
        'In Progress' => 'Great news! Your laundry is now being processed.',
        'Ready' => 'Your laundry is ready for pickup!',
        'Delivered' => 'Your order has been delivered.',
        'Pickup' => 'Thank you! Your order has been completed.'
    ];
    
    $message = $statusMessages[$new_status] ?? "Your order status has been updated to: $new_status";
    
    return sendNotification(
        $conn,
        $user_id,
        'order',
        "Order #$order_id - $new_status",
        $message,
        '../user/order_status.php'
    );
}

/**
 * Notify customer of payment received
 * 
 * @param mysqli $conn Database connection
 * @param int $order_id Order ID
 * @param int $user_id User ID
 * @param float $amount Payment amount
 * @return bool Success status
 */
function notifyCustomerPaymentReceived($conn, $order_id, $user_id, $amount) {
    return sendNotification(
        $conn,
        $user_id,
        'payment',
        "Payment Received - Order #$order_id",
        "We have received your payment of ₱" . number_format($amount, 2) . ". Thank you!",
        '../user/order_status.php'
    );
}

// ============================================================================
// EMAIL TEMPLATES
// ============================================================================

/**
 * Build HTML email template
 * 
 * @param array $data Template data
 * @return string HTML email
 */
function buildEmailTemplate($data) {
    $greeting = $data['greeting'] ?? 'Customer';
    $subject = $data['subject'] ?? 'Notification';
    $body = $data['body'] ?? '';
    $cta_text = $data['cta_text'] ?? '';
    $cta_link = $data['cta_link'] ?? '';
    
    $ctaButton = '';
    if ($cta_link && $cta_text) {
        $ctaButton = "
            <p style='text-align: center; margin: 30px 0;'>
                <a href='$cta_link' style='background: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: 600; display: inline-block;'>$cta_text</a>
            </p>
        ";
    }
    
    return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #2563eb, #1e40af); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: white; padding: 30px; border: 1px solid #e5e7eb; }
                .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 13px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 style='margin: 0;'>🧺 Laundry System</h1>
                </div>
                <div class='content'>
                    <h2 style='color: #2563eb;'>$subject</h2>
                    <p>Hello <strong>$greeting</strong>,</p>
                    $body
                    $ctaButton
                </div>
                <div class='footer'>
                    <hr style='border: 1px solid #e5e7eb; margin: 20px 0;'>
                    <p>© " . date('Y') . " Laundry Management System. All rights reserved.</p>
                    <p>This is an automated message, please do not reply.</p>
                </div>
            </div>
        </body>
        </html>
    ";
}

// ============================================================================
// TESTING FUNCTION
// ============================================================================

/**
 * Test email sending
 * 
 * @param string $email Recipient email
 * @param string $name Recipient name
 * @return bool Success status
 */
function testEmailSending($email, $name) {
    $subject = "Test Email from Laundry System";
    $body = buildEmailTemplate([
        'greeting' => $name,
        'subject' => 'Email Test',
        'body' => "
            <p>This is a test email from your Laundry Management System.</p>
            <p>If you received this, your email configuration is working correctly!</p>
            <p><strong>Sent at:</strong> " . date('F d, Y h:i A') . "</p>
        "
    ]);
    
    return sendEmailNotification($email, $subject, $body);
}

?>