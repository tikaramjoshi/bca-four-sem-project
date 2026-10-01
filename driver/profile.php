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
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .profile {
            width: 90%;
            max-width: 900px;
            margin: 40px auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-sizing: border-box;
        }

        .profile h2 {
            text-align: center;
            color: #1560bd;
            margin: 0 0 25px;
        }

        .photo {
            text-align: center;
            margin-bottom: 20px;
        }

        .photo>img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #1560bd;
        }

        .profile-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .info {
            margin: 0;
            padding: 13px 15px;
            background: #f7f7f7;
            border-radius: 6px;
            box-sizing: border-box;
        }

        .info span {
            display: block;
            color: #777;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .info strong {
            display: block;
            font-size: 16px;
            color: #222;
            word-break: break-word;
        }

        .status {
            color: #f39c12 !important;
            font-weight: bold;
        }

        .license {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

        .license h3 {
            color: #1560bd;
            margin: 0 0 15px;
        }

        .license-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .license-info .full {
            grid-column: 1 / 3;
        }

        .license-images {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }

        .license-images .photo {
            margin: 0;
            text-align: left;
        }

        .license-images .photo strong {
            display: block;
            font-size: 15px;
            color: #555;
            margin-bottom: 8px;
        }

        .license-images .photo img {
            display: block;
            width: 100%;
            height: 240px;
            object-fit: cover;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f7f7f7;
        }

        .btn {
            margin-top: 20px;
            text-align: center;
        }

        .btn a {
            display: inline-block;
            padding: 10px 20px;
            background: #1560bd;
            color: #fff;
            text-decoration: none;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
        }

        .btn a:hover {
            background: #0d4d9c;
        }

        @media (max-width: 600px) {
            .profile {
                width: 95%;
                margin: 20px auto;
                padding: 20px;
            }

            .profile-info {
                grid-template-columns: 1fr;
            }

            .license-info {
                grid-template-columns: 1fr;
            }

            .license-info .full {
                grid-column: 1;
            }

            .license-images {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .license-images .photo img {
                height: 200px;
            }
        }
    </style>
</head>

<body>
    <?php include "dri_header.php"; ?>

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
                <a href="dashboard.php">Back</a> <a href="edit.php">Edit Profile</a> <a href="../role_request.php">Change Role </a>
            </div>
        </div>
    </div>

</body>

</html>