<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';

if (!$conn instanceof mysqli) {
    exit('Database connection is not available.');
}

$userId = (int)$_SESSION['user_id'];
$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$current || !$new || !$confirm) {
        $message = 'Please fill all fields.';
    } elseif ($new !== $confirm) {
        $message = 'New password and confirm password do not match.';
    } elseif (strlen($new) < 3) {
        $message = 'Password must be at least 6 characters.';
    } else {
        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id=? LIMIT 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || !password_verify($current, $user['password'])) {
            $message = 'Current password is incorrect.';
        } else {
            $password = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password=? WHERE user_id=?");
            $stmt->bind_param('si', $password, $userId);
            $stmt->execute();

            $message = 'Password changed successfully.';
            $success = true;
        }
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Change Password</title>
    <style>
        body {
            margin: 0;
            background: #f4f6f8;
            font-family: Arial, sans-serif;
            display: grid;
            place-items: center;
            min-height: 100vh;
        }

        .box {
            width: 350px;
            background: white;
            padding: 25px;
            border-radius: 10px;

        }

        h2 {
            text-align: center;
        }

        input {
            width: 100%;
            box-sizing: border-box;
            padding: 11px;
            margin: 8px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            width: 100%;
            padding: 11px;
            background: #0f766e;
            color: white;
            border: 0;
            border-radius: 5px;
            margin-top: 10px;
        }

        .message {
            text-align: center;
            margin-bottom: 12px;
            color: #b91c1c;
            padding: 12px;
            border-radius: 6px;
        }

        .success {
            background: #dcfce7;
            color: #15803d;
        }

        .close {
            display: block;
            margin: 12px auto 0;
            width: 80px;
            padding: 9px;
            background: #0f766e;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>

<body>
    <div class="box">
        <h2>Change Password</h2>

        <?php if ($message): ?>
            <div class="message <?= $success ? 'success' : '' ?>">
                <?= htmlspecialchars($message) ?>

                <?php if ($success): ?>
                    <a href="logout.php" class="close">Close</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="password" name="current_password" placeholder="Current Password" required>
            <input type="password" name="new_password" placeholder="New Password" required>
            <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
            <button type="submit">Change Password</button><br><br>
            <?php
            $role = $_SESSION['role'] ?? '';

            if ($role === 'passenger') {
                $dashboard = 'passenger/dashboard.php';
            } elseif ($role === 'admin') {
                $dashboard = 'admin/dashboard.php';
            } elseif ($role === 'owner') {
                $dashboard = 'owner/dashboard.php';
            } elseif ($role === 'driver') {
                $dashboard = 'driver/dashboard.php';
            } else {
                $dashboard = 'login.php';
            }
            ?>

            <a href="<?= htmlspecialchars($dashboard) ?>">Cancel</a>
        </form>
    </div>
</body>

</html>