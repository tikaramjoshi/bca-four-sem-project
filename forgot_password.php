<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
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
            color: #1560BD;
            margin-bottom: 20px;
        }
        input {
            width: 100%;
            padding: 12px;
            margin-top: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }
        button {
            width: 100%;
            margin-top: 20px;
            padding: 12px;
            border: none;
            background: #1560BD;
            color: white;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background: #0d4d9c;
        }
        .msg {
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
        a {
            display: block;
            margin-top: 20px;
            text-align: center;
            text-decoration: none;
            color: #1560BD;
        }
    </style>
</head>
<body>
    <div class="box">
        <h2>Forgot Password</h2>
        <?php
        if (isset($_SESSION['success'])) {
            echo "<div class='msg success'>" . $_SESSION['success'] . "</div>";
            unset($_SESSION['success']);
        }
        if (isset($_SESSION['error'])) {
            echo "<div class='msg error'>" . $_SESSION['error'] . "</div>";
            unset($_SESSION['error']);
        }
        ?>
        <form action="send_otp.php" method="POST">
            <input
                type="email"
                name="email"
                placeholder="Enter your registered email"
                required>
            <button type="submit">
                Send OTP
            </button>
        </form>
        <a href="login.php">
            Back to Login
        </a>
    </div>
</body>
</html>