<?php
if (!isset($driver_id)) {
    $driver_id = (int)$_SESSION['user_id'];
}
if (!isset($driver)) {
    $stmt = $conn->prepare("SELECT user_id,name,email,phone,profile_image,verification_status FROM users WHERE user_id=? AND role='driver' LIMIT 1");
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $driver = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
$profile_image = !empty($driver['profile_image']) ? $driver['profile_image'] : 'default.png';
$stmt = $conn->prepare("SELECT profile_photo FROM driver_verification WHERE driver_id=? ORDER BY verification_id DESC LIMIT 1");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$verification = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!isset($driver_status)) {
    $driver_status = "Available";
}
$driver_photo = !empty($verification['profile_photo'])
    ? "../uploads/driver/profile/" . $verification['profile_photo']
    : "../uploads/profile/" . $profile_image;
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

<header class="header">
    <div class="logo">Driver Dashboard</div>
    <div><a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">Home</a></div>
    <div><a href="scan_ticket.php" class="<?= basename($_SERVER['PHP_SELF']) == 'scan_ticket.php' ? 'active' : '' ?>">Scan</a></div>
    <div><a href="bookings.php" class="<?= basename($_SERVER['PHP_SELF']) == 'bookings.php' ? 'active' : '' ?>">View Bookings</a></div>


    <div class="driver-profile">
        <div class="driver-info">
            <strong>Welcome,&nbsp;<?= htmlspecialchars($driver['name']) ?></strong>
        </div>
        <img src="<?= htmlspecialchars($driver_photo) ?>" class="profile-image" alt="Driver Profile" onclick="toggleProfileMenu(event)" onerror="this.onerror=null;this.src='../uploads/default.png';">
        <div class="profile-menu" id="profileMenu">
            <a href="profile.php"><i class="fa fa-user-circle"></i> My Profile</a>
            <a href="driver_verification.php"><i class="fa fa-check-circle"></i> Verification</a>
            <a href="my_bus.php"><i class="fa fa-bus"></i> My Bus</a>
            <a href="trips.php"><i class="fa fa-road"></i> My Trips</a>
            <a href="../changepassword.php"><i class="fa fa-key"></i> Change Password</a>
            <hr>
            <div class="menu-divider"></div>
            <a href="../logout.php" class="logout-link"><i class="fa fa-sign-out"></i> Logout</a>
        </div>
    </div>

</header>

<script>
    function toggleProfileMenu(e) {
        e.stopPropagation();
        const menu = document.getElementById("profileMenu");
        menu.classList.toggle("active");
    }

    document.addEventListener("click", function(e) {
        const profile = document.querySelector(".driver-profile");
        const menu = document.getElementById("profileMenu");

        if (profile && menu && !profile.contains(e.target)) {
            menu.classList.remove("active");
        }
    });
</script>