<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "admin") {
    header("Location: ../login.php");
    exit();
}

require_once "../db.php";
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("User not found.");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile</title>
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
    </style>
</head>

<body>
    <div class="header">
        <h2>Admin Profile</h2>
        <a href="dashboard.php">Home</a>
    </div>
    <div class="container">
        <div class="profile-box">
            <h2>Welcome
                <?= htmlspecialchars($_SESSION['name']) ?>
            </h2>
            <div class="profile-img">
                <?php
                $image = !empty($user['profile_image']) ? $user['profile_image'] : 'default.png';
                ?>

                <img src="../uploads/profile/admin/<?= htmlspecialchars($user['name']) ?>/<?= htmlspecialchars($image) ?>"
                    alt="Profile">
            </div>
            <table>
                <tr>
                    <td>Name</td>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                </tr>
                <tr>
                    <td>Email</td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                </tr>
                <tr>
                    <td>Phone</td>
                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                </tr>
                <tr>
                    <td>Role</td>
                    <td><?php echo ucfirst($user['role']); ?></td>
                </tr>
                <tr>
                    <td>Created At</td>
                    <td><?php echo $user['created_at']; ?></td>
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