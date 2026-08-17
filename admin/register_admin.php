<?php

session_start();

require_once "../db.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$role = "admin";
$message = "";
$message_type = "";

if (isset($_SESSION['success'])) {
    $message = $_SESSION['success'];
    $message_type = "success";
    unset($_SESSION['success']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        $message = "Phone number must be exactly 10 digits!";
        $message_type = "error";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match!";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role='admin'");
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();
        if ($admin['total'] >= 3) {
            $message = "Maximum 3 admins are allowed!";
            $message_type = "error";
        } else {
            $stmt = $conn->prepare("
    SELECT email, phone 
    FROM users 
    WHERE email=? OR phone=?
");

            $stmt->bind_param("ss", $email, $phone);
            $stmt->execute();
            $result = $stmt->get_result();
            $email_exists = false;
            $phone_exists = false;
            while ($row = $result->fetch_assoc()) {
                if ($row['email'] == $email) {
                    $email_exists = true;
                }
                if ($row['phone'] == $phone) {
                    $phone_exists = true;
                }
            }
            if ($email_exists && $phone_exists) {
                $message = "Email and phone already exists!";
                $message_type = "error";
            } elseif ($email_exists) {
                $message = "Email already exists!";
                $message_type = "error";
            } elseif ($phone_exists) {
                $message = "Phone already exists!";
                $message_type = "error";
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("
                    INSERT INTO users(name,email,phone,password,role)
                    VALUES(?,?,?,?,?)
                ");
                $stmt->bind_param(
                    "sssss",
                    $name,
                    $email,
                    $phone,
                    $hash,
                    $role
                );
                if ($stmt->execute()) {

                    $message = "Admin Registration Successful!";
                    $message_type = "success";
                } else {

                    $message = "Something went wrong. Please try again.";
                    $message_type = "error";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Register</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="register_admin.css">

</head>

<body>
    <div class="main">
        <nav>
            <div>
                <a href="../index.php">Home</a>
            </div>
            <div class="submit">
                <button type="button" id="login"
                    onclick="location.href='../login.php'">
                    Login
                </button>
                <button type="button" id="register" class="active">Register</button>
            </div>
        </nav>
    </div>
    <div class="register-page">
        <div class="from-register">
            <h2>Admin Register</h2>
            <form method="POST">
                <div class="lab-inp">
                    <input type="text" name="name" placeholder="Enter your full name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>
                <div class="lab-inp">
                    <input type="email" name="email" placeholder="Enter your email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <div class="lab-inp">
                    <input type="text" name="phone" id="phone" maxlength="10" placeholder="Enter your phone number" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>
                <div class="lab-inp pass">
                    <input type="password" name="password" id="pas" placeholder="Enter your password" minlength="6" required>
                    <span class="pass_view" onclick="passView('pas',this)"> <i class="fa-solid fa-eye"></i> </span>
                </div>
                <div class="lab-inp pass">
                    <input type="password" name="confirm_password" id="c_pas" placeholder="Confirm password" minlength="6" required>
                    <span class="pass_view" onclick="passView('c_pas',this)"> <i class="fa-solid fa-eye"></i> </span>
                </div>
                <button type="submit" class="btn-register"> Register Admin </button>
                <a href="../login.php"> I already have an account? </a>
            </form>
        </div>
    </div>
    <footer class="last">
        <p>&copy;2026 Online Bus Ticket Booking System | All rights reserved.</p>
    </footer>
    <?php if ($message != "") { ?>
        <div class="popup-bg">
            <div class="popup">
                <h3><?php echo $message; ?></h3>
                <button type="button" onclick="closePopup()"> Close </button>
            </div>
        </div>
    <?php } ?>
    <script>
        function closePopup() {

            <?php if ($message_type == "success") { ?>
                window.location.href = "../login.php";
            <?php } else { ?>
                document.querySelector(".popup-bg").style.display = "none";
            <?php } ?>
        }
        const phone = document.getElementById("phone");
        phone.addEventListener("input", function() {
            this.value = this.value.replace(/[^0-9]/g, "").slice(0, 10);
        });

        function passView(id, element) {
            let input = document.getElementById(id);
            let icon = element.querySelector("i");
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }
    </script>
</body>

</html>