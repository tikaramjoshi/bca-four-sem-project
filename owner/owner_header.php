<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "owner") {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";
include "../post_popup.php";
$owner_id = (int)$_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);
$ownerQuery = $conn->prepare("SELECT users.name,users.email,users.verification_status,owner_verification.owner_photo FROM users LEFT JOIN owner_verification ON users.user_id=owner_verification.owner_id WHERE users.user_id=? ORDER BY owner_verification.verification_id DESC LIMIT 1");
$ownerQuery->bind_param("i", $owner_id);
$ownerQuery->execute();
$result = $ownerQuery->get_result();
$owner = $result->fetch_assoc();
$ownerQuery->close();
$owner_name = $owner['name'];
$owner_email = $owner['email'];
$verification_status = $owner['verification_status'];
$isVerified = ($verification_status === "verified");
$profile_image = $owner['owner_photo'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Owner Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="owner.css">
</head>

<body>
    <nav class="main">
        <div class="nav-left">
            <a href="dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">Home</a>
            <a href="<?= $isVerified ? 'register_bus.php' : '#' ?>" class="<?= $current_page == 'register_bus.php' ? 'active' : '' ?>">Add Bus</a>
            <a href="<?= $isVerified ? 'my_bus.php' : '#' ?>" class="<?= $current_page == 'my_bus.php' ? 'active' : '' ?>">My Bus</a>
            <a href="<?= $isVerified ? 'driver.php' : '#' ?>" class="<?= $current_page == 'driver.php' ? 'active' : '' ?>">Driver</a>
            <a href="<?= $isVerified ? 'assign_driver.php' : '#' ?>" class="<?= $current_page == 'assign_driver.php' ? 'active' : '' ?>">Assign Driver</a>
            <a href="<?= $isVerified ? 'schedule.php' : '#' ?>" class="<?= $current_page == 'schedule.php' ? 'active' : '' ?>">Schedule</a>
            <a href="#aboutSection">About</a>
        </div>
        <div style="display:flex;gap:10px;color:white;align-items:center">
            <h3>Welcome, <span class="profile-name"><?= htmlspecialchars($owner_name) ?></span></h3>
            <span class="status <?= strtolower($verification_status) ?>"><?= htmlspecialchars(ucfirst($verification_status)) ?></span>
            <div class="settings-menu">
                <div class="image" onclick="toggleMenu()">
                    <?php if (!empty($profile_image)) { ?>
                        <img src="../uploads/profile/<?= $owner_id ?>/profile/<?= htmlspecialchars($profile_image) ?>" class="nav-profile-img" alt="Profile">
                    <?php } else { ?>
                        <img src="../uploads/default.png" class="nav-profile-img" alt="Default Profile">
                    <?php } ?>
                </div>
                <div class="dropdown" id="dropdownMenu">
                    <a href="profile.php"><i class="fa fa-user"></i>Profile</a>
                    <a href="edit_profile.php"><i class="fa fa-edit"></i>Edit Profile</a>
                    <a href="verification.php"><i class="fa fa-file"></i>Verified Account</a>
                    <a href="../changepassword.php"><i class="fa fa-key"></i>Change Password</a>
                    <hr>
                    <a href="../logout.php"><i class="fa fa-sign-out-alt"></i>Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <script>
        function toggleMenu() {
            document.getElementById("dropdownMenu").classList.toggle("show");
        }
        window.onclick = function(e) {
            if (!e.target.closest(".settings-menu")) {
                document.getElementById("dropdownMenu").classList.remove("show");
            }
        };
    </script>