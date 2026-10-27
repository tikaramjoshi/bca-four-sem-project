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
$stmt = $conn->prepare("SELECT profile_photo,license_number,license_issue_date,license_expiry_date,license_photo_front,license_photo_back FROM driver_verification WHERE driver_id=? ORDER BY verification_id DESC LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$verification = $stmt->get_result()->fetch_assoc();
$license_number = $verification['license_number'] ?? '';
$license_issue_date = $verification['license_issue_date'] ?? '';
$license_expiry_date = $verification['license_expiry_date'] ?? '';
if (!empty($verification['profile_photo']) && file_exists("../uploads/driver/profile/" . $verification['profile_photo'])) {
    $image = "../uploads/driver/profile/" . $verification['profile_photo'];
} elseif (!empty($driver['profile_image']) && file_exists("../uploads/profile/" . $driver['profile_image'])) {
    $image = "../uploads/profile/" . $driver['profile_image'];
} else {
    $image = "../images/default.png";
}
$license_front = !empty($verification['license_photo_front']) && file_exists("../uploads/driver/license/" . $verification['license_photo_front']) ? "../uploads/driver/license/" . $verification['license_photo_front'] : "../images/default.png";
$license_back = !empty($verification['license_photo_back']) && file_exists("../uploads/driver/license/" . $verification['license_photo_back']) ? "../uploads/driver/license/" . $verification['license_photo_back'] : "../images/default.png";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Driver Profile</title>
    <link rel="stylesheet" href="profile.css">
</head>

<body>
    <?php include "driver_header.php"; ?>
    <div class="container">
        <div class="profile">
            <h2>Driver Profile</h2>
            <div class="photo">
                <img src="<?= htmlspecialchars($image) ?>" alt="Driver Profile" onerror="this.onerror=null;this.src='../images/default.png';">
                <br><br>
                <span>Name:</span>
                <strong><?= htmlspecialchars($driver['name']) ?></strong>
                <br>
                <span>Verification Status</span>
                <strong class="status"><?= ucfirst(htmlspecialchars($driver['verification_status'] ?? 'unverified')) ?></strong>
            </div>
            <div class="profile-info">
                <div class="info">
                    <strong>Email: <?= htmlspecialchars($driver['email']) ?></strong>
                </div>
                <div class="info">
                    <strong>Phone: <?= htmlspecialchars($driver['phone']) ?></strong>
                </div>
            </div>
            <div class="license">
                <h3>Driving License</h3>
                <div class="license-info">
                    <div class="info full">
                        <strong>License Number: <?= htmlspecialchars($license_number ?: 'Not Available') ?></strong>
                    </div>
                    <div class="info">
                        <strong>Issue Date: <?= htmlspecialchars($license_issue_date ?: 'Not Available') ?></strong>
                    </div>
                    <div class="info">
                        <strong>Expiry Date: <?= htmlspecialchars($license_expiry_date ?: 'Not Available') ?></strong>
                    </div>
                </div>
                <div class="license-images">
                    <div class="photo">
                        <strong>License Front</strong>
                        <img src="<?= htmlspecialchars($license_front) ?>" alt="License Front" onerror="this.onerror=null;this.src='../images/default.png';">
                    </div>
                    <div class="photo">
                        <strong>License Back</strong>
                        <img src="<?= htmlspecialchars($license_back) ?>" alt="License Back" onerror="this.onerror=null;this.src='../images/default.png';">
                    </div>
                </div>
            </div>
            <div class="btn">
                <a href="dashboard.php"><i class="fa fa-arrow-left"></i> &nbsp;Back</a>
                <a href="edit.php"><i class="fa fa-edit"></i> &nbsp;Edit Profile</a>
                <a href="../role_request.php"><i class="fa fa-exchange"></i> &nbsp;Change Role</a>
            </div>
        </div>
    </div>
</body>

</html>