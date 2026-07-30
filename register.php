<?php

require_once "db.php";

$role = "passenger";

$message = "";
$message_type = "";

if (isset($_GET['role'])) {
    $role = strtolower($_GET['role']);
}

$allowed_roles = ["passenger", "owner", "driver"];

if (!in_array($role, $allowed_roles)) {
    $role = "passenger";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    // $role = $_POST['role'];
    $role = strtolower($_POST['role'] ?? 'passenger');
    if (!in_array($role, $allowed_roles, true)) {
        $role = "passenger";
    }

    if ($password !== $confirm_password) {

        $message = "BOSS Password does not match!";
        $message_type = "error";
    } else {
        $emailCheck = $pdo->prepare("SELECT 1 FROM users WHERE email=?");
        $emailCheck->execute([$email]);
        $emailExists = $emailCheck->fetch();

        $phoneCheck = $pdo->prepare("SELECT 1 FROM users WHERE phone=?");
        $phoneCheck->execute([$phone]);
        $phoneExists = $phoneCheck->fetch();

        if ($emailExists && $phoneExists) {

            $message = "BOSS Email and Phone already registered!";
            $message_type = "error";
        } elseif ($emailExists) {

            $message = "BOSS Email already registered!";
            $message_type = "error";
        } elseif ($phoneExists) {

            $message = "BOSS Phone already registered!";
            $message_type = "error";
        } else {
            $password = password_hash($password, PASSWORD_BCRYPT);

            try {

                $query = "INSERT INTO users(name,email,phone,password,role)VALUES(:name,:email,:phone,:password,:role)";

                $stmt = $pdo->prepare($query);

                $stmt->execute([
                    ":name" => $name,
                    ":email" => $email,
                    ":phone" => $phone,
                    ":password" => $password,
                    ":role" => $role

                ]);

                $message = ucfirst($role) . " Registration Successful!";
                $message_type = "success";
            } catch (PDOException $e) {

                $message = "BOSS Something went wrong. Please try again.";
                error_log($e->getMessage());
                // $message = $e->getMessage();
                $message_type = "error";
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
    <title>
        <?php echo ($role == "passenger") ? "Register" : ucfirst($role) . " Register"; ?>
    </title>

    <link rel="stylesheet" href="register-style.css">
</head>

<body>
    <div class="main">
        <nav>
            <div>
                <a href="index.php">Home</a>
            </div>
            <div class="submit">
                <button type="button" id="login"
                    onclick="location.href='login.php'">Login</button>
                <button type="button" id="register" class="active">Register</button>
            </div>
        </nav>
    </div>
    <div class="register-page">
        <div class="from-register">
            <h2>
                <?php if ($role == "passenger") {
                    echo "Register";
                } else {
                    echo ucfirst($role) . " Register";
                } ?>
            </h2>
            <!-- <p class="role-title">Account Type: <strong>?php echo ucfirst($role); ?></strong></p> -->

            <form method="POST">
                <input type="hidden" name="role" value="<?php echo $role; ?>">
                <div class="lab-inp"> <input type="text" name="name" required> <label>Full Name</label></div>
                <div class="lab-inp"> <input type="email" name="email" required> <label>Email</label> </div>
                <div class="lab-inp"> <input type="tel" name="phone" required> <label>Phone Number</label></div>
                <div class="lab-inp"> <input type="password" name="password" required> <label>Password</label> </div>
                <div class="lab-inp"> <input type="password" name="confirm_password" required> <label>Confirm Password</label> </div>
                <button type="submit" class="btn-register">
                    Register
                </button>
                <a href="login.php">I already have an account?</a>
            </form>
        </div>
    </div>
    <footer class="last">
        <p>&copy;2026 Online Bus Ticket Booking System | All rights reserved.</p>
    </footer>

    <?php if ($message != "") { ?>
        <div class="popup-bg">
            <div class="popup">
                <h3>
                    <?php echo $message; ?>
                </h3>
                <button type="button" onclick="closePopup()">Close</button>
            </div>
        </div>
    <?php } ?>
</body>
<script>
    function closePopup() {
        document.querySelector(".popup-bg").style.display = "none";

        <?php if ($message_type == "success") { ?>
            window.location.href = "login.php";
        <?php } ?>

    }

    document.querySelectorAll(".lab-inp input").forEach(function(input) {

        input.addEventListener("focus", function() {
            this.nextElementSibling.classList.add("active");
        });

        input.addEventListener("blur", function() {
            if (this.value.trim() === "") {
                this.nextElementSibling.classList.remove("active");
            }
        });
    });

</script>

</html>