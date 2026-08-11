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
    $stmt = $conn->prepare("SELECT * FROM users WHERE email=? OR phone=?");
    $stmt->bind_param("ss", $login, $login);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    $stmt->close();
    if ($user && password_verify($password, $user['password'])) {

        $_SESSION['login_attempt'] = 0;

        unset($_SESSION['login_lock_time']);
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];


        if ($_SESSION['role'] == "admin") {
            $stmt = $conn->prepare("
        UPDATE users
        SET verification_status='verified'
        WHERE user_id=? AND verification_status='unverified'
    ");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
        }

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
    <link rel="stylesheet" href="login.css">
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
                        <h3 id="popupMessage"> <?php echo $message; ?> </h3>
                    </div>
                </div>
            <?php } ?>
            <form action="" method="POST">
                <input type="text" name="email" placeholder="Enter email or mobile number" required>
                <div class="lab-inp pass">
                    <input type="password" name="password" id="pas" placeholder="Enter password" required><span onclick="passView('pas', this)" aria-label="show or hide password"><i class="fa-solid fa-eye"></i></span>
                </div>
                <button type="submit" class="btn-login" id="loginSubmit">Login</button>
                <a href="email/forgot_password.php">Forgot password?</a>
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