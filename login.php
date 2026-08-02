<?php

session_start();
require_once "db.php";

$message = $_SESSION['message'] ?? "";
$message_type = $_SESSION['message_type'] ?? "";

unset($_SESSION['message']);
unset($_SESSION['message_type']);
$locked = false;


if (isset($_SESSION['login_lock_time'])) {
    if (time() < $_SESSION['login_lock_time']) {
        $locked = true;
        $remaining = $_SESSION['login_lock_time'] - time();
        $message = "Login locked. Try again after " . $remaining . " seconds";

        $message_type = "error";
    } else {
        unset($_SESSION['login_lock_time']);
        unset($_SESSION['login_attempt']);
    }
}

if (!isset($_SESSION['login_attempt'])) {
    $_SESSION['login_attempt'] = 0;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && !$locked) {

    $login = trim($_POST['email']);

    $password = $_POST['password'];
    $stmt = $pdo->prepare(
        "SELECT * FROM users WHERE email=:login OR phone=:login"
    );

    $stmt->execute([

        "login" => $login

    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {

        $_SESSION['login_attempt'] = 0;

        unset($_SESSION['login_lock_time']);
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];

        if ($user['role'] == "admin") {
            $page = "admin/dashboard.php";
        } elseif ($user['role'] == "owner") {
            $page = "owner/dashboard.php";
        } elseif ($user['role'] == "driver") {
            $page = "driver/dashboard.php";
        } else {
            $page = "passenger/dashboard.php";
        }
        $message = "Please wait...";
        $message_type = "success";
    } else {
        $_SESSION['login_attempt']++;
        if ($_SESSION['login_attempt'] >= 5) {
            $_SESSION['login_lock_time'] = time() + 60;
            $_SESSION['message'] =
                "5 failed attempts. Login locked for 60 seconds.";
        } else {
            $_SESSION['message'] =
                "Incorrect email or password!";
        }
        $_SESSION['message_type'] = "error";
        header("Location: login.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <title>Login</title>
    <style>
        * {
            padding: 0;
            margin: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f4f7f6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .main {
            background-color: #1560BD;
            padding: 15px 25px;
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            color: #fff;
        }

        nav a {
            text-decoration: none;
            color: black;
            background-color: rgb(165, 154, 239);
            padding: 8px 15px;
            font-weight: bold;
            border-radius: 4px;
            transition: 0.3s;
        }

        nav a:hover,
        .submit button:hover {
            color: white;
            background-color: rgb(15, 160, 112);
        }

        .submit {
            display: flex;
            align-items: center;
        }

        .submit button {
            background-color: orange;
            border: none;
            padding: 8px 15px;
            border-radius: 15px;
            font-weight: bold;
            margin-left: 10px;
            cursor: pointer;
            transition: 0.3s;
        }

        #login.active {
            background-color: rgb(17, 127, 134);
            color: white;
        }

        .login-page {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        .form-login {
            width: 100%;
            max-width: 400px;
            background: white;
            padding: 32px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .form-login input {
            width: 100%;
            padding: 12px;
            margin-top: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 15px;
            outline: none;
        }

        .form-login input:focus {
            border-color: #1560BD;
        }

        .form-login button {
            width: 100%;
            padding: 12px;
            border: none;
            color: white;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
            font-weight: bold;
        }

        .btn-login {
            background: #1560BD;
            margin-top: 18px;
        }

        .btn-login:hover,
        .btn-register:hover {
            background: #0fa070;
        }

        .btn-register {
            background-color: #42b72a;
            margin-top: 10px;
        }

        .form-login a {
            display: block;
            text-align: center;
            margin: 10px 0;
            color: #1560BD;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
        }

        .form-login a:hover {
            text-decoration: underline;
        }

        .last {
            background-color: #1560BD;
            color: white;
            width: 100%;
            padding: 15px;
            text-align: center;
        }

        .lab-inp {
            position: relative;
            margin-top: 12px;
        }

        .lab-inp input {
            width: 100%;
            padding: 12px 45px 12px 12px;
            margin-top: 0;
        }

        .lab-inp span {
            position: absolute;
            right: 15px;
            top: 55%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            font-size: 18px;
        }

        .lab-inp span:hover {
            color: #1560BD;
        }

        @media screen and (max-width: 600px) {
            nav {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }

            .submit button {
                margin-left: 8px;
            }

            .login-page {
                padding: 10px 5px;
            }

            .form-login {
                padding: 15px;
            }

        }

        .error {
            background: #ffe5e5;
            color: red;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            text-align: center;
        }

        .success {
            background: #e5ffe5;
            color: green;
            padding: 10px;
            margin-top: 15px;
            border-radius: 5px;
            text-align: center;
        }

        .popup-bg {

            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, .5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 999;
        }

        .popup {
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
        }

        .blur-lock {
            filter: blur(2px);
            opacity: .4;
            pointer-events: none;
        }
    </style>
</head>

<body>
    <div class="main">
        <nav>
            <div>
                <a href="index.php" id="home">Home</a>
            </div>
            <div class="submit">
                <button type="button" id="login" class="active">Login</button>
                <button type="button" id="register" onclick="location.href='register.php'">Register</button>
            </div>
        </nav>
    </div>
    <div class="login-page">
        <div class="form-login">
            <?php if ($message != "") { ?>
                <div class="popup-bg">
                    <div class="popup">
                        <h3 id="popupMessage">
                            <?php echo $message; ?>
                        </h3>
                    </div>
                </div>
            <?php } ?>
            <form action="" method="POST">
                <input type="text" name="email" placeholder="Enter email or mobile number" required>
                <div class="lab-inp pass">
                    <input type="password" name="password" id="pas" placeholder="Enter password" required><span onclick="passView('pas', this)" aria-label="show or hide password"><i class="fa-solid fa-eye"></i></span>
                </div>
                <button type="submit" class="btn-login" id="loginSubmit">Login</button>
                <a href="forgot_password.php">Forgot password?</a> 
                <button type="button" id="registerBtn" class="btn-register" onclick="location.href='register.php'">Create New Account</button>
            </form>
        </div>
    </div>
    <footer class="last">
        <p>&copy;2026 Online Bus Ticket Booking System | All rights reserved.</p>
    </footer>
    <script>
        function passView(id, element) {
            const input = document.getElementById(id);
            const icon = element.querySelector("i");
            if (input.type == "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }
        <?php if ($message_type == "success") { ?>
            let randomTime = Math.floor(Math.random() * 5) + 1;
            setTimeout(function() {
                window.location.href = "<?php echo $page; ?>";
            }, randomTime * 1000);
        <?php } ?>
        <?php if ($message_type == "error" && !$locked) { ?>
            let wrongSec = 5;
            let popup = document.getElementById("popupMessage");
            let wrongTimer = setInterval(function() {
                if (popup) {
                    popup.innerHTML =
                        "Incorrect email or password!<br>Try again after " +
                        wrongSec + " seconds";
                }
                wrongSec--;
                if (wrongSec < 0) {
                    clearInterval(wrongTimer);
                    let box = document.querySelector(".popup-bg");
                    if (box) {
                        box.style.display = "none";
                    }
                }
            }, 1000);
        <?php } ?>
        <?php if ($locked) { ?>
            let lockSec = <?php echo $remaining; ?>;
            let lockMsg = document.getElementById("popupMessage");
            let lockTimer = setInterval(function() {
                if (lockMsg) {
                    lockMsg.innerHTML =
                        "Login locked.<br>Try again after " +
                        lockSec + " seconds";
                }
                lockSec--;
                if (lockSec < 0) {
                    clearInterval(lockTimer);
                    location.reload();
                }
            }, 1000);
            let loginBtn = document.getElementById("loginSubmit");
            let registerBtn = document.getElementById("registerBtn");
            if (loginBtn) {
                loginBtn.disabled = true;
                loginBtn.classList.add("blur-lock");
            }
            if (registerBtn) {
                registerBtn.disabled = true;
                registerBtn.classList.add("blur-lock");
            }
        <?php } ?>
    </script>
</body>

</html>