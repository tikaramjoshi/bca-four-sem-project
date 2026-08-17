<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'driver') {
    header("Location: ../login.php");
    exit;
}

$id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT name,email,phone,profile_image,verification_status FROM users WHERE user_id=? AND role='driver' LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$driver = $stmt->get_result()->fetch_assoc();

if (!$driver) {
    header("Location: dashboard.php");
    exit;
}

$stmt = $conn->prepare("SELECT profile_photo FROM driver_verification WHERE driver_id=? ORDER BY verification_id DESC LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$verification = $stmt->get_result()->fetch_assoc();

if (!empty($verification['profile_photo']) && file_exists("../uploads/driver/profile/" . $verification['profile_photo'])) {
    $image = "../uploads/driver/profile/" . $verification['profile_photo'];
} elseif (!empty($driver['profile_image']) && file_exists("../uploads/profile/" . $driver['profile_image'])) {
    $image = "../uploads/profile/" . $driver['profile_image'];
} else {
    $image = "../images/default.png";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Driver Profile</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial
        }

        body {
            background: #f4f7fb;
            padding: 40px
        }

        .profile {
            max-width: 500px;
            margin: auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 20px #ddd
        }

        .profile h2 {
            text-align: center;
            color: #1560bd;
            margin-bottom: 25px
        }

        .photo {
            text-align: center;
            margin-bottom: 25px
        }

        .photo img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #1560bd
        }

        .info {
            margin: 15px 0;
            padding: 12px;
            background: #f7f7f7;
            border-radius: 6px
        }

        .info span {
            display: block;
            color: #777;
            font-size: 13px;
            margin-bottom: 5px
        }

        .info strong {
            font-size: 16px
        }

        .status {
            color: #198754;
            font-weight: bold
        }

        .btn {
            display: block;
            text-align: center;
            text-decoration: none;
            background: #1560bd;
            color: #fff;
            padding: 12px;
            border-radius: 6px;
            margin-top: 20px
        }

        .btn:hover {
            background: #0d4d9b
        }
    </style>
</head>

<body>
    <div class="profile">
        <h2>Driver Profile</h2>
        <div class="photo">
            <img src="<?= htmlspecialchars($image) ?>" alt="Driver Profile" onerror="this.onerror=null;this.src='../images/default.png';">
        </div>
        <div class="info"><span>Name</span><strong><?= htmlspecialchars($driver['name']) ?></strong></div>
        <div class="info"><span>Email</span><strong><?= htmlspecialchars($driver['email']) ?></strong></div>
        <div class="info"><span>Phone</span><strong><?= htmlspecialchars($driver['phone']) ?></strong></div>
        <div class="info"><span>Verification Status</span><strong class="status"><?= ucfirst(htmlspecialchars($driver['verification_status'] ?? 'unverified')) ?></strong></div>
        <a href="dashboard.php" class="btn">Home</a>
    </div>
</body>

</html>