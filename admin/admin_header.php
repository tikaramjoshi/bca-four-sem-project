<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit;
}
require_once "../db.php";

$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name,profile_image FROM users WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

$admin_name = $admin['name'] ?? 'Admin';
$profile_image = !empty($admin['profile_image']) ? $admin['profile_image'] : 'default.png';
$current_page = basename($_SERVER['PHP_SELF']);
$active_page = $active_page ?? $current_page;
$booking_active = in_array($current_page, ['bookings.php', 'booking_history.php'], true);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <link rel="stylesheet" href="dashboard_admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <title>Admin Panel</title>
</head>

<body>

    <div class="header">
        <h2>Welcome Admin</h2>
        <div class="setting">
            <strong><?= htmlspecialchars($admin_name) ?></strong>
            <img src="../uploads/profile/admin/<?= htmlspecialchars($admin_name) ?>/<?= htmlspecialchars($profile_image) ?>" class="setting-profile" alt="Profile" onclick="toggleMenu()" onerror="this.onerror=null;this.src='../uploads/default.png';">
            <div class="setting-menu" id="settingMenu">
                <a href="profile.php"><i class="fa fa-user"></i> My Profile</a>
                <a href="edit_profile.php"><i class="fa fa-edit"></i> Edit Profile</a>
                <a href="policy.php"><i class="fa fa-file"></i> Manage Policy</a>
                <a href="../changepassword.php"><i class="fa fa-key"></i> Change Password</a>
                <a href="manage_users.php"><i class=" fa fa-user-cog"></i> Manage Users</a>
                <hr>
                <a href="../logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <a href="dashboard.php" class="<?= $active_page === 'dashboard.php' ? 'active' : '' ?>"> <i class="fa fa-dashboard"></i>&nbsp;&nbsp;Dashboard</a>

            <a href="bookings.php" class="<?= $active_page === 'bookings.php' ? 'active' : '' ?>"><i class="fa fa-ticket"></i>&nbsp;&nbsp;Bookings</a>

            <a href="view_owners.php" class="<?= $active_page === 'view_owners.php' ? 'active' : '' ?>"> <i class="fa fa-briefcase"></i>&nbsp;&nbsp;Owners</a>

            <a href="drivers.php" class="<?= $active_page === 'drivers.php' ? 'active' : '' ?>"><i class="fa fa-id-card-o"></i>&nbsp;&nbsp;Drivers</a>

            <a href="passengers.php" class="<?= $active_page === 'passengers.php' ? 'active' : '' ?>"><i class="fa fa-users"></i>&nbsp;&nbsp;Passengers</a>

            <a href="all_bus.php" class="<?= $active_page === 'all_bus.php' ? 'active' : '' ?>"><i class="fa fa-bus"></i>&nbsp;&nbsp;Buses</a>

            <a href="change_role.php" class="<?= $active_page === 'change_role.php' ? 'active' : '' ?>"><i class="fa fa-exchange"></i>&nbsp;&nbsp;Change Role</a>

            <a href="assign_driver.php" class="<?= $active_page === 'assign_driver.php' ? 'active' : '' ?>"><i class="fa fa-user-plus"></i>&nbsp;&nbsp;Assign Driver</a>

            <a href="routes.php" class="<?= $active_page === 'routes.php' ? 'active' : '' ?>"><i class="fa fa-map-signs"></i>&nbsp;&nbsp;Routes</a>

            <a href="schedule.php" class="<?= $active_page === 'schedule.php' ? 'active' : '' ?>"><i class="fa fa-calendar"></i>&nbsp;&nbsp;Schedule</a>

            <a href="popular_routes.php" class="<?= $active_page === 'popular_routes.php' ? 'active' : '' ?>"><i class="fa fa-line-chart"></i>&nbsp;&nbsp;Popular Route</a>

            <a href="view_post.php" class="<?= $active_page === 'view_post.php' ? 'active' : '' ?>"><i class="fa fa-pencil-square-o"></i>&nbsp;&nbsp;Add Post</a>

            <a href="uploads.php" class="<?= $active_page === 'uploads.php' ? 'active' : '' ?>"><i class="fa fa-picture-o"></i>&nbsp;&nbsp;Images</a>

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