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


    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        $message = "BOSS Phone number must be exactly 10 digits!";
        $message_type = "error";
    } elseif ($password !== $confirm_password) {

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

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

            <form method="POST">
                <input type="hidden" name="role" value="<?php echo $role; ?>">
                <div class="lab-inp"><input type="text" name="name" placeholder="Enter your full name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"></div>
                <div class="lab-inp"><input type="email" name="email" placeholder="Enter your email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"> </div>
                <div class="lab-inp"><input type="text" name="phone" placeholder="Enter your phone number" maxlength="10" id="phone" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"></div>
                <div class="lab-inp pass"><input type="password" name="password" id="pas" placeholder="Enter your password" required><span class="pass_view" onclick="passView('pas', this)"><i class="fa-solid fa-eye"></i></span></div>
                <div class="lab-inp pass"><input type="password" name="confirm_password" id="c_pas" placeholder="Confirm password" required><span class="pass_view" onclick="passView('c_pas', this)"><i class="fa-solid fa-eye"></i></span> </div>
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
                 <p id="countdown" style="margin-top:15px;color:#1560BD;font-weight:bold;">
            Closing in 3s...
        </p>
            </div>
        </div>
    <?php } ?>

    <script>
function closePopup(){

    const popup = document.querySelector(".popup-bg");

    if(popup){
        popup.style.display = "none";
    }

    <?php if($message_type=="success"){ ?>
        window.location.href = "login.php";
    <?php } ?>

}

<?php if($message != "") { ?>


let timeLeft = 3;

const countdown = document.getElementById("countdown");

const timer = setInterval(function () {

    timeLeft--;

    if (timeLeft > 0) {

        countdown.innerHTML = "Closing in " + timeLeft + "s...";

    } else {

        clearInterval(timer);

        closePopup();

    }

}, 1000);

<?php } ?>

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