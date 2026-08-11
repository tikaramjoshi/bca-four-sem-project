<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "passenger") {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name, email, phone, profile_image, role, verification_status, created_at FROM users WHERE user_id=? AND role='passenger'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}
$image = !empty($user['profile_image']) ? $user['profile_image'] : 'default.png';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f4f7f6;
        }

        .header {
            background: #1560BD;
            color: #fff;
            padding: 18px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h2 {
            font-size: 20px;
        }

        .header a {
            text-decoration: none;
            color: #fff;
            background: #bd8815;
            padding: 10px 18px;
            border-radius: 5px;
        }

        .container {
            width: 550px;
            max-width: 95%;
            margin: 40px auto;
        }

        .profile-box {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 0 10px rgba(0, 0, 0, .12);
        }

        .profile-box h2 {
            text-align: center;
            color: #1560BD;
            margin-bottom: 20px;
        }

        .profile-img {
            text-align: center;
            margin-bottom: 20px;
        }

        .profile-img img {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #1560BD;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }

        td:first-child {
            width: 180px;
            font-weight: bold;
        }

        .status {
            font-weight: bold;
        }

        .status.verified {
            color: #28a745;
        }

        .status.pending {
            color: #f39c12;
        }

        .status.rejected {
            color: #dc3545;
        }

        .btns {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }

        .btn {
            text-decoration: none;
            color: #fff;
            padding: 10px 18px;
            border-radius: 5px;
        }

        .edit {
            background: #1560BD;
        }

        .back {
            background: #28a745;
        }

        .btn:hover {
            opacity: .9;
        }
    </style>
</head>

<body>
    <div class="header">
        <h2>My Profile</h2>
        <a href="dashboard.php">Dashboard</a>
    </div>
    <div class="container">
        <div class="profile-box">
            <h2>Welcome</h2>
            <div class="profile-img">
                <img src="../uploads/profile/passenger/<?= htmlspecialchars($user['name']) ?>/<?= htmlspecialchars($image) ?>" alt="Profile">
            </div>
            <table>
                <tr>
                    <td>Name</td>
                    <td><?= htmlspecialchars($user['name']) ?></td>
                </tr>
                <tr>
                    <td>Email</td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                </tr>
                <tr>
                    <td>Phone</td>
                    <td><?= htmlspecialchars($user['phone']) ?></td>
                </tr>
                <tr>
                    <td>Role</td>
                    <td><?= htmlspecialchars(ucfirst($user['role'])) ?></td>
                </tr>
                <tr>
                    <td>Verification</td>
                    <td class="status <?= strtolower($user['verification_status']) ?>">
                        <?= htmlspecialchars(ucfirst($user['verification_status'])) ?>
                    </td>
                </tr>
                <tr>
                    <td>Created At</td>
                    <td><?= htmlspecialchars($user['created_at']) ?></td>
                </tr>
            </table>
            <div class="btns">
                <a class="btn edit" href="edit_profile.php">Edit Profile</a>
                <a class="btn back" href="dashboard.php">Back</a>
            </div>
        </div>
    </div>
</body>

</html>