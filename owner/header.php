<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";

$owner_id = (int)$_SESSION['user_id'];

$ownerQuery = $conn->prepare("SELECT user_id,name,email,phone,profile_image,verification_status FROM users WHERE user_id=? AND role='owner' LIMIT 1");
$ownerQuery->bind_param("i", $owner_id);
$ownerQuery->execute();
$owner = $ownerQuery->get_result()->fetch_assoc();
$ownerQuery->close();

if (!$owner) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$owner_name = $owner['name'];
$owner_email = $owner['email'];
$verification_status = !empty($owner['verification_status']) ? $owner['verification_status'] : 'unverified';
$isVerified = $verification_status === 'verified';
$profile_image = !empty($owner['profile_image']) ? $owner['profile_image'] : '';

$verifyStmt = $conn->prepare("SELECT status,reject_reason FROM owner_verification WHERE owner_id=? ORDER BY verification_id DESC LIMIT 1");
$verifyStmt->bind_param("i", $owner_id);
$verifyStmt->execute();
$verify = $verifyStmt->get_result()->fetch_assoc();
$verifyStmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'Owner Dashboard' ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="owner.css">
    <?php if (isset($page_css)): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($page_css) ?>">
    <?php endif; ?>
    <style>
        .main {
            background: #1560BD;
            min-height: 65px;
            padding: 0 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            position: relative;
            z-index: 1000;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .nav-left a {
            color: #fff;
            text-decoration: none;
            padding: 10px 13px;
            border-radius: 5px;
            font-weight: bold;
            transition: .2s;
        }

        .nav-left a:hover {
            background: #0d4e9c;
        }

        .owner-right {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            position: relative;
            white-space: nowrap;
        }

        .owner-right h3 {
            margin: 0;
            font-size: 15px;
        }

        .profile-name {
            color: #fff;
        }

        .status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            text-transform: capitalize;
        }

        .status.verified {
            background: #dff6e5;
            color: #16803c;
        }

        .status.pending {
            background: #fff3cd;
            color: #856404;
        }

        .status.rejected {
            background: #ffe1e1;
            color: #d62828;
        }

        .status.unverified {
            background: #eee;
            color: #777;
        }

        .settings-menu {
            position: relative;
        }

        .image {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid #fff;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            transition: .2s;
        }

        .image:hover {
            transform: scale(1.05);
        }

        .nav-profile-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .dropdown {
            position: absolute;
            top: 54px;
            right: 0;
            width: 220px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, .18);
            padding: 8px 0;
            display: none;
            z-index: 9999;
        }

        .dropdown.show {
            display: block;
        }

        .dropdown a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 15px;
            color: #333;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: .2s;
        }

        .dropdown a i {
            width: 18px;
            color: #1560BD;
        }

        .dropdown a:hover {
            background: #f0f5ff;
            color: #1560BD;
        }

        .dropdown hr {
            border: 0;
            border-top: 1px solid #eee;
            margin: 6px 0;
        }

        .verify-banner {
            margin: 15px 25px;
            padding: 15px 20px;
            background: #fff3cd;
            border-radius: 7px;
            border-left: 6px solid #f0ad4e;
        }

        .verify-banner h2 {
            margin-bottom: 6px;
            color: #856404;
        }

        .verify-banner p {
            margin-bottom: 10px;
        }

        .verify-btn {
            display: inline-block;
            text-decoration: none;
            background: #1560BD;
            color: #fff;
            padding: 9px 15px;
            border-radius: 5px;
            font-weight: bold;
        }

        .verify-btn:hover {
            background: #0d4e9c;
        }

        @media(max-width:1000px) {
            .main {
                padding: 10px 15px;
                align-items: flex-start;
            }

            .owner-right h3 {
                display: none;
            }

            .nav-left a {
                padding: 8px 9px;
                font-size: 13px;
            }
        }

        @media(max-width:700px) {
            .main {
                flex-direction: column;
                align-items: stretch;
            }

            .nav-left {
                justify-content: center;
            }

            .owner-right {
                justify-content: flex-end;
            }

            .dropdown {
                right: 0;
            }
        }
    </style>
</head>

<body>

    <nav class="main">

        <div class="nav-left">
            <a href="dashboard.php">Home</a>

            <a href="<?= $isVerified ? 'register_bus.php' : '#' ?>"
                <?= !$isVerified ? 'onclick="alert(\'Please complete account verification first.\');return false;" style="opacity:.5;cursor:not-allowed;"' : '' ?>>
                Add Bus
            </a>

            <a href="<?= $isVerified ? 'my_bus.php' : '#' ?>"
                <?= !$isVerified ? 'onclick="alert(\'Please complete account verification first.\');return false;" style="opacity:.5;cursor:not-allowed;"' : '' ?>>
                My Bus
            </a>

            <a href="<?= $isVerified ? 'driver.php' : '#' ?>"
                <?= !$isVerified ? 'onclick="alert(\'Please complete account verification first.\');return false;" style="opacity:.5;cursor:not-allowed;"' : '' ?>>
                Driver
            </a>

            <a href="<?= $isVerified ? 'assign_driver.php' : '#' ?>"
                <?= !$isVerified ? 'onclick="alert(\'Please complete account verification first.\');return false;" style="opacity:.5;cursor:not-allowed;"' : '' ?>>
                Assign Driver
            </a>

            <a href="<?= $isVerified ? 'schedule.php' : '#' ?>"
                <?= !$isVerified ? 'onclick="alert(\'Please complete account verification first.\');return false;" style="opacity:.5;cursor:not-allowed;"' : '' ?>>
                Schedule
            </a>

            <a href="#aboutSection">About</a>
        </div>

        <div class="owner-right">

            <h3>
                Welcome,
                <span class="profile-name"><?= htmlspecialchars($owner_name) ?></span>
            </h3>

            <span class="status <?= htmlspecialchars(strtolower($verification_status)) ?>">
                <?= htmlspecialchars(ucfirst($verification_status)) ?>
            </span>

            <div class="settings-menu">

                <div class="image" id="profileImage" onclick="toggleMenu(event)">
                    <?php if (!empty($profile_image) && $profile_image !== 'default.png'): ?>
                        <img
                            src="../uploads/profile/<?= $owner_id ?>/profile/<?= htmlspecialchars($profile_image) ?>"
                            class="nav-profile-img"
                            alt="Profile">
                    <?php else: ?>
                        <img
                            src="../uploads/default.png"
                            class="nav-profile-img"
                            alt="Default Profile">
                    <?php endif; ?>
                </div>

                <div class="dropdown" id="dropdownMenu">

                    <a href="profile.php">
                        <i class="fa fa-user"></i>
                        <span>Profile</span>
                    </a>

                    <a href="edit_profile.php">
                        <i class="fa fa-edit"></i>
                        <span>Edit Profile</span>
                    </a>

                    <a href="verification.php">
                        <i class="fa fa-file-circle-check"></i>
                        <span>Verified Account</span>
                    </a>

                    <a href="../changepassword.php">
                        <i class="fa fa-key"></i>
                        <span>Change Password</span>
                    </a>

                    <hr>

                    <a href="../logout.php">
                        <i class="fa fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>

                </div>

            </div>

        </div>

    </nav>

    <?php if ($verification_status === 'rejected'): ?>

        <div class="verify-banner" style="border-left:6px solid red;">
            <h2>Verification Rejected</h2>

            <p>
                <b>Reason:</b><br>
                <?= htmlspecialchars($verify['reject_reason'] ?? 'No reason provided') ?>
            </p>

            <a href="verification.php" class="verify-btn">
                Submit Again
            </a>
        </div>

    <?php elseif (!$isVerified): ?>

        <div class="verify-banner">

            <p style="margin-bottom:10px;">
                Your account is not verified. Please complete verification to access all owner features.
            </p>

            <a href="verification.php" class="verify-btn">
                Complete Verification
            </a>

        </div>

    <?php endif; ?>

    <script>
        function toggleMenu(event) {
            event.stopPropagation();

            const dropdown = document.getElementById("dropdownMenu");

            dropdown.classList.toggle("show");
        }

        document.addEventListener("click", function(event) {
            const menu = document.querySelector(".settings-menu");
            const dropdown = document.getElementById("dropdownMenu");

            if (!menu.contains(event.target)) {
                dropdown.classList.remove("show");
            }
        });
    </script>