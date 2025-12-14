<?php
session_start();
require '../db_connect.php';

// ✅ Ensure only admins can access this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// ✅ Validate the user_id input
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);

    // ✅ Prevent deleting admin accounts
    $check = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $check->bind_param('i', $user_id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        if ($row['role'] === 'admin') {
            $_SESSION['error'] = "❌ You cannot delete another admin account.";
        } else {
            // ✅ Delete all related orders first (to prevent orphan data)
            $conn->query("DELETE FROM orders WHERE user_id = $user_id");
            $conn->query("DELETE FROM order_history WHERE user_id = $user_id");

            // ✅ Then delete user
            $delete = $conn->prepare("DELETE FROM users WHERE id = ?");
            $delete->bind_param('i', $user_id);
            if ($delete->execute()) {
                $_SESSION['success'] = "✅ User and their orders were successfully deleted.";
            } else {
                $_SESSION['error'] = "⚠️ Failed to delete user. Please try again.";
            }
            $delete->close();
        }
    } else {
        $_SESSION['error'] = "⚠️ User not found.";
    }

    $check->close();
}

// ✅ Redirect back to dashboard
header('Location: dashboard.php');
exit;
?>
