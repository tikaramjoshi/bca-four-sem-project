<?php
session_start();
require_once '../db.php';
require_once '../mail_config.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: /sl_project_final/login.php");
    exit;
}
$message = '';
$error = '';
$selected_user = null;
if (isset($_SESSION['success'])) {
    $message = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
if (isset($_GET['verify']) && $_GET['verify'] == '1') {
    if (
        !isset($_SESSION['admin_reset_otp_verified']) ||
        $_SESSION['admin_reset_otp_verified'] !== true
    ) {
        $_SESSION['error'] = "OTP verification is required.";
        header("Location: /sl_project_final/admin/reset_password.php");
        exit;
    }
    $reset_user_id = (int)($_SESSION['admin_reset_user_id'] ?? 0);
    if ($reset_user_id <= 0) {
        $_SESSION['error'] = "Reset user information not found.";
        header("Location: /sl_project_final/admin/reset_password.php");
        exit;
    }
    $stmt = mysqli_prepare(
        $conn,
        "SELECT user_id, name, email, role FROM users WHERE user_id = ?"
    );
    mysqli_stmt_bind_param($stmt, "i", $reset_user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $reset_user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if (!$reset_user) {
        $_SESSION['error'] = "User not found.";
        header("Location: /sl_project_final/admin/reset_password.php");
        exit;
    }
    $new_password = password_hash('123456', PASSWORD_DEFAULT);
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE users SET password = ? WHERE user_id = ?"
    );
    mysqli_stmt_bind_param($stmt, "si", $new_password, $reset_user_id);
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        $mail = new PHPMailer(true);
        $mail_sent = false;
        try {
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = MAIL_PORT;
            $mail->setFrom(
                MAIL_USERNAME,
                "Online Bus Ticket Booking System"
            );
            $mail->addAddress(
                $reset_user['email'],
                $reset_user['name']
            );
            $mail->isHTML(true);
            $mail->Subject = "Password Reset Notification";
            $mail->Body = "
            <div style='font-family:Arial,sans-serif;padding:20px;line-height:1.6'>
                <h2 style='color:#1560BD'>
                    Online Bus Ticket Booking System
                </h2>
                <p>
                    Hello <b>" . htmlspecialchars($reset_user['name']) . "</b>,
                </p>
                <p>
                    Your password has been reset by the <b>Online Bus Ticket Booking System Administrator.</b>
                </p>
                <p>
                    <b>New Password:</b>
                    <span style='font-size:18px;color:#1560BD'>
                        123456
                    </span>
                </p>
                <p>
                    You can now login using this new password.
                </p>
                <p>
                    For security, please change your password after logging in.
                </p>
                <br>
                <p>Thank you.</p>
                <p>
                    <b>Online Bus Ticket Booking System</b>
                </p>
            </div>
            ";
            $mail->send();
            $mail_sent = true;
        } catch (Exception $e) {
            $mail_sent = false;
        }
        if ($mail_sent) {
            $_SESSION['success'] =
                "Password for " . $reset_user['name'] .
                " has been reset successfully and the new password has been sent to the user's email.";
        } else {
            $_SESSION['success'] =
                "Password for " . $reset_user['name'] .
                " has been reset successfully, but the notification email could not be sent.";
        }
        $_SESSION['user_reset_notification'] =
            "Your password has been reset by Admin.";
        unset($_SESSION['admin_reset_otp_verified']);
        unset($_SESSION['reset_otp']);
        unset($_SESSION['otp_expire']);
        unset($_SESSION['reset_email']);
        unset($_SESSION['admin_reset_mode']);
        unset($_SESSION['admin_reset_email']);
        header("Location: /sl_project_final/admin/reset_password.php");
        exit;
    } else {
        mysqli_stmt_close($stmt);
        $_SESSION['error'] = "Failed to reset password.";
        header("Location: /sl_project_final/admin/reset_password.php");
        exit;
    }
}
if (isset($_GET['user_id'])) {
    $user_id = (int)$_GET['user_id'];
    $stmt = mysqli_prepare(
        $conn,
        "SELECT user_id, name, email, role FROM users WHERE user_id = ?"
    );
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $selected_user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if (!$selected_user) {
        $error = "User not found.";
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $stmt = mysqli_prepare(
        $conn,
        "SELECT user_id, name, email, role FROM users WHERE user_id = ?"
    );
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $selected_user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if (!$selected_user) {
        $error = "User not found.";
    } else {
        $admin_id = (int)$_SESSION['user_id'];
        $stmt = mysqli_prepare(
            $conn,
            "SELECT name, email FROM users WHERE user_id = ? AND role = 'admin'"
        );
        mysqli_stmt_bind_param($stmt, "i", $admin_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $admin = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        if (!$admin) {
            $error = "Admin account not found.";
        } else {
            $_SESSION['admin_reset_mode'] = true;
            $_SESSION['admin_reset_user_id'] = $selected_user['user_id'];
            $_SESSION['admin_reset_user_name'] = $selected_user['name'];
            $_SESSION['admin_reset_user_email'] = $selected_user['email'];
            $_SESSION['admin_reset_user_role'] = $selected_user['role'];
            $_SESSION['admin_reset_email'] = $admin['email'];
            $_SESSION['reset_email'] = $admin['email'];
            unset($_SESSION['admin_reset_otp_verified']);
            unset($_SESSION['reset_otp']);
            unset($_SESSION['otp_expire']);
            header("Location: /sl_project_final/email/send_otp.php?admin_reset=1");
            exit;
        }
    }
}
$users = mysqli_query(
    $conn,
    "SELECT user_id, name, email, role, verification_status
     FROM users
     ORDER BY user_id ASC"
);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Admin</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #222;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 25px;
        }

        .card {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .card h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 21px;
        }

        .btn {
            border: none;
            padding: 10px 17px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }

        .btn-reset {
            background: #1560bd;
            color: white;
        }

        .btn-reset:hover {
            background: #0f4f9d;
        }

        .btn-cancel {
            background: #777;
            color: white;
            margin-left: 8px;
        }

        .alert {
            padding: 13px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .success {
            background: #dff5e4;
            color: #176b2c;
        }

        .error {
            background: #fde2e2;
            color: #a51d1d;
        }

        .user-info {
            background: #f4f7fb;
            padding: 15px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .user-info p {
            margin: 7px 0;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th,
        table td {
            padding: 13px 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        table th {
            background: #1560bd;
            color: white;
        }

        table tr:hover {
            background: #f7f9fc;
        }

        .role {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .role-admin {
            background: #eadcff;
            color: #6420a8;
        }

        .role-owner {
            background: #dceeff;
            color: #125a9c;
        }

        .role-driver {
            background: #fff0c7;
            color: #805b00;
        }

        .role-passenger {
            background: #dff5e4;
            color: #176b2c;
        }

        @media (max-width: 900px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }
    </style>
</head>

<body>
    <?php include "admin_header.php"; ?>
    <div class="main-content">
        <div class="page-title">Reset User Password</div>
        <?php if ($message): ?>
            <div class="alert success">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <?php if ($selected_user && isset($_GET['user_id'])): ?>
            <div class="card">
                <h2> Reset Password for </h2>
                <div class="user-info">
                    <p> <strong>User ID:</strong> <?php echo htmlspecialchars($selected_user['user_id']); ?> </p>
                    <p>
                        <strong>Name:</strong>
                        <?php echo htmlspecialchars($selected_user['name']); ?>
                    </p>
                    <p>
                        <strong>Email:</strong>
                        <?php echo htmlspecialchars($selected_user['email']); ?>
                    </p>
                    <p>
                        <strong>Role:</strong>
                        <?php echo htmlspecialchars($selected_user['role']); ?>
                    </p>
                </div>
                <form method="POST">
                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($selected_user['user_id']); ?>">
                    <!--
                OLD PASSWORD INPUT CODE
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" required minlength="6">
                </div>
                -->
                    <button type="submit" class="btn btn-reset"> Reset Password </button>
                    <a href="/sl_project_final/admin/reset_password.php" class="btn btn-cancel"> Cancel </a>
                </form>
            </div>
        <?php endif; ?>
        <div class="card">
            <h2>All Users</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users && mysqli_num_rows($users) > 0): ?>
                            <?php while ($user = mysqli_fetch_assoc($users)): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($user['user_id']); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($user['name']); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </td>
                                    <td>
                                        <span class="role role-<?php echo htmlspecialchars($user['role']); ?>">
                                            <?php echo htmlspecialchars($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($user['verification_status']); ?>
                                    </td>
                                    <td>
                                        <a href="/sl_project_final/admin/reset_password.php?user_id=<?php echo $user['user_id']; ?>" class="btn btn-reset"> Reset Password </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">No users found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>