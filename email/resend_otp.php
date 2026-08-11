<?php
session_start();

require_once "../db.php";
require_once "../mail_config.php";
require "../vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION['reset_email'];

$stmt = $conn->prepare("SELECT name FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

if (!$user) {
    header("Location: forgot_password.php");
    exit();
}

$otp = rand(100000, 999999);

$_SESSION['reset_otp'] = $otp;
$_SESSION['otp_expire'] = time() + 180;

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
    $mail->Subject = "New OTP";

    $mail->Body = "
    <h2>Online Bus Booking</h2>
    <p>Your New OTP:</p>
    <h1>$otp</h1>
    <p>Valid for 5 minutes.</p>
    ";

    $mail->send();

    $_SESSION['success'] = "New OTP Sent Successfully.";
} catch (Exception $e) {

    $_SESSION['error'] = "Failed to Send OTP.";
}

header("Location: verify_otp.php");
exit();
