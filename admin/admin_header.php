<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once "../db.php";

$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name, profile_image FROM users WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

$admin_name = $admin['name'] ?? 'Admin';
$profile_image = !empty($admin['profile_image']) ? $admin['profile_image'] : "default.png";

$active_page = $active_page ?? 'dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="dashboard_admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <title>Admin Panel</title>
</head>

<body>

    <div class="header">
        <h2>Welcome Admin</h2>
        <div class="setting">
            <strong><?= htmlspecialchars($admin_name) ?></strong>
            <img src="../uploads/profile/admin/<?= htmlspecialchars($admin_name) ?>/<?= htmlspecialchars($profile_image) ?>" alt="Profile" class="setting-profile" onclick="toggleMenu()" onerror="this.onerror=null;this.src='../uploads/default.png';">
            <div class="setting-menu" id="settingMenu">
                <a href="profile.php"><i class="fa fa-user"></i> My Profile</a>
                <a href="edit_profile.php"><i class="fa fa-edit"></i> Edit Profile</a>
                <a href="policy.php"><i class="fa fa-file"></i> Manage Policy</a>
                <a href="../changepassword.php"><i class="fa fa-key"></i> Change Password</a>
                <hr>
                <a href="../logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

    <div class="container">

        <div class="sidebar">
            <a href="dashboard.php" class="<?= $active_page === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
            <a href="view_owners.php" class="<?= $active_page === 'view_owners.php' ? 'active' : '' ?>">Owners</a>
            <a href="drivers.php" class="<?= $active_page === 'drivers.php' ? 'active' : '' ?>">Drivers</a>
            <a href="passengers.php" class="<?= $active_page === 'passengers.php' ? 'active' : '' ?>">Passengers</a>
            <a href="all_bus.php" class="<?= $active_page === 'all_bus.php' ? 'active' : '' ?>">Buses</a>
            <a href="assign_driver.php" class="<?= $active_page === 'assign_driver.php' ? 'active' : '' ?>">Assign Driver</a>
            <a href="routes.php" class="<?= $active_page === 'routes.php' ? 'active' : '' ?>">Routes</a>
            <a href="schedule.php" class="<?= $active_page === 'schedule.php' ? 'active' : '' ?>">Schedule</a>
            <a href="bookings.php" class="<?= $active_page === 'bookings.php' ? 'active' : '' ?>">Bookings</a>
            <a href="popular_routes.php" class="<?= $active_page === 'popular_routes.php' ? 'active' : '' ?>">Popular Route</a>
            <a href="uploads.php" class="<?= $active_page === 'uploads.php' ? 'active' : '' ?>">Images</a>
        </div>

        <script>
            function toggleMenu() {
                document.getElementById("settingMenu").classList.toggle("show");
            }
            window.addEventListener("click", function(e) {
                if (!e.target.closest(".setting")) {
                    document.getElementById("settingMenu").classList.remove("show");
                }
            });
        </script>
        <!-- <script>
            function toggleSetting() {
                document.getElementById("settingMenu").classList.toggle("show");
            }

            document.addEventListener("click", function(e) {
                const setting = document.querySelector(".setting");
                const menu = document.getElementById("settingMenu");
                if (!setting.contains(e.target)) {
                    menu.classList.remove("show");
                }
            });
        </script> -->