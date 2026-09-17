<?php
session_start();

if (!isset($_SESSION['otp_verified']) || !isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

require_once "../db.php";

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if ($password != $confirm_password) {
        $message = "Passwords do not match!";
        $message_type = "error";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters!";
        $message_type = "error";
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $email = $_SESSION['reset_email'];

        $stmt = $conn->prepare("UPDATE users SET password=? WHERE email=?");
        $stmt->bind_param("ss", $hash, $email);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $message = "Password changed successfully.";
            $message_type = "success";

            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_otp']);
            unset($_SESSION['otp_expire']);
            unset($_SESSION['otp_verified']);
        } else {
            $message = "Password could not be changed!";
            $message_type = "error";
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

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
            width: 420px;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #1560BD;
        }

        input {
            width: 100%;
            padding: 12px;
            margin: 12px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #1560BD;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #0d4d9c;
        }

        .success {
            color: green;
            text-align: center;
            margin-bottom: 15px;
            font-weight: bold;
        }

        .error {
            color: red;
            text-align: center;
            margin-bottom: 15px;
            font-weight: bold;
        }

        .password-box {
            position: relative;
        }

        .password-box input {
            padding-right: 45px;
        }

        .password-box i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
        }
    </style>
</head>

<body>
    <div class="box">
        <h2>Reset Password</h2>

        <?php if ($message != "") { ?>
            <div class="<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php } ?>

        <?php if ($message_type != "success") { ?>
            <form method="POST">
                <div class="password-box">
                    <input type="password" name="password" id="password" placeholder="New Password" required>
                    <i class="fa-solid fa-eye" onclick="togglePassword('password', this)"></i>
                </div>
                <div class="password-box">
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
                    <i class="fa-solid fa-eye" onclick="togglePassword('confirm_password', this)"></i>
                </div>
                <button type="submit">Reset Password</button>
            </form>

        <?php } ?>
    </div>

    <?php if ($message_type == "success") { ?>
    <?php } ?>
    <script>
        setTimeout(function() {
            window.location.href = "../login.php";
        }, 3000);

        function togglePassword(id, icon) {
            let password = document.getElementById(id);
            if (password.type === "password") {
                password.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                password.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }
    </script>

</body>

</html>