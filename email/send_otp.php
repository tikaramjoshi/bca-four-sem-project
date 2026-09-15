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
    if (!$admin_email || !$selected_user_id) {
        $_SESSION['error'] = "Reset information not found.";
        header("Location: ../admin/reset_password.php");
        exit();
    }
    $stmt = $conn->prepare("SELECT user_id,name,email,role FROM users WHERE user_id=?");
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
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please enter a valid email.";
        header("Location: forgot_password.php");
        exit();
    }
    $stmt = $conn->prepare("SELECT user_id,name,email,role FROM users WHERE email=?");
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
        <div style='font-family:Arial,sans-serif;background:#f4f7ff;padding:30px'>
        <div style='max-width:600px;margin:auto;background:white;border-radius:15px;padding:30px;box-shadow:0 5px 20px rgba(0,0,0,0.15)'>
        <h2 style='text-align:center;color:#1560BD'>Online Bus Ticket Booking System</h2>
        <p>Hello <b>Admin</b>,</p>
        <p>A password reset request has been made for the following user.</p>
        <div style='background:#f1f5ff;padding:15px;border-radius:10px'>
        <p><b>User ID:</b> " . htmlspecialchars($user['user_id']) . "</p>
        <p><b>Name:</b> " . htmlspecialchars($user['name']) . "</p>
        <p><b>Email:</b> " . htmlspecialchars($user['email']) . "</p>
        <p><b>Role:</b> " . htmlspecialchars($user['role']) . "</p>
        </div>
        <p style='margin-top:20px'>Your verification code is:</p>
        <div style='text-align:center;background:#1560BD;color:white;padding:15px;border-radius:10px'>
        <h1 style='letter-spacing:10px;margin:0'>$otp</h1>
        </div>
        <p>This verification code is valid for <b>3 minutes</b>.</p>
        <p><b>Reset Requested At:</b> " . date('Y-m-d H:i:s') . "</p>
        <p>If you did not request this reset, please ignore this email.</p>
        <p>Thank you.</p>
        <p><b>Online Bus Ticket Booking System</b></p>
        </div>
        </div>";
    } else {
        $mail->Body = "
        <div style='font-family:Arial,sans-serif;background:#f4f7ff;padding:30px'>
        <div style='max-width:600px;margin:auto;background:white;border-radius:15px;padding:30px;box-shadow:0 5px 20px rgba(0,0,0,0.15)'>
        <h2 style='text-align:center;color:#1560BD'>Online Bus Ticket Booking System</h2>
        <p>Hello <b>" . htmlspecialchars($user['name']) . "</b>,</p>
        <p>Your password reset verification code is:</p>
        <div style='text-align:center;background:#1560BD;color:white;padding:15px;border-radius:10px'>
        <h1 style='letter-spacing:10px;margin:0'>$otp</h1>
        </div>
        <p style='text-align:center;font-size:16px'>This code is valid for <b>3 minutes</b>.</p>
        <div style='background:#fff3cd;padding:15px;border-radius:10px'>
        <p style='margin:0'><b>Security Notice:</b> If you did not request a password reset, please ignore this email.</p>
        </div>
        <p>Thank you.</p>
        <p><b>Online Bus Ticket Booking System</b></p>
        </div>
        </div>";
    }
    $mail->send();
    $_SESSION['success'] = $admin_reset ? "Verification code has been sent to admin email." : "Verification code has been sent to your email.";
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
