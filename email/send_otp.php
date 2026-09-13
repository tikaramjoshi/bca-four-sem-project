<?php
session_start();
require_once "../db.php";
require_once "../mail_config.php";
require "../vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$admin_reset = isset($_GET['admin_reset']) && $_GET['admin_reset'] == '1';
if ($admin_reset) {
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        header("Location: ../login.php");
        exit();
    }
    $admin_email = $_SESSION['admin_reset_email'] ?? '';
    $selected_user_id = $_SESSION['admin_reset_user_id'] ?? 0;
    $selected_user_name = $_SESSION['admin_reset_user_name'] ?? '';
    $selected_user_email = $_SESSION['admin_reset_user_email'] ?? '';
    if (!$admin_email || !$selected_user_id) {
        $_SESSION['error'] = "Reset information not found.";
        header("Location: ../admin/reset_password.php");
        exit();
    }
    $stmt = $conn->prepare("SELECT user_id, name, email, role FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $selected_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    if (!$user) {
        $_SESSION['error'] = "Selected user not found.";
        header("Location: ../admin/reset_password.php");
        exit();
    }
    $email = $admin_email;
} else {
    if ($_SERVER["REQUEST_METHOD"] != "POST") {
        header("Location: forgot_password.php");
        exit();
    }
    $email = trim($_POST['email']);
    $stmt = $conn->prepare("SELECT user_id, name, email, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    if (!$user) {
        $_SESSION['error'] = "Email not found!";
        header("Location: forgot_password.php");
        exit();
    }
}
$otp = rand(100000, 999999);
$_SESSION['reset_email'] = $email;
$_SESSION['reset_otp'] = $otp;
$_SESSION['otp_expire'] = time() + 180;
if ($admin_reset) {
    $_SESSION['admin_reset_mode'] = true;
    $_SESSION['admin_reset_user_id'] = $user['user_id'];
    $_SESSION['admin_reset_user_name'] = $user['name'];
    $_SESSION['admin_reset_user_email'] = $user['email'];
    $_SESSION['admin_reset_user_role'] = $user['role'];
}
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = MAIL_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = MAIL_USERNAME;
    $mail->Password = MAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = MAIL_PORT;
    $mail->setFrom(MAIL_USERNAME, "Online Bus Ticket Booking System");
    $mail->addAddress($email, $admin_reset ? "Admin" : $user['name']);
    $mail->isHTML(true);
    $mail->Subject = "Password Reset Verification Code";
    if ($admin_reset) {
        $mail->Body = "
        <div style='font-family:Arial,sans-serif;padding:20px'>
            <h2 style='color:#1560BD'>Online Bus Ticket Booking System</h2>
            <p>Hello <b>Admin</b>,</p>
            <p>A password reset request has been initiated by Admin.</p>
            <h3>User Information</h3>
            <p><b>User ID:</b> " . htmlspecialchars($user['user_id']) . "</p>
            <p><b>Name:</b> " . htmlspecialchars($user['name']) . "</p>
            <p><b>Email:</b> " . htmlspecialchars($user['email']) . "</p>
            <p><b>Role:</b> " . htmlspecialchars($user['role']) . "</p>
            <h3>Password Reset Information</h3>
            <p>The password for the above user will be reset after successful OTP verification.</p>
            <p><b>Verification Code:</b></p>
            <h1 style='letter-spacing:8px;color:red'>$otp</h1>
            <p>This verification code is valid for <b>3 minutes</b>.</p>
            <p><b>Reset Requested At:</b> " . date('Y-m-d H:i:s') . "</p>
            <p>After successful verification, the user's password will be reset by the system.</p>
            <br>
            <p>Thank you.</p>
            <p><b>Online Bus Ticket Booking System</b></p>
        </div>
        ";
    } else {
        $mail->Body = "
        <div style='font-family:Arial,sans-serif;padding:20px'>
            <h2 style='color:#1560BD'>Online Bus Ticket Booking System</h2>
            <p>Hello <b>" . htmlspecialchars($user['name']) . "</b>,</p>
            <p>Your password reset verification code is:</p>
            <h1 style='letter-spacing:8px;color:red'>$otp</h1>
            <p>This code is valid for <b>3 minutes</b>.</p>
            <p>If you did not request this, please ignore this email.</p>
            <br>
            <p>Thank you.</p>
            <p><b>Online Bus Ticket Booking System</b></p>
        </div>
        ";
    }
    $mail->send();
    $_SESSION['success'] = "Verification code has been sent to admin email.";
    if ($admin_reset) {
        header("Location: verify_otp.php?admin_reset=1");
    } else {
        header("Location: verify_otp.php");
    }
    exit();
} catch (Exception $e) {
    $_SESSION['error'] = "Mail Error : " . $mail->ErrorInfo;
    if ($admin_reset) {
        header("Location: ../admin/reset_password.php");
    } else {
        header("Location: forgot_password.php");
    }
    exit();
}
