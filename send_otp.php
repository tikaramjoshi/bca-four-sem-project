<?php
session_start();

require_once "db.php";
require_once "mail_config.php";
require "vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: forgot_password.php");
    exit();
}

$email = trim($_POST['email']);

// Check email exists
$stmt = $pdo->prepare("SELECT user_id,name,email FROM users WHERE email=?");
$stmt->execute([$email]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {

    $_SESSION['error'] = "Email not found!";
    header("Location: forgot_password.php");
    exit();
}

// Generate OTP
$otp = rand(100000,999999);

// Save OTP in Session
$_SESSION['reset_email'] = $email;
$_SESSION['reset_otp'] = $otp;
$_SESSION['otp_expire'] = time() + 300; // 5 Minutes

$mail = new PHPMailer(true);

try {

    $mail->isSMTP();
    $mail->Host = MAIL_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = MAIL_USERNAME;
    $mail->Password = MAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = MAIL_PORT;

    $mail->setFrom(MAIL_USERNAME, "Online Bus Booking");
    $mail->addAddress($email, $user['name']);

    $mail->isHTML(true);

    $mail->Subject = "Password Reset OTP";

    $mail->Body = "
    <div style='font-family:Arial;padding:20px'>
        <h2 style='color:#1560BD'>Online Bus Booking System</h2>

        <p>Hello <b>{$user['name']}</b>,</p>

        <p>Your Password Reset OTP is:</p>

        <h1 style='letter-spacing:8px;color:red'>$otp</h1>

        <p>This OTP is valid for <b>5 minutes</b>.</p>

        <p>If you did not request a password reset, please ignore this email.</p>

        <br>

        <p>Thank you.</p>
    </div>
    ";

    $mail->send();

    $_SESSION['success'] = "OTP has been sent to your email.";

    header("Location: verify_otp.php");
    exit();

} catch (Exception $e) {

    $_SESSION['error'] = "Mail Error : ".$mail->ErrorInfo;

    header("Location: forgot_password.php");
    exit();
}