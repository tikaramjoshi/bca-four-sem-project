<?php
session_start();

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $userOTP = trim($_POST['otp']);

    if (time() > $_SESSION['otp_expire']) {

        $_SESSION['error'] = "OTP Expired! Please request a new OTP.";
        header("Location: forgot_password.php");
        exit();
    }

    if ($userOTP == $_SESSION['reset_otp']) {

        $_SESSION['otp_verified'] = true;

        header("Location: reset_password.php");
        exit();
    } else {

        $error = "Invalid OTP!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial;
        }
        body {
            background: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .box {
            width: 400px;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, .2);
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #1560BD;
        }
        input {
            width: 100%;
            padding: 12px;
            margin: 15px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #1560BD;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover {
            background: #0b4d9a;
        }
        .error {
            color: red;
            text-align: center;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .success {
            color: green;
            text-align: center;
            margin-bottom: 15px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="box">
        <h2>Verify OTP</h2>
        <?php
        if (isset($error)) {
            echo "<div class='error'>$error</div>";
        }
        if (isset($_SESSION['success'])) {
            echo "<div class='success'>" . $_SESSION['success'] . "</div>";
            unset($_SESSION['success']);
        }
        ?>
        <form method="POST">
            <input
                type="text"
                name="otp"
                placeholder="Enter 6 Digit OTP"
                maxlength="6"
                required>
            <button type="submit"> Verify OTP  </button>
            <p id="timer" style="margin-top:15px;color:red;font-weight:bold;text-align:center;"></p>

            <div id="resend" style="display:none;text-align:center;margin-top:10px;">
                <a href="resend_otp.php">Resend OTP</a>
            </div>
        </form>
    </div>
    <script>
        let sec = <?= max(0, $_SESSION['otp_expire'] - time()) ?>;
        const timer = document.getElementById("timer");
        const resend = document.getElementById("resend");
        function countdown() {
            if (sec <= 0) {
                timer.innerHTML = "OTP Expired";
                resend.style.display = "block";
                return;
            }

            let min = Math.floor(sec / 60);
            let second = sec % 60;
            timer.innerHTML =
                "OTP expires in " +
                min + ":" +
                (second < 10 ? "0" + second : second);
            sec--;
            setTimeout(countdown, 1000);
        }
        countdown();
    </script>
</body>
</html>