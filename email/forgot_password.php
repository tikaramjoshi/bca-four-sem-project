<?php
session_start();
unset($_SESSION['message']);
unset($_SESSION['message_type']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="../index.css">
    <link rel="stylesheet" href="forgot.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
</head>

<body>
    <?php include "head.php"; ?>
    <div class="forgot-container">
        <div class="box">
            <h2>Forgot Password</h2>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="msg success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']);
            endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="msg error"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']);
            endif; ?>
            <form action="send_otp.php" method="POST">
                <input type="email" name="email" placeholder="Enter your registered email" required>
                <button type="submit">Send OTP</button>
            </form>
            <a href="../login.php">Back to Login</a>
        </div>
    </div>
    <?php include "foot.php"; ?>
</body>

</html>