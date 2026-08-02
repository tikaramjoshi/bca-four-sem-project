<?php
session_start();

if (
    !isset($_SESSION['otp_verified']) ||
    !isset($_SESSION['reset_email'])
) {
    header("Location: forgot_password.php");
    exit();
}

require_once "db.php";

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

        $stmt = $pdo->prepare("
            UPDATE users
            SET password=?
            WHERE email=?
        ");

        $stmt->execute([
            $hash,
            $_SESSION['reset_email']
        ]);

        unset($_SESSION['reset_email']);
        unset($_SESSION['reset_otp']);
        unset($_SESSION['otp_expire']);
        unset($_SESSION['otp_verified']);

        $_SESSION['success'] = "Password changed successfully.";

        header("Location: login.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">

<title>Reset Password</title>

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:Arial;
}

body{
background:#f4f7f6;
display:flex;
justify-content:center;
align-items:center;
height:100vh;
}

.box{
width:420px;
background:#fff;
padding:30px;
border-radius:10px;
box-shadow:0 0 15px rgba(0,0,0,.2);
}

h2{
text-align:center;
margin-bottom:20px;
color:#1560BD;
}

input{
width:100%;
padding:12px;
margin:12px 0;
border:1px solid #ccc;
border-radius:5px;
font-size:16px;
}

button{
width:100%;
padding:12px;
background:#1560BD;
color:#fff;
border:none;
border-radius:5px;
font-size:16px;
cursor:pointer;
}

button:hover{
background:#0d4d9c;
}

.success{
color:green;
text-align:center;
margin-bottom:15px;
font-weight:bold;
}

.error{
color:red;
text-align:center;
margin-bottom:15px;
font-weight:bold;
}

</style>

</head>

<body>

<div class="box">

<h2>Reset Password</h2>

<?php

if($message!=""){
echo "<div class='$message_type'>$message</div>";
}

?>

<form method="POST">

<input
type="password"
name="password"
placeholder="New Password"
required>

<input
type="password"
name="confirm_password"
placeholder="Confirm Password"
required>

<button type="submit">

Reset Password

</button>

</form>

</div>

</body>

</html>