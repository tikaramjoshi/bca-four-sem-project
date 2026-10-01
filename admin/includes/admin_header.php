<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<header class="header">
    <h2>Welcome Admin</h2>

    <div class="setting">
        <strong><?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?></strong>
        <img src="../uploads/profile/<?= htmlspecialchars($_SESSION['profile_image'] ?? 'default.png') ?>" class="setting-profile" onclick="toggleSetting()" onerror="this.onerror=null;this.src='../images/default.png';">

        <div class="setting-menu" id="settingMenu">
            <a href="profile.php">Profile</a>
            <a href="edit_profile.php">Edit Profile</a>
            <hr>
            <a href="../logout.php">Logout</a>
        </div>
    </div>
</header>

<nav class="sidebar">
    <a href="dashboard.php" class="<?= $current_page === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
    <a href="owners.php" class="<?= $current_page === 'owners.php' ? 'active' : '' ?>">Owners</a>
    <a href="drivers.php" class="<?= $current_page === 'drivers.php' ? 'active' : '' ?>">Drivers</a>
    <a href="passengers.php" class="<?= $current_page === 'passengers.php' ? 'active' : '' ?>">Passengers</a>
    <a href="buses.php" class="<?= $current_page === 'buses.php' ? 'active' : '' ?>">Buses</a>
    <a href="assign_driver.php" class="<?= $current_page === 'assign_driver.php' ? 'active' : '' ?>">Assign Driver</a>
    <a href="routes.php" class="<?= $current_page === 'routes.php' ? 'active' : '' ?>">Routes</a>
    <a href="schedule.php" class="<?= $current_page === 'schedule.php' ? 'active' : '' ?>">Schedule</a>
    <a href="bookings.php" class="<?= $current_page === 'bookings.php' ? 'active' : '' ?>">Bookings</a>
    <a href="popular_route.php" class="<?= $current_page === 'popular_route.php' ? 'active' : '' ?>">Popular Route</a>
    <a href="uploads.php" class="<?= $current_page === 'uploads.php' ? 'active' : '' ?>">Images</a>
</nav>

<script>
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
</script>